<?php
require_once __DIR__.'/../includes/auth.php';auth_require();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET');exit;}
$feature=is_string($_GET['feature']??null)?$_GET['feature']:'';$item=ui_service_find($feature);if(!$item)access_deny(404,'unknown_service',__t('This service was not found.','این خدمت پیدا نشد.','دا خدمت ونه موندل شو.'));
$mode=domain_visibility($feature);if($mode==='hidden')access_deny(403,'feature_disabled',__t('Access to this service is disabled.','دسترسی به این خدمت غیرفعال است.','دې خدمت لاسرسی بند دی.'));
if($mode==='enabled')redirect(ui_item_url($item,$mode));
http_response_code(403);$pageTitle=__t('Service activation','فعال‌سازی خدمت','د خدمت فعالول');require_once __DIR__.'/../includes/header.php';
$label=__t($item['en'],$item['da'],$item['en']);$contacts=ui_support_contacts();$message=__t('Hello, I would like to activate this service: ','سلام، می‌خواهم این خدمت فعال شود: ','سلام، زه غواړم دا خدمت فعال شي: ').$label;
?>
<section class="service-activation" aria-labelledby="serviceActivationTitle">
  <div class="service-activation-art" aria-hidden="true"><span><i class="fa-solid fa-lock"></i></span><i class="fa-solid fa-shield-halved"></i></div>
  <div class="service-activation-copy">
    <div class="eyebrow">NIK SMART COUNT · SERVICE ACCESS</div>
    <h1 id="serviceActivationTitle"><?= sanitize($label) ?></h1>
    <h2><?= __t('This service is currently locked','این خدمت فعلاً قفل است','دا خدمت اوس بند دی') ?></h2>
    <p><?= __t('Your account and business records are safe. To activate this service, contact support through any of the options below.','حساب و سوابق تجارت شما محفوظ است. برای فعال‌سازی این خدمت، از یکی از گزینه‌های تماس زیر استفاده کنید.','ستاسو حساب او سوداګرۍ سوابق خوندي دي. د دې خدمت د فعالولو لپاره له لاندې اړیکو وکاروئ.') ?></p>
    <div class="service-contact-grid">
      <a class="service-contact phone" href="tel:<?= sanitize($contacts['phone']) ?>"><span><i class="fa-solid fa-phone"></i></span><div><small><?= __t('Call support','تماس با پشتیبانی','له ملاتړې اړیکه') ?></small><strong dir="ltr"><?= sanitize($contacts['phone']) ?></strong></div><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
      <a class="service-contact whatsapp" href="https://wa.me/<?= sanitize($contacts['whatsapp']) ?>?text=<?= rawurlencode($message) ?>" target="_blank" rel="noopener noreferrer"><span><i class="fa-brands fa-whatsapp"></i></span><div><small><?= __t('WhatsApp activation','فعال‌سازی از واتس‌اپ','د واټساپ له لارې فعالول') ?></small><strong><?= __t('Send an activation message','ارسال پیام فعال‌سازی','د فعالولو پیغام واستوئ') ?></strong></div><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
      <?php if($contacts['email']): ?><a class="service-contact email" href="mailto:<?= sanitize($contacts['email']) ?>?subject=<?= rawurlencode($message) ?>"><span><i class="fa-regular fa-envelope"></i></span><div><small><?= __t('Email support','ایمیل به پشتیبانی','ملاتړ ته برېښنالیک') ?></small><strong><?= sanitize($contacts['email']) ?></strong></div><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif ?>
    </div>
    <a class="service-back-link" href="<?= BASE_URL ?>index.php"><i class="fa-solid fa-arrow-left"></i> <?= __t('Back to dashboard','بازگشت به داشبورد','داشبورډ ته ستنیدل') ?></a>
  </div>
</section>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
