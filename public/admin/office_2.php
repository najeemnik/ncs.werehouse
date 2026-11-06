<?php
/** Canonical Office 2 entry: ordinary database authentication, then privileged authorization. */
require_once __DIR__.'/../includes/functions.php';require_once __DIR__.'/../includes/office2.php';
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
if(auth_check() && domain_is_central(auth_user()) && !mfa_access_valid(auth_user()))redirect(BASE_URL.'admin/verify.php');
$authError='';$action=$_POST['action']??'';
if(auth_check()&&!empty($_SESSION['screen_locked']))redirect(BASE_URL.'modules/login.php?locked=1&next='.rawurlencode(office2_url()));
if(auth_check()&&(int)access_profile(auth_user()['id'])['must_change_password'])redirect(BASE_URL.'modules/change_password.php?next='.rawurlencode(office2_url()));
if(!office2_authorized()){
    if(auth_check()&&!domain_is_central(auth_user()))access_deny(403,'office2_required',__t('Office 2 access is not permitted for this account.','این حساب اجازهٔ دسترسی به دفتر دو را ندارد.','دا حساب دفتر دو ته اجازه نه لري.'));
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        if(!csrf_verify($_POST['csrf_token']??''))$authError=__t('The sign-in session was not retained. Refresh this preview and allow cookies for it.','نشست ورود حفظ نشده است؛ پیش‌نمایش را تازه کنید و ذخیرهٔ کوکی آن را اجازه دهید.','د ننوتلو ناسته نه ده ساتل شوې؛ کتنه تازه او کوکي ته اجازه ورکړئ.');
        elseif($action==='office_login'&&!auth_check()){
            if(auth_login($_POST['username']??'',$_POST['password']??'')){
                if(!auth_is_admin()){auth_logout();redirect(BASE_URL.'modules/login.php');}
                redirect(office2_url());
            }
            $authError=__t('Sign-in failed. Check the account credentials and account availability.','ورود ناموفق بود؛ اطلاعات ورود و فعال‌بودن حساب را بررسی کنید.','ننوتل ناکام شول؛ معلومات او د حساب فعالوالی وګورئ.');
        }elseif($action==='verify_master_pin'&&auth_check()){
            if(office2_step_up($_POST['master_pin']??''))redirect(office2_url());
            $authError=__t('Access verification failed, or too many attempts.','تأیید دسترسی ناموفق بود یا تلاش‌های زیادی انجام شده است.','د لاسرسي تایید ناکام شو یا ډېرې هڅې شوې دي.');
        }else access_deny(403,'office2_required',__t('Office 2 authorization is required.','مجوز دفتر دو لازم است.','د دفتر دو اجازه اړینه ده.'));
    }
    $authMode=auth_check()?'verify_master_pin':'office_login';
    $authTitle=__t('Office 2 — Sign in','دفتر دو — ورود','دفتر دو — ننوتل');
    $authSubtitle=$authMode==='verify_master_pin'?__t('Your administrator account is verified. Enter the saved Office 2 access password.','حساب مدیر تأیید شد؛ رمز دسترسی ثبت‌شدهٔ دفتر دو را وارد کنید.','مدیر حساب تایید شو؛ د دفتر دو ثبت شوی لاسرسي رمز ولیکئ.'):__t('Sign in with an authorized server-side account. New accounts must change their temporary password.','با حساب مجاز سرور وارد شوید. حساب تازه باید رمز موقت خود را تغییر دهد.','د سرور مجاز حساب وکاروئ. نوی حساب باید لنډمهالی رمز بدل کړي.');
    require __DIR__.'/../includes/office2_auth_view.php';exit;
}
office2_require();
$tabs=['overview','shops','users','features','subscriptions','ai','global','security','audit'];
$tab=is_string($_GET['tab']??null)&&in_array($_GET['tab'],$tabs,true)?$_GET['tab']:'overview';$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{
        $result=office2_mutate($action,$_POST,$_FILES);
        if(!empty($result['sign_out'])){auth_logout();redirect(BASE_URL.'modules/login.php?signed_out=1');}
        set_flash(__t('Change saved and audited.','تغییر ذخیره و رویداد آن ثبت شد.','بدلون خوندي او ثبت شو.'),'success');
        if(!empty($result['redirect']))redirect($result['redirect']);
        $shopActions=['create_shop','create_shop_employee','shop_state','shop_subscription','shop_renew','shop_employees','save_shop_branding','save_shop_features','open_shop','leave_shop'];
        $afterTab=in_array($action,$shopActions,true)?'shops':(in_array($action,['create_user','edit_user','account_state','reset_password','save_permissions','save_domain_permissions','assign_user_branch','reset_user_mfa'],true)?'users':(in_array($action,['save_subscription','renew_subscription'],true)?'subscriptions':(in_array($action,['save_ai','reset_ai'],true)?'ai':$tab)));
        redirect(office2_url().'?'.http_build_query(['tab'=>$afterTab]+(!empty($result['user_id'])?['user_id'=>$result['user_id']]:[])+(!empty($result['shop_id'])?['shop_id'=>$result['shop_id']]:[])));
    }catch(Throwable $e){
        $status=(int)$e->getCode();if($status===403)http_response_code(403);
        $error=$e instanceof InvalidArgumentException||$status===403?$e->getMessage():__t('The operation was not completed safely. No partial administrative changes were saved.','عملیات به‌صورت امن تکمیل نشد؛ تغییرات مدیریتی ناقص ذخیره نشدند.','عمل په خوندي ډول بشپړ نه شو؛ نیمګړي بدلونونه ونه ساتل شول.');
    }
}
$users=office2_users();$shops=office2_shop_rows();$activeShop=shop_current_id();$selected=null;
if(isset($_GET['user_id'])){try{$selected=office2_user_details($_GET['user_id']);}catch(InvalidArgumentException $e){$error=$e->getMessage();}}
$pageTitle=__t('Office 2','دفتر دو','دوهم دفتر');
require __DIR__.'/../includes/header.php';echo '<section class="card modern-form"><a class="btn btn-primary" href="'.BASE_URL.'modules/setup.php">'.__t('Setup checklist','لست راه‌اندازی','د تنظیم لست').'</a><h2>'.__t('Central workspace','مدیریت مرکزی','مرکزي مدیریت').'</h2><p><a class="btn btn-primary" href="'.BASE_URL.'modules/management.php">'.__t('Owners, employees & permissions','مالکین، کارمندان و صلاحیت‌ها','مالکان او کارکوونکي').'</a> <a class="btn btn-secondary" href="'.BASE_URL.'admin/user_preview.php">'.__t('Read-only user preview','پیش‌نمایش فقط‌خواندنی یوزر','د کارن کتنه').'</a> <a class="btn btn-secondary" href="'.BASE_URL.'modules/portfolio.php">'.__t('All businesses & branches','تمام تجارت‌ها و شعبه‌ها','ټولې څانګې').'</a> <a class="btn btn-secondary" href="'.BASE_URL.'modules/activity.php">'.__t('Activity reports','راپور فعالیت‌ها','د فعالیت راپور').'</a> <a class="btn btn-secondary" href="'.BASE_URL.'modules/backup.php">'.__t('Encrypted backups','بکاپ رمزشده','رمز شوی شاتړ').'</a></p></section>';require __DIR__.'/../includes/office2_view.php';require __DIR__.'/../includes/footer.php';
