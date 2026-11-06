<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Losses and Damaged Goods Module (ضرر و خسارات اجناس)
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

$pageTitle = 'ضرر و خسارات اجناس';
$error = '';
$success = '';

// Self-healing: ensure expenses and stock tables have all required columns
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $cols = $pdo->query("PRAGMA table_info(expenses)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $cols = array_map('strtolower', $cols ?: []);
        if (!in_array('expense_type', $cols)) $pdo->exec("ALTER TABLE expenses ADD COLUMN expense_type VARCHAR(20) DEFAULT 'مصارف'");
        if (!in_array('stock_id', $cols)) $pdo->exec("ALTER TABLE expenses ADD COLUMN stock_id INTEGER DEFAULT NULL");
        if (!in_array('purchase_price', $cols)) $pdo->exec("ALTER TABLE expenses ADD COLUMN purchase_price DECIMAL(15,2) DEFAULT 0.00");
        if (!in_array('sale_price', $cols)) $pdo->exec("ALTER TABLE expenses ADD COLUMN sale_price DECIMAL(15,2) DEFAULT 0.00");
        if (!in_array('loss_qty', $cols)) $pdo->exec("ALTER TABLE expenses ADD COLUMN loss_qty INTEGER DEFAULT 1");

        $sCols = $pdo->query("PRAGMA table_info(stock)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $sCols = array_map('strtolower', $sCols ?: []);
        if (!in_array('damaged_qty', $sCols)) $pdo->exec("ALTER TABLE stock ADD COLUMN damaged_qty INTEGER DEFAULT 0");
        if (!in_array('is_damaged', $sCols)) $pdo->exec("ALTER TABLE stock ADD COLUMN is_damaged INTEGER DEFAULT 0");
        if (!in_array('damage_notes', $sCols)) $pdo->exec("ALTER TABLE stock ADD COLUMN damage_notes VARCHAR(255) DEFAULT ''");
    } else {
        $cols = $pdo->query("SHOW COLUMNS FROM `expenses`")->fetchAll(PDO::FETCH_COLUMN, 0);
        $cols = array_map('strtolower', $cols ?: []);
        if (!in_array('expense_type', $cols)) $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `expense_type` VARCHAR(20) NOT NULL DEFAULT 'مصارف'");
        if (!in_array('stock_id', $cols)) $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `stock_id` INT NULL DEFAULT NULL");
        if (!in_array('purchase_price', $cols)) $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `purchase_price` DECIMAL(15,2) NULL DEFAULT 0.00");
        if (!in_array('sale_price', $cols)) $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `sale_price` DECIMAL(15,2) NULL DEFAULT 0.00");
        if (!in_array('loss_qty', $cols)) $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `loss_qty` INT DEFAULT 1");

        try {
            $sCols = $pdo->query("SHOW COLUMNS FROM `stock`")->fetchAll(PDO::FETCH_COLUMN, 0);
            $sCols = array_map('strtolower', $sCols ?: []);
            if (!in_array('damaged_qty', $sCols)) $pdo->exec("ALTER TABLE `stock` ADD COLUMN `damaged_qty` INT NOT NULL DEFAULT 0");
            if (!in_array('is_damaged', $sCols)) $pdo->exec("ALTER TABLE `stock` ADD COLUMN `is_damaged` TINYINT(1) DEFAULT 0");
            if (!in_array('damage_notes', $sCols)) $pdo->exec("ALTER TABLE `stock` ADD COLUMN `damage_notes` VARCHAR(255) DEFAULT ''");
        } catch (Exception $scEx) {}
    }
} catch (Exception $colEx) {}

$currencyContext=currency_prepare_request(['add_loss'=>['amount'=>2]],'',true);

// Handle Add Loss
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_loss') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!');
    } else {
        $category    = trim($_POST['category'] ?? 'آسیب کالا');
        $amount      = (float)clean_number($_POST['amount'] ?? '0', true);
        $lossDate    = !empty($_POST['loss_date']) ? $_POST['loss_date'] : date('Y-m-d');
        $stockId     = !empty($_POST['stock_id']) ? (int)$_POST['stock_id'] : null;
        $lossQty     = !empty($_POST['loss_qty']) ? (float)$_POST['loss_qty'] : 1;
        $description = trim($_POST['description'] ?? '');
        $exactNow    = date('Y-m-d H:i:s');

        if ($amount <= 0) {
            $error = __t('Please enter a valid loss amount.', 'لطفاً مبلغ ضرر را به درستی وارد نمایید.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO expenses (expense_type, category, amount, expense_date, description, stock_id, loss_qty, created_by, created_at, shop_id) 
                                       VALUES ('ضرر', :cat, :amt, :ldate, :desc, :sid, :lqty, :uid, :cdate, " . shop_id() . ")");
                $stmt->execute([
                    ':cat'   => $category,
                    ':amt'   => $amount,
                    ':ldate' => $lossDate,
                    ':desc'  => $description,
                    ':sid'   => $stockId,
                    ':lqty'  => $lossQty,
                    ':uid'   => $currentUser['id'],
                    ':cdate' => $exactNow
                ]);
            } catch (Exception $insEx) {
                $stmt = $pdo->prepare("INSERT INTO expenses (category, amount, expense_date, description, created_by, created_at, shop_id) 
                                       VALUES (:cat, :amt, :ldate, :desc, :uid, :cdate, " . shop_id() . ")");
                $stmt->execute([
                    ':cat'   => $category,
                    ':amt'   => $amount,
                    ':ldate' => $lossDate,
                    ':desc'  => $description,
                    ':uid'   => $currentUser['id'],
                    ':cdate' => $exactNow
                ]);
            }

            $lossId=(int)$pdo->lastInsertId();
            // Deduct from stock if linked
            if ($stockId) {
                $stkStmt = $pdo->prepare("SELECT * FROM stock WHERE id = :id AND " . shop_sql().finance_lock());
                $stkStmt->execute([':id' => $stockId]);
                $stkItem = $stkStmt->fetch();

                if ($stkItem) {
                    $lossQty=inventory_quantity($lossQty);
                    $curQty = (float)$stkItem['quantity'];
                    if(dec_units($lossQty,3)>dec_units(dec_sub($stkItem['quantity'],$stkItem['reserved_qty'],3),3)){currency_request_rollback();set_flash(__t('Loss quantity exceeds available inventory.','مقدار خسارت از موجودی بیشتر است.','د تاوان مقدار له موجودي زیات دی.'),'danger');redirect(BASE_URL.'modules/losses.php');}
                    $inventoryCost=inventory_cost_for_quantity($stkItem,$lossQty);
                    $pdo->prepare('UPDATE expenses SET inventory_cost=?,inventory_currency_code=? WHERE id=? AND shop_id=?')->execute([$inventoryCost,$stkItem['currency_code'],$lossId,shop_id()]);
                    $sName  = $stkItem['name'];
                    $lossAction = trim($_POST['stock_loss_action'] ?? 'strike');

                    if ($lossAction === 'delete') {
                        throw new InvalidArgumentException('Preserve stock history; record damaged quantities instead of deleting the item.');
                        $pdo->prepare("DELETE FROM stock WHERE id = :id AND " . shop_sql())->execute([':id' => $stockId]);
                    } else {
                        $newHealthyQty = max(0, $curQty - $lossQty);
                        $newDamagedQty = dec_add($stkItem['damaged_qty'] ?? 0,$lossQty,3);
                        $unitCost = (float)$stkItem['purchase_price'] + (float)$stkItem['expenses'];
                        $newCapital = dec_sub(inventory_value($stkItem),$inventoryCost);

                        try {
                            $updStmt1 = $pdo->prepare("UPDATE stock 
                                                       SET quantity = :new_qty,
                                                           capital_amount = :new_cap,
                                                           inventory_value = :new_value,
                                                           damaged_qty = :new_dmg,
                                                           is_damaged = 1,
                                                           damage_notes = :cat_note
                                                       WHERE id = :id AND " . shop_sql());
                            $updStmt1->execute([
                                ':new_qty'  => $newHealthyQty,
                                ':new_cap'  => $newCapital,
                                ':new_value'=> $newCapital,
                                ':new_dmg'  => $newDamagedQty,
                                ':cat_note' => $category,
                                ':id'       => $stockId
                            ]);
                        } catch (Exception $upEx1) {
                            try {
                                $pdo->prepare("UPDATE stock SET quantity = :new_qty WHERE id = :id AND " . shop_sql())
                                    ->execute([':new_qty' => $newHealthyQty, ':id' => $stockId]);
                            } catch (Exception $upEx2) {}
                        }
                    }
                }
            }

            currency_record('expenses',$lossId,$currencyContext,['amount'=>$amount]);
            currency_request_commit();
            log_activity($currentUser['id'], 'ثبت ضرر جنس', "ثبت ضرر به مبلغ " . currency_format($amount,$currencyContext['code']) . " بابت «{$category}»");
            set_flash(__t('Damaged goods loss recorded successfully and inventory quantity updated.', 'خسارت جنس با موفقیت در بخش «ضرر و خسارات» ثبت گردید و از تعداد موجودی گدام کسر شد.'), 'success');
            redirect(BASE_URL . 'modules/losses.php');
        }
    }
}

// Handle Delete Loss
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_loss') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash(__t('Security session error!', 'خطای امنیتی نشست!'), 'danger');
    } else {
        $delId = (int)($_POST['loss_id'] ?? 0);
        if ($delId > 0) {
            try {
                $expStmt = $pdo->prepare("SELECT stock_id, loss_qty, inventory_cost FROM ".live_documents_source('expenses')." WHERE id = :id AND " . shop_sql());
                $expStmt->execute([':id' => $delId]);
                $expData = $expStmt->fetch();

                if ($expData && !empty($expData['stock_id']) && (float)($expData['loss_qty'] ?? 0) > 0) {
                    $stkId = (int)$expData['stock_id'];
                    $lQty  = (float)$expData['loss_qty'];

                    $stkStmt = $pdo->prepare("SELECT * FROM stock WHERE id = :id AND " . shop_sql().finance_lock());
                    $stkStmt->execute([':id' => $stkId]);
                    $stkItem = $stkStmt->fetch();

                    if ($stkItem) {
                        $restoredQty = dec_add($stkItem['quantity'],$lQty,3);
                        $restoredDamaged = max(0, (float)($stkItem['damaged_qty'] ?? 0) - $lQty);
                        $isDamaged = ($restoredDamaged > 0) ? 1 : 0;
                        $unitCost = (float)$stkItem['purchase_price'] + (float)$stkItem['expenses'];
                        $restoredCap = dec_add(inventory_value($stkItem),$expData['inventory_cost']??dec_mul($unitCost,$lQty));

                        try {
                            $pdo->prepare("UPDATE stock 
                                           SET quantity = :rqty,
                                               capital_amount = :rcap,
                                               inventory_value = :rvalue,
                                               damaged_qty = :rdmg,
                                               is_damaged = :isdmg
                                           WHERE id = :id AND " . shop_sql())
                                ->execute([
                                    ':rqty'  => $restoredQty,
                                    ':rcap'  => $restoredCap,
                                    ':rvalue'=> $restoredCap,
                                    ':rdmg'  => $restoredDamaged,
                                    ':isdmg' => $isDamaged,
                                    ':id'    => $stkId
                                ]);
                        } catch (Exception $rstEx) {
                            try {
                                $pdo->prepare("UPDATE stock SET quantity = :rqty WHERE id = :id AND " . shop_sql())
                                    ->execute([':rqty' => $restoredQty, ':id' => $stkId]);
                            } catch (Exception $rstEx2) {}
                        }
                    }
                }

                $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = :id AND " . shop_sql());
                $stmt->execute([':id' => $delId]);
                log_activity($currentUser['id'], 'حذف ضرر جنس', "حذف رکورد ضرر شناسه {$delId}");
                set_flash(__t('Loss record removed and items returned to healthy stock.', 'رکورد ضرر حذف شد و تعداد کسر شده مجدداً به موجودی سالم گدام اضافه گردید.'), 'success');
            } catch (Exception $e) {
                set_flash(__t('Error deleting loss: ', 'خطا در حذف رکورد ضرر: ') . public_error($e), 'danger');
            }
        }
    }
    redirect(BASE_URL . 'modules/losses.php');
}

// Filter parameters
$catFilter  = trim($_GET['category'] ?? '');
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');

$where = [currency_sql('e'), shop_sql('e')];
$params = [];

$hasExpenseType = true;
try {
    $pdo->query("SELECT expense_type FROM ".live_documents_source('expenses')." LIMIT 1");
    $where[] = "e.expense_type = 'ضرر'";
} catch (Exception $e) {
    $hasExpenseType = false;
    $where[] = "1=1";
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

// Fetch Losses
$losses = [];
try {
    $sql = "SELECT e.*, u.fullname as user_name, s.name as product_name, s.unit as stock_unit
            FROM ".live_documents_source('expenses','e')." 
            LEFT JOIN users u ON e.created_by = u.id 
            LEFT JOIN stock s ON e.stock_id = s.id 
            {$whereSql} 
           ";
    $sorts=['newest'=>['e.id DESC','Newest first','تازه‌ترین اول'],'oldest'=>['e.id ASC','Oldest first','قدیمی‌ترین اول'],'amount_high'=>['e.amount DESC,e.id DESC','Amount: high to low','مبلغ: زیاد به کم'],'amount_low'=>['e.amount ASC,e.id ASC','Amount: low to high','مبلغ: کم به زیاد']];
    [$list,$losses]=listing_query($pdo,$sql,$params,$sorts);
} catch (Exception $queryEx) {
    try {
        $sqlFallback = "SELECT e.*, u.fullname as user_name, '' as product_name 
                        FROM ".live_documents_source('expenses','e')." 
                        LEFT JOIN users u ON e.created_by = u.id 
                        {$whereSql} 
                       ";
        [$list,$losses]=listing_query($pdo,$sqlFallback,$params,$sorts);
    } catch (Exception $ex2) {
        $losses = [];
    }
}

// Metrics
$totalLossAll = 0.0;
$totalLossMonth = 0.0;
$totalLossToday = 0.0;

try {
    if ($hasExpenseType) {
        $totalLossAll = (float)($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM ".live_documents_source('expenses')." WHERE {$accountWhere} AND expense_type = 'ضرر' AND " . shop_sql())->fetchColumn());
        
        $curMonth = date('Y-m');
        $stmtM = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM ".live_documents_source('expenses')." WHERE {$accountWhere} AND expense_type = 'ضرر' AND (expense_date LIKE ? OR (expense_date IS NULL AND created_at LIKE ?)) AND shop_id=?");
        $stmtM->execute([$curMonth . '%', $curMonth . '%', shop_id()]);
        $totalLossMonth = (float)$stmtM->fetchColumn();

        $curToday = date('Y-m-d');
        $stmtT = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM ".live_documents_source('expenses')." WHERE {$accountWhere} AND expense_type = 'ضرر' AND (expense_date = ? OR (expense_date IS NULL AND (created_at LIKE ? OR DATE(created_at) = ?))) AND shop_id=?");
        $stmtT->execute([$curToday, $curToday . '%', $curToday, shop_id()]);
        $totalLossToday = (float)$stmtT->fetchColumn();
    }
} catch (Exception $metEx) {}

// Stock catalogue for damage selection
$stockItems = [];
$preselectedStockId = (int)($_GET['stock_id'] ?? 0);
try {
    $stockItems = $pdo->query("SELECT id, name, specifications, quantity, unit, purchase_price, expenses, currency_code FROM stock WHERE " . shop_sql() . " ORDER BY name ASC")->fetchAll();
} catch (Exception $stkEx) {}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 20px; font-weight: 800; color: var(--danger); margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-triangle-exclamation text-danger"></i>
                <?= __t('Damaged Goods & Losses Management (Segregated from Expenses)', 'مدیریت ضرر و خسارات اجناس (تفکیک شده از مصارف)', 'د توکو د زیان او تاوان مدیریت (له لګښتونو څخه جلا)') ?>
            </h1>
            <p style="font-size: 13px; color: var(--text-muted); margin: 4px 0 0 0;">
                <?= __t('Record damaged screens, motherboard defects, depreciation or missing inventory', 'ثبت خسارات ناشی از شکستگی صفحه، سوختگی قطعات، افت قیمت یا مفقودی اجناس گدام', 'د سکرین ماتېدل، بورډ سوځېدل او نور تاوانونه ثبتول') ?>
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= BASE_URL ?>modules/expenses.php" class="btn btn-secondary" style="padding: 8px 14px;">
                <i class="fa-solid fa-wallet ml-1"></i> <?= __t('Go to Shop Expenses', 'رفتن به مصارف دکان', 'د هټۍ لګښتونو ته تلل') ?>
            </a>
            <button onclick="window.print()" class="btn btn-secondary" style="padding: 8px 14px;">
                <i class="fa-solid fa-print ml-1"></i> <?= __t('Print Table', 'چاپ جدول', 'جدول چاپ کړئ') ?>
            </button>
        </div>
    </div>
</div>

<!-- LOSS METRICS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div style="background: var(--danger-soft); border: 1px solid #fecaca; border-radius: 12px; padding: 16px; text-align: center;">
        <div style="font-size: 12px; color: var(--danger); font-weight: 700; margin-bottom: 4px;">
            <i class="fa-solid fa-calendar-day ml-1"></i> <?= __t("Today's Losses", 'ضرر ثبت شده امروز', 'د نن ورځې تاوان') ?>
        </div>
        <div style="font-size: 22px; font-weight: 800; color: var(--danger);"><?= format_money($totalLossToday) ?></div>
        <div style="font-size: 11px; color: var(--danger); margin-top: 4px;"><?= __t('Date: ', 'تاریخ: ', 'نېټه: ') ?><?= date('Y/m/d') ?></div>
    </div>

    <div style="background: var(--warning-soft); border: 1px solid #fed7aa; border-radius: 12px; padding: 16px; text-align: center;">
        <div style="font-size: 12px; color: #9a3412; font-weight: 700; margin-bottom: 4px;">
            <i class="fa-solid fa-calendar-days ml-1"></i> <?= __t('Current Month Losses', 'ضرر این ماه', 'د دې میاشتې تاوان') ?>
        </div>
        <div style="font-size: 22px; font-weight: 800; color: #ea580c;"><?= format_money($totalLossMonth) ?></div>
        <div style="font-size: 11px; color: #9a3412; margin-top: 4px;"><?= __t('Damaged goods this month', 'مجموع ضرر ماه جاری', 'د روانې میاشتې ټولټال') ?></div>
    </div>

    <div style="background: var(--purple-soft); border: 1px solid #e9d5ff; border-radius: 12px; padding: 16px; text-align: center;">
        <div style="font-size: 12px; color: var(--purple); font-weight: 700; margin-bottom: 4px;">
            <i class="fa-solid fa-chart-pie ml-1"></i> <?= __t('Total All-Time Losses', 'مجموع کل ضررها', 'ټولټال تاوان') ?>
        </div>
        <div style="font-size: 22px; font-weight: 800; color: var(--purple);"><?= format_money($totalLossAll) ?></div>
        <div style="font-size: 11px; color: var(--purple); margin-top: 4px;"><?= __t('Since store initialization', 'از ابتدای ثبت برنامه', 'له پیله تر دې دمه') ?></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <!-- Add Loss Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title" style="color: var(--danger);">
                <i class="fa-solid fa-circle-minus ml-1"></i> <?= __t('Register Damaged Goods / Loss', 'ثبت ضرر و خسارت جنس', 'د توکو د تاوان ثبتول') ?>
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
            <input type="hidden" name="action" value="add_loss">
            <?php currency_fields(['amount'=>2]); ?>

            <div class="form-group">
                <label class="form-label" for="category"><?= __t('Damage Reason / Defect: *', 'علت ضرر و نوع خسارت: *', 'د تاوان لامل: *') ?></label>
                <select name="category" id="category" class="form-control" required>
                    <option value="آسیب کالا"><?= __t('Damaged product','آسیب کالا','زیانمن توکی') ?></option>
                    <option value="انقضا یا فساد"><?= __t('Expired / spoiled product','انقضا یا فساد کالا','تېرمهاله / خراب توکی') ?></option>
                    <option value="کسری موجودی"><?= __t('Inventory shortage','کسری موجودی','د موجودۍ کموالی') ?></option>
                    <option value="افت قیمت شدید جنس"><?= __t('Market Price Depreciation', 'افت قیمت شدید جنس در بازار', 'په بازار کې د بیې لوېدل') ?></option>
                    <option value="سرقت یا مفقودی"><?= __t('Theft or Lost Inventory', 'سرقت یا مفقودی کالا', 'غلا یا ورکېدل') ?></option>
                    <option value="سایر خسارات اجناس"><?= __t('Other Goods Damages', 'سایر خسارات اجناس', 'نور زیانونه او تاوانونه') ?></option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="stock_id">
                    <?= __t('Select Inventory Item (Optional):', 'انتخاب جنس از گدام (جهت کسر موجودی):', 'له ګودامه د توکي ټاکل:') ?>
                </label>
                <select name="stock_id" id="stock_id" class="form-control" onchange="autoFillPrice(this)">
                    <option value=""><?= __t('-- General / Unassigned Item --', '-- جنس متفرقه / بدون کسر گدام --', '-- عمومي / بې کټګورۍ جنس --') ?></option>
                    <?php foreach ($stockItems as $si): 
                        $unitCost = (float)$si['purchase_price'] + (float)$si['expenses'];
                        $isSel = ($preselectedStockId === (int)$si['id']);
                    ?>
                        <option value="<?= $si['id'] ?>" 
                                data-price="<?= (float)$si['purchase_price'] ?>"
                                data-cost="<?= $unitCost ?>" data-currency="<?= $si['currency_code'] ?>"
                                data-qty="<?= (float)$si['quantity'] ?>"
                                data-name="<?= sanitize($si['name']) ?>"
                                <?= $isSel ? 'selected' : '' ?>>
                            <?= sanitize($si['name']) ?> (<?= __t('Stock: ', 'موجودی: ', 'موجودي: ') ?><?= display_qty_unit($si['quantity'],$si['unit']) ?> | <?= __t('Cost: ', 'خرید: ', 'پېرود: ') ?><?= currency_format($si['purchase_price'],$si['currency_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="stockLiveFeedback" style="display: none; margin-top: 8px; padding: 8px 12px; background: var(--success-soft); border: 1px solid #bbf7d0; border-radius: 8px; font-size: 12px; color: var(--success); font-weight: 700;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label class="form-label" for="amount"><?= sprintf(__t('Total Loss Amount (%s): *', 'مبلغ ضرر (%s): *', 'د تاوان اندازه (%s): *'), get_currency_label()) ?></label>
                    <input type="number" step="any" min="1" id="amount" name="amount" class="form-control" required placeholder="0" style="font-weight: bold; color: var(--danger);">
                </div>
                <div class="form-group">
                    <label class="form-label" for="loss_qty"><?= __t('Quantity Damaged:', 'تعداد خسارت دیده:', 'د تاوان شوو شمېر:') ?></label>
                    <input type="number" min="0.001" id="loss_qty" name="loss_qty" class="form-control" value="1" step="0.001">
                </div>
            </div>

            <div id="stockActionBox" style="background: var(--warning-soft); border: 1.5px solid #fed7aa; border-radius: 10px; padding: 12px; margin-bottom: 14px;">
                <label style="font-weight: 800; font-size: 12.5px; color: #9a3412; display: block; margin-bottom: 8px;">
                    <i class="fa-solid fa-boxes-stacked ml-1"></i> <?= __t('Inventory Stock Action:', 'وضعیت و تکلیف جنس در گدام (استاک):', 'په ګودام کې د توکي حالت:') ?>
                </label>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12.5px; cursor: pointer; color: var(--text-main);">
                        <input type="radio" name="stock_loss_action" value="strike" checked style="margin-top: 3px;">
                        <span>
                            <strong><?= __t('Deduct from healthy stock', 'کسر از موجودی سالم و تفکیک ضایعات در گدام', 'له روغ موجودي څخه کمول') ?></strong> (<?= __t('Reduces healthy count and warehouse value while keeping remaining healthy items sellable', 'تعداد ضرر از سرجمع موجودی سالم کسر می‌گردد و اجناس سالم قابل فروش باقی می‌مانند', 'د تاوان اندازه له روغو توکو کمیږي') ?>).
                        </span>
                    </label>
                    <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12.5px; cursor: pointer; color: var(--danger);">
                        <input type="radio" name="stock_loss_action" value="delete" style="margin-top: 3px;">
                        <span>
                            <strong><?= __t('Delete entire stock item', 'حذف فوری و کامل این قلم جنس از گدام', 'له ګودامه د توکي بشپړ حذف') ?></strong> (<?= __t('Use only if entire batch is ruined and zero healthy units remain', 'فقط در صورتی که تمام موجودی تلف شده باشد', 'که ټول جنس تلف شوی وي') ?>).
                        </span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="loss_date"><?= __t('Damage Incident Date:', 'تاریخ وقوع خسارت:', 'د پېښې نېټه:') ?></label>
                <input type="date" id="loss_date" name="loss_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="description"><?= __t('Cause & Incident Description:', 'شرح جزئیات و علت خسارت:', 'تفصیل او شرح:') ?></label>
                <textarea id="description" name="description" class="form-control" rows="2" placeholder="<?= __t('Explain how damage occurred (e.g. cracked during screen replacement)...', 'توضیح دهید چطور خسارت وارد شد (مثلاً: در هنگام تعویض گلس شکسته شد)...', 'د تاوان د پېښېدو علت ولیکئ...') ?>"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; background: var(--danger); border-color: var(--danger); padding: 10px;">
                <i class="fa-solid fa-check ml-1"></i> <?= __t('Submit Loss Record', 'ثبت نهایی ضرر جنس', 'د تاوان ریکارډ ثبت کړئ') ?>
            </button>
        </form>
    </div>

    <!-- Losses List Table -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h3 class="card-title">
                <i class="fa-solid fa-list ml-1"></i> <?= __t('Recorded Losses & Damages History', 'تاریخچه ضررها و خسارات ثبت شده', 'د ثبت شویو تاوانونو تاریخچه') ?>
            </h3>
            <span class="badge" style="background: var(--danger-soft); color: var(--danger); font-size: 12px;">
                <?= $currentLang === 'en' ? count($losses) . ' loss records' : count($losses) . ' مورد خسارت' ?>
            </span>
        </div>

        <!-- FILTER -->
        <form method="GET" action="" style="background: var(--surface-muted); padding: 10px; border-radius: 8px; margin-bottom: 14px; display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 1; min-width: 130px;">
                <label style="font-size: 11px; font-weight: 700; color: var(--text-muted);"><?= __t('Damage Reason:', 'علت ضرر:', 'د تاوان لامل:') ?></label>
                <select name="category" class="form-control" style="font-size: 12px; padding: 6px;">
                    <option value=""><?= __t('All Reasons', 'همه علت‌ها', 'ټول لاملونه') ?></option>
                    <?php foreach(['آسیب کالا'=>__t('Damaged product','آسیب کالا','زیانمن توکی'),'انقضا یا فساد'=>__t('Expired / spoiled','انقضا یا فساد','تېرمهاله / خراب'),'کسری موجودی'=>__t('Inventory shortage','کسری موجودی','د موجودۍ کموالی')] as $value=>$label): ?><option value="<?= sanitize($value) ?>" <?= $catFilter===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?>
                    <option value="شکستگی صفحه و تاچ" <?= $catFilter === 'شکستگی صفحه و تاچ' ? 'selected' : '' ?>><?= __t('Screen Damage', 'شکستگی صفحه', 'د سکرین ماتېدل') ?></option>
                    <option value="سوختگی آی‌سی و بورد" <?= $catFilter === 'سوختگی آی‌سی و بورد' ? 'selected' : '' ?>><?= __t('Motherboard Defect', 'سوختگی بورد', 'د بورډ سوځېدل') ?></option>
                    <option value="خرابی و بادکردگی باطری" <?= $catFilter === 'خرابی و بادکردگی باطری' ? 'selected' : '' ?>><?= __t('Battery Defect', 'خرابی باطری', 'د بیټرۍ خرابوالی') ?></option>
                    <option value="افت قیمت شدید جنس" <?= $catFilter === 'افت قیمت شدید جنس' ? 'selected' : '' ?>><?= __t('Depreciation', 'افت قیمت', 'د بیې لوېدل') ?></option>
                    <option value="سرقت یا مفقودی" <?= $catFilter === 'سرقت یا مفقودی' ? 'selected' : '' ?>><?= __t('Theft / Lost', 'سرقت/مفقودی', 'غلا / ورکېدل') ?></option>
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
            <a href="<?= BASE_URL ?>modules/losses.php" class="btn btn-sm btn-secondary" style="height: 35px; padding: 0 10px;" title="<?= __t('Reset Filter', 'حذف فیلتر', 'فلټر لرې کول') ?>"><i class="fa-solid fa-rotate-left"></i></a>
        </form>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">#</th>
                        <th><?= __t('Damage Reason', 'علت خسارت', 'د تاوان لامل') ?></th>
                        <th><?= __t('Related Stock Item', 'جنس مربوطه', 'اړوند توکی') ?></th>
                        <th style="text-align: center;"><?= __t('Quantity', 'تعداد', 'شمېر') ?></th>
                        <th style="text-align: center;"><?= __t('Loss Amount', 'مبلغ ضرر', 'د تاوان اندازه') ?></th>
                        <th><?= __t('Date', 'تاریخ', 'نېټه') ?></th>
                        <th><?= __t('Description', 'توضیحات', 'تفصیل') ?></th>
                        <th><?= __t('Recorded By', 'ثبت‌کننده', 'ثبتوونکی') ?></th>
                        <th style="width: 60px; text-align: center;"><?= __t('Actions', 'عملیات', 'کړنې') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($losses)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: var(--text-light);">
                                <i class="fa-solid fa-shield-halved" style="font-size: 28px; display: block; margin-bottom: 6px; color: var(--border-dark);"></i>
                                <?= __t('No damage or loss records found.', 'هیچ موردی برای ضرر و خسارت اجناس یافت نشد.', 'د تاوان هېڅ ریکارډ ونه موندل شو.') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($losses as $idx => $ls): ?>
                            <tr>
                                <td style="text-align: center; font-weight: 700; color: var(--text-muted);"><?= $currentLang === 'en' ? ($idx + 1) : to_persian_num($idx + 1) ?></td>
                                <td>
                                    <span class="badge" style="background: var(--danger-soft); color: var(--danger); font-weight: 700;">
                                        <?= sanitize(display_loss_reason($ls['category'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= sanitize($ls['product_name'] ?: __t('General / Unassigned Item', 'جنس متفرقه / نامشخص', 'عمومي جنس')) ?>
                                </td>
                                <td style="text-align: center; font-weight: 700;">
                                    <?= display_qty_unit((float)($ls['loss_qty'] ?: 1),$ls['stock_unit']??'unit') ?>
                                </td>
                                <td style="text-align: center; font-weight: 800; color: var(--danger);">
                                    <?= format_money($ls['amount'], false) ?> <?= get_currency_label() ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted);">
                                    <?= format_date($ls['expense_date']) ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted); max-width: 180px;">
                                    <?= sanitize($ls['description'] ?: '-') ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted);">
                                    <?= sanitize(display_user_fullname($ls['user_name'] ?: 'مدیر دکان')) ?>
                                </td>
                                <td style="text-align: center;">
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('<?= __t('Are you sure you want to delete this loss record? Items will be restored to healthy stock.', 'آیا از حذف این رکورد ضرر مطمئن هستید؟', 'ایا باوري یاست چې دا تاوان ړنګ کړئ؟') ?>');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_loss">
                                        <input type="hidden" name="loss_id" value="<?= $ls['id'] ?>">
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

<script>
const isEnglish = <?= json_for_html($currentLang === 'en') ?>;
const currencyLabel = <?= json_for_html(get_currency_label()) ?>;

function autoFillPrice(sel,keepAmount=false) {
    var opt = sel.options[sel.selectedIndex];
    var fb = document.getElementById('stockLiveFeedback');
    var lossQtyInput = document.getElementById('loss_qty');
    var amountInput = document.getElementById('amount');
    
    if (!opt || !opt.value) {
        if (fb) fb.style.display = 'none';
        return;
    }

    var price = parseFloat(opt.getAttribute('data-price') || 0);
    var cost = parseFloat(opt.getAttribute('data-cost') || price);
    var costCurrency=opt.dataset.currency||'AFN';
    var currentQty = parseFloat(opt.getAttribute('data-qty') || 0);
    var lossQty = parseFloat(lossQtyInput.value || 1);
    if (lossQty <= 0) lossQty = 0.001;

    if (cost > 0 && !keepAmount && costCurrency===MoneyForm.code(amountInput.form)) {
        MoneyForm.setLedger(amountInput.form,'amount',(cost * lossQty).toFixed(2));
    }

    var remaining = Math.max(0, currentQty - lossQty);

    if (fb) {
        fb.style.display = 'block';
        if (lossQty > currentQty) {
            fb.style.background = 'var(--danger-soft)';
            fb.style.borderColor = '#fca5a5';
            fb.style.color = 'var(--danger)';
            fb.innerHTML = '<i class="fa-solid fa-triangle-exclamation ml-1"></i> ' +
                (isEnglish 
                    ? `Current stock in warehouse: <strong>${currentQty} units</strong> | <span style="color: var(--danger);">Warning: Damaged quantity (${lossQty}) exceeds stock! The quantity must be corrected before saving.</span>`
                    : `موجودی فعلی در گدام: <strong>${currentQty} عدد</strong> | <span style="color: var(--danger);">هشدار: تعداد ضایع شده (${lossQty}) بیشتر از موجودی گدام است! مقدار را پیش از ثبت اصلاح کنید.</span>`);
        } else {
            fb.style.background = 'var(--success-soft)';
            fb.style.borderColor = '#bbf7d0';
            fb.style.color = 'var(--success)';
            fb.innerHTML = '<i class="fa-solid fa-boxes-stacked ml-1"></i> ' +
                (isEnglish
                    ? `Current stock: <strong>${currentQty} units</strong> | Cost: <strong>${cost.toFixed(2)} ${costCurrency}</strong> | Stock after damage: <strong style="color: var(--success);">${remaining} healthy units</strong> remaining.`
                    : `موجودی فعلی در گدام: <strong>${currentQty} عدد</strong> | قیمت تمام‌شد: <strong>${cost.toFixed(2)} ${costCurrency}</strong> | موجودی پس از کسر خسارت: <strong style="color: var(--success); font-size: 13px;">${remaining} عدد سالم</strong> باقی می‌ماند.`);
        }
    }
}

document.getElementById('amount').form.addEventListener('currencychange',()=>autoFillPrice(document.getElementById('stock_id'),true));

document.getElementById('loss_qty')?.addEventListener('input', function() {
    var sel = document.getElementById('stock_id');
    if (sel && sel.selectedIndex > 0) autoFillPrice(sel);
});

document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('stock_id');
    if (sel && sel.selectedIndex > 0) {
        autoFillPrice(sel);
    }
});
</script>

<?php if(isset($list)){listing_controls_form($list);listing_footer($list);}require_once __DIR__ . '/../includes/footer.php'; ?>
