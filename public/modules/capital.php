<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Shop Capital Management (سرمایه عمومی دکان)
 * 100% Language Isolation Supported: English, Dari, Pashto
 */

require_once __DIR__.'/../includes/listing.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();
$accountWhere=currency_sql();
$currentLang = get_current_lang();
$currentDir  = get_current_dir();

$error = '';
$success = '';

$currencyContext=currency_prepare_request(['add_capital'=>['amount'=>2]],'',true);

// Handle Capital Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_capital') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!');
    } else {
        $amount = (float)normalize_digits($_POST['amount'] ?? '0');
        $transType = trim($_POST['transaction_type'] ?? 'سرمایه');
        $sourceDesc = trim($_POST['source_description'] ?? '');
        $transDate = !empty($_POST['transaction_date']) ? $_POST['transaction_date'] : date('Y-m-d');

        if ($amount <= 0) {
            $error = __t('Please enter a valid capital amount.', 'مبلغ وارد شده معتبر نیست.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO capital (amount, transaction_type, source_description, transaction_date, shop_id) 
                                   VALUES (:amt, :ttype, :sdesc, :tdate, " . shop_id() . ")");
            $stmt->execute([
                ':amt'   => $amount,
                ':ttype' => $transType,
                ':sdesc' => $sourceDesc,
                ':tdate' => $transDate
            ]);

            try {
            currency_record('capital',(int)$pdo->lastInsertId(),$currencyContext,['amount'=>$amount]);
            currency_request_commit();
            log_activity($currentUser['id'], 'تغییر سرمایه', "ثبت {$transType} به مبلغ " . currency_format($amount,$currencyContext['code']));
            set_flash(__t('Capital transaction recorded successfully.', 'عملیات حساب سرمایه با موفقیت ثبت گردید.'), 'success');
            redirect(BASE_URL . 'modules/capital.php');
            } catch(Throwable $e){currency_request_rollback();http_response_code(422);$error=public_error($e);}

        }
    }
}

// Fetch all capital transactions
$sorts=['newest'=>['id DESC','Newest first','تازه‌ترین اول'],'oldest'=>['id ASC','Oldest first','قدیمی‌ترین اول'],'amount_high'=>['amount DESC,id DESC','Amount: high to low','مبلغ: زیاد به کم']];[$list,$transactions]=listing_query($pdo,'SELECT * FROM '.live_documents_source('capital').' WHERE '.$accountWhere.' AND '.shop_sql(),[],$sorts);
$totals=$pdo->query("SELECT COALESCE(SUM(CASE WHEN transaction_type='برداشت' THEN amount ELSE 0 END),0) AS withdrawn,COALESCE(SUM(CASE WHEN transaction_type<>'برداشت' THEN amount ELSE 0 END),0) AS injected FROM ".live_documents_source('capital')." WHERE $accountWhere AND ".shop_sql())->fetch();$totalInjected=$totals['injected'];$totalWithdrawn=$totals['withdrawn'];$currentNetCapital=dec_sub($totalInjected,$totalWithdrawn);
$pageTitle = 'گردش و حساب سرمایه دکان';
require_once __DIR__ . '/../includes/header.php';

$isLocked = is_feature_locked('capital_turnover');
?>

<?php if ($isLocked): ?>
<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title" style="display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-vault ml-1"></i> <?= __t('Shop Capital Turnover & Cash Flow Management', 'مدیریت گردش سرمایه دکان', 'د پانګې د څرخ مدیریت') ?>
            <span class="badge-lock-tag"><i class="fa-solid fa-lock text-amber-500"></i> <?= __t('Locked', 'قفل می‌باشد', 'قفل دی') ?></span>
        </h3>
        <a href="<?= BASE_URL ?>modules/partners.php" class="btn btn-secondary btn-sm"><?= __t('Return to Partners', 'بازگشت به شرکا', 'شریکانو ته بېرته تلل') ?></a>
    </div>
</div>

<div class="locked-blur-wrapper">
    <div class="locked-blur-content" style="padding: 20px;">
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
            <div class="card" style="padding: 20px; border: 1px solid var(--border-dark);">
                <h4><?= __t('Capital Ledger', 'گردش حساب سرمایه', 'د پانګې حساب') ?></h4>
                <p><?= __t('Initial Total Capital: 500,000 AFN', 'مجموع سرمایه اولیه: ۵۰۰,۰۰۰ افغانی', 'لومړنۍ پانګه: ۵۰۰،۰۰۰ افغانۍ') ?></p>
                <p><?= __t('Recent Cash Injection: 100,000 AFN', 'تزریق نقدینگی اخیر: ۱۰۰,۰۰۰ افغانی', 'وروستۍ نغده پانګه: ۱۰۰،۰۰۰ افغانۍ') ?></p>
            </div>
            <div class="card" style="padding: 20px; border: 1px solid var(--border-dark);">
                <h4><?= __t('Bank & Cash Vault Transactions', 'تراکنش‌های بانکی و صندوق', 'بانکي او نغدي راکړه ورکړه') ?></h4>
                <p><?= __t('Transaction #1: Capital Injection', 'تراکنش شماره ۱: افزایش سرمایه', 'لومړۍ معامله: پانګه ډېرول') ?></p>
                <p><?= __t('Transaction #2: Capital Withdrawal', 'تراکنش شماره ۲: برداشت اضطراری', 'دویمه معامله: ایستل') ?></p>
            </div>
        </div>
    </div>
    <div class="locked-overlay-card">
        <h3><i class="fa-solid fa-lock mr-2"></i> <?= __t('Feature is Locked', 'این گزینه قفل می‌باشد', 'دا برخه قفل ده') ?></h3>
        <p style="font-size: 14px; margin-bottom: 8px;"><?= __t('Contact Ahmad Najeem Nik (+93783788278) to unlock full access.', 'جهت درخواست و فعال‌سازی این گزینه با احمد نجیم نیک تماس بگیرید', 'د خلاصولو لپاره له احمد نجیم نیک سره اړیکه ونیسئ') ?></p>
        <p class="en-notice"><?= __t('This feature is currently locked. To request access and unlock this feature, please contact Ahmad Najeem Nik.', 'این قابلیت در نسخه دمو قفل است. جهت دسترسی کامل تماس بگیرید.', 'دا برخه قفل ده.') ?></p>
        <a href="https://wa.me/93783788278?text=Hello%20Ahmad%20Najeem%20Nik,%20I%20would%20like%20to%20unlock%20Capital%20Turnover%20Management" target="_blank" class="btn-whatsapp-direct">
            <i class="fa-brands fa-whatsapp"></i> <?= __t('WhatsApp Ahmad Najeem Nik (+93783788278)', 'واتس‌اپ احمد نجیم نیک (+93783788278)', 'د احمد نجیم نیک واټس اپ (+93783788278)') ?>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; exit; endif; ?>

<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div>
            <h3 class="card-title" style="margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-vault ml-1 text-primary"></i> <?= __t('Store Capital Turnover & Vault Management', 'سرمایه در گردش و صندوق عمومی دکان', 'د هټۍ د پانګې څرخ او عمومي صندوق') ?>
            </h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                <?= __t('Net Available Capital: ', 'موجودی خالص سرمایه: ', 'خالصه موجوده پانګه: ') ?><strong style="color: var(--text-main); font-size: 15px;"><?= format_money($currentNetCapital) ?></strong>
                <span style="color: var(--border-dark); margin: 0 6px;">|</span>
                <?= __t('Total Injected: ', 'مجموع تزریق‌شده: ', 'ټوله ورزیاته شوې: ') ?><span style="color: var(--success); font-weight: 700;"><?= format_money($totalInjected) ?></span>
                <span style="color: var(--border-dark); margin: 0 6px;">|</span>
                <?= __t('Total Withdrawn: ', 'مجموع برداشت‌ها: ', 'ټولټال ایستل شوې: ') ?><span style="color: var(--danger); font-weight: 700;"><?= format_money($totalWithdrawn) ?></span>
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="<?= BASE_URL ?>modules/partners.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                <i class="fa-solid fa-users"></i>
                <span><?= __t('Store Partners & Shares', 'شرکاء عمومی دکان', 'د هټۍ عمومي شریکان') ?></span>
            </a>
            <a href="<?= BASE_URL ?>modules/mudaraba.php" class="btn btn-sm" style="background: var(--success-soft); color: var(--success); border: 1.5px solid #a7f3d0; display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                <i class="fa-solid fa-handshake-angle"></i>
                <span><?= __t('Dedicated Mudaraba Account', 'حساب اختصاصی مضاربت', 'د مضاربت ځانګړی حساب') ?></span>
            </a>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; align-items: start;">
    <!-- Add Capital Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-plus-circle text-primary"></i> <?= __t('Record Capital Turnover', 'ثبت گردش سرمایه جدید', 'د پانګې نوې راکړه ورکړه') ?>
            </h3>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?><?php funds_select(); ?>
            <input type="hidden" name="action" value="add_capital">
            <?php currency_fields(['amount'=>2]); ?>

            <div class="form-group">
                <label class="form-label" for="transaction_type" style="font-weight: 700;"><?= __t('Operation Type:', 'نوع عملیات:', 'د معاملې ډول:') ?></label>
                <select id="transaction_type" name="transaction_type" class="form-control" style="font-weight: 600;">
                    <option value="سرمایه"><?= __t('Capital Injection / Increase Store Capital', 'تزریق سرمایه جدید / افزایش سرمایه دکان', 'نوې پانګه ورزیاتول') ?></option>
                    <option value="برداشت"><?= __t('Capital Withdrawal', 'برداشت از سرمایه', 'له پانګې ایستل') ?></option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="amount" style="font-weight: 700;">
                    <?= sprintf(__t('Amount (%s): *', 'مبلغ (%s): *', 'اندازه (%s): *'), get_currency_label()) ?>
                </label>
                <input type="number" step="any" id="amount" name="amount" class="form-control numeric-input" required placeholder="<?= __t('Amount in AFN', 'مبلغ به افغانی', 'اندازه په افغانۍ') ?>" style="font-weight: 700; font-size: 15px;">
            </div>

            <div class="form-group">
                <label class="form-label" for="source_description" style="font-weight: 700;"><?= __t('Source / Description:', 'توضیحات و منبع سرمایه:', 'تفصیل او سرچینه:') ?></label>
                <input type="text" id="source_description" name="source_description" class="form-control" placeholder="<?= __t('e.g. Cash injection by owner or partner', 'مثلاً: آورده نقدی احمد ضیا یا تسویه نقدی', 'لکه: د خاوند لخوا نغده پانګه') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="transaction_date" style="font-weight: 700;"><?= __t('Transaction Date:', 'تاریخ تراکنش:', 'د راکړې ورکړې نېټه:') ?></label>
                <input type="date" id="transaction_date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px; padding: 10px; font-weight: 800; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                <i class="fa-solid fa-check"></i>
                <span><?= __t('Save Capital Entry', 'ثبت در گردش حساب سرمایه', 'په حساب کې ثبت کړئ') ?></span>
            </button>
        </form>
    </div>

    <!-- Capital History Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-clock-rotate-left text-primary"></i> <?= __t('Shop Capital Transactions History', 'تاریخچه تغییرات سرمایه دکان', 'د پانګې د بدلونونو تاریخچه') ?>
            </h3>
        </div>

        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                <thead>
                    <tr>
                        <th style="width: 45px; text-align: center; white-space: nowrap;">#</th>
                        <th style="width: 120px; text-align: center; white-space: nowrap"><?= __t('Type', 'نوع عملیات', 'ډول') ?></th>
                        <th style="min-width: 130px; text-align: <?= $currentDir === 'rtl' ? 'left' : 'right' ?>; white-space: nowrap;"><?= sprintf(__t('Amount (%s)', 'مبلغ (افغانی)', 'اندازه (%s)'), get_currency_label()) ?></th>
                        <th style="min-width: 180px;"><?= __t('Source Description', 'شرح و منبع تراکنش', 'تفصیل') ?></th>
                        <th style="width: 125px; text-align: center; white-space: nowrap;"><?= __t('Date', 'تاریخ تراکنش', 'نېټه') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 28px;">
                                <i class="fa-solid fa-receipt" style="font-size: 28px; display: block; margin-bottom: 8px; color: var(--border-dark);"></i>
                                <?= __t('No capital transactions recorded yet.', 'تاکنون هیچ تراکنشی در حساب سرمایه ثبت نشده است.', 'تر اوسه هېڅ معامله نه ده ثبت شوې.') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $tNum = 1;
                        foreach ($transactions as $tx): 
                        ?>
                            <tr>
                                <td style="text-align: center; font-weight: 700; color: var(--text-muted); white-space: nowrap;"><?= $currentLang === 'en' ? ($tNum++) : to_persian_num($tNum++) ?></td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <?php if ($tx['transaction_type'] === 'برداشت'): ?>
                                        <span class="badge badge-danger" style="font-weight: 700; padding: 3px 8px;"><?= __t('Withdrawal', 'برداشت', 'ایستل') ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-success" style="font-weight: 700; padding: 3px 8px;"><?= __t('Capital Injection', 'افزایش سرمایه', 'ډېرول') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: <?= $currentDir === 'rtl' ? 'left' : 'right' ?>; white-space: nowrap;">
                                    <strong style="font-size: 13.5px; <?= $tx['transaction_type'] === 'برداشت' ? 'color: var(--danger);' : 'color: var(--success);' ?>">
                                        <?= $tx['transaction_type'] === 'برداشت' ? '-' : '+' ?><?= format_money($tx['amount']) ?>
                                    </strong>
                                </td>
                                <td style="font-size: 13px; line-height: 1.6; color: var(--text-main);"><?= sanitize($tx['source_description'] ?: '-') ?></td>
                                <td style="text-align: center; white-space: nowrap; direction: ltr; font-family: inherit; font-size: 12.5px; font-weight: 700; color: var(--text-muted);">
                                    <?= $currentLang === 'en' ? date('Y/m/d', strtotime($tx['transaction_date'])) : to_persian_num(date('Y/m/d', strtotime($tx['transaction_date']))) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php listing_controls_form($list);listing_footer($list);require_once __DIR__ . '/../includes/footer.php'; ?>
