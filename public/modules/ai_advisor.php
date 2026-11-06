<?php
require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/advisor.php';auth_require();$answer='';$error='';$question='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST')try{
    if(!csrf_verify($_POST['csrf_token']??''))throw new RuntimeException('Invalid session.',403);
    $question=is_string($_POST['question']??null)?trim($_POST['question']):'';$answer=advisor_request($question);
}catch(Throwable $e){http_response_code(in_array((int)$e->getCode(),[403,429,503])?(int)$e->getCode():422);$error=$e instanceof JsonException?__t('Invalid provider response.','پاسخ سرویس معتبر نیست.','ځواب ناسم دی.'):public_error($e);}
$pageTitle=__t('Business advisor','مشاور تجارت','د سوداګرۍ سلاکار');require __DIR__.'/../includes/header.php';?>
<section class="card modern-form"><h2><?= sanitize($pageTitle) ?></h2><p><?= __t('Ask about sales, expenses and practical improvements. Only totals from the active branch and modules you may view are sent to the provider. Customer identities, passwords and individual invoices are not sent.','دربارهٔ فروش، مصارف و پیشرفت تجارت بپرسید. تنها مجموع ارقام شعبهٔ فعال و بخش‌های مجاز شما به سرویس فرستاده می‌شود؛ نام مشتریان، رمزها و بل‌های جداگانه فرستاده نمی‌شوند.','د پلور، لګښتونو او پرمختګ پوښتنه وکړئ؛ یوازې د مجازې څانګې مجموعې لېږل کېږي.') ?></p>
<?php if(runtime_value('NIKXOR_AI_ENABLED')!=='1'):?><div class="alert alert-info"><?= __t('Provider connection pending. Your central administrator must configure API access.','اتصال سرویس در انتظار تنظیم مدیریت مرکزی است.','د سرویس تنظیم ته اړتیا ده.') ?></div><?php endif ?>
<?php if($error):?><p class="alert alert-danger" role="alert"><?= sanitize($error) ?></p><?php endif ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="ask_ai"><label for="advisorQuestion"><?= __t('Your question','پرسش شما','ستاسو پوښتنه') ?></label><textarea id="advisorQuestion" name="question" class="form-control" minlength="2" maxlength="2000" rows="4" required><?= sanitize($question) ?></textarea><button class="btn btn-primary"><?= __t('Send authorized summary & ask','فرستادن خلاصهٔ مجاز و پرسش','مجاز لنډیز ولېږئ') ?></button></form>
<?php if($answer):?><article class="card" aria-live="polite" style="white-space:pre-wrap;padding:20px"><?= sanitize($answer) ?></article><?php endif ?></section>
<?php require __DIR__.'/../includes/footer.php'; ?>
