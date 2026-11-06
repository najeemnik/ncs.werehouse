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

// Load optional panel data only when its presentation permission allows real values.
$showInventoryAlerts=ui_preferences()['notifications']==='on';$lowStockMode=domain_visibility('inventory.low_stock_alert');
$lowStockItems=($showInventoryAlerts&&$lowStockMode==='enabled')?$pdo->query("SELECT s.*, c.name as category_name FROM stock s LEFT JOIN categories c ON s.category_id = c.id WHERE s.quantity <= 3 AND ".shop_sql('s')." ORDER BY s.quantity ASC LIMIT 6")->fetchAll():[];

// Mudaraba values are never queried merely to render a locked or hidden panel.
$mudarabaMode=domain_visibility('dashboard.mudaraba');$mudarabaPanelMode=domain_visibility('dashboard.mudaraba_panel');$mudarabaMainMode=domain_visibility('mudaraba');$reportsMode=domain_visibility('reports');
$mudarabaContracts=($mudarabaMode==='enabled'||$mudarabaPanelMode==='enabled')?currency_contract_views($pdo->query("SELECT * FROM mudaraba_contracts WHERE " . shop_sql() . " ORDER BY id DESC")->fetchAll()):[];
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

?>

<?php
$todayProfitValue=!$metrics['today_profit_known']?currency_profit_html(0,false):($metrics['today_net_profit']>0?'+'.format_money($metrics['today_net_profit']):($metrics['today_net_profit']<0?'-'.format_money(abs($metrics['today_net_profit'])):format_money(0)));
$todayProfitDescription=!$metrics['today_profit_known']?__t('Mixed-currency profit is not calculated.','سود معاملات چندارزی محاسبه نشده است.','د څو اسعارو ګټه نه ده حساب شوې.'):sprintf(__('card_breakdown'),format_money($metrics['today_sales_profit'],false),format_money($metrics['today_expenses'],false),format_money($metrics['today_losses'],false));
$overallProfitValue=!$metrics['profit_known']?currency_profit_html(0,false):($metrics['overall_net_profit']>=0?format_money($metrics['overall_net_profit']):'-'.format_money(abs($metrics['overall_net_profit'])));
$dashboardCards=[
 ['dashboard-stock-value','dashboard.stock_value',__('card_stock_sale_value'),format_money($metrics['stock_sale_value']),sprintf(__('card_stock_sale_value_desc'),format_money($metrics['stock_value'],false)).'<br><i class="fa-solid fa-layer-group"></i> '.sprintf(__t('%s items in shop','%s مقدار موجودی در دکان','په هټۍ کې %s موجودي'),to_persian_num($metrics['total_items_count'])),'fa-boxes-stacked','primary'],
 ['dashboard-mudaraba','dashboard.mudaraba',__('nav_mudaraba'),format_money($totalMudarabaActiveCap),'<i class="fa-solid fa-globe"></i> '.to_persian_num($activeMudarabaCount).' '.__('card_total_items_desc'),'fa-handshake-angle','success'],
 ['dashboard-capital','dashboard.capital',__('card_shop_capital'),currency_profit_html($metrics['total_capital'],$metrics['profit_known']),'<i class="fa-solid fa-building-columns"></i> '.__('card_shop_capital_desc'),'fa-vault','primary'],
 ['dashboard-today-sales','dashboard.today_sales',__('card_today_sales'),format_money($metrics['today_sales']),$metrics['today_sales']>0?__('card_today_sales_profit').' <strong>'.currency_profit_html($metrics['today_sales_profit'],$metrics['today_profit_known']).'</strong>':'<i class="fa-solid fa-receipt"></i> '.__('card_no_sales_today'),'fa-cart-shopping','info'],
 ['dashboard-today-profit','dashboard.today_profit',$metrics['today_net_profit']>=0?__('card_today_net_profit'):__('card_today_net_loss'),$todayProfitValue,$todayProfitDescription,$metrics['today_net_profit']>=0?'fa-arrow-trend-up':'fa-arrow-trend-down',$metrics['today_net_profit']>=0?'success':'danger'],
 ['dashboard-expenses','dashboard.expenses',__('card_expenses'),format_money($metrics['today_expenses']),sprintf(__('card_expenses_desc'),format_money($metrics['total_expenses'],false)),'fa-receipt','warning'],
 ['dashboard-overall-profit','dashboard.overall_profit',$metrics['overall_net_profit']>=0?__('card_overall_profit'):__('card_overall_loss'),$overallProfitValue,__('card_today_sales_profit').' '.currency_profit_html($metrics['total_sales_profit'],$metrics['profit_known']).'<br>'.sprintf(__('card_total_losses'),format_money($metrics['total_losses'],false)),$metrics['overall_net_profit']>=0?'fa-chart-line':'fa-arrow-trend-down',$metrics['overall_net_profit']>=0?'success':'danger'],
 ['dashboard-debts','dashboard.debts',__('card_debts'),format_money($metrics['total_outstanding_debts']),__('card_debts_desc'),'fa-hand-holding-dollar','danger'],
];
?>
<section class="dashboard-hero">
  <div class="dashboard-hero-copy"><div class="eyebrow">NIK SMART COUNT · YOUR WORKSPACE</div><h1><?= __t('Welcome back, ','خوش برگشتی، ','ښه راغلې، ') ?><?= sanitize($currentUser['fullname']) ?> <span aria-hidden="true">👋</span></h1><p><?= __t('Your business at a glance — clear, calm and ready for today’s work.','تجارت شما در یک نگاه — روشن، منظم و آمادهٔ کار امروز.','ستاسو سوداګري په یوه کتنه کې — روښانه، منظمه او د ننني کار لپاره چمتو.') ?></p><span class="dashboard-hero-date"><i class="fa-regular fa-calendar"></i> <?= ui_date(date('Y-m-d')) ?></span></div>
  <div class="dashboard-hero-art" aria-hidden="true"><span><i class="fa-solid fa-chart-line"></i></span><span><i class="fa-solid fa-boxes-stacked"></i></span><span><i class="fa-solid fa-receipt"></i></span><span><i class="fa-solid fa-handshake"></i></span></div>
</section>
<div class="stats-grid dashboard-stats">
<?php foreach($dashboardCards as $card)ui_render_dashboard_card(...$card); ?>
</div>

<?php $quickMode=domain_visibility('dashboard.quick_actions');if($quickMode!=='hidden'): ?>
<section class="card dashboard-quick-card" id="quick-actions">
  <div class="card-header"><div><div class="eyebrow">YOUR DAILY WORKSPACE</div><h2 class="card-title"><i class="fa-solid fa-bolt"></i> <?= __('quick_actions_title') ?></h2><p class="dashboard-section-subtitle"><?= __t('Everything you need, one clear tap away.','هر چیزی که نیاز دارید، با یک کلیک روشن در دسترس است.','هر هغه څه چې اړتیا لرئ، په یوه روښانه کلیک کې.') ?></p></div></div>
  <?php if($quickMode==='locked'): ?><a class="dashboard-panel-locked" href="<?= sanitize(ui_service_url('dashboard.quick_actions')) ?>"><i class="fa-solid fa-lock"></i><span><strong><?= __t('Quick operations are locked','عملیات مشترک قفل است','چټک عملیات بند دي') ?></strong><small><?= __t('Open service activation and contact options','فعال‌سازی و گزینه‌های تماس را باز کنید','د فعالولو او اړیکې اختیارونه پرانیزئ') ?></small></span><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
  <?php else: ?><div class="dashboard-action-grid">
    <?php
      ui_render_dashboard_action('sales','sales.php',__('btn_new_sale'),'fa-cash-register');
      ui_render_dashboard_action('sales.invoices','invoices.php',__('btn_invoices_archive'),'fa-receipt');
      ui_render_dashboard_action('mudaraba','mudaraba.php',__('btn_mudaraba'),'fa-handshake-angle');
      ui_render_dashboard_action('purchases','stock_add.php',__('btn_add_stock'),'fa-circle-plus');
      ui_render_dashboard_action('inventory','stock.php',__('btn_stock_list'),'fa-boxes-stacked');
      ui_render_dashboard_action('debts','debts.php',__('btn_debts'),'fa-file-invoice');
      ui_render_dashboard_action('reports','reports.php',__('btn_financial_reports'),'fa-chart-pie');
    ?>
  </div><?php endif ?>
</section>
<?php endif ?>

<!-- MUDARABA PARTNERS LIVE PANEL IN DASHBOARD -->
<?php if($mudarabaPanelMode!=='hidden'): ?>
<div class="card" id="mudaraba-panel" style="margin-bottom: 24px; border-top: 3px solid var(--success);">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title" style="color: var(--success);">
            <i class="fa-solid fa-handshake-angle ml-1"></i> <?= __('mudaraba_table_title') ?>
        </h3>
        <?php if($mudarabaMainMode!=='hidden'): ?><a href="<?= sanitize($mudarabaMainMode==='locked'?ui_service_url('mudaraba'):BASE_URL.'modules/mudaraba.php') ?>" class="btn btn-sm btn-secondary">
            <i class="fa-solid <?= $mudarabaMainMode==='locked'?'fa-lock':($currentDir === 'rtl' ? 'fa-arrow-left' : 'fa-arrow-right') ?> ml-1"></i> <?= __('mudaraba_manage_link') ?>
        </a><?php endif ?>
    </div>
    <?php if($mudarabaPanelMode==='locked'): ?><a class="dashboard-panel-locked" href="<?= sanitize(ui_service_url('dashboard.mudaraba_panel')) ?>"><i class="fa-solid fa-lock"></i><span><strong><?= __t('Mudaraba panel is locked','پنل مضاربت قفل است','د مضاربت پنل بند دی') ?></strong><small><?= __t('Open service activation and contact options','فعال‌سازی و گزینه‌های تماس را باز کنید','د فعالولو او اړیکې اختیارونه پرانیزئ') ?></small></span><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php else: ?>
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
                            <?php if($mudarabaMainMode==='enabled'): ?><a href="<?= BASE_URL ?>modules/mudaraba.php" style="color: var(--info); font-weight: 700;"><?= __('add_first_mudaraba') ?></a><?php endif ?>
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
                                <?php if($mudarabaMainMode!=='hidden'): ?><a href="<?= sanitize($mudarabaMainMode==='locked'?ui_service_url('mudaraba'):BASE_URL.'modules/mudaraba.php') ?>" class="btn btn-sm btn-primary" title="<?= __('manage') ?>">
                                    <i class="fa-solid <?= $mudarabaMainMode==='locked'?'fa-lock':'fa-pen-to-square' ?>"></i>
                                </a><?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>
</div>
<?php endif ?>

<div class="dashboard-lower-grid" id="cash-flow">
    <!-- Shop Status & Financial Summary -->
    <?php $cashFlowMode=domain_visibility('dashboard.cash_flow');if($cashFlowMode!=='hidden'): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-chart-simple ml-1"></i> <?= __('cash_flow_status') ?>
            </h3>
            <?php if($reportsMode!=='hidden'): ?><a href="<?= sanitize($reportsMode==='locked'?ui_service_url('reports'):BASE_URL.'modules/reports.php') ?>" class="btn btn-sm btn-secondary">
                <i class="fa-solid <?= $reportsMode==='locked'?'fa-lock':'fa-file-invoice' ?>"></i> <?= __('view_invoices_in_reports') ?>
            </a><?php endif ?>
        </div>
        <?php if($cashFlowMode==='locked'): ?><a class="dashboard-panel-locked" href="<?= sanitize(ui_service_url('dashboard.cash_flow')) ?>"><i class="fa-solid fa-lock"></i><span><strong><?= __t('Financial summary is locked','خلاصهٔ مالی قفل است','مالي لنډیز بند دی') ?></strong><small><?= __t('Open service activation and contact options','فعال‌سازی و گزینه‌های تماس را باز کنید','د فعالولو او اړیکې اختیارونه پرانیزئ') ?></small></span><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php else: ?>
        <div style="padding: 10px 0;">
            <div class="dashboard-summary-grid">
                <?php if(domain_can('dashboard.today_sales')): ?><div style="background: var(--surface-muted); padding: 12px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 4px;"><?= __('today_invoice_sales') ?></div>
                    <div style="font-size: 17px; font-weight: 800; color: var(--info);"><?= format_money($metrics['today_sales']) ?></div>
                    <div style="font-size: 11px; color: var(--success); margin-top: 4px;"><i class="fa-solid fa-check-circle ml-1"></i> <?= __('transactions_recorded') ?></div>
                </div><?php endif ?>
                <div style="background: var(--danger-soft); padding: 12px; border-radius: 8px; border: 1px solid #fecaca;">
                    <div style="font-size: 11.5px; color: var(--danger); margin-bottom: 4px;"><?= __('today_damaged_goods') ?></div>
                    <div style="font-size: 17px; font-weight: 800; color: var(--danger);"><?= format_money($metrics['today_losses']) ?></div>
                    <div style="font-size: 11px; color: var(--danger); margin-top: 4px;"><i class="fa-solid fa-triangle-exclamation ml-1"></i> <?= __('deduction_from_stock_capital') ?></div>
                </div>
                <?php if(domain_can('dashboard.debts')): ?><div style="background: var(--surface-muted); padding: 12px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 4px;"><?= __('market_receivables') ?></div>
                    <div style="font-size: 17px; font-weight: 800; color: var(--danger);"><?= format_money($metrics['total_outstanding_debts']) ?></div>
                    <div style="font-size: 11px; color: #eab308; margin-top: 4px;"><i class="fa-solid fa-clock ml-1"></i> <?= __('collectible_in_market') ?></div>
                </div><?php endif ?>
            </div>

            <?php if($reportsMode!=='hidden'): ?><div class="dashboard-report-banner">
                <div>
                    <h4 style="font-size: 14px; font-weight: 700; color: var(--info); margin-bottom: 4px;">
                        <i class="fa-solid fa-receipt ml-1"></i> <?= __('full_reports_banner_title') ?>
                    </h4>
                    <p style="font-size: 12.5px; color: var(--info); margin: 0;">
                        <?= __('full_reports_banner_desc') ?>
                    </p>
                </div>
                <a href="<?= sanitize($reportsMode==='locked'?ui_service_url('reports'):BASE_URL.'modules/reports.php') ?>" class="btn btn-primary btn-sm" style="white-space: nowrap;">
                    <?= __('btn_reports_and_invoices') ?> <i class="fa-solid <?= $reportsMode==='locked'?'fa-lock':($currentDir === 'rtl' ? 'fa-arrow-left' : 'fa-arrow-right') ?> mr-1"></i>
                </a>
            </div><?php endif ?>
        </div>
        <?php endif ?>
    </div>
    <?php endif ?>

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
