<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Sales Bills Archive (آرشیف بل ها)
 * URL: http://localhost/nikxor/modules/invoices.php
 */

require_once __DIR__.'/../includes/listing.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();
$accountWhere=currency_sql();
$currencyContext=currency_prepare_request(['edit_invoice'=>['paid_amount'=>2]],'',true);


$pageTitle = 'آرشیف بل ها';

// Handle Actions (Single Delete, Bulk Delete, Edit Invoice)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('خطای امنیتی نشست!', 'danger');
        redirect(BASE_URL . 'modules/invoices.php');
    }

    if(in_array($_POST['action'],['delete_invoice','bulk_delete_invoices','edit_invoice'],true)){access_deny(409,'posted_document',__t('Posted invoices are preserved. Use a sales return to correct goods and balances.','بل ثبت‌شده حفظ می‌شود؛ برای اصلاح جنس و حساب از برگشت فروش استفاده کنید.','ثبت شوی بل وساتئ؛ د پلور بېرته ستنول وکاروئ.'));}

    // 1. Single Delete Invoice
    if ($_POST['action'] === 'delete_invoice') {
        $delId = (int)($_POST['invoice_id'] ?? 0);
        if ($delId > 0) {
            try {
                $pdo->beginTransaction();
                $inv = $pdo->query("SELECT invoice_no FROM sales WHERE id = " . (int)$delId . " AND " . shop_sql())->fetch();
                if (!$inv) throw new Exception('Invoice not found.');
                $invNo = $inv['invoice_no'];

                $pdo->exec("DELETE FROM installments WHERE debt_id IN (SELECT id FROM debts WHERE sale_id = {$delId}) AND " . shop_sql());
                $pdo->exec("DELETE FROM debts WHERE sale_id = {$delId} AND " . shop_sql());
                $pdo->exec("DELETE FROM sale_items WHERE sale_id = {$delId} AND " . shop_sql());
                $pdo->exec("DELETE FROM sales WHERE id = {$delId} AND " . shop_sql());
                $pdo->commit();

                log_activity($currentUser['id'], 'حذف بل فروش', "حذف بل {$invNo}");
                set_flash("بل {$invNo} با موفقیت حذف گردید.", 'success');
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('خطا در حذف بل: ' . public_error($e), 'danger');
            }
        }
        redirect(BASE_URL . 'modules/invoices.php');
    }

    // 2. Bulk Delete Invoices (سلکت و حذف گروهی بل ها)
    if ($_POST['action'] === 'bulk_delete_invoices') {
        $selectedIds = $_POST['selected_ids'] ?? [];
        if (!is_array($selectedIds) || empty($selectedIds)) {
            set_flash('هیچ بلی برای حذف انتخاب نشده است.', 'warning');
            redirect(BASE_URL . 'modules/invoices.php');
        }

        $validIds = array_values(array_filter(array_map('intval', $selectedIds), fn($id) => $id > 0));
        if (empty($validIds)) {
            set_flash('شناسه‌های ارسالی معتبر نمی‌باشند.', 'danger');
            redirect(BASE_URL . 'modules/invoices.php');
        }

        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM installments WHERE debt_id IN (SELECT id FROM debts WHERE sale_id IN ($placeholders)) AND " . shop_sql())->execute($validIds);
            $pdo->prepare("DELETE FROM debts WHERE sale_id IN ($placeholders) AND " . shop_sql())->execute($validIds);
            $pdo->prepare("DELETE FROM sale_items WHERE sale_id IN ($placeholders) AND " . shop_sql())->execute($validIds);
            $delStmt = $pdo->prepare("DELETE FROM sales WHERE id IN ($placeholders) AND " . shop_sql());
            $delStmt->execute($validIds);
            $deletedCount = $delStmt->rowCount();
            $pdo->commit();

            log_activity($currentUser['id'], 'حذف گروهی بل‌ها', "حذف {$deletedCount} بل فروش انتخاب شده");
            set_flash("تعداد " . to_persian_num($deletedCount) . " بل با موفقیت حذف گردیدند.", 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('خطا در حذف گروهی بل‌ها: ' . public_error($e), 'danger');
        }
        redirect(BASE_URL . 'modules/invoices.php');
    }

    // 3. Edit Invoice
    if ($_POST['action'] === 'edit_invoice') {
        $editId = (int)($_POST['invoice_id'] ?? 0);
        $custName = trim($_POST['customer_name'] ?? '');
        $custPhone = trim($_POST['customer_phone'] ?? '');
        $paidAmt = (float)normalize_digits($_POST['paid_amount'] ?? '0');
        $payType = trim($_POST['payment_type'] ?? 'نقد');
        $notes = trim($_POST['notes'] ?? '');

        if ($editId <= 0 || empty($custName)) {
            set_flash('لطفاً نام مشتری را وارد نمایید.', 'danger');
        } else {
            try {
                $cur = $pdo->query("SELECT * FROM sales WHERE id = " . (int)$editId . " AND " . shop_sql())->fetch();
                if ($cur) {
                    currency_assert_record('sales',$editId,$currencyContext['code']);
                    $tot = (float)$cur['total_amount'];
                    $rem = max(0, $tot - $paidAmt);

                    $pdo->prepare("UPDATE sales SET 
                        customer_name = :cn,
                        customer_phone = :cp,
                        paid_amount = :pa,
                        remaining_amount = :ra,
                        payment_type = :pt,
                        notes = :nt
                        WHERE id = :id AND " . shop_sql())->execute([
                        ':cn' => $custName,
                        ':cp' => $custPhone,
                        ':pa' => $paidAmt,
                        ':ra' => $rem,
                        ':pt' => $payType,
                        ':nt' => $notes,
                        ':id' => $editId
                    ]);

                    // Sync associated debt if exists
                    $dCheck = $pdo->query("SELECT id FROM debts WHERE sale_id = " . (int)$editId . " AND " . shop_sql())->fetch();
                    if ($dCheck) {
                        $newStatus = ($rem <= 0) ? 'تسویه' : 'باز';
                        $pdo->prepare("UPDATE debts SET 
                            customer_name = :cn,
                            customer_phone = :cp,
                            paid_amount = :pa,
                            remaining_amount = :ra,
                            status = :st
                            WHERE id = :did AND " . shop_sql())->execute([
                            ':cn' => $custName,
                            ':cp' => $custPhone,
                            ':pa' => $paidAmt,
                            ':ra' => $rem,
                            ':st' => $newStatus,
                            ':did' => $dCheck['id']
                        ]);
                    }

                    currency_record('sales',$editId,$currencyContext,['paid_amount'=>$paidAmt,'remaining_amount'=>$rem],'payment_edit');
                    currency_request_commit();
                    log_activity($currentUser['id'], 'ویرایش بل فروش', "ویرایش بل {$cur['invoice_no']}");
                    set_flash("بل {$cur['invoice_no']} با موفقیت ویرایش گردید.", 'success');
                }
            } catch (Exception $e) {
                currency_request_rollback();
                set_flash('خطا در ویرایش بل: ' . public_error($e), 'danger');
            }
        }
        redirect(BASE_URL . 'modules/invoices.php');
    }
}

// Filter parameters
$search    = trim($_GET['search'] ?? '');
$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to'] ?? '');
$status    = trim($_GET['status'] ?? 'all');

// Build query
$where = [currency_sql('s'), shop_sql('s')];
$params = [];

if ($search !== '') {
    $where[] = "(s.invoice_no LIKE :search OR s.customer_name LIKE :search OR s.customer_phone LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($dateFrom !== '') {
    $where[] = "DATE(s.created_at) >= :date_from";
    $params[':date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = "DATE(s.created_at) <= :date_to";
    $params[':date_to'] = $dateTo;
}

if ($status === 'cash') {
    $where[] = "s.remaining_amount <= 0";
} elseif ($status === 'debt') {
    $where[] = "s.remaining_amount > 0";
} elseif ($status === 'installment') {
    $where[] = "(s.payment_type = 'اقساط' OR s.payment_type = 'installment')";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Summary counts
$summaryQuery = "SELECT 
    COUNT(s.id) as total_invoices,
    COALESCE(SUM(s.total_amount), 0) as grand_total_sales,
    COALESCE(SUM(s.paid_amount), 0) as total_paid,
    COALESCE(SUM(s.remaining_amount), 0) as total_remaining_debt
    FROM sales s
    {$whereSql}";

$sumStmt = $pdo->prepare($summaryQuery);
$sumStmt->execute($params);
$summary = $sumStmt->fetch() ?: [
    'total_invoices' => 0,
    'grand_total_sales' => 0,
    'total_paid' => 0,
    'total_remaining_debt' => 0
];

// Fetch Invoices
$invoicesQuery = "SELECT s.*, EXISTS(SELECT 1 FROM sales_returns sr WHERE sr.sale_id=s.id AND sr.shop_id=s.shop_id) AS is_returned, EXISTS(SELECT 1 FROM sales_returns sr WHERE sr.sale_id=s.id AND sr.shop_id=s.shop_id AND sr.status='posted') AS fully_returned, (SELECT COALESCE(SUM(r.amount-r.paid_amount),0) FROM customer_refunds r WHERE r.sale_id=s.id AND r.shop_id=s.shop_id) AS refund_due, ".sale_net_sql('total_amount','s')." AS net_amount, u.fullname as staff_name,
    (SELECT COUNT(id) FROM sale_items WHERE sale_id = s.id) as total_items_count
    FROM sales s
    LEFT JOIN users u ON s.created_by = u.id
    {$whereSql}
";

$sorts=['newest'=>['s.id DESC','Newest first','تازه‌ترین اول'],'oldest'=>['s.id ASC','Oldest first','قدیمی‌ترین اول'],'amount_high'=>['s.total_amount DESC,s.id DESC','Amount: high to low','مبلغ: زیاد به کم'],'amount_low'=>['s.total_amount ASC,s.id ASC','Amount: low to high','مبلغ: کم به زیاد']];
[$list,$invoices]=listing_query($pdo,$invoicesQuery,$params,$sorts);

// Pre-load items safely for each invoice
$invoiceIds = array_column($invoices, 'id');
$itemsBySale = [];
if (!empty($invoiceIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($invoiceIds), '?'));
    $itemsStmt = $pdo->prepare("SELECT sale_id, product_name, quantity, unit, total_price FROM sale_items WHERE sale_id IN ($inPlaceholders) AND " . shop_sql());
    $itemsStmt->execute($invoiceIds);
    while ($it = $itemsStmt->fetch()) {
        $itemsBySale[$it['sale_id']][] = $it['product_name'] . ' (' . display_qty_unit($it['quantity'],$it['unit']) . ')';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 20px; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-receipt text-primary"></i>
                <?= __('invoices_title') ?>
            </h1>
            <p style="color: var(--text-muted); font-size: 13px; margin: 4px 0 0 0;">
                <?= $currentLang === 'en' ? 'Manage, search, and reprint sales invoices by date, customer, and items.' : ($currentLang === 'ps' ? 'د نېټې، مشخصاتو او د پېرودونکي نوم له مخې د پلورل شوو بلونو لټون او چاپ.' : 'مدیریت، جستجو و چاپ مجدد تمام بل‌های فروش صادر شده مطابق تاریخ، مشخصات و نام خریدار') ?>
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= BASE_URL ?>modules/sales.php" class="btn btn-primary" style="padding: 8px 16px;">
                <i class="fa-solid fa-plus ml-1"></i> <?= __('btn_new_sale') ?>
            </a>
            <button onclick="window.print()" class="btn btn-secondary" style="padding: 8px 16px;">
                <i class="fa-solid fa-print ml-1"></i> <?= __('report_print_btn') ?>
            </button>
        </div>
    </div>

    <!-- SUMMARY METRICS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: var(--surface-muted); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; text-align: center;">
            <div style="font-size: 12px; color: var(--text-muted); font-weight: 600; margin-bottom: 4px;"><?= $currentLang === 'en' ? 'Total Invoices' : ($currentLang === 'ps' ? 'ټول بلونه' : 'تعداد کل بل‌ها') ?></div>
            <div style="font-size: 22px; font-weight: 800; color: var(--info);"><?= number_format((int)$summary['total_invoices']) ?> <span style="font-size: 12px; font-weight: normal;"><?= $currentLang === 'en' ? 'bills' : ($currentLang === 'ps' ? 'بله' : 'بل') ?></span></div>
        </div>
        <div style="background: var(--success-soft); border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px; text-align: center;">
            <div style="font-size: 12px; color: var(--success); font-weight: 600; margin-bottom: 4px;"><?= __('report_gross_sales') ?></div>
            <div style="font-size: 20px; font-weight: 800; color: var(--success);"><?= format_money($summary['grand_total_sales']) ?></div>
        </div>
        <div style="background: var(--info-soft); border: 1px solid #bfdbfe; border-radius: 12px; padding: 16px; text-align: center;">
            <div style="font-size: 12px; color: var(--info); font-weight: 600; margin-bottom: 4px;"><?= $currentLang === 'en' ? 'Original Invoice Payments' : ($currentLang === 'ps' ? 'ټولې ترلاسه شوې نغدې پیسې' : 'مجموع پرداخت‌های اصلی بل‌ها') ?></div>
            <div style="font-size: 20px; font-weight: 800; color: var(--info);"><?= format_money($summary['total_paid']) ?></div>
        </div>
        <div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 12px; padding: 16px; text-align: center;">
            <div style="font-size: 12px; color: #9f1239; font-weight: 600; margin-bottom: 4px;"><?= __('total_receivables_stat') ?></div>
            <div style="font-size: 20px; font-weight: 800; color: var(--danger);"><?= format_money($summary['total_remaining_debt']) ?></div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <form method="GET" action="" style="background: var(--surface-muted); padding: 16px; border-radius: 10px; margin-bottom: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: flex-end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 12px; font-weight: 700;"><?= $currentLang === 'en' ? 'Invoice # or Customer Name/Phone:' : ($currentLang === 'ps' ? 'د بل شمېره یا د پیرودونکي نوم/تلیفون:' : 'شماره بل یا نام/شماره مشتری:') ?></label>
                <input type="text" name="search" class="form-control" placeholder="<?= sanitize(__t('For example: NZ or Ahmad...','مثلاً: NZ یا احمد...','For example: NZ or Ahmad...')) ?>" value="<?= sanitize($search) ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 12px; font-weight: 700;"><?= __('from_date') ?></label>
                <input type="date" name="date_from" class="form-control" value="<?= sanitize($dateFrom) ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 12px; font-weight: 700;"><?= __('to_date') ?></label>
                <input type="date" name="date_to" class="form-control" value="<?= sanitize($dateTo) ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 12px; font-weight: 700;"><?= __('invoice_filter_payment') ?></label>
                <select name="status" class="form-control">
                    <option value="all" <?= ($status === 'all') ? 'selected' : '' ?>><?= $currentLang === 'en' ? 'All Payment Types' : ($currentLang === 'ps' ? 'ټول حالتونه' : 'همه وضعیت‌ها') ?></option>
                    <option value="cash" <?= ($status === 'cash') ? 'selected' : '' ?>><?= $currentLang === 'en' ? 'Cash Only' : ($currentLang === 'ps' ? 'یوازې نغد' : 'فقط نقدی') ?></option>
                    <option value="debt" <?= ($status === 'debt') ? 'selected' : '' ?>><?= $currentLang === 'en' ? 'With Loan / Credit' : ($currentLang === 'ps' ? 'له پور سره' : 'دارای قرضه') ?></option>
                    <option value="installment" <?= ($status === 'installment') ? 'selected' : '' ?>><?= $currentLang === 'en' ? 'Installments' : ($currentLang === 'ps' ? 'په قسطونو' : 'اقساطی') ?></option>
                </select>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 9px;">
                    <i class="fa-solid fa-filter ml-1"></i> <?= __('filter') ?>
                </button>
                <a href="<?= BASE_URL ?>modules/invoices.php" class="btn btn-secondary" style="padding: 9px;" title="<?= __('reset') ?>">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </div>
    </form>

    <!-- BULK SELECTION ACTION BAR -->
    <div id="invBulkActionBar" class="card" style="display: none; background: var(--info-soft); border: 1.5px solid var(--info); margin-bottom: 16px; padding: 12px 18px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.12);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <span style="font-weight: 800; color: var(--info); font-size: 14px;">
                    <i class="fa-solid fa-square-check text-blue-600 ml-1"></i> <span id="invSelectedCountText">0</span> <?= $currentLang === 'en' ? 'Invoices Selected' : ($currentLang === 'ps' ? 'بلونه وټاکل شول' : 'بل انتخاب گردید') ?>
                </span>
                <button type="button" onclick="selectAllInvRows(true)" class="btn btn-sm btn-secondary" style="background: var(--surface);">
                    <i class="fa-solid fa-list-check ml-1"></i> <?= $currentLang === 'en' ? 'Select All' : ($currentLang === 'ps' ? 'ټول ټاکل' : 'انتخاب همه') ?>
                </button>
                <button type="button" onclick="selectAllInvRows(false)" class="btn btn-sm btn-secondary" style="background: var(--surface);">
                    <i class="fa-solid fa-xmark ml-1"></i> <?= $currentLang === 'en' ? 'Deselect All' : ($currentLang === 'ps' ? 'ټول ناټاکل' : 'لغو انتخاب‌ها') ?>
                </button>
            </div>
            <div>
                <button type="button" onclick="confirmBulkDeleteInvoices()" class="btn btn-danger btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                    <i class="fa-solid fa-trash-can"></i> <?= $currentLang === 'en' ? 'Delete Selected Invoices' : ($currentLang === 'ps' ? 'ټاکل شوي بلونه ړنګول' : 'حذف بل‌های انتخاب شده') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden Form for Bulk Deletion of Invoices -->
    <form id="invBulkDeleteForm" method="POST" action="" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="bulk_delete_invoices">
        <div id="invBulkDeleteInputsContainer"></div>
    </form>

    <!-- INVOICES TABLE -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 38px; text-align: center;">
                        <input type="checkbox" id="selectAllInvoicesHeader" onchange="toggleAllInvoices(this)" title="<?= sanitize(__t('Select All','انتخاب همه','Select All')) ?>">
                    </th>
                    <th style="width: 45px; text-align: center;">#</th>
                    <th><?= __('col_inv_no') ?></th>
                    <th><?= __('col_date') ?></th>
                    <th><?= __('invoice_customer_col') ?></th>
                    <th><?= __('col_items') ?></th>
                    <th style="text-align: center;"><?= __('invoice_total_col') ?></th>
                    <th style="text-align: center;"><?= __('debt_paid_amount') ?></th>
                    <th style="text-align: center;"><?= __('debt_remaining_amount') ?></th>
                    <th style="text-align: center;"><?= __('invoice_filter_payment') ?></th>
                    <th style="text-align: center; min-width: 220px;"><?= __('col_actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 36px 12px; color: var(--text-light);">
                            <i class="fa-solid fa-folder-open" style="font-size: 32px; display: block; margin-bottom: 8px; color: var(--border-dark);"></i>
                            <?= $currentLang === 'en' ? 'No invoices found matching your filters.' : ($currentLang === 'ps' ? 'له فلټر سره سم بل ونه موندل شو.' : 'هیچ بلی مطابق فیلترهای وارد شده یافت نشد.') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $idx => $inv): 
                        $hasDebt = ((float)$inv['remaining_amount'] > 0);
                        $itemsText = !empty($itemsBySale[$inv['id']]) ? implode($currentLang==='en'?', ':'، ', $itemsBySale[$inv['id']]) : ($currentLang === 'en' ? 'No items' : ($currentLang === 'ps' ? 'بې جنسه' : 'بدون اقلام'));
                    ?>
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" class="inv-chk" value="<?= $inv['id'] ?>" onchange="updateInvBulkBar()">
                            </td>
                            <td style="text-align: center; font-weight: 700; color: var(--text-muted);"><?= $idx + 1 ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>print/invoice.php?id=<?= $inv['id'] ?>" target="_blank" style="font-weight: 800; color: var(--info); text-decoration: none; display: flex; align-items: center; gap: 4px;">
                                    <i class="fa-solid fa-file-lines"></i>
                                    <span><?= sanitize($inv['invoice_no']) ?> <?php if(!empty($inv['is_returned'])):?><strong class="badge"><?= $inv['fully_returned']?__t('Fully returned','کامل برگشت‌شده','بشپړ ستنول شوی'):__t('Partially returned','قسمتی برگشت‌شده','یوه برخه ستنول شوې') ?></strong><?php endif ?></span>
                                </a>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--text-main); font-size: 13px;">
                                    <?= date('Y/m/d', strtotime($inv['created_at'])) ?>
                                </div>
                                <div style="font-size: 11px; color: var(--text-muted);">
                                    <?= date('H:i:s', strtotime($inv['created_at'])) ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--text-main); font-size: 13.5px;">
                                    <?= sanitize($inv['customer_name']) ?>
                                </div>
                                <?php if (!empty($inv['customer_phone'])): ?>
                                    <div style="font-size: 11.5px; color: var(--text-muted); direction: ltr; text-align: right;">
                                        <i class="fa-solid fa-phone" style="font-size: 10px;"></i> <?= sanitize($inv['customer_phone']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 12.5px; color: var(--text-main); max-width: 320px; line-height: 1.5;">
                                    <?= sanitize($itemsText) ?>
                                </div>
                                <span class="badge badge-secondary" style="font-size: 10px; margin-top: 4px;">
                                    <?= (int)$inv['total_items_count'] ?> <?= __t('items','قلم جنس') ?>
                                </span>
                            </td>
                            <td style="text-align: center; font-weight: 800; color: var(--text-main); font-size: 13.5px;">
                                <?= format_money($inv['total_amount'], false) ?> <?= currency_badge('sales',$inv['id']) ?><?php if($inv['is_returned']):?><small style="display:block"><?= __t('After returns','پس از برگشت','له ستنولو وروسته') ?>: <?= format_money($inv['net_amount'],false) ?></small><?php endif ?><?php if((float)$inv['refund_due']>0):?><small style="display:block"><?= __t('Refund due','باقی‌ماندهٔ برگشت پول','د ستنولو پاتې پیسې') ?>: <?= format_money($inv['refund_due'],false) ?></small><?php endif ?>
                            </td>
                            <td style="text-align: center; font-weight: 700; color: var(--success); font-size: 13px;">
                                <?= format_money($inv['paid_amount'], false) ?>
                            </td>
                            <td style="text-align: center; font-weight: 700; color: <?= $hasDebt ? '#e11d48' : 'var(--text-muted)' ?>; font-size: 13px;">
                                <?= $hasDebt ? format_money($inv['remaining_amount'], false) : format_money(0, false) ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($inv['payment_type'] === 'اقساط' || $inv['payment_type'] === 'installment'): ?>
                                    <span class="badge" style="background: var(--warning-soft); color: var(--warning);"><?= display_payment_type('قسط وار') ?></span>
                                <?php elseif ($hasDebt): ?>
                                    <span class="badge badge-danger"><?= display_payment_type('قرض') ?></span>
                                <?php else: ?>
                                    <span class="badge badge-success"><?= display_payment_type('نقد') ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 4px; justify-content: center; align-items: center; flex-wrap: wrap;">
                                    <a href="<?= BASE_URL ?>print/invoice.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-sm btn-primary" title="<?= sanitize(__t('Print or save PDF','پرینت و ذخیره PDF','Print or save PDF')) ?>" style="padding: 4px 8px;">
                                        <i class="fa-solid fa-file-pdf"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>print/invoice.php?id=<?= $inv['id'] ?>&export=doc" class="btn btn-sm btn-secondary" title="<?= sanitize(__t('Download Word document (.doc)','دانلود نسخه Word (.doc)','Download Word document (.doc)')) ?>" style="padding: 4px 8px;">
                                        <i class="fa-solid fa-file-word"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-secondary" style="padding: 4px 8px;"
                                            onclick='openEditInvoiceModal(<?= json_for_html([
                                                'id' => (int)$inv['id'],
                                                'invoice_no' => $inv['invoice_no'],'currency_code'=>$inv['currency_code'],
                                                'customer_name' => $inv['customer_name'],
                                                'customer_phone' => $inv['customer_phone'] ?? '',
                                                'total_amount' => (float)$inv['total_amount'],
                                                'paid_amount' => (float)$inv['paid_amount'],
                                                'payment_type' => $inv['payment_type'],
                                                'notes' => $inv['notes'] ?? ''
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);'
                                            title="<?= sanitize(__t('Edit invoice details','ویرایش مشخصات بل','Edit invoice details')) ?>">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <form method="POST" action="" onsubmit="return confirm('آیا از حذف این بل فروش اطمینان کامل دارید؟ اقلام و حساب قرضه متصل به آن نیز حذف خواهد شد.');" style="display: inline; margin: 0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_invoice">
                                        <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" style="padding: 4px 8px;" title="<?= sanitize(__t('Delete invoice','حذف بل','Delete invoice')) ?>">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Edit Invoice (ویرایش بل فروش) -->
<div id="editInvoiceModal" class="feature-modal-backdrop" onclick="if(event.target === this) closeEditInvoiceModal();">
    <div class="feature-modal-box" style="max-width: 500px; text-align: right;">
        <button type="button" class="feature-modal-close" onclick="closeEditInvoiceModal();">&times;</button>
        <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 16px; color: var(--text-main);">
            <i class="fa-solid fa-pen-to-square ml-1" style="color: var(--info);"></i> <?= $currentLang === 'en' ? 'Edit Invoice' : ($currentLang === 'ps' ? 'د بل سمول' : 'ویرایش مشخصات بل') ?> <span id="editInvNumber" style="color: var(--info);"></span>
        </h3>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_invoice">
            <?php currency_fields(['paid_amount'=>2]); ?>
            <input type="hidden" name="invoice_id" id="editInvId" value="0">

            <div style="background: var(--surface-muted); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 12px; font-size: 13px;">
                <?= __('invoice_total_col') ?>: <strong id="editInvTotalText" style="color: var(--text-main);">0</strong>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group">
                    <label class="form-label"><?= __('invoice_customer_col') ?>:</label>
                    <input type="text" name="customer_name" id="editInvCustomerName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('customer_phone_label') ?>:</label>
                    <input type="text" name="customer_phone" id="editInvCustomerPhone" class="form-control numeric-input">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group">
                    <label class="form-label"><?= __('debt_paid_amount') ?> (<?= get_currency_label() ?>):</label>
                    <input type="number" step="any" name="paid_amount" id="editInvPaidAmount" class="form-control numeric-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('invoice_filter_payment') ?>:</label>
                    <select name="payment_type" id="editInvPaymentType" class="form-control">
                        <option value="نقد"><?= display_payment_type('نقد') ?></option>
                        <option value="قرض"><?= display_payment_type('قرض') ?></option>
                        <option value="قسط وار"><?= display_payment_type('قسط وار') ?></option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label"><?= $currentLang === 'en' ? 'Invoice Notes:' : ($currentLang === 'ps' ? 'د بل یادښت:' : 'یادداشت بل:') ?></label>
                <input type="text" name="notes" id="editInvNotes" class="form-control">
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center; padding: 10px;">
                    <i class="fa-solid fa-check ml-1"></i> <?= __('save_settings') ?>
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeEditInvoiceModal();">
                    <?= __('cancel') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Bulk Selection Logic for Invoices
function toggleAllInvoices(headerMaster) {
    const checkboxes = document.querySelectorAll('.inv-chk');
    checkboxes.forEach(cb => cb.checked = headerMaster.checked);
    updateInvBulkBar();
}

function selectAllInvRows(checkAll) {
    const checkboxes = document.querySelectorAll('.inv-chk');
    checkboxes.forEach(cb => cb.checked = checkAll);
    const master = document.getElementById('selectAllInvoicesHeader');
    if (master) master.checked = checkAll;
    updateInvBulkBar();
}

function updateInvBulkBar() {
    const checkboxes = document.querySelectorAll('.inv-chk:checked');
    const bar = document.getElementById('invBulkActionBar');
    const countText = document.getElementById('invSelectedCountText');
    const count = checkboxes.length;

    if (count > 0) {
        bar.style.display = 'block';
        countText.textContent = count.toLocaleString('fa-IR');
    } else {
        bar.style.display = 'none';
    }
}

function confirmBulkDeleteInvoices() {
    const checkboxes = document.querySelectorAll('.inv-chk:checked');
    if (checkboxes.length === 0) {
        alert('لطفاً حداقل یک بل را برای حذف انتخاب نمایید.');
        return;
    }

    if (!confirm(`آیا از حذف تعداد ${checkboxes.length} بل فروش انتخاب شده به صورت گروهی اطمینان کامل دارید؟ اقلام و حسابات قرضه متصل به این بل‌ها نیز حذف خواهند شد.`)) {
        return;
    }

    const container = document.getElementById('invBulkDeleteInputsContainer');
    container.innerHTML = '';
    checkboxes.forEach(cb => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'selected_ids[]';
        hidden.value = cb.value;
        container.appendChild(hidden);
    });

    document.getElementById('invBulkDeleteForm').submit();
}

function refreshInvoiceTotal(form){document.getElementById('editInvTotalText').textContent=MoneyForm.displayLedger(form,form.dataset.invoiceTotal||0)+' '+(form.dataset.invoiceCurrency||MoneyForm.code(form));}
document.getElementById('editInvPaidAmount').form.addEventListener('currencychange',function(){refreshInvoiceTotal(this);});
// Edit Modal
function openEditInvoiceModal(data) {
    MoneyForm.setCurrency(document.getElementById('editInvPaidAmount').form,data.currency_code);
    document.getElementById('editInvId').value = data.id;
    document.getElementById('editInvNumber').textContent = '(' + (data.invoice_no || '') + ')';
    const form=document.getElementById('editInvPaidAmount').form;form.dataset.invoiceTotal=data.total_amount||0;form.dataset.invoiceCurrency=data.currency_code;refreshInvoiceTotal(form);
    document.getElementById('editInvCustomerName').value = data.customer_name || '';
    document.getElementById('editInvCustomerPhone').value = data.customer_phone || '';
    MoneyForm.setLedger(document.getElementById('editInvPaidAmount').form,'paid_amount',data.paid_amount || 0);
    document.getElementById('editInvPaymentType').value = data.payment_type || 'نقد';
    document.getElementById('editInvNotes').value = data.notes || '';
    document.getElementById('editInvoiceModal').style.display = 'flex';
}

function closeEditInvoiceModal() {
    document.getElementById('editInvoiceModal').style.display = 'none';
}
</script>

<?php listing_controls_form($list);listing_footer($list);require_once __DIR__ . '/../includes/footer.php'; ?>
