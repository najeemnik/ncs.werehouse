<?php
/**
 * NSC Executive Reporting & P&L Analysis API
 * Solves Excel Flaw #1: Multi-Year Monthly Performance Reports (Year + Month)
 * Generates Real-time Budget vs Actual and Profitability Snapshots
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

sendSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getConnection();
$user = authenticateUser(false);
$tenantId = getTenantId($user);
requireModule($user, 'reports', $method !== 'GET');


$reportType = $_GET['type'] ?? 'dashboard';
$projectId = $_GET['projectId'] ?? null;
$year = (int)($_GET['year'] ?? date('Y'));

// -------------------------------------------------------------
// ۱. گزارش داشبورد کلان و شاخص‌های کلیدی (Executive Dashboard)
// -------------------------------------------------------------
if ($reportType === 'dashboard') {
    // مجموع درآمد
    $stmt = prepareStatement($db, "SELECT COALESCE(SUM(amount_in_base_afn), 0) as total_rev FROM revenue_entries WHERE tenant_id = :tid");
    $stmt->execute(['tenant_scope' => $tenantId, 'tid' => $tenantId]);
    $totalRevenue = (float)$stmt->fetch()['total_rev'];

    // مجموع مصارف
    $stmt = prepareStatement($db, "SELECT COALESCE(SUM(amount_in_base_afn), 0) as total_exp FROM payment_vouchers WHERE tenant_id = :tid AND payment_status != 'cancelled'");
    $stmt->execute(['tenant_scope' => $tenantId, 'tid' => $tenantId]);
    $totalExpenses = (float)$stmt->fetch()['total_exp'];

    // مجموع بودجه مصوب پروژه‌ها
    $stmt = prepareStatement($db, "SELECT COALESCE(SUM(approved_budget), 0) as total_bgt FROM projects WHERE tenant_id = :tid AND status != 'archived'");
    $stmt->execute(['tenant_scope' => $tenantId, 'tid' => $tenantId]);
    $totalBudget = (float)$stmt->fetch()['total_bgt'];

    // موجودی بانک‌ها و صندوق‌ها
    $stmt = prepareStatement($db, "
        SELECT 
            COALESCE(SUM(CASE WHEN account_type = 'bank' THEN current_balance ELSE 0 END), 0) as bank_balance,
            COALESCE(SUM(CASE WHEN account_type = 'central_cash' THEN current_balance ELSE 0 END), 0) as cash_balance,
            COALESCE(SUM(CASE WHEN account_type = 'petty_cash' THEN current_balance ELSE 0 END), 0) as petty_balance
        FROM treasury_accounts
        WHERE tenant_id = :tid
    ");
    $stmt->execute(['tenant_scope' => $tenantId, 'tid' => $tenantId]);
    $treasury = $stmt->fetch();

    // مطالبات از مشتریان (Receivables)
    $stmt = prepareStatement($db, "SELECT COALESCE(SUM(outstanding_receivable), 0) as total_rec FROM clients WHERE tenant_id = :tid");
    $stmt->execute(['tenant_scope' => $tenantId, 'tid' => $tenantId]);
    $totalReceivables = (float)$stmt->fetch()['total_rec'];

    // بدهی‌ها به تأمین‌کنندگان (Payables)
    $stmt = prepareStatement($db, "SELECT COALESCE(SUM(current_payable_balance), 0) as total_pay FROM suppliers WHERE tenant_id = :tid");
    $stmt->execute(['tenant_scope' => $tenantId, 'tid' => $tenantId]);
    $totalPayables = (float)$stmt->fetch()['total_pay'];

    // تعداد پروژه‌های فعال و تکمیل شده
    $stmt = prepareStatement($db, "
        SELECT 
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count
        FROM projects
        WHERE tenant_id = :tid
    ");
    $stmt->execute(['tenant_scope' => $tenantId, 'tid' => $tenantId]);
    $counts = $stmt->fetch();

    $netProfit = $totalRevenue - $totalExpenses;
    $profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;
    $budgetUsedPercent = $totalBudget > 0 ? ($totalExpenses / $totalBudget) * 100 : 0;

    jsonResponse(true, [
        'totalRevenue' => $totalRevenue,
        'totalExpenses' => $totalExpenses,
        'netProfit' => $netProfit,
        'profitMarginPercent' => round($profitMargin, 2),
        'totalBudget' => $totalBudget,
        'budgetUsedPercent' => round($budgetUsedPercent, 2),
        'bankBalance' => (float)$treasury['bank_balance'],
        'cashBalance' => (float)$treasury['cash_balance'],
        'pettyBalance' => (float)$treasury['petty_balance'],
        'totalReceivables' => $totalReceivables,
        'totalPayables' => $totalPayables,
        'activeProjectsCount' => (int)$counts['active_count'],
        'completedProjectsCount' => (int)$counts['completed_count']
    ]);
}

// -------------------------------------------------------------
// ۲. گزارش سود و زیان جامع پروژه‌ها (Project P&L Snapshot)
// -------------------------------------------------------------
if ($reportType === 'project_pnl') {
    $stmt = prepareStatement($db, "
        SELECT 
            p.id, p.project_code, p.name, p.status, p.approved_budget,
            COALESCE((SELECT SUM(amount_in_base_afn) FROM revenue_entries WHERE project_id = p.id AND tenant_id = :tid), 0) as actual_revenue,
            COALESCE((SELECT SUM(amount_in_base_afn) FROM payment_vouchers WHERE project_id = p.id AND tenant_id = :tid AND payment_status != 'cancelled'), 0) as actual_cost
        FROM projects p
        WHERE p.tenant_id = :tid
        ORDER BY p.created_at DESC
    ");
    $stmt->execute(['tenant_scope' => $tenantId, 'tid' => $tenantId]);
    $rows = $stmt->fetchAll();

    $pnlList = [];
    foreach ($rows as $row) {
        $rev = (float)$row['actual_revenue'];
        $cost = (float)$row['actual_cost'];
        $budget = (float)$row['approved_budget'];
        $profit = $rev - $cost;
        $margin = $rev > 0 ? ($profit / $rev) * 100 : 0;
        $budgetUsed = $budget > 0 ? ($cost / $budget) * 100 : 0;
        $remainingBudget = $budget - $cost;

        $pnlList[] = [
            'projectId' => $row['id'],
            'projectCode' => $row['project_code'],
            'projectName' => $row['name'],
            'status' => $row['status'],
            'budget' => $budget,
            'revenue' => $rev,
            'actualCost' => $cost,
            'grossProfit' => $profit,
            'profitMarginPercent' => round($margin, 2),
            'budgetUsedPercent' => round($budgetUsed, 2),
            'remainingBudget' => $remainingBudget,
            'isOverBudget' => $cost > $budget
        ];
    }

    jsonResponse(true, ['pnl' => $pnlList]);
}

// -------------------------------------------------------------
// ۳. گزارش مقایسه بودجه با مصرف واقعی (Budget vs Actual)
// -------------------------------------------------------------
if ($reportType === 'budget_vs_actual') {
    if (empty($projectId)) {
        jsonResponse(false, null, 'شناسه پروژه مشخص نشده است.', 400);
    }

    // بودجه‌های سرفصل
    $bStmt = prepareStatement($db, "SELECT * FROM project_budgets WHERE project_id = :pid AND tenant_id = :tid");
    $bStmt->execute(['tenant_scope' => $tenantId, 'pid' => $projectId, 'tid' => $tenantId]);
    $budgets = $bStmt->fetchAll();

    // مصارف تفکیک شده
    $eStmt = prepareStatement($db, "
        SELECT expense_category, SUM(amount_in_base_afn) as actual_spent 
        FROM payment_vouchers 
        WHERE project_id = :pid AND tenant_id = :tid AND payment_status != 'cancelled'
        GROUP BY expense_category
    ");
    $eStmt->execute(['tenant_scope' => $tenantId, 'pid' => $projectId, 'tid' => $tenantId]);
    $spentMap = [];
    foreach ($eStmt->fetchAll() as $s) {
        $spentMap[$s['expense_category']] = (float)$s['actual_spent'];
    }

    $comparison = [];
    foreach ($budgets as $b) {
        $code = $b['category_code'];
        $allocated = (float)$b['budgeted_amount'];
        $actual = $spentMap[$code] ?? 0;
        $variance = $allocated - $actual;

        $comparison[] = [
            'categoryCode' => $code,
            'categoryName' => $b['category_name'],
            'budgeted' => $allocated,
            'actual' => $actual,
            'variance' => $variance,
            'isOverBudget' => $actual > $allocated,
            'percentUsed' => $allocated > 0 ? round(($actual / $allocated) * 100, 2) : 0
        ];
    }

    jsonResponse(true, ['comparison' => $comparison]);
}

// -------------------------------------------------------------
// ۴. گزارش ماهانه هوشمند با تفکیک سال (Monthly Report - Flaw #1 Solved)
// -------------------------------------------------------------
if ($reportType === 'monthly_timeline') {
    // محاسبه دقیق عواید ماهانه برای سال مشخص
    $revStmt = prepareStatement($db, "
        SELECT 
            MONTH(received_date) as m, 
            SUM(amount_in_base_afn) as monthly_income
        FROM revenue_entries
        WHERE YEAR(received_date) = :yr AND tenant_id = :tid
        GROUP BY MONTH(received_date)
    ");
    $revStmt->execute(['tenant_scope' => $tenantId, 'yr' => $year, 'tid' => $tenantId]);
    $revMonths = [];
    foreach ($revStmt->fetchAll() as $r) {
        $revMonths[(int)$r['m']] = (float)$r['monthly_income'];
    }

    // محاسبه مصارف ماهانه برای همان سال
    $expStmt = prepareStatement($db, "
        SELECT 
            MONTH(payment_date) as m, 
            SUM(amount_in_base_afn) as monthly_expense
        FROM payment_vouchers
        WHERE YEAR(payment_date) = :yr AND tenant_id = :tid AND payment_status != 'cancelled'
        GROUP BY MONTH(payment_date)
    ");
    $expStmt->execute(['tenant_scope' => $tenantId, 'yr' => $year, 'tid' => $tenantId]);
    $expMonths = [];
    foreach ($expStmt->fetchAll() as $e) {
        $expMonths[(int)$e['m']] = (float)$e['monthly_expense'];
    }

    $monthsNames = [
        1 => 'جنوری / حمل', 2 => 'فبروری / ثور', 3 => 'مارچ / جوزا',
        4 => 'اپریل / سرطان', 5 => 'می / اسد', 6 => 'جون / سنبله',
        7 => 'جولای / میزان', 8 => 'اگست / عقرب', 9 => 'سپتمبر / قوس',
        10 => 'اکتوبر / جدی', 11 => 'نوامبر / دلو', 12 => 'دسمبر / حوت'
    ];

    $timeline = [];
    for ($m = 1; $m <= 12; $m++) {
        $inc = $revMonths[$m] ?? 0;
        $exp = $expMonths[$m] ?? 0;
        $net = $inc - $exp;

        $timeline[] = [
            'year' => $year,
            'month' => $m,
            'monthName' => $monthsNames[$m],
            'income' => $inc,
            'expense' => $exp,
            'netMovement' => $net
        ];
    }

    jsonResponse(true, ['year' => $year, 'months' => $timeline]);
}

jsonResponse(false, null, 'Action is not available.', 404);
