<?php
/** Temporary compatibility alias. Authorization is always performed by Office 2. */
require_once __DIR__.'/../includes/functions.php';require_once __DIR__.'/../includes/auth.php';
if(auth_check()&&!auth_is_admin())access_deny(403,'office2_required',__t('Access denied.','دسترسی ندارید.','لاسرسی نشته.'));
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    require __DIR__.'/office_2.php';
}else{
    $query=[];foreach(['tab','user_id','lang'] as $key)if(isset($_GET[$key])&&is_string($_GET[$key]))$query[$key]=$_GET[$key];
    redirect(BASE_URL.'admin/office_2.php'.($query?'?'.http_build_query($query):''));
}
