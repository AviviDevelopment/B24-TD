<?

define('APP_REG_URL', 'https://avivi.com.ua/TDoctor/app/index.php');

require_once("db.php");
require_once("log.php");
require_once('classes/bitrix24.php');
require_once('classes/bitrix24exception.php');
require_once('classes/bitrix24entity.php');
require_once('classes/bitrix24user.php');

require_once('classes/task/elapseditem.php');
require_once('classes/task/item.php');
require_once('classes/event/event.php');
require_once('classes/sonetgroup.php');
require_once('classes/TimeDoctor.php');

function prepareFromDB($arAccessParams) {
    $arResult = array();
    $arResult['domain'] = $arAccessParams['PORTAL'];
    $arResult['member_id'] = $arAccessParams['MEMBERID'];
    $arResult['refresh_token'] = $arAccessParams['B_REFRESH_TOCKEN'];
    $arResult['access_token'] = $arAccessParams['B_ACCESS_TOCKEN'];
    $arResult['td_access_token'] = $arAccessParams['TD_ACCESS_TOCKEN'];

    $arResult['B24clientId'] = $arAccessParams['B_CLIENT_ID'];
    $arResult['B24clientSecret'] = $arAccessParams['B_CLIENT_SECRET'];

    return $arResult;
}

function getBitrix24 (&$arAccessData, &$btokenRefreshed, &$errorMessage, $arScope=array()) 
{
    
    \CB24Log::Add('getBitrix24');
    $btokenRefreshed = null;
    $obB24App = new \Bitrix24\Bitrix24();
    if (!is_array($arScope)) {
        $arScope = array();
    }
    if (!in_array('user', $arScope)) {
        $arScope[] = 'user';
    }
    $obB24App->setApplicationScope($arScope);
    $obB24App->setApplicationId($arAccessData['B24clientId']);
    $obB24App->setApplicationSecret($arAccessData['B24clientSecret']);
 
    $obB24App->setDomain($arAccessData['domain']);
    $obB24App->setMemberId($arAccessData['member_id']);
    $obB24App->setRefreshToken($arAccessData['refresh_token']);
    $obB24App->setAccessToken($arAccessData['access_token']);

    try {
        $resExpire = $obB24App->isAccessTokenExpire();
    }
    catch(\Exception $e) {
        $errorMessage = $e->getMessage();
    }

    if ($resExpire) {

        $obB24App->setRedirectUri(APP_REG_URL);

        try 
        {
            $result = $obB24App->getNewAccessToken();
        }
        catch(\Exception $e) 
        {
            $errorMessage = $e->getMessage();
        }
        if ($result === false) 
        {
            $errorMessage = 'access denied';
        }
        elseif (is_array($result) && array_key_exists('access_token', $result) && !empty($result['access_token'])) 
        {
            $arAccessData['refresh_token']=$result['refresh_token'];
            $arAccessData['access_token']=$result['access_token'];
            $obB24App->setRefreshToken($arAccessData['refresh_token']);
            $obB24App->setAccessToken($arAccessData['access_token']);
            $btokenRefreshed = true;
        }
        else {
            $btokenRefreshed = false;
        }
    }
    else {
        $btokenRefreshed = false;
    }
    \CB24Log::Add('getBitrix24_END'); 
    return $obB24App;
}

$settings = array(
    'host' => 'localhost',
    'user' => 'ct24708_timedoc',
    'pass' => 'fQBZirRO',
    'db' => 'ct24708_timedoc',
    'charset' => 'utf8',
);

global $db;
$db = new SafeMySQL($settings);
