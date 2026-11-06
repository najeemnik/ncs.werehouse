<?php
/**
 * Nik Smart Count - Comprehensive Financial Reports & Analytics Module
 * 100% Multilingual Isolated: English, Dari, Pashto
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();
$netSalesSql=sale_net_sql('total_amount');$netProfitSql=sale_net_sql('profit');
$accountWhere=currency_sql().' AND '.shop_sql();
$currentLang = get_current_lang();
$currentDir  = get_current_dir();

$pageTitle = 'گزارشات مالی و حسابداری دکان';

// Filters: Date range
$quick = $_GET['quick'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

if ($quick === 'today') {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
} elseif ($quick === 'this_week') {
    $startDate = date('Y-m-d', strtotime('-6 days'));
    $endDate = date('Y-m-d');
} elseif ($quick === 'this_month' || (empty($startDate) && empty($endDate))) {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
}

// Ensure end date includes full day for DATETIME queries
$startDateTime = $startDate . ' 00:00:00';
$endDateTime   = $endDate . ' 23:59:59';

// 1. Sales & Profit Metrics in Period
$salesStmt = $pdo->prepare("SELECT 
    COUNT(*) as total_invoices,
    COALESCE(SUM({$netSalesSql}), 0) as total_gross,
    0 as total_discount,
    COALESCE(SUM({$netSalesSql}), 0) as total_sales,
    COALESCE(SUM(paid_amount), 0) as total_paid,
    COALESCE(SUM(remaining_amount), 0) as total_remaining,
    COALESCE(SUM({$netProfitSql}), 0) as total_profit
    FROM sales 
    WHERE {$accountWhere} AND NOT EXISTS(SELECT 1 FROM sales_returns sr WHERE sr.sale_id=sales.id AND sr.shop_id=sales.shop_id AND sr.status='posted') AND created_at BETWEEN :s_full AND :e_full");
$salesStmt->execute([
    ':s_full' => $startDateTime,
    ':e_full' => $endDateTime
]);
$salesReport = $salesStmt->fetch();

$totalInvoices        = (int)($salesReport['total_invoices'] ?? 0);
$totalSalesAmount     = (float)($salesReport['total_sales'] ?? 0);
$totalPaidAmount      = (float)($salesReport['total_paid'] ?? 0);
$totalRemainingAmount = (float)($salesReport['total_remaining'] ?? 0);
$totalGrossProfit     = (float)($salesReport['total_profit'] ?? 0);

// 2. Expenses (مصارف تفکیک شده از ضرر)
$hasExpenseType = true;
try {
    $pdo->query("SELECT expense_type FROM ".live_documents_source('expenses')." LIMIT 1");
} catch (Exception $e) {
    $hasExpenseType = false;
}

$expSql = "SELECT COALESCE(SUM(amount), 0) as total_expenses FROM ".live_documents_source('expenses')." WHERE {$accountWhere} AND ((expense_date BETWEEN :s AND :e) OR (created_at BETWEEN :s_full AND :e_full))";
if ($hasExpenseType) {
    $expSql .= " AND expense_type = 'مصارف'";
}
$expStmt = $pdo->prepare($expSql);
$expStmt->execute([':s' => $startDate, ':e' => $endDate, ':s_full' => $startDateTime, ':e_full' => $endDateTime]);
$totalExpenses = (float)($expStmt->fetch()['total_expenses'] ?? 0);

// 3. Losses (ضرر و خسارات اجناس)
$totalLosses = 0.0;
if ($hasExpenseType) {
    $lossStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_loss FROM ".live_documents_source('expenses')." WHERE {$accountWhere} AND expense_type = 'ضرر' AND ((expense_date BETWEEN :s AND :e) OR (created_at BETWEEN :s_full AND :e_full))");
    $lossStmt->execute([':s' => $startDate, ':e' => $endDate, ':s_full' => $startDateTime, ':e_full' => $endDateTime]);
    $totalLosses = (float)($lossStmt->fetch()['total_loss'] ?? 0);
}

// 4. Net Operating Profit = Gross Profit - Expenses - Losses
$netProfit = $totalGrossProfit - $totalExpenses - $totalLosses;
$profitKnown=currency_unknown_profit_count(null,$startDateTime,$endDateTime)===0;

// 5. Debt Installments Collected in this Period
$instStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_inst FROM installments WHERE {$accountWhere} AND payment_date BETWEEN :s AND :e");
$instStmt->execute([':s' => $startDate, ':e' => $endDate]);
$installmentsCollected = (float)($instStmt->fetch()['total_inst'] ?? 0);

// 6. Top Sold Products in this Period
$topStmt = $pdo->prepare("SELECT si.product_name, SUM(si.quantity-COALESCE((SELECT SUM(rl.quantity) FROM return_lines rl WHERE rl.sale_item_id=si.id AND rl.shop_id=si.shop_id),0)) as sold_qty, SUM(si.total_price-COALESCE((SELECT SUM(rl.total_amount) FROM return_lines rl WHERE rl.sale_item_id=si.id AND rl.shop_id=si.shop_id),0)) as total_revenue, SUM(si.profit-COALESCE((SELECT SUM(rl.total_amount-rl.cost_amount) FROM return_lines rl WHERE rl.sale_item_id=si.id AND rl.shop_id=si.shop_id),0)) as total_item_profit, MIN(si.profit_known) as profit_known
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE NOT EXISTS(SELECT 1 FROM sales_returns sr WHERE sr.sale_id=s.id AND sr.shop_id=s.shop_id AND sr.status='posted') AND s.currency_code=".$pdo->quote(currency_base_code())." AND ".shop_sql('s')." AND s.created_at BETWEEN :s_full AND :e_full
    GROUP BY si.product_name
    ORDER BY sold_qty DESC
    LIMIT 5");
$topStmt->execute([':s_full' => $startDateTime, ':e_full' => $endDateTime]);
$topItems = $topStmt->fetchAll();

// 7. Mudaraba payouts in period
$mudarabaPayoutInPeriod = 0;
$mudPayoutRecords = [];
try {
    $recStmt = $pdo->prepare("SELECT * FROM ".live_documents_source('capital')." 
        WHERE {$accountWhere} AND transaction_type = 'برداشت'
          AND (source_description LIKE '%مضاربت%' OR source_description LIKE '%شریک%')
          AND (
              (transaction_date BETWEEN :s AND :e) 
              OR (transaction_date IS NULL AND created_at BETWEEN :s_full AND :e_full)
          )
        ORDER BY COALESCE(transaction_date, created_at) DESC, id DESC");
    $recStmt->execute([
        ':s'      => $startDate, 
        ':e'      => $endDate,
        ':s_full' => $startDateTime,
        ':e_full' => $endDateTime
    ]);
    $mudPayoutRecords = $recStmt->fetchAll();
    foreach ($mudPayoutRecords as $mpr) {
        $mudarabaPayoutInPeriod += (float)$mpr['amount'];
    }
} catch (Exception $e) {
    try {
        $mudPayoutStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total_mud_payout FROM ".live_documents_source('capital')." WHERE {$accountWhere} AND transaction_type = 'برداشت' AND (source_description LIKE '%مضاربت%' OR source_description LIKE '%شریک%')");
        $mudPayoutStmt->execute();
        $mudarabaPayoutInPeriod = (float)$mudPayoutStmt->fetchColumn();
    } catch (Exception $e2) {}
}

// Detailed list of Mudaraba contracts
$mudContracts = [];
$totalMudActiveCap = 0;
$totalMudPaidProfit = 0;
$totalMudReturnedCap = 0;
$totalMudSales = 0;
$totalMudProfit = 0;
try {
    $mudContracts = currency_contract_views($pdo->query("SELECT * FROM mudaraba_contracts WHERE " . shop_sql() . " ORDER BY id DESC")->fetchAll());
    foreach ($mudContracts as $mc) {
        if ($mc['status'] === 'active') {
            $totalMudActiveCap += (float)$mc['capital_amount'];
        }
        $totalMudSales       += (float)$mc['total_sales_amount'];
        $totalMudProfit      += (float)$mc['total_profit_amount'];
        $totalMudPaidProfit  += (float)($mc['paid_profit_to_partner'] ?? 0);
        $totalMudReturnedCap += (float)($mc['returned_capital'] ?? 0);
    }
} catch (Exception $mEx) {}

require_once __DIR__ . '/../includes/header.php';
?>

<?php currency_render_summary($startDateTime,$endDateTime); ?>

<div class="card no-print" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h3 class="card-title" style="margin-bottom: 4px;">
                <i class="fa-solid fa-chart-line ml-1"></i> <?= __t('Financial & Profit Reports Filter', 'فیلتر گزارشات مالی و سود دکان', 'د مالي او ګټې راپورونو فلټر') ?>
            </h3>
            <span style="font-size: 12px; color: var(--text-muted);">
                <?= __t('Time Period: From ', 'بازه زمانی: از ', 'د مهال وېش موده: له ') ?><?= $currentLang === 'en' ? $startDate : to_persian_num($startDate) ?><?= __t(' to ', ' تا ', ' تر ') ?><?= $currentLang === 'en' ? $endDate : to_persian_num($endDate) ?>
            </span>
        </div>

        <div style="display: flex; gap: 6px;">
            <a href="<?= BASE_URL ?>modules/reports.php?quick=today" class="btn btn-sm <?= $quick === 'today' ? 'btn-primary' : 'btn-secondary' ?>"><?= __t('Today', 'امروز', 'نن ورځ') ?></a>
            <a href="<?= BASE_URL ?>modules/reports.php?quick=this_week" class="btn btn-sm <?= $quick === 'this_week' ? 'btn-primary' : 'btn-secondary' ?>"><?= __t('Past 7 Days', '۷ روز اخیر', 'وروستۍ ۷ ورځې') ?></a>
            <a href="<?= BASE_URL ?>modules/reports.php?quick=this_month" class="btn btn-sm <?= ($quick === 'this_month' || empty($quick)) ? 'btn-primary' : 'btn-secondary' ?>"><?= __t('This Month', 'این ماه', 'روانه میاشت') ?></a>
            <button type="button" onclick="window.print()" class="btn btn-sm btn-primary">
                <i class="fa-solid fa-print ml-1"></i> <?= __t('Print Report', 'چاپ گزارش', 'راپور چاپ کړئ') ?>
            </button>
        </div>
    </div>

    <!-- Custom date filter -->
    <form method="GET" action="" style="margin-top: 14px; display: flex; gap: 10px; align-items: end; flex-wrap: wrap;">
        <div>
            <label class="form-label" style="font-size: 11.5px;"><?= __t('From Date:', 'از تاریخ:', 'له نېټې:') ?></label>
            <input type="date" name="start_date" class="form-control" value="<?= sanitize($startDate) ?>" required>
        </div>
        <div>
            <label class="form-label" style="font-size: 11.5px;"><?= __t('To Date:', 'تا تاریخ:', 'تر نېټې:') ?></label>
            <input type="date" name="end_date" class="form-control" value="<?= sanitize($endDate) ?>" required>
        </div>
        <div>
            <button type="submit" class="btn btn-secondary" style="height: 38px;">
                <i class="fa-solid fa-magnifying-glass"></i> <?= __t('Apply Date Filter', 'اعمال تاریخ', 'نېټه عملي کړئ') ?>
            </button>
        </div>
    </form>
</div>

<!-- Report Summary Cards Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= __t('Period Gross Sales', 'فروش این دوره', 'د دورې پلور') ?></span>
            <div class="stat-icon icon-sales"><i class="fa-solid fa-cart-shopping"></i></div>
        </div>
        <div class="stat-value"><?= format_money($totalSalesAmount) ?></div>
        <div class="stat-desc"><?= $currentLang === 'en' ? $totalInvoices : to_persian_num($totalInvoices) ?> <?= __t('invoices', 'بل', 'بلونه') ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= __t('Period Gross Profit', 'سود ناخالص دوره', 'د دورې ناخالصه ګټه') ?></span>
            <div class="stat-icon icon-profit"><i class="fa-solid fa-arrow-trend-up"></i></div>
        </div>
        <div class="stat-value" style="color: var(--success);"><?= currency_profit_html($totalGrossProfit,$profitKnown) ?></div>
        <div class="stat-desc"><?= __t('From product sales margins', 'سود ناخالص فروشات کالاها', 'د توکو د پلور ګټه') ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= __t('Operating Expenses', 'مصارف جاری دکان', 'د هټۍ لګښتونه') ?></span>
            <div class="stat-icon icon-debt" style="background: rgba(245, 158, 11, 0.15); color: var(--warning);"><i class="fa-solid fa-receipt"></i></div>
        </div>
        <div class="stat-value" style="color: var(--warning);"><?= format_money($totalExpenses) ?></div>
        <div class="stat-desc"><?= __t('Shop operating costs', 'مصارف عملیاتی دکان', 'د هټۍ عملیاتي لګښتونه') ?></div>
    </div>

    <div class="stat-card" style="border-right: 4px solid var(--danger);">
        <div class="stat-header">
            <span class="stat-title"><?= __t('Damaged Goods & Losses', 'ضرر و خسارات اجناس', 'د توکو زیان او تاوان') ?></span>
            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
        <div class="stat-value" style="color: var(--danger);"><?= format_money($totalLosses) ?></div>
        <div class="stat-desc"><?= __t('Defects & depreciation', 'خسارت و افت ارزش اجناس', 'د توکو زیانونه') ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= $netProfit >= 0 ? __t('Period Net Profit', 'مفاد خالص دوره', 'د دورې خالص ګټه') : __t('Period Net Loss', 'ضرر خالص دوره', 'د دورې خالص تاوان') ?></span>
            <div class="stat-icon" style="background: <?= $netProfit >= 0 ? 'rgba(22, 163, 74, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; color: <?= $netProfit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;"><i class="fa-solid <?= $netProfit >= 0 ? 'fa-sack-dollar' : 'fa-arrow-trend-down' ?>"></i></div>
        </div>
        <div class="stat-value" style="color: <?= $netProfit >= 0 ? 'var(--success)' : 'var(--danger)' ?>; font-size: 24px;">
            <?= $netProfit >= 0 ? '+' : '-' ?><?= currency_profit_html(abs($netProfit),$profitKnown) ?>
        </div>
        <div class="stat-desc">
            <?= $netProfit >= 0 ? __t('Net profit after expenses and losses', 'مفاد خالص پس از کسر تمام مصارف و ضررها', 'د لګښتونو او زیانونو له کمېدو وروسته ګټه') : __t('Net loss recorded for this period', 'ضرر خالص ثبت شده در این دوره', 'په دې دوره کې ثبت شوی تاوان') ?>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
    <!-- Cash Flow in Period -->
    <div class="card" style="position: relative;">
        <div class="card-header">
            <h3 class="card-title" style="display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-money-bill-transfer ml-1"></i> <?= __t('Liquidity & Debts Flow in this Period', 'وضعیت نقدینگی و قرضه ها در این بازه', 'په دې دوره کې د نغدو پیسو او پورونو حالت') ?>
                <?php if (is_feature_locked('liquidity_report')): ?>
                    <span class="badge-lock-tag" onclick="showLockModal('liquidity_report', 'Cash Flow & Debts Period Report', '<?= __t('Cash Flow & Debts Period Report', 'وضعیت نقدینگی و قرضه‌ها در این دوره', 'د نغدو پیسو راپور') ?>');" style="cursor: pointer;">
                        <i class="fa-solid fa-lock text-amber-500"></i> <?= __t('Locked', 'قفل', 'قفل') ?>
                    </span>
                <?php endif; ?>
            </h3>
        </div>
        <?php if (is_feature_locked('liquidity_report')): ?>
            <div class="locked-blur-wrapper" style="min-height: 220px;">
                <div class="locked-blur-content">
                    <table class="table">
                        <tbody>
                            <tr><td><?= __t('Total cash collected from sales:', 'مجموع نقدینگی دریافت شده از فروشات:', 'له پلور څخه ترلاسه شوې نغدې پیسې:') ?></td><td><strong>120,000 <?= get_currency_label() ?></strong></td></tr>
                            <tr><td><?= __t('Installments collected from receivables:', 'اقساط و وصولی های دریافتی از قرضه ها:', 'له پورونو ترلاسه شوي قسطونه:') ?></td><td><strong>45,000 <?= get_currency_label() ?></strong></td></tr>
                            <tr><td><?= __t('Total cash flow into register:', 'مجموع کل پول نقد وارد شده به صندوق:', 'صندوق ته ټولې داخلې شوې پیسې:') ?></td><td><strong>165,000 <?= get_currency_label() ?></strong></td></tr>
                            <tr><td><?= __t('Unsettled customer receivables:', 'قرضه های پرداخت نشده مشتریان:', 'د پیرودونکو نا تادیه شوي پورونه:') ?></td><td><strong>30,000 <?= get_currency_label() ?></strong></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="locked-overlay-card" style="padding: 16px;">
                    <h4 style="font-size: 13.5px; margin-bottom: 6px;"><i class="fa-solid fa-lock text-amber-500"></i> <?= __t('This Feature is Locked', 'این گزینه قفل می‌باشد', 'دا برخه قفل ده') ?></h4>
                    <p style="font-size: 12px; margin-bottom: 6px;"><?= __t('Contact Ahmad Najeem Nik (+93783788278) to unlock full access.', 'جهت درخواست این گزینه با احمد نجیم نیک تماس بگیرید', 'د پرانیستلو لپاره له احمد نجیم نیک سره اړیکه ونیسئ') ?></p>
                    <a href="https://wa.me/93783788278?text=Hello%20Ahmad%20Najeem%20Nik,%20I%20would%20like%20to%20unlock%20Cash%20Flow%20Report" target="_blank" class="btn-whatsapp-direct" style="padding: 6px 12px; font-size: 11px;">
                        <i class="fa-brands fa-whatsapp"></i> <?= __t('WhatsApp Ahmad Najeem Nik', 'واتس‌اپ احمد نجیم نیک', 'د احمد نجیم نیک واټس اپ') ?>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <table class="table">
                <tbody>
                    <tr>
                        <td><?= __t('Total cash collected from sales:', 'مجموع نقدینگی دریافت شده از فروشات:', 'له پلور څخه ترلاسه شوې نغدې پیسې:') ?></td>
                        <td><strong style="color: var(--success);"><?= format_money($totalPaidAmount) ?></strong></td>
                    </tr>
                    <tr>
                        <td><?= __t('Installments collected from receivables:', 'اقساط و وصولی های دریافتی از قرضه ها:', 'له پورونو ترلاسه شوي قسطونه:') ?></td>
                        <td><strong style="color: var(--success);"><?= format_money($installmentsCollected) ?></strong></td>
                    </tr>
                    <tr>
                        <td><?= __t('Total cash flow into store register:', 'مجموع کل پول نقد وارد شده به صندوق:', 'صندوق ته ټولې داخلې شوې نغدې پیسې:') ?></td>
                        <td><strong style="color: var(--text-main); font-size: 14px;"><?= format_money($totalPaidAmount + $installmentsCollected) ?></strong></td>
                    </tr>
                    <tr>
                        <td><?= __t('Operating cash expenses paid from register:', 'مصارف نقدی جاری دکان (پرداخت‌شده از دخل):', 'له دخل څخه تادیه شوي لګښتونه:') ?></td>
                        <td><strong style="color: #ea580c;"><?= format_money($totalExpenses) ?></strong></td>
                    </tr>
                    <tr>
                        <td><?= __t('Warehouse goods damage & loss depreciation:', 'ارزش ضرر و خسارات اجناس گدام (افت ارزش موجودی):', 'د ګودام د توکو تاوان او افت:') ?></td>
                        <td><strong style="color: var(--danger);"><?= format_money($totalLosses) ?></strong></td>
                    </tr>
                    <tr>
                        <td><?= __t('Payouts and settlements with Mudaraba partners:', 'پرداختی‌ها و تسویه با شرکای مضاربت در این بازه:', 'له مضاربت شریکانو سره تصفیه:') ?></td>
                        <td><strong style="color: var(--danger);"><?= format_money($mudarabaPayoutInPeriod) ?></strong></td>
                    </tr>
                    <tr style="background: var(--surface-muted);">
                        <td><strong><?= __t('Net Available Cash Register Balance:', 'خالص نقدینگی نهایی صندوق (دخل نقدی):', 'د صندوق خالصې نغدې پیسې:') ?></strong></td>
                        <?php $netCash = ($totalPaidAmount + $installmentsCollected) - $totalExpenses - $mudarabaPayoutInPeriod; ?>
                        <td><strong style="color: <?= $netCash >= 0 ? 'var(--success)' : 'var(--danger)' ?>; font-size: 15px;"><?= format_money($netCash) ?></strong></td>
                    </tr>
                    <tr>
                        <td><?= __t('Unsettled customer receivables recorded in period:', 'قرضه های ثبت شده و پرداخت نشده مشتریان در این بازه:', 'په دې موده کې د پیرودونکو پاتې پورونه:') ?></td>
                        <td><strong style="color: var(--danger);"><?= format_money($totalRemainingAmount) ?></strong></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Top Sold Products -->
    <div class="card" data-permission="reports.top_selling" <?= domain_visibility('reports.top_selling')==='hidden'?'hidden':'' ?> style="position: relative;">
        <div class="card-header">
            <h3 class="card-title" style="display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-fire ml-1"></i> <?= __t('Top Selling Products in this Period', 'پرفروش ترین اجناس در این دوره', 'په دې دوره کې ډېر پلورل شوي توکي') ?>
                <?php if (is_feature_locked('top_selling_report')): ?>
                    <span class="badge-lock-tag" onclick="showLockModal('top_selling_report', 'Top Selling Products Report', '<?= __t('Top Selling Products Report', 'پرفروش‌ترین اجناس در این دوره', 'ډېر پلورل شوي توکي') ?>');" style="cursor: pointer;">
                        <i class="fa-solid fa-lock text-amber-500"></i> <?= __t('Locked', 'قفل', 'قفل') ?>
                    </span>
                <?php endif; ?>
            </h3>
        </div>
        <?php if (is_feature_locked('top_selling_report')): ?>
            <div class="locked-blur-wrapper" style="min-height: 220px;">
                <div class="locked-blur-content">
                    <table class="table">
                        <thead><tr><th><?= __t('Product Name', 'اسم جنس', 'د توکي نوم') ?></th><th><?= __t('Qty', 'تعداد', 'شمېر') ?></th><th><?= __t('Total Revenue', 'مجموع فروش', 'ټول پلور') ?></th><th><?= __t('Profit', 'مفاد', 'ګټه') ?></th></tr></thead>
                        <tbody>
                            <tr><td>Product A</td><td>12</td><td>240,000</td><td>18,000</td></tr>
                            <tr><td>Product C</td><td>35</td><td>17,500</td><td>5,250</td></tr>
                            <tr><td>Product B</td><td>4</td><td>180,000</td><td>12,000</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="locked-overlay-card" style="padding: 16px;">
                    <h4 style="font-size: 13.5px; margin-bottom: 6px;"><i class="fa-solid fa-lock text-amber-500"></i> <?= __t('This Feature is Locked', 'این گزینه قفل می‌باشد', 'دا برخه قفل ده') ?></h4>
                    <p style="font-size: 12px; margin-bottom: 6px;"><?= __t('Contact Ahmad Najeem Nik (+93783788278) to unlock full access.', 'جهت درخواست این گزینه با احمد نجیم نیک تماس بگیرید', 'د پرانیستلو لپاره اړیکه ونیسئ') ?></p>
                    <a href="https://wa.me/93783788278?text=Hello%20Ahmad%20Najeem%20Nik,%20I%20would%20like%20to%20unlock%20Top%20Selling%20Products%20Report" target="_blank" class="btn-whatsapp-direct" style="padding: 6px 12px; font-size: 11px;">
                        <i class="fa-brands fa-whatsapp"></i> <?= __t('WhatsApp Ahmad Najeem Nik', 'واتس‌اپ احمد نجیم نیک', 'د احمد نجیم نیک واټس اپ') ?>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= __t('Product Name', 'اسم جنس', 'د توکي نوم') ?></th>
                            <th style="width: 70px; text-align: center;"><?= __t('Quantity', 'تعداد', 'شمېر') ?></th>
                            <th style="width: 90px;"><?= __t('Revenue', 'مجموع فروش', 'ټول پلور') ?></th>
                            <th style="width: 80px;"><?= __t('Profit', 'مفاد حاصله', 'لاسته راغلې ګټه') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topItems)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 16px;">
                                    <?= __t('No sales recorded in this period.', 'در این بازه زمانی فروشی ثبت نگردیده است.', 'په دې دوره کې هېڅ پلور نه دی ثبت شوی.') ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topItems as $top): ?>
                                <tr>
                                    <td><strong><?= sanitize($top['product_name']) ?></strong></td>
                                    <td style="text-align: center;"><span class="badge badge-secondary"><?= $currentLang === 'en' ? $top['sold_qty'] : to_persian_num($top['sold_qty']) ?></span></td>
                                    <td><?= format_money($top['total_revenue']) ?></td>
                                    <td><strong style="color: var(--success);"><?= currency_profit_html($top['total_item_profit'],$top['profit_known']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mudaraba Foreign Investment & Partnership Report Section -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h3 class="card-title" style="display: flex; align-items: center; gap: 8px; color: var(--success); margin: 0;">
            <i class="fa-solid fa-handshake-angle"></i>
            <?= __t('Mudaraba & Foreign Investment Financial Statement & Ledger', 'صورتحساب و راپور مالی مضاربت و سرمایه‌گذاری خارجی', 'د مضاربت او بهرنۍ پانګونې مالي صورتحساب') ?>
        </h3>
        <span class="badge" style="background: var(--success-soft); color: var(--success); font-size: 12px; font-weight: 700;">
            <?= sprintf(__t('%s partners and Mudaraba projects', '%s شریک و پروژه مضاربت', '%s شریکان او د مضاربت پروژې'), ($currentLang === 'en' ? count($mudContracts) : to_persian_num(count($mudContracts)))) ?>
        </span>
    </div>

    <!-- Quick stats cards for Mudaraba -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 18px; margin-top: 10px;">
        <div style="background: var(--success-soft); border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px; text-align: center;">
            <div style="font-size: 11.5px; color: var(--success); font-weight: 700;"><?= __t('Active Mudaraba Capital', 'سرمایه فعال مضاربت', 'فعاله مضاربت پانګه') ?></div>
            <div style="font-size: 18px; font-weight: 800; color: var(--success); margin-top: 2px;"><?= format_money($totalMudActiveCap) ?></div>
        </div>
        <div style="background: var(--info-soft); border: 1px solid #bfdbfe; border-radius: 10px; padding: 12px; text-align: center;">
            <div style="font-size: 11.5px; color: var(--info); font-weight: 700;"><?= __t('Total Mudaraba Goods Sold', 'مجموع فروش کالای مضاربت', 'د مضاربت د توکو ټولټال پلور') ?></div>
            <div style="font-size: 18px; font-weight: 800; color: var(--info); margin-top: 2px;"><?= format_money($totalMudSales) ?></div>
        </div>
        <div style="background: var(--purple-soft); border: 1px solid #e9d5ff; border-radius: 10px; padding: 12px; text-align: center;">
            <div style="font-size: 11.5px; color: var(--purple); font-weight: 700;"><?= __t('Total Realized Profit', 'مجموع سود حاصله', 'ټولټال ترلاسه شوې ګټه') ?></div>
            <div style="font-size: 18px; font-weight: 800; color: var(--purple); margin-top: 2px;"><?= currency_profit_html($totalMudProfit,!array_filter($mudContracts,fn($c)=>!$c['profit_known'])) ?></div>
        </div>
        <div style="background: var(--warning-soft); border: 1px solid #fed7aa; border-radius: 10px; padding: 12px; text-align: center;">
            <div style="font-size: 11.5px; color: #9a3412; font-weight: 700;"><?= __t('Total Profit Paid to Partners', 'کل سود پرداخت‌شده به شرکا', 'شریکانو ته ورکړل شوې ګټه') ?></div>
            <div style="font-size: 18px; font-weight: 800; color: #ea580c; margin-top: 2px;"><?= format_money($totalMudPaidProfit) ?></div>
        </div>
        <div style="background: #fdf2f8; border: 1px solid #fbcfe8; border-radius: 10px; padding: 12px; text-align: center;">
            <div style="font-size: 11.5px; color: #9d174d; font-weight: 700;"><?= __t('Principal Capital Returned', 'اصل سرمایه مسترد شده', 'بېرته ورکړل شوې اصلي پانګه') ?></div>
            <div style="font-size: 18px; font-weight: 800; color: #db2777; margin-top: 2px;"><?= format_money($totalMudReturnedCap) ?></div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 35px;">#</th>
                    <th><?= __t('Partner Name', 'نام شریک مضاربت', 'د مضاربت شریک نوم') ?></th>
                    <th><?= __t('Project / Commercial Goods', 'پروژه / کالای تجاری', 'پروژه / تجارتي توکي') ?></th>
                    <th><?= __t('Initial Capital', 'سرمایه اولیه', 'لومړنۍ پانګه') ?></th>
                    <th><?= __t('Profit Ratio', 'نسبت سود', 'د ګټې نسبت') ?></th>
                    <th><?= __t('Sales Revenue', 'فروشات', 'پلور') ?></th>
                    <th><?= __t('Gross Profit', 'سود کل', 'ټوله ګټه') ?></th>
                    <th><?= __t('Partner Share', 'سهم سود شریک', 'د شریک د ګټې ونډه') ?></th>
                    <th><?= __t('Paid Profit', 'سود پرداخت شده', 'ورکړل شوې ګټه') ?></th>
                    <th><?= __t('Returned Capital', 'سرمایه مسترد شده', 'بېرته ورکړل شوې پانګه') ?></th>
                    <th><?= __t('Remaining Balance', 'مانده طلب شریک', 'د شریک پاتې طلب') ?></th>
                    <th><?= __t('Status', 'وضعیت', 'حالت') ?></th>
                    <th><?= __t('Invoice Sheet', 'سند بل', 'بل سند') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mudContracts)): ?>
                    <tr>
                        <td colspan="13" style="text-align: center; color: var(--text-muted); padding: 16px;">
                            <?= __t('No Mudaraba contracts registered yet.', 'هیچ قرارداد مضاربتی ثبت نگردیده است.', 'هېڅ مضاربت قرارداد نه دی ثبت شوی.') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $mIdx = 1;
                    foreach ($mudContracts as $mc): 
                        $pShare = ((float)$mc['total_profit_amount'] * (float)$mc['partner_profit_rate']) / 100;
                        $remProfit = max(0, $pShare - (float)($mc['paid_profit_to_partner'] ?? 0));
                        $remCap = max(0, (float)$mc['capital_amount'] - (float)($mc['returned_capital'] ?? 0));
                        $totalRem = $remProfit + $remCap;
                    ?>
                    <tr>
                        <td><?= $currentLang === 'en' ? $mIdx++ : to_persian_num($mIdx++) ?></td>
                        <td><strong><?= sanitize($mc['partner_name']) ?></strong></td>
                        <td><?= sanitize($mc['project_title']) ?></td>
                        <td><strong><?= format_money($mc['capital_amount'], false) ?></strong></td>
                        <td><?= $currentLang === 'en' ? (float)$mc['partner_profit_rate'] : to_persian_num((float)$mc['partner_profit_rate']) ?>%</td>
                        <td><?= format_money($mc['total_sales_amount'], false) ?></td>
                        <td style="color: var(--info); font-weight: 700;"><?= currency_profit_html($mc['total_profit_amount'],$mc['profit_known']) ?></td>
                        <td style="color: var(--purple); font-weight: 700;"><?= currency_profit_html($pShare,$mc['profit_known']) ?></td>
                        <td style="color: var(--success); font-weight: 700;"><?= format_money($mc['paid_profit_to_partner'], false) ?></td>
                        <td style="color: var(--info); font-weight: 700;"><?= format_money($mc['returned_capital'], false) ?></td>
                        <td style="color: <?= $totalRem > 0 ? 'var(--danger)' : 'var(--success)' ?>; font-weight: 800;">
                            <?= !$mc['profit_known']?currency_profit_html(0,false):($totalRem > 0 ? format_money($totalRem, false) : __t('Fully Settled', 'تسویه کامل', 'بشپړه تصفیه')) ?>
                        </td>
                        <td>
                            <?php if ($mc['status'] === 'settled'): ?>
                                <span class="badge badge-secondary"><?= __t('Settled', 'تسویه شده', 'تصفیه شوی') ?></span>
                            <?php else: ?>
                                <span class="badge badge-success"><?= __t('Active', 'جاری', 'جاري') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>print/mudaraba.php?id=<?= $mc['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding: 2px 7px; font-size: 11px;" title="<?= __t('Print Official Mudaraba Contract', 'چاپ سند رسمی قرارداد مضاربت', 'د مضاربت د قرارداد سند چاپ کړئ') ?>">
                                <i class="fa-solid fa-print"></i> <?= __t('Bill', 'بل', 'بل') ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Payout Receipts Log in Period -->
    <div style="margin-top: 24px; border-top: 1px solid var(--border-color); padding-top: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h4 style="font-size: 14px; font-weight: 800; color: var(--success); margin: 0; display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-receipt"></i>
                <?= __t('Mudaraba Payout Receipts & Settlement History in Period', 'تاریخچه پرداختی‌ها و اسناد تسویه با شرکای مضاربت در این بازه', 'په دې موده کې د مضاربت د تصفیو او تادیاتو تاریخچه') ?>
            </h4>
            <span style="font-size: 12px; color: var(--text-muted);">
                <?= __t('Period Total Payout: ', 'مجموع پرداختی دوره: ', 'د دورې ټولټال تادیه: ') ?><strong style="color: var(--danger);"><?= format_money($mudarabaPayoutInPeriod) ?></strong> (<?= __t('Deducted from store capital', 'کسر شده از سرمایه دکان', 'د هټۍ له پانګې کسر شوې') ?>)
            </span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr style="background: var(--surface-muted);">
                        <th style="width: 35px;">#</th>
                        <th><?= __t('Receipt Date', 'تاریخ رسید', 'د رسید نېټه') ?></th>
                        <th><?= __t('Payment Description & Details', 'شرح و تفصیلات پرداخت', 'د تادیې شرح او تفصیل') ?></th>
                        <th><?= __t('Accounting Classification', 'نوعیت حسابداری', 'حسابي ډول') ?></th>
                        <th><?= __t('Paid Amount (Store Capital Deduction)', 'مبلغ پرداختی (کسر از سرمایه دکان)', 'ورکړل شوې پیسې') ?></th>
                        <th><?= __t('Document Status', 'وضعیت سند', 'د سند حالت') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mudPayoutRecords)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 14px; font-size: 12.5px;">
                                <?= __t('No Mudaraba partner payouts were recorded during this time period.', 'در این بازه زمانی هیچ پرداختی برای شرکای مضاربت ثبت نشده است.', 'په دې موده کې د مضاربت شریکانو ته هېڅ تادیه نه ده ثبت شوې.') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $rIdx = 1;
                        foreach ($mudPayoutRecords as $mRec): 
                            $rDate = !empty($mRec['transaction_date']) ? $mRec['transaction_date'] : date('Y-m-d', strtotime($mRec['created_at']));
                        ?>
                        <tr>
                            <td><?= $currentLang === 'en' ? $rIdx++ : to_persian_num($rIdx++) ?></td>
                            <td><strong><?= $currentLang === 'en' ? $rDate : to_persian_num($rDate) ?></strong></td>
                            <td style="font-size: 12.5px; color: var(--text-main);"><?= sanitize($mRec['source_description']) ?></td>
                            <td><span class="badge badge-danger"><?= __t('Store Capital Withdrawal', 'برداشت از سرمایه دکان', 'د هټۍ له پانګې ایستل') ?></span></td>
                            <td style="color: var(--danger); font-weight: 800; font-size: 13.5px;">
                                -<?= format_money($mRec['amount']) ?>
                            </td>
                            <td>
                                <span class="badge badge-success" style="font-size: 11px;">
                                    <i class="fa-solid fa-circle-check ml-1"></i> <?= __t('Approved & Disbursed', 'تایید و تسلیم شده', 'تایید او تسلیم شوی') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
