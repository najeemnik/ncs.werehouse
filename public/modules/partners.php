<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Partners and Profit Sharing Module (شریک ها و تقسیم مفاد)
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

$currencyContext=currency_prepare_request(['add_partner'=>['investment_amount'=>2],'edit_partner'=>['investment_amount'=>2]],'',true);

// Handle Add Partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_partner') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!');
    } else {
        $name        = trim($_POST['name'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $share       = (float)normalize_digits($_POST['share_percentage'] ?? '0');
        $investment  = (float)normalize_digits($_POST['investment_amount'] ?? '0');
        $joinDate    = !empty($_POST['join_date']) ? $_POST['join_date'] : date('Y-m-d');
        $notes       = trim($_POST['notes'] ?? '');

        if (empty($name)) {
            $error = __t('Partner name is required.', 'لطفاً نام شریک را وارد نمایید.');
        } elseif ($share < 0 || $share > 100) {
            $error = __t('Share percentage must be between 0 and 100.', 'فیصدی سهم باید عددی بین ۰ تا ۱۰۰ باشد.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO partners (name, phone, share_percentage, investment_amount, join_date, status, notes, shop_id) 
                                   VALUES (:name, :phone, :share, :inv, :jdate, 'active', :notes, " . shop_id() . ")");
            $stmt->execute([
                ':name'  => $name,
                ':phone' => $phone,
                ':share' => $share,
                ':inv'   => $investment,
                ':jdate' => $joinDate,
                ':notes' => $notes
            ]);

            currency_record('partners',(int)$pdo->lastInsertId(),$currencyContext,['investment_amount'=>$investment],'create');
            currency_request_commit();
            log_activity($currentUser['id'], 'ثبت شریک جدید', "ثبت شریک جدید «{$name}» با سهم {$share}٪");
            set_flash(sprintf(__t('New partner «%s» added successfully.', 'شریک جدید «%s» با موفقیت اضافه شد.'), $name), 'success');
            redirect(BASE_URL . 'modules/partners.php');
        }
    }
}

// Handle Edit Partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_partner') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!');
    } else {
        $partnerId   = (int)($_POST['partner_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $share       = (float)normalize_digits($_POST['share_percentage'] ?? '0');
        $investment  = (float)normalize_digits($_POST['investment_amount'] ?? '0');
        $status      = trim($_POST['status'] ?? 'active');
        $notes       = trim($_POST['notes'] ?? '');

        if ($partnerId <= 0 || empty($name)) {
            $error = __t('Invalid partner information provided.', 'اطلاعات ارسالی معتبر نیست.');
        } elseif ($share < 0 || $share > 100) {
            $error = __t('Share percentage must be between 0 and 100.', 'فیصدی سهم باید عددی بین ۰ تا ۱۰۰ باشد.');
        } else {
            $stmt = $pdo->prepare("UPDATE partners SET 
                name = :name, phone = :phone, share_percentage = :share, 
                investment_amount = :inv, status = :status, notes = :notes 
                WHERE id = :id AND " . shop_sql());
            $stmt->execute([
                ':name'   => $name,
                ':phone'  => $phone,
                ':share'  => $share,
                ':inv'    => $investment,
                ':status' => $status,
                ':notes'  => $notes,
                ':id'     => $partnerId
            ]);

            currency_record('partners',$partnerId,$currencyContext,['investment_amount'=>$investment],'edit');
            currency_request_commit();
            log_activity($currentUser['id'], 'ویرایش سهم شریک', "ویرایش مشخصات و سهم شریک «{$name}» به {$share}٪");
            set_flash(sprintf(__t('Partner «%s» profile and share updated successfully.', 'مشخصات و سهم شریک «%s» با موفقیت بروزرسانی شد.'), $name), 'success');
            redirect(BASE_URL . 'modules/partners.php');
        }
    }
}

// Handle Delete Partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_partner') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash(__t('Security session error!', 'خطای امنیتی نشست!'), 'danger');
    } else {
        $delId = (int)($_POST['partner_id'] ?? 0);
        if ($delId > 0) {
            $pStmt = $pdo->prepare("SELECT name FROM partners WHERE id = :id AND " . shop_sql());
            $pStmt->execute([':id' => $delId]);
            $pName = $pStmt->fetchColumn() ?: "ID {$delId}";
            $stmt = $pdo->prepare("DELETE FROM partners WHERE id = :id AND " . shop_sql());
            $stmt->execute([':id' => $delId]);
            log_activity($currentUser['id'], 'حذف شریک', "حذف شریک «{$pName}» با شناسه {$delId}");
            set_flash(sprintf(__t('Partner «%s» deleted successfully.', 'شریک «%s» با موفقیت حذف گردید.'), $pName), 'success');
        }
    }
    redirect(BASE_URL . 'modules/partners.php');
}

// Fetch partners
$sorts=['newest'=>['id DESC','Newest first','تازه‌ترین اول'],'oldest'=>['id ASC','Oldest first','قدیمی‌ترین اول'],'name_asc'=>['name ASC,id ASC','Name: A–Z','نام: الف تا ی']];[$list,$partners]=listing_query($pdo,'SELECT * FROM partners WHERE '.shop_sql(),[],$sorts,'oldest');

// Calculate total shop net profit to date
$metrics = get_dashboard_metrics();
$totalNetProfit = $metrics['overall_net_profit'];

// Total capital
$totalCapitalStmt = $pdo->query("SELECT SUM(amount) AS total FROM ".live_documents_source('capital')." WHERE {$accountWhere} AND transaction_type = 'سرمایه' AND " . shop_sql());
$totalCapital = (float)($totalCapitalStmt->fetch()['total'] ?? 0);

$pageTitle = 'مدیریت شریک ها و سهم سرمایه';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h3 class="card-title" style="margin-bottom: 4px;">
                <i class="fa-solid fa-users-gear ml-1"></i> <?= __t('Partnership Accounts & Profit Sharing', 'حساب شرکا و تسهیم مفاد', 'د شریکانو حساب او د ګټې وېش') ?>
            </h3>
            <p style="font-size: 12.5px; color: var(--text-muted);">
                <?= __t('Total Distributable Net Store Profit: ', 'مجموع مفاد خالص قابل تقسیم دکان: ', 'د هټۍ د وېش وړ خالص ګټه: ') ?><strong style="color: var(--success); font-size: 15px;"><?= currency_profit_html($totalNetProfit,$metrics['profit_known']) ?></strong> |
                <?= __t('General Capital: ', 'سرمایه عمومی: ', 'عمومي پانګه: ') ?><strong><?= format_money($totalCapital) ?></strong>
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="<?= BASE_URL ?>modules/mudaraba.php" class="btn btn-primary btn-sm" style="background: var(--success); border-color: var(--success);">
                <i class="fa-solid fa-handshake-angle ml-1"></i> <?= __t('Dedicated Mudaraba Account (Foreign Goods)', 'حساب اختصاصی مضاربت (کالای خارجی)', 'د مضاربت ځانګړی حساب (بهرني توکي)') ?>
            </a>
            <?php if (is_feature_locked('capital_turnover')): ?>
                <button type="button" onclick="showLockModal('capital_turnover', 'Capital Turnover & Cash Flow Management', '<?= __t('Capital Turnover & Cash Flow Management', 'مدیریت گردش سرمایه دکان') ?>');" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-vault ml-1"></i> <?= __t('Capital Turnover Management', 'مدیریت گردش سرمایه دکان', 'د پانګې د څرخ مدیریت') ?>
                    <span class="badge-lock-tag" style="padding: 1px 6px; font-size: 10px;"><i class="fa-solid fa-lock text-amber-500"></i> <?= __t('Locked', 'قفل', 'قفل') ?></span>
                </button>
            <?php else: ?>
                <a href="<?= BASE_URL ?>modules/capital.php" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-vault ml-1"></i> <?= __t('Capital Turnover Management', 'مدیریت گردش سرمایه دکان', 'د پانګې د څرخ مدیریت') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <!-- Add Partner Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-user-plus ml-1"></i> <?= __t('Register New Partner', 'ثبت شریک جدید', 'نوی شریک ثبتول') ?>
            </h3>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_partner">
            <?php currency_fields(['investment_amount'=>2]); ?>

            <div class="form-group">
                <label class="form-label" for="name">
                    <?= __t('Partner Name: *', 'نام شریک: *', 'د شریک نوم: *') ?>
                </label>
                <input type="text" id="name" name="name" class="form-control" required placeholder="<?= __t('Full Partner Name', 'نام کامل شریک', 'د شریک بشپړ نوم') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="phone"><?= __t('Phone Number:', 'شماره تماس:', 'د تلیفون شمېره:') ?></label>
                <input type="text" id="phone" name="phone" class="form-control numeric-input" placeholder="07xxxxxxxx">
            </div>

            <div class="form-group">
                <label class="form-label" for="share_percentage">
                    <?= __t('Profit Share (%): *', 'فیصدی سهم از مفاد (٪): *', 'د ګټې سلنه (٪): *') ?>
                </label>
                <input type="number" step="any" id="share_percentage" name="share_percentage" class="form-control numeric-input" min="0" max="100" required placeholder="<?= __t('e.g. 50', 'مثلاً 50', 'لکه 50') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="investment_amount"><?= sprintf(__t('Initial Investment Capital (%s):', 'مبلغ سرمایه گذاری اولیه (%s):', 'د لومړنۍ پانګې اندازه (%s):'), get_currency_label()) ?></label>
                <input type="number" step="any" id="investment_amount" name="investment_amount" class="form-control numeric-input" value="0" placeholder="0">
            </div>

            <div class="form-group">
                <label class="form-label" for="join_date"><?= __t('Partnership Start Date:', 'تاریخ آغاز همکاری:', 'د همکارۍ د پیل نېټه:') ?></label>
                <input type="date" id="join_date" name="join_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="notes"><?= __t('Partnership Terms & Notes:', 'توضیحات و شرایط شراکت:', 'د شراکت شرایط:') ?></label>
                <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="<?= __t('Partnership agreement details and terms...', 'توضیحات درباره قرارداد شراکت...', 'د شراکت تړون په اړه توضیحات...') ?>"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                <i class="fa-solid fa-check ml-1"></i> <?= __t('Save Partner Details', 'ثبت مشخصات شریک', 'د شریک ثبتول') ?>
            </button>
        </form>
    </div>

    <!-- Partners Table & Profit Share Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-chart-pie ml-1"></i> <?= __t('Partners List & Current Profit Distribution', 'وضعیت سهم و مفاد شرکا', 'د شریکانو د ګټې وېش حالت') ?>
            </h3>
            <span class="badge badge-secondary"><?= $currentLang === 'en' ? count($partners) . ' partners' : to_persian_num(count($partners)) . ' شریک ثبت شده' ?></span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= __t('Partner Name', 'نام شریک', 'د شریک نوم') ?></th>
                        <th><?= __t('Phone', 'تماس', 'تلیفون') ?></th>
                        <th><?= __t('Share %', 'درصد سهم', 'د ونډې سلنه') ?></th>
                        <th><?= __t('Capital Invested', 'آورده اولیه', 'لومړنۍ پانګه') ?></th>
                        <th><?= __t('Current Profit Share', 'سهم از مفاد فعلی', 'له اوسنۍ ګټې ونډه') ?></th>
                        <th><?= __t('Actions', 'عملیات', 'کړنې') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($partners)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 20px;">
                                <?= __t('No partners registered yet.', 'هیچ شریکی ثبت نشده است.', 'تر اوسه هېڅ شریک نه دی ثبت شوی.') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($partners as $part): 
                            $sharePct = (float)$part['share_percentage'];
                            $partnerProfitShare = ($totalNetProfit > 0) ? ($totalNetProfit * ($sharePct / 100)) : 0;
                        ?>
                            <tr>
                                <td>
                                    <strong><?= sanitize($part['name']) ?></strong>
                                    <?php if (!empty($part['notes'])): ?>
                                        <div style="font-size: 11px; color: var(--text-muted);"><?= sanitize($part['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= sanitize($part['phone'] ?: '-') ?></td>
                                <td>
                                    <span class="badge badge-primary" style="font-size: 12px;"><?= $currentLang === 'en' ? $sharePct : to_persian_num($sharePct) ?>%</span>
                                </td>
                                <td><?= currency_format($part['investment_amount'],$part['currency_code']) ?></td>
                                <td>
                                    <strong style="color: <?= $partnerProfitShare >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                                        <?= currency_profit_html($partnerProfitShare,$metrics['profit_known']) ?>
                                    </strong>
                                </td>
                                <td style="white-space: nowrap;">
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="openEditPartnerModal(<?= htmlspecialchars(json_for_html($part), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="fa-solid fa-pen-to-square"></i> <?= __t('Edit', 'ویرایش سهم', 'سمول') ?>
                                    </button>
                                    <form method="POST" action="" style="display: inline-block;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_partner">
                                        <input type="hidden" name="partner_id" value="<?= $part['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(<?= sanitize(json_for_html(sprintf(__t('Are you sure you want to remove partner «%s»?', 'آیا از حذف شریک «%s» اطمینان کامل دارید؟', 'ایا باوري یاست چې دا شریک ړنګ کړئ؟'), $part['name']))) ?>);" title="<?= __t('Delete Partner', 'حذف شریک', 'شریک ړنګول') ?>">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Partner Modal -->
<div id="editPartnerModal" class="feature-modal-backdrop" onclick="if(event.target === this) closeEditPartnerModal();">
    <div class="feature-modal-box" style="max-width: 460px; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <button type="button" class="feature-modal-close" onclick="closeEditPartnerModal();">&times;</button>
        <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 14px; color: var(--text-main);">
            <i class="fa-solid fa-pen-to-square ml-1" style="color: var(--info);"></i> <?= __t('Edit Partner Details & Profit Share', 'ویرایش مشخصات و درصد سهم شریک', 'د شریک د ونډې سمول') ?>
        </h3>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_partner">
            <?php currency_fields(['investment_amount'=>2]); ?>
            <input type="hidden" id="editPartnerId" name="partner_id" value="0">

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label"><?= __t('Partner Name:', 'نام شریک:', 'د شریک نوم:') ?></label>
                <input type="text" id="editPartnerName" name="name" class="form-control" required>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label"><?= __t('Phone Number:', 'شماره تماس:', 'د تلیفون شمېره:') ?></label>
                <input type="text" id="editPartnerPhone" name="phone" class="form-control numeric-input">
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label"><?= __t('Profit Share Percentage (%):', 'درصد سهم از مفاد (٪):', 'د ګټې سلنه (٪):') ?></label>
                <input type="number" step="any" id="editPartnerShare" name="share_percentage" class="form-control numeric-input" min="0" max="100" required>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label"><?= sprintf(__t('Investment Capital (%s):', 'مبلغ آورده / سرمایه‌گذاری (%s):', 'د پانګې اندازه (%s):'), get_currency_label()) ?></label>
                <input type="number" step="any" id="editPartnerInv" name="investment_amount" class="form-control numeric-input">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label"><?= __t('Agreement Notes:', 'یادداشت و توافقات:', 'توضیحات او هوکړه:') ?></label>
                <textarea id="editPartnerNotes" name="notes" class="form-control" rows="2"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 10px;">
                <i class="fa-solid fa-floppy-disk ml-1"></i> <?= __t('Save Changes', 'ذخیره تغییرات سهم', 'بدلونونه خوندي کړئ') ?>
            </button>
        </form>
    </div>
</div>

<script>
function openEditPartnerModal(part) {
    MoneyForm.setCurrency(document.getElementById('editPartnerInv').form,part.currency_code);
    document.getElementById('editPartnerId').value = part.id;
    document.getElementById('editPartnerName').value = part.name;
    document.getElementById('editPartnerPhone').value = part.phone || '';
    document.getElementById('editPartnerShare').value = part.share_percentage;
    MoneyForm.setLedger(document.getElementById('editPartnerInv').form,'investment_amount',part.investment_amount || 0);
    document.getElementById('editPartnerNotes').value = part.notes || '';
    document.getElementById('editPartnerModal').style.display = 'flex';
}
function closeEditPartnerModal() {
    document.getElementById('editPartnerModal').style.display = 'none';
}
</script>

<?php listing_controls_form($list);listing_footer($list);require_once __DIR__ . '/../includes/footer.php'; ?>
