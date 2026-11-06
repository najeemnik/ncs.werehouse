<?php
/**
 * Nik Smart Count - Warehousing - Business & Inventory Management System
 * Standard Header Layout
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Enforce login for all normal application pages
auth_require();

$currentUser = auth_user();
$currentLang = get_current_lang();
$currentDir = get_current_dir();
$supportedLangs = get_supported_languages();
$currentLangInfo = $supportedLangs[$currentLang] ?? $supportedLangs['en'];

$shopName = function_exists('get_shop_title') ? get_shop_title() : get_setting('shop_name', DEFAULT_SHOP_NAME);
$shopLogo = get_setting('shop_logo', '');
$uiPrefs=ui_preferences();
$shopLogoUrl=ui_logo_url();
$brandLogoUrl=brand_mark_url();
$brandFullLogoUrl=brand_full_logo_url();
$showWelcome=!empty($_SESSION['nik_welcome']);unset($_SESSION['nik_welcome']);

$pageTitleKeys = [
    'سیستم مدیریت دکان' => 'system_subname',
    'داشبورد عمومی دکان' => 'nav_dashboard',
    'فروختن جنس (بل جدید)' => 'nav_sales',
    'فروختن جنس (بل چند جنسی جدید)' => 'nav_sales',
    'آرشیف بل ها' => 'nav_invoices',
    'آرشیف بل‌ها' => 'nav_invoices',
    'مدیریت گدام و لست اجناس' => 'nav_stock',
    'ثبت جنس جدید در گدام' => 'btn_add_stock',
    'ویرایش مشخصات جنس در گدام' => 'edit_stock_title',
    'مدیریت قرضه ها و حسابات' => 'nav_debts',
    'مدیریت قرضه ها، طلبات و تفکیک سرمایه و مفاد' => 'nav_debts',
    'مشتریان و حسابات' => 'nav_customers',
    'مدیریت مشتریان و حسابات VIP' => 'nav_customers',
    'مصارف دکان' => 'nav_expenses',
    'مدیریت مصارف دکان' => 'nav_expenses',
    'ضرر و خسارات اجناس' => 'nav_losses',
    'مدیریت ضرر و خسارات اجناس' => 'nav_losses',
    'مدیریت ضرر و خسارات اجناس (تفکیک شده از مصارف)' => 'nav_losses',
    'شرکاء و سرمایه دکان' => 'nav_partners',
    'مدیریت شریک ها و سهم سرمایه' => 'nav_partners',
    'سرمایه و مضاربت (خارجی)' => 'nav_mudaraba',
    'حساب اختصاصی مضاربت و سرمایه‌گذاری خارجی' => 'nav_mudaraba',
    'گزارشات مالی و حسابداری دکان' => 'nav_reports',
    'هوش مصنوعی دکان' => 'nav_ai_advisor',
    'هوش مصنوعی حسابدار و مشاور مالی دکان' => 'nav_ai_advisor',
    'پشتیبان‌گیری (بک‌آپ)' => 'nav_backup',
    'پشتیبان‌گیری دیتابیس و امنیت اطلاعات' => 'nav_backup',
    'درباره برنامه و سازنده' => 'nav_about',
    'درباره سامانه حسابی Nik Smart Count - Warehousing و سازنده' => 'nav_about',
    'کنترل پنل سوپر ادمین' => 'nav_super_admin',
    'مدیریت دسته بندی های دکان' => 'nav_categories',
    'نمای گدام و موقعیت اجناس' => 'nav_warehouse',
    'گردش و حساب سرمایه دکان' => 'nav_capital'
];
$displayTitle = isset($pageTitle) && isset($pageTitleKeys[$pageTitle]) ? __($pageTitleKeys[$pageTitle]) : (isset($pageTitle) ? __($pageTitle, $pageTitle) : __('system_subname'));
$currentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="<?= sanitize($currentLang) ?>" dir="<?= sanitize($currentDir) ?>"<?= ui_root_attributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($displayTitle) ?> — <?= sanitize($shopName) ?></title>
    <!-- Nik Smart Count branding: logo-only tab icon, installable app metadata -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>uploads/branding/favicon-32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="<?= BASE_URL ?>uploads/branding/favicon-48.png">
    <link rel="icon" href="<?= BASE_URL ?>uploads/branding/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>uploads/branding/apple-touch-icon.png">
    <link rel="manifest" href="<?= BASE_URL ?>manifest.webmanifest">
    <meta name="theme-color" content="#0b2545">
    <meta name="application-name" content="<?= sanitize(brand_name()) ?>">
    <meta name="apple-mobile-web-app-title" content="Nik Smart Count">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css">
    <!-- Vazirmatn Font -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/vazirmatn/font.css">
    <!-- Core Application CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
    <meta name="csrf-token" content="<?= sanitize(csrf_token()) ?>">
    <script>document.documentElement.dataset.systemDark=String(matchMedia('(prefers-color-scheme: dark)').matches);</script>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/themes.css?v=2.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/branding.css?v=<?= APP_VERSION ?>">
    <script type="application/json" id="nikxor-ui-config"><?= json_for_html($uiPrefs+['theme_url'=>BASE_URL.'includes/change_theme.php','save_error'=>__t('Settings were not saved. Please refresh.','تنظیمات ذخیره نشد؛ صفحه را تازه کنید.','تنظیمات خوندي نه شول؛ پاڼه تازه کړئ.')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?></script>
    <script src="<?= BASE_URL ?>assets/js/preferences.js?v=<?= APP_VERSION ?>" defer></script>
    <?php $accessRoute=domain_route();$accessModes=[];foreach(domain_catalog() as $feature=>$unused)$accessModes[$feature]=domain_visibility($feature); ?>
    <script type="application/json" id="nsc-access-config"><?= json_for_html(['module'=>$accessRoute[0]??null,'defaultAction'=>basename($_SERVER['SCRIPT_NAME'])==='stock_edit.php'?'edit':'create','services'=>$accessModes,'locked'=>__t('Locked by permissions','این کار اجازه ندارد','دا کار اجازه نه لري'),'partnershipPending'=>__t('Use the reviewed settlement accounts for partner payments.','برای پرداخت شریک از حساب تسویهٔ بررسی‌شده استفاده کنید.','د شریک تادیه د تایید شوې تصفیې له حسابه وکړئ.'),'partnershipSettlement'=>__t('Open settlement accounts','بازکردن حساب تسویه','د تصفیې حساب پرانیزئ'),'script'=>basename($_SERVER['SCRIPT_NAME'])]) ?></script>
    <script src="<?= BASE_URL ?>assets/js/access-ui.js?v=2" defer></script>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/workflow.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/header.css?v=<?= APP_VERSION ?>">
    <script src="<?= BASE_URL ?>assets/js/header.js?v=<?= APP_VERSION ?>" defer></script>
</head>
<body class="modern-app dir-<?= sanitize($currentDir) ?>">
<a class="skip-link" href="#mainContent"><?= __t('Skip to content','رفتن به محتوا','منځپانګې ته لاړ شئ') ?></a>
<?php if ($showWelcome): ?>
<!-- One-time 3D welcome entrance after a successful sign-in (logo only). -->
<div id="nikWelcome" class="nik-welcome" hidden role="status" aria-live="polite">
    <div class="nik-welcome-stage">
        <img class="nik-welcome-logo" src="<?= sanitize($brandLogoUrl) ?>" alt="<?= sanitize(brand_name()) ?>">
        <div class="nik-welcome-word"><?= __t('Welcome to Nik Smart Count','خوش آمدید به نیک اسمارت کاونټ','نیک سمارټ کاونټ ته ښه راغلاست') ?><span><?= __t('Warehousing & smart accounting','گدام داری و حسابداری هوشمند','ګودام داری او هوښیار حساب') ?></span></div>
        <div class="nik-welcome-sub"><?= sanitize($shopName) ?></div>
        <div class="nik-welcome-bar"><i></i></div>
    </div>
</div>
<?php endif; ?>


<div class="app-wrapper">
    <!-- Sidebar Navigation -->
    <aside class="app-sidebar" id="appSidebar">
        <div class="sidebar-brand">
            <div class="brand-icon golden-aura-glow">
                <?php if ($brandLogoUrl): ?>
                    <img src="<?= sanitize($brandLogoUrl) ?>" alt="<?= sanitize(brand_name()) ?>" style="width: 38px; height: 38px; object-fit: contain; border-radius: 6px; background: #fff; padding: 2px;">
                <?php else: ?>
                    <i class="fa-solid fa-store" style="color: #fbbf24;"></i>
                <?php endif; ?>
            </div>
            <div class="brand-info">
                <h1><?= sanitize($shopName) ?></h1>
                <span><?= sanitize(__('system_subname')) ?></span>
            </div>
            <button type="button" id="sidebarCloseBtn" class="sidebar-close-btn" aria-label="<?= __('close_menu') ?>" title="<?= __('close_menu') ?>">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <nav class="sidebar-nav" aria-label="<?= __t('Main navigation','بخش‌های اصلی','اصلي برخې') ?>">
        <?php
        $nav=[
          ['Overview','نمای عمومی','branches','portfolio.php'],['Dashboard','داشبورد','dashboard','../index.php'],
          ['Sales','فروش','sales','sales.php'],['Sales invoices','بل‌های فروش','sales.invoices','invoices.php'],['Purchases','خرید','purchases','purchases.php'],['Purchase returns','برگشت خرید','purchases','purchase_returns.php'],
          ['Inventory','گدام و موجودی','inventory','stock.php'],['Locations','موقعیت اجناس','inventory.locations','warehouse.php'],['Barcode labels','لیبل بارکُد','inventory.labels','barcode_guide.php'],
          ['Stock movements & transfers','گردش و انتقال جنس','inventory','inventory_documents.php'],['Sales returns','برگشت فروش','sales','sales_returns.php'],['Customers','مشتریان','customers','customers.php'],['Receivables & payables','طلب‌ها و بدهی‌ها','debts','debts.php'],['Expenses','مصارف عملیاتی','expenses','expenses.php'],['Damage & losses','ضایعات اجناس','losses','losses.php'],
          ['Cash & bank','صندوق و بانک','treasury','treasury.php'],['Currency exchange','تبدیل ارز','treasury.exchange','fx.php'],['Accounting books','دفاتر حسابداری','accounting','accounting.php'],['Corrections','اسناد اصلاحی','accounting.correct','corrections.php'],['Suppliers','عرضه‌کنندگان','suppliers','suppliers.php'],['Capital','سرمایه','capital','capital.php'],['Partners','شرکا','partners','partners.php'],['Mudaraba','مضاربت','mudaraba','mudaraba.php'],['Partner settlement','تسویهٔ شریک','mudaraba.settle','partnership_settlement.php'],['Reports','راپورها','reports','reports.php'],
          ['Daily closing','بستن حساب روز','closings','closing.php'],['Business advisor','مشاور تجارت','ai','ai_advisor.php'],['Activity history','تاریخچهٔ فعالیت','audit','activity.php'],['Staff & permissions','کارمندان و صلاحیت‌ها','staff','management.php'],
          ['Settings','تنظیمات','settings','settings.php'],['About','دربارهٔ نرم‌افزار','about','about.php']];
        foreach($nav as [$en,$da,$feature,$file]){
          $mode=domain_visibility($feature);if($mode==='hidden')continue;
          $label=__t($en,$da,$en);
          if($mode==='locked'){echo '<span class="nav-link service-locked" aria-disabled="true"><i class="fa-solid fa-lock" aria-hidden="true"></i><span>'.sanitize($label).' · '.__t('Locked','قفل','قفل').'</span></span>';continue;}
          $url=$file==='../index.php'?BASE_URL.'index.php':BASE_URL.'modules/'.$file;
          echo '<a class="nav-link '.($currentScript===basename($file)?'active':'').'" href="'.sanitize($url).'"><span>'.sanitize($label).'</span></a>';
        }
        ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-snippet">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fa-solid fa-store"></i>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?= sanitize(function_exists('display_user_fullname') ? display_user_fullname($currentUser['fullname']) : $currentUser['fullname']) ?></div>
                        <div class="user-role">
                            <?= __('shop_environment') ?>
                        </div>
                    </div>
                </div>
                <?php if(!preview_active()): ?><button type="button" onclick="lockShopScreen();" class="logout-btn" style="background: var(--warning-soft); color: var(--warning); border: 1px solid #fde68a;" title="<?= __('lock_shop_screen') ?>">
                    <i class="fa-solid fa-lock"></i>
                </button><?php endif; ?>
            </div>
            <?php if(!preview_active()): ?><button type="submit" form="accountLogoutForm" class="btn btn-secondary btn-sm" style="width:100%;margin-top:12px"><i class="fa-solid fa-arrow-right-from-bracket"></i> <?= __t('Sign out','خروج از حساب','له حسابه وتل') ?></button><?php endif; ?>
        </div>
    </aside>

    <!-- Mobile Drawer Overlay -->
    <div id="mobileNavOverlay" class="mobile-overlay"></div>

    <!-- Main Content Area -->
    <main class="app-main" id="mainContent" tabindex="-1">
        <?php if($preview=preview_state()): $previewBranch=shop_find((int)$preview['branch_id']); ?>
        <div class="alert alert-warning" role="status" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;border:2px solid #f59e0b">
            <strong><i class="fa-solid fa-eye"></i> <?= __t('READ-ONLY USER PREVIEW','پیش‌نمایش فقط‌خواندنی یوزر','د کارن یوازې لوستنۍ کتنه') ?></strong>
            <span><?= sanitize($preview['user']['fullname'].' · '.$preview['user']['username'].' · '.($previewBranch['name']??'')) ?></span>
            <span><?= __t('No sale, payment, setting or other POST change can be saved.','هیچ فروش، پرداخت، تنظیم یا تغییر POST ذخیره نمی‌شود.','هېڅ پلور، تادیه یا بدلون خوندي نه کېږي.') ?></span>
            <form method="post" action="<?= BASE_URL ?>admin/user_preview.php" style="margin-inline-start:auto"><?= csrf_field() ?><input type="hidden" name="action" value="stop"><button class="btn btn-primary" type="submit"><?= __t('Exit preview','خروج از پیش‌نمایش','له کتنې وتل') ?></button></form>
        </div>
        <?php endif; ?>
        <?php
        // A central Office 2 administrator may work inside another shop; make that explicit.
        $contextShopId = (int)($_SESSION['shop_context_id'] ?? 0);
        if ($contextShopId > 0 && function_exists('shop_find')) {
            $contextShopRow = shop_find($contextShopId);
            if ($contextShopRow) { ?>
        <div class="shop-context-bar" role="status" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 12px;padding:9px 14px;border-radius:10px;border:1px solid #f59e0b;background:#fffbeb;color:#78350f;font-size:12.5px;font-weight:600;">
            <i class="fa-solid fa-store" aria-hidden="true"></i>
            <span><?= __t('Working inside shop','در حال کار در فروشگاه','په دوکان کې کار') ?> <strong><?= sanitize($contextShopRow['name']) ?></strong> (#<?= (int)$contextShopRow['id'] ?>)</span>
            <a href="<?= sanitize(BASE_URL) ?>admin/office_2.php?tab=shops" style="margin-inline-start:auto;color:#92400e;text-decoration:underline;"><?= __t('Back to my own shop','بازگشت به فروشگاه خودم','زما دوکان ته ستنیدل') ?></a>
        </div>
        <?php }
        }
        ?>
        <header class="app-topbar compact-topbar">
            <div class="page-title">
                <button type="button" id="mobileMenuToggle" class="mobile-menu-toggle header-icon" aria-controls="appSidebar" aria-expanded="false" aria-label="<?= __t('Open navigation','بازکردن بخش‌ها','برخې پرانیزئ') ?>" title="<?= __t('Open navigation','بازکردن بخش‌ها','برخې پرانیزئ') ?>">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>
                <?php if ($shopLogoUrl): ?>
                    <img class="header-shop-logo" src="<?= sanitize($shopLogoUrl) ?>" alt="">
                <?php endif; ?>
                <h2 title="<?= sanitize($displayTitle) ?>"><?= sanitize($displayTitle) ?></h2>
            </div>
            <div class="topbar-actions" role="group" aria-label="<?= __t('Page tools','گزینه‌های صفحه','د پاڼې وسیلې') ?>">
                <?php $quickSaleMode=domain_visibility('sales'); if($quickSaleMode!=='hidden'): ?>
                    <?php if($quickSaleMode==='locked'): ?>
                        <span class="header-control header-sale service-locked" aria-disabled="true" aria-label="<?= sanitize(__('quick_sell').' · '.__t('Locked','قفل','قفل')) ?>" title="<?= sanitize(__('quick_sell').' · '.__t('Locked','قفل','قفل')) ?>"><i class="fa-solid fa-lock" aria-hidden="true"></i><span class="header-action-text"><?= __('quick_sell') ?></span></span>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>modules/sales.php" class="header-control header-sale" aria-label="<?= __('quick_sell') ?>" title="<?= __('quick_sell') ?>"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i><span class="header-action-text"><?= __('quick_sell') ?></span></a>
                    <?php endif; ?>
                <?php endif; ?>
                <button type="button" id="headerToolsToggle" class="header-control header-icon" aria-controls="headerToolsPanel" aria-expanded="false" aria-label="<?= __t('More tools','گزینه‌های بیشتر','نورې وسیلې') ?>" title="<?= __t('More tools','گزینه‌های بیشتر','نورې وسیلې') ?>"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
                <div id="headerToolsPanel" class="header-tools-panel" role="group" aria-label="<?= __t('Display and security','نمایش و امنیت','بڼه او امنیت') ?>">
                    <?= currency_account_filter() ?>
                    <div class="lang-selector-wrapper">
                        <button type="button" id="langDropdownBtn" class="header-control header-language" aria-expanded="false" aria-controls="langDropdownMenu" title="<?= __('select_language') ?>">
                            <i class="fa-solid fa-globe" aria-hidden="true"></i><span class="lang-native"><?= sanitize($currentLang==='en'?$currentLangInfo['name']:$currentLangInfo['native']) ?></span><i class="fa-solid fa-chevron-down header-chevron" aria-hidden="true"></i>
                        </button>
                        <div id="langDropdownMenu" class="lang-dropdown-menu" hidden>
                            <div class="header-menu-heading"><?= __('select_language') ?></div>
                            <?php foreach ($supportedLangs as $lCode => $lInfo): ?>
                                <a href="?<?= sanitize(http_build_query(array_merge($_GET,['lang'=>$lCode]))) ?>" class="lang-dropdown-item" <?= $lCode === $currentLang ? 'aria-current="true"' : '' ?>><span><?= sanitize($currentLang==='en'?$lInfo['name']:$lInfo['native']) ?></span><?php if($lCode===$currentLang): ?><i class="fa-solid fa-check" aria-hidden="true"></i><?php endif; ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="button" data-toggle-mode class="header-control header-appearance" aria-label="<?= __t('Toggle light/dark mode','تغییر حالت روشن و تاریک','رڼا او تیاره بڼه بدلول') ?>" title="<?= __t('Light / dark','روشن / تاریک','رڼا / تیاره') ?>"><i class="fa-regular fa-moon" aria-hidden="true"></i><span class="header-panel-label"><?= __t('Light / dark','روشن / تاریک','رڼا / تیاره') ?></span></button>
                    <?php if(!preview_active()): ?><button type="button" data-header-lock class="header-control header-lock" aria-label="<?= __('lock_shop_screen') ?>" title="<?= __('lock_shop_screen') ?>"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span class="header-panel-label"><?= __('lock_shop_screen') ?></span></button><?php endif; ?>
                </div>
            </div>
        </header>

        <div class="app-content">

            <!-- Installable app hint: shown only when the browser offers installation. -->
            <div class="nik-install-banner" data-nik-install-banner role="region" aria-label="<?= __t('Install application','نصب برنامه','اپلیکیشن نصب') ?>">
                <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                <span style="flex:1 1 200px;min-width:0;font-size:12px;"><?= __t('Install Nik Smart Count on this device for a full-screen, app-like experience.','نیک اسمارت کاونټ را روی این دستگاه نصب کنید تا تمام‌صفحه و مانند یک برنامه باز شود.','نیک سمارټ کاونټ په دې وسیله نصب کړئ چې بشپړ پرده او د اپلیکیشن په څېر پرانیستل شي.') ?></span>
                <button type="button" data-nik-install data-install-hint="<?= __t('Open the browser menu and choose “Install app / Add to Home screen”.','از منوی مرورگر «نصب برنامه / افزودن به صفحه اصلی» را انتخاب کنید.','د براوزر له مینو څخه «اپلیکیشن نصب / کور پاڼې ته زیاتول» وټاکئ.') ?>"><i class="fa-solid fa-download"></i> <?= __t('Install','نصب','نصب') ?></button>
                <button type="button" class="nik-banner-dismiss" data-nik-install-dismiss aria-label="<?= __t('Close','بستن','بندول') ?>"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <?php 
            $flash = get_flash();
            if ($flash): 
            ?>
                <div class="alert alert-<?= sanitize($flash['type']) ?>" data-flash role="status">
                    <i class="fa-solid fa-circle-info"></i>
                    <span><?= sanitize($flash['text']) ?></span>
                </div>
            <?php endif; ?>
