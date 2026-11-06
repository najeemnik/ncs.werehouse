<?php
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
auth_require();$currentUser=auth_user();$isAdmin=auth_is_admin();
$tabs=['appearance','branding','financial','security','backup','general'];
$tab=is_string($_GET['tab']??null)&&in_array($_GET['tab'],$tabs,true)?$_GET['tab']:'appearance';
if(!$isAdmin&&in_array($tab,['branding','backup'],true)){http_response_code(403);exit(__t('Access denied.','دسترسی ندارید.','لاسرسی نشته.'));}
$error='';
if($tab==='backup')require_once __DIR__.'/../includes/backup_controller.php';
elseif(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    $action=$_POST['action']??(isset($_POST['enabled'])?'currency_save':'');
    if(in_array($action,['branding_save','currency_save','informational_rate'],true)&&!$isAdmin){http_response_code(403);exit(__t('Administrator access is required.','دسترسی مدیر لازم است.','د مدیر لاسرسی اړین دی.'));}
    try{
        if(!csrf_verify($_POST['csrf_token']??''))throw new InvalidArgumentException(__t('Your form session has expired. Refresh and try again.','نشست فرم منقضی شده؛ صفحه را تازه و دوباره تلاش کنید.','د فورم ناستې وخت پای ته رسېدلی؛ پاڼه تازه کړئ.'));
        switch($action){
            case 'appearance_save':ui_save_preferences($_POST,$currentUser['id'],['theme','palette','effect','mode']);$tab='appearance';break;
            case 'general_save':$post=$_POST+['notifications'=>'off','auto_dismiss'=>'off'];ui_save_preferences($post,$currentUser['id'],['language','date_format','number_format','time_zone','notifications','auto_dismiss']);$tab='general';break;
            case 'financial_preferences':ui_save_preferences($_POST,$currentUser['id'],['default_currency','currency_display']);$tab='financial';break;
            case 'branding_save':ui_save_branding($_POST,$_FILES);$tab='branding';break;
            case 'currency_save':
                if(!is_array($_POST['enabled']??[]))throw new InvalidArgumentException(__t('Invalid currency selection.','انتخاب ارز نامعتبر است.','د اسعارو انتخاب ناسم دی.'));
                foreach($_POST['enabled']??[] as $code)if(!is_string($code))throw new InvalidArgumentException(__t('Invalid currency selection.','انتخاب ارز نامعتبر است.','د اسعارو انتخاب ناسم دی.'));
                currency_settings_save($_POST,$currentUser['id']);$tab='financial';break;
            case 'informational_rate':
                if(isset($_POST['reference_rate'])&&!is_string($_POST['reference_rate']))throw new InvalidArgumentException(__t('Invalid rate.','نرخ نامعتبر است.','نرخ ناسم دی.'));
                ui_save_informational_rate($_POST);$tab='financial';break;
            case 'password_change':security_change_password($_POST['current_password']??'',$_POST['new_password']??'',$_POST['confirm_password']??'');$tab='security';break;
            default:throw new InvalidArgumentException(__t('Unknown settings action.','عملیات تنظیمات نامعتبر است.','د تنظیماتو عمل ناسم دی.'));
        }
        log_activity($currentUser['id'],'Settings updated',$action);
        set_flash($action==='password_change'?__t('Password changed. Other sessions must sign in again.','رمز تغییر کرد؛ نشست‌های دیگر باید دوباره وارد شوند.','رمز بدل شو؛ نورې ناستې باید بیا ننوځي.'):__t('Settings saved successfully.','تنظیمات با موفقیت ذخیره شد.','تنظیمات په بریا خوندي شول.'),'success');
        redirect(BASE_URL.'modules/settings.php?tab='.$tab);
    }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?public_error($e):__t('The change could not be saved safely. No partial changes were applied.','تغییر به‌صورت امن ذخیره نشد؛ تغییر ناقص اعمال نشده است.','بدلون په خوندي ډول ونه ساتل شو؛ نیمګړی بدلون نه دی شوی.');}
}
$prefs=ui_preferences();$pageTitle=__t('Settings','تنظیمات','تنظیمات');
require_once __DIR__.'/../includes/header.php';
require __DIR__.'/../includes/settings_view.php';
require_once __DIR__.'/../includes/footer.php';
