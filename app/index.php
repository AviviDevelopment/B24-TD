<?
require_once("tools.php");

$url = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
$arPortal = $db->getRow("SELECT * FROM `b24_portal_reg` WHERE `PORTAL` = '{$url}'");

?>
<!doctype html>
<html lang="ru">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Синхронизация TimeDoctor и Bitrix24</title>

    <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script src="//api.bitrix24.com/api/v1/"></script>    
    <script type="text/javascript" src="js/application.js"></script>

    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="//maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css" rel="stylesheet">
    <link href="css/material.min.css" rel="stylesheet">
    <link href="css/ripples.min.css" rel="stylesheet">
    <link href="css/application.css" rel="stylesheet">    


</head>
<body>
    <div id="app" class="container-fluid" > 
        <div class="bs-callout bs-callout-danger">
            <img src="images/timedoctor.png" style="width: 75px;float: left;margin-left: -15px;margin-top: -25px;"/>    
            <h4 id ="tapp">Установка приложения "Time Doctor"</h4>
        </div>    
        <div class="alert alert-dismissable alert-warning hidden" id="error"></div>
        
        <div class="bs-callout">
            <p id ="tTD">Введите Client Id и Secret Key с  <a  href="https://webapi.timedoctor.com/app" target="_blank">кабинета Time Doctor</a></p>
        </div>
        <div>
            <input type="text" placeholder="Client Id" value="<?=$arPortal['TD_CLIENT_ID']?>" id="TD_client_id" style="width:100%;border: 1px solid #eee;padding-left: 20px;"/>
        </div>
        <div>
            <input type="text" placeholder="Secret Key" value="<?=$arPortal['TD_SECRET_KEY']?>" id="TD_secret_key" style="width:100%;border: 1px solid #eee;padding-left: 20px;"/>
        </div>

        <div class="bs-callout">
            <p id ="tB24">Введите client_id и client_secret с <a  href="https://<?=$url?>/marketplace/local/list/" target="_blank">настроек приложения в Bitrix24</a></p>
        </div>
        <div>
            <input type="text" placeholder="client_id" value="<?=$arPortal['B_CLIENT_ID']?>" id="B24_client_id" style="width:100%;border: 1px solid #eee;padding-left: 20px;"/>
        </div>
        <div>
            <input type="text" placeholder="client_secret" value="<?=$arPortal['B_CLIENT_SECRET']?>" id="B24_client_secret" style="width:100%;border: 1px solid #eee;padding-left: 20px;"/>
        </div>    

        <div class="bs-callout">
            <p id ="tprTD">Введите название стандартного проекта для Time Doctor</p>
        </div>
        <div>
            <input type="text" placeholder="B24 tasks" value="<?=$arPortal['TD_PROJECT_NAME']?>" id="TD_project_name" style="width:100%;border: 1px solid #eee;padding-left: 20px;"/>
        </div>
        <div>
            <a href="javascript:void(0);" id="save-btn" onclick="app.finishInstallation();" class="btn btn-success btn-raised"><i class="fa fa-check"></i> сохранить<div class="ripple-wrapper"></div></a>
        </div>

    </div>
</body>
</html>