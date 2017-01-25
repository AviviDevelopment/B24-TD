<?php

/**
 * іFile to get CODE from Integration confirmation
 * 
 * Getting access_token and refresh_token by client_id, client_secret and code
 * Separately getting companyID and projectID
 * All received data going to DB
 */

if ($_GET['portal'])
{
    require_once('classes/TimeDoctor.php');
    require_once('db.php');
    require_once('log.php');

    $settings = array(
        'host' => 'localhost',
        'user' => 'ct24708_timedoc',
        'pass' => 'fQBZirRO',
        'db' => 'ct24708_timedoc',
        'charset' => 'utf8',
    );

    global $db;
    $db = new SafeMySQL($settings);

    $clientID = "";
    $clientSecret = "";        
    $b24PortalAddress = $_GET['portal'];
    
    $arPortal = $db->getRow("SELECT * FROM `b24_portal_reg` WHERE `PORTAL` = '{$b24PortalAddress}'");
    CB24Log::Add('arPortal'.print_r($arPortal, true));

    if (isset($_GET['code']) && !empty($arPortal)) 
    {

        $clientID = $arPortal['TD_CLIENT_ID'];
        $clientSecret = $arPortal['TD_SECRET_KEY'];

        $redirectURI = "https://avivi.com.ua/TDoctor/app/TDAuth.php?portal={$b24PortalAddress}"; 
        $code = $_GET['code'];

        $tempUrlTD = "/oauth/v2/token?client_id={$clientID}&client_secret={$clientSecret}&grant_type=authorization_code&redirect_uri={$redirectURI}&code={$code}";
        $tokens = TimeDoctor\TimeDoctor::curlRequestSingle($tempUrlTD);
        CB24Log::Add('tokens'.print_r($tokens, true));
        
        if (!$tokens['error']) 
        {
            $tempUrlTD = "/v1.1/companies?access_token={$tokens['access_token']}";
            $accounts = TimeDoctor\TimeDoctor::curlRequestSingle($tempUrlTD);
            $companyID = $accounts['accounts'][0]->company_id;

            $arPortal['TD_ACCESS_TOCKEN']  = $tokens['access_token'];
            $arPortal['TD_REFRESH_TOCKEN'] = $tokens['refresh_token'];
            $arPortal['TD_COMPANY']  = $companyID;

            $td_error = '';
            $TDObject = new TimeDoctor\TimeDoctor($arPortal, $td_error);
            
            if ($td_error != '')
            {
                CB24Log::Add('Error - '.print_r($td_error, true));
                die(); 
            }

            $users = $TDObject->getUsers();
            $userID = $users[0]->user_id;

            $projectID = $arPortal['TD_PROJECT_ID'];
            if ($projectID == 0)
            {
	            $project = $TDObject->newProject($userID, $arPortal['TD_PROJECT_NAME']);
	            $projectID = $project['project_id'];
            }

            if ($companyID)
            {
                $access_token = $TDObject->auth['TD_ACCESS_TOCKEN'];
                $refresh_token = $TDObject->auth['TD_REFRESH_TOCKEN'];
                $res = $db->query("UPDATE `b24_portal_reg` SET `TD_ACCESS_TOCKEN` = '{$access_token}', `TD_REFRESH_TOCKEN` = '{$refresh_token}', `TD_COMPANY` = {$companyID}, `TD_PROJECT_ID` = {$projectID} WHERE `PORTAL` LIKE '{$b24PortalAddress}'");
                if ($res)
                    CB24Log::Add("Auth saved!");
                header("Location: https://webapi.timedoctor.com/app");
            }
            else
            {
               CB24Log::Add("ERROR of getting companyID!");
            }
        }
    }
    else
    {
        CB24Log::Add('You have to register your clientID and clientSecret in Bitrix24 Application');
    }
}