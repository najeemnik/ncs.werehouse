<?php
/** Authorization always runs on the server, including direct URLs and POST requests. */
function domain_is_central(?array $u): bool {return $u && in_array($u['role'],['superadmin','super_admin'],true) && empty($u['shop_id']);}
function domain_branch(int $id): ?array {global $pdo;$q=$pdo->prepare('SELECT s.*,b.owner_user_id AS business_owner,b.status AS business_status,b.subscription_active AS business_subscription_active,b.subscription_expires_at AS business_subscription_expires_at FROM shops s JOIN businesses b ON b.id=s.business_id WHERE s.id=?');$q->execute([$id]);return $q->fetch()?:null;}
function domain_business_operational(array $business): bool {
    if(($business['status']??'active')!=='active'||(int)($business['subscription_active']??1)!==1)return false;
    $expires=trim((string)($business['subscription_expires_at']??''));
    return $expires===''||strcmp($expires,date('Y-m-d H:i:s'))>0;
}
function domain_branch_allowed(array $u,int $id): bool {
    global $pdo;$s=domain_branch($id);if(!$s)return false;
    if(domain_is_central($u))return true;
    if(($s['business_status']??'')!=='active'||!(int)($s['business_subscription_active']??1)||(!empty($s['business_subscription_expires_at'])&&strcmp($s['business_subscription_expires_at'],date('Y-m-d H:i:s'))<=0))return false;
    if((int)$s['business_owner']>0){$q=$pdo->prepare('SELECT u.status,a.is_locked,a.subscription_active,a.subscription_expires_at FROM users u LEFT JOIN user_access a ON a.user_id=u.id WHERE u.id=?');$q->execute([$s['business_owner']]);$owner=$q->fetch();if(!$owner||$owner['status']!=='active'||(int)$owner['is_locked']||($owner['subscription_active']!==null&&!(int)$owner['subscription_active'])||($owner['subscription_expires_at']&&$owner['subscription_expires_at']<date('Y-m-d H:i:s')))return false;}
    if(!shop_is_operational($s)||$s['business_status']!=='active')return false;
    if((int)$s['business_owner']===(int)$u['id'])return true;
    $q=$pdo->prepare('SELECT 1 FROM branch_memberships m JOIN shops home ON home.id=? WHERE m.user_id=? AND m.shop_id=? AND home.business_id=?');
    $q->execute([(int)($u['shop_id']??0),$u['id'],$id,$s['business_id']]);return (bool)$q->fetchColumn();
}
function domain_branches(array $u): array {global $pdo;return array_values(array_filter($pdo->query('SELECT id,name,city,business_id FROM shops ORDER BY name,id')->fetchAll(),fn($s)=>domain_branch_allowed($u,(int)$s['id'])));}
function domain_business_id(): int {$s=domain_branch(shop_id());return (int)($s['business_id']??0);}
function domain_owner(array $u,int $business): bool {global $pdo;$q=$pdo->prepare('SELECT owner_user_id FROM businesses WHERE id=?');$q->execute([$business]);return (int)$q->fetchColumn()===(int)$u['id'];}
function domain_catalog(): array {
    $modules=['treasury'=>['Cash & bank','صندوق و بانک'],'accounting'=>['Accounting books','دفاتر حسابداری'],'suppliers'=>['Suppliers','عرضه‌کنندگان'],'dashboard'=>['Dashboard','داشبورد'],'sales'=>['Sales','فروش'],'purchases'=>['Purchases','خرید'],'inventory'=>['Inventory','گدام و موجودی'],'customers'=>['Customers','مشتریان'],'debts'=>['Receivables & payables','طلب‌ها و بدهی‌ها'],'expenses'=>['Operating expenses','مصارف عملیاتی'],'losses'=>['Inventory damage','ضایعات اجناس'],'partners'=>['Partners','شرکا'],'mudaraba'=>['Mudaraba contracts','قراردادهای مضاربت'],'capital'=>['Capital','سرمایه'],'reports'=>['Reports','راپورها'],'settings'=>['Settings','تنظیمات'],'backup'=>['Backup & restore','بکاپ و بازیابی'],'ai'=>['Business advisor','مشاور تجارت'],'staff'=>['Staff management','مدیریت کارمندان'],'closings'=>['Daily closing','بستن حساب روز'],'audit'=>['Activity history','تاریخچهٔ فعالیت'],'branches'=>['Branches overview','نمای شعبه‌ها'],'about'=>['About','دربارهٔ نرم‌افزار']];
    $out=[];foreach($modules as $k=>$v){$out[$k]=$v;foreach(['view'=>['View','دیدن'],'create'=>['Create','ساختن'],'edit'=>['Edit','ویرایش'],'delete'=>['Delete','حذف'],'approve'=>['Approve','تأیید'],'print'=>['Print','چاپ'],'export'=>['Export','خروجی']] as $a=>$l)$out[$k.'.'.$a]=[$v[0].' · '.$l[0],$v[1].' · '.$l[1]];}
    foreach(['mudaraba.release_stock'=>['Release settled partner goods','آزادکردن جنس شریک پس از تسویه'],'mudaraba.settle'=>['Partner settlement accounts','حساب تسویهٔ شریک'],'mudaraba.review_returns'=>['Review distributed partner returns','تأیید برگشت پس از پرداخت شریک'],'accounting.correct'=>['Correct mistaken documents','اصلاح سند اشتباه'],'treasury.exchange'=>['Documented currency exchange','تبدیل ارز با سند'],'treasury.fx_settle'=>['Foreign currency settlement','تسویه با ارز متفاوت'],'inventory.locations'=>['Warehouse locations','موقعیت اجناس'],'inventory.labels'=>['Barcode labels','لیبل بارکُد'],'sales.invoices'=>['Sales invoices','بل‌های فروش'],'settings.branding'=>['Business branding','نشان تجارت'],'settings.currency'=>['Currencies','اسعار'],'settings.mfa'=>['Google Authenticator & trusted devices','تأییدکنندهٔ گوگل و دستگاه‌های مورد اعتماد'],'sales.profit_preview'=>['Sale profit preview','نمای مفاد فروش'],'inventory.low_stock_alert'=>['Low stock alerts','خبر کمبود جنس'],'inventory.low_stock_list'=>['Low stock list','لست اجناس کمبود'],'reports.top_selling'=>['Top selling report','راپور پرفروش‌ترین اجناس'],'reports.cash_debt'=>['Cash and debt report','راپور پول و قرضه'],'backup.restore'=>['Restore backup','بازیابی بکاپ'],'ai.ask'=>['Ask the advisor','پرسش از مشاور']] as $k=>$v)$out[$k]=$v;
    foreach(['dashboard.stock_value'=>['Dashboard · Inventory value','داشبورد · ارزش موجودی'],'dashboard.mudaraba'=>['Dashboard · Mudaraba account','داشبورد · حساب مضاربت'],'dashboard.capital'=>['Dashboard · Shop capital','داشبورد · سرمایهٔ دکان'],'dashboard.today_sales'=>['Dashboard · Today’s sales','داشبورد · فروشات امروز'],'dashboard.today_profit'=>['Dashboard · Today’s net profit','داشبورد · سود خالص امروز'],'dashboard.expenses'=>['Dashboard · Operating expenses','داشبورد · مصارف عملیاتی'],'dashboard.overall_profit'=>['Dashboard · Overall net profit','داشبورد · سود خالص عمومی'],'dashboard.debts'=>['Dashboard · Outstanding receivables','داشبورد · طلب‌های باقی‌مانده'],'dashboard.quick_actions'=>['Dashboard · Quick operations','داشبورد · عملیات مشترک'],'dashboard.mudaraba_panel'=>['Dashboard · Mudaraba live panel','داشبورد · پنل زندهٔ مضاربت'],'dashboard.cash_flow'=>['Dashboard · Financial summary','داشبورد · خلاصهٔ مالی']] as $k=>$v)$out[$k]=$v;
    return $out;
}
function domain_label(string $key): string {$c=domain_catalog();$v=$c[$key]??[$key,$key];return __t($v[0],$v[1],$v[0]);}
function domain_policy(string $type,int $id,string $feature): ?array {global $pdo;$key=$type.':'.$id.':'.$feature;if(array_key_exists($key,$GLOBALS['_domain_policy_cache']??[]))return $GLOBALS['_domain_policy_cache'][$key];$q=$pdo->prepare('SELECT * FROM service_policies WHERE scope_type=? AND scope_id=? AND feature=?');$q->execute([$type,$id,$feature]);return $GLOBALS['_domain_policy_cache'][$key]=$q->fetch()?:null;}
function domain_policy_chain(array $u,int $shop): array {
    $s=domain_branch($shop);if(!$s)return [];
    $chain=[['system',0],['business',(int)$s['business_id']],['branch',$shop]];
    if((int)$s['business_owner']!==(int)$u['id']){if((int)$s['business_owner']>0)$chain[]=['user',(int)$s['business_owner']];$chain[]=['owner_user',(int)$u['id']];}
    $chain[]=['user',(int)$u['id']];return $chain;
}
function domain_can(string $feature,?array $u=null,?int $shop=null): bool {
    $u=$u??auth_user();$shop=$shop??shop_id();if(!$u||!isset(domain_catalog()[$feature]))return false;
    if(domain_is_central($u))return true;
    if(!domain_branch_allowed($u,$shop))return false;
    $s=domain_branch($shop);$owner=(int)$s['business_owner']===(int)$u['id'];
    $keys=[$feature];if(str_contains($feature,'.'))$keys[]=explode('.',$feature)[0];
    foreach(domain_policy_chain($u,$shop) as [$type,$id])foreach($keys as $k){
        $p=domain_policy($type,$id,$k);
        // Owner approval AND central approval are mandatory for employee AI.
        $default=$owner || !in_array(explode('.',$k)[0],['treasury','accounting','suppliers','ai','staff','backup','audit','settings','capital','partners','mudaraba'],true);
        if(!$owner && preg_match('/\.(delete|approve|export)$/D',$k))$default=false;
        if(!$owner && $k==='branches.create')$default=false;
        if(in_array($type,['system','business','branch'],true)||($type==='user'&&$id!==(int)$u['id']))$default=true;
        if($type==='user' && $id===(int)$u['id'] && explode('.',$k)[0]==='ai' && !$owner)$default=false;
        if($type==='owner_user' && explode('.',$k)[0]==='ai')$default=false;
        if($p?!(int)$p['enabled']:!$default)return false;
    }
    return true;
}
function domain_visibility(string $feature): string {
    if(domain_can($feature))return 'enabled';$u=auth_user();$result='locked';
    foreach(domain_policy_chain($u,shop_id()) as [$type,$id])foreach([$feature,explode('.',$feature)[0]] as $k){$p=domain_policy($type,$id,$k);if($p && !(int)$p['enabled'] && $p['visibility']==='hidden')return 'hidden';}
    return $result;
}
function domain_require(string $feature): void {if(!domain_can($feature)){if(shop_id()<1&&!domain_is_central(auth_user()))access_deny(403,'branch_assignment_required',__t('Your account has no assigned business branch. Ask the administrator to assign one in Office 2 → Users.','برای حساب شما شعبه تعیین نشده است؛ مدیر باید از دفتر دو ← کاربران، تجارت و شعبه را تعیین کند.','ستاسو حساب څانګه نه لري؛ مدیر باید یې وټاکي.'));access_deny(403,'permission_denied',__t('You do not have permission for this action.','اجازهٔ انجام این کار را ندارید.','د دې کار اجازه نه لرئ.'));}}
function domain_route(): array {
    $f=basename($_SERVER['SCRIPT_NAME']??'');
    $map=['index.php'=>'dashboard','sales.php'=>'sales','invoices.php'=>'sales','stock.php'=>'inventory','stock_add.php'=>'purchases','purchases.php'=>'purchases','stock_edit.php'=>'inventory','warehouse.php'=>'inventory','barcode_guide.php'=>'inventory','categories.php'=>'inventory','customers.php'=>'customers','debts.php'=>'debts','expenses.php'=>'expenses','losses.php'=>'losses','partners.php'=>'partners','mudaraba.php'=>'mudaraba','partnership_settlement.php'=>'mudaraba','capital.php'=>'capital','reports.php'=>'reports','settings.php'=>'settings','backup.php'=>'backup','ai_advisor.php'=>'ai','about.php'=>'about','management.php'=>'staff','portfolio.php'=>'branches','activity.php'=>'audit','closing.php'=>'closings','inventory_documents.php'=>'inventory','sales_returns.php'=>'sales','treasury.php'=>'treasury','fx.php'=>'treasury','accounting.php'=>'accounting','corrections.php'=>'accounting','suppliers.php'=>'suppliers','purchase_returns.php'=>'purchases'];
    $module=$map[$f]??null;
    if(str_contains($_SERVER['SCRIPT_NAME']??'','/print/'))return [$f==='invoice.php'?'sales':'mudaraba','print'];
    if(!$module)return [];
    $a='view';
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        $raw=(string)($_POST['action']??'');$a='create';
        if(preg_match('/delete|remove|reset|clear|factory/',$raw))$a='delete';
        elseif(preg_match('/approve|confirm|post|settle|payout|progress|pay_installment/',$raw))$a='approve';
        elseif(preg_match('/edit|update|save|toggle|keep/',$raw)||$f==='stock_edit.php'||$module==='settings')$a='edit';
    }
    if($f==='settings.php'&&str_starts_with((string)($_POST['action']??''),'mfa_'))return ['settings','mfa'];
    return [$module,$a];
}
function domain_enforce_route(): void {
    if(str_contains($_SERVER['SCRIPT_NAME']??'','/admin/')){if(!domain_is_central(auth_user()))access_deny(403,'central_only',__t('Central administration only.','تنها برای مدیریت مرکزی.','یوازې مرکزي مدیریت.'));return;}
    $route=domain_route();if(!$route)return;
    $requestKey=auth_user()['id'].':'.shop_id().':'.($_SERVER['SCRIPT_NAME']??'').':'.($_SERVER['REQUEST_METHOD']??'GET').':'.$route[1];if(isset($GLOBALS['_route_enforced'][$requestKey]))return;
    domain_require($route[0].'.'.$route[1]);
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        if(!csrf_verify($_POST['csrf_token']??''))access_deny(403,'csrf','Invalid form session.');
        domain_validate_references();
        if($route[1]==='delete'&&in_array($route[0],['expenses','losses','debts','capital','mudaraba'],true))access_deny(409,'preserve_history',__t('Posted financial history cannot be deleted; use an authorized correction document.','تاریخچهٔ مالی حذف نمی‌شود؛ از سند اصلاحی مجاز استفاده کنید.','مالي تاریخ مه ړنګوئ؛ اصلاحي سند وکاروئ.'));
        if($route[0]==='debts'&&$route[1]==='edit'&&(int)($_POST['debt_id']??0)>0){$q=$GLOBALS['pdo']->prepare('SELECT COUNT(*) FROM fund_transactions WHERE shop_id=? AND source_type=? AND source_id=?');$q->execute([shop_id(),'debt',(int)$_POST['debt_id']]);if((int)$q->fetchColumn())access_deny(409,'posted_balance','A settled account needs a correction document, not direct editing.');}
    }
    $f=basename($_SERVER['SCRIPT_NAME']);
    $sub=['warehouse.php'=>'inventory.locations','barcode_guide.php'=>'inventory.labels','invoices.php'=>'sales.invoices'];if(isset($sub[$f])){domain_require($sub[$f]);try{domain_consume_exact($sub[$f]);}catch(RuntimeException $e){if($e->getCode()===429)access_deny(429,'allowance_reached',$e->getMessage());throw $e;}}
    if(isset($_GET['export'])||isset($_GET['download']))domain_require($route[0].'.export');
    if($f==='settings.php'&&($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        $action=$_POST['action']??'';if(in_array($action,['currency_save','informational_rate'],true)&&!domain_is_central(auth_user()))access_deny(403,'central_currency','Currency configuration requires central administration.');
        if($action==='branding_save')domain_require('settings.branding');
    }
    if($f==='backup.php' || ($f==='settings.php'&&($_GET['tab']??'')==='backup')){
        if(!domain_is_central(auth_user()))access_deny(403,'central_backup',__t('Full database backups are restricted to central administration.','بکاپ کامل دیتابیس تنها در اختیار مدیریت مرکزی است.','بشپړ شاتړ یوازې مرکزي مدیریت ته دی.'));
        if(($_POST['action']??'')==='restore_backup')domain_require('backup.restore');
    }
    // Header/auth helpers may run more than once; one HTTP request uses one allowance.
    if($route[0]!=='ai'){try{domain_consume($route[0].'.'.((isset($_GET['export'])||isset($_GET['download']))?'export':$route[1]));}catch(RuntimeException $e){if($e->getCode()===429)access_deny(429,'allowance_reached',$e->getMessage());throw $e;}}
    $GLOBALS['_route_enforced'][$requestKey]=true;
}
function domain_audit(string $type,string $entity,$id=null,?array $after=null,$actor=null,?array $before=null): void {
    global $pdo;$shop=shop_id();$s=$shop?domain_branch($shop):null;
    $pdo->prepare('INSERT INTO security_events (business_id,shop_id,actor_id,event_type,entity_type,entity_id,before_json,after_json,created_at) VALUES (?,?,?,?,?,?,?,?,?)')->execute([(int)($s['business_id']??0),$shop,$actor??($_SESSION['user_id']??null),substr($type,0,60),substr($entity,0,60),$id===null?null:(string)$id,$before?json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE):null,$after?json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE):null,gmdate('Y-m-d H:i:s')]);
}
function domain_save_policy(string $type,int $id,string $feature,array $p): void {
    global $pdo;if(!in_array($type,['system','business','branch','user','owner_user'],true)||!isset(domain_catalog()[$feature]))throw new InvalidArgumentException('Unknown policy.');
    if(isset($p['mode'])){$mode=$p['mode'];if(!in_array($mode,['enabled','locked','hidden'],true))throw new InvalidArgumentException('Invalid policy mode.');$enabled=$mode==='enabled'?'1':'0';$visibility=$mode==='hidden'?'hidden':'locked';}
    else{$enabled=$p['enabled']??'0';$visibility=$p['visibility']??'hidden';}
    if(!in_array((string)$enabled,['0','1'],true)||!in_array($visibility,['hidden','locked'],true))throw new InvalidArgumentException('Invalid policy.');
    $limits=[];foreach(['daily_limit','monthly_limit'] as $k){$v=$p[$k]??'';if(!is_scalar($v)||($v!==''&&(!ctype_digit((string)$v)||(int)$v>1000000)))throw new InvalidArgumentException('Invalid limit.');$limits[]=$v===''?null:(int)$v;}
    $old=domain_policy($type,$id,$feature);$pdo->prepare('DELETE FROM service_policies WHERE scope_type=? AND scope_id=? AND feature=?')->execute([$type,$id,$feature]);
    $pdo->prepare('INSERT INTO service_policies (scope_type,scope_id,feature,enabled,visibility,daily_limit,monthly_limit) VALUES (?,?,?,?,?,?,?)')->execute([$type,$id,$feature,$enabled,$visibility,...$limits]);
    unset($GLOBALS['_domain_policy_cache']);
    domain_audit('policy_changed','policy',$type.':'.$id.':'.$feature,['enabled'=>(int)$enabled,'visibility'=>$visibility,'limits'=>$limits],null,$old);
}
/** Reserve all inherited quotas atomically; also called by AI, not only the UI. */
function domain_consume(string $feature,?array $u=null,bool $exact=false): void {
    global $pdo;$u=$u??auth_user();if(!domain_can($feature,$u))throw new RuntimeException('Permission denied.',403);
    if(preview_active())return;
    $own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try{
        $ins=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE':'INSERT IGNORE';
        $keys=[$feature];if(!$exact&&str_contains($feature,'.'))$keys[]=explode('.',$feature)[0];if(!$exact&&$feature==='ai.ask')$keys[]='ai.create';
        foreach(domain_policy_chain($u,shop_id()) as [$type,$id])foreach($keys as $k)foreach(['daily_limit'=>date('Y-m-d'),'monthly_limit'=>date('Y-m')] as $limit=>$period){
            $p=domain_policy($type,$id,$k);if(!$p||$p[$limit]===null)continue;
            $pdo->prepare("$ins INTO service_usage (scope_type,scope_id,feature,period_key,used) VALUES (?,?,?,?,0)")->execute([$type,$id,$k,$period]);
            $q=$pdo->prepare('UPDATE service_usage SET used=used+1 WHERE scope_type=? AND scope_id=? AND feature=? AND period_key=? AND used<?');$q->execute([$type,$id,$k,$period,(int)$p[$limit]]);
            if($q->rowCount()!==1)throw new RuntimeException(__t('Daily or monthly allowance reached.','سهمیهٔ روزانه یا ماهانه تمام شده است.','ورځنۍ یا میاشتنۍ ونډه پای ته ورسېده.'),429);
        }
        if($own)$pdo->commit();
    }catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function domain_consume_exact(string $feature): void {
 if(PHP_SAPI!=='cli'&&isset($GLOBALS['_domain_exact_used'][$feature]))return;domain_consume($feature,null,true);if(PHP_SAPI!=='cli')$GLOBALS['_domain_exact_used'][$feature]=true;
}
/** Reject foreign record references before legacy mutation handlers run. */
function domain_validate_references(): void {
    global $pdo;
    $tables=['supplier_id'=>'suppliers','category_id'=>'categories','partner_id'=>'partners','mudaraba_id'=>'mudaraba_contracts','contract_id'=>'mudaraba_contracts','stock_id'=>'stock','debt_id'=>'debts','invoice_id'=>'sales','sale_id'=>'sales','customer_id'=>'customers'];
    foreach($tables as $field=>$table){$id=$_POST[$field]??null;if($id===null||$id===''||$id==='0'||$id===0)continue;if(!is_scalar($id)||!ctype_digit((string)$id))access_deny(422,'invalid_reference','Invalid record reference.');$q=$pdo->prepare("SELECT id FROM $table WHERE id=? AND shop_id=?");$q->execute([(int)$id,shop_id()]);if(!$q->fetchColumn())access_deny(403,'foreign_reference','Record unavailable in this branch.');}
    $file=basename($_SERVER['SCRIPT_NAME']??'');$map=['stock.php'=>'stock','invoices.php'=>'sales','debts.php'=>'debts','mudaraba.php'=>'mudaraba_contracts','categories.php'=>'categories'];
    if(isset($map[$file])){
        $ids=$_POST['selected_ids']??[];if(!is_array($ids)||count($ids)>1000)access_deny(422,'invalid_selection','Invalid selection.');if(isset($_POST['id']))$ids[]=$_POST['id'];
        foreach($ids as $id){if(!is_scalar($id)||!ctype_digit((string)$id))access_deny(422,'invalid_reference','Invalid record reference.');if(!(int)$id)continue;$q=$pdo->prepare('SELECT id FROM '.$map[$file].' WHERE id=? AND shop_id=?');$q->execute([(int)$id,shop_id()]);if(!$q->fetchColumn())access_deny(403,'foreign_reference','Record unavailable in this branch.');}
    }
}

function domain_legacy_feature(string $key): string {
    $map=['ai_advisor'=>'ai','backup_module'=>'backup','customers_module'=>'customers','customers_access'=>'customers','shelf_view'=>'inventory.locations','warehouse_position'=>'inventory.locations','preview_profit_specs'=>'sales.profit_preview','low_stock_alert'=>'inventory.low_stock_alert','view_low_stock'=>'inventory.low_stock_list','capital_turnover'=>'capital','top_selling_report'=>'reports.top_selling','cash_debt_period_report'=>'reports.cash_debt','liquidity_report'=>'reports.cash_debt','shop_expenses_entry'=>'expenses.create','inventory_module'=>'inventory','warehouse_module'=>'inventory.locations','invoices_module'=>'sales.invoices','categories_module'=>'inventory','dashboard_stock_value'=>'dashboard.stock_value','dashboard_mudaraba'=>'dashboard.mudaraba','dashboard_capital'=>'dashboard.capital','dashboard_today_sales'=>'dashboard.today_sales','dashboard_today_profit'=>'dashboard.today_profit','dashboard_expenses'=>'dashboard.expenses','dashboard_overall_profit'=>'dashboard.overall_profit','dashboard_debts'=>'dashboard.debts','dashboard_quick_actions'=>'dashboard.quick_actions','dashboard_mudaraba_panel'=>'dashboard.mudaraba_panel','dashboard_cash_flow'=>'dashboard.cash_flow'];
    return $map[$key]??preg_replace('/_module$/','',$key);
}
function domain_legacy_policy(string $scope,int $id,string $legacy,$allowed): void {
    global $pdo;$feature=domain_legacy_feature($legacy);if(!isset(domain_catalog()[$feature]))return;
    if($allowed==='inherit'){$pdo->prepare('DELETE FROM service_policies WHERE scope_type=? AND scope_id=? AND feature=?')->execute([$scope,$id,$feature]);unset($GLOBALS['_domain_policy_cache']);}
    else domain_save_policy($scope,$id,$feature,['enabled'=>(string)(int)$allowed,'visibility'=>'locked']);
}

/** Assigned staff count, including employees whose home branch is elsewhere. */
function domain_branch_employee_count(int $branch): int {global $pdo;$q=$pdo->prepare("SELECT COUNT(DISTINCT u.id) FROM branch_memberships m JOIN users u ON u.id=m.user_id WHERE m.shop_id=? AND u.role='staff'");$q->execute([$branch]);return (int)$q->fetchColumn();}
