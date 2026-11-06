<?php
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/auth.php';
auth_require(null,true);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    if(!csrf_verify($_POST['csrf_token']??'')){http_response_code(403);exit(__t('Invalid form session. Refresh and try again.','نشست فرم نامعتبر است؛ صفحه را تازه کنید.','د فورم ناسته ناسمه ده؛ پاڼه تازه کړئ.'));}
    log_activity(auth_user()['id'],'Account sign-out','Session ended.');auth_logout();redirect(BASE_URL.'modules/login.php?signed_out=1');
}
// GET remains a safe, working entry point for existing bookmarks.
$pageTitle=__t('Sign out','خروج از حساب','له حسابه وتل');
if(!empty($_SESSION['screen_locked']))redirect(BASE_URL.'modules/login.php?locked=1');
require_once __DIR__.'/includes/header.php';
?>
<div class="card" style="max-width:500px;margin:30px auto"><h3 class="card-title"><?= __t('Sign out of this account?','از این حساب خارج می‌شوید؟','له دې حسابه وځئ؟') ?></h3><p class="muted" style="margin:12px 0 22px"><?= __t('Your saved records remain unchanged. You will need your username and password to return.','سوابق ذخیره‌شده تغییر نمی‌کند. برای ورود دوباره نام کاربری و رمز لازم است.','ثبت شوي معلومات نه بدلېږي. د بیا ننوتلو لپاره کارن نوم او رمز اړین دي.') ?></p><form method="POST"><?= csrf_field() ?><button type="submit" class="btn btn-primary"><?= __t('Sign out','خروج از حساب','له حسابه وتل') ?></button> <a class="btn btn-secondary" href="<?= BASE_URL ?>index.php"><?= __t('Cancel','لغو','لغوه') ?></a></form></div>
<?php require_once __DIR__.'/includes/footer.php'; ?>
