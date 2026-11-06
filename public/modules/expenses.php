<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Shop Expenses Module (مدیریت مصارف دکان - تفکیک شده از ضرر)
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

$pageTitle = 'مدیریت مصارف دکان';
$error = '';
$success = '';

// Self-healing: ensure expenses table has required columns
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $cols = $pdo->query("PRAGMA table_info(expenses)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $cols = array_map('strtolower', $cols ?: []);
        if (!in_array('expense_type', $cols)) $pdo->exec("ALTER TABLE expenses ADD COLUMN expense_type VARCHAR(20) DEFAULT 'مصارف'");
    } else {
        $cols = $pdo->query("SHOW COLUMNS FROM `expenses`")->fetchAll(PDO::FETCH_COLUMN, 0);
        $cols = array_map('strtolower', $cols ?: []);
        if (!in_array('expense_type', $cols)) {
            $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `expense_type` VARCHAR(20) NOT NULL DEFAULT 'مصارف'");
        }
    }
} catch (Exception $e) {}

$currencyContext=currency_prepare_request(['add_expense'=>['amount'=>2]],'',true);

// Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!');
    } else {
        $category    = trim($_POST['category'] ?? 'متفرقه');
        $amount      = (float)clean_number($_POST['amount'] ?? '0', true);
        $expenseDate = !empty($_POST['expense_date']) ? $_POST['expense_date'] : date('Y-m-d');
        $description = trim($_POST['description'] ?? '');
        $exactNow    = date('Y-m-d H:i:s');

        if (is_feature_locked('expenses_module') || is_feature_locked('shop_expenses_entry')) {
            $error = __t('Expenses module is locked. Contact administration ask central administration to enable access.', 'بخش مصارف دکان در حال حاضر قفل می‌باشد! جهت فعال‌سازی می‌توانید با پین‌کد  وارد پنل سوپر ادمین شده و قفل آن را باز فرمایید.');
        } elseif ($amount <= 0) {
            $error = __t('Please enter a valid expense amount.', 'لطفاً مبلغ مصرف را به درستی وارد نمایید.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO expenses (expense_type, category, amount, expense_date, description, created_by, created_at, shop_id) 
                                       VALUES ('مصارف', :cat, :amt, :edate, :desc, :uid, :cdate, " . shop_id() . ")");
                $stmt->execute([
                    ':cat'   => $category,
                    ':amt'   => $amount,
                    ':edate' => $expenseDate,
                    ':desc'  => $description,
                    ':uid'   => $currentUser['id'],
                    ':cdate' => $exactNow
                ]);
            } catch (Exception $insEx) {
                $stmt = $pdo->prepare("INSERT INTO expenses (category, amount, expense_date, description, created_by, created_at, shop_id) 
                                       VALUES (:cat, :amt, :edate, :desc, :uid, :cdate, " . shop_id() . ")");
                $stmt->execute([
                    ':cat'   => $category,
                    ':amt'   => $amount,
                    ':edate' => $expenseDate,
                    ':desc'  => $description,
                    ':uid'   => $currentUser['id'],
                    ':cdate' => $exactNow
                ]);
            }

            try {
            currency_record('expenses',(int)$pdo->lastInsertId(),$currencyContext,['amount'=>$amount]);
            currency_request_commit();
            log_activity($currentUser['id'], 'ثبت مصرف دکان', "ثبت مصرف در بخش {$category} به مبلغ " . currency_format($amount,$currencyContext['code']));
            set_flash(__t('Store expense recorded successfully.', 'مصرف دکان با موفقیت ثبت گردید.'), 'success');
            redirect(BASE_URL . 'modules/expenses.php');
            } catch(Throwable $e){currency_request_rollback();http_response_code(422);$error=public_error($e);}

        }
    }
}

// Handle Delete Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_expense') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash(__t('Security session error!', 'خطای امنیتی نشست!'), 'danger');
    } else {
        $delId = (int)($_POST['expense_id'] ?? 0);
        if ($delId > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = :id AND " . shop_sql());
                $stmt->execute([':id' => $delId]);
                log_activity($currentUser['id'], 'حذف مصرف دکان', "حذف رکورد مصرف شناسه {$delId}");
                set_flash(__t('Expense record deleted successfully.', 'مورد با موفقیت حذف گردید.'), 'success');
            } catch (Exception $e) {
                set_flash(__t('Error deleting expense: ', 'خطا در حذف رکورد: ') . public_error($e), 'danger');
            }
        }
    }
    redirect(BASE_URL . 'modules/expenses.php');
}

// Filters
$catFilter = trim($_GET['category'] ?? '');
$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to'] ?? '');

$hasExpenseType = false;
try {
    $test = $pdo->query("SELECT expense_type FROM ".live_documents_source('expenses')." LIMIT 1");
    $hasExpenseType = true;
} catch (Exception $e) {}

$where = [currency_sql('e'), shop_sql('e')];
$params = [];

if ($hasExpenseType) {
    $where[] = "e.expense_type = 'مصارف'";
}
if ($catFilter !== '') {
    $where[] = "e.category = :cat";
    $params[':cat'] = $catFilter;
}
if ($dateFrom !== '') {
    $where[] = "e.expense_date >= :dfrom";
    $params[':dfrom'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = "e.expense_date <= :dto";
    $params[':dto'] = $dateTo;
}

$whereSql = "WHERE " . implode(" AND ", $where);

// Fetch list of expenses
$expenses = [];
try {
    $sql = "SELECT e.*, u.fullname as user_name FROM ".live_documents_source('expenses','e')." LEFT JOIN users u ON e.created_by = u.id {$whereSql}";
    $sorts=['newest'=>['e.id DESC','Newest first','تازه‌ترین اول'],'oldest'=>['e.id ASC','Oldest first','قدیمی‌ترین اول'],'amount_high'=>['e.amount DESC,e.id DESC','Amount: high to low','مبلغ: زیاد به کم'],'amount_low'=>['e.amount ASC,e.id ASC','Amount: low to high','مبلغ: کم به زیاد']];
    [$list,$expenses]=listing_query($pdo,$sql,$params,$sorts);
} catch (Exception $ex) {
    $expenses = [];
}

// Totals
$totExpAll   = 0.0;
$totExpMonth = 0.0;
$totExpToday = 0.0;

try {
    if ($hasExpenseType) {
        $totExpAll = (float)($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM ".live_documents_source('expenses')." WHERE {$accountWhere} AND expense_type = 'مصارف' AND " . shop_sql())->fetchColumn());
        
        $curMonth = date('Y-m');
        $stmtM = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM ".live_documents_source('expenses')." WHERE {$accountWhere} AND expense_type = 'مصارف' AND expense_date LIKE ? AND shop_id=?");
        $stmtM->execute([$curMonth . '%', shop_id()]);
        $totExpMonth = (float)$stmtM->fetchColumn();

        $curToday = date('Y-m-d');
        $stmtT = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM ".live_documents_source('expenses')." WHERE {$accountWhere} AND expense_type = 'مصارف' AND expense_date = ? AND shop_id=?");
        $stmtT->execute([$curToday, shop_id()]);
        $totExpToday = (float)$stmtT->fetchColumn();
    } else {
        $totExpAll = (float)($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM ".live_documents_source('expenses')." WHERE {$accountWhere} AND " . shop_sql())->fetchColumn());
    }
} catch (Exception $e) {}

$isExpensesLocked = is_feature_locked('expenses_module') || is_feature_locked('shop_expenses_entry');

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($isExpensesLocked): ?>
<div class="card" style="border: 2px dashed #f59e0b; background: var(--warning-soft); padding: 18px 24px; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 44px; height: 44px; background: var(--warning-soft); color: var(--warning); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                <i class="fa-solid fa-lock"></i>
            </div>
            <div>
                <div style="font-size: 15px; font-weight: 800; color: var(--warning);"><?= __t('Expenses Module is Currently Locked', 'گزینه مصارف دکان در حال حاضر قفل می‌باشد', 'د هټۍ لګښتونه قفل دي') ?></div>
                <div style="font-size: 12.5px; color: var(--warning); margin-top: 2px;">
                    <?= __t('Expense entry is locked. You can unlock it in the Super Admin panel after two-step verification.', 'طبق تنظیمات امنیتی، ثبت و ویرایش مصارف قفل است. کنترل و بازگشایی آن در پنل سوپر ادمین (پین ) انجام می‌شود.', 'د لګښتونو برخه قفل ده.') ?>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <?php if (!empty($_SESSION['super_admin_verified']) || ($currentUser['username'] ?? '') === 'nikadmin'): ?>
                <a href="<?= BASE_URL ?>admin/office_2.php" class="btn btn-warning btn-sm" style="font-weight: 700; background: #f59e0b; color: var(--text-main); border: none;">
                    <i class="fa-solid fa-unlock-keyhole ml-1"></i> <?= __t('Unlock in Super Admin', 'بازگشایی در پنل سوپر ادمین', 'په سوپر اډمین کې خلاصول') ?>
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-warning btn-sm" onclick="showLockModal('expenses_module', 'Shop Expenses Module', '<?= __t('Shop Expenses Module', 'مدیریت مصارف دکان') ?>')" style="font-weight: 700;">
                    <i class="fa-solid fa-shield-halved ml-1"></i> <?= __t('View License Details', 'مشاهده جزئیات لایسنس', 'د لایسنس تفصیل') ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h1 style="font-size: 20px; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-receipt text-primary"></i>
                <?= __t('Operating Expenses Management (Segregated from Losses)', 'مدیریت مصارف جاری دکان (تفکیک شده از ضرر)', 'د هټۍ د روانو لګښتونو مدیریت (له تاوان څخه جلا)') ?>
            </h1>
            <p style="font-size: 13px; color: var(--text-muted); margin: 4px 0 0 0;">
                <?= __t('Record store operational expenses: rent, electricity, meals, wages, and transport', 'ثبت و محاسبه مصارف عملیاتی، کرایه، بل برق، نان، معاشات و ترانسپورت دکان', 'د کرایې، برېښنا، معاشونو او ورځنیو لګښتونو ثبتول') ?>
            </p>
        </div>

        <div style="display: flex; gap: 8px;">
            <a href="<?= BASE_URL ?>modules/losses.php" class="btn btn-secondary" style="color: var(--danger); border-color: #fca5a5; background: #fff5f5;">
                <i class="fa-solid fa-triangle-exclamation ml-1"></i> <?= __t('Go to Damaged Goods & Losses', 'رفتن به ضرر و خسارات اجناس', 'د توکو زیان او تاوان ته تلل') ?>
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fa-solid fa-print ml-1"></i> <?= __t('Print Table', 'چاپ جدول', 'جدول چاپ کړئ') ?>
            </button>
        </div>
    </div>
</div>

<!-- METRIC STATS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div style="background: var(--surface-muted); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; text-align: center;">
        <div style="font-size: 12px; color: var(--text-muted); font-weight: 700; margin-bottom: 4px;">
            <i class="fa-solid fa-calendar-day ml-1"></i> <?= __t("Today's Store Expenses", 'مصارف امروز دکان', 'د نن ورځې لګښتونه') ?>
        </div>
        <div style="font-size: 22px; font-weight: 800; color: var(--info);"><?= format_money($totExpToday) ?></div>
        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;"><?= __t('Date: ', 'تاریخ: ', 'نېټه: ') ?><?= date('Y/m/d') ?></div>
    </div>

    <div style="background: var(--info-soft); border: 1px solid #bfdbfe; border-radius: 12px; padding: 16px; text-align: center;">
        <div style="font-size: 12px; color: var(--info); font-weight: 700; margin-bottom: 4px;">
            <i class="fa-solid fa-calendar-days ml-1"></i> <?= __t('Current Month Expenses', 'مجموع مصارف ماه جاری', 'د روانې میاشتې لګښتونه') ?>
        </div>
        <div style="font-size: 22px; font-weight: 800; color: var(--info);"><?= format_money($totExpMonth) ?></div>
        <div style="font-size: 11px; color: var(--info); margin-top: 4px;"><?= __t('Expenses this month', 'مصارف این ماه', 'د روانې میاشتې لګښتونه') ?></div>
    </div>

    <div style="background: var(--warning-soft); border: 1px solid #fde68a; border-radius: 12px; padding: 16px; text-align: center;">
        <div style="font-size: 12px; color: var(--warning); font-weight: 700; margin-bottom: 4px;">
            <i class="fa-solid fa-vault ml-1"></i> <?= __t('Total General Operating Expenses', 'مجموع کل مصارف عمومی', 'ټولټال عمومي لګښتونه') ?>
        </div>
        <div style="font-size: 22px; font-weight: 800; color: var(--warning);"><?= format_money($totExpAll) ?></div>
        <div style="font-size: 11px; color: var(--warning); margin-top: 4px;"><?= __t('Since system initialization', 'از ابتدای ثبت سیستم', 'له پیله تر دې دمه') ?></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <!-- Add Expense Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-plus-circle ml-1"></i> <?= __t('Register New Expense', 'ثبت مصرف جدید', 'نوی لګښت ثبتول') ?>
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
            <input type="hidden" name="action" value="add_expense">
            <?php currency_fields(['amount'=>2]); ?>

            <div class="form-group">
                <label class="form-label" for="category"><?= __t('Expense Category: *', 'بخش / کتگوری مصرف: *', 'د لګښت کټګوري: *') ?></label>
                <select name="category" id="category" class="form-control" required>
                    <option value="کرایه دکان"><?= __t('Store Rent', 'کرایه دکان', 'د هټۍ کرایه') ?></option>
                    <option value="بل برق"><?= __t('Electricity Bill', 'بل برق', 'د برېښنا بل') ?></option>
                    <option value="نان و چای و غذا"><?= __t('Food, Tea & Meals', 'نان و چای و غذا', 'ډوډۍ او چای') ?></option>
                    <option value="اینترنت و مخابرات"><?= __t('Internet & Communications', 'اینترنت و مخابرات', 'انټرنیټ او اړیکې') ?></option>
                    <option value="معاش شاگرد و پرسنل"><?= __t('Staff Wages & Salaries', 'معاش شاگرد و پرسنل', 'د کارکوونکو معاش') ?></option>
                    <option value="مالیه و شاروالی"><?= __t('Taxes & Municipality Fees', 'مالیه و شاروالی', 'مالیات او ښاروالي') ?></option>
                    <option value="ترانسپورت و کرایه موتر"><?= __t('Transport & Freight', 'ترانسپورت و کرایه موتر', 'ترانسپورت او بار وړل') ?></option>
                    <option value="وسایل و لوازم مصرفی دکان"><?= __t('Store Supplies & Consumables', 'وسایل و لوازم مصرفی دکان', 'د هټۍ مصرفي وسایل') ?></option>
                    <option value="متفرقه"><?= __t('Miscellaneous', 'متفرقه', 'متفرقه') ?></option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="amount"><?= sprintf(__t('Expense Amount (%s): *', 'مبلغ مصرف (%s): *', 'د لګښت اندازه (%s): *'), get_currency_label()) ?></label>
                <input type="number" step="any" min="1" id="amount" name="amount" class="form-control" required placeholder="<?= __t('e.g. 500', 'مثلاً: ۵۰۰', 'لکه: ۵۰۰') ?>" style="font-weight: bold; color: var(--info);">
            </div>

            <div class="form-group">
                <label class="form-label" for="expense_date"><?= __t('Expense Date:', 'تاریخ مصرف:', 'د لګښت نېټه:') ?></label>
                <input type="date" id="expense_date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="description"><?= __t('Description & Details:', 'شرح و جزئیات مصرف:', 'تفصیل او شرح:') ?></label>
                <textarea id="description" name="description" class="form-control" rows="2" placeholder="<?= __t('Optional notes regarding this expense...', 'توضیحات اختیاری در مورد این هزینه...', 'د لګښت په اړه اضافي یادښت...') ?>"></textarea>
            </div>

            <?php if ($isExpensesLocked): ?>
                <button type="button" class="btn btn-warning" onclick="showLockModal('expenses_module', 'Shop Expenses Module', '<?= __t('Shop Expenses Module', 'مدیریت مصارف دکان') ?>')" style="width: 100%; padding: 11px; font-weight: 700; background: var(--warning-soft); color: var(--warning); border: 1px solid #fde68a;">
                    <i class="fa-solid fa-lock ml-1 text-amber-600"></i> <?= __t('This module is locked (View License)', 'این بخش قفل است (مشاهده لایسنس)', 'دا برخه قفل ده') ?>
                </button>
            <?php else: ?>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; font-weight: 700;">
                    <i class="fa-solid fa-check ml-1"></i> <?= __t('Submit Store Expense', 'ثبت نهایی مصرف', 'لګښت ثبت کړئ') ?>
                </button>
            <?php endif; ?>
        </form>
    </div>

    <!-- Expenses List Table -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h3 class="card-title">
                <i class="fa-solid fa-list ml-1"></i> <?= __t('Recorded Store Expenses History', 'تاریخچه مصارف ثبت شده دکان', 'د ثبت شویو لګښتونو تاریخچه') ?>
            </h3>
            <span class="badge badge-secondary"><?= $currentLang === 'en' ? count($expenses) . ' records' : count($expenses) . ' رکورد مصرف' ?></span>
        </div>

        <!-- FILTER -->
        <form method="GET" action="" style="background: var(--surface-muted); padding: 10px; border-radius: 8px; margin-bottom: 14px; display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 1; min-width: 130px;">
                <label style="font-size: 11px; font-weight: 700; color: var(--text-muted);"><?= __t('Category:', 'کتگوری مصرف:', 'کټګوري:') ?></label>
                <select name="category" class="form-control" style="font-size: 12px; padding: 6px;">
                    <option value=""><?= __t('All Categories', 'همه موارد', 'ټول موارد') ?></option>
                    <option value="کرایه دکان" <?= $catFilter === 'کرایه دکان' ? 'selected' : '' ?>><?= __t('Store Rent', 'کرایه دکان', 'د هټۍ کرایه') ?></option>
                    <option value="بل برق" <?= $catFilter === 'بل برق' ? 'selected' : '' ?>><?= __t('Electricity', 'بل برق', 'د برېښنا بل') ?></option>
                    <option value="نان و چای و غذا" <?= $catFilter === 'نان و چای و غذا' ? 'selected' : '' ?>><?= __t('Food & Tea', 'نان و چای', 'ډوډۍ او چای') ?></option>
                    <option value="اینترنت و مخابرات" <?= $catFilter === 'اینترنت و مخابرات' ? 'selected' : '' ?>><?= __t('Internet', 'اینترنت', 'انټرنیټ') ?></option>
                    <option value="معاش شاگرد و پرسنل" <?= $catFilter === 'معاش شاگرد و پرسنل' ? 'selected' : '' ?>><?= __t('Staff Wages', 'معاش شاگرد', 'معاش') ?></option>
                    <option value="مالیه و شاروالی" <?= $catFilter === 'مالیه و شاروالی' ? 'selected' : '' ?>><?= __t('Taxes', 'مالیه', 'مالیات') ?></option>
                    <option value="ترانسپورت و کرایه موتر" <?= $catFilter === 'ترانسپورت و کرایه موتر' ? 'selected' : '' ?>><?= __t('Transport', 'ترانسپورت', 'ترانسپورت') ?></option>
                </select>
            </div>
            <div style="flex: 1; min-width: 120px;">
                <label style="font-size: 11px; font-weight: 700; color: var(--text-muted);"><?= __t('From Date:', 'از تاریخ:', 'له نېټې:') ?></label>
                <input type="date" name="date_from" class="form-control" style="font-size: 12px; padding: 6px;" value="<?= sanitize($dateFrom) ?>">
            </div>
            <div style="flex: 1; min-width: 120px;">
                <label style="font-size: 11px; font-weight: 700; color: var(--text-muted);"><?= __t('To Date:', 'تا تاریخ:', 'تر نېټې:') ?></label>
                <input type="date" name="date_to" class="form-control" style="font-size: 12px; padding: 6px;" value="<?= sanitize($dateTo) ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-primary" style="height: 35px; padding: 0 12px;"><?= __t('Filter', 'فیلتر', 'فلټر') ?></button>
            <a href="<?= BASE_URL ?>modules/expenses.php" class="btn btn-sm btn-secondary" style="height: 35px; padding: 0 10px;" title="<?= __t('Reset Filter', 'حذف فیلتر', 'فلټر لرې کول') ?>"><i class="fa-solid fa-rotate-left"></i></a>
        </form>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">#</th>
                        <th><?= __t('Expense Category', 'بخش مصرف', 'د لګښت برخه') ?></th>
                        <th style="text-align: center;"><?= __t('Amount', 'مبلغ', 'اندازه') ?></th>
                        <th><?= __t('Date', 'تاریخ', 'نېټه') ?></th>
                        <th><?= __t('Description', 'توضیحات', 'تفصیل') ?></th>
                        <th><?= __t('Recorded By', 'ثبت‌کننده', 'ثبتوونکی') ?></th>
                        <th style="width: 60px; text-align: center;"><?= __t('Actions', 'عملیات', 'کړنې') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-light);">
                                <i class="fa-solid fa-receipt" style="font-size: 28px; display: block; margin-bottom: 6px; color: var(--border-dark);"></i>
                                <?= __t('No expense records found matching criteria.', 'هیچ مصرفی مطابق جستجو یافت نشد.', 'د پلټنې سره سم هېڅ لګښت ونه موندل شو.') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $idx => $ex): ?>
                            <tr>
                                <td style="text-align: center; font-weight: 700; color: var(--text-muted);"><?= $currentLang === 'en' ? ($idx + 1) : to_persian_num($idx + 1) ?></td>
                                <td>
                                    <span class="badge" style="background: var(--surface-muted); color: var(--text-main); font-weight: 700;">
                                        <?= sanitize(display_expense_category($ex['category'])) ?>
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: 800; color: var(--text-main);">
                                    <?= format_money($ex['amount'], false) ?> <?= get_currency_label() ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted);">
                                    <?= format_date($ex['expense_date']) ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted); max-width: 200px;">
                                    <?= sanitize($ex['description'] ?: '-') ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted);">
                                    <?= sanitize(display_user_fullname($ex['user_name'] ?: 'مدیر دکان')) ?>
                                </td>
                                <td style="text-align: center;">
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('<?= __t('Are you sure you want to delete this expense record?', 'آیا از حذف این مصرف مطمئن هستید؟', 'ایا باوري یاست چې دا لګښت ړنګ کړئ؟') ?>');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_expense">
                                        <input type="hidden" name="expense_id" value="<?= $ex['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" style="padding: 3px 8px;" title="<?= __t('Delete', 'حذف', 'ړنګول') ?>">
                                            <i class="fa-solid fa-trash-can"></i>
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

<?php if(isset($list)){listing_controls_form($list);listing_footer($list);}require_once __DIR__ . '/../includes/footer.php'; ?>
