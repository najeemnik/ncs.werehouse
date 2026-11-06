<?php
require_once __DIR__.'/backup_service.php';
auth_require('admin');
$backupLocked=is_feature_locked('backup_module');$backupError='';$backupFiles=[];
$backupReturn=basename($_SERVER['SCRIPT_NAME']??'')==='settings.php'?BASE_URL.'modules/settings.php?tab=backup':BASE_URL.'modules/backup.php';
if(isset($_GET['download'])){
    if($backupLocked){http_response_code(403);exit(__t('Backup access is locked.','دسترسی بکاپ قفل است.','شاتړ ته لاسرسی قفل دی.'));}
    if(in_array($_GET['download'],['sqlite','sql'],true)){
        set_flash(__t('Create an encrypted backup below. Direct plaintext database exports are no longer exposed.','از بخش زیر بکاپ رمزشده بسازید؛ دیتابیس به‌صورت متنِ بدون رمز ارائه نمی‌شود.','لاندې رمز شوی شاتړ جوړ کړئ؛ بې رمزه ډیټابیس نه وړاندې کېږي.'),'info');redirect($backupReturn);
    }
    // Full backups include authentication data and legacy administrative logs.
    // Creating or downloading them requires the same privileged Office session.
    require_once __DIR__.'/office2.php';
    if(!office2_authorized())access_deny(403,'office2_required',__t('Office 2 authorization is required for a full database download.','دریافت دیتابیس کامل به تأیید دسترسی دفتر دو نیاز دارد.','د بشپړ ډیټابیس ښکته کول د دفتر دو اجازه غواړي.'));
    office2_require();
    backup_download($_GET['download']);
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    if($backupLocked){http_response_code(403);exit(__t('Backup access is locked.','دسترسی بکاپ قفل است.','شاتړ ته لاسرسی قفل دی.'));}
    try{
        if(!csrf_verify($_POST['csrf_token']??''))throw new InvalidArgumentException(__t('Refresh the form and try again.','فرم را تازه و دوباره تلاش کنید.','فورمه تازه او بیا هڅه وکړئ.'));
        $passphrase=$_POST['backup_passphrase']??'';
        if(($_POST['action']??'')==='create_snapshot'){
            require_once __DIR__.'/office2.php';office2_require();
            if(!is_string($passphrase)||!is_string($_POST['backup_confirm']??null)||!hash_equals($passphrase,$_POST['backup_confirm']))throw new InvalidArgumentException(__t('Backup passphrase confirmation does not match.','تکرار رمز بکاپ مطابقت ندارد.','د شاتړ د رمز تایید سمون نه خوري.'));
            $meta=backup_save_verified(backup_capture($pdo,true),$passphrase);
            log_activity($currentUser['id'],'Encrypted backup created','Verified database snapshot.');
            set_flash(__t('Encrypted backup created and verified. Download it and keep the passphrase separately.','بکاپ رمزشده ساخته و بررسی شد؛ آن را دانلود و رمز را جداگانه نگهداری کنید.','رمز شوی شاتړ جوړ او وکتل شو؛ ښکته یې کړئ او رمز جلا وساتئ.'),'success');redirect($backupReturn);
        }elseif(($_POST['action']??'')==='restore_backup'){
            // A full restore can replace privileged users and access rules. Require
            // existing Office 2 step-up authorization, not merely the admin role.
            require_once __DIR__.'/office2.php';office2_require();
            if(($_POST['restore_confirmation']??'')!=='RESTORE'||empty($_POST['restore_acknowledged']))throw new InvalidArgumentException(__t('Confirm the warning and type RESTORE exactly.','هشدار را تأیید و دقیقاً RESTORE بنویسید.','خبرتیا تایید او RESTORE په سمه توګه ولیکئ.'));
            if(!security_current_password($_POST['current_password']??''))throw new InvalidArgumentException(__t('Current account password is incorrect, or too many attempts.','رمز فعلی حساب نادرست است یا تلاش‌های زیادی انجام شده است.','اوسنی رمز ناسم دی یا ډېرې هڅې شوې دي.'));
            $file=$_FILES['backup_file']??[];
            if(($file['error']??-1)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')||($file['size']??0)>ui_upload_limit(16*1024*1024))throw new InvalidArgumentException(__t('Choose an encrypted .nxb backup up to 16 MB.','بکاپ رمزشدهٔ .nxb تا ۱۶ مگابایت انتخاب کنید.','تر ۱۶ مېګابایټه رمز شوی .nxb شاتړ وټاکئ.'));
            $snapshot=backup_decrypt(file_get_contents($file['tmp_name']),$passphrase);backup_restore($pdo,$snapshot,$passphrase);
            log_activity(null,'Encrypted backup restored','Validated restore; recovery backup retained; sessions invalidated.');
            auth_logout();redirect(BASE_URL.'modules/login.php?restored=1');
        }else throw new InvalidArgumentException(__t('Unknown backup action.','عملیات بکاپ نامعتبر است.','د شاتړ عمل ناسم دی.'));
    }catch(Throwable $e){$backupError=$e instanceof InvalidArgumentException?$e->getMessage():__t('The backup operation could not be completed safely. The current database was retained. Check storage permissions and required PHP extensions.','عملیات بکاپ به‌صورت امن تکمیل نشد؛ دیتابیس فعلی حفظ شد. دسترسی ذخیره‌سازی و افزونه‌های PHP را بررسی کنید.','د شاتړ عمل په خوندي ډول بشپړ نه شو؛ اوسنی ډیټابیس وساتل شو. د ځای اجازه او PHP وګورئ.');}
}
if(!$backupLocked){try{$backupFiles=backup_list();}catch(Throwable $e){$backupError=__t('Private backup storage is not available. Configure NIKXOR_PRIVATE_DIR outside the public web directory.','محل خصوصی بکاپ آماده نیست؛ NIKXOR_PRIVATE_DIR را بیرون از پوشهٔ عمومی تنظیم کنید.','خصوصي شاتړ ځای چمتو نه دی؛ NIKXOR_PRIVATE_DIR د عامه ویب له پوښۍ بهر وټاکئ.');}}
