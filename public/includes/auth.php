<?php
/** Authentication through existing database accounts. No automatic or universal login. */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/totp.php';

function auth_check() {
    global $pdo;
    if(session_status()===PHP_SESSION_NONE)session_start();
    if(empty($_SESSION['authenticated'])||empty($_SESSION['user_id'])){security_forget_auth();return false;}
    if(time()-(int)($_SESSION['auth_seen']??0)>1800||time()-(int)($_SESSION['auth_started']??0)>43200){security_forget_auth();return false;}
    if(!isset($GLOBALS['_auth_user'])){
        $stmt=$pdo->prepare('SELECT id,username,password,fullname,role,status,shop_id FROM users WHERE id=?');$stmt->execute([(int)$_SESSION['user_id']]);$row=$stmt->fetch();
        if(!$row||!access_account_available($row)||!hash_equals((string)($_SESSION['auth_version']??''),security_user_version($row['id']))||!hash_equals((string)($_SESSION['auth_epoch']??''),security_epoch())||!hash_equals((string)($_SESSION['auth_fingerprint']??''),hash('sha256',$row['password']))){security_forget_auth();return false;}
        unset($row['password']);$GLOBALS['_auth_user']=$row;
        $_SESSION['user_role']=$row['role'];$_SESSION['user_fullname']=$row['fullname'];
    }
    $_SESSION['auth_seen']=time();
    ui_apply_language_choice((int)$_SESSION['user_id']);
    return true;
}

function auth_user() {if(!auth_check())return null;$preview=preview_state();return $preview['user']??$GLOBALS['_auth_user'];}
function auth_is_admin(){ $user=auth_user();return $user&&in_array($user['role'],['admin','superadmin','super_admin'],true); }

function auth_require($requiredRole=null,$allowLocked=false) {
    $valid=auth_check();$locked=$valid&&!empty($_SESSION['screen_locked']);
    if(!$valid||($locked&&!$allowLocked)){
        $return=security_safe_return($_SERVER['REQUEST_URI']??BASE_URL.'index.php');
        $url=BASE_URL.'modules/login.php?next='.rawurlencode($return).($locked?'&locked=1':'');
        $language=$_SESSION['app_lang_pending']??null;
        if(is_string($language)&&isset(get_supported_languages()[$language]))$url.='&lang='.rawurlencode($language);
        if(access_json_request()){http_response_code(401);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'auth_required'=>true,'login_url'=>$url]);exit;}
        redirect($url);
    }
    if($requiredRole&&!(($requiredRole==='admin'&&auth_is_admin())||auth_user()['role']===$requiredRole)){access_deny(403,'role_required',__t('Access denied.','دسترسی ندارید.','لاسرسی نشته.'));}
    if(preview_active()&&($_SERVER['REQUEST_METHOD']??'GET')!=='GET')access_deny(403,'preview_read_only',__t('Preview is read-only. Exit preview to make real changes.','پیش‌نمایش فقط‌خواندنی است؛ برای تغییر واقعی از پیش‌نمایش خارج شوید.','کتنه یوازې لوستلې ده؛ د بدلون لپاره له کتنې ووځئ.'));
    security_maintenance_guard();
    access_require_password_change();
    if(domain_is_central(auth_user()) && !totp_session_valid() && !in_array(basename($_SERVER['SCRIPT_NAME']??''),['verify.php','logout.php'],true)){if(access_json_request())access_deny(403,'mfa_required','Two-step verification required.');redirect(BASE_URL.'admin/verify.php');}
    if(function_exists('shop_find')){
        $shopOwner=auth_user();$shopId=(int)($shopOwner['shop_id']??0);
        if($shopId>0&&!access_super_role((string)$shopOwner['role'])){
            $shopRow=shop_find($shopId);
            if($shopRow&&!shop_is_operational($shopRow))access_deny(403,'shop_locked',__t('This shop is locked or its subscription has ended. Ask Office 2 to renew it.','این فروشگاه قفل است یا اشتراک آن پایان یافته؛ از دفتر دو تمدید بخواهید.','دا دوکان قفل دی یا ګډون یې پای ته رسیدلی؛ له دفتر دو تمدید وغواړئ.'));
        }
    }
    domain_enforce_route();
    if(access_route_denied())access_deny(403,'feature_disabled',__t('Access to this module is disabled.','دسترسی به این بخش غیرفعال است.','دې برخې ته لاسرسی بند دی.'));
    header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
}

if (!function_exists('require_login')) {
    function require_login($requiredRole = null) {
        auth_require($requiredRole);
    }
}

function admin_auth_require() {
    require_once __DIR__.'/office2.php';
    office2_require();
}

function auth_login_pin($pin) {
    $failure=['success'=>false,'message'=>__t('Please sign in with your account first, or check the saved PIN.','ابتدا با حساب خود وارد شوید یا PIN ثبت‌شده را بررسی کنید.','لومړی خپل حساب ته ننوځئ یا ثبت شوی PIN وګورئ.')];
    if(!auth_check()||!domain_is_central(auth_user())||(int)access_profile(auth_user()['id'])['must_change_password']||!security_attempt('secondary-pin',(string)auth_user()['id']))return $failure;
    foreach(['super_admin_pin','admin_pin'] as $key){
        if(security_pin_matches($pin,get_setting($key,''))){
            $_SESSION['screen_locked']=false;
            if($key==='super_admin_pin'){$_SESSION['super_admin_verified']=true;$_SESSION['office2_version']=get_setting('office2.credential_version','0');}
            security_clear_attempts('secondary-pin',(string)auth_user()['id']);
            return ['success'=>true,'role'=>$key==='super_admin_pin'?'superadmin':auth_user()['role'],'redirect'=>BASE_URL.($key==='super_admin_pin'?'admin/office_2.php':'index.php'),'message'=>__t('Verified.','تأیید شد.','تایید شو.')];
        }
    }
    return $failure;
}

function auth_login($username,$password) {
    global $pdo;
    if(!is_string($username)||!is_string($password)||strlen($username)>100||strlen($password)>1024)return false;
    $username=trim($username);if($username===''||$password==='')return false;
    $identity=strtolower($username);
    if(!security_attempt('login-ip',$_SERVER['REMOTE_ADDR']??'local',50)||!security_attempt('login-user',$identity,6)){$GLOBALS['_auth_error']='limited';return false;}
    $stmt=$pdo->prepare('SELECT * FROM users WHERE username=?');$stmt->execute([$username]);$user=$stmt->fetch();
    if(!$user){password_verify($password,security_dummy_hash());return false;}
    $matches=security_password_matches($password,$user['password']);if(!$matches)return false;
    if(!access_account_available($user)){$GLOBALS['_auth_error']='unavailable';return false;}
    security_clear_attempts('login-user',$identity);security_establish_session($user);
    log_activity($user['id'],'Account sign-in','Authenticated using the existing password verifier.');
    return true;
}

function auth_logout() {
    if(session_status()===PHP_SESSION_NONE)session_start();
    $_SESSION=[];unset($GLOBALS['_auth_user'],$GLOBALS['_ui_preferences']);
    if(ini_get('session.use_cookies')){ $p=session_get_cookie_params();setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>true,'samesite'=>$p['samesite']??'Lax']); }
    session_destroy();
}
// The existing Office interface remains intact, behind primary account authentication.
if(str_contains($_SERVER['SCRIPT_NAME']??'','/admin/')&&!in_array(basename($_SERVER['SCRIPT_NAME']),['office_2.php','secret_panel_786.php','user_preview.php'],true))auth_require('admin');
