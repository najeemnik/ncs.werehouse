<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Main Executive Dashboard
 * URL: http://localhost/nikxor/index.php
 */

$pageTitle = 'داشبورد عمومی دکان';
require_once __DIR__ . '/includes/header.php';

// Calculate core metrics
$metrics = get_dashboard_metrics();
$accountWhere=currency_sql();
$salesAccountWhere=currency_sql('s');

// Fetch low stock items (quantity <= 3)
$lowStockStmt = $pdo->query("SELECT s.*, c.name as category_name 
                             FROM stock s 
                             LEFT JOIN categories c ON s.category_id = c.id 
                             WHERE s.quantity <= 3 AND ".shop_sql('s')." 
                             ORDER BY s.quantity ASC LIMIT 6");
$lowStockItems = $lowStockStmt->fetchAll();
$showInventoryAlerts=ui_preferences()['notifications']==='on';

// Fetch Mudaraba contracts for dashboard display
$mudarabaContracts = currency_contract_views($pdo->query("SELECT * FROM mudaraba_contracts WHERE " . shop_sql() . " ORDER BY id DESC")->fetchAll());
$totalMudarabaActiveCap = 0;
$totalMudarabaSales = 0;
$totalMudarabaShopProfit = 0;
$activeMudarabaCount = 0;
foreach ($mudarabaContracts as $mc) {
    if ($mc['status'] === 'active') {
        $totalMudarabaActiveCap += (float)$mc['capital_amount'];
        $activeMudarabaCount++;
    }
    $totalMudarabaSales += (float)$mc['total_sales_amount'];
    $totalMudarabaShopProfit += ((float)$mc['total_profit_amount'] * (float)$mc['shop_profit_rate']) / 100;
}

// Fetch latest sales (last 6 transactions)
$recentSalesStmt = $pdo->query("SELECT s.*, u.fullname as staff_name 
                                FROM sales s 
                                LEFT JOIN users u ON s.created_by = u.id 
                                WHERE {$salesAccountWhere} AND ".shop_sql('s')." ORDER BY s.id DESC LIMIT 6");
$recentSales = $recentSalesStmt->fetchAll();
?>

<!-- Metric Statistics Cards -->
<div class="stats-grid dashboard-stats">
    <!-- Card 1: ارزش موجودی به قیمت فروش / Inventory value at selling price -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= __('card_stock_sale_value') ?></span>
            <div class="stat-icon icon-stock">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
        </div>
        <div class="stat-value"><?= format_money($metrics['stock_sale_value']) ?></div>
        <div class="stat-desc">
            <?= sprintf(__('card_stock_sale_value_desc'), format_money($metrics['stock_value'], false)) ?><br>
            <i class="fa-solid fa-layer-group ml-1"></i> <?= sprintf(__t('%s items in shop','%s مقدار موجودی در دکان','په هټۍ کې %s موجودي'), to_persian_num($metrics['total_items_count'])) ?>
        </div>
    </div>

    <!-- Card 2: سرمایه مضاربت و کالای خارجی / Mudaraba Capital -->
    <div class="stat-card" style="border-right: 4px solid var(--success);">
        <div class="stat-header">
            <span class="stat-title"><?= __('nav_mudaraba') ?></span>
            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: var(--success);">
                <i class="fa-solid fa-handshake-angle"></i>
            </div>
        </div>
        <div class="stat-value" style="color: var(--success);"><?= format_money($totalMudarabaActiveCap) ?></div>
        <div class="stat-desc" style="display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fa-solid fa-globe ml-1"></i> <?= to_persian_num($activeMudarabaCount) ?> <?= __('card_total_items_desc') ?></span>
            <a href="<?= BASE_URL ?>modules/mudaraba.php" style="color: var(--info); font-weight: 700; text-decoration: none; font-size: 11.5px;">
                <?= __('manage') ?> <i class="fa-solid fa-arrow-left"></i>
            </a>
        </div>
    </div>

    <!-- Card 3: سرمایه عمومی / Shop Capital -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= __('card_shop_capital') ?></span>
            <div class="stat-icon icon-capital">
                <i class="fa-solid fa-vault"></i>
            </div>
        </div>
        <div class="stat-value"><?= currency_profit_html($metrics['total_capital'],$metrics['profit_known']) ?></div>
        <div class="stat-desc">
            <i class="fa-solid fa-building-columns ml-1"></i> <?= __('card_shop_capital_desc') ?>
        </div>
    </div>

    <!-- Card 4: فروشات امروز / Today's Sales -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= __('card_today_sales') ?></span>
            <div class="stat-icon icon-sales">
                <i class="fa-solid fa-cart-shopping"></i>
            </div>
        </div>
        <div class="stat-value" style="color: var(--info);"><?= format_money($metrics['today_sales']) ?></div>
        <div class="stat-desc">
            <?php if ($metrics['today_sales'] > 0): ?>
                <?= __('card_today_sales_profit') ?> <strong style="color: var(--success);"><?= currency_profit_html($metrics['today_sales_profit'],$metrics['today_profit_known']) ?></strong>
            <?php else: ?>
                <span style="color: var(--text-muted);"><i class="fa-solid fa-receipt ml-1"></i> <?= __('card_no_sales_today') ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card 5: وضعیت سود و زیان نهایی امروز / Today's Net Profit or Loss -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= $metrics['today_net_profit'] >= 0 ? __('card_today_net_profit') : __('card_today_net_loss') ?></span>
            <div class="stat-icon" style="background: <?= $metrics['today_net_profit'] >= 0 ? 'rgba(22, 163, 74, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; color: <?= $metrics['today_net_profit'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                <i class="fa-solid <?= $metrics['today_net_profit'] >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
            </div>
        </div>
        <div class="stat-value" style="color: <?= $metrics['today_net_profit'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
            <?php if(!$metrics['today_profit_known']): ?><?= currency_profit_html(0,false) ?>
            <?php elseif ($metrics['today_net_profit'] > 0): ?>
                +<?= format_money($metrics['today_net_profit']) ?>
            <?php elseif ($metrics['today_net_profit'] < 0): ?>
                -<?= format_money(abs($metrics['today_net_profit'])) ?>
            <?php else: ?>
                <?= format_money(0) ?>
            <?php endif; ?>
        </div>
        <div class="stat-desc" style="font-size: 11px;">
            <?php if(!$metrics['today_profit_known']): ?><?= __t('Mixed-currency profit is not calculated.','سود معاملات چندارزی محاسبه نشده است.','د څو اسعارو ګټه نه ده حساب شوې.') ?>
            <?php elseif ($metrics['today_sales_profit'] == 0 && ($metrics['today_losses'] > 0 || $metrics['today_expenses'] > 0)): ?>
                <span style="color: var(--danger);"><i class="fa-solid fa-triangle-exclamation ml-1"></i> <?= __('card_deficit_explanation') ?></span>
            <?php else: ?>
                <?= sprintf(__('card_breakdown'), format_money($metrics['today_sales_profit'], false), format_money($metrics['today_expenses'], false), format_money($metrics['today_losses'], false)) ?>
            <?php endif; ?>
            <?php if ($metrics['today_losses'] > 0): ?><br><span style="color: var(--danger);"><i class="fa-solid fa-triangle-exclamation ml-1"></i> <?= sprintf(__('card_today_losses_line'), format_money($metrics['today_losses'], false)) ?></span><?php endif; ?>
        </div>
    </div>

    <!-- Card 7: مصارف جاری دکان / Operating Expenses -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= __('card_expenses') ?></span>
            <div class="stat-icon icon-debt" style="background: rgba(245, 158, 11, 0.15); color: var(--warning);">
                <i class="fa-solid fa-receipt"></i>
            </div>
        </div>
        <div class="stat-value" style="color: var(--warning);">
            <?= format_money($metrics['today_expenses']) ?>
        </div>
        <div class="stat-desc" style="display: flex; justify-content: space-between; align-items: center;">
            <span><?= sprintf(__('card_expenses_desc'), format_money($metrics['total_expenses'], false)) ?></span>
            <a href="<?= BASE_URL ?>modules/expenses.php" style="color: var(--warning); font-weight: 700; text-decoration: none; font-size: 11px;">
                <?= __('manage') ?> <i class="fa-solid fa-arrow-left"></i>
            </a>
        </div>
    </div>

    <!-- Card 8: مجموع مفاد عمومی / Overall Net Profit or Loss -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= $metrics['overall_net_profit'] >= 0 ? __('card_overall_profit') : __('card_overall_loss') ?></span>
            <div class="stat-icon" style="background: <?= $metrics['overall_net_profit'] >= 0 ? 'rgba(22, 163, 74, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; color: <?= $metrics['overall_net_profit'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                <i class="fa-solid <?= $metrics['overall_net_profit'] >= 0 ? 'fa-chart-line' : 'fa-arrow-trend-down' ?>"></i>
            </div>
        </div>
        <div class="stat-value" style="color: <?= $metrics['overall_net_profit'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
            <?php if(!$metrics['profit_known']): ?><?= currency_profit_html(0,false) ?>
            <?php elseif ($metrics['overall_net_profit'] >= 0): ?>
                <?= format_money($metrics['overall_net_profit']) ?>
            <?php else: ?>
                -<?= format_money(abs($metrics['overall_net_profit'])) ?>
            <?php endif; ?>
        </div>
        <div class="stat-desc">
            <?= __('card_today_sales_profit') ?> <?= currency_profit_html($metrics['total_sales_profit'],$metrics['profit_known']) ?><br>
            <span style="color: var(--danger);"><i class="fa-solid fa-triangle-exclamation ml-1"></i> <?= sprintf(__('card_total_losses'), format_money($metrics['total_losses'], false)) ?></span>
        </div>
    </div>

    <!-- Card 9: قرضه های باقی مانده / Debts -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title"><?= __('card_debts') ?></span>
            <div class="stat-icon icon-debt">
                <i class="fa-solid fa-hand-holding-dollar"></i>
            </div>
        </div>
        <div class="stat-value" style="color: var(--danger);">
            <?= format_money($metrics['total_outstanding_debts']) ?>
        </div>
        <div class="stat-desc">
            <?= __('card_debts_desc') ?>
        </div>
    </div>
</div>

<!-- Quick Action Shortcuts -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-bolt ml-1"></i> <?= __('quick_actions_title') ?>
        </h3>
    </div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="<?= BASE_URL ?>modules/sales.php" class="btn btn-primary">
            <i class="fa-solid fa-cash-register"></i> <?= __('btn_new_sale') ?>
        </a>
        <a href="<?= BASE_URL ?>modules/invoices.php" class="btn btn-secondary" style="background: var(--info); color: var(--surface);">
            <i class="fa-solid fa-receipt"></i> <?= __('btn_invoices_archive') ?>
        </a>
        <a href="<?= BASE_URL ?>modules/mudaraba.php" class="btn btn-secondary" style="background: var(--success); color: var(--surface);">
            <i class="fa-solid fa-handshake-angle"></i> <?= __('btn_mudaraba') ?>
        </a>
        <a href="<?= BASE_URL ?>modules/stock_add.php" class="btn btn-secondary">
            <i class="fa-solid fa-plus-circle"></i> <?= __('btn_add_stock') ?>
        </a>
        <a href="<?= BASE_URL ?>modules/stock.php" class="btn btn-secondary">
            <i class="fa-solid fa-boxes-stacked"></i> <?= __('btn_stock_list') ?>
        </a>
        <a href="<?= BASE_URL ?>modules/debts.php" class="btn btn-secondary">
            <i class="fa-solid fa-file-invoice"></i> <?= __('btn_debts') ?>
        </a>
        <a href="<?= BASE_URL ?>modules/reports.php" class="btn btn-secondary">
            <i class="fa-solid fa-chart-pie"></i> <?= __('btn_financial_reports') ?>
        </a>
    </div>
</div>

<!-- MUDARABA PARTNERS LIVE PANEL IN DASHBOARD -->
<div class="card" style="margin-bottom: 24px; border-top: 3px solid var(--success);">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title" style="color: var(--success);">
            <i class="fa-solid fa-handshake-angle ml-1"></i> <?= __('mudaraba_table_title') ?>
        </h3>
        <a href="<?= BASE_URL ?>modules/mudaraba.php" class="btn btn-sm btn-secondary">
            <i class="fa-solid <?= $currentDir === 'rtl' ? 'fa-arrow-left' : 'fa-arrow-right' ?> ml-1"></i> <?= __('mudaraba_manage_link') ?>
        </a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th><?= __('partner_name') ?></th>
                    <th><?= __('consignment_desc') ?></th>
                    <th><?= __('country_origin') ?></th>
                    <th style="text-align: center;"><?= __('capital_provided') ?></th>
                    <th style="text-align: center;"><?= __('profit_split') ?></th>
                    <th style="text-align: center;"><?= __('realized_profit') ?></th>
                    <th style="text-align: center;"><?= __('partner_share') ?></th>
                    <th style="text-align: center;"><?= __('status') ?></th>
                    <th style="text-align: center; width: 140px;"><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mudarabaContracts)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 24px; color: var(--text-light);">
                            <?= __('no_mudaraba_notice') ?> 
                            <a href="<?= BASE_URL ?>modules/mudaraba.php" style="color: var(--info); font-weight: 700;"><?= __('add_first_mudaraba') ?></a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach (array_slice($mudarabaContracts, 0, 5) as $mc): 
                        $pShare = ((float)$mc['total_profit_amount'] * (float)$mc['partner_profit_rate']) / 100;
                    ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($mc['partner_name']) ?></strong>
                                <?php if (!empty($mc['phone'])): ?>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= sanitize($mc['phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-weight: 700; color: var(--text-main);"><?= sanitize($mc['project_title']) ?></span>
                            </td>
                            <td>
                                <span class="badge" style="background: var(--surface-muted); color: var(--text-main); font-size: 11.5px;">
                                    <?= sanitize($mc['country_source'] ?: ($currentLang === 'en' ? 'Foreign' : ($currentLang === 'ps' ? 'بهرني' : 'خارجی'))) ?>
                                </span>
                            </td>
                            <td style="text-align: center; font-weight: 800; color: var(--success);">
                                <?= format_money($mc['capital_amount'], false) ?>
                            </td>
                            <td style="text-align: center; font-size: 12px; font-weight: 700;">
                                <?= (float)$mc['partner_profit_rate'] ?>% / <?= (float)$mc['shop_profit_rate'] ?>%
                            </td>
                            <td style="text-align: center; font-weight: 800; color: var(--info);">
                                <?= currency_profit_html($mc['total_profit_amount'],$mc['profit_known']) ?>
                            </td>
                            <td style="text-align: center; font-weight: 800; color: var(--purple);">
                                <?= currency_profit_html($pShare,$mc['profit_known']) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= ($mc['status'] === 'active') ? 'success' : 'secondary' ?>">
                                    <?= ($mc['status'] === 'active') ? __('active') : __('settled') ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <a href="<?= BASE_URL ?>print/mudaraba.php?id=<?= $mc['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" title="<?= __('print') ?>">
                                    <i class="fa-solid fa-print"></i> PDF
                                </a>
                                <a href="<?= BASE_URL ?>modules/mudaraba.php" class="btn btn-sm btn-primary" title="<?= __('manage') ?>">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px;">
    <!-- Shop Status & Financial Summary -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-chart-simple ml-1"></i> <?= __('cash_flow_status') ?>
            </h3>
            <a href="<?= BASE_URL ?>modules/reports.php" class="btn btn-sm btn-secondary">
                <i class="fa-solid fa-file-invoice"></i> <?= __('view_invoices_in_reports') ?>
            </a>
        </div>
        <div style="padding: 10px 0;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 16px;">
                <div style="background: var(--surface-muted); padding: 12px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 4px;"><?= __('today_invoice_sales') ?></div>
                    <div style="font-size: 17px; font-weight: 800; color: var(--info);"><?= format_money($metrics['today_sales']) ?></div>
                    <div style="font-size: 11px; color: var(--success); margin-top: 4px;"><i class="fa-solid fa-check-circle ml-1"></i> <?= __('transactions_recorded') ?></div>
                </div>
                <div style="background: var(--danger-soft); padding: 12px; border-radius: 8px; border: 1px solid #fecaca;">
                    <div style="font-size: 11.5px; color: var(--danger); margin-bottom: 4px;"><?= __('today_damaged_goods') ?></div>
                    <div style="font-size: 17px; font-weight: 800; color: var(--danger);"><?= format_money($metrics['today_losses']) ?></div>
                    <div style="font-size: 11px; color: var(--danger); margin-top: 4px;"><i class="fa-solid fa-triangle-exclamation ml-1"></i> <?= __('deduction_from_stock_capital') ?></div>
                </div>
                <div style="background: var(--surface-muted); padding: 12px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 4px;"><?= __('market_receivables') ?></div>
                    <div style="font-size: 17px; font-weight: 800; color: var(--danger);"><?= format_money($metrics['total_outstanding_debts']) ?></div>
                    <div style="font-size: 11px; color: #eab308; margin-top: 4px;"><i class="fa-solid fa-clock ml-1"></i> <?= __('collectible_in_market') ?></div>
                </div>
            </div>

            <div style="background: var(--info-soft); border: 1px solid #bfdbfe; border-radius: 10px; padding: 16px; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h4 style="font-size: 14px; font-weight: 700; color: var(--info); margin-bottom: 4px;">
                        <i class="fa-solid fa-receipt ml-1"></i> <?= __('full_reports_banner_title') ?>
                    </h4>
                    <p style="font-size: 12.5px; color: var(--info); margin: 0;">
                        <?= __('full_reports_banner_desc') ?>
                    </p>
                </div>
                <a href="<?= BASE_URL ?>modules/reports.php" class="btn btn-primary btn-sm" style="white-space: nowrap;">
                    <?= __('btn_reports_and_invoices') ?> <i class="fa-solid <?= $currentDir === 'rtl' ? 'fa-arrow-left' : 'fa-arrow-right' ?> mr-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert Box -->
    <div class="card" data-permission="inventory.low_stock_alert" data-inventory-alert <?= ($showInventoryAlerts&&domain_visibility('inventory.low_stock_alert')!=='hidden')?'':'hidden' ?>>
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title" style="display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-triangle-exclamation" style="color: var(--danger);"></i> <?= __('low_stock_warning') ?>
                <?php if (is_feature_locked('low_stock_alert')): ?>
                    <span class="badge-lock-tag" onclick="showLockModal('low_stock_alert', 'Low Stock Alert Notification', '<?= __('low_stock_warning') ?>');">
                        <i class="fa-solid fa-lock"></i> <?= __('locked') ?>
                    </span>
                <?php endif; ?>
            </h3>
            <span class="badge badge-danger"><?= to_persian_num(count($lowStockItems)) ?> <?= __('items') ?></span>
        </div>

        <?php if (is_feature_locked('low_stock_alert')): ?>
            <!-- Locked Blurred View with Overlay -->
            <div class="locked-blur-wrapper">
                <div class="locked-blur-content" style="padding: 10px;">
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; justify-content: space-between; padding: 8px 10px; background-color: var(--surface-muted); border-radius: 6px;">
                            <div>
                                <div style="font-size: 13px; font-weight: 700;">Samsung Galaxy S24 Ultra</div>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= __('location') ?>: A1</div>
                            </div>
                            <span class="badge badge-danger">1 <?= __('pieces') ?></span>
                        </div>
                    </div>
                </div>
                <div class="locked-overlay-card">
                    <h4><i class="fa-solid fa-lock mr-1"></i> <?= __('feature_locked_title') ?></h4>
                    <p class="en-notice"><?= __('feature_locked_desc') ?></p>
                    <a href="https://wa.me/93783788278?text=Hello%20Ahmad%20Najeem%20Nik,%20I%20would%20like%20to%20unlock%20Low%20Stock%20Alert" target="_blank" class="btn-whatsapp-direct">
                        <i class="fa-brands fa-whatsapp"></i> <?= __('whatsapp_btn') ?>
                    </a>
                </div>
            </div>
            <div style="margin-top: 14px; text-align: center;">
                <button type="button" onclick="showLockModal('view_low_stock', 'View All Low Stock Items', '<?= __('low_stock_warning') ?>');" class="btn btn-sm btn-secondary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <i class="fa-solid fa-lock text-amber-500"></i> <?= __('low_stock_warning') ?>
                </button>
            </div>
        <?php else: ?>
            <!-- Unlocked View -->
            <?php if (empty($lowStockItems)): ?>
                <div style="text-align: center; padding: 24px 10px; color: var(--success);">
                    <i class="fa-solid fa-circle-check" style="font-size: 32px; margin-bottom: 8px;"></i>
                    <p style="font-size: 13px;"><?= __('no_low_stock') ?></p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($lowStockItems as $lItem): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; background-color: var(--surface-muted); border-radius: 6px; border: 1px solid var(--border-color);">
                            <div>
                                <div style="font-size: 13px; font-weight: 700; color: var(--text-main);"><?= sanitize($lItem['name']) ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= sanitize($lItem['warehouse_location'] ?? ($currentLang === 'en' ? 'Warehouse' : 'گدام')) ?></div>
                            </div>
                            <div style="text-align: <?= $currentDir === 'rtl' ? 'left' : 'right' ?>;">
                                <span class="badge <?= $lItem['quantity'] <= 0 ? 'badge-danger' : 'badge-warning' ?>" style="font-size: 12px;">
                                    <?= to_persian_num($lItem['quantity']) ?> <?= __('pieces') ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 14px; text-align: center;">
                    <a href="<?= BASE_URL ?>modules/stock.php?filter=low" class="btn btn-sm btn-secondary" style="width: 100%;">
                        <?= __('low_stock_warning') ?> (<?= to_persian_num(count($lowStockItems)) ?>)
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
