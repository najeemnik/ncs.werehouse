<?php
/** Existing backup entry point, using the shared secure backup/restore service. */
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
auth_require('admin');$currentUser=auth_user();
require_once __DIR__.'/../includes/backup_controller.php';
$pageTitle=__t('Backup & restore','بکاپ و بازیابی','شاتړ او بیا رغونه');
require_once __DIR__.'/../includes/header.php';
?>
<div class="settings-heading"><div><div class="eyebrow">NIKXOR / <?= __t('DATA SAFETY','امنیت اطلاعات','د معلوماتو خوندیتوب') ?></div><h2><?= __t('Backup & restore','بکاپ و بازیابی','شاتړ او بیا رغونه') ?></h2><p><?= __t('Private storage. Verified copies. Deliberate recovery.','ذخیرهٔ خصوصی، نسخهٔ بررسی‌شده و بازیابی با تأیید شما.','خصوصي ساتنه، تایید شوې نسخه او ستاسو په تایید بیا رغونه.') ?></p></div><a class="btn btn-secondary" href="<?= BASE_URL ?>modules/settings.php?tab=backup"><i class="fa-solid fa-sliders"></i> <?= __t('Settings','تنظیمات','تنظیمات') ?></a></div>
<section class="settings-panel">
<?php require __DIR__.'/../includes/backup_panel.php'; ?>
</section>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
