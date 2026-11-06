<?php
require_once __DIR__.'/functions.php';
require_once __DIR__.'/auth.php';
auth_require(null,true);
header('Cache-Control: no-store');
$action=$_POST['action']??'';
function lock_response($success,$message='',$status=200){http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>$success,'message'=>$message],JSON_UNESCAPED_UNICODE);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){header('Allow: POST');lock_response(false,__t('Use a valid form request.','از درخواست معتبر فرم استفاده کنید.','د فورم معتبره غوښتنه وکاروئ.'),405);}
if(!csrf_verify($_POST['csrf_token']??''))lock_response(false,__t('Refresh the page and try again.','صفحه را تازه و دوباره تلاش کنید.','پاڼه تازه او بیا هڅه وکړئ.'),403);
if($action==='lock_screen'){$_SESSION['screen_locked']=true;lock_response(true);}
if($action==='verify_unlock'){
    $user=auth_user();$secret=$_POST['pin']??'';
    $valid=security_attempt('screen-unlock',(string)$user['id'])&&(security_pin_matches($secret,security_screen_pin())||security_current_password($secret));
    if($valid){$_SESSION['screen_locked']=false;security_clear_attempts('screen-unlock',(string)$user['id']);session_regenerate_id(true);lock_response(true);}
    lock_response(false,__t('Invalid password or saved PIN, or too many attempts.','رمز یا PIN ثبت‌شده نادرست است یا تلاش‌های زیادی انجام شده است.','رمز یا ثبت شوی PIN ناسم دی یا ډېرې هڅې شوې دي.'),422);
}
if($action==='save_screen_lock_settings'){
    $return=security_safe_return($_POST['return_to']??BASE_URL.'modules/settings.php?tab=security');
    try{
        if(!security_current_password($_POST['current_password']??''))throw new InvalidArgumentException(__t('Current password is incorrect.','رمز فعلی نادرست است.','اوسنی رمز ناسم دی.'));
        $pin=$_POST['new_pin']??'';$confirm=$_POST['confirm_pin']??'';
        if(!is_string($pin)||!is_string($confirm))throw new InvalidArgumentException(__t('Invalid PIN.','PIN نامعتبر است.','PIN ناسم دی.'));
        $pin=normalize_numbers(trim($pin));$confirm=normalize_numbers(trim($confirm));
        if($pin!==''&&(!preg_match('/^\d{6,12}$/D',$pin)||!hash_equals($pin,$confirm)))throw new InvalidArgumentException(__t('Use a matching PIN of 6–12 digits.','PIN از ۶ تا ۱۲ رقم و تکرار یکسان وارد کنید.','له ۶ تر ۱۲ شمېرو یو شان PIN ولیکئ.'));
        $id=auth_user()['id'];$pdo->beginTransaction();
        if($pin!==''&&!update_setting('security.screen_pin.'.$id,password_hash($pin,PASSWORD_DEFAULT)))throw new RuntimeException('Write failed.');
        if(!update_setting('security.screen_enabled.'.$id,isset($_POST['screen_lock_enabled'])?'1':'0'))throw new RuntimeException('Write failed.');
        $pdo->commit();set_flash(__t('Your screen-lock preferences were saved.','تنظیمات قفل صفحهٔ حساب شما ذخیره شد.','ستاسو د پردې قفل تنظیمات خوندي شول.'),'success');
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();set_flash($e instanceof InvalidArgumentException?$e->getMessage():__t('Lock settings were not changed.','تنظیمات قفل تغییر نکرد.','د قفل تنظیمات بدل نه شول.'),'danger');}
    redirect($return);
}
lock_response(false,__t('Unknown action.','عملیات نامعتبر است.','عمل ناسم دی.'),400);
