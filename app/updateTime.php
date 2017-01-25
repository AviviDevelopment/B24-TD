<?
/**
 * If we need DOCUMENT ROOT path - use a variable
 * CRON doesn't know what $_SERVER["DOCUMENT_ROOT"] is
 */
$site_path="/home/c/ct24708/avivi_ua/public_html";
$_SERVER["DOCUMENT_ROOT"] = $site_path;
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

/**
 * PHP parameters to fine work by CRON tab 
 * 
 * Making more execution time
 * More memmory limit 
 * No time limit
 * Error reportimg = all
 */

ini_set('max_execution_time', 1800);
ini_set('memory_limit', '2000M');
set_time_limit(0);
//error_reporting(E_ALL);

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);


require_once("tools.php");
global $db;


$application = new CUpdateTasksTime($db);
$application->start();

/**
 * Class which main meta is updating tasks time in B24 portal. 
 * 
 * Doing loop for each portal, getting its tasks
 * If there are tasks - making B24 auth and TD auth
 * Getting Worklogs from TD (limit is 500 records for one request, use offset to change page)
 * If there's a B24 record of time - just update it
 * If no record - create it, update task data in DB with record ID
 */
class CUpdateTasksTime
{
	private $db;
	public $arPortals;
	public $defaultComment = "TimeDoctor";

	/**
	 * Making DB class variable
	 * Getting list of portals 
	 * @param array $db - DB connection
	 */
	public function __construct($db)
	{	
		$this->db = $db;
		$this->arPortals = $this->db->getAll("SELECT * FROM `b24_portal_reg` WHERE 
			`TD_REFRESH_TOCKEN` IS NOT NULL 
				AND `TD_ACCESS_TOCKEN` IS NOT NULL");
	}

	/**
	 * Getting list of portal's tasks 
	 * TD task ID using as a key
	 * @param string $portal - portal name fdrom DB
	 * @return array empty or with tasks, TD task ID as a key of each task
	 */
	private function getTasksByPortal($portal)
	{
		$arTasks = array();
		$arTasks = $this->db->getAll("SELECT * FROM `b24_tasks` WHERE `PORTAL` = '{$portal}'");
		if($arTasks)
		{
			$arTemp = array();
			foreach ($arTasks as $key => $arTask) 
				$arTemp[$arTask["TD_TASK_ID"]] = $arTask;	
			$arTasks = $arTemp;
		}
		return $arTasks;
	}

	/**
	 * Doing loop for each task
	 * See, whether there's already record of time from TD in DB
	 * If it is - update it, if no - create new record and update task record in DB with time record ID
	 *
	 * TD returns time in seconds
	 * @param array $arWorklogs - array of tasks with time from TD
	 * @param array $arPortalTasks - array of portal tasks with B24 and TD data
	 * @param object $B24ElapsedTime - object of \Bitrix24\Task\ElapsedItem class, for calling ElapsedItem methods, e.g. add record or update record 
	 * @param string $domain - portal name/address
	 */
	private function updateTasksTime($arWorklogs, $arPortalTasks, $B24ElapsedTime, $domain)
	{
		foreach ($arWorklogs as $key => $arWorklog) 
		{
			$arWorklog = (array) $arWorklog;
			if($arWorklog["length"])
			{
				$currTime = date("Y-m-d H:i:s");
				if(isset($arPortalTasks[$arWorklog["task_id"]]))
				{
					$arPortalTask = $arPortalTasks[$arWorklog["task_id"]];
					if($arPortalTask["B24_TIME_RECORD_ID"])
					{
						$recordData = array();
						$recordData = $B24ElapsedTime->update($arPortalTask["B_TASK_ID"], $arPortalTask["B24_TIME_RECORD_ID"], 
							array("SECONDS" => $arWorklog["length"], "COMMENT_TEXT" => $this->defaultComment, "CREATED_DATE" => $currTime));
					}
					else
					{
						$recordData = array();
						$recordData = $B24ElapsedTime->add($arPortalTask["B_TASK_ID"], 
							array("SECONDS" => $arWorklog["length"], "COMMENT_TEXT" => $this->defaultComment, "CREATED_DATE" => $currTime));
						if($recordData["result"])
							$res = $this->db->query("UPDATE `b24_tasks` SET `B24_TIME_RECORD_ID` = {$recordData["result"]} 
								WHERE `B_TASK_ID` = {$arPortalTask["B_TASK_ID"]} AND `PORTAL` = '{$domain}'");
					}
				}

			}
		}
	}

	/**
	 * Starting update proccess
	 */
	public function start()
	{
		foreach ($this->arPortals as $key => $portal)
		{
			$portalName = $portal["PORTAL"];
			$arPortalTasks = array();
			$arPortalTasks = self::getTasksByPortal($portalName);
			/** If there are tasks in portal */
			if($arPortalTasks)
			{
				$BXerror = '';
				$isTokenRefreshed = false;
				$portalData = array();
				$B24Access = array();
				$portalData = prepareFromDB($portal);

				/** Bitrix24 auth */
				$B24Access = getBitrix24($portalData, $isTokenRefreshed, $BXerror);
				if(!$BXerror)
				{
					/** TD auth */
					$TDerror = '';
					$TDAccess = array();
					$TDAccess = new TimeDoctor\TimeDoctor($portal, $TDerror);
					if(!$TDerror)
					{
						$limitPerPage = 500;
						$offset = 1;
						$totalTasksCount = 0;

						$sTasks = '';
						$arWorklogs = array();

						foreach ($arPortalTasks as $TDid => $arTask) 
							$sTasks .= $arTask["TD_TASK_ID"].",";		
						$sTasks = substr($sTasks, 0, -1);

						/** Getting Worklogs */
						$arWorklogs = $TDAccess->getWorkLogs($sTasks, $offset, $limitPerPage);

						if($arWorklogs['worklogs']->items)
						{
							$domain = '';
							$domain = $B24Access->getDomain();
							$B24ElapsedTime = new \Bitrix24\Task\ElapsedItem($B24Access);
							self::updateTasksTime((array) $arWorklogs['worklogs']->items, $arPortalTasks, $B24ElapsedTime, $domain);
							$totalTasksCount = $arWorklogs['worklogs']->count;

							if($totalTasksCount > $limitPerPage)
							{
								while ($totalTasksCount > $offset)
								{
								    $offset += $limitPerPage;
									$arWorklogs = array();
									$arWorklogs = $TDAccess->getWorkLogs($sTasks, $offset, $limitPerPage);
									if($arWorklogs['worklogs']->items)
										self::updateTasksTime((array) $arWorklogs['worklogs']->items, $arPortalTasks, $B24ElapsedTime, $domain);
								}
							}
						}
					}	
				}
			}
		}
	}

}