<?php
/** Extension of the existing role + feature-lock system, not a parallel authorization store. */
function access_module_features() {
    $labels=[
        'dashboard_module'=>['Dashboard','داشبورد','ډشبورډ'],
        'sales_module'=>['Sales','فروش','پلور'],'purchases_module'=>['Purchases','خرید','پېرود'],
        'inventory_module'=>['Inventory','گدام','موجودي'],'reports_module'=>['Reports','گزارش‌ها','راپورونه'],
        'settings_module'=>['Settings','تنظیمات','تنظیمات'],'invoices_module'=>['Invoices','بل‌ها','بلونه'],
        'debts_module'=>['Debts and settlements','قرضه و تسویه','پورونه او تصفیه'],
        'customers_access'=>['Customers','مشتریان','پېرودونکي'],'partners_module'=>['Partners','شرکاء','شریکان'],
        'mudaraba_module'=>['Mudaraba','مضاربت','مضاربت'],'categories_module'=>['Categories','دسته‌بندی‌ها','ډلې'],
        'warehouse_module'=>['Warehouse','نمای گدام','ګودام'],'capital_module'=>['Capital','سرمایه','پانګه'],
        'losses_module'=>['Losses','خسارات','زیانونه'],'about_module'=>['About','درباره','په اړه']
    ];
    $features=[];
    foreach($labels as $key=>$l)$features[$key]=['key'=>$key,'title_en'=>$l[0],'title_fa'=>$l[1],'title_ps'=>$l[2],'desc_en'=>'Module access; existing financial permissions still apply.','desc_fa'=>'دسترسی ماژول؛ محدودیت‌های قبلی و نقش کاربر همچنان معتبرند.','default'=>0,'module'=>true];
    foreach(['liquidity_report'=>['Liquidity report','گزارش نقدینگی'],'shop_expenses_entry'=>['Expense entry','ثبت مصارف']] as $key=>$label)$features[$key]=['key'=>$key,'title_en'=>$label[0],'title_fa'=>$label[1],'desc_en'=>'Existing detailed feature gate.','desc_fa'=>'کنترل جزئی موجود؛ قواعد قبلی حفظ می‌شوند.','default'=>1,'module'=>false];
    return $features;
}
function access_profile($id) {
    global $pdo;$q=$pdo->prepare('SELECT * FROM user_access WHERE user_id=?');$q->execute([(int)$id]);
    return $q->fetch()?:['user_id'=>(int)$id,'is_locked'=>0,'must_change_password'=>0,'subscription_active'=>1,'subscription_expires_at'=>null,'ai_daily_limit'=>null,'ai_monthly_limit'=>null,'ai_day'=>'','ai_month'=>'','ai_day_used'=>0,'ai_month_used'=>0,'ai_reset_at'=>null];
}
function access_ensure_profile($id) {
    global $pdo;
    $sql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE INTO user_access (user_id) VALUES (?)':'INSERT IGNORE INTO user_access (user_id) VALUES (?)';
    $pdo->prepare($sql)->execute([(int)$id]);
}
function access_super_role($role){return in_array($role,['superadmin','super_admin'],true);}
function access_account_available(array $user) {
    $p=access_profile($user['id']);
    if($user['status']!=='active'||(int)$p['is_locked'])return false;
    // Administrators of Office 2 must be able to renew subscriptions from Office 2.
    if(access_super_role($user['role']))return true;
    // A locked or expired shop blocks its own accounts, never the central Office 2 accounts.
    $shopId=(int)($user['shop_id']??0);
    if($shopId>0&&function_exists('shop_find')){$shop=shop_find($shopId);if($shop&&!shop_is_operational($shop))return false;}
    return (int)$p['subscription_active']===1&&(!$p['subscription_expires_at']||strcmp($p['subscription_expires_at'],date('Y-m-d H:i:s'))>0);
}
/**
 * Per-shop feature state: 1 = explicitly allowed, 0 = denied, null = inherit.
 * Loaded once per request because every page asks about several features.
 */
function shop_feature_state($feature,$shopId=0) {
    global $pdo;
    static $cache=[];
    if(!($pdo instanceof PDO)||!function_exists('shop_current_id'))return null;
    $shopId=$shopId>0?(int)$shopId:(int)shop_current_id();
    if($shopId<=0)return null;
    if(!isset($cache[$shopId])){
        $cache[$shopId]=[];
        try{
            foreach($pdo->query('SELECT shop_id,feature_key,allowed FROM shop_feature_access') as $row){
                $cache[(int)$row['shop_id']][(string)$row['feature_key']]=(int)$row['allowed'];
            }
        }catch(Throwable $e){}
    }
    return array_key_exists($feature,$cache[$shopId])?$cache[$shopId][$feature]:null;
}
function access_user_denied($feature,$id=null) {
    global $pdo;$id=$id??(int)($_SESSION['user_id']??0);if(!$id)return false;
    $q=$pdo->prepare('SELECT allowed FROM user_feature_access WHERE user_id=? AND feature_key=?');$q->execute([$id,$feature]);$value=$q->fetchColumn();
    return $value!==false&&(int)$value===0;
}
function access_route_features($script=null,$method=null) {
    $script=$script??($_SERVER['SCRIPT_NAME']??'');$method=$method??($_SERVER['REQUEST_METHOD']??'GET');
    if(str_contains($script,'/admin/'))return [];
    if(str_contains($script,'/print/'))return basename($script)==='invoice.php'?['sales_module','invoices_module']:['mudaraba_module'];
    $map=[
        'index.php'=>['dashboard_module'],'sales.php'=>['sales_module'],
        'invoices.php'=>['sales_module','invoices_module'],'purchases.php'=>['purchases_module'],
        'stock.php'=>['inventory_module'],'stock_add.php'=>['purchases_module','inventory_module'],
        'stock_edit.php'=>['inventory_module'],'reports.php'=>['reports_module'],
        'expenses.php'=>['expenses_module'],'settings.php'=>['settings_module'],
        'backup.php'=>['backup_module'],'ai_advisor.php'=>['ai_advisor'],
        'debts.php'=>['debts_module'],'customers.php'=>['customers_access'],
        'partners.php'=>['partners_module'],'mudaraba.php'=>['mudaraba_module'],
        'categories.php'=>['categories_module'],'warehouse.php'=>['warehouse_module'],'barcode_guide.php'=>['inventory_module'],
        'capital.php'=>['capital_module'],'losses.php'=>['losses_module'],'about.php'=>['about_module'],
        'change_theme.php'=>['settings_module']
    ];
    $keys=$map[basename($script)]??[];
    if(basename($script)==='stock_edit.php'&&$method==='POST')$keys[]='purchases_module';
    if(basename($script)==='settings.php'&&($_GET['tab']??'')==='backup')$keys[]='backup_module';
    return $keys;
}
function access_route_denied() {
    $modules=access_module_features();
    foreach(access_route_features() as $key){
        if(access_user_denied($key))return true;
        // Preserve existing read-only license previews. New module controls protect
        // the complete route; existing license gates also protect every POST/download.
        if(is_feature_locked($key)&&(isset($modules[$key])||($_SERVER['REQUEST_METHOD']??'GET')==='POST'||isset($_GET['download'])))return true;
    }
    return false;
}
function access_json_request(){return defined('OFFICE2_API')||strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest'||str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');}
function access_deny($status,$code,$message,$url=null) {
    http_response_code($status);header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
    if(access_json_request()){
        header('Content-Type: application/json; charset=utf-8');$out=['success'=>false,'error'=>$code,'message'=>$message];if($url)$out['redirect']=$url;echo json_encode($out,JSON_UNESCAPED_UNICODE);
    }else{
        header('Content-Type: text/html; charset=utf-8');echo '<!doctype html><html lang="'.sanitize(get_current_lang()).'" dir="'.get_current_dir().'"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Nik Smart Count - Warehousing</title><body><main><h1>'.sanitize($message).'</h1><p><a href="'.sanitize(BASE_URL.'index.php').'">'.sanitize(__t('Back','بازگشت','بېرته')).'</a></p></main></body></html>';
    }
    exit;
}
function access_require_password_change() {
    if(!(int)access_profile((int)$_SESSION['user_id'])['must_change_password'])return;
    if(in_array(basename($_SERVER['SCRIPT_NAME']??''),['change_password.php','logout.php'],true))return;
    $url=BASE_URL.'modules/change_password.php?next='.rawurlencode(security_safe_return($_SERVER['REQUEST_URI']??BASE_URL.'index.php'));
    if(access_json_request())access_deny(403,'password_change_required',__t('Change your temporary password before continuing.','پیش از ادامه، رمز موقت خود را تغییر دهید.','له دوام مخکې خپل لنډمهالی رمز بدل کړئ.'),$url);
    redirect($url);
}
/** Atomic server-side quota reservation. No prompt, password or financial amount is logged. */
function access_ai_consume($requestKey,$currency) {
    global $pdo;$user=auth_user();
    if(!$user||!access_account_available($user)||(int)access_profile($user['id'])['must_change_password']||!empty($_SESSION['screen_locked']))throw new RuntimeException('Authentication required.',401);
    if(is_feature_locked('ai_advisor'))throw new RuntimeException(__t('AI access is disabled.','دسترسی مشاور غیرفعال است.','د سلاکار لاسرسی بند دی.'),403);
    if(!is_string($requestKey)||!preg_match('/^[a-f0-9]{32}$/D',$requestKey)||!in_array($currency,['AFN','USD','AED','EUR'],true))throw new InvalidArgumentException(__t('Invalid request. Refresh the form.','درخواست نامعتبر است؛ فرم را تازه کنید.','غوښتنه ناسمه ده؛ فورمه تازه کړئ.'));
    $day=date('Y-m-d');$month=date('Y-m');
    $pdo->beginTransaction();
    try{
        access_ensure_profile($user['id']);
        $q=$pdo->prepare('SELECT id FROM ai_usage_events WHERE user_id=? AND request_key=?');$q->execute([$user['id'],$requestKey]);
        if($q->fetchColumn())throw new RuntimeException(__t('This request was already processed. Start a new request.','این درخواست قبلاً پردازش شده است؛ درخواست تازه بفرستید.','دا غوښتنه مخکې پروسس شوې؛ نوې غوښتنه وکړئ.'),409);
        $q=$pdo->prepare("UPDATE user_access SET ai_day_used=CASE WHEN ai_day=? THEN ai_day_used+1 ELSE 1 END, ai_month_used=CASE WHEN ai_month=? THEN ai_month_used+1 ELSE 1 END, ai_day=?,ai_month=?,updated_at=CURRENT_TIMESTAMP
            WHERE user_id=? AND is_locked=0 AND must_change_password=0
            AND (ai_daily_limit IS NULL OR (CASE WHEN ai_day=? THEN ai_day_used ELSE 0 END)<ai_daily_limit)
            AND (ai_monthly_limit IS NULL OR (CASE WHEN ai_month=? THEN ai_month_used ELSE 0 END)<ai_monthly_limit)
            AND NOT EXISTS(SELECT 1 FROM user_feature_access WHERE user_id=? AND feature_key='ai_advisor' AND allowed=0)");
        $q->execute([$day,$month,$day,$month,$user['id'],$day,$month,$user['id']]);
        if($q->rowCount()!==1)throw new RuntimeException(__t('The daily or monthly usage allowance has been reached, or access is disabled.','سهمیهٔ روزانه یا ماهانه تمام شده، یا دسترسی غیرفعال است.','ورځنۍ یا میاشتنۍ ونډه پای ته رسېدلې یا لاسرسی بند دی.'),429);
        $pdo->prepare('INSERT INTO ai_usage_events (user_id,request_key,currency_code,created_at) VALUES (?,?,?,?)')->execute([$user['id'],$requestKey,$currency,gmdate('Y-m-d H:i:s')]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
