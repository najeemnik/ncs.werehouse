<?php
/**
 * Nik Smart Count - Warehousing - Multi-Language Localization System (i18n)
 * Supports: English (en - Default), Dari / Afghan Persian (da), Pashto (ps)
 */

if (!defined('APP_DEFAULT_LANG')) {
    define('APP_DEFAULT_LANG', 'en');
}

// Supported languages definition
function get_supported_languages() {
    return [
        'en' => [
            'code'      => 'en',
            'name'      => 'English',
            'native'    => 'English',
            'dir'       => 'ltr',
            'flag'      => '🇬🇧',
            'currency'  => 'AFN',
            'locale'    => 'en_US'
        ],
        'da' => [
            'code'      => 'da',
            'name'      => 'Dari (Afghanistan)',
            'native'    => 'دری (افغانستان)',
            'dir'       => 'rtl',
            'flag'      => '🇦🇫',
            'currency'  => 'افغانی',
            'locale'    => 'fa_AF'
        ],
        'ps' => [
            'code'      => 'ps',
            'name'      => 'Pashto',
            'native'    => 'پښتو',
            'dir'       => 'rtl',
            'flag'      => '🇦🇫',
            'currency'  => 'افغانۍ',
            'locale'    => 'ps_AF'
        ]
    ];
}

// Language resolver and switcher
function init_app_language() {
    $supported=get_supported_languages();
    $requested=$_GET['lang']??null;
    if(is_string($requested)){
        $requested=strtolower(trim($requested));
        if(in_array($requested,['fa','fa-af'],true))$requested='da';
        if(isset($supported[$requested])){
            $_SESSION['app_lang']=$requested;
            $_SESSION['app_lang_pending']=$requested;
            app_set_preference_cookie('app_lang',$requested,time()+86400*365);
            // Keep the explicit choice in the URL. Removing it with an immediate
            // redirect used to silently revert to English when iframe cookies were blocked.
            return $requested;
        }
    }
    $current=$_SESSION['app_lang']??null;
    if(!is_string($current)||!isset($supported[$current])){
        $cookie=$_COOKIE['app_lang']??null;
        $_SESSION['app_lang']=is_string($cookie)&&isset($supported[$cookie])?$cookie:APP_DEFAULT_LANG;
    }
    return $_SESSION['app_lang'];
}

function get_current_lang() {
    $lang=$_SESSION['app_lang']??null;
    return is_string($lang)&&isset(get_supported_languages()[$lang])?$lang:init_app_language();
}

function app_url_with_language($url,$lang) {
    $parts=parse_url($url);$query=[];
    if(isset($parts['query']))parse_str($parts['query'],$query);
    $query['lang']=isset(get_supported_languages()[$lang])?$lang:APP_DEFAULT_LANG;
    return ($parts['path']??BASE_URL.'index.php').'?'.http_build_query($query);
}

function get_current_dir() {
    $lang = get_current_lang();
    $supported = get_supported_languages();
    return $supported[$lang]['dir'] ?? 'ltr';
}

function get_currency_label() {
    if (function_exists('currency_base_code') && strpos($_SERVER['SCRIPT_NAME']??'', '/admin/') === false) return currency_base_code();
    $lang = get_current_lang();
    $supported = get_supported_languages();
    return $supported[$lang]['currency'] ?? 'AFN';
}

/**
 * Universal Translation Dictionary
 */
function get_translation_dictionary() {
    static $dict = null;
    if ($dict !== null) {
        return $dict;
    }

    $dict = [
        // Brand & System
        'app_name' => [
            'en' => 'Nik Smart Count - Warehousing',
            'da' => 'نیک اسمارت کاونټ — گدام داری',
            'ps' => 'نیک سمارټ کاونټ — ګودام داری'
        ],
        'brand_name' => [
            'en' => 'Nik Smart Count - Warehousing',
            'da' => 'نیک اسمارت کاونټ — گدام داری',
            'ps' => 'نیک سمارټ کاونټ — ګودام داری'
        ],
        'brand_name_short' => [
            'en' => 'Nik Smart Count',
            'da' => 'نیک اسمارت کاونټ',
            'ps' => 'نیک سمارټ کاونټ'
        ],
        'brand_company' => [
            'en' => 'Smart accounting & warehouse management',
            'da' => 'حسابداری هوشمند نیک - گدام داری',
            'ps' => 'د نیک هوښیار حساب - ګودام داری'
        ],
        'system_subname' => [
            'en' => 'Smart accounting & warehouse management',
            'da' => 'حسابداری هوشمند و مدیریت گدام',
            'ps' => 'هوښیار حساب او د ګودام مدیریت'
        ],
        'default_shop_name' => [
            'en' => 'Nik Smart Count - Warehousing',
            'da' => 'نیک اسمارت کاونټ — گدام داری',
            'ps' => 'نیک سمارټ کاونټ — ګودام داری'
        ],

        // Navigation
        'nav_dashboard' => [
            'en' => 'Dashboard',
            'da' => 'صفحه اصلی',
            'ps' => 'اصلي مخه'
        ],
        'nav_sales' => [
            'en' => 'Sell Product (New Invoice)',
            'da' => 'فروختن جنس (بل جدید)',
            'ps' => 'د جنس پلورل (نوی بل)'
        ],
        'nav_invoices' => [
            'en' => 'Invoices Archive',
            'da' => 'آرشیف بل‌ها',
            'ps' => 'د بلونو آرشیف'
        ],
        'nav_stock' => [
            'en' => 'Warehouse & Inventory',
            'da' => 'گدام و اجناس',
            'ps' => 'ګودام او اجناس'
        ],
        'nav_debts' => [
            'en' => 'Debts & Installments',
            'da' => 'قرضه‌ها و حسابات',
            'ps' => 'پورونه او حسابونه'
        ],
        'nav_customers' => [
            'en' => 'Customers & Accounts',
            'da' => 'مشتریان و حسابات',
            'ps' => 'پیرودونکي او حسابونه'
        ],
        'nav_expenses' => [
            'en' => 'Shop Expenses',
            'da' => 'مصارف دکان',
            'ps' => 'د هټۍ لګښتونه'
        ],
        'nav_losses' => [
            'en' => 'Damaged Goods & Losses',
            'da' => 'ضرر و خسارات اجناس',
            'ps' => 'د اجناسو زیان او تاوان'
        ],
        'nav_partners' => [
            'en' => 'Partners & Capital',
            'da' => 'شرکاء و سرمایه دکان',
            'ps' => 'شریکان او سرمایه'
        ],
        'nav_mudaraba' => [
            'en' => 'Mudaraba Account (Foreign)',
            'da' => 'سرمایه و مضاربت (خارجی)',
            'ps' => 'د مضاربت حساب (بهرني)'
        ],
        'nav_reports' => [
            'en' => 'Comprehensive Financial Reports',
            'da' => 'گزارشات جامع مالی',
            'ps' => 'جامع مالي راپورونه'
        ],
        'nav_ai_advisor' => [
            'en' => 'Shop AI Advisor',
            'da' => 'هوش مصنوعی دکان',
            'ps' => 'د هټۍ مصنوعي استخبارات'
        ],
        'nav_backup' => [
            'en' => 'Database Backup',
            'da' => 'پشتیبان‌گیری (بک‌آپ)',
            'ps' => 'د ډیټابیس شاتړ (بک اپ)'
        ],
        'nav_about' => [
            'en' => 'About System & Developer',
            'da' => 'درباره برنامه و سازنده',
            'ps' => 'د سیسټم او جوړونکي په اړه'
        ],
        'nav_super_admin' => [
            'en' => 'Super Admin Panel',
            'da' => 'کنترل پنل سوپر ادمین',
            'ps' => 'د سوپر اډمین کنټرول پینل'
        ],

        // Header & Actions
        'quick_sell' => [
            'en' => 'New Sale',
            'da' => 'فروش جدید',
            'ps' => 'نوی پلور'
        ],
        'quick_sell_long' => [
            'en' => 'Sell Product (New Invoice)',
            'da' => 'فروختن جنس (بل جدید)',
            'ps' => 'د جنس پلورل (نوی بل)'
        ],
        'lock_shop_screen' => [
            'en' => 'Instant Screen Lock',
            'da' => 'قفل فوری دکان',
            'ps' => 'د سکرین چټک قفل'
        ],
        'set_pin' => [
            'en' => 'Set Lock PIN',
            'da' => 'تنظیم پین‌کد قفل',
            'ps' => 'د قفل پین‌کوډ تنظیم'
        ],
        'shop_environment' => [
            'en' => 'General Shop Environment',
            'da' => 'محیط عمومی دکان',
            'ps' => 'د هټۍ عمومي چاپېریال'
        ],
        'close_menu' => [
            'en' => 'Close Menu',
            'da' => 'بستن منو',
            'ps' => 'د مینو تړل'
        ],
        'language' => [
            'en' => 'Language',
            'da' => 'زبان',
            'ps' => 'ژبه'
        ],
        'select_language' => [
            'en' => 'Select Language',
            'da' => 'انتخاب زبان',
            'ps' => 'ژبه غوره کړئ'
        ],

        // Dashboard Cards
        'card_stock_value' => [
            'en' => 'Total Warehouse Value',
            'da' => 'ارزش مجموعی گدام',
            'ps' => 'د ګودام ټول ارزښت'
        ],
        'card_stock_value_desc' => [
            'en' => 'Total value of healthy stock in warehouse',
            'da' => 'مجموع ارزش اجناس سالم در گدام',
            'ps' => 'په ګودام کې د روغو توکو ټول ارزښت'
        ],
        'card_total_items' => [
            'en' => 'Total Inventory Units',
            'da' => 'تعداد مجموعی اجناس',
            'ps' => 'د ټولو اجناسو شمېر'
        ],
        'card_total_items_desc' => [
            'en' => 'Active partners & projects',
            'da' => 'شریک و پروژه فعال',
            'ps' => 'فعال شریکان او پروژې'
        ],
        'card_shop_capital' => [
            'en' => 'Total Shop Capital',
            'da' => 'سرمایه عمومی دکان',
            'ps' => 'د هټۍ عمومي سرمایه'
        ],
        'card_shop_capital_desc' => [
            'en' => 'Capital + inventory purchase cost + sales profit - expenses',
            'da' => 'سرمایه + قیمت خرید موجودی + مفاد فروش − مصارف',
            'ps' => 'سرمایه + د موجودۍ پېرود بیه + د پلور ګټه − لګښتونه'
        ],
        'card_stock_sale_value' => [
            'en' => 'Inventory Value at Selling Price',
            'da' => 'ارزش موجودی به قیمت فروش',
            'ps' => 'د موجودۍ ارزښت د پلور بیې سره'
        ],
        'card_stock_sale_value_desc' => [
            'en' => 'Calculated from recorded sale prices · Purchase cost: %s',
            'da' => 'بر اساس قیمت‌های فروش ثبت‌شده · قیمت خرید: %s',
            'ps' => 'د ثبت شوو پلور بیو له مخې · د پېرود بیه: %s'
        ],
        'card_total_losses' => [
            'en' => 'Cumulative recorded losses: %s',
            'da' => 'مجموع ضررهای ثبت‌شده: %s',
            'ps' => 'ټول ثبت شوي تاوانونه: %s'
        ],
        'card_today_losses_line' => [
            'en' => "Today's recorded losses: %s",
            'da' => 'ضرر ثبت‌شدهٔ امروز: %s',
            'ps' => 'د نن ثبت شوی تاوان: %s'
        ],
        'card_today_sales' => [
            'en' => "Today's Sales (Invoices)",
            'da' => 'فروشات امروز (بل‌ها)',
            'ps' => 'د نن ورځې پلور (بلونه)'
        ],
        'card_today_sales_profit' => [
            'en' => 'Gross profit from sales:',
            'da' => 'مفاد ناخالص فروش:',
            'ps' => 'د پلور ناخالصه ګټه:'
        ],
        'card_no_sales_today' => [
            'en' => 'No invoices issued today',
            'da' => 'بدون صدور بل در امروز',
            'ps' => 'نن ورځ کوم بل نه دی صادر شوی'
        ],
        'card_today_net_profit' => [
            'en' => "Today's Net Profit",
            'da' => 'مفاد خالص امروز',
            'ps' => 'د نن ورځې خالص ګټه'
        ],
        'card_today_net_loss' => [
            'en' => "Today's Net Loss",
            'da' => 'ضرر خالص امروز',
            'ps' => 'د نن ورځې خالص زیان'
        ],
        'card_deficit_explanation' => [
            'en' => 'Deficit due to damaged goods or expenses',
            'da' => 'کسری بابت خسارات جنس یا مصارف',
            'ps' => 'د توکو د تاوان او لګښتونو کسر'
        ],
        'card_breakdown' => [
            'en' => 'Sales: %s | Expenses: %s | Losses: %s',
            'da' => 'فروش: %s | مصرف: %s | ضرر: %s',
            'ps' => 'پلور: %s | لګښت: %s | تاوان: %s'
        ],
        'card_damages' => [
            'en' => 'Damaged Goods & Losses',
            'da' => 'ضرر و خسارات اجناس',
            'ps' => 'د اجناسو زیان او تاوان'
        ],
        'card_damages_desc' => [
            'en' => "Today's loss (Total: %s)",
            'da' => 'خسارت امروز (کل: %s)',
            'ps' => 'د نن تاوان (ټول: %s)'
        ],
        'card_expenses' => [
            'en' => 'Operating Expenses',
            'da' => 'مصارف جاری دکان',
            'ps' => 'د هټۍ روان لګښتونه'
        ],
        'card_expenses_desc' => [
            'en' => "Today's expenses (Total: %s)",
            'da' => 'مصرف امروز (کل: %s)',
            'ps' => 'د نن لګښت (ټول: %s)'
        ],
        'card_overall_profit' => [
            'en' => 'Overall Shop Net Profit',
            'da' => 'مجموع مفاد عمومی دکان',
            'ps' => 'د هټۍ عمومي خالص ګټه'
        ],
        'card_overall_loss' => [
            'en' => 'Overall Shop Net Loss',
            'da' => 'مجموع ضرر عمومی دکان',
            'ps' => 'د هټۍ عمومي خالص تاوان'
        ],
        'card_debts' => [
            'en' => 'Outstanding Receivables',
            'da' => 'قرضه های باقی مانده',
            'ps' => 'پاتې پورونه او طلبونه'
        ],
        'card_debts_desc' => [
            'en' => 'Shop receivables from market & customers',
            'da' => 'طلب های دکان از مشتریان بازار',
            'ps' => 'له پیرودونکو څخه د هټۍ طلبونه'
        ],

        // Quick Shortcuts & Dashboard Sections
        'quick_actions_title' => [
            'en' => 'Quick Access to Common Operations',
            'da' => 'دسترسی سریع به عملیات های پرکاربرد',
            'ps' => 'ډېرو کارېدونکو عملیاتو ته چټک لاسرسی'
        ],
        'btn_new_sale' => [
            'en' => 'Sell New Product (New Invoice)',
            'da' => 'فروختن جنس جدید (صدور بل)',
            'ps' => 'د نوي توکي پلورل (بل صادرول)'
        ],
        'btn_invoices_archive' => [
            'en' => 'Invoices Archive',
            'da' => 'آرشیف بل ها',
            'ps' => 'د بلونو آرشیف'
        ],
        'btn_mudaraba' => [
            'en' => 'Mudaraba Account (Foreign Goods)',
            'da' => 'حساب مضاربت (کالای خارجی)',
            'ps' => 'د مضاربت حساب (بهرني توکي)'
        ],
        'btn_add_stock' => [
            'en' => 'New Purchase / Add Item',
            'da' => 'خرید جدید / افزودن جنس',
            'ps' => 'نوی پېرود / توکی زیاتول'
        ],
        'btn_stock_list' => [
            'en' => 'Inventory List & Management',
            'da' => 'لست و مدیریت اجناس',
            'ps' => 'د اجناسو لست او مدیریت'
        ],
        'btn_debts' => [
            'en' => 'Debts & Installments Management',
            'da' => 'مدیریت قرضه ها و اقساط',
            'ps' => 'د پورونو او قسطونو مدیریت'
        ],
        'btn_financial_reports' => [
            'en' => 'Financial Reports & Accounting',
            'da' => 'گزارشات مالی و حسابداری',
            'ps' => 'مالي او محاسباتي راپورونه'
        ],

        // Dashboard Live Status
        'cash_flow_status' => [
            'en' => 'Live Cash Flow & Daily Shop Status',
            'da' => 'وضعیت زنده نقدینگی و جریان روزانه دکان',
            'ps' => 'د نغدو پیسو او د هټۍ ورځني جریان حالت'
        ],
        'view_invoices_in_reports' => [
            'en' => 'View Invoices in Reports',
            'da' => 'مشاهده بل‌ها در گزارشات مالی',
            'ps' => 'په مالي راپورونو کې د بلونو لیدل'
        ],
        'today_invoice_sales' => [
            'en' => "Today's Invoice Sales",
            'da' => 'فروشات بل‌های امروز',
            'ps' => 'د نن ورځې د بلونو پلور'
        ],
        'transactions_recorded' => [
            'en' => 'Transactions Recorded',
            'da' => 'معاملات ثبت شده',
            'ps' => 'ثبت شوې معاملې'
        ],
        'today_damaged_goods' => [
            'en' => "Today's Damaged Goods",
            'da' => 'خسارات اجناس امروز',
            'ps' => 'د نن ورځې د توکو زیانونه'
        ],
        'deduction_from_stock_capital' => [
            'en' => 'Deducted from stock capital',
            'da' => 'کسر از سرمایه کالا',
            'ps' => 'د توکو له پانګې کسر شوی'
        ],
        'market_receivables' => [
            'en' => 'Market Receivables',
            'da' => 'مانده طلبات از مشتریان',
            'ps' => 'له پیرودونکو پاتې طلبونه'
        ],
        'collectible_in_market' => [
            'en' => 'Collectible from market',
            'da' => 'قابل وصول در بازار',
            'ps' => 'په بازار کې د ترلاسه کولو وړ'
        ],
        'full_reports_banner_title' => [
            'en' => 'Full Sales & Invoice History',
            'da' => 'گزارشات کامل فروشات و بل‌ها',
            'ps' => 'د پلور او بلونو بشپړ تاریخچه'
        ],
        'full_reports_banner_desc' => [
            'en' => 'History of all invoices, exact timestamps, reprint options, and item details are centered in Financial Reports.',
            'da' => 'تاریخچه تمام بل‌ها، ساعت دقیق صدور، چاپ مجدد و جزئیات کالاها در بخش گزارشات مالی متمرکز شده است.',
            'ps' => 'د ټولو بلونو تاریخ، د صدور دقیق وخت، بیا چاپ او د توکو تفصیلات په مالي راپورونو کې شتون لري.'
        ],
        'btn_reports_and_invoices' => [
            'en' => 'Reports & Invoices',
            'da' => 'گزارشات و بل‌ها',
            'ps' => 'راپورونه او بلونه'
        ],
        'low_stock_warning' => [
            'en' => 'Low Stock Warning',
            'da' => 'هشدار کمبود موجودی',
            'ps' => 'د موجودي د کمښت خبرداری'
        ],
        'available_qty' => [
            'en' => 'In stock: %s items',
            'da' => 'موجودی: %s عدد',
            'ps' => 'موجودي: %s دانې'
        ],
        'warehouse_loc' => [
            'en' => 'Location: %s',
            'da' => 'موقعیت: %s',
            'ps' => 'ځای: %s'
        ],
        'reorder_purchase' => [
            'en' => 'Buy / Reorder',
            'da' => 'خرید مجدد',
            'ps' => 'بیا پیرودل'
        ],
        'no_low_stock' => [
            'en' => 'All items in stock have sufficient inventory.',
            'da' => 'تمام اجناس گدام دارای موجودی کافی می‌باشند.',
            'ps' => 'د ګودام ټول توکي کافي موجودي لري.'
        ],

        // General Financial Terms
        'total' => [
            'en' => 'Total',
            'da' => 'مجموع',
            'ps' => 'ټول'
        ],
        'paid' => [
            'en' => 'Paid',
            'da' => 'پرداخت شده',
            'ps' => 'ورکړل شوی'
        ],
        'remaining' => [
            'en' => 'Remaining',
            'da' => 'باقی مانده',
            'ps' => 'پاتې'
        ],
        'profit' => [
            'en' => 'Profit',
            'da' => 'مفاد',
            'ps' => 'ګټه'
        ],
        'loss' => [
            'en' => 'Loss',
            'da' => 'ضرر',
            'ps' => 'زیان'
        ],
        'expenses' => [
            'en' => 'Expenses',
            'da' => 'مصارف',
            'ps' => 'لګښتونه'
        ],
        'quantity' => [
            'en' => 'Quantity',
            'da' => 'تعداد',
            'ps' => 'شمېر'
        ],
        'purchase_price' => [
            'en' => 'Purchase Price',
            'da' => 'نرخ خرید',
            'ps' => 'د پیرودلو نرخ'
        ],
        'sale_price' => [
            'en' => 'Sale Price',
            'da' => 'نرخ فروش',
            'ps' => 'د پلور نرخ'
        ],
        'unit_cost' => [
            'en' => 'Unit Cost (Total)',
            'da' => 'تمام شد فی واحد',
            'ps' => 'د هر یوه تمام شد'
        ],
        'cost_price' => [
            'en' => 'Cost Price',
            'da' => 'نرخ تمام شد',
            'ps' => 'د تمام شد بیه'
        ],
        'unit_profit' => [
            'en' => 'Unit Profit',
            'da' => 'فایده فی دانه',
            'ps' => 'د هر یوه ګټه'
        ],
        'actions' => [
            'en' => 'Actions',
            'da' => 'عملیات',
            'ps' => 'عملیات'
        ],
        'manage' => [
            'en' => 'Manage',
            'da' => 'مدیریت',
            'ps' => 'مدیریت'
        ],
        'items' => [
            'en' => 'items',
            'da' => 'قلم جنس',
            'ps' => 'قلمه جنس'
        ],
        'pieces' => [
            'en' => 'pcs',
            'da' => 'عدد',
            'ps' => 'دانې'
        ],

        // Common Buttons & Dialogs
        'save' => [
            'en' => 'Save',
            'da' => 'ذخیره',
            'ps' => 'خوندي کول'
        ],
        'cancel' => [
            'en' => 'Cancel',
            'da' => 'انصراف',
            'ps' => 'لغوه'
        ],
        'delete' => [
            'en' => 'Delete',
            'da' => 'حذف',
            'ps' => 'ړنګول'
        ],
        'edit' => [
            'en' => 'Edit',
            'da' => 'ویرایش',
            'ps' => 'سمول'
        ],
        'print' => [
            'en' => 'Print',
            'da' => 'چاپ',
            'ps' => 'چاپ'
        ],
        'search' => [
            'en' => 'Search',
            'da' => 'جستجو',
            'ps' => 'پلټنه'
        ],
        'filter' => [
            'en' => 'Filter',
            'da' => 'فیلتر',
            'ps' => 'فلټر'
        ],
        'confirm' => [
            'en' => 'Confirm',
            'da' => 'تایید',
            'ps' => 'تایید'
        ],
        'yes' => [
            'en' => 'Yes',
            'da' => 'بلی',
            'ps' => 'هو'
        ],
        'no' => [
            'en' => 'No',
            'da' => 'نخیر',
            'ps' => 'نه'
        ],
        'status' => [
            'en' => 'Status',
            'da' => 'وضعیت',
            'ps' => 'حالت'
        ],
        'date' => [
            'en' => 'Date',
            'da' => 'تاریخ',
            'ps' => 'نېټه'
        ],
        'date_time' => [
            'en' => 'Date & Time',
            'da' => 'تاریخ و ساعت',
            'ps' => 'نېټه او وخت'
        ],
        'description' => [
            'en' => 'Description',
            'da' => 'توضیحات',
            'ps' => 'تفصیل'
        ],
        'product_name' => [
            'en' => 'Product Name',
            'da' => 'اسم جنس',
            'ps' => 'د جنس نوم'
        ],
        'category' => [
            'en' => 'Category',
            'da' => 'دسته بندی',
            'ps' => 'ډلبندي'
        ],
        'specifications' => [
            'en' => 'Specifications',
            'da' => 'مشخصات',
            'ps' => 'ځانګړنې'
        ],
        'customer' => [
            'en' => 'Customer',
            'da' => 'مشتری',
            'ps' => 'پیرودونکی'
        ],
        'phone' => [
            'en' => 'Phone Number',
            'da' => 'شماره تماس',
            'ps' => 'د اړیکې شمېره'
        ],
        'barcode' => [
            'en' => 'Barcode / Serial',
            'da' => 'بارکد / سریال',
            'ps' => 'بارکوډ / سریال'
        ],
        'location' => [
            'en' => 'Warehouse Location',
            'da' => 'موقعیت در گدام',
            'ps' => 'په ګودام کې ځای'
        ],
        'locked' => [
            'en' => 'Locked',
            'da' => 'قفل',
            'ps' => 'قفل'
        ],
        'feature_locked_title' => [
            'en' => 'This Feature is Locked',
            'da' => 'این گزینه قفل می‌باشد',
            'ps' => 'دا برخه قلف ده'
        ],
        'feature_locked_desc' => [
            'en' => 'To request access and unlock this feature, please contact Ahmad Najeem Nik.',
            'da' => 'جهت درخواست این گزینه با احمد نجیم نیک تماس بگیرید',
            'ps' => 'د دې برخې د فعالولو لپاره له احمد نجیم نیک سره اړیکه ونیسئ.'
        ],
        'whatsapp_btn' => [
            'en' => 'WhatsApp Ahmad Najeem Nik',
            'da' => 'واتس‌اپ احمد نجیم نیک',
            'ps' => 'د احمد نجیم نیک واټساپ'
        ],
        'phone_call_btn' => [
            'en' => 'Call: +93783788278',
            'da' => 'تماس: ۰۷۸۳۷۸۸۲۷۸',
            'ps' => 'اړیکه: ۰۷۸۳۷۸۸۲۷۸'
        ],
        'super_admin_pin_modal_title' => [
            'en' => 'Super Admin Master Security Panel',
            'da' => 'ورود امنیتی به پنل سوپر ادمین',
            'ps' => 'د سوپر اډمین امنیتي پینل ته ننوتل'
        ],
        'enter_pin' => [
            'en' => 'Enter 7-Digit Master PIN Code:',
            'da' => 'پین‌کد ۷ رقمی اختصاصی را وارد نمایید:',
            'ps' => '۷ عددي ځانګړی پین کوډ دننه کړئ:'
        ],
        'unlock_and_enter' => [
            'en' => 'Verify & Unlock Panel',
            'da' => 'تایید و بازگشایی پنل',
            'ps' => 'تایید او پینل خلاصول'
        ],

        // Stock & Inventory Module
        'stock_inventory' => [
            'en' => 'Stock & Inventory',
            'da' => 'موجودی و گدام',
            'ps' => 'موجودي او ګودام'
        ],
        'stock_items_list' => [
            'en' => 'Stock Items & Warehouse Inventory',
            'da' => 'لست اجناس و موجودی گدام',
            'ps' => 'د توکو لست او د ګودام موجودي'
        ],
        'stock_total_summary' => [
            'en' => 'Total: %s items | In-Stock: %s pcs | Total Warehouse Value: %s',
            'da' => 'مجموع: %s قلم جنس | موجودی سالم: %s عدد | مجموع ارزش گدام: %s',
            'ps' => 'ټول: %s قلمه توکي | روغه موجودي: %s دانې | د ګودام ټول ارزښت: %s'
        ],
        'damaged_deduction_stat' => [
            'en' => '| Damaged & Losses Deduction: %s pcs (Deducted Value: %s)',
            'da' => '| کسر ضرر و خسارات: %s عدد (ارزش منفی شده: %s)',
            'ps' => '| د زیان او تاوان کسر: %s دانې (منفي شوی ارزښت: %s)'
        ],
        'btn_add_stock_top' => [
            'en' => 'New Purchase / Add Item',
            'da' => 'خرید جدید / افزودن جنس',
            'ps' => 'نوی پېرود / توکی زیاتول'
        ],
        'btn_clear_stock_all' => [
            'en' => 'Clear Warehouse (Delete All)',
            'da' => 'پاکسازی کامل گدام (حذف همه)',
            'ps' => 'د ګودام بشپړ پاکول (ټول ړنګول)'
        ],
        'shelf_view_btn' => [
            'en' => 'Shelf / Cupboard View',
            'da' => 'نمای قفسه ها (الماری)',
            'ps' => 'د المارۍ / قفسو بڼه'
        ],
        'search_item_label' => [
            'en' => 'Search Item / Specs:',
            'da' => 'جستجوی جنس / مشخصات:',
            'ps' => 'د جنس / ځانګړنو پلټنه:'
        ],
        'search_item_placeholder' => [
            'en' => 'Product name, SKU, category or specifications...',
            'da' => 'نام جنس، کد، کتگوری یا مشخصات...',
            'ps' => 'د توکي نوم، کوډ، کټګوري یا ځانګړنې...'
        ],
        'all_categories' => [
            'en' => 'All Categories',
            'da' => 'همه دسته ها',
            'ps' => 'ټولې ډلبندۍ'
        ],
        'filter_stock_type' => [
            'en' => 'Stock Status & Capital Type:',
            'da' => 'وضعیت موجودی و نوعیت سرمایه:',
            'ps' => 'د موجودۍ حالت او د پانګې ډول:'
        ],
        'filter_all_stock' => [
            'en' => 'All Warehouse Stock',
            'da' => 'همه اجناس گدام',
            'ps' => 'د ګودام ټول توکي'
        ],
        'filter_shop_only' => [
            'en' => 'Shop Stock Only',
            'da' => 'فقط اجناس عادی دکان',
            'ps' => 'یوازې د هټۍ عادي توکي'
        ],
        'filter_mudaraba_only' => [
            'en' => 'Mudaraba (Foreign) Stock Only',
            'da' => 'فقط اجناس حساب مضاربت (خارجی)',
            'ps' => 'یوازې د مضاربت (بهرني) توکي'
        ],
        'filter_low_only' => [
            'en' => 'Low Stock (Less than 3)',
            'da' => 'اجناس رو به اتمام (کمتر از ۳)',
            'ps' => 'کم پاتې توکي (له ۳ کم)'
        ],
        'filter_damaged_only' => [
            'en' => 'Damaged / Defective Items',
            'da' => 'اجناس خسارت‌دیده و نقص‌دار',
            'ps' => 'زیانمن او عیب لرونکي توکي'
        ],
        'selected_items_count' => [
            'en' => '%s items selected',
            'da' => '%s جنس انتخاب گردید',
            'ps' => '%s توکي وټاکل شول'
        ],
        'select_all_page' => [
            'en' => 'Select All on Page',
            'da' => 'انتخاب تمام صفحه',
            'ps' => 'ټوله پاڼه ټاکل'
        ],
        'deselect_all' => [
            'en' => 'Deselect All',
            'da' => 'لغو انتخاب‌ها',
            'ps' => 'ټاکنه لغوه کول'
        ],
        'delete_selected' => [
            'en' => 'Delete Selected',
            'da' => 'حذف موارد انتخاب شده',
            'ps' => 'ټاکل شوي ړنګول'
        ],
        'no_items_found' => [
            'en' => 'No items found matching the specified criteria.',
            'da' => 'هیچ جنسی با مشخصات وارد شده یافت نشد.',
            'ps' => 'د ورکړل شویو ځانګړنو سره کوم جنس ونه موندل شو.'
        ],
        'healthy_stock' => [
            'en' => 'Healthy Stock',
            'da' => 'موجودی سالم',
            'ps' => 'روغه موجودي'
        ],
        'damaged_lost' => [
            'en' => 'Damaged / Lost',
            'da' => 'خسارت / ضایعات',
            'ps' => 'زیان / ضایع شوی'
        ],
        'total_value' => [
            'en' => 'Total Value',
            'da' => 'ارزش کل',
            'ps' => 'ټول ارزښت'
        ],
        'sale_price_and_profit' => [
            'en' => 'Sale Price & Profit',
            'da' => 'نرخ فروش و مفاد',
            'ps' => 'د پلور بیه او ګټه'
        ],
        'est_profit_per_unit' => [
            'en' => 'Est. profit: +%s',
            'da' => 'فایده تخمینی: +%s',
            'ps' => 'تخمیني ګټه: +%s'
        ],
        'zero_stock_warning' => [
            'en' => 'Out of Stock',
            'da' => 'اتمام موجودی',
            'ps' => 'موجودي ختمه ده'
        ],
        'record_loss_action' => [
            'en' => 'Record Damage / Loss',
            'da' => 'ثبت ضرر و خسارت',
            'ps' => 'د تاوان او زیان ثبتول'
        ],

        // Add Stock Form & Calculations
        'register_new_stock_title' => [
            'en' => 'Register New Item in Warehouse',
            'da' => 'ثبت جنس جدید در گدام',
            'ps' => 'په ګودام کې د نوي جنس ثبتول'
        ],
        'register_new_stock_sub' => [
            'en' => 'Enter specifications, purchase price, expenses, and pricing.',
            'da' => 'مشخصات، قیمت خرید، مصارف اضافی و قیمت‌گذاری جنس را وارد نمایید.',
            'ps' => 'د توکو ځانګړنې، د پیرودلو بیه، اضافي لګښتونه او نرخ وټاکئ.'
        ],
        'product_name_label' => [
            'en' => 'Product Name / Model:',
            'da' => 'نام و مدل جنس:',
            'ps' => 'د جنس نوم او ماډل:'
        ],
        'category_label' => [
            'en' => 'Category:',
            'da' => 'دسته بندی:',
            'ps' => 'ډلبندي:'
        ],
        'warehouse_loc_label' => [
            'en' => 'Warehouse / Shelf Location:',
            'da' => 'موقعیت در گدام یا دکان:',
            'ps' => 'په ګودام یا هټۍ کې ځای:'
        ],
        'specs_label' => [
            'en' => 'Specifications (size, color, model, packaging, etc.):',
            'da' => 'مشخصات جنس (اندازه، رنگ، مدل، بسته‌بندی و غیره):',
            'ps' => 'ځانګړنې (اندازه، رنګ، ماډل، بسته او نور):'
        ],
        'quantity_label' => [
            'en' => 'Quantity / Measurement:',
            'da' => 'تعداد / مقدار:',
            'ps' => 'شمېر / مقدار:'
        ],
        'purchase_price_label' => [
            'en' => 'Purchase Price Per Unit (%s):',
            'da' => 'نرخ خریداری فی دانه (%s):',
            'ps' => 'د هر یوه د پیرودلو بیه (%s):'
        ],
        'sale_price_label' => [
            'en' => 'Suggested Sale Price Per Unit (%s):',
            'da' => 'نرخ پیشنهادی فروش فی دانه (%s):',
            'ps' => 'د هر یوه د پلور وړاندیز شوې بیه (%s):'
        ],
        'expenses_label' => [
            'en' => 'Additional Expenses Per Unit (Shipping, Customs, Parts):',
            'da' => 'مصارف اضافی فی دانه (کرایه، گمرک، پرزه):',
            'ps' => 'د هر یوه اضافي لګښتونه (کرایه، ګمرک، پرزې):'
        ],
        'live_cost_calc_title' => [
            'en' => 'Live Preview of Unit Cost & Estimated Profit:',
            'da' => 'پیش‌نمایش و محاسبه زنده نرخ تمام‌شد و مفاد تخمینی:',
            'ps' => 'د تمام شد بیې او تخمیني ګټې ژوندی حساب:'
        ],
        'waiting_for_price' => [
            'en' => 'Awaiting Price Input',
            'da' => 'در انتظار ورود نرخ',
            'ps' => 'د بیې داخلولو ته په تمه'
        ],
        'unit_cost_label' => [
            'en' => 'Total Cost Per Unit:',
            'da' => 'نرخ تمام‌شد فی دانه:',
            'ps' => 'د هر یوه تمام شد بیه:'
        ],
        'unit_cost_formula' => [
            'en' => '(Purchase + Extra Expenses)',
            'da' => '(خرید + مصارف اضافی)',
            'ps' => '(پیرودل + اضافي لګښتونه)'
        ],
        'est_profit_label' => [
            'en' => 'Estimated Profit Per Unit:',
            'da' => 'فایده تخمینی فی دانه:',
            'ps' => 'د هر یوه تخمیني ګټه:'
        ],
        'est_profit_formula' => [
            'en' => '(Sale minus Total Cost)',
            'da' => '(فروش منهای تمام‌شد)',
            'ps' => '(پلور منفي تمام شد)'
        ],
        'total_capital_label' => [
            'en' => 'Total Stock Capital in Warehouse:',
            'da' => 'مجموع سرمایه خرید در گدام:',
            'ps' => 'په ګودام کې ټوله پانګه:'
        ],
        'total_capital_formula' => [
            'en' => '(Total Cost × Quantity)',
            'da' => '(تمام‌شد × تعداد)',
            'ps' => '(تمام شد × شمېر)'
        ],
        'total_batch_profit' => [
            'en' => 'Total Expected Batch Profit:',
            'da' => 'مجموع فایده کل این بار:',
            'ps' => 'د دې ټول بار ټوله ګټه:'
        ],
        'total_batch_profit_formula' => [
            'en' => '(Unit Profit × Quantity)',
            'da' => '(فایده فی دانه × تعداد)',
            'ps' => '(د هر یوه ګټه × شمېر)'
        ],
        'barcode_label' => [
            'en' => 'Barcode / Serial (optional):',
            'da' => 'بارکد / سریال (اختیاری):',
            'ps' => 'بارکوډ / سریال (اختیاري):'
        ],
        'country_origin_label' => [
            'en' => 'Country of Origin (Dubai, China, etc.):',
            'da' => 'کشور سازنده / مبدا (دوبی، چین، ایران...):',
            'ps' => 'جوړونکی / مبدا هېواد:'
        ],
        'supplier_label' => [
            'en' => 'Company / Supplier Name:',
            'da' => 'نام شرکت / تامین کننده:',
            'ps' => 'د شرکت / برابرونکي نوم:'
        ],
        'mudaraba_question' => [
            'en' => 'Was this item purchased with Mudaraba partner capital?',
            'da' => 'آیا این جنس از پول حساب مضاربت با شخص خاصی خریداری شده است؟',
            'ps' => 'ایا دا جنس د مضاربت له پانګې څخه اخیستل شوی دی؟'
        ],
        'mudaraba_default_opt' => [
            'en' => '-- No, purchased from regular shop capital --',
            'da' => '-- خیر، از سرمایه عمومی دکان خریداری شده --',
            'ps' => '-- نه، د هټۍ له عمومي پانګې اخیستل شوی --'
        ],
        'save_item_btn' => [
            'en' => 'Save Item to Warehouse',
            'da' => 'ثبت و ذخیره جنس در گدام',
            'ps' => 'په ګودام کې د جنس ثبت او خوندي کول'
        ],
        'edit_stock_title' => [
            'en' => 'Edit Stock Item',
            'da' => 'ویرایش اطلاعات جنس در گدام',
            'ps' => 'په ګودام کې د جنس سمول'
        ],

        // Sales & POS Module
        'new_multiproduct_sale' => [
            'en' => 'Store Multi-Item Invoice',
            'da' => 'صدور بل چند جنسی فروشگاه',
            'ps' => 'د پلورنځي څو جنسیز بل صادرول'
        ],
        'invoice_badge' => [
            'en' => 'Invoice #%s',
            'da' => 'بل: %s',
            'ps' => 'بل: %s'
        ],
        'buyer_name_label' => [
            'en' => 'Customer Name:',
            'da' => 'نام و تخلص خریدار:',
            'ps' => 'د پیرودونکي نوم:'
        ],
        'buyer_phone_label' => [
            'en' => 'Customer Phone:',
            'da' => 'شماره تماس مشتری:',
            'ps' => 'د پیرودونکي د اړیکې شمېره:'
        ],
        'fast_search_label' => [
            'en' => 'Fast Item Search to Add to Invoice:',
            'da' => 'سرچ سریع جنس جهت اضافه به بل:',
            'ps' => 'بل ته د ورزیاتولو لپاره د توکو چټکه پلټنه:'
        ],
        'fast_search_sub' => [
            'en' => 'Type product name, model, barcode, or category',
            'da' => 'نام، مدل، بارکد یا کتگوری جنس را بنویسید',
            'ps' => 'د جنس نوم، ماډل، بارکوډ یا ډلبندي ولیکئ'
        ],
        'pos_search_placeholder' => [
            'en' => 'Search product, SKU, barcode or category...',
            'da' => 'جستجوی جنس، کد، بارکد یا کتگوری...',
            'ps' => 'د توکي، کوډ، بارکوډ یا کټګورۍ پلټنه...'
        ],
        'pos_item_name' => [
            'en' => 'Item Name & Specs',
            'da' => 'نام جنس و مشخصات',
            'ps' => 'د توکي نوم او ځانګړنې'
        ],
        'pos_in_stock' => [
            'en' => 'In Stock',
            'da' => 'موجودی',
            'ps' => 'موجودي'
        ],
        'pos_unit_price' => [
            'en' => 'Unit Price (%s)',
            'da' => 'نرخ فی واحد (%s)',
            'ps' => 'د هر یوه بیه (%s)'
        ],
        'pos_total_price' => [
            'en' => 'Total Amount',
            'da' => 'قیمت مجموع',
            'ps' => 'ټوله بیه'
        ],
        'empty_pos_notice' => [
            'en' => 'No items added to invoice yet. Search above to add items easily.',
            'da' => 'هنوز جنسی به بل اضافه نشده است. با نوشتن نام جنس در فیلد سرچ بالا آن را به راحتی اضافه نمایید.',
            'ps' => 'لا تر اوسه بل ته جنس نه دی ورزیات شوی. د پورته پلټنې له لارې توکي اضافه کړئ.'
        ],
        'invoice_notes_label' => [
            'en' => 'Additional Invoice Notes & Terms:',
            'da' => 'شرح و توضیحات اضافی بل:',
            'ps' => 'د بل اضافي توضیحات او یادښتونه:'
        ],
        'checkout_card_title' => [
            'en' => 'Financial Settlement & Checkout',
            'da' => 'محاسبات مالی و تسویه',
            'ps' => 'مالي حساب او تادیه'
        ],
        'total_invoice_items' => [
            'en' => 'Total Invoice Items:',
            'da' => 'تعداد کل اقلام بل:',
            'ps' => 'د بل د توکو ټول شمېر:'
        ],
        'invoice_grand_total' => [
            'en' => 'Invoice Grand Total:',
            'da' => 'مجموع کل بل:',
            'ps' => 'د بل ټولټال:'
        ],
        'payment_type_label' => [
            'en' => 'Payment Method:',
            'da' => 'نوع پرداخت:',
            'ps' => 'د تادیې ډول:'
        ],
        'pay_cash' => [
            'en' => 'Cash (Full Settlement)',
            'da' => 'نقد (تسویه کامل نقدی)',
            'ps' => 'نغد (بشپړه نغدي تادیه)'
        ],
        'pay_credit' => [
            'en' => 'Credit / Debt (Customer Ledger)',
            'da' => 'قرض (ثبت در دفتر حسابات)',
            'ps' => 'پور (په حسابونو کې ثبتول)'
        ],
        'pay_installment' => [
            'en' => 'Installment (Down payment + Installments)',
            'da' => 'قسط وار (پیش‌پرداخت + اقساط)',
            'ps' => 'قسط واره (مخکې تادیه + قسطونه)'
        ],
        'paid_cash_amount' => [
            'en' => 'Amount Received in Cash (%s):',
            'da' => 'مبلغ وصول شده نقداً (%s):',
            'ps' => 'نغدې ترلاسه شوې پیسې (%s):'
        ],
        'customer_balance_due' => [
            'en' => 'Customer Balance Due:',
            'da' => 'مانده بدهکاری مشتری:',
            'ps' => 'د پیرودونکي پاتې پور:'
        ],
        'balance_auto_debt_note' => [
            'en' => 'This amount will automatically be recorded in customer debts ledger.',
            'da' => 'این مبلغ به طور خودکار به حساب بدهی های مشتری اضافه می گردد.',
            'ps' => 'دا پیسې به په اوتومات ډول د پیرودونکي د پورونو په حساب کې ثبت شي.'
        ],
        'complete_and_print_btn' => [
            'en' => 'Complete Sale & Print A5 Invoice',
            'da' => 'ثبت نهایی و چاپ بل A5',
            'ps' => 'وروستی ثبت او د A5 بل چاپول'
        ],

        // Invoices Archive
        'invoices_archive_title' => [
            'en' => 'Sales Invoices Archive',
            'da' => 'آرشیف بل‌های فروش',
            'ps' => 'د پلور د بلونو آرشیف'
        ],
        'invoice_number' => [
            'en' => 'Invoice No',
            'da' => 'شماره بل',
            'ps' => 'د بل شمېره'
        ],
        'print_invoice' => [
            'en' => 'Print Invoice',
            'da' => 'چاپ بل',
            'ps' => 'بل چاپول'
        ],
        'items_count' => [
            'en' => 'Items Count',
            'da' => 'تعداد اقلام',
            'ps' => 'د توکو شمېر'
        ],
        'cash_settled' => [
            'en' => 'Cash Settled',
            'da' => 'تسویه نقدی',
            'ps' => 'نغده تادیه شوې'
        ],
        'debt_recorded' => [
            'en' => 'Credit / Debt',
            'da' => 'قرضه ثبت شده',
            'ps' => 'ثبت شوی پور'
        ],

        // Losses & Damages
        'losses_management_title' => [
            'en' => 'Damaged Goods & Losses Management',
            'da' => 'مدیریت ضرر و خسارات اجناس',
            'ps' => 'د توکو د زیان او تاوان مدیریت'
        ],
        'today_recorded_loss' => [
            'en' => 'Loss Recorded Today',
            'da' => 'ضرر ثبت شده امروز',
            'ps' => 'نن ثبت شوی زیان'
        ],
        'monthly_loss' => [
            'en' => 'Total Loss This Month',
            'da' => 'مجموع ضرر در ماه جاری',
            'ps' => 'په روانه میاشت کې ټول زیان'
        ],
        'all_time_loss' => [
            'en' => 'Overall All-Time Losses',
            'da' => 'مجموع کل ضرر و خسارات',
            'ps' => 'د ټولو وختونو ټول تاوان'
        ],
        'add_loss_title' => [
            'en' => 'Record New Loss / Damage',
            'da' => 'ثبت ضرر و خسارت جدید',
            'ps' => 'د نوي تاوان او زیان ثبتول'
        ],
        'loss_reason' => [
            'en' => 'Loss Reason / Cause:',
            'da' => 'نوعیت و علت خسارت:',
            'ps' => 'د زیان او تاوان ډول او لامل:'
        ],
        'select_stock_for_loss' => [
            'en' => 'Select Item from Warehouse to Deduct:',
            'da' => 'انتخاب جنس از گدام جهت کسر موجودی:',
            'ps' => 'له ګودام څخه د کسر لپاره د توکي ټاکل:'
        ],
        'no_specific_item' => [
            'en' => '-- General shop loss (no specific item) --',
            'da' => '-- بدون جنس مشخص (خسارت عمومی دکان) --',
            'ps' => '-- بې له مشخص جنسه (د هټۍ عمومي تاوان) --'
        ],
        'total_loss_amount' => [
            'en' => 'Total Loss Amount (%s):',
            'da' => 'مبلغ کل ضرر (%s):',
            'ps' => 'د تاوان ټوله بیه (%s):'
        ],
        'lost_quantity' => [
            'en' => 'Damaged Quantity (Deducted from stock):',
            'da' => 'تعداد ضایع شده (کسر از گدام):',
            'ps' => 'ضایع شوی شمېر (له ګودام څخه کسر):'
        ],
        'save_loss_btn' => [
            'en' => 'Record Loss & Deduct from Stock',
            'da' => 'ثبت قطعی ضرر و اعمال در گدام',
            'ps' => 'د تاوان قطعي ثبتول او په ګودام کې پلي کول'
        ],

        // Reports
        'reports_title' => [
            'en' => 'Comprehensive Financial Reports & Accounting',
            'da' => 'گزارشات مالی و حسابداری دکان',
            'ps' => 'د هټۍ مالي او حسابداري راپورونه'
        ],
        'period_sales' => [
            'en' => 'Total Period Sales',
            'da' => 'مجموع فروشات دوره',
            'ps' => 'په ټاکلې موده کې ټول پلور'
        ],
        'period_gross_profit' => [
            'en' => 'Gross Sales Profit',
            'da' => 'مفاد ناخالص فروشات',
            'ps' => 'د پلور ناخالصه ګټه'
        ],
        'period_expenses' => [
            'en' => 'Operating Expenses',
            'da' => 'مصارف جاری دکان',
            'ps' => 'د هټۍ روان لګښتونه'
        ],
        'period_losses' => [
            'en' => 'Goods Damages & Losses',
            'da' => 'ضرر و خسارات اجناس',
            'ps' => 'د توکو زیان او تاوان'
        ],
        'period_net_profit' => [
            'en' => 'Final Net Profit',
            'da' => 'مفاد خالص نهایی (Net Profit)',
            'ps' => 'وروستۍ خالصه ګټه'
        ],
        'period_net_loss' => [
            'en' => 'Final Net Loss',
            'da' => 'ضرر خالص نهایی دوره',
            'ps' => 'د ټاکلې مودې خالص زیان'
        ],
        'cash_flow_period' => [
            'en' => 'Cash Flow & Receivables in Period',
            'da' => 'وضعیت نقدینگی و قرضه ها در این بازه',
            'ps' => 'په دې موده کې د نغدو پیسو او پورونو وضعیت'
        ],
        'date_from' => [
            'en' => 'From Date:',
            'da' => 'از تاریخ:',
            'ps' => 'له نېټې:'
        ],
        'date_to' => [
            'en' => 'To Date:',
            'da' => 'تا تاریخ:',
            'ps' => 'تر نېټې:'
        ],

        // Debts & Customers
        'debts_management_title' => [
            'en' => 'Debts & Installments Management',
            'da' => 'مدیریت قرضه ها و حسابات',
            'ps' => 'د پورونو او حسابونو مدیریت'
        ],
        'customers_title' => [
            'en' => 'Customers & Accounts Ledger',
            'da' => 'مشتریان و دفتر حسابات',
            'ps' => 'پیرودونکي او د حسابونو دفتر'
        ],
        'customer_name' => [
            'en' => 'Customer Name',
            'da' => 'نام مشتری',
            'ps' => 'د پیرودونکي نوم'
        ],
        'total_debt' => [
            'en' => 'Total Debt',
            'da' => 'مجموع بدهکاری',
            'ps' => 'ټول پور'
        ],
        'settle_installment' => [
            'en' => 'Pay Installment / Settle',
            'da' => 'پرداخت قسط / تسویه',
            'ps' => 'د قسط تادیه / تسویه'
        ],

        // Mudaraba
        'mudaraba_table_title' => [
            'en' => 'Mudaraba Partners & Foreign Consignments (Segregated)',
            'da' => 'وضعیت شرکاء و کالاهای مضاربت (تفکیک‌شده از حسابات دکان)',
            'ps' => 'د مضاربت د شریکانو او بهرنیو توکو حالت'
        ],
        'mudaraba_manage_link' => [
            'en' => 'Full Management & New Contract',
            'da' => 'مدیریت کامل و ثبت قرارداد جدید',
            'ps' => 'بشپړ مدیریت او نوی تړون ثبتول'
        ],
        'partner_name' => [
            'en' => 'Partner Name',
            'da' => 'نام شریک مضاربت',
            'ps' => 'د مضاربت د شریک نوم'
        ],
        'consignment_desc' => [
            'en' => 'Cargo / Project Description',
            'da' => 'شرح کالا و محموله',
            'ps' => 'د توکو او بار شرحه'
        ],
        'country_origin' => [
            'en' => 'Country of Origin',
            'da' => 'کشور مبدا',
            'ps' => 'مبدا هېواد'
        ],
        'capital_provided' => [
            'en' => 'Capital Provided',
            'da' => 'سرمایه ارائه‌شده',
            'ps' => 'ورکړل شوې پانګه'
        ],
        'profit_split' => [
            'en' => 'Profit Split',
            'da' => 'نسبت سود',
            'ps' => 'د ګټې وېش'
        ],
        'realized_profit' => [
            'en' => 'Realized Profit',
            'da' => 'مفاد تحقق‌یافته',
            'ps' => 'ترلاسه شوې ګټه'
        ],
        'partner_share' => [
            'en' => 'Partner Profit Share',
            'da' => 'سهم سود شریک',
            'ps' => 'د شریک د ګټې برخه'
        ],
        'active' => [
            'en' => 'Active',
            'da' => 'فعال',
            'ps' => 'فعال'
        ],
        'settled' => [
            'en' => 'Settled',
            'da' => 'تسویه شده',
            'ps' => 'تسویه شوی'
        ],
        'no_mudaraba_notice' => [
            'en' => 'No Mudaraba contracts registered yet.',
            'da' => 'هنوز قرارداد مضاربتی برای واردات و شراکت در کالا ثبت نشده است.',
            'ps' => 'لا تر اوسه د توکو د شراکت او وارداتو کوم مضاربت تړون نه دی ثبت شوی.'
        ],
        'add_first_mudaraba' => [
            'en' => 'Register First Mudaraba Contract',
            'da' => 'ثبت اولین قرارداد مضاربت',
            'ps' => 'لومړنی مضاربت تړون ثبتول'
        ],

        // Screen Lock
        'screen_locked_title' => [
            'en' => 'Shop System is Locked',
            'da' => 'سیستم دکان قفل گردیده است',
            'ps' => 'د هټۍ سیسټم قفل شوی دی'
        ],
        'screen_locked_desc_pin' => [
            'en' => 'The shop system is locked.<br>Enter your PIN code to unlock.',
            'da' => 'سیستم دکان قفل گردیده است.<br>جهت بازگشایی، پین‌کد خود را وارد نمایید.',
            'ps' => 'د هټۍ سیسټم قفل شوی دی.<br>د خلاصولو لپاره خپل پین کوډ دننه کړئ.'
        ],
        'screen_locked_desc_nopin' => [
            'en' => 'The shop system is temporarily locked.<br>Click unlock button to proceed.',
            'da' => 'سیستم دکان به صورت موقت قفل گردیده است.<br>روی دکمه بازگشایی کلیک نمایید.',
            'ps' => 'د هټۍ سیسټم د لنډ وخت لپاره قفل دی.<br>د خلاصولو لپاره تڼۍ کېکاږئ.'
        ],
        'instant_unlock_btn' => [
            'en' => 'Instantly Unlock Shop Screen',
            'da' => 'باز کردن فوری صفحه دکان',
            'ps' => 'د هټۍ د سکرین سمدستي خلاصول'
        ],
        'lock_settings_link' => [
            'en' => 'Set or Change Lock PIN (Shopkeeper Custom PIN)',
            'da' => 'تنظیم یا تغییر پین‌کد قفل (دلخواه دکاندار)',
            'ps' => 'د قفل پین کوډ تنظیم یا بدلول'
        ]
,
        'lock_settings_title' => [
            'en' => 'Screen Lock PIN Settings (Shopkeeper Custom PIN)',
            'da' => 'تنظیم پین‌کد قفل صفحه (دلخواه دکاندار)',
            'ps' => 'د سکرین قفل پین کوډ تنظیم (د هټۍ وال خوښه)'
        ],
        'lock_settings_help_text' => [
            'en' => 'Screen lock can be enabled to protect your database whenever you leave your shop desk. You can customize your security PIN here.',
            'da' => 'سیستم قفل صفحه جهت محافظت از دیتابیس هنگام ترک میز دکان تعبیه شده است. می‌توانید پین‌کد دلخواه خود را تعیین نمایید.',
            'ps' => 'د هټۍ د میز پرېښودلو پر مهال د معلوماتو ساتنې لپاره سکرین قفل کړئ. دلته خپل پین کوډ وټاکئ.'
        ],
        'lock_settings_enable_label' => [
            'en' => 'Enable Screen Lock with PIN Code (Screen Protection)',
            'da' => 'فعال بودن قفل صفحه با پین‌کد (محافظت از صفحه)',
            'ps' => 'د پین کوډ سره د سکرین قفل فعالول (د سکرین ساتنه)'
        ],
        'lock_settings_new_pin' => [
            'en' => 'New Custom PIN Code (e.g. 1234):',
            'da' => 'پین‌کد جدید دلخواه (مثلاً ۱۲۳۴):',
            'ps' => 'نوی د خوښې پین کوډ (د بېلګې په توګه ۱۲۳۴):'
        ],
        'lock_settings_new_pin_placeholder' => [
            'en' => 'Enter your custom PIN code',
            'da' => 'پین‌کد دلخواه خود را وارد کنید',
            'ps' => 'خپل د خوښې پین کوډ دننه کړئ'
        ],
        'lock_settings_repeat_pin' => [
            'en' => 'Repeat New PIN Code:',
            'da' => 'تکرار پین‌کد جدید:',
            'ps' => 'د نوي پین کوډ تکرار:'
        ],
        'lock_settings_repeat_pin_placeholder' => [
            'en' => 'Repeat PIN code again',
            'da' => 'تکرار مجدد پین‌کد',
            'ps' => 'پین کوډ بیا تکرار کړئ'
        ],
        'cancel' => [
            'en' => 'Cancel',
            'da' => 'انصراف',
            'ps' => 'لغوه کول'
        ],
        'save_settings' => [
            'en' => 'Save PIN Settings',
            'da' => 'ذخیره تنظیمات پین',
            'ps' => 'د پین تنظیمات خوندي کول'
        ],
        'msg_invalid_pin' => [
            'en' => 'Entered PIN is incorrect!',
            'da' => 'پین‌کد وارد شده نادرست است!',
            'ps' => 'دننه شوی پین کوډ غلط دی!'
        ],
        'msg_enter_pin' => [
            'en' => 'Please enter your PIN code.',
            'da' => 'لطفاً پین‌کد را وارد نمایید.',
            'ps' => 'مهرباني وکړئ پین کوډ دننه کړئ.'
        ],
        'msg_server_error' => [
            'en' => 'Server connection error. Please enter PIN again.',
            'da' => 'خطا در ارتباط با سرور. پین را مجدداً وارد نمایید.',
            'ps' => 'له سرور سره په اړیکه کې ستونزه. بیا هڅه وکړئ.'
        ],
        'live_profit_specs_title' => [
            'en' => 'Live Profit & Specifications Preview',
            'da' => 'پیش‌نمایش مشخصات و مفاد',
            'ps' => 'د ځانګړتیاوو او ګټې ژوندی لید'
        ],
        'live_profit_locked' => [
            'en' => 'Live Profit Preview is Locked',
            'da' => 'پیش‌نمایش مفاد قفل است',
            'ps' => 'د ګټې لید قفل دی'
        ],
        'estimated_trade_profit' => [
            'en' => 'Estimated Profit for This Transaction:',
            'da' => 'مفاد تخمینی این معامله:',
            'ps' => 'د دې معاملې اټکلي ګټه:'
        ],
        'gross_profit_margin' => [
            'en' => 'Gross Profit Margin:',
            'da' => 'حاشیه سود ناخالص:',
            'ps' => 'د ناخالصې ګټې سلنه:'
        ],
        'contact_dev_whatsapp' => [
            'en' => 'WhatsApp Ahmad Najeem Nik',
            'da' => 'واتس‌اپ احمد نجیم نیک',
            'ps' => 'د احمد نجیم نیک واټس‌اپ'
        ],
        'live_invoice_profit_label' => [
            'en' => 'Estimated Total Invoice Profit:',
            'da' => 'مفاد تخمینی فروشات بل:',
            'ps' => 'د بل د پلور اټکلي ګټه:'
        ],
        'live_profit_calc_hint' => [
            'en' => 'Live calculation based on purchase cost + item expenses vs selling price',
            'da' => 'محاسبه زنده بر اساس نرخ خرید گدام + مصارف جنسی در برابر قیمت فروش',
            'ps' => 'د پیرود نرخ + مصارفو او د پلور نرخ پر بنسټ ژوندی حساب'
        ],
        'no_items_matched_search' => [
            'en' => 'No products found matching your search.',
            'da' => 'هیچ جنسی مطابق جستجوی شما یافت نشد.',
            'ps' => 'ستاسو له پلټنې سره سم جنس ونه موندل شو.'
        ],
        'no_items_in_pos_yet' => [
            'en' => 'No items added to invoice yet. Start typing product name in the quick search box above.',
            'da' => 'هنوز جنسی به بل اضافه نشده است. با نوشتن نام جنس در فیلد سرچ بالا آن را به راحتی اضافه نمایید.',
            'ps' => 'تر اوسه په بل کې کوم جنس نه دی اضافه شوی. پورته په چټک لټون کې د جنس نوم ولیکئ.'
        ],
        'max_stock_reached_alert' => [
            'en' => 'Maximum available stock for this item has been added to invoice.',
            'da' => 'حداکثر موجودی این جنس در گدام به بل اضافه شده است.',
            'ps' => 'په بل کې د دې جنس اعظمي شته ګدام شمېر اضافه شوی دی.'
        ],
        'insufficient_stock_alert' => [
            'en' => 'Insufficient stock! Current stock: ',
            'da' => 'موجودی ناکافی است! موجودی فعلی: ',
            'ps' => 'ګدام کم دی! اوسنی شتون: '
        ],
        'add_at_least_one_item_alert' => [
            'en' => 'Please add at least one item to the invoice.',
            'da' => 'لطفاً حداقل یک قلم جنس را به بل اضافه کنید.',
            'ps' => 'مهرباني وکړئ لږ تر لږه یو جنس په بل کې ورزیات کړئ.'
        ],
        'custom_unit_price_placeholder' => [
            'en' => 'Custom price...',
            'da' => 'نرخ دلخواه...',
            'ps' => 'خوښې نرخ...'
        ],
        'mudaraba_partner_tag' => [
            'en' => 'Mudaraba: ',
            'da' => 'مضاربت: ',
            'ps' => 'مضاربت: '
        ],
        'stock_available_prefix' => [
            'en' => 'In Stock: ',
            'da' => 'موجودی: ',
            'ps' => 'شتون: '
        ],
        'confirm_bulk_delete_title' => [
            'en' => 'Confirm Bulk Deletion of Selected Items',
            'da' => 'تأیید حذف گروهی اجناس انتخاب شده',
            'ps' => 'د ټاکل شوو جنسونو د یوځای ړنګولو تایید'
        ],
        'confirm_bulk_delete_desc' => [
            'en' => 'Are you sure you want to delete these selected items from the warehouse?',
            'da' => 'آیا از حذف کامل اجناس انتخاب شده از گدام اطمینان قطعی دارید؟',
            'ps' => 'ایا تاسو باوري یاست چې دا ټاکل شوي اجناس له ګودام څخه وباسئ؟'
        ],
        'confirm_bulk_delete_safety_note' => [
            'en' => '(Previous sales invoices and financial reports will remain safely preserved).',
            'da' => '(سوابق بل‌های صادر شده و گزارشات مالی قبلی به طور ایمن حفظ خواهند شد).',
            'ps' => '(د مخکینیو پلورل شوو بلونو او مالي راپورونو ریکارډ به په خوندي توګه پاتې وي).'
        ],
        'yes_delete_selected' => [
            'en' => 'Yes, Delete Selected Items',
            'da' => 'بله، حذف شوند',
            'ps' => 'هو، ړنګ یې کړئ'
        ],
        'cancel_and_return' => [
            'en' => 'Cancel & Return',
            'da' => 'لغو و بازگشت',
            'ps' => 'لغوه کول او شاته تګ'
        ],
        'confirm_delete_all_title' => [
            'en' => 'Clear Warehouse Completely (Delete All Items)',
            'da' => 'پاکسازی کامل گدام (حذف همه اجناس)',
            'ps' => 'د ګودام بشپړ پاکول (د ټولو جنسونو ړنګول)'
        ],
        'confirm_delete_all_warning' => [
            'en' => 'Critical Warning: This operation will completely clear all warehouse stock. This action is irreversible.',
            'da' => 'هشدار بسیار مهم: با انجام این عملیات، تمام اجناس موجود در گدام به طور یکجا و کامل حذف خواهند شد و موجودی گدام صفر خواهد شد. این عملیات غیرقابل بازگشت است.',
            'ps' => 'مهم خبرداری: په دې کار سره به د ګودام ټول توکي په بشپړ ډول پاک شي او ګودام به صفر شي. دا چاره بیرته نه ګرځي.'
        ],
        'confirm_delete_all_prompt' => [
            'en' => 'To confirm, type "DELETE ALL" in the box below:',
            'da' => 'جهت تأیید، عبارت «حذف همه» را در کادر زیر تایپ نمایید:',
            'ps' => 'د تایید لپاره، لاندې بکس کې «DELETE ALL» یا «حذف همه» ولیکئ:'
        ],
        'confirm_delete_all_placeholder' => [
            'en' => 'DELETE ALL',
            'da' => 'حذف همه',
            'ps' => 'DELETE ALL'
        ],
        'btn_confirm_delete_all' => [
            'en' => 'Confirm and Clear Entire Warehouse',
            'da' => 'تأیید و پاکسازی کامل گدام',
            'ps' => 'تایید او د ټول ګودام پاکول'
        ],
        'select_at_least_one_item' => [
            'en' => 'Please select at least one item first.',
            'da' => 'لطفاً ابتدا حداقل یک جنس را انتخاب نمایید.',
            'ps' => 'مهرباني وکړئ لومړی لږ تر لږه یو جنس وټاکئ.'
        ],
        'stock_damaged_row_title' => [
            'en' => 'Damaged / Deducted Portion:',
            'da' => 'بخش کسر شده / ضایعات:',
            'ps' => 'زیانمنه شوې / تاواني شوې برخه:'
        ],
        'stock_damage_cause' => [
            'en' => 'Cause: ',
            'da' => 'علت کسر: ',
            'ps' => 'د زیان لامل: '
        ],
        'stock_view_in_losses' => [
            'en' => 'View in Losses',
            'da' => 'مشاهده در ضررها',
            'ps' => 'په تاوانونو کې کتل'
        ],
        'stock_settle_damage_btn' => [
            'en' => 'Settle Damage Row',
            'da' => 'تسویه ردیف ضایعات',
            'ps' => 'د تاوان برخه پاکول'
        ],
        'stock_out_of_stock' => [
            'en' => 'Out of Stock (0 units)',
            'da' => 'موجودی: ۰ عدد',
            'ps' => 'تمام شوی (۰ دانې)'
        ],
        'stock_low_stock_tag' => [
            'en' => 'Low Stock',
            'da' => 'رو به اتمام',
            'ps' => 'په خلاصېدو'
        ],
        'stock_in_stock_tag' => [
            'en' => 'In Stock',
            'da' => 'موجود',
            'ps' => 'موجود'
        ],
        'stock_undefined_price' => [
            'en' => 'Not Set',
            'da' => 'تعیین‌نشده',
            'ps' => 'نه دی ټاکل شوی'
        ],
        'stock_profit_suffix' => [
            'en' => 'Profit',
            'da' => 'مفاد',
            'ps' => 'ګټه'
        ],
        'debts_management_title' => [
            'en' => 'Debts, Loans & Customer Ledgers',
            'da' => 'مدیریت قرضه ها، طلبات و تفکیک سرمایه و مفاد',
            'ps' => 'د پورونو، طلبونو او حسابونو مدیریت'
        ],
        'debts_receivable_tab' => [
            'en' => 'Receivables / Debtors (Customers Owe Shop)',
            'da' => 'طلب من (قرض دیگران به دکان)',
            'ps' => 'زما طلبونه (پر پیرودونکو پور)'
        ],
        'debts_payable_tab' => [
            'en' => 'Payables / Creditors (Shop Owes Suppliers)',
            'da' => 'بدهی من (قرض دکان به دیگران)',
            'ps' => 'زما پورونه (پر ما د شرکتونو پور)'
        ],
        'total_receivables_stat' => [
            'en' => 'Total Receivables',
            'da' => 'مجموع طلبات دکان',
            'ps' => 'د هټۍ ټول طلبونه'
        ],
        'total_payables_stat' => [
            'en' => 'Total Payables',
            'da' => 'مجموع بدهی‌های دکان',
            'ps' => 'د هټۍ ټول پورونه'
        ],
        'btn_add_debt' => [
            'en' => 'Register New Debt / Loan',
            'da' => 'ثبت قرضه جدید',
            'ps' => 'نوی پور ثبتول'
        ],
        'debt_person_name' => [
            'en' => 'Person / Customer / Company Name',
            'da' => 'نام شخص / مشتری / شرکت',
            'ps' => 'د کس / پیرودونکي / شرکت نوم'
        ],
        'debt_original_amount' => [
            'en' => 'Original Amount',
            'da' => 'مبلغ اصل قرضه',
            'ps' => 'د پور اصلي اندازه'
        ],
        'debt_paid_amount' => [
            'en' => 'Paid Amount',
            'da' => 'پرداخت شده',
            'ps' => 'تادیه شوې برخه'
        ],
        'debt_remaining_amount' => [
            'en' => 'Remaining Balance',
            'da' => 'باقی‌مانده طلب',
            'ps' => 'پاتې پور'
        ],
        'debt_due_date' => [
            'en' => 'Due Date',
            'da' => 'تاریخ وعده / سررسید',
            'ps' => 'د وعدې نېټه'
        ],
        'debt_settle_btn' => [
            'en' => 'Receive / Settle Payment',
            'da' => 'دریافت و تسویه قسط',
            'ps' => 'د پور وصولول او تصفیه'
        ],
        'reports_title' => [
            'en' => 'Financial Reports & Store Accounting',
            'da' => 'گزارشات مالی و حسابداری دکان',
            'ps' => 'مالي راپورونه او د هټۍ محاسبه'
        ],
        'report_tab_daily' => [
            'en' => 'Daily Profit & Loss',
            'da' => 'سود و زیان روزانه',
            'ps' => 'ورځنۍ ګټه او تاوان'
        ],
        'report_tab_monthly' => [
            'en' => 'Monthly Overview',
            'da' => 'عملکرد ماهوار',
            'ps' => 'میاشتنی راپور'
        ],
        'report_tab_balance' => [
            'en' => 'Store Balance Sheet',
            'da' => 'بیلانس عمومی دکان',
            'ps' => 'عمومي بېلانس'
        ],
        'report_gross_sales' => [
            'en' => 'Gross Sales Revenue',
            'da' => 'مجموع فروشات ناخالص',
            'ps' => 'د پلور ټول عاید'
        ],
        'report_cost_goods' => [
            'en' => 'Cost of Goods Sold (COGS)',
            'da' => 'تمام‌شد اجناس فروخته شده',
            'ps' => 'د پلورل شوو توکو لګښت'
        ],
        'report_net_profit' => [
            'en' => 'Net Store Profit',
            'da' => 'مفاد خالص دکان',
            'ps' => 'د هټۍ خالص ګټه'
        ],
        'report_print_btn' => [
            'en' => 'Print Official Financial Report',
            'da' => 'پرینت گزارش رسمی',
            'ps' => 'د رسمي راپور چاپول'
        ],
        'mudaraba_title' => [
            'en' => 'Mudaraba Investments & Foreign Trade Contracts',
            'da' => 'حساب اختصاصی مضاربت و سرمایه‌گذاری خارجی',
            'ps' => 'د بهرني تجارت او مضاربت ځانګړی حساب'
        ],
        'mudaraba_active_contracts' => [
            'en' => 'Active Mudaraba Contracts',
            'da' => 'قراردادهای جاری مضاربت',
            'ps' => 'د مضاربت روان قراردادونه'
        ],
        'mudaraba_partner_name' => [
            'en' => 'Investor / Partner Name',
            'da' => 'نام سرمایه‌گذار / شریک',
            'ps' => 'د پانګوال / شریک نوم'
        ],
        'mudaraba_capital_amount' => [
            'en' => 'Invested Capital',
            'da' => 'مبلغ سرمایه اولیه',
            'ps' => 'لومړنۍ پانګه'
        ],
        'mudaraba_profit_ratio' => [
            'en' => 'Agreed Profit Share (%)',
            'da' => 'فیصدی سهم مفاد',
            'ps' => 'د ګټې سلنه'
        ],
        'mudaraba_import_country' => [
            'en' => 'Origin / Trade Country',
            'da' => 'کشور مبدأ کالا',
            'ps' => 'د سوداګرۍ هېواد'
        ],
        'btn_add_mudaraba' => [
            'en' => 'Register New Mudaraba Contract',
            'da' => 'ثبت قرارداد مضاربت جدید',
            'ps' => 'د مضاربت نوی قرارداد'
        ],
        'mudaraba_settle_contract' => [
            'en' => 'Settle / Clear Contract',
            'da' => 'تسویه کامل قرارداد',
            'ps' => 'د قرارداد بشپړ تصفیه'
        ],
        'expenses_title' => [
            'en' => 'Store Operating Expenses',
            'da' => 'مدیریت مصارف دکان',
            'ps' => 'د هټۍ د روانو لګښتونو مدیریت'
        ],
        'btn_add_expense' => [
            'en' => 'Register New Expense',
            'da' => 'ثبت مصرف جدید',
            'ps' => 'نوی لګښت ثبتول'
        ],
        'expense_category_label' => [
            'en' => 'Expense Category',
            'da' => 'دسته‌بندی مصرف',
            'ps' => 'د لګښت ډول'
        ],
        'expense_amount_label' => [
            'en' => 'Expense Amount',
            'da' => 'مبلغ مصرف',
            'ps' => 'د لګښت اندازه'
        ],
        'expense_date_label' => [
            'en' => 'Expense Date',
            'da' => 'تاریخ ثبت',
            'ps' => 'د لګښت نېټه'
        ],
        'losses_title' => [
            'en' => 'Stock Losses & Damages Ledger',
            'da' => 'ضرر و خسارات اجناس',
            'ps' => 'د جنسونو زیان او تاوان'
        ],
        'btn_add_loss' => [
            'en' => 'Register New Damage / Loss',
            'da' => 'ثبت خسارت و ضرر جدید',
            'ps' => 'نوی زیان ثبتول'
        ],
        'loss_reason_label' => [
            'en' => 'Damage / Loss Reason',
            'da' => 'علت خسارت',
            'ps' => 'د تاوان لامل'
        ],
        'loss_stock_item' => [
            'en' => 'Affected Stock Item',
            'da' => 'جنس آسیب‌دیده',
            'ps' => 'زیانمن شوی جنس'
        ],
        'loss_total_amount' => [
            'en' => 'Total Loss Value',
            'da' => 'ارزش مجموع خسارت',
            'ps' => 'د ټول تاوان ارزښت'
        ],
        'customers_title' => [
            'en' => 'Customer Directory & VIP Ledgers',
            'da' => 'مدیریت مشتریان و حسابات VIP',
            'ps' => 'د پېرودونکو او VIP حسابونو مدیریت'
        ],
        'btn_add_customer' => [
            'en' => 'Register New Customer',
            'da' => 'ثبت مشتری جدید',
            'ps' => 'نوی پېرودونکی ثبتول'
        ],
        'customer_phone_label' => [
            'en' => 'Phone Number',
            'da' => 'شماره تماس',
            'ps' => 'د تلیفون شمېره'
        ],
        'customer_address_label' => [
            'en' => 'Shop / Address',
            'da' => 'آدرس / موقعیت',
            'ps' => 'پته / ځای'
        ],
        'partners_title' => [
            'en' => 'Store Partners & Capital Shares',
            'da' => 'مدیریت شریک ها و سهم سرمایه',
            'ps' => 'د شریکانو او پانګې ونډې مدیریت'
        ],
        'btn_add_partner' => [
            'en' => 'Register New Partner',
            'da' => 'ثبت شریک جدید',
            'ps' => 'نوی شریک ثبتول'
        ],
        'partner_share_percent' => [
            'en' => 'Capital Share Percentage (%)',
            'da' => 'فیصدی سهم سرمایه (%)',
            'ps' => 'د پانګې ونډې سلنه (%)'
        ],
        'partner_profit_balance' => [
            'en' => 'Withdrawable Profit Balance',
            'da' => 'سود قابل برداشت',
            'ps' => 'د ترلاسه کولو وړ ګټه'
        ],
        'invoices_title' => [
            'en' => 'Sales Invoices Archive',
            'da' => 'آرشیف بل‌های فروش',
            'ps' => 'د پلورل شوو بلونو آرشیف'
        ],
        'invoice_search_placeholder' => [
            'en' => 'Search by invoice #, buyer name, phone...',
            'da' => 'جستجو با شماره بل، نام مشتری، تلفن...',
            'ps' => 'د بل شمېرې، نوم یا تلیفون له مخې پلټنه...'
        ],
        'invoice_filter_payment' => [
            'en' => 'Payment Type Filter',
            'da' => 'نوعیت پرداخت',
            'ps' => 'د پیسو ورکړې ډول'
        ],
        'btn_view_print_invoice' => [
            'en' => 'Print Invoice',
            'da' => 'چاپ بل',
            'ps' => 'بل چاپول'
        ],
        'invoice_customer_col' => [
            'en' => 'Customer / Buyer',
            'da' => 'خریدار / مشتری',
            'ps' => 'پېرودونکی'
        ],
        'invoice_items_col' => [
            'en' => 'Items Count',
            'da' => 'تعداد اقلام',
            'ps' => 'د جنسونو شمېر'
        ],
        'invoice_total_col' => [
            'en' => 'Grand Total',
            'da' => 'مجموع بل',
            'ps' => 'د بل مجموعه'
        ],
        'invoice_status_col' => [
            'en' => 'Status',
            'da' => 'وضعیت',
            'ps' => 'حالت'
        ],
        'backup_title' => [
            'en' => 'Database Backup & Data Security',
            'da' => 'پشتیبان‌گیری دیتابیس و امنیت اطلاعات',
            'ps' => 'د ډیټابیس شاتړ او د معلوماتو خوندیتوب'
        ],
        'backup_download_btn' => [
            'en' => 'Download Complete Database Backup',
            'da' => 'دانلود نسخه پشتیبان کامل',
            'ps' => 'د ډیټابیس بشپړ شاتړ ډاونلوډ کړئ'
        ],
        'about_title' => [
            'en' => 'About Nik Smart Count - Warehousing',
            'da' => 'درباره سامانه حسابی Nik Smart Count - Warehousing و سازنده',
            'ps' => 'د Nik Smart Count - Warehousing حسابدارۍ او جوړوونکي په اړه'
        ],
        'about_dev_name' => [
            'en' => 'Technical Architect & Developer: Ahmad Najeem Nik',
            'da' => 'توسعه‌دهنده و طراح سیستم: احمد نجیم نیک',
            'ps' => 'جوړونکی او انجینر: احمد نجیم نیک'
        ],
        'nav_categories' => [
            'en' => 'Categories',
            'da' => 'دسته‌بندی‌های دکان',
            'ps' => 'د هټۍ کټګورۍ'
        ],
        'nav_warehouse' => [
            'en' => 'Warehouse View',
            'da' => 'نمای گدام و موقعیت اجناس',
            'ps' => 'د ګودام لید او موقعیت'
        ],
        'nav_capital' => [
            'en' => 'Capital Movement',
            'da' => 'گردش و حساب سرمایه دکان',
            'ps' => 'د پانګې جریان او حساب'
        ],
        'edit_stock_title' => [
            'en' => 'Edit Warehouse Stock Item',
            'da' => 'ویرایش مشخصات جنس در گدام',
            'ps' => 'په ګودام کې د جنس ځانګړتیاوې بدلول'
        ],

        // Debts & Installments Module Extended
        'debts_receivables_title' => [
            'en' => 'Receivables Ledger (Customers Owe Shop)',
            'da' => 'دفتر طلبات من از مردم و مشتریان',
            'ps' => 'له خلکو او پیرودونکو څخه د طلبونو کتاب'
        ],
        'debts_payables_title' => [
            'en' => 'Payables Ledger (Shop Owes Suppliers)',
            'da' => 'دفتر قرضداری‌های من به مردم',
            'ps' => 'خلکو ته زما د پورونو کتاب'
        ],
        'debts_hasana_title' => [
            'en' => 'Benevolent Loans Ledger (Zero-Profit Qard Hasan)',
            'da' => 'دفتر قرض‌الحسنه (بدون سود)',
            'ps' => 'د قرض‌الحسنه کتاب (بې سوده)'
        ],
        'debts_stat_receivables_desc' => [
            'en' => 'Amounts due from customers and market buyers',
            'da' => 'مبالغی که دکان باید از خریداران وصول کند',
            'ps' => 'هغه پیسې چې هټۍ باید له پیرودونکو راټولې کړي'
        ],
        'debts_stat_payables_desc' => [
            'en' => 'Amounts due to suppliers, wholesalers, and creditors',
            'da' => 'طلب شرکت‌ها، عمده‌فروشان و اشخاص از دکان',
            'ps' => 'پر هټۍ د شرکتونو او ټولیزو پلورونکو پورونه'
        ],
        'debts_stat_hasana_desc' => [
            'en' => 'Interest-free benevolent and trusted financial aids',
            'da' => 'کمک‌های مالی اعتمادی و بدون هیچ‌گونه سود',
            'ps' => 'باوري او بې سوده مالي مرستې'
        ],
        'debts_open_status' => [
            'en' => 'Open Accounts',
            'da' => 'حساب‌های باز',
            'ps' => 'خلاص حسابونه'
        ],
        'debts_settled_status' => [
            'en' => 'Settled Accounts',
            'da' => 'تسویه شده',
            'ps' => 'تصفیه شوي'
        ],
        'debts_all_status' => [
            'en' => 'All',
            'da' => 'همه',
            'ps' => 'ټول'
        ],
        'debts_col_person' => [
            'en' => 'Person / Customer Name',
            'da' => 'نام شخص / مشتری',
            'ps' => 'د کس / پیرودونکي نوم'
        ],
        'debts_col_type' => [
            'en' => 'Classification',
            'da' => 'نوعیت',
            'ps' => 'ډول'
        ],
        'debts_col_total' => [
            'en' => 'Total Debt',
            'da' => 'مبلغ کل',
            'ps' => 'ټولټال'
        ],
        'debts_col_paid' => [
            'en' => 'Paid Amount',
            'da' => 'پرداخت شده',
            'ps' => 'تادیه شوې'
        ],
        'debts_col_remaining' => [
            'en' => 'Remaining Balance',
            'da' => 'باقی‌مانده',
            'ps' => 'پاتې'
        ],
        'debts_col_due' => [
            'en' => 'Due Date',
            'da' => 'سررسید',
            'ps' => 'د وعدې نېټه'
        ],
        'debts_col_status' => [
            'en' => 'Status',
            'da' => 'وضعیت',
            'ps' => 'حالت'
        ],
        'debts_col_actions' => [
            'en' => 'Actions',
            'da' => 'عملیات',
            'ps' => 'کړنې'
        ],
        'debts_btn_settle' => [
            'en' => 'Receive Installment',
            'da' => 'ثبت رسیدگی',
            'ps' => 'قسط ثبتول'
        ],
        'debts_btn_edit' => [
            'en' => 'Edit',
            'da' => 'ویرایش',
            'ps' => 'سمول'
        ],
        'debts_btn_delete' => [
            'en' => 'Delete',
            'da' => 'حذف',
            'ps' => 'ړنګول'
        ],
        'debts_btn_new' => [
            'en' => 'Register New Debt / Loan',
            'da' => 'ثبت قرضه / طلب جدید',
            'ps' => 'نوی پور / طلب'
        ],
        'debts_selected_count' => [
            'en' => '%s debt accounts selected',
            'da' => '%s حساب قرضه انتخاب گردید',
            'ps' => '%s د پور حسابونه وټاکل شول'
        ],
        'debts_modal_new_title' => [
            'en' => 'Register New Debt / Loan Account',
            'da' => 'ثبت حساب قرضه یا طلب جدید',
            'ps' => 'د پور یا طلب نوی حساب ثبتول'
        ],
        'debts_modal_person_name' => [
            'en' => 'Person / Customer / Company Name:',
            'da' => 'نام شخص / خریدار / شرکت:',
            'ps' => 'د کس / پیرودونکي / شرکت نوم:'
        ],
        'debts_modal_phone' => [
            'en' => 'Phone Number:',
            'da' => 'شماره تماس:',
            'ps' => 'د تلیفون شمېره:'
        ],
        'debts_modal_type' => [
            'en' => 'Account Classification:',
            'da' => 'نوعیت حساب:',
            'ps' => 'د حساب ډول:'
        ],
        'debts_modal_opt_receivable' => [
            'en' => 'Receivable (Customer owes shop)',
            'da' => 'طلب من (شخص به دکان بدهکار است)',
            'ps' => 'زما طلب (کس پر هټۍ پوروړی دی)'
        ],
        'debts_modal_opt_payable' => [
            'en' => 'Payable (Shop owes supplier / person)',
            'da' => 'بدهی من (دکان به شخص یا شرکت بدهکار است)',
            'ps' => 'زما پور (هټۍ شرکت یا کس ته پوروړې ده)'
        ],
        'debts_modal_hasana_check' => [
            'en' => 'This is a Benevolent Loan (Qard Hasan - Zero Profit)',
            'da' => 'این قرضه قرض‌الحسنه است (اعتمادی و بدون هیچ‌گونه سود)',
            'ps' => 'دا قرض الحسنه ده (بې له کومې ګټې)'
        ],
        'debts_modal_total_amount' => [
            'en' => 'Total Debt Amount (%s):',
            'da' => 'مبلغ کل قرضه (%s):',
            'ps' => 'د پور ټوله اندازه (%s):'
        ],
        'debts_modal_down_payment' => [
            'en' => 'Initial Down Payment Received / Paid (%s):',
            'da' => 'پیش‌پرداخت نقد اولیه (%s):',
            'ps' => 'لومړنۍ نغده مخکې تادیه (%s):'
        ],
        'debts_modal_due_date' => [
            'en' => 'Due Date / Settlement Deadline:',
            'da' => 'تاریخ سررسید / وعده پرداخت:',
            'ps' => 'د تادیې نېټه / وعده:'
        ],
        'debts_modal_notes' => [
            'en' => 'Additional Terms & Conditions:',
            'da' => 'توضیحات و شرایط توافق شده:',
            'ps' => 'اضافي توضیحات او شرایط:'
        ],
        'debts_modal_submit' => [
            'en' => 'Save Debt Account',
            'da' => 'ثبت و ذخیره حساب قرضه',
            'ps' => 'د پور حساب خوندي کول'
        ],
        'debts_installment_title' => [
            'en' => 'Receive / Settle Debt Payment',
            'da' => 'رسیدگی و دریافت قسط قرضه',
            'ps' => 'د پور قسط وصولول او تصفیه'
        ],
        'debts_inst_amount' => [
            'en' => 'Payment Amount (%s):',
            'da' => 'مبلغ پرداختی این قسط (%s):',
            'ps' => 'د دې قسط اندازه (%s):'
        ],
        'debts_inst_date' => [
            'en' => 'Payment Date:',
            'da' => 'تاریخ پرداخت:',
            'ps' => 'د تادیې نېټه:'
        ],
        'debts_inst_reason' => [
            'en' => 'Payment Reason / Category:',
            'da' => 'دلیل و منبع پرداخت:',
            'ps' => 'د تادیې لامل:'
        ],
        'debts_inst_capital_share' => [
            'en' => 'Principal Capital Share (%s):',
            'da' => 'سهم اصل سرمایه (%s):',
            'ps' => 'د اصلي پانګې ونډه (%s):'
        ],
        'debts_inst_profit_share' => [
            'en' => 'Net Profit Share (%s):',
            'da' => 'سهم مفاد خالص (%s):',
            'ps' => 'د خالصې ګټې ونډه (%s):'
        ],
        'debts_inst_notes' => [
            'en' => 'Payment Receipt Notes:',
            'da' => 'توضیحات و شرح رسیدگی:',
            'ps' => 'د تادیې د رسید تفصیل:'
        ],
        'debts_inst_submit' => [
            'en' => 'Submit Payment',
            'da' => 'ثبت و ذخیره پرداختی',
            'ps' => 'تادیه ثبت او خوندي کړئ'
        ],

        // Customers Module Extended
        'cust_directory_title' => [
            'en' => 'Customer Accounts & VIP Directory',
            'da' => 'باشگاه مشتریان و حسابات',
            'ps' => 'د پیرودونکو ټولنه او حسابونه'
        ],
        'cust_directory_sub' => [
            'en' => 'Complete management of wholesale & retail buyers, receivables, and transactions',
            'da' => 'مدیریت کامل خریداران عمده و پرچون، استعلام مطالبات و سابقه معاملات',
            'ps' => 'د ټولیز او پرچون پیرودونکو بشپړ مدیریت او حسابونه'
        ],
        'cust_total_registered' => [
            'en' => 'Total Registered Customers',
            'da' => 'مجموع مشتریان ثبت شده',
            'ps' => 'ټول ثبت شوي پیرودونکي'
        ],
        'cust_wholesale_count' => [
            'en' => 'Wholesale Buyers',
            'da' => 'مشتریان خریدار عمده',
            'ps' => 'ټولیز پلورونکي'
        ],
        'cust_retail_count' => [
            'en' => 'Retail Customers',
            'da' => 'مشتریان پرچون عادی',
            'ps' => 'عادي پرچون پیرودونکي'
        ],
        'cust_receivables_total' => [
            'en' => 'Total Outstanding Receivables',
            'da' => 'مجموع طلبات معوقه از مشتریان',
            'ps' => 'له پیرودونکو پاتې ټول طلبونه'
        ],
        'cust_search_placeholder' => [
            'en' => 'Search name, phone number, company...',
            'da' => 'جستجوی نام، شماره تماس، نام شرکت...',
            'ps' => 'د نوم، تلیفون، شرکت پلټنه...'
        ],
        'cust_type_all' => [
            'en' => 'All Customer Types',
            'da' => 'همه انواع مشتری',
            'ps' => 'د پیرودونکو ټول ډولونه'
        ],
        'cust_type_wholesale' => [
            'en' => 'Wholesale Buyers',
            'da' => 'خریداران عمده',
            'ps' => 'ټولیز پیرودونکي'
        ],
        'cust_type_retail' => [
            'en' => 'Retail Buyers',
            'da' => 'خریداران پرچون',
            'ps' => 'پرچون پیرودونکي'
        ],
        'cust_type_partner' => [
            'en' => 'Business Partners',
            'da' => 'همکاران تجارتی',
            'ps' => 'سوداګریز همکاران'
        ],
        'cust_col_name' => [
            'en' => 'Customer Name',
            'da' => 'نام و مشخصات مشتری',
            'ps' => 'د پیرودونکي نوم او ځانګړنې'
        ],
        'cust_col_type' => [
            'en' => 'Type',
            'da' => 'نوعیت',
            'ps' => 'ډول'
        ],
        'cust_col_company' => [
            'en' => 'Company / Store',
            'da' => 'شرکت / دکان',
            'ps' => 'شرکت / هټۍ'
        ],
        'cust_col_phone' => [
            'en' => 'Phone Number',
            'da' => 'شماره تماس',
            'ps' => 'د تلیفون شمېره'
        ],
        'cust_col_balance' => [
            'en' => 'Receivables / Debts',
            'da' => 'مانده طلبات / قرضه',
            'ps' => 'پاتې پورونه'
        ],
        'cust_col_actions' => [
            'en' => 'Actions',
            'da' => 'عملیات',
            'ps' => 'کړنې'
        ],
        'cust_btn_new' => [
            'en' => 'Register New Customer',
            'da' => 'ثبت مشتری جدید',
            'ps' => 'نوی پیرودونکی ثبتول'
        ],
        'cust_btn_view_debts' => [
            'en' => 'View Receivables',
            'da' => 'مشاهده قرضه‌ها',
            'ps' => 'د پورونو لیدل'
        ],
        'cust_btn_ledger' => [
            'en' => 'Account Ledger',
            'da' => 'دفتر حساب',
            'ps' => 'د حساب کتاب'
        ],
        'cust_vip_preview_alert' => [
            'en' => 'Customer module is running in preview mode. License is required to save new VIP records.',
            'da' => 'ماژول مشتریان در حالت پیش‌نمایش فعال است. جهت ثبت و ذخیره داده‌ها نیاز به تهیه لایسنس دارید.',
            'ps' => 'د پیرودونکو ماډل په آزمایښتي حالت کې دی. د نویو VIP معلوماتو لپاره لایسنس ته اړتیا ده.'
        ],
        'cust_activate_license' => [
            'en' => 'Activate License',
            'da' => 'فعال‌سازی لایسنس',
            'ps' => 'لایسنس فعالول'
        ],
        'cust_person_unit' => [
            'en' => 'persons',
            'da' => 'نفر',
            'ps' => 'تنه'
        ],
        'cust_company_unit' => [
            'en' => 'companies',
            'da' => 'شخص/شرکت',
            'ps' => 'شرکتونه'
        ],
        'cust_clear_filter' => [
            'en' => 'Clear Filter',
            'da' => 'پاک کردن فیلتر',
            'ps' => 'فلټر پاکول'
        ],
        'cust_modal_title' => [
            'en' => 'Register New Customer Profile',
            'da' => 'ثبت پروفایل مشتری جدید',
            'ps' => 'د نوي پیرودونکي پروفایل ثبتول'
        ],
        'cust_modal_fullname' => [
            'en' => 'Full Customer Name:',
            'da' => 'نام و تخلص کامل مشتری:',
            'ps' => 'د پیرودونکي بشپړ نوم:'
        ],
        'cust_modal_type_label' => [
            'en' => 'Customer Tier / Type:',
            'da' => 'نوعیت مشتری:',
            'ps' => 'د پیرودونکي ډول:'
        ],
        'cust_modal_company_name' => [
            'en' => 'Store / Company Name:',
            'da' => 'نام دکان یا شرکت:',
            'ps' => 'د هټۍ یا شرکت نوم:'
        ],
        'cust_modal_address' => [
            'en' => 'Address / City / Location:',
            'da' => 'آدرس / ولایت / موقعیت:',
            'ps' => 'پته / ښار / ځای:'
        ],
        'cust_modal_notes' => [
            'en' => 'Notes & Terms:',
            'da' => 'توضیحات و شرایط مشتری:',
            'ps' => 'توضیحات او یادښتونه:'
        ],
        'cust_modal_submit' => [
            'en' => 'Save Customer Profile',
            'da' => 'ثبت و ذخیره مشتری',
            'ps' => 'پیرودونکی خوندي کول'
        ],

        // Expenses Module Extended
        'exp_page_title' => [
            'en' => 'Store Operating Expenses (Separated from Losses)',
            'da' => 'مدیریت مصارف جاری دکان (تفکیک شده از ضرر)',
            'ps' => 'د هټۍ روان لګښتونه (له تاوانونو جلا)'
        ],
        'exp_page_sub' => [
            'en' => 'Track operational overhead, rent, electricity, refreshments, salaries, and logistics',
            'da' => 'ثبت و محاسبه مصارف عملیاتی، کرایه، بل برق، نان، معاشات و ترانسپورت دکان',
            'ps' => 'د کرایې، برېښنا، ډوډۍ او معاشونو لګښتونه'
        ],
        'exp_go_to_losses' => [
            'en' => 'Go to Stock Damages & Losses',
            'da' => 'رفتن به ضرر و خسارات اجناس',
            'ps' => 'د توکو زیان او تاوان ته تلل'
        ],
        'exp_print_table' => [
            'en' => 'Print Table',
            'da' => 'چاپ جدول',
            'ps' => 'جدول چاپول'
        ],
        'exp_today' => [
            'en' => "Today's Expenses",
            'da' => 'مصارف امروز',
            'ps' => 'د نن لګښتونه'
        ],
        'exp_this_month' => [
            'en' => "This Month's Expenses",
            'da' => 'مصارف این ماه',
            'ps' => 'د دې میاشتې لګښتونه'
        ],
        'exp_all_time' => [
            'en' => 'All-Time Operating Expenses',
            'da' => 'مجموع کل مصارف جاری',
            'ps' => 'ټول روان لګښتونه'
        ],
        'exp_add_title' => [
            'en' => 'Register New Expense',
            'da' => 'ثبت مصرف جدید',
            'ps' => 'نوی لګښت ثبتول'
        ],
        'exp_category_select' => [
            'en' => 'Expense Category:',
            'da' => 'دسته‌بندی مصرف:',
            'ps' => 'د لګښت ډول:'
        ],
        'exp_amount_label' => [
            'en' => 'Expense Amount (%s):',
            'da' => 'مبلغ مصرف (%s):',
            'ps' => 'د لګښت اندازه (%s):'
        ],
        'exp_date_label' => [
            'en' => 'Expense Date:',
            'da' => 'تاریخ مصرف:',
            'ps' => 'د لګښت نېټه:'
        ],
        'exp_desc_label' => [
            'en' => 'Description & Details:',
            'da' => 'توضیحات و شرح مصرف:',
            'ps' => 'توضیحات او تفصیل:'
        ],
        'exp_btn_save' => [
            'en' => 'Save Expense',
            'da' => 'ثبت و ذخیره مصرف',
            'ps' => 'لګښت خوندي کول'
        ],
        'exp_list_title' => [
            'en' => 'Expense Records List',
            'da' => 'لست مصارف ثبت شده',
            'ps' => 'د ثبت شوو لګښتونو لست'
        ],
        'exp_filter_all_cats' => [
            'en' => 'All Categories',
            'da' => 'همه دسته‌ها',
            'ps' => 'ټول ډولونه'
        ],
        'exp_col_cat' => [
            'en' => 'Category',
            'da' => 'دسته‌بندی',
            'ps' => 'ډلبندي'
        ],
        'exp_col_amount' => [
            'en' => 'Amount',
            'da' => 'مبلغ',
            'ps' => 'اندازه'
        ],
        'exp_col_date' => [
            'en' => 'Date',
            'da' => 'تاریخ',
            'ps' => 'نېټه'
        ],
        'exp_col_desc' => [
            'en' => 'Description',
            'da' => 'شرح مصرف',
            'ps' => 'تفصیل'
        ],
        'exp_col_user' => [
            'en' => 'Recorded By',
            'da' => 'ثبت کننده',
            'ps' => 'ثبتوونکی'
        ],
        'exp_locked_notice' => [
            'en' => 'Shop Expenses module is currently locked by security settings.',
            'da' => 'گزینه مصارف دکان در حال حاضر قفل می‌باشد',
            'ps' => 'د هټۍ د لګښتونو برخه اوس مهال قلف ده'
        ],
        'exp_locked_desc' => [
            'en' => 'According to security settings, expense entry is locked. You can unlock it in Super Admin panel after two-step verification.',
            'da' => 'طبق تنظیمات امنیتی، ثبت و ویرایش مصارف قفل است. کنترل و بازگشایی آن در پنل سوپر ادمین (پین ) انجام می‌شود.',
            'ps' => 'د امنیتي تنظیماتو له مخې دا برخه بنده ده. په سوپر اډمین کې یې خلاصولای شئ.'
        ],
        'exp_unlock_btn' => [
            'en' => 'Unlock in Super Admin Panel',
            'da' => 'بازگشایی در پنل سوپر ادمین',
            'ps' => 'په سوپر اډمین پینل کې خلاصول'
        ],
        'exp_license_details' => [
            'en' => 'View License Details',
            'da' => 'مشاهده جزئیات لایسنس',
            'ps' => 'د لایسنس تفصیلات کتل'
        ],

        // Losses Module Extended
        'loss_page_title' => [
            'en' => 'Damaged Goods & Stock Losses (Separated from Expenses)',
            'da' => 'مدیریت ضرر و خسارات اجناس (تفکیک شده از مصارف)',
            'ps' => 'د توکو تاوان او زیان (له لګښتونو جلا)'
        ],
        'loss_page_sub' => [
            'en' => 'Record losses from broken screens, component damage, price drops, or lost stock',
            'da' => 'ثبت خسارات ناشی از شکستگی صفحه، سوختگی قطعات، افت قیمت یا مفقودی اجناس گدام',
            'ps' => 'د سکرین ماتېدو، سوځېدو او تاوان ثبتول'
        ],
        'loss_go_to_expenses' => [
            'en' => 'Go to Operating Expenses',
            'da' => 'رفتن به مصارف دکان',
            'ps' => 'د هټۍ لګښتونو ته تلل'
        ],
        'loss_today' => [
            'en' => "Today's Losses",
            'da' => 'خسارات امروز',
            'ps' => 'د نن تاوانونه'
        ],
        'loss_this_month' => [
            'en' => "This Month's Losses",
            'da' => 'خسارات این ماه',
            'ps' => 'د دې میاشتې تاوانونه'
        ],
        'loss_all_time' => [
            'en' => 'All-Time Total Losses',
            'da' => 'مجموع کل خسارات',
            'ps' => 'ټول تاوانونه'
        ],
        'loss_add_title' => [
            'en' => 'Register New Damage / Loss',
            'da' => 'ثبت خسارت و ضرر جدید',
            'ps' => 'نوی تاوان ثبتول'
        ],
        'loss_stock_select' => [
            'en' => 'Select Item from Warehouse Stock:',
            'da' => 'انتخاب جنس آسیب‌دیده از گدام:',
            'ps' => 'له ګودام څخه د جنس ټاکل:'
        ],
        'loss_stock_placeholder' => [
            'en' => '-- General / Non-Stock Loss --',
            'da' => '-- خسارت عمومی / خارج از گدام --',
            'ps' => '-- عمومي تاوان / له ګودام بهر --'
        ],
        'loss_reason_select' => [
            'en' => 'Damage Cause / Reason:',
            'da' => 'علت و نوعیت خسارت:',
            'ps' => 'د زیان لامل:'
        ],
        'loss_qty_label' => [
            'en' => 'Damaged Quantity (Units):',
            'da' => 'تعداد خسارت‌دیده (عدد):',
            'ps' => 'د زیانمنو شمېر (دانې):'
        ],
        'loss_amount_label' => [
            'en' => 'Total Loss Value (%s):',
            'da' => 'مبلغ مجموع خسارت (%s):',
            'ps' => 'د ټول تاوان اندازه (%s):'
        ],
        'loss_action_label' => [
            'en' => 'Warehouse Stock Action:',
            'da' => 'نحوه اقدام در گدام:',
            'ps' => 'په ګودام کې اقدام:'
        ],
        'loss_action_strike' => [
            'en' => 'Deduct from healthy stock & keep damage note',
            'da' => 'کسر از موجودی سالم و ثبت یادداشت خسارت',
            'ps' => 'له روغې زېرمې کسرول او یادښت ساتل'
        ],
        'loss_action_delete' => [
            'en' => 'Delete entire product row from warehouse',
            'da' => 'حذف کامل ردیف کالا از گدام',
            'ps' => 'له ګودام څخه د توکي بشپړ ړنګول'
        ],
        'loss_btn_save' => [
            'en' => 'Record Damage / Loss',
            'da' => 'ثبت ضرر و کسر از گدام',
            'ps' => 'تاوان ثبتول او له ګودام کمول'
        ],
        'loss_list_title' => [
            'en' => 'Recorded Damages & Losses List',
            'da' => 'لست ضرر و خسارات ثبت شده',
            'ps' => 'د ثبت شوو تاوانونو لست'
        ],

        // Partners & Capital Module Extended
        'partners_card_title' => [
            'en' => 'Partnership Accounts & Profit Sharing',
            'da' => 'حساب شرکا و تسهیم مفاد',
            'ps' => 'د شریکانو حساب او د ګټې وېش'
        ],
        'partners_net_profit_desc' => [
            'en' => 'Total Distributable Net Store Profit',
            'da' => 'مجموع مفاد خالص قابل تقسیم دکان',
            'ps' => 'د هټۍ د وېش وړ خالص ګټه'
        ],
        'partners_general_capital' => [
            'en' => 'General Capital',
            'da' => 'سرمایه عمومی',
            'ps' => 'عمومي پانګه'
        ],
        'partners_mudaraba_link' => [
            'en' => 'Dedicated Mudaraba Account (Foreign Goods)',
            'da' => 'حساب اختصاصی مضاربت (کالای خارجی)',
            'ps' => 'د مضاربت ځانګړی حساب (بهرني توکي)'
        ],
        'partners_capital_turnover' => [
            'en' => 'Capital Turnover Management',
            'da' => 'مدیریت گردش سرمایه دکان',
            'ps' => 'د پانګې د څرخ مدیریت'
        ],
        'partners_add_title' => [
            'en' => 'Register New Partner',
            'da' => 'ثبت شریک جدید',
            'ps' => 'نوی شریک ثبتول'
        ],
        'partners_name_label' => [
            'en' => 'Partner Name:',
            'da' => 'نام شریک:',
            'ps' => 'د شریک نوم:'
        ],
        'partners_phone_label' => [
            'en' => 'Phone Number:',
            'da' => 'شماره تماس:',
            'ps' => 'د تلیفون شمېره:'
        ],
        'partners_share_label' => [
            'en' => 'Profit Share (%):',
            'da' => 'فیصدی سهم از مفاد (٪):',
            'ps' => 'د ګټې سلنه (٪):'
        ],
        'partners_inv_label' => [
            'en' => 'Initial Investment Amount (%s):',
            'da' => 'مبلغ سرمایه‌گذاری اولیه (%s):',
            'ps' => 'د لومړنۍ پانګونې اندازه (%s):'
        ],
        'partners_date_label' => [
            'en' => 'Partnership Start Date:',
            'da' => 'تاریخ آغاز همکاری:',
            'ps' => 'د همکارۍ د پیل نېټه:'
        ],
        'partners_notes_label' => [
            'en' => 'Terms & Conditions:',
            'da' => 'توضیحات و شرایط شراکت:',
            'ps' => 'د شراکت شرایط:'
        ],
        'partners_btn_save' => [
            'en' => 'Save Partner Details',
            'da' => 'ثبت مشخصات شریک',
            'ps' => 'د شریک ثبتول'
        ],
        'partners_list_title' => [
            'en' => 'Store Partners List & Profit Share',
            'da' => 'لست شرکاء دکان و سهم از سود',
            'ps' => 'د هټۍ د شریکانو لست او د ګټې ونډه'
        ],

        // Mudaraba Module Extended
        'mud_page_title' => [
            'en' => 'Dedicated Mudaraba Account (Foreign Sourcing)',
            'da' => 'حساب اختصاصی مضاربت و سرمایه‌گذاری خارجی',
            'ps' => 'د بهرني تجارت او مضاربت ځانګړی حساب'
        ],
        'mud_page_sub' => [
            'en' => 'Joint venture investments, Dubai & foreign imports, transparent profit sharing',
            'da' => 'سرمایه‌گذاری مشترک، بارهای وارداتی دبی، محاسبه دقیق سود و تصفیه حساب',
            'ps' => 'ګډه پانګونه، له دوبۍ وارداتي بارونه او د ګټې روڼ وېش'
        ],
        'mud_active_capital' => [
            'en' => 'Active Contracts Capital',
            'da' => 'سرمایه در گردش قراردادهای فعال',
            'ps' => 'په روانو قراردادونو کې پانګه'
        ],
        'mud_total_sales' => [
            'en' => 'Total Sales from Mudaraba Goods',
            'da' => 'مجموع فروشات کالاهای مضاربت',
            'ps' => 'د مضاربت د توکو ټول پلور'
        ],
        'mud_total_profit' => [
            'en' => 'Total Realized Profit',
            'da' => 'مجموع مفاد حاصل شده',
            'ps' => 'ټوله ترلاسه شوې ګټه'
        ],
        'mud_paid_to_partner' => [
            'en' => 'Profit Paid to Investor',
            'da' => 'مفاد پرداخت شده به شریک',
            'ps' => 'شریک ته ورکړل شوې ګټه'
        ],
        'mud_returned_cap' => [
            'en' => 'Returned Capital Principal',
            'da' => 'استرداد اصل سرمایه',
            'ps' => 'بېرته ورکړل شوې پانګه'
        ],
        'mud_btn_new' => [
            'en' => 'Register New Mudaraba Contract',
            'da' => 'ثبت قرارداد مضاربت جدید',
            'ps' => 'د مضاربت نوی قرارداد'
        ],
        'mud_contracts_list' => [
            'en' => 'Contracts & Joint Shipments List',
            'da' => 'لست قراردادها و محموله‌های مضاربت',
            'ps' => 'د مضاربت د قراردادونو لست'
        ],
        'mud_contract_details' => [
            'en' => 'Contract Details',
            'da' => 'جزئیات قرارداد',
            'ps' => 'د قرارداد تفصیل'
        ],
        'mud_record_sales' => [
            'en' => 'Record Sales & Profit',
            'da' => 'ثبت فروش و مفاد',
            'ps' => 'پلور او ګټه ثبتول'
        ],
        'mud_payout' => [
            'en' => 'Payout & Settlement',
            'da' => 'تسویه و پرداخت وجه',
            'ps' => 'تصفیه او د پیسو ورکړه'
        ],
        'mud_print_sheet' => [
            'en' => 'Print Official Sheet',
            'da' => 'چاپ صورتحساب',
            'ps' => 'د صورتحساب چاپ'
        ],

        // Reports Module Extended
        'rep_page_title' => [
            'en' => 'Comprehensive Financial & Accounting Reports',
            'da' => 'گزارشات مالی و حسابداری دکان',
            'ps' => 'د هټۍ مالي او حسابداري راپورونه'
        ],
        'rep_filter_title' => [
            'en' => 'Filter Store Financial & Profit Reports',
            'da' => 'فیلتر گزارشات مالی و سود دکان',
            'ps' => 'د مالي او ګټې راپورونو فلټر'
        ],
        'rep_time_range' => [
            'en' => 'Time Range: From %s to %s',
            'da' => 'بازه زمانی: از %s تا %s',
            'ps' => 'د وخت موده: له %s څخه تر %s'
        ],
        'rep_today' => [
            'en' => 'Today',
            'da' => 'امروز',
            'ps' => 'نن'
        ],
        'rep_7days' => [
            'en' => 'Past 7 Days',
            'da' => '۷ روز اخیر',
            'ps' => 'وروستۍ ۷ ورځې'
        ],
        'rep_this_month' => [
            'en' => 'This Month',
            'da' => 'این ماه',
            'ps' => 'دا میاشت'
        ],
        'rep_print' => [
            'en' => 'Print Financial Report',
            'da' => 'چاپ گزارش',
            'ps' => 'راپور چاپول'
        ],
        'rep_date_from' => [
            'en' => 'From Date:',
            'da' => 'از تاریخ:',
            'ps' => 'له نېټې:'
        ],
        'rep_date_to' => [
            'en' => 'To Date:',
            'da' => 'تا تاریخ:',
            'ps' => 'تر نېټې:'
        ],
        'rep_apply_date' => [
            'en' => 'Apply Filter',
            'da' => 'اعمال تاریخ',
            'ps' => 'نېټه عملي کول'
        ],
        'rep_period_sales' => [
            'en' => 'Period Sales',
            'da' => 'فروشات این بازه',
            'ps' => 'د دې دورې پلور'
        ],
        'rep_period_gross' => [
            'en' => 'Period Gross Profit',
            'da' => 'مفاد ناخالص فروش',
            'ps' => 'د پلور ناخالصه ګټه'
        ],
        'rep_period_expenses' => [
            'en' => 'Operating Expenses',
            'da' => 'مصارف جاری دکان',
            'ps' => 'روان لګښتونه'
        ],
        'rep_period_losses' => [
            'en' => 'Damaged Goods / Losses',
            'da' => 'ضرر و خسارات اجناس',
            'ps' => 'د توکو زیان او تاوان'
        ],
        'rep_period_net_profit' => [
            'en' => 'Final Net Profit',
            'da' => 'مفاد خالص نهایی',
            'ps' => 'وروستۍ خالص ګټه'
        ],
        'rep_period_net_loss' => [
            'en' => 'Final Net Loss',
            'da' => 'ضرر خالص نهایی',
            'ps' => 'وروستی خالص زیان'
        ],
        'rep_cash_received' => [
            'en' => 'Cash Collected at Counter',
            'da' => 'نقدینگی وصول شده نقداً',
            'ps' => 'نغدې ترلاسه شوې پیسې'
        ],
        'rep_debts_added' => [
            'en' => 'New Receivables Added',
            'da' => 'قرضه‌های ثبت شده در این بازه',
            'ps' => 'په دې دوره کې ثبت شوي پورونه'
        ],
        'rep_top_selling_title' => [
            'en' => 'Top-Selling Products in Period',
            'da' => 'پرفروش‌ترین اجناس در این بازه',
            'ps' => 'ډېر پلورل شوي توکي'
        ],
        'rep_invoices_history' => [
            'en' => 'Invoices Issued in Period',
            'da' => 'بل‌های صادر شده در این بازه',
            'ps' => 'په دې دوره کې صادر شوي بلونه'
        ],

        // AI Advisor Module Extended
        'ai_page_title' => [
            'en' => 'Smart AI Shop Accountant & Advisor',
            'da' => 'هوش مصنوعی حسابدار و مشاور مالی دکان',
            'ps' => 'د هټۍ د محاسبې هوښیار سلاکار'
        ],
        'ai_page_sub' => [
            'en' => 'Instant financial insights, sales demand forecasting, and profit audit',
            'da' => 'مشاوره هوشمند، پیش‌بینی فروش و پاسخ فوری به سوالات مالی و سود دکان',
            'ps' => 'هوښیاره مشوره او مالي شننه'
        ],
        'ai_chat_title' => [
            'en' => 'Ask Shop AI Accountant',
            'da' => 'گفتگو با هوش مصنوعی حسابدار',
            'ps' => 'له هوښیار محاسب سره خبرې'
        ],
        'ai_input_placeholder' => [
            'en' => 'Ask about store profit, top items, reorder suggestions...',
            'da' => 'سوال خود را درباره سود، فروش، بهترین اجناس بپرسید...',
            'ps' => 'د ګټې او پلور په اړه خپله پوښتنه ولیکئ...'
        ],
        'ai_btn_ask' => [
            'en' => 'Send Question',
            'da' => 'ارسال سوال',
            'ps' => 'پوښتنه ولېږئ'
        ],
        'ai_quick_suggestions' => [
            'en' => 'Suggested Analyses',
            'da' => 'پرسش‌های پیشنهادی سریع',
            'ps' => 'وړاندیز شوې پوښتنې'
        ],

        // Backup Module Extended
        'bk_page_title' => [
            'en' => 'Database Backup & Data Security',
            'da' => 'پشتیبان‌گیری دیتابیس و امنیت اطلاعات',
            'ps' => 'د ډیټابیس شاتړ او د معلوماتو خوندیتوب'
        ],
        'bk_page_sub' => [
            'en' => 'Safely download database backups and protect business data',
            'da' => 'تهیه نسخه پشتیبان خودکار و دستی از دیتابیس دکان و بازگردانی اطلاعات',
            'ps' => 'د معلوماتو خوندي شاتړ اخیستل'
        ],
        'bk_download_sqlite' => [
            'en' => 'Download SQLite Database',
            'da' => 'دانلود فایل دیتابیس SQLite',
            'ps' => 'د SQLite فایل ډاونلوډ'
        ],
        'bk_download_sql' => [
            'en' => 'Download SQL Dump Script',
            'da' => 'دانلود اسکریپت SQL Dump',
            'ps' => 'د SQL اسکرېپټ ډاونلوډ'
        ],
        'bk_snapshots_title' => [
            'en' => 'Local Backup Snapshots',
            'da' => 'نسخه‌های پشتیبان محلی',
            'ps' => 'ځايي شاتړونه'
        ],
        'bk_create_snapshot' => [
            'en' => 'Create New Instant Backup',
            'da' => 'ایجاد پشتیبان جدید',
            'ps' => 'نوی شاتړ جوړول'
        ],

        // About Module Extended
        'ab_dev_title' => [
            'en' => 'Technical Architect & Developer',
            'da' => 'توسعه‌دهنده و طراح سیستم',
            'ps' => 'د سیسټم طراح او انجینر'
        ],
        'ab_dev_name' => [
            'en' => 'Ahmad Najeem Nik',
            'da' => 'احمد نجیم نیک',
            'ps' => 'احمد نجیم نیک'
        ],
        'ab_dev_desc' => [
            'en' => 'Senior Software Architect specializing in enterprise retail ERPs, accounting systems, and practical AI integrations',
            'da' => 'توسعه‌دهنده ارشد نرم‌افزارهای تجاری، سامانه‌های مالی یکپارچه و هوش مصنوعی',
            'ps' => 'د سوداګریزو او مالي سافټویرونو لوړپوړی انجینر'
        ],
        'ab_call_btn' => [
            'en' => 'Call Phone: +93 783 788 278',
            'da' => 'تماس تیلیفونی: ۰۷۸۳۷۸۸۲۷۸',
            'ps' => 'تلیفوني اړیکه: ۰۷۸۳۷۸۸۲۷۸'
        ],
        'ab_whatsapp_btn' => [
            'en' => 'WhatsApp Chat',
            'da' => 'ارتباط در واتساپ',
            'ps' => 'په واټساپ کې اړیکه'
        ],
        'ab_system_title' => [
            'en' => 'About Nik Smart Count - Warehousing',
            'da' => 'درباره نرم‌افزار Nik Smart Count - Warehousing',
            'ps' => 'د Nik Smart Count - Warehousing سافټویر په اړه'
        ],
        'ab_system_desc' => [
            'en' => 'Nik Smart Count - Warehousing manages purchases, inventory, sales and accounts for retailers, wholesalers and general trading businesses.',
            'da' => 'نیک اسمارت کانت سیستم مدیریت خرید، موجودی، فروش و حسابات برای دکان‌ها، عمده‌فروشان و کسب‌وکارهای تجارتی است.',
            'ps' => 'نیک اسمارت کانت د پرچون، عمده پلور او سوداګرۍ لپاره د پېرود، موجودۍ، پلور او حسابونو سیسټم دی.'
        ]
    ];

    return $dict;
}

/**
 * Translation helper function
 *
 * @param string $key
 * @param string $fallback
 * @return string
 */
function i18n_lookup($key, $fallback = '') {
    $lang = get_current_lang();
    $dict = get_translation_dictionary();

    if (isset($dict[$key][$lang])) {
        return $dict[$key][$lang];
    }
    // Check phrase translation
    $phrase = get_phrase_translation($key, $lang);
    if ($phrase !== null) {
        return $phrase;
    }
    // Fallback to English
    if (isset($dict[$key]['en'])) {
        return $dict[$key]['en'];
    }
    // Fallback to Dari
    if (isset($dict[$key]['da'])) {
        return $dict[$key]['da'];
    }
    if ($lang === 'en' && !empty($fallback)) {
        $trans = get_phrase_translation($fallback, 'en');
        if ($trans !== null) return $trans;
    }
    return !empty($fallback) ? $fallback : $key;
}

/**
 * Strict tri-lingual isolation helper:
 * Returns 100% English when lang=en, 100% Dari when lang=da, 100% Pashto when lang=ps.
 *
 * @param string $en English translation
 * @param string $da Dari translation
 * @param string $ps Pashto translation
 * @return string
 */
function __t($en, $da, $ps = '') {
    $lang = get_current_lang();
    if ($lang === 'en') {
        return $en;
    }
    if ($lang === 'ps') {
        return !empty($ps) ? $ps : $da;
    }
    return dari_terms($da);
}

/**
 * Translation helper with echo
 *
 * @param string $key
 * @param string $fallback
 */
function __e($key, $fallback = '') {
    echo htmlspecialchars(__($key, $fallback));
}

/**
 * Universal phrase translator for dynamic text and system phrases
 */
function get_phrase_translation($phrase, $lang) {
    static $phraseMap = null;
    if ($phraseMap === null) {
        $phraseMap = [
            'سیستم مدیریت دکان' => ['en' => 'Shop Management System', 'da' => 'سیستم مدیریت دکان', 'ps' => 'د هټۍ مدیریت سیسټم'],
            'داشبورد عمومی دکان' => ['en' => 'General Shop Dashboard', 'da' => 'داشبورد عمومی دکان', 'ps' => 'د هټۍ عمومي ډشبورډ'],
            'فروختن جنس (بل جدید)' => ['en' => 'Sell Product (New Invoice)', 'da' => 'فروختن جنس (بل جدید)', 'ps' => 'د جنس پلورل (نوی بل)'],
            'فروختن جنس (بل چند جنسی جدید)' => ['en' => 'Sell Product (Multi-Item Invoice)', 'da' => 'فروختن جنس (بل چند جنسی جدید)', 'ps' => 'د څو جنسیز بل پلورل'],
            'آرشیف بل ها' => ['en' => 'Invoices Archive', 'da' => 'آرشیف بل‌ها', 'ps' => 'د بلونو آرشیف'],
            'آرشیف بل‌ها' => ['en' => 'Invoices Archive', 'da' => 'آرشیف بل‌ها', 'ps' => 'د بلونو آرشیف'],
            'مدیریت گدام و لست اجناس' => ['en' => 'Warehouse & Inventory Management', 'da' => 'مدیریت گدام و لست اجناس', 'ps' => 'د ګودام او اجناسو مدیریت'],
            'ثبت جنس جدید در گدام' => ['en' => 'Add New Stock Item', 'da' => 'ثبت جنس جدید در گدام', 'ps' => 'په ګودام کې نوی جنس ثبتول'],
            'ویرایش مشخصات جنس در گدام' => ['en' => 'Edit Stock Item Details', 'da' => 'ویرایش مشخصات جنس در گدام', 'ps' => 'د جنس مشخصات سمول'],
            'مدیریت قرضه ها و حسابات' => ['en' => 'Debts & Accounts Management', 'da' => 'مدیریت قرضه ها و حسابات', 'ps' => 'د پورونو او حسابونو مدیریت'],
            'مدیریت قرضه ها، طلبات و تفکیک سرمایه و مفاد' => ['en' => 'Debts, Receivables & Capital/Profit Segregation', 'da' => 'مدیریت قرضه ها، طلبات و تفکیک سرمایه و مفاد', 'ps' => 'د پورونو، طلبونو او د پانګې او ګټې بېلوالی'],
            'مشتریان و حسابات' => ['en' => 'Customers & Accounts', 'da' => 'مشتریان و حسابات', 'ps' => 'پیرودونکي او حسابونه'],
            'مدیریت مشتریان و حسابات VIP' => ['en' => 'VIP Customer Accounts & Directory', 'da' => 'مدیریت مشتریان و حسابات VIP', 'ps' => 'د VIP پیرودونکو مدیریت او حسابونه'],
            'مصارف دکان' => ['en' => 'Shop Expenses', 'da' => 'مصارف دکان', 'ps' => 'د هټۍ لګښتونه'],
            'مدیریت مصارف دکان' => ['en' => 'Shop Expenses Management', 'da' => 'مدیریت مصارف دکان', 'ps' => 'د هټۍ د لګښتونو مدیریت'],
            'مدیریت مصارف جاری دکان (تفکیک شده از ضرر)' => ['en' => 'Operating Expenses Management (Segregated from Losses)', 'da' => 'مدیریت مصارف جاری دکان (تفکیک شده از ضرر)', 'ps' => 'د هټۍ د روانو لګښتونو مدیریت (له تاوان څخه جلا)'],
            'ضرر و خسارات اجناس' => ['en' => 'Damaged Goods & Losses', 'da' => 'ضرر و خسارات اجناس', 'ps' => 'د توکو زیان او تاوان'],
            'مدیریت ضرر و خسارات اجناس' => ['en' => 'Damaged Goods & Losses Management', 'da' => 'مدیریت ضرر و خسارات اجناس', 'ps' => 'د توکو د زیان او تاوان مدیریت'],
            'مدیریت ضرر و خسارات اجناس (تفکیک شده از مصارف)' => ['en' => 'Damaged Goods & Losses Management (Segregated from Expenses)', 'da' => 'مدیریت ضرر و خسارات اجناس (تفکیک شده از مصارف)', 'ps' => 'د توکو د زیان او تاوان مدیریت (له لګښتونو څخه جلا)'],
            'شرکاء و سرمایه دکان' => ['en' => 'Partners & Store Capital', 'da' => 'شرکاء و سرمایه دکان', 'ps' => 'شریکان او د هټۍ پانګه'],
            'مدیریت شریک ها و سهم سرمایه' => ['en' => 'Manage Store Partners & Capital Shares', 'da' => 'مدیریت شریک ها و سهم سرمایه', 'ps' => 'د شریکانو او د پانګې د ونډو مدیریت'],
            'سرمایه و مضاربت (خارجی)' => ['en' => 'Mudaraba Account (Foreign Sourcing)', 'da' => 'سرمایه و مضاربت (خارجی)', 'ps' => 'د مضاربت حساب (بهرني توکي)'],
            'حساب اختصاصی مضاربت و سرمایه‌گذاری خارجی' => ['en' => 'Dedicated Mudaraba Account & Foreign Investments', 'da' => 'حساب اختصاصی مضاربت و سرمایه‌گذاری خارجی', 'ps' => 'د بهرني تجارت او مضاربت ځانګړی حساب'],
            'گزارشات مالی و حسابداری دکان' => ['en' => 'Financial & Accounting Reports', 'da' => 'گزارشات مالی و حسابداری دکان', 'ps' => 'د هټۍ مالي او حسابداري راپورونه'],
            'گزارشات جامع مالی' => ['en' => 'Comprehensive Financial Reports', 'da' => 'گزارشات جامع مالی', 'ps' => 'جامع مالي راپورونه'],
            'هوش مصنوعی دکان' => ['en' => 'Shop AI Advisor', 'da' => 'هوش مصنوعی دکان', 'ps' => 'د هټۍ مصنوعي استخبارات'],
            'هوش مصنوعی حسابدار و مشاور مالی دکان' => ['en' => 'Shop AI Accountant & Financial Advisor', 'da' => 'هوش مصنوعی حسابدار و مشاور مالی دکان', 'ps' => 'د هټۍ د محاسبې هوښیار سلاکار'],
            'پشتیبان‌گیری (بک‌آپ)' => ['en' => 'Database Backup', 'da' => 'پشتیبان‌گیری (بک‌آپ)', 'ps' => 'د ډیټابیس شاتړ'],
            'پشتیبان‌گیری دیتابیس و امنیت اطلاعات' => ['en' => 'Database Backup & Data Security', 'da' => 'پشتیبان‌گیری دیتابیس و امنیت اطلاعات', 'ps' => 'د ډیټابیس شاتړ او د معلوماتو خوندیتوب'],
            'درباره برنامه و سازنده' => ['en' => 'About System & Developer', 'da' => 'درباره برنامه و سازنده', 'ps' => 'د سیسټم او جوړونکي په اړه'],
            'درباره سامانه حسابی Nik Smart Count - Warehousing و سازنده' => ['en' => 'About Nik Smart Count - Warehousing Accounting System & Developer', 'da' => 'درباره سامانه حسابی Nik Smart Count - Warehousing و سازنده', 'ps' => 'د نیک اسمارت کانت حسسابي سیسټم او جوړونکي په اړه'],
            'کنترل پنل سوپر ادمین' => ['en' => 'Super Admin Control Panel', 'da' => 'کنترل پنل سوپر ادمین', 'ps' => 'د سوپر اډمین کنټرول پینل'],
            'مدیریت دسته بندی های دکان' => ['en' => 'Shop Categories Management', 'da' => 'مدیریت دسته بندی های دکان', 'ps' => 'د هټۍ د کټګوریو مدیریت'],
            'نمای گدام و موقعیت اجناس' => ['en' => 'Warehouse View & Item Locations', 'da' => 'نمای گدام و موقعیت اجناس', 'ps' => 'د ګودام لید او د توکو ځایونه'],
            'گردش و حساب سرمایه دکان' => ['en' => 'Shop Capital & Turnover Ledger', 'da' => 'گردش و حساب سرمایه دکان', 'ps' => 'د هټۍ د پانګې څرخ او حساب'],
            'طلب من از مردم (مشتریان)' => ['en' => 'Receivables (Customers Owe Store)', 'da' => 'طلب من از مردم (مشتریان)', 'ps' => 'له پیرودونکو څخه زما طلب'],
            'مبالغی که دکان باید از خریداران وصول کند' => ['en' => 'Amounts due to be collected from buyers', 'da' => 'مبالغی که دکان باید از خریداران وصول کند', 'ps' => 'هغه پیسې چې هټۍ باید له پیرودونکو ترلاسه کړي'],
            'قرضداری‌های من (طلب مردم از من)' => ['en' => 'Payables (Store Owes Creditors)', 'da' => 'قرضداری‌های من (طلب مردم از من)', 'ps' => 'خلکو ته زما پورونه'],
            'طلب شرکت‌ها، عمده‌فروشان و اشخاص از دکان' => ['en' => 'Amounts owed to suppliers, wholesalers, and creditors', 'da' => 'طلب شرکت‌ها، عمده‌فروشان و اشخاص از دکان', 'ps' => 'پر هټۍ د شرکتونو او ټولیزو پلورونکو پورونه'],
            'قرض‌الحسنه (اعتمادی و بدون سود)' => ['en' => 'Benevolent Loans (Interest-Free Qard Hasan)', 'da' => 'قرض‌الحسنه (اعتمادی و بدون سود)', 'ps' => 'قرض الحسنه (بې ګټې او باوري)'],
            'کمک‌های مالی اعتمادی و بدون هیچ‌گونه سود' => ['en' => 'Trusted benevolent financial aids with zero interest', 'da' => 'کمک‌های مالی اعتمادی و بدون هیچ‌گونه سود', 'ps' => 'باوري او بې سوده مالي مرستې'],
            'دفتر طلبات من از مردم و مشتریان' => ['en' => 'Receivables Ledger (Customers Owe Store)', 'da' => 'دفتر طلبات من از مردم و مشتریان', 'ps' => 'له خلکو او پیرودونکو څخه د طلبونو کتاب'],
            'دفتر قرضداری‌های من به مردم' => ['en' => 'Payables Ledger (Store Owes Creditors)', 'da' => 'دفتر قرضداری‌های من به مردم', 'ps' => 'خلکو ته زما د پورونو کتاب'],
            'دفتر قرض‌الحسنه (بدون سود)' => ['en' => 'Benevolent Loans Ledger (Zero Profit)', 'da' => 'دفتر قرض‌الحسنه (بدون سود)', 'ps' => 'د قرض‌الحسنه کتاب (بې سوده)'],
            'ثبت قرضه / طلب جدید' => ['en' => 'Register New Debt / Loan', 'da' => 'ثبت قرضه / طلب جدید', 'ps' => 'نوی پور / طلب ثبتول'],
            'حساب‌های باز' => ['en' => 'Open Accounts', 'da' => 'حساب‌های باز', 'ps' => 'خلاص حسابونه'],
            'تسویه شده' => ['en' => 'Settled', 'da' => 'تسویه شده', 'ps' => 'تصفیه شوي'],
            'همه' => ['en' => 'All', 'da' => 'همه', 'ps' => 'ټول'],
            'انتخاب همه' => ['en' => 'Select All', 'da' => 'انتخاب همه', 'ps' => 'ټول ټاکل'],
            'لغو انتخاب‌ها' => ['en' => 'Deselect All', 'da' => 'لغو انتخاب‌ها', 'ps' => 'ټاکنه لغوه کول'],
            'حذف موارد انتخاب شده' => ['en' => 'Delete Selected', 'da' => 'حذف موارد انتخاب شده', 'ps' => 'ټاکل شوي ړنګول'],
            'طرف حساب / شخص' => ['en' => 'Account / Contact Name', 'da' => 'طرف حساب / شخص', 'ps' => 'د کس / حساب نوم'],
            'شماره تماس' => ['en' => 'Phone Number', 'da' => 'شماره تماس', 'ps' => 'د تلیفون شمېره'],
            'نوعیت حساب' => ['en' => 'Account Classification', 'da' => 'نوعیت حساب', 'ps' => 'د حساب ډول'],
            'مجموع قرضه' => ['en' => 'Total Debt', 'da' => 'مجموع قرضه', 'ps' => 'ټولټال پور'],
            'رسیدگی شده و تفکیک (سرمایه / سود)' => ['en' => 'Paid & Breakdown (Capital / Profit)', 'da' => 'رسیدگی شده و تفکیک (سرمایه / سود)', 'ps' => 'تادیه شوې او بېلوالی (پانګه / ګټه)'],
            'باقی‌مانده' => ['en' => 'Remaining Balance', 'da' => 'باقی‌مانده', 'ps' => 'پاتې پیسې'],
            'وضعیت' => ['en' => 'Status', 'da' => 'وضعیت', 'ps' => 'حالت'],
            'تاریخ ثبت' => ['en' => 'Date Recorded', 'da' => 'تاریخ ثبت', 'ps' => 'د ثبت نېټه'],
            'عملیات' => ['en' => 'Actions', 'da' => 'عملیات', 'ps' => 'کړنې'],
            'قرض‌الحسنه' => ['en' => 'Benevolent Loan', 'da' => 'قرض‌الحسنه', 'ps' => 'قرض الحسنه'],
            'قرضداری من' => ['en' => 'Payable', 'da' => 'قرضداری من', 'ps' => 'زما پور'],
            'طلب من' => ['en' => 'Receivable', 'da' => 'طلب من', 'ps' => 'زما طلب'],
            'تسویه کامل' => ['en' => 'Fully Settled', 'da' => 'تسویه کامل', 'ps' => 'بشپړه تصفیه'],
            'جاری (باز)' => ['en' => 'Active (Open)', 'da' => 'جاری (باز)', 'ps' => 'خلاص (پاتې)'],
            'رسیدگی' => ['en' => 'Settle / Pay', 'da' => 'رسیدگی', 'ps' => 'تصفیه'],
            'تفکیک' => ['en' => 'Breakdown', 'da' => 'تفکیک', 'ps' => 'بېلوالی'],
            'ویرایش' => ['en' => 'Edit', 'da' => 'ویرایش', 'ps' => 'سمول'],
            'حذف' => ['en' => 'Delete', 'da' => 'حذف', 'ps' => 'ړنګول'],
            'ذخیره تغییرات' => ['en' => 'Save Changes', 'da' => 'ذخیره تغییرات', 'ps' => 'بدلونونه خوندي کړئ'],
            'انصراف' => ['en' => 'Cancel', 'da' => 'انصراف', 'ps' => 'لغوه'],
            'بستن پنجره' => ['en' => 'Close Window', 'da' => 'بستن پنجره', 'ps' => 'کړکۍ وتړئ'],
            'سرمایه' => ['en' => 'Capital', 'da' => 'سرمایه', 'ps' => 'پانګه'],
            'مفاد' => ['en' => 'Profit', 'da' => 'مفاد', 'ps' => 'ګټه'],
            'هیچ رکوردی در این بخش یافت نشد.' => ['en' => 'No records found in this section.', 'da' => 'هیچ رکوردی در این بخش یافت نشد.', 'ps' => 'په دې برخه کې هېڅ ریکارډ ونه موندل شو.'],
        ];
    }

    $t = trim($phrase);
    if (isset($phraseMap[$t][$lang])) {
        return $phraseMap[$t][$lang];
    }
    return null;
}

/**
 * Currency symbol helper
 */
function __currency() {
    return get_currency_label();
}



/**
 * Universal Display & Localization Helper Functions
 */

function get_shop_title() {
    $raw = function_exists('get_setting') ? get_setting('shop_name', DEFAULT_SHOP_NAME) : DEFAULT_SHOP_NAME;
    // Saved business names are user data, not industry-specific UI labels.
    if ($raw === DEFAULT_SHOP_NAME || $raw === '') return __('default_shop_name');
    return $raw;
}

function display_user_fullname($name) {
    $lang = get_current_lang();
    if (empty($name) || strpos($name, "مدیر دکان") !== false || strpos($name, "Shop Manager") !== false) {
        if ($lang === "en") return "Shop Manager";
        if ($lang === "ps") return "د هټۍ مدیر";
        return "مدیر دکان";
    }
    if (strpos($name, "سوپر ادمین") !== false || strpos($name, "Super Admin") !== false) {
        if ($lang === "en") return "Ahmad Najeem Nik (Super Admin)";
        if ($lang === "ps") return "احمد نجیم نیک (سوپر اډمین)";
        return "احمد نجیم نیک (سوپر ادمین)";
    }
    return $name;
}

function display_payment_type($type) {
    $lang = get_current_lang();
    $t = trim((string)$type);
    if ($t === "نقد" || strtolower($t) === "cash") {
        if ($lang === "en") return "Cash";
        if ($lang === "ps") return "نغد";
        return "نقد";
    }
    if ($t === "قرض" || strtolower($t) === "credit" || strtolower($t) === "debt") {
        if ($lang === "en") return "Credit / Loan";
        if ($lang === "ps") return "پور";
        return "قرضه";
    }
    if ($t === "قسط وار" || strtolower($t) === "installment") {
        if ($lang === "en") return "Installment";
        if ($lang === "ps") return "په قسطونو";
        return "قسط‌وار";
    }
    return $t;
}

function display_status($status) {
    $lang = get_current_lang();
    $s = trim((string)$status);
    if ($s === "باز" || strtolower($s) === "open" || strtolower($s) === "pending") {
        if ($lang === "en") return "Pending / Open";
        if ($lang === "ps") return "خلاص (پاتې)";
        return "باز (تسویه ناشده)";
    }
    if ($s === "تسویه" || strtolower($s) === "settled" || strtolower($s) === "cleared") {
        if ($lang === "en") return "Settled";
        if ($lang === "ps") return "تصفیه شوی";
        return "تسویه شده";
    }
    if ($s === "فعال" || strtolower($s) === "active") {
        if ($lang === "en") return "Active";
        if ($lang === "ps") return "فعال";
        return "فعال";
    }
    if ($s === "فسخ" || strtolower($s) === "cancelled" || strtolower($s) === "terminated") {
        if ($lang === "en") return "Terminated";
        if ($lang === "ps") return "فسخه شوی";
        return "فسخ شده";
    }
    return $s;
}

function display_debt_type($type) {
    $lang = get_current_lang();
    $t = trim((string)$type);
    if ($t === "receivable" || strpos($t, "طلب") !== false) {
        if ($lang === "en") return "Receivable (They owe us)";
        if ($lang === "ps") return "زما طلب (پر نورو پور)";
        return "طلب من (قرض دیگران به دکان)";
    }
    if ($t === "payable" || strpos($t, "بدهی") !== false) {
        if ($lang === "en") return "Payable (We owe them)";
        if ($lang === "ps") return "زما پور (پر ما د شرکتونو پور)";
        return "بدهی من (قرض دکان به دیگران)";
    }
    return $t;
}

function display_expense_type($type) {
    $lang = get_current_lang();
    $t = trim((string)$type);
    if ($t === "ضرر" || strtolower($t) === "loss") {
        if ($lang === "en") return "Stock Loss / Damage";
        if ($lang === "ps") return "د جنس زیان / تاوان";
        return "ضرر و خسارت جنس";
    }
    if ($lang === "en") return "Operating Expense";
    if ($lang === "ps") return "روان لګښتونه";
    return "مصارف جاری دکان";
}

function display_expense_category($cat) {
    $lang = get_current_lang();
    $c = trim((string)$cat);
    $map = [
        "کرایه دکان" => ["en" => "Shop Rent", "da" => "کرایه دکان", "ps" => "د هټۍ کرایه"],
        "برق و انرژی" => ["en" => "Electricity & Power", "da" => "برق و انرژی", "ps" => "برېښنا او انرژي"],
        "غذا و چای" => ["en" => "Food & Tea", "da" => "غذا و چای", "ps" => "خواړه او چای"],
        "انترنت و ارتباطات" => ["en" => "Internet & Phone", "da" => "انترنت و ارتباطات", "ps" => "انټرنیټ او اړیکې"],
        "متفرقه" => ["en" => "Miscellaneous", "da" => "متفرقه", "ps" => "متفرقه"],
        "ترمیم و استهلاک" => ["en" => "Repairs & Maintenance", "da" => "ترمیم و استهلاک", "ps" => "ترمیم او ساتنه"],
        "مالیات و جواز" => ["en" => "Taxes & Licensing", "da" => "مالیات و جواز", "ps" => "مالیات او جواز"],
        "خسارت جنس" => ["en" => "Stock Damage", "da" => "خسارت جنس", "ps" => "د جنس تاوان"],
        "خسارت عمومی" => ["en" => "General Damage", "da" => "خسارت عمومی", "ps" => "عمومي تاوان"]
    ];
    if (isset($map[$c][$lang])) return $map[$c][$lang];
    return $c;
}

function display_loss_reason($reason) {
    $lang = get_current_lang();
    $r = trim((string)$reason);
    $map = [
        "شکستگی ال سی دی" => ["en" => "Broken Screen / LCD", "da" => "شکستگی ال سی دی", "ps" => "د ال سي ډي ماتېدل"],
        "خرابی باتری" => ["en" => "Battery Defect", "da" => "خرابی باتری", "ps" => "د بیټرۍ خرابوالی"],
        "آب خوردگی" => ["en" => "Water Damage", "da" => "آب خوردگی", "ps" => "اوبو خوړل"],
        "سوختگی برد" => ["en" => "Motherboard Defect", "da" => "سوختگی برد", "ps" => "د بورډ سوځېدل"],
        "استهلاک و خش" => ["en" => "Scratched & Depreciated", "da" => "استهلاک و خش", "ps" => "زړښت او تخریب"],
        "خسارت جنس" => ["en" => "Stock Loss", "da" => "خسارت جنس", "ps" => "د جنس تاوان"],
        "خسارت عمومی" => ["en" => "General Damage", "da" => "خسارت عمومی", "ps" => "عمومي تاوان"]
    ];
    if (isset($map[$r][$lang])) return $map[$r][$lang];
    return $r;
}

function display_country($country) {
    $lang = get_current_lang();
    $c = trim((string)$country);
    $map = [
        "دوبی (امارات)" => ["en" => "Dubai (UAE)", "da" => "دوبی (امارات)", "ps" => "دوبی (امارات)"],
        "چین" => ["en" => "China", "da" => "چین", "ps" => "چین"],
        "ایران" => ["en" => "Iran", "da" => "ایران", "ps" => "ایران"],
        "ترکیه" => ["en" => "Turkey", "da" => "ترکیه", "ps" => "ترکیه"]
    ];
    if (isset($map[$c][$lang])) return $map[$c][$lang];
    return $c;
}

function display_customer_type($type) {
    $lang = get_current_lang();
    $t = trim((string)$type);
    if ($t === "پرچون") {
        if ($lang === "en") return "Retail Customer";
        if ($lang === "ps") return "پرچون پېرودونکی";
        return "مشتری پرچون";
    }
    if ($t === "عمده") {
        if ($lang === "en") return "Wholesale Customer";
        if ($lang === "ps") return "عمده پېرودونکی";
        return "مشتری عمده";
    }
    return $t;
}

function display_stock_location($loc) {
    $lang = get_current_lang();
    $l = trim((string)$loc);
    $map = [
        "الماری ۱" => ["en" => "Cabinet 1", "da" => "الماری ۱", "ps" => "المارۍ ۱"],
        "الماری ۲" => ["en" => "Cabinet 2", "da" => "الماری ۲", "ps" => "المارۍ ۲"],
        "الماری ویترین ۱" => ["en" => "Showcase Cabinet 1", "da" => "الماری ویترین ۱", "ps" => "د ویترین المارۍ ۱"],
        "الماری انبار و گدام مرکزی" => ["en" => "Main Warehouse Storage", "da" => "الماری انبار و گدام مرکزی", "ps" => "د مرکزي ګودام المارۍ"],
        "قفسه لوازم جانبی" => ["en" => "Accessories Shelf", "da" => "قفسه لوازم جانبی", "ps" => "د فرعي توکو المارۍ"],
        "ویترین" => ["en" => "Showcase", "da" => "ویترین", "ps" => "ویترین"],
        "نامشخص" => ["en" => "Unassigned", "da" => "نامشخص", "ps" => "ناڅرګند"]
    ];
    if (isset($map[$l][$lang])) return $map[$l][$lang];
    return $l;
}

function display_qty_unit($qty, $unit=null) {
    if ($unit && $unit !== 'unit') return format_qty($qty) . ' ' . sanitize($unit);
    $lang = get_current_lang();
    $num = format_qty($qty);
    if ($lang === "en") return $num . ((float)$qty===1.0?" unit":" units");
    if ($lang === "ps") return $num . " دانې";
    return $num . " عدد";
}

function display_item_count($count) {
    $lang = get_current_lang();
    $num = ($lang === "en") ? (string)$count : to_persian_num($count);
    if ($lang === "en") return $num . " items";
    if ($lang === "ps") return $num . " قلمه";
    return $num . " قلم";
}

function display_debt_status($status) {
    $lang = get_current_lang();
    $s = trim((string)$status);
    if ($s === 'تسویه' || strtolower($s) === 'settled') {
        if ($lang === 'en') return 'Settled';
        if ($lang === 'ps') return 'تصفیه شوی';
        return 'تسویه شده';
    }
    if ($lang === 'en') return 'Active (Open)';
    if ($lang === 'ps') return 'خلاص (پاتې)';
    return 'جاری (باز)';
}

function display_payment_reason($reason) {
    $lang = get_current_lang();
    $r = trim((string)$reason);
    $map = [
        "وصولی نسیه فروش کالا" => ["en" => "Sales Payment", "da" => "وصولی فروش کالا", "ps" => "د پلور تادیه"],
        "وصولی نسیه فروش موبایل" => ["en" => "Mobile Sale Installment", "da" => "وصولی نسیه فروش موبایل", "ps" => "د موبایل پلور قسط"],
        "وصولی نسیه لوازم جانبی" => ["en" => "Accessories Sale Installment", "da" => "وصولی نسیه لوازم جانبی", "ps" => "د لوازمو پلور قسط"],
        "تسویه حساب بل دکان" => ["en" => "Shop Invoice Settlement", "da" => "تسویه حساب بل دکان", "ps" => "د هټۍ د بل تصفیه"],
        "برگشت قرض نقدی / دستی" => ["en" => "Cash Loan Return", "da" => "برگشت قرض نقدی / دستی", "ps" => "د نغدي پور بېرته ورکړه"],
        "بازپرداخت بدهی دکان به عمده‌فروش" => ["en" => "Payment to Wholesaler", "da" => "بازپرداخت بدهی دکان به عمده‌فروش", "ps" => "عمده پلورونکي ته د پور تادیه"],
        "پیش‌پرداخت اولیه هنگام ثبت حساب" => ["en" => "Initial Down Payment", "da" => "پیش‌پرداخت اولیه هنگام ثبت حساب", "ps" => "لومړنۍ مخکې تادیه"],
        "پیش‌پرداخت نقد اولیه" => ["en" => "Initial Cash Down Payment", "da" => "پیش‌پرداخت نقد اولیه", "ps" => "لومړنۍ نغده مخکې تادیه"],
        "سایر" => ["en" => "Other", "da" => "سایر", "ps" => "نور"],
        "وصولی قرضه" => ["en" => "Debt Collection", "da" => "وصولی قرضه", "ps" => "د پور وصول"]
    ];
    if (isset($map[$r][$lang])) return $map[$r][$lang];
    if ($lang === 'en') {
        if (str_starts_with($r, "پیش پرداخت نقد بل ")) return "Cash Down Payment for Inv " . substr($r, strlen("پیش پرداخت نقد بل "));
        if (str_starts_with($r, "فروش قرضه - ")) return "Credit Sale - " . substr($r, strlen("فروش قرضه - "));
        if (str_starts_with($r, "فروش قسطی - ")) return "Installment Sale - " . substr($r, strlen("فروش قسطی - "));
    }
    return $r;
}

function display_partner_role($role) {
    $lang = get_current_lang();
    $r = trim((string)$role);
    $map = [
        "سرمایه‌گذار اصلی" => ["en" => "Lead Investor", "da" => "سرمایه‌گذار اصلی", "ps" => "اصلي پانګوال"],
        "شریک کاری" => ["en" => "Working Partner", "da" => "شریک کاری", "ps" => "کاري شریک"],
        "شریک مالی" => ["en" => "Financial Partner", "da" => "شریک مالی", "ps" => "مالي شریک"],
        "شریک ۵۰/۵۰" => ["en" => "50/50 Partner", "da" => "شریک ۵۰/۵۰", "ps" => "۵۰/۵۰ شریک"],
        "مضارب" => ["en" => "Mudarib (Manager)", "da" => "مضارب", "ps" => "مضارب"]
    ];
    if (isset($map[$r][$lang])) return $map[$r][$lang];
    return $r;
}

function display_mudaraba_status($status) {
    $lang = get_current_lang();
    $s = trim((string)$status);
    if ($s === 'active' || $s === 'فعال') {
        if ($lang === 'en') return 'Active Contract';
        if ($lang === 'ps') return 'فعال قرارداد';
        return 'قرارداد فعال';
    }
    if ($s === 'settled' || $s === 'تسویه' || $s === 'completed') {
        if ($lang === 'en') return 'Fully Settled';
        if ($lang === 'ps') return 'بشپړ تصفیه شوی';
        return 'تسویه کامل';
    }
    if ($s === 'terminated' || $s === 'فسخ') {
        if ($lang === 'en') return 'Terminated';
        if ($lang === 'ps') return 'فسخه شوی';
        return 'فسخ شده';
    }
    return $s;
}


/** UI vocabulary only: never rewrite stored names, notes or accounting values. */
function dari_terms(string $text): string {
    return strtr($text,['نام کاربری'=>'نام استفاده‌کننده','کاربران'=>'استفاده‌کننده‌ها','کاربر'=>'استفاده‌کننده','فروشگاه'=>'دکان','گزارش'=>'راپور','کالاها'=>'اجناس','کالا'=>'جنس','هزینه‌ها'=>'مصارف','هزینه'=>'مصرف','سود'=>'مفاد','فرم'=>'فورم','سامانه'=>'سیستم','انبار'=>'گدام','کنترل'=>'کنترول','اطلاعات'=>'معلومات','مجوز'=>'اجازه','پشتیبان‌گیری'=>'بکاپ','ویرایش'=>'تغییر','پوشه'=>'فولدر','شناسه'=>'شماره','بازنشانی'=>'ازنو تنظیم','بازیابی'=>'بازیابی']);
}
function __($key,$fallback='') {
    $text=i18n_lookup($key,$fallback);
    return get_current_lang()==='da'?dari_terms($text):$text;
}
