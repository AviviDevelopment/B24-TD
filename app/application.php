<?php

require_once("tools.php");

$application = new CApplication();

if (!empty($_REQUEST)) 
{
    CB24Log::Add('_REQUEST :'.print_r($_REQUEST,true));
    $application->start();
    $application->manageAjax($_REQUEST['operation'], $_REQUEST);
}

/**
 * Class which main eta is adding and handling Bitrix24 events and combine them with TimeDoctor functional
 */
class CApplication
{
    public $arB24App;
    public $TDObject;
    public $arAccessParams = array();
    private $b24_error = '';
    private $td_error = '';
    public $is_ajax_mode = false;

    /**
     * Bitrix24 and TimeDoctor auth
     */
    public function start () 
    {
        CB24Log::Add('function - start');
		global $db;
        $this->is_ajax_mode = isset($_REQUEST['operation']);
        $domain = ($_REQUEST['operation'] == "portaladd") ? $_REQUEST['domain'] : $_REQUEST['auth']['domain'];
        $res = $db->getRow("SELECT * FROM `b24_portal_reg` WHERE `PORTAL` = '{$domain}'");
		$this->arAccessParams = ($_REQUEST['operation'] == 'portaladd') ? $_REQUEST : prepareFromDB($res);

        $this->b24_error = $this->checkB24Auth();

        if ($this->b24_error != '') 
        {
            CB24Log::Add('B24_error'.print_r($this->b24_error, true));
            die;
        }

        $this->TDObject = new TimeDoctor\TimeDoctor($res, $this->td_error);

        if ($this->td_error != '') 
        {
            CB24Log::Add('TD Error - '.print_r($this->td_error, true));
            die;
        }

        CB24Log::Add('Auth success!');
    }

    /**
     * Checking auth expiration in Bitrix24 
     */
    public function checkB24Auth() {

        CB24Log::Add('function - checkB24Auth');
        $isTokenRefreshed = false;

        $this->arB24App = getBitrix24($this->arAccessParams, $isTokenRefreshed, $this->b24_error);
        CB24Log::Add('checkB24Auth - arB24App'.print_r($this->arB24App, true));
        CB24Log::Add('function - checkB24Auth END');
        return $this->b24_error === true;
    }

    /**
     * Handling Bitrix24 events
     * @param string $operation - event, ehich should be handle
     * @param array $params - array of portal data/answer   
     */
    public function manageAjax($operation, $params)
    {
        CB24Log::Add('function - manageAjax');

        global $db;

        switch ($operation){
            case 'portaladd':              

                $this->saveAuth($params['TDclientId'], $params['TDsecretKey'], $params['TDprojectName'], $params['B24clientId'], $params['B24clientSecret']);  

                $b24Event = new Bitrix24\Event\Event($this->arB24App);
                $allEvents = $b24Event->get();

                if (empty($allEvents['result']))
                {
                    $arEvents = Array(
                        "OnAppUninstall" => "https://avivi.com.ua/TDoctor/app/application.php?operation=uninstall",
                        "OnTaskAdd" => "https://avivi.com.ua/TDoctor/app/application.php?operation=taskadd",
                        "OnTaskUpdate" => "https://avivi.com.ua/TDoctor/app/application.php?operation=taskupdate",
                        "OnTaskDelete" => "https://avivi.com.ua/TDoctor/app/application.php?operation=taskdelete",
                        );
                    foreach ($arEvents as $event => $handler)
                        $addEvent = $b24Event->bind($event, $handler);
                }

                CB24Log::Add('portaladd');
            break;

            case 'taskadd':

                $B24ItemObject = new \Bitrix24\Task\Item($this->arB24App);
                $B24task = $B24ItemObject->getData($params['data']['FIELDS_AFTER']['ID']);

                $this->newTask($B24task);                            
                
                CB24Log::Add('taskadd');
            break;    

            case 'taskupdate':
                CB24Log::Add('TDObject'.print_r($this->TDObject, true));

                $domain = $this->arB24App->getDomain();                

                $B24ItemObject = new \Bitrix24\Task\Item($this->arB24App);
                $B24task = $B24ItemObject->getData($params['data']['FIELDS_AFTER']['ID']);

                $DBtask = $db->getRow("SELECT * FROM `b24_tasks` WHERE `B_TASK_ID` = {$B24task['ID']} AND `PORTAL` LIKE '{$domain}'"); 

                /** No task in DB */
                if (!$DBtask)
                {
                	$this->newTask($B24task);
                	die();
                }

                $oldTDtask = $this->TDObject->getTask($DBtask['TD_TASK_ID'], $DBtask['TD_USER_ID']);

                $areChanges = false;

                $active = (($B24task['REAL_STATUS'] == '4') || ($B24task['REAL_STATUS'] == '5')) ? false: true;
                $oldActive = $oldTDtask['active'];

                /** task status changed */
                if ($active != $oldActive)
                {	                	
	                if (!$active)
	                {
	            		$TDtask = $this->TDObject->deactiveTask($DBtask['TD_USER_ID'], $DBtask['TD_TASK_ID'], $B24task['TITLE']);
                        CB24Log::Add('Task deactivated'.print_r($B24task, true));
	                	die();
	                } 
	                else 
                    {
	                	$areChanges = true;
                    }
                }

                /** Responsible person changed */
                if ($B24task['RESPONSIBLE_ID'] != $DBtask['B_USER_ID'])
                {
                	$areChanges = true;
	                
	                $B24UserObject = new Bitrix24\Bitrix24User\Bitrix24User($this->arB24App); 
	                $B24user = $B24UserObject->get("ID", "DESC", array("ID" => $B24task['RESPONSIBLE_ID']));

	                $TDuser = $this->TDObject->getUsers($B24user['EMAIL']);

	                if (!$TDuser)
	                {
	            		$TDtask = $this->TDObject->deactiveTask($DBtask['TD_USER_ID'], $DBtask['TD_TASK_ID'], $B24task['TITLE']);
                        CB24Log::Add('Task deactivated'.print_r($B24task, true));
	                	die();
	                }
                }
                else 
                {
                	$TDuser = $this->TDObject->getUser($DBtask['TD_USER_ID']);
                }

                /** Task project changed */
                if ($B24task['GROUP_ID'] != $DBtask['B_PROJECT_ID'])
                {
                	$areChanges = true;    
					$TDproject = $this->getProject($B24task, $TDuser);     	
                } 
                else
                {
                	$TDproject = $this->TDObject->getProject($DBtask['TD_PROJECT_ID'],$TDuser['user_id']);
                }

            	if ($areChanges)
            	{ 
					CB24Log::Add('There are Changes in task');  
            		$newValues = array(
						'task_name' => $B24task['TITLE'],
			  		   	'project_id' => $TDproject['project_id'],			
		  		    	'user_id' => $TDuser['user_id'],			
		  		    	'active' => $active,			
		  		    	'task_link' => "https://{$domain}/company/personal/user/{$B24task['RESPONSIBLE_ID']}/tasks/task/view/{$B24task['ID']}/"		            			
            			);
            		$TDtask = $this->TDObject->editTask($DBtask['TD_USER_ID'], $DBtask['TD_TASK_ID'], $newValues);

                    if ($TDtask)
                    {           	
                        $res = $db->query("UPDATE `b24_tasks` SET `B_PROJECT_ID` = {$B24task['GROUP_ID']}, `B_USER_ID` = {$B24task['RESPONSIBLE_ID']}, `TD_PROJECT_ID` = {$TDtask['project_id']},  `TD_USER_ID` = {$TDtask['user_id']}  WHERE `B_TASK_ID` = {$DBtask['B_TASK_ID']} AND `PORTAL` LIKE '{$domain}'");
                    }
            	}                  

                CB24Log::Add('taskupdate');
            break;

            case 'taskdelete':

                $domain = $this->arB24App->getDomain();                
                $taskID = $params['data']['FIELDS_BEFORE']['ID'];
                $DBtask = $db->getRow("SELECT * FROM `b24_tasks` WHERE `B_TASK_ID` = {$taskID}  AND PORTAL LIKE '{$domain}'"); 

                if ($DBtask)
                {
	                $oldTDtask = $this->TDObject->getTask($DBtask['TD_TASK_ID'], $DBtask['TD_USER_ID']);
	        		$TDtask = $this->TDObject->deactiveTask($DBtask['TD_USER_ID'], $DBtask['TD_TASK_ID'], $oldTDtask['task_name']);
                    
                    $res = $db->query("DELETE FROM `b24_tasks` WHERE `B_TASK_ID` = {$taskID}");
                    if ($res)
                        CB24Log::Add('taskdelete');
                }
                else
                {
	                CB24Log::Add('not deleted');	
                }

            break;

            case 'uninstall':
                CB24Log::Add('uninstall : '.print_r($_REQUEST, true));
                break;

            default:
                CB24Log::Add('unknown operation');
        }
    }

    /**
     * Saving data from app to DB
     * Calling for new portal adding, operation = portaladd
     *
     * @param string $TDclientId - client Id from TimeDoctor
     * @param string $TDsecretKey - secret key from TimeDoctor
     * @param string $TDprojectName -  name of standart project in TimeDoctor
     * @param string $B24clientId -  client Id for B24 application
     * @param string $B24clientSecret -  client Secret for B24 application
     */
    public function saveAuth($TDclientId, $TDsecretKey, $TDprojectName, $B24clientId, $B24clientSecret) 
    {
        CB24Log::Add('function - saveAuth'); 
        global $db;

    	$domain = $this->arB24App->getDomain();
    	$accessToken = $this->arB24App->getAccessToken();
    	$refreshToken = $this->arB24App->getRefreshToken();
    	$memberId = $this->arB24App->getMemberId();

        if (!empty($this->TDObject->auth) && ($this->TDObject->auth['TD_PROJECT_NAME'] != $TDprojectName) && ($this->TDObject->auth['TD_PROJECT_ID'] != 0))
        {
            $users = $this->TDObject->getUsers();
            $userID = $users[0]->user_id;
            $TDproject = $this->TDObject->editProject($userID, $this->TDObject->auth['TD_PROJECT_ID'], $TDprojectName);
        }
        
        $res = $db->query(
            'INSERT INTO b24_portal_reg (PORTAL, B_ACCESS_TOCKEN, B_REFRESH_TOCKEN, B_CLIENT_ID,	B_CLIENT_SECRET, MEMBERID, TD_CLIENT_ID, TD_SECRET_KEY, TD_PROJECT_NAME) values (?s, ?s, ?s, ?s, ?s, ?s, ?s, ?s, ?s)'.
            ' ON DUPLICATE KEY UPDATE B_ACCESS_TOCKEN = ?s, B_REFRESH_TOCKEN = ?s, B_CLIENT_ID = ?s, B_CLIENT_SECRET = ?s, MEMBERID = ?s, TD_CLIENT_ID = ?s, TD_SECRET_KEY = ?s, TD_PROJECT_NAME = ?s',
            $domain, $accessToken, $refreshToken, $B24clientId, $B24clientSecret, $memberId, $TDclientId, $TDsecretKey, $TDprojectName,
            $accessToken, $refreshToken, $B24clientId, $B24clientSecret, $memberId, $TDclientId, $TDsecretKey, $TDprojectName
        );
    }

    /**
     * Creating new task in TimeDoctor, copy of Bitrix24 task
     * Checking whether Bitrix24 user exists in TimeDoctor (checking by e-mail)
     * If exists - making task for this user
     *
     * @param array $B24task - array of B24 task
     */
	private function newTask($B24task) 
	{ 
        CB24Log::Add('function - newTask');  

		global $db;

        $B24UserObject = new Bitrix24\Bitrix24User\Bitrix24User($this->arB24App); 
        $B24user = $B24UserObject->get("ID", "DESC", array("ID" => $B24task['RESPONSIBLE_ID']));

        $TDuser = $this->TDObject->getUsers($B24user['EMAIL']);

        if ($TDuser)
        {
        	$TDproject = $this->getProject($B24task, $TDuser);            

            $newValues = array(
                        'task_name' => $B24task['TITLE'],
                        'project_id' => $TDproject['project_id'],         
                        'user_id' => $TDuser['user_id'],          
                        'active' => true,            
                        'task_link' => "https://{$this->TDObject->auth['PORTAL']}/company/personal/user/{$B24task['RESPONSIBLE_ID']}/tasks/task/view/{$B24task['ID']}/"                             
                        );            
            $TDtask = $this->TDObject->newTask($TDuser['user_id'], $newValues);
            
            if ($TDtask)
            {
                $res = $db->query("INSERT INTO b24_tasks ( B_TASK_ID, B_PROJECT_ID, B_USER_ID, TD_TASK_ID, TD_PROJECT_ID,   TD_USER_ID, PORTAL) VALUES ({$B24task['ID']}, {$B24task['GROUP_ID']}, {$B24task['RESPONSIBLE_ID']}, {$TDtask['task_id']}, {$TDtask['project_id']}, {$TDtask['user_id']}, '{$this->TDObject->auth['PORTAL']}')");
            }
            CB24Log::Add('Task added');
        }
        else
        {
        	CB24Log::Add('Task not added');
        }
	}

    /**
     * Getting project data from TimeDoctor
     * Checking whether task is in the group
     * If not - getting default project from settings
     * If in the group - getting same project from TD, or create it if it's not exist
     *
     * @param array $B24task - array of B24 task     
     * @param array $TDuser - array of TimeDoctor user 
     * @return array project data from TimeDoctor
     */
	private function getProject($B24task, $TDuser) 
	{ 
        CB24Log::Add('function - getProject'); 

	    if ($B24task['GROUP_ID'] == 0)
        {
            $TDproject = $this->TDObject->getProject($this->TDObject->auth['TD_PROJECT_ID'], $TDuser['user_id']);
        }
        else
        {
	    	$B24group = new Bitrix24\Sonet\SonetGroup($this->arB24App);
	    	$B24project = $B24group->get(array('NAME'=> 'ASC'), array('ID' => $B24task['GROUP_ID']));

	        $TDproject = $this->TDObject->getProjects($TDuser['user_id'], $B24project['NAME']);

			if (!$TDproject)
            {
				$TDproject = $this->TDObject->newProject($TDuser['user_id'], $B24project['NAME']);
            }
	    }     		
        CB24Log::Add('function - end getProject'); 
	    return $TDproject;
	}

}
