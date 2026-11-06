<?php
/** Single presentation registry. Server authorization remains in domain_enforce_route(). */
function ui_service_groups(): array {
    static $groups=null;if($groups!==null)return $groups;
    $item=function(string $en,string $da,string $feature,string $file,string $icon): array{return compact('en','da','feature','file','icon');};
    $groups=[
      ['id'=>'overview','en'=>'Overview','da'=>'نمای عمومی','icon'=>'fa-table-cells-large','feature'=>null,'items'=>[
        $item('Businesses & branches','تجارت‌ها و شعبه‌ها','branches','portfolio.php','fa-code-branch'),
      ]],
      ['id'=>'dashboard','en'=>'Dashboard','da'=>'داشبورد','icon'=>'fa-house-chimney','feature'=>'dashboard','items'=>[
        $item('Dashboard home','صفحهٔ اصلی داشبورد','dashboard','../index.php','fa-house'),
        $item('Inventory value','ارزش موجودی','dashboard.stock_value','../index.php#dashboard-stock-value','fa-boxes-stacked'),
        $item('Mudaraba account','حساب مضاربت','dashboard.mudaraba','../index.php#dashboard-mudaraba','fa-handshake-angle'),
        $item('Shop capital','سرمایهٔ دکان','dashboard.capital','../index.php#dashboard-capital','fa-vault'),
        $item('Today’s sales','فروشات امروز','dashboard.today_sales','../index.php#dashboard-today-sales','fa-cart-shopping'),
        $item('Today’s net profit','سود خالص امروز','dashboard.today_profit','../index.php#dashboard-today-profit','fa-arrow-trend-up'),
        $item('Operating expenses','مصارف عملیاتی','dashboard.expenses','../index.php#dashboard-expenses','fa-receipt'),
        $item('Overall net profit','سود خالص عمومی','dashboard.overall_profit','../index.php#dashboard-overall-profit','fa-chart-line'),
        $item('Outstanding receivables','طلب‌های باقی‌مانده','dashboard.debts','../index.php#dashboard-debts','fa-hand-holding-dollar'),
        $item('Quick operations','عملیات مشترک','dashboard.quick_actions','../index.php#quick-actions','fa-bolt'),
        $item('Mudaraba live panel','پنل زندهٔ مضاربت','dashboard.mudaraba_panel','../index.php#mudaraba-panel','fa-handshake'),
        $item('Financial summary','خلاصهٔ مالی','dashboard.cash_flow','../index.php#cash-flow','fa-chart-simple'),
      ]],
      ['id'=>'sales','en'=>'Sales','da'=>'فروش','icon'=>'fa-cart-shopping','feature'=>null,'items'=>[
        $item('Sell product · new invoice','فروش جنس · بل جدید','sales','sales.php','fa-cash-register'),
        $item('Sales invoices','بل‌های فروش','sales.invoices','invoices.php','fa-file-invoice-dollar'),
        $item('Sales returns','برگشت فروش','sales','sales_returns.php','fa-rotate-left'),
      ]],
      ['id'=>'purchases','en'=>'Purchases & suppliers','da'=>'خرید و عرضه‌کنندگان','icon'=>'fa-bag-shopping','feature'=>null,'items'=>[
        $item('Purchases','خریدها','purchases','purchases.php','fa-bag-shopping'),
        $item('New purchase / add item','خرید جدید / افزودن جنس','purchases','stock_add.php','fa-plus'),
        $item('Purchase returns','برگشت خرید','purchases','purchase_returns.php','fa-rotate-left'),
        $item('Suppliers','عرضه‌کنندگان','suppliers','suppliers.php','fa-truck-field'),
      ]],
      ['id'=>'inventory','en'=>'Inventory','da'=>'گدام و موجودی','icon'=>'fa-boxes-stacked','feature'=>null,'items'=>[
        $item('Inventory list & management','لست و مدیریت موجودی','inventory','stock.php','fa-box-open'),
        $item('Locations','موقعیت اجناس','inventory.locations','warehouse.php','fa-location-dot'),
        $item('Barcode labels','لیبل بارکُد','inventory.labels','barcode_guide.php','fa-barcode'),
        $item('Stock movements & transfers','گردش و انتقال جنس','inventory','inventory_documents.php','fa-arrow-right-arrow-left'),
      ]],
      ['id'=>'relations','en'=>'Customers & accounts','da'=>'مشتریان و حساب‌ها','icon'=>'fa-users','feature'=>null,'items'=>[
        $item('Customers','مشتریان','customers','customers.php','fa-users'),
        $item('Receivables & payables','طلب‌ها و بدهی‌ها','debts','debts.php','fa-file-invoice'),
      ]],
      ['id'=>'operations','en'=>'Expenses & losses','da'=>'مصارف و ضایعات','icon'=>'fa-receipt','feature'=>null,'items'=>[
        $item('Operating expenses','مصارف عملیاتی','expenses','expenses.php','fa-receipt'),
        $item('Damage & losses','ضایعات اجناس','losses','losses.php','fa-house-crack'),
      ]],
      ['id'=>'finance','en'=>'Finance & reports','da'=>'مالی و راپورها','icon'=>'fa-chart-pie','feature'=>null,'items'=>[
        $item('Cash & bank','صندوق و بانک','treasury','treasury.php','fa-building-columns'),
        $item('Currency exchange','تبدیل ارز','treasury.exchange','fx.php','fa-money-bill-transfer'),
        $item('Accounting books','دفاتر حسابداری','accounting','accounting.php','fa-book-open'),
        $item('Corrections','اسناد اصلاحی','accounting.correct','corrections.php','fa-file-pen'),
        $item('Capital','سرمایه','capital','capital.php','fa-vault'),
        $item('Reports','راپورها','reports','reports.php','fa-chart-pie'),
        $item('Daily closing','بستن حساب روز','closings','closing.php','fa-calendar-check'),
      ]],
      ['id'=>'partners','en'=>'Partners & Mudaraba','da'=>'شرکا و مضاربت','icon'=>'fa-handshake','feature'=>null,'items'=>[
        $item('Partners','شرکا','partners','partners.php','fa-user-group'),
        $item('Mudaraba','مضاربت','mudaraba','mudaraba.php','fa-handshake-angle'),
        $item('Partner settlement','تسویهٔ شریک','mudaraba.settle','partnership_settlement.php','fa-scale-balanced'),
      ]],
      ['id'=>'workspace','en'=>'Workspace & administration','da'=>'فضای کاری و مدیریت','icon'=>'fa-sliders','feature'=>null,'items'=>[
        $item('Business advisor','مشاور تجارت','ai','ai_advisor.php','fa-wand-magic-sparkles'),
        $item('Activity history','تاریخچهٔ فعالیت','audit','activity.php','fa-clock-rotate-left'),
        $item('Staff & permissions','کارمندان و صلاحیت‌ها','staff','management.php','fa-user-shield'),
        $item('Settings','تنظیمات','settings','settings.php','fa-gear'),
        $item('About','دربارهٔ نرم‌افزار','about','about.php','fa-circle-info'),
      ]],
    ];return $groups;
}
function ui_service_find(string $feature): ?array {
    if(!preg_match('/^[a-z]+(?:\.[a-z_]+)?$/D',$feature)||!isset(domain_catalog()[$feature]))return null;
    foreach(ui_service_groups() as $group)foreach($group['items'] as $item)if($item['feature']===$feature)return $item;return null;
}
function ui_service_url(string $feature): string {return BASE_URL.'modules/service.php?feature='.rawurlencode($feature);}
function ui_item_url(array $item,string $mode): string {if($mode==='locked')return ui_service_url($item['feature']);return str_starts_with($item['file'],'../')?BASE_URL.substr($item['file'],3):BASE_URL.'modules/'.$item['file'];}
function ui_render_dashboard_card(string $id,string $feature,string $title,string $value,string $description,string $icon,string $tone='primary'): void {
    $mode=domain_visibility($feature);if($mode==='hidden')return;
    if($mode==='locked'){
        echo '<a id="'.sanitize($id).'" class="stat-card dashboard-service-card is-locked" href="'.sanitize(ui_service_url($feature)).'"><div class="stat-header"><span class="stat-title">'.sanitize($title).'</span><span class="stat-icon"><i class="fa-solid fa-lock" aria-hidden="true"></i></span></div><strong class="dashboard-lock-title">'.__t('Service locked','این خدمت قفل است','دا خدمت بند دی').'</strong><span class="stat-desc">'.__t('Open activation and contact options','گزینه‌های فعال‌سازی و تماس را باز کنید','د فعالولو او اړیکې اختیارونه پرانیزئ').' <i class="fa-solid fa-arrow-up-right-from-square"></i></span></a>';return;
    }
    echo '<article id="'.sanitize($id).'" class="stat-card dashboard-service-card tone-'.sanitize($tone).'"><div class="stat-header"><span class="stat-title">'.sanitize($title).'</span><span class="stat-icon"><i class="fa-solid '.sanitize($icon).'" aria-hidden="true"></i></span></div><div class="stat-value">'.$value.'</div><div class="stat-desc">'.$description.'</div></article>';
}
function ui_render_dashboard_action(string $feature,string $file,string $label,string $icon): void {
    $mode=domain_visibility($feature);if($mode==='hidden')return;$url=$mode==='locked'?ui_service_url($feature):BASE_URL.'modules/'.$file;
    echo '<a class="dashboard-action tone-'.($mode==='locked'?'locked':'enabled').'" href="'.sanitize($url).'"><span class="dashboard-action-icon"><i class="fa-solid '.($mode==='locked'?'fa-lock':$icon).'" aria-hidden="true"></i></span><span>'.sanitize($label).'</span><i class="fa-solid fa-arrow-up-right-from-square dashboard-action-arrow" aria-hidden="true"></i></a>';
}
function ui_support_contacts(): array {
    $phone='+93783788278';$whatsapp='93783788278';$email=runtime_value('NIKXOR_SUPPORT_EMAIL')?:get_setting('developer_email','');
    return ['phone'=>$phone,'whatsapp'=>$whatsapp,'email'=>filter_var($email,FILTER_VALIDATE_EMAIL)?$email:''];
}
