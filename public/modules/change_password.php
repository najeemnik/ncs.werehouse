<?php
/** Mandatory first-password change, also available when personal settings are restricted. */
require_once __DIR__.'/../includes/functions.php';require_once __DIR__.'/../includes/auth.php';
auth_require();
$next=security_safe_return($_POST['next']??$_GET['next']??BASE_URL.'index.php');
if(str_contains($next,'/change_password.php')||str_contains($next,'/office_2_api.php'))$next=BASE_URL.'index.php';
$authError='';$authMode='password_change';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{
        if(!csrf_verify($_POST['csrf_token']??''))throw new InvalidArgumentException(__t('Refresh the form and try again.','فرم را تازه و دوباره تلاش کنید.','فورمه تازه او بیا هڅه وکړئ.'));
        security_change_password($_POST['current_password']??'',$_POST['new_password']??'',$_POST['confirm_password']??'');
        set_flash(__t('Password changed. Other sessions were revoked.','رمز تغییر کرد؛ نشست‌های دیگر باطل شدند.','رمز بدل شو؛ نورې ناستې لغوه شوې.'),'success');redirect(app_url_with_language($next,get_current_lang()));
    }catch(Throwable $e){$authError=$e instanceof InvalidArgumentException?public_error($e):__t('Password could not be changed safely. Try again.','رمز به‌صورت امن تغییر نکرد؛ دوباره تلاش کنید.','رمز په خوندي ډول بدل نه شو؛ بیا هڅه وکړئ.');}
}
$authTitle=__t('Change your password','تغییر رمز حساب','د حساب رمز بدل کړئ');
$authSubtitle=__t('A temporary password cannot be used to access the workspace. Choose at least 12 characters; never reuse the initial password.','با رمز موقت نمی‌توان وارد بخش‌های برنامه شد. رمز جدید حداقل ۱۲ کاراکتری و متفاوت انتخاب کنید.','په لنډمهالي رمز کاري ځای ته لاسرسی نشته. نوی او لږ تر لږه ۱۲ توري رمز وټاکئ.');
require __DIR__.'/../includes/office2_auth_view.php';
