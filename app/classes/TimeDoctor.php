<?php
namespace TimeDoctor;

const API_HOST = "https://webapi.timedoctor.com";
const APP_NAME = "B24 integration";

class TimeDoctor
{

	public $auth;

    /**
     * получаем новые параметры для авторизации - access_token и refresh_token
     */
	public function __construct($arPortal, &$errorMessage)
	{
		// \CB24Log::Add('TD arPortal - '.print_r($arPortal, true));
       if($arPortal['TD_CLIENT_ID'] && $arPortal['TD_SECRET_KEY'] && $arPortal['TD_REFRESH_TOCKEN'])
        {

            $tempUrlTD = "/oauth/v2/token?client_id={$arPortal['TD_CLIENT_ID']}&client_secret={$arPortal['TD_SECRET_KEY']}&grant_type=refresh_token&refresh_token={$arPortal['TD_REFRESH_TOCKEN']}";
			$arResult = $this->curlRequestSingle($tempUrlTD); 

            if($arResult['access_token'])
            {
            	global $db;
            	$access_token = $arResult['access_token'];
            	$refresh_token = $arResult['refresh_token'];
            	$portal = $arPortal['PORTAL'];
            	$companyID = $arPortal['TD_COMPANY'];
            	$adminID = $arPortal['TD_ADMIN_ID'];
                $resSet = $db->query("UPDATE `b24_portal_reg` SET `TD_ACCESS_TOCKEN` = '{$access_token}', `TD_REFRESH_TOCKEN` = '{$refresh_token}', `TD_COMPANY` = {$companyID}, `TD_ADMIN_ID` = {$adminID}  WHERE `PORTAL` LIKE '{$portal}'");
                // \CB24Log::Add('TD resSet - '.print_r($resSet, true));
                if (!$resSet)
                {
                	$errorMessage = "DB error in TimeDoctor.php!";
                }
	            $this->auth = $db->getRow("SELECT * FROM `b24_portal_reg` WHERE `PORTAL` = '{$portal}'");
            }
            else
            {
            	$errorMessage = "Time Doctor auth error!";
            }

        }
	}

    /**
     * Инициализирует сеанс cURL для одного URL
     * если указан метод передачи данных, то отправляем данные указаным методом
     */
	public function curlRequestSingle($url, $method = false, $postFields = false)
	{
	    $curl = curl_init();
	    curl_setopt_array($curl, array(
	        CURLOPT_RETURNTRANSFER => 1,
	        CURLOPT_URL => API_HOST.$url,
	        CURLOPT_USERAGENT => APP_NAME
	    ));
	    if ($method){

	    	$data_string = json_encode($postFields);			
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
	    	curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
	    	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	    }

	    $resp = curl_exec($curl);
	    curl_close($curl);
	    $result = $resp ? json_decode($resp) : null ;
	    $result = get_object_vars($result);
	    return $result;
	}	

    /**
     * получаем пользователей ТД
     * если передали $email то возвращает одного пользователя по email
     */
	public function getUsers($email = false)
	{
        $tempUrlTD = '/v1.1/companies/'.$this->auth['TD_COMPANY'].'/users?access_token='.$this->auth['TD_ACCESS_TOCKEN'].'&_format=json';
        $aruserTd = $this->curlRequestSingle($tempUrlTD);

        if ($email)
        {
	        $TDuser = false;
	        foreach ($aruserTd['users'] as $user) 
	        { 
	            if ($email == $user->email)
	                $TDuser = get_object_vars($user);  
	        }		
			
			return $TDuser;
		}
		else
		{
			return $aruserTd['users'];
		}
	}

    /**
     * получаем пользователя ТД по ID
     */
	public function getUser($ID)
	{
        $tempUrlTD = '/v1.1/companies/'.$this->auth['TD_COMPANY'].'/users/'.$ID.'?access_token='.$this->auth['TD_ACCESS_TOCKEN'].'&_format=json';
        $aruserTd = $this->curlRequestSingle($tempUrlTD);

        return $aruserTd;
	}		

	/**
     * getting Worklogs for today
     * @param string $arTasks - array with task IDS
	 * @return array with task logged time, in seconds
     */
	public function getWorkLogs($tasks, $offset = 1, $limit = 0)
	{	
		$arWorkLogs = array();
		$startDay = date('Y')."-01-01";
		$today = date('Y-m-d');

        $tempUrlTD = '/v1.1/companies/'.$this->auth['TD_COMPANY'].'/worklogs?access_token='.$this->auth['TD_ACCESS_TOCKEN'].'&_format=json&start_date='.$startDay.'&end_date='.$today.'&task_ids='.$tasks.'&offset='.$offset.'&limit='.$limit;
        $arWorkLogs = $this->curlRequestSingle($tempUrlTD);

        return $arWorkLogs;
	}	

    /**
     * если есть $projectName, то возвращает проект по названию
     * иначе возвращает все проекты пользоваетля
     */
	public function getProjects($userID, $projectName = false)
	{
        $tempUrlTD = '/v1.1/companies/'.$this->auth['TD_COMPANY'].'/users/'.$userID.'/projects?access_token='.$this->auth['TD_ACCESS_TOCKEN'].'&_format=json';
        $arprojectsTd = $this->curlRequestSingle($tempUrlTD);

        if ($projectName)
        {
	        $TDproject = false;
	        foreach ($arprojectsTd['projects'] as $project) 
	        { 
				$projectTemp = get_object_vars($project);
				if (($projectName == $projectTemp['project_name']) && (APP_NAME== $projectTemp['project_source']))
					$TDproject = $projectTemp;
	        }		
			
			return $TDproject;
		}
		else
		{
			return $arprojectsTd['projects'];
		}

	}	

    /**
     * получаем проект ТД по ID проекта и ID пользователя
     */
	public function getProject($ID, $userID)
	{
        $tempUrlTD = '/v1.1/companies/'.$this->auth['TD_COMPANY'].'/users/'.$userID.'/projects/'.$ID.'?access_token='.$this->auth['TD_ACCESS_TOCKEN'].'&_format=json';
        $arprojectTd = $this->curlRequestSingle($tempUrlTD);

        return $arprojectTd;
	}		

    /**
     * создание нового проекта пользователя
     */
	public function newProject($userID, $projectName)
	{
        $tempUrlTD = '/v1.1/companies/'.$this->auth['TD_COMPANY'].'/users/'.$this->auth['TD_ADMIN_ID'].'/projects?access_token='.$this->auth['TD_ACCESS_TOCKEN'].'&_format=json';
        $projectFields = array(
        	'assign_users' => '{$userID}',
        	'project' => array('project_name' => $projectName)
        	);
        $projectTd = $this->curlRequestSingle($tempUrlTD, 'POST', $projectFields);

		return $projectTd;
	}	

    /**
     * редактирование проекта пользователя
     */
	public function editProject($userID, $projectID, $projectName)
	{
        $tempUrlTD = "/v1.1/companies/{$this->auth['TD_COMPANY']}/users/{$userID}/projects/{$projectID}?access_token={$this->auth['TD_ACCESS_TOCKEN']}&_format=json";
        $putFields = array('project' => array('project_name' => $projectName));
        $projectTd = $this->curlRequestSingle($tempUrlTD, 'PUT', $putFields);

		return $projectTd;
	}	

    /**
     * получаем задачу ТД по ID
     */
	public function getTask($ID, $userID)
	{
        $tempUrlTD = '/v1.1/companies/'.$this->auth['TD_COMPANY'].'/users/'.$userID.'/tasks/'.$ID.'?access_token='.$this->auth['TD_ACCESS_TOCKEN'].'&_format=json';
        $artaskTd = $this->curlRequestSingle($tempUrlTD);

        return $artaskTd;
	}			

    /**
     * создание новой задачи для пользователя в ТД
     */
	public function newTask($userID, $newValues = false)
	{

        $tempUrlTD = '/v1.1/companies/'.$this->auth['TD_COMPANY'].'/users/'.$userID.'/tasks?access_token='.$this->auth['TD_ACCESS_TOCKEN'].'&_format=json';
 		
 		if ($newValues)
 		{
	 		$postFields = array('task' => $newValues);
	        $TDtask = $this->curlRequestSingle($tempUrlTD, 'POST', $postFields);
    	}
    	else
    		$TDtask = $this->curlRequestSingle($tempUrlTD);	
		return $TDtask;
	}		

    /**
     * редактирование задачи для пользователя в ТД
     * $newValues - массив новых значений для задачи
	 * $newValues = array(
	 * 		'task_name' => $taskName,
	 *    	'project_id' => $projectID,			
	 *     	'user_id' => $userID,			
	 *     	'active' => true,			
	 *     	'task_link' => 'url...'			
	 * 	);     
     */
	public function editTask($oldUserID, $taskID, $newValues)
	{

        $tempUrlTD = '/v1.1/companies/'.$this->auth['TD_COMPANY'].'/users/'.$oldUserID.'/tasks/'.$taskID.'?access_token='.$this->auth['TD_ACCESS_TOCKEN'].'&_format=json'; 
 		$putFields = array('task' => $newValues);
        $TDtask = $this->curlRequestSingle($tempUrlTD, 'PUT', $putFields);

		return $TDtask;
	}	

    /**
     * деактивация задачи для пользователя в ТД
     */
	public function deactiveTask($oldUserID, $taskID, $title)
	{
        $tempUrlTD = '/v1.1/companies/'.$this->auth['TD_COMPANY'].'/users/'.$oldUserID.'/tasks/'.$taskID.'?access_token='.$this->auth['TD_ACCESS_TOCKEN'].'&_format=json';
		$newValues = array(
			'task_name' => $title,		
	    	'active' => false,				            			
			);	 
 		$putFields = array('task' => $newValues);
        $TDtask = $this->curlRequestSingle($tempUrlTD, 'PUT', $putFields);

		return $TDtask;
	}			
}