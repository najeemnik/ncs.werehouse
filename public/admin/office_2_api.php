<?php
/** Server-authorized API. No password/hash fields are ever serialized. */
define('OFFICE2_API',true);
require_once __DIR__.'/../includes/functions.php';require_once __DIR__.'/../includes/office2.php';
office2_require();header('Content-Type: application/json; charset=utf-8');
try{
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    if($method==='GET'){
        $view=$_GET['view']??'users';
        if($view==='users')$data=office2_users();
        elseif($view==='user')$data=office2_user_details($_GET['user_id']??'');
        elseif($view==='subscription_history'){$u=office2_target($_GET['user_id']??'');$data=office2_history($u['id'],'subscription');}
        elseif($view==='features'){$data=get_lockable_features();foreach($data as $key=>&$feature)$feature['globally_locked']=is_feature_globally_locked($key);unset($feature);}
        elseif($view==='audit')$data=office2_history();
        else throw new InvalidArgumentException('Unknown view.');
    }elseif($method==='POST'){
        $post=$_POST;
        if(str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json')){
            $body=file_get_contents('php://input',false,null,0,65537);if(strlen($body)>65536)throw new InvalidArgumentException('Request is too large.');
            $post=json_decode($body,true,10,JSON_THROW_ON_ERROR);if(!is_array($post))throw new InvalidArgumentException('Invalid request.');
        }
        $data=office2_mutate($post['action']??'',$post,$_FILES);
        if(!empty($data['sign_out']))auth_logout();
    }else{header('Allow: GET, POST');access_deny(405,'method_not_allowed','Method not allowed.');}
    echo json_encode(['success'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    $status=in_array((int)$e->getCode(),[401,403,409,429],true)?(int)$e->getCode():($e instanceof InvalidArgumentException||$e instanceof JsonException?422:500);
    access_deny($status,'office2_request_failed',$status===500?__t('The operation could not be completed safely.','عملیات به‌صورت امن تکمیل نشد.','عمل په خوندي ډول بشپړ نه شو.'):$e->getMessage());
}
