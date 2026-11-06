<?php
/** Account sign-in using the existing password verifier and saved users. */
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
$authenticated=auth_check();$locked=$authenticated&&!empty($_SESSION['screen_locked']);
$next=security_safe_return($_POST['next']??$_GET['next']??BASE_URL.'index.php');
if($authenticated&&!$locked)redirect(app_url_with_language($next,get_current_lang()));
$error='';$username='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    if(!csrf_verify($_POST['csrf_token']??'')){
        $missingCookie=empty($_COOKIE[session_name()]);
        $error=$missingCookie?__t('Your browser did not keep the sign-in session. Open this page in a new tab and try again; this is not a password error.','مرورگر نشست ورود را نگه نداشت. همین صفحه را در تب جدا باز کنید و دوباره وارد شوید؛ این خطای رمز نیست.','براوزر د ننوتلو ناسته نه ده ساتلې. دا پاڼه په نوي ټب کې خلاصه او بیا هڅه وکړئ؛ دا د رمز تېروتنه نه ده.') : __t('Your form session expired. Please try again.','نشست فرم منقضی شده؛ دوباره تلاش کنید.','د فورم ناستې وخت پای ته رسېدلی؛ بیا هڅه وکړئ.');
    }
    elseif($locked){
        $secret=$_POST['password']??'';
        $user=auth_user();
        if(security_attempt('screen-unlock',(string)$user['id'])&&(!security_screen_enabled()||security_pin_matches($secret,security_screen_pin())||security_current_password($secret))){
            $_SESSION['screen_locked']=false;security_clear_attempts('screen-unlock',(string)$user['id']);session_regenerate_id(true);unset($_SESSION['csrf_token']);redirect(app_url_with_language($next,get_current_lang()));
        }
        $error=__t('Password or saved lock PIN is incorrect, or there have been too many attempts.','رمز یا PIN ثبت‌شدهٔ قفل نادرست است، یا تلاش‌های زیادی انجام شده است.','رمز یا ثبت شوی PIN ناسم دی، یا ډېرې هڅې شوې دي.');
    }else{
        $username=is_string($_POST['username']??null)?trim($_POST['username']):'';
        if(auth_login($username,$_POST['password']??'')){ $_SESSION['nik_welcome']=true; redirect(app_url_with_language($next,get_current_lang())); }
        $error=($GLOBALS['_auth_error']??'')==='limited'?__t('Too many sign-in attempts. Try again in ten minutes.','تلاش‌های ورود زیاد است؛ ده دقیقه بعد دوباره تلاش کنید.','د ننوتلو هڅې ډېرې شوې؛ لس دقیقې وروسته بیا هڅه وکړئ.'):__t('The username or password is incorrect.','نام کاربری یا رمز عبور نادرست است.','کارن نوم یا رمز ناسم دی.');
    }
}
if(($GLOBALS['_auth_error']??'')==='unavailable')$error=__t('This account is inactive, locked, or its subscription has expired. Contact the administrator.','حساب غیرفعال یا قفل است، یا اعتبار اشتراک پایان یافته؛ با مدیر تماس بگیرید.','حساب غیرفعال یا قفل دی، یا ګډون پای ته رسېدلی؛ له مدیر سره اړیکه ونیسئ.');
$lang=get_current_lang();$dir=get_current_dir();$prefs=ui_preferences($authenticated?(int)$_SESSION['user_id']:0);$shopName=get_shop_title();$logo=ui_logo_url()?:brand_mark_url();$brandName=brand_name();$customShop=$shopName!==__('default_shop_name');
?>
<!DOCTYPE html>
<html lang="<?= sanitize($lang) ?>" dir="<?= sanitize($dir) ?>"<?= ui_root_attributes() ?>>
<head>
 <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= __t('Sign in','ورود','ننوتل') ?> — <?= sanitize($brandName) ?></title>
 <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>uploads/branding/favicon-32.png"><link rel="icon" href="<?= BASE_URL ?>uploads/branding/favicon.ico" sizes="any"><link rel="apple-touch-icon" href="<?= BASE_URL ?>uploads/branding/apple-touch-icon.png">
 <link rel="manifest" href="<?= BASE_URL ?>manifest.webmanifest"><meta name="theme-color" content="#0b2545"><meta name="apple-mobile-web-app-title" content="Nik Smart Count"><meta name="apple-mobile-web-app-capable" content="yes">
 <script>document.documentElement.dataset.systemDark=String(matchMedia('(prefers-color-scheme: dark)').matches);try{var m=localStorage.getItem('nikxor_login_mode');if(m==='light'||m==='dark')document.documentElement.dataset.mode=m;}catch(e){}</script>
 <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/vazirmatn/font.css"><link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css"><link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css"><link rel="stylesheet" href="<?= BASE_URL ?>assets/css/themes.css?v=2.0"><link rel="stylesheet" href="<?= BASE_URL ?>assets/css/branding.css?v=<?= APP_VERSION ?>">
 <script type="application/json" id="nikxor-ui-config"><?= json_for_html($prefs,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script><script src="<?= BASE_URL ?>assets/js/preferences.js?v=2.0" defer></script>
</head>
<body class="auth-page dir-<?= $dir ?>">
 <main class="auth-shell">
  <section class="auth-brand" data-wordmark="shown" aria-label="<?= sanitize($brandName) ?>">
   <div class="auth-brand-name">
    <span class="auth-brand-logo"><img src="<?= sanitize($logo) ?>" alt="<?= sanitize($brandName) ?>"></span>
    <span class="auth-brand-text">
      <strong><?= sanitize($brandName) ?></strong>
      <em><?= sanitize(__('brand_company')) ?></em>
    </span>
    <button type="button" class="auth-wordmark-toggle" data-nik-wordmark-toggle aria-pressed="false" data-show-text="<?= __t('Show brand name','نمایش نام برنامه','د اپلیکیشن نوم ښودل') ?>" data-hide-text="<?= __t('Hide brand name','پنهان‌کردن نام برنامه','د اپلیکیشن نوم پټول') ?>" aria-label="<?= __t('Hide brand name','پنهان‌کردن نام برنامه','د اپلیکیشن نوم پټول') ?>"><i class="fa-regular fa-eye"></i></button>
   </div>
   <?php if($customShop): ?><div style="margin-top:10px;font-size:11.5px;font-weight:700;color:var(--primary);"><i class="fa-solid fa-store" aria-hidden="true"></i> <?= sanitize($shopName) ?></div><?php endif; ?>
   <div><div class="eyebrow">NIK SMART COUNT WORKSPACE</div><h1><?= __t('Your business. Clearly in view.','کسب‌وکار شما، روشن و منظم.','ستاسو کاروبار، روښانه او منظم.') ?></h1><p><?= __t('One familiar workspace for inventory, sales, purchases and independent currency accounts.','یک فضای آشنا برای گدام، فروش، خرید و حساب‌های مستقلِ هر ارز.','یو آشنا کاري ځای د موجودۍ، پلور، پېرود او د هر اسعارو جلا حسابونو لپاره.') ?></p><ul class="auth-feature-list"><li><i class="fa-regular fa-circle-check"></i><?= __t('Your existing records, preserved','سوابق قبلی شما، محفوظ','ستاسو پخواني معلومات، خوندي') ?></li><li><i class="fa-regular fa-circle-check"></i><?= __t('Every currency, a separate account','هر ارز، یک حساب مستقل','هر اسعار، یو جلا حساب') ?></li><li><i class="fa-regular fa-circle-check"></i><?= __t('Installable on phone, tablet and laptop','قابل نصب روی موبایل، تبلت و لپ‌تاپ','په موبایل، ټابلیټ او لپ ټاپ نصب کېدای شي') ?></li></ul></div>
   <div class="auth-brand-footer"><?= __t('Built for everyday business.','برای کارهای روزمرهٔ کسب‌وکار.','د کاروبار د ورځنیو چارو لپاره.') ?></div>
  </section>
  <section class="auth-form-pane">
   <div class="auth-toolbar"><nav aria-label="<?= __('language') ?>"><?php foreach(get_supported_languages() as $code=>$info): ?><a href="?<?= sanitize(http_build_query(['lang'=>$code,'next'=>$next])) ?>" <?= $code===$lang?'aria-current="true"':'' ?>><?= sanitize($info['native']) ?></a><?php endforeach; ?></nav><button class="auth-icon-btn" type="button" data-toggle-mode data-public="1" title="<?= __t('Light / dark','روشن / تاریک','رڼا / تیاره') ?>" aria-label="<?= __t('Toggle light/dark mode','تغییر حالت روشن/تاریک','رڼا/تیاره بڼه بدلول') ?>"><i class="fa-regular fa-moon"></i></button></div>
   <h2><?= $locked?__t('Welcome back','خوش آمدید','ښه راغلاست'):__t('Sign in to your workspace','ورود به فضای کاری','خپل کاري ځای ته ننوځئ') ?></h2>
   <p class="auth-subtitle"><?= $locked?__t('Unlock with your account password or your saved screen-lock PIN.','با رمز حساب یا PIN ثبت‌شدهٔ قفل صفحه وارد شوید.','د حساب رمز یا د پردې ثبت شوي PIN سره قفل خلاص کړئ.'):__t('Use your existing username and password.','از نام کاربری و رمز ثبت‌شدهٔ خود استفاده کنید.','خپل ثبت شوی کارن نوم او رمز وکاروئ.') ?></p>
   <?php if(isset($_GET['signed_out'])): ?><div class="alert alert-success" role="status"><?= __t('You have been signed out securely.','با موفقیت از حساب خارج شدید.','له حسابه په خوندي ډول ووتلئ.') ?></div><?php endif; ?>
   <?php if(isset($_GET['restored'])): ?><div class="alert alert-success" role="status"><?= __t('Backup restored. Sign in using an account from that backup.','بکاپ بازیابی شد؛ با حساب همان نسخه وارد شوید.','شاتړ بیا ورغول شو؛ د هماغې نسخې حساب وکاروئ.') ?></div><?php endif; ?>
   <?php if($error): ?><div class="alert alert-danger" id="loginError" role="alert"><?= sanitize($error) ?></div><?php endif; ?>
   <form method="POST" class="auth-form" id="loginForm"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $locked?'unlock':'login' ?>"><input type="hidden" name="next" value="<?= sanitize($next) ?>">
    <?php if(!$locked): ?><div class="form-group"><label class="form-label" for="username"><?= __t('Username','نام کاربری','کارن نوم') ?></label><input type="text" class="form-control" id="username" name="username" autocomplete="username" data-sensitive required maxlength="100" value="<?= sanitize($username) ?>" autofocus spellcheck="false" autocapitalize="none"></div><?php endif; ?>
    <div class="form-group"><label class="form-label" for="loginPassword"><?= $locked?__t('Password or lock PIN','رمز یا PIN قفل','رمز یا د قفل PIN'):__t('Password','رمز عبور','رمز') ?></label><div class="password-field"><input type="password" class="form-control" name="password" id="loginPassword" autocomplete="current-password" data-sensitive required maxlength="256" <?= $locked?'autofocus':'' ?>><button type="button" class="password-toggle" data-toggle-password="loginPassword" data-show-label="<?= __t('Show password','نمایش رمز','رمز وښایئ') ?>" data-hide-label="<?= __t('Hide password','پنهان‌کردن رمز','رمز پټ کړئ') ?>" aria-label="<?= __t('Show password','نمایش رمز','رمز وښایئ') ?>" aria-pressed="false"><i class="fa-regular fa-eye" aria-hidden="true"></i></button></div></div>
    <button type="submit" class="btn btn-primary"><span><?= $locked?__t('Unlock workspace','باز کردن فضای کاری','کاري ځای خلاص کړئ'):__t('Sign in','ورود به حساب','حساب ته ننوتل') ?></span><i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i></button>
   </form>
   <?php if($locked): ?><form method="POST" action="<?= BASE_URL ?>logout.php" style="margin-top:12px"><?= csrf_field() ?><button class="btn btn-secondary" style="width:100%" type="submit"><?= __t('Sign out instead','خروج از حساب','له حسابه وتل') ?></button></form><?php endif; ?>
   <p class="auth-footer"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> <?= __t('Your credentials stay private.','اطلاعات ورود شما خصوصی می‌ماند.','ستاسو د ننوتلو معلومات خصوصي پاتې کېږي.') ?><br><a id="openLoginTab" href="<?= sanitize(BASE_URL.'modules/login.php?'.http_build_query(['lang'=>$lang,'next'=>$next])) ?>" target="_blank" rel="noopener"><?= __t('Session blocked in preview? Open in a new tab.','اگر نشست در پیش‌نمایش مسدود است، در صفحهٔ جدا باز کنید.','که په کتنه کې ناسته بنده وي، په نوې پاڼه کې یې خلاصه کړئ.') ?></a></p>
  </section>
 </main>
<script src="<?= BASE_URL ?>assets/js/branding.js?v=<?= APP_VERSION ?>" data-base="<?= BASE_URL ?>"></script>
</body></html>
