<?php
require_once __DIR__ . '/config.php';require_once __DIR__.'/licensing.php';
set_exception_handler(function (Throwable $e): void {
    error_log('NSC failure: ' . $e->getMessage());
    if (Database::isConnected() && Database::getConnection()->inTransaction()) Database::getConnection()->rollBack();
    jsonResponse(false, null, 'The operation failed. Please retry or contact the administrator.', 500);
});
class Database {
    private static ?PDO $instance = null;
    public static function isConnected(): bool { return self::$instance !== null; }
    public static function getConnection(): PDO {
        if (!self::$instance) {
            if (!DB_NAME || !DB_USER || !DB_PASS) jsonResponse(false, null, 'Database configuration is required.', 503);
            self::$instance = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$instance->exec("SET time_zone = '+00:00'");
        }
        return self::$instance;
    }
}
function jsonResponse(bool $success, $data = null, ?string $error = null, int $statusCode = 200): void {
    if($statusCode>=400 && Database::isConnected() && Database::getConnection()->inTransaction()) Database::getConnection()->rollBack();
    http_response_code($statusCode); $r = ['success' => $success];
    if ($data !== null) $r['data'] = $data;
    if ($error !== null) $r[$success ? 'message' : 'error'] = $error;
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
function generateUuid(): string {
    $b = random_bytes(16); $b[6] = chr(ord($b[6]) & 15 | 64); $b[8] = chr(ord($b[8]) & 63 | 128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '28800'); session_name('nsc_session');
    session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>APP_ENV === 'production', 'httponly'=>true, 'samesite'=>'Strict']);
    session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function requireCsrf(): void {
    startSecureSession();
    if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) jsonResponse(false, null, 'Invalid request token. Reload and retry.', 403);
}
function readJson(int $maxBytes = 2097152): array {
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if (strlen($raw) > $maxBytes) jsonResponse(false, null, 'Request is too large.', 413);
    try { $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { jsonResponse(false, null, 'Invalid JSON.', 400); }
    if (!is_array($data) || !str_starts_with(ltrim($raw), '{')) jsonResponse(false, null, 'A JSON object is required.', 400);
    foreach(['id','action','username','email','password','currentPassword','name','companyId','companyName','phone','token','projectId','fromProjectId','toProjectId','date','purchaseDate','payrollDate','transferDate','contractDate','startDate'] as $key)if(isset($data[$key])&&!is_string($data[$key]))jsonResponse(false,null,'Invalid text field.',400);
    return $data;
}
function getTenantId(array $user): string {
    return !empty($user['owner_admin_id']) ? $user['owner_admin_id'] : $user['id'];
}
function authenticateUser(bool $requireMaster = false): array {
    startSecureSession();
    if (empty($_SESSION['uid']) || time() - ($_SESSION['last_seen'] ?? 0) > 1800 || time() - ($_SESSION['issued_at'] ?? 0) > 28800) {
        $_SESSION = []; session_destroy(); jsonResponse(false, null, 'Please sign in again.', 401);
    }
    $s = Database::getConnection()->prepare('SELECT * FROM users WHERE id = :id'); $s->execute(['id'=>$_SESSION['uid']]); $u = $s->fetch();
    if (!$u || $u['is_locked'] || (int)$u['session_version'] !== (int)($_SESSION['version'] ?? -1)) {
        $_SESSION = []; session_destroy(); jsonResponse(false, null, 'This session is no longer valid.', 401);
    }
    assertLicenseAccess($u);
    if ($requireMaster && !$u['is_master']) jsonResponse(false, null, 'Master administrator access is required.', 403);
    $_SESSION['last_seen'] = time();
    if (in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST','PUT','DELETE'], true)) requireCsrf();
    return $u;
}
function publicUser(array $u): array {
    return array_merge(['id'=>$u['id'], 'name'=>$u['name'], 'username'=>$u['username'], 'email'=>$u['email'], 'role'=>$u['role']==='master'?'admin':$u['role'],
        'companyProfile'=>json_decode($u['profile_json']??'{}',true), 'phone'=>$u['phone'], 'companyName'=>$u['company_name'], 'companyAddress'=>$u['company_address'], 'active'=>!$u['is_locked']&&!($u['office7_locked']??false), 'verified'=>true,
        'isMasterSuperAdmin'=>(bool)$u['is_master'], 'ownerAdminId'=>$u['owner_admin_id'], 'subscriptionPlan'=>$u['subscription_plan'],
        'subscriptionExpiresAt'=>$u['subscription_expires_at'], 'permissions'=>json_decode($u['permissions_json'] ?? '{}',true),
        'customEnabledModules'=>json_decode($u['allowed_modules_json'] ?? '{}',true), 'allowedTabs'=>json_decode($u['allowed_tabs_json'] ?? '[]',true), 'createdAt'=>$u['created_at'],'sessionVersion'=>(int)$u['session_version'],'office7Link'=>office7Link($u)],licenseInfo($u));
}
function requireModule(array $u, string $module, bool $write = false): void {
    $mods=json_decode($u['allowed_modules_json']??'{}',true)?:[];
    if(($mods[$module]??true)===false)jsonResponse(false,null,'This module is disabled.',403);
    if($u['is_master'])return;
    // Legacy SQL responses are company-wide; employee access uses the scoped workspace API.
    if(!isCompanyOwner($u))jsonResponse(false,null,'Use the project-scoped workspace API.',403);
    if(!hasProjectAccess($u,'*',$module,'view'))jsonResponse(false,null,'This module is disabled.',403);
    // Legacy writes combine mutation/posting in one action. Do not bypass any Office restriction.
    if($write)foreach(['create','edit','delete','approve','reverse'] as $action)if(!hasProjectAccess($u,'*',$module,$action))jsonResponse(false,null,'This legacy operation is restricted by Office Seven.',403);
}
function assertOwned(PDO $db, string $table, ?string $id, string $tenant, bool $optional = false): void {
    if (!$id) { if ($optional) return; jsonResponse(false,null,'A record identifier is required.',400); }
    $allowed = ['projects','project_budgets','treasury_accounts','suppliers','subcontractors','clients','apartment_units','assets_machinery','payment_vouchers','journal_entries','revenue_entries'];
    if (!in_array($table,$allowed,true)) throw new LogicException('Invalid resource type');
    $s=$db->prepare("SELECT id FROM `$table` WHERE id = :id AND tenant_id = :tid"); $s->execute(['id'=>$id,'tid'=>$tenant]);
    if (!$s->fetch()) jsonResponse(false,null,'Record was not found.',404);
}
function guardUpsert(PDO $db, string $table, string $id, string $tenant): void {
    $allowed=['projects','project_budgets','suppliers','subcontractors','apartment_units','assets_machinery'];
    if (!in_array($table,$allowed,true)) throw new LogicException('Invalid resource type');
    $s=$db->prepare("SELECT tenant_id FROM `$table` WHERE id = :id"); $s->execute(['id'=>$id]); $r=$s->fetch();
    if ($r && $r['tenant_id'] !== $tenant) jsonResponse(false,null,'Record was not found.',404);
}
// Native PDO cannot bind one named placeholder twice. Expand it safely and bind each occurrence.
class NscStatement {
    private PDOStatement $statement; private array $mapping;
    public function __construct(PDO $db, string $sql) {
        $this->mapping=[]; $counts=[];
        $sql=preg_replace_callback('/(?<!:):([A-Za-z_][A-Za-z0-9_]*)/',function($m) use (&$counts){$key=$m[1]; $n=($counts[$key]??0)+1; $counts[$key]=$n; $new=$key.'__'.$n; $this->mapping[$new]=$key; return ':'.$new;},$sql);
        $this->statement=$db->prepare($sql);
    }
    public function execute(?array $params=null): bool { if ($params===null) return $this->statement->execute(); $mapped=[]; foreach($this->mapping as $new=>$old) { if (!array_key_exists($old,$params)) throw new InvalidArgumentException('Missing parameter'); $mapped[$new]=$params[$old]; } return $this->statement->execute($mapped); }
    public function bindValue($key,$value,$type=PDO::PARAM_STR): bool { $key=ltrim($key,':');foreach($this->mapping as $new=>$old) if($old===$key) $this->statement->bindValue(':'.$new,$value,$type);return true; }
    public function __call($name,$args) { return $this->statement->$name(...$args); }
}
function prepareStatement(PDO $db,string $sql): NscStatement { return new NscStatement($db,$sql); }
function logAudit(PDO $db, ?string $userId, string $userName, string $action, string $module, ?string $recordId, ?string $details = null): void {
    $tenant=null;
    if($userId) { $s=$db->prepare('SELECT id, owner_admin_id FROM users WHERE id = :id');$s->execute(['id'=>$userId]);$u=$s->fetch();if($u)$tenant=getTenantId($u); }
    $agent=json_decode(json_encode(substr((string)($_SERVER['HTTP_USER_AGENT']??'cli'),0,4096),JSON_INVALID_UTF8_SUBSTITUTE),true);preg_match('/^.{0,255}/us',$agent,$agentPart);
    $s=$db->prepare('INSERT INTO system_audit_logs (tenant_id,user_id,user_name,action,module,record_id,ip_address,user_agent,details) VALUES (:tid,:uid,:name,:action,:module,:rid,:ip,:ua,:details)');
    $s->execute(['tid'=>$tenant,'uid'=>$userId,'name'=>$userName,'action'=>$action,'module'=>$module,'rid'=>$recordId,'ip'=>$_SERVER['REMOTE_ADDR']??'cli','ua'=>$agentPart[0]??'cli','details'=>$details]);
}
function validateBusinessInput(PDO $db, array $user, array $input): void {
    $tenant=getTenantId($user);
    $refs=['projectId'=>'projects','assignedProjectId'=>'projects','fromProjectId'=>'projects','toProjectId'=>'projects','supplierId'=>'suppliers','clientId'=>'clients','targetAccountId'=>'treasury_accounts','sourceAccountId'=>'treasury_accounts','fromAccountId'=>'treasury_accounts','toAccountId'=>'treasury_accounts','budgetCategoryId'=>'project_budgets'];
    foreach($refs as $key=>$table) if(!empty($input[$key])) assertOwned($db,$table,(string)$input[$key],$tenant);
    foreach(['amount','exchangeRate','unitPrice','quantity','days','daysOrWorkUnits','rate','ratePerUnit','advance','advanceDeduction','purchaseCost','salvageValue','priceUsd','priceAfn','totalPaidAfn','contractValue','approvedBudget','budgetedAmount','totalPurchases','totalPaid','openingBalance','certifiedAmount','paidAmount','retentionAmount'] as $key) {
        if(isset($input[$key]) && (!is_numeric($input[$key]) || !is_finite((float)$input[$key]) || (float)$input[$key] < 0 || (float)$input[$key] > 1000000000000)) jsonResponse(false,null,'An amount is invalid.',400);
    }
    if(isset($input['exchangeRate']) && ((float)$input['exchangeRate']<.0001 || (float)$input['exchangeRate']>1000000)) jsonResponse(false,null,'Exchange rate must be positive.',400);
    if(isset($input['currency']) && !in_array($input['currency'],['AFN','USD'],true)) jsonResponse(false,null,'Unsupported currency.',400);
    foreach(['date','purchaseDate','payrollDate','transferDate','entryDate','contractDate','startDate','expectedFinish'] as $key) {
        if(!empty($input[$key])) {$d=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$input[$key]);if(!$d || $d->format('Y-m-d')!==$input[$key])jsonResponse(false,null,'Invalid date.',400);}
    }
    if(in_array(basename($_SERVER['SCRIPT_FILENAME']??''),['finance.php','materials.php','payroll.php','apartments.php'],true)){
        $s=$db->prepare('SELECT document FROM tenant_workspaces WHERE tenant_id=:tid');$s->execute(['tid'=>$tenant]);$settings=json_decode($s->fetchColumn()?:'{}',true)['appSettings']??[];$cutoff=$settings['closedThrough']??'';
        if($cutoff){$dates=[];foreach(['date','purchaseDate','payrollDate','transferDate','contractDate'] as $key)if(!empty($input[$key]))$dates[]=$input[$key];if(!$dates)$dates[]=(new DateTimeImmutable('now',new DateTimeZone('Asia/Kabul')))->format('Y-m-d');foreach($dates as $date)if($date<=$cutoff)jsonResponse(false,null,'The accounting period is closed.',409);}
    }
}
function beginIdempotent(PDO $db,string $tenant,array $input,string $action): void {
    $key=(string)($input['requestId']??'');
    if(!preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$key))jsonResponse(false,null,'A unique requestId is required for financial operations.',400);
    $hash=hash('sha256',json_encode($input,JSON_THROW_ON_ERROR));$db->beginTransaction();
    $s=$db->prepare('INSERT IGNORE INTO financial_requests(tenant_id,request_id,request_hash,action,result) VALUES(:tid,:key,:hash,:action,NULL)');$s->execute(['tid'=>$tenant,'key'=>$key,'hash'=>$hash,'action'=>$action]);
    $s=$db->prepare('SELECT request_hash,action,result FROM financial_requests WHERE tenant_id=:tid AND request_id=:key FOR UPDATE');$s->execute(['tid'=>$tenant,'key'=>$key]);$row=$s->fetch();
    if($row['request_hash']!==$hash||$row['action']!==$action){$db->rollBack();jsonResponse(false,null,'Request identifier was already used for another operation.',409);}
    if($row['result']!==null){$db->rollBack();jsonResponse(true,json_decode($row['result'],true));}
    $GLOBALS['financial_request_context']=[$tenant,$key];
}
function finishIdempotent(PDO $db,array $result): void {
    [$tenant,$key]=$GLOBALS['financial_request_context'];
    $s=$db->prepare('UPDATE financial_requests SET result=:result WHERE tenant_id=:tid AND request_id=:key');$s->execute(['result'=>json_encode($result,JSON_THROW_ON_ERROR),'tid'=>$tenant,'key'=>$key]);$db->commit();
}
function validateGrants($permissions): void {
    if(!is_array($permissions)||!isset($permissions['projectGrants'])||!is_array($permissions['projectGrants'])||count($permissions['projectGrants'])>5000)jsonResponse(false,null,'Invalid project permissions.',400);
    $modules=['dashboard','projects','steel','concrete','expenses','contractors','labor','suppliers','assets','apartments','payments','budget','documents','reports','accounting','partners','investments','transfers','credits','equity','audit_logs','services','guide','assistant','drive','backup','calculator'];
    foreach($permissions['projectGrants'] as $grant) {
        if(!is_array($grant)||!is_string($grant['projectId']??null)||!is_array($grant['modules']??null))jsonResponse(false,null,'Invalid project grant.',400);
        foreach($grant['modules'] as $module=>$actions) if((!in_array($module,$modules,true)&&!preg_match('/^custom_[a-zA-Z0-9-]{1,60}$/D',$module))||!is_array($actions)||array_diff($actions,['view','create','edit','delete','approve','reverse','print','export']))jsonResponse(false,null,'Invalid module permission.',400);
    }
}
function isCompanyOwner(array $u): bool {return (bool)$u['is_master']||($u['role']==='admin'&&!$u['owner_admin_id']);}
function hasProjectAccess(array $u,string $project,string $module,string $action='view'): bool {
    if($u['is_master'])return true;
    $tenant=getTenantId($u);
    if(!array_key_exists($tenant,$GLOBALS['nsc_policy_cache']??[])) {$s=Database::getConnection()->prepare('SELECT document FROM tenant_workspaces WHERE tenant_id=:tid');$s->execute(['tid'=>$tenant]);$GLOBALS['nsc_policy_cache'][$tenant]=json_decode($s->fetchColumn()?:'{}',true);}
    if(($GLOBALS['nsc_policy_cache'][$tenant]['appSettings']['dashboardDesign']['moduleSettings'][$module]['locked']??false) && $action!=='view')return false;
    if(in_array($action,$GLOBALS['nsc_policy_cache'][$tenant]['appSettings']['dashboardDesign']['moduleSettings'][$module]['disabledActions']??[],true)||in_array('view',$GLOBALS['nsc_policy_cache'][$tenant]['appSettings']['dashboardDesign']['moduleSettings'][$module]['disabledActions']??[],true))return false;
    $mods=json_decode($u['allowed_modules_json']??'{}',true)?:[];if(($mods[$module]??true)===false)return false;
    if(isCompanyOwner($u))return true;
    $p=json_decode($u['permissions_json']??'{}',true)?:[];
    foreach($p['projectGrants']??[] as $g)if(($g['projectId']==='*'||$g['projectId']===$project)&&in_array($action,$g['modules'][$module]??[],true))return true;
    return false;
}
function workspaceModule(string $collection): ?string {
    return ['projects'=>'projects','expenses'=>'expenses','steel'=>'steel','concrete'=>'concrete','contractors'=>'contractors','suppliers'=>'suppliers','labor'=>'labor','assets'=>'assets','apartments'=>'apartments','payments'=>'payments','documents'=>'documents','projectPartners'=>'partners','projectInvestments'=>'investments','projectTransfers'=>'transfers','creditNotes'=>'credits','partnerEquityTransfers'=>'equity','projectBudgets'=>'budget'][$collection]??null;
}
function workspaceNeedsFiltering(array $u,array $document): bool {
 if($u['is_master'])return false;
 if(in_array(false,json_decode($u['allowed_modules_json']??'{}',true)?:[],true))return true;
 foreach($document['appSettings']['dashboardDesign']['moduleSettings']??[] as $setting)if(in_array('view',$setting['disabledActions']??[],true))return true;
 return false;
}
function workspaceStudioProjectAccess(array $u,string $project):bool {
 require_once __DIR__.'/studio-model.php';$tenant=getTenantId($u);
 if(!array_key_exists($tenant,$GLOBALS['nsc_studio_access_cache']??[])){$s=Database::getConnection()->prepare('SELECT published FROM studio_configs WHERE tenant_id=:t');$s->execute(['t'=>$tenant]);$GLOBALS['nsc_studio_access_cache'][$tenant]=json_decode($s->fetchColumn()?:'null',true);}
 $cfg=$GLOBALS['nsc_studio_access_cache'][$tenant];if(!$cfg)return false;foreach(array_merge($cfg['modules'],$cfg['pages']) as $m)if(studioPermission($u,$cfg,$m['id'],$project,'view'))return true;return false;
}
function visibleWorkspace(array $u,array $document): array {
    unset($document['appSettings']['sectionProtection'],$document['appSettings']['geminiApiKey']);
    $owner=isCompanyOwner($u);$controls=json_decode($u['allowed_modules_json']??'{}',true)?:[];
    if($owner&&!workspaceNeedsFiltering($u,$document))return $document;
    $visible=[];$readDependencies=['expenses','steel','concrete','payments','apartments','projectInvestments','projectPartners','projectBudgets','labor','projectTransfers','creditNotes'];
    foreach($document as $collection=>$rows) {
        $module=workspaceModule($collection);
        if($module && is_array($rows))$visible[$collection]=array_values(array_filter($rows,function($r)use($u,$collection,$module,$readDependencies,$document){$pid=$collection==='projects'?($r['id']??''):($r['projectId']??$r['assignedProjectId']??'');if(!$pid)return false;
            if($collection==='projects'){foreach(array_merge(array_column($document['appSettings']['dashboardDesign']['pages']??[],'id'),['projects','steel','concrete','expenses','apartments','payments','reports','accounting','budget','contractors','suppliers','assets','labor','documents','partners','investments','transfers','credits','equity','audit_logs','services','guide','assistant','drive','backup','calculator']) as $m)if(hasProjectAccess($u,$pid,$m))return true;return workspaceStudioProjectAccess($u,$pid);}
            if($collection==='projectTransfers')return (hasProjectAccess($u,$pid,'transfers')&&(($r['kind']??'')==='ownership_transfer'||hasProjectAccess($u,$r['toProjectId']??'','transfers')))||(($r['kind']??'')!=='ownership_transfer'&&array_filter([$pid,$r['toProjectId']??''],fn($id)=>hasProjectAccess($u,$id,'reports')||hasProjectAccess($u,$id,'accounting')||hasProjectAccess($u,$id,'dashboard')));
            return hasProjectAccess($u,$pid,$module)||($collection==='projectPartners'&&hasProjectAccess($u,$pid,'investments'))||(in_array($collection,$readDependencies,true)&&(hasProjectAccess($u,$pid,'reports')||hasProjectAccess($u,$pid,'accounting')||hasProjectAccess($u,$pid,'dashboard')));
        }));
    }
    if(isset($visible['projects']))$visible['projects']=array_map(function($project)use($u){
      $pid=$project['id'];
      if(!hasProjectAccess($u,$pid,'projects')&&!hasProjectAccess($u,$pid,'reports')&&!hasProjectAccess($u,$pid,'accounting')&&!hasProjectAccess($u,$pid,'dashboard')){
        $fields=['id','name','code','city','address','floors','units','buildingArea','currency','defaultExchangeRate','status','startDate','progressPercentage','appearance','createdAt'];
        if(hasProjectAccess($u,$pid,'budget'))$fields[]='budget';
        if(hasProjectAccess($u,$pid,'transfers'))$fields=array_merge($fields,['initialOwner','currentOwner','clientOwner','transferHistory']);
        $project=array_intersect_key($project,array_flip($fields));
      }
      if(!hasProjectAccess($u,$pid,'partners'))unset($project['partners']);
      if(!hasProjectAccess($u,$pid,'expenses'))unset($project['initialGeneralExpenses']);
      return $project;
    },$visible['projects']);
    $financialFields=['id','projectId','toProjectId','kind','toMethod','date','time','category','amount','currency','exchangeRate','amountInUSD','partyType','retentionPercentage','taxPercentage','paidAmount','paymentStatus','approvalStatus','reversal','partialReversals','creditAdjustments','sourceCollection','sourceId','correctionOf','remainingAmount','totalAmount','kg','tons','pricePerTon','quantityM3','pricePerM3','pumpCharge','remainingBalance','relatedType','relatedId','method','paymentType','unitNumber','areaM2','totalPrice','salePrice','status','contractDate','saleDate','downPayment','downPaymentCurrency','amountReceived','totalReceived','installmentId','installments','type','partnerId','accountCode','sharePercentage','daysWorked','daysReduction','dailyRate','advanceDeduction','paidDate','grossWage','netPayable'];
    foreach($visible as $collection=>$rows){$module=workspaceModule($collection);if(!$module||$collection==='projects')continue;
        $visible[$collection]=array_map(function($row)use($u,$module,$financialFields){if(hasProjectAccess($u,$row['projectId']??'',$module)&&($module!=='transfers'||($row['kind']??'')==='ownership_transfer'||hasProjectAccess($u,$row['toProjectId']??'','transfers')))return $row;
            $safe=array_intersect_key($row,array_flip($financialFields));
            if($module==='partners'&&hasProjectAccess($u,$row['projectId']??'','investments'))$safe=['id'=>$row['id'],'projectId'=>$row['projectId'],'name'=>$row['name']??''];
            if($module==='transfers')foreach(['projectId','toProjectId'] as $key){$pid=$safe[$key]??'';if(!hasProjectAccess($u,$pid,'reports')&&!hasProjectAccess($u,$pid,'accounting')&&!hasProjectAccess($u,$pid,'dashboard'))$safe[$key]='';}
            // Reports need amounts and account classes, not customer contact information.
            foreach(['description','partyName','supplierName','concreteGrade','billNumber','partnerName','buyerName','buyerPhone','workerName','workPeriod'] as $field)$safe[$field]='';
            if(isset($safe['reversal']))$safe['reversal']=array_intersect_key($safe['reversal'],array_flip(['id','date','amount']));
            if(isset($safe['creditAdjustments']))$safe['creditAdjustments']=array_map(fn($e)=>array_intersect_key($e,array_flip(['id','creditNoteId','date','amount','daysReduction','restoresCredit'])),$safe['creditAdjustments']);
            if(isset($safe['partialReversals']))$safe['partialReversals']=array_map(fn($e)=>array_intersect_key($e,array_flip(['id','date','amount'])),$safe['partialReversals']);
            if(isset($safe['installments']))$safe['installments']=array_map(fn($i)=>array_intersect_key($i,array_flip(['id','installmentNumber','amount','dueDate','paidAmount','remainingAmount','currency','status'])), $safe['installments']);
            return $safe;
        },$rows);
    }
    if($owner){
      foreach($document as $key=>$value)if(!workspaceModule($key))$visible[$key]=$value;
      // Client-generated history can contain names/amounts from hidden modules.
      // Authoritative activity remains available through the scoped audit endpoint.
      $visible['auditLogs']=[];$visible['activityEvents']=[];
      unset($visible['appSettings']['designHistory']);
      if(isset($visible['appSettings']['dashboardDesign']['pages']))$visible['appSettings']['dashboardDesign']['pages']=array_values(array_filter($visible['appSettings']['dashboardDesign']['pages'],fn($page)=>hasProjectAccess($u,'*',$page['id'],'view')));
      return $visible;
    }
    $visible['appSettings']=['companyName'=>$u['company_name'],'language'=>in_array($document['appSettings']['language']??'en',['en','fa','ps'],true)?($document['appSettings']['language']??'en'):'en','companyProfile'=>$document['appSettings']['companyProfile']??[], 'documentTemplates'=>$document['appSettings']['documentTemplates']??[], 'enabledModules'=>[], 'dashboardDesign'=>$document['appSettings']['dashboardDesign']??null];
    if(isset($visible['appSettings']['dashboardDesign']['pages']))$visible['appSettings']['dashboardDesign']['pages']=array_values(array_filter($visible['appSettings']['dashboardDesign']['pages'],fn($p)=>count(array_filter($visible['projects']??[],fn($project)=>hasProjectAccess($u,$project['id'],$p['id'],'view')))>0));
    $visible['appSettings']['notificationPreferences']=array_intersect_key($document['appSettings']['notificationPreferences']??[],array_flip(['expenses','payments','sales','budget','documents','browser','quietStart','quietEnd']));
    $visible['currentProjId']=$visible['projects'][0]['id']??'';
    return $visible;
}
function mergeStaffWorkspace(array $u,array $current,array $incoming): array {
    $visible=visibleWorkspace($u,$current);
    foreach($incoming as $collection=>$rows) {
        $module=workspaceModule($collection);
        if(!$module){continue;} // Staff cannot change company settings, grants, or audit history.
        if(!is_array($rows))jsonResponse(false,null,'Invalid collection.',400);
        $old=[];foreach($visible[$collection]??[] as $r)$old[$r['id']]=$r;
        $new=[];foreach($rows as $r){if(!is_array($r)||!is_string($r['id']??null)||isset($new[$r['id']]))jsonResponse(false,null,'Invalid record identifier.',400);$new[$r['id']]=$r;}
        foreach(array_unique(array_merge(array_keys($old),array_keys($new))) as $id){
            $before=$old[$id]??null;$after=$new[$id]??null;if($before==$after)continue;
            $action=$before===null?'create':($after===null?'delete':'edit');$row=$after??$before;if($collection==='creditNotes'){authorizeCredit($u,$row,false);if($before)authorizeCredit($u,$before,false);}if($collection==='projectTransfers'){authorizeTransfer($u,$row,$action);if($before)authorizeTransfer($u,$before,$action);}$pid=$collection==='projects'?$id:($row['projectId']??$row['assignedProjectId']??'');
            if(!$pid||!hasProjectAccess($u,$pid,$module,$action)||($before && !hasProjectAccess($u,$collection==='projects'?$id:($before['projectId']??$before['assignedProjectId']??''),$module,$action)))jsonResponse(false,null,'This project operation is not permitted.',403);
            if($before===null)foreach($current[$collection]??[] as $existing)if(($existing['id']??null)===$id)jsonResponse(false,null,'Record identifier is unavailable.',409);
        }
        $merged=[];$visibleIds=array_fill_keys(array_keys($old),true);
        foreach($current[$collection]??[] as $r)if(!isset($visibleIds[$r['id']]))$merged[]=$r;
        $full=[];foreach($current[$collection]??[] as $r)$full[$r['id']]=$r;
        foreach($new as $id=>$row)$merged[]=isset($old[$id]) && $old[$id]==$row ? $full[$id] : array_replace($full[$id]??[],$row);
        $current[$collection]=$merged;
    }
    return $current;
}
function enforceClosedPeriod(array $current,array $incoming,bool $allowReversal=false): void {
    $cutoff=$current['appSettings']['closedThrough']??'';if(!$cutoff)return;
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$cutoff))throw new LogicException('Invalid closed period');
    foreach(['expenses','steel','concrete','payments','projectInvestments','projectTransfers','creditNotes','labor','apartments'] as $collection){
        $old=[];foreach($current[$collection]??[] as $r)$old[$r['id']]=$r;$new=[];foreach($incoming[$collection]??[] as $r)$new[$r['id']]=$r;
        foreach(array_unique(array_merge(array_keys($old),array_keys($new))) as $id){$before=$old[$id]??null;$after=$new[$id]??null;if($before==$after)continue;
            if($allowReversal&&$before&&$after&&!isset($before['reversal'])&&isset($after['reversal'])){$base=$after;unset($base['reversal']);if($base==$before&&$after['reversal']['date']>$cutoff)continue;}
            if($allowReversal&&$before&&$after){$base=$after;foreach(['reversal','partialReversals'] as $k){if(array_key_exists($k,$before))$base[$k]=$before[$k];else unset($base[$k]);}$newParts=array_slice($after['partialReversals']??[],count($before['partialReversals']??[]));if($base==$before&&count($newParts)===1&&$newParts[0]['date']>$cutoff)continue;}
            if($allowReversal&&$before&&$after){$base=$after;if(array_key_exists('creditAdjustments',$before))$base['creditAdjustments']=$before['creditAdjustments'];else unset($base['creditAdjustments']);$parts=array_slice($after['creditAdjustments']??[],count($before['creditAdjustments']??[]));if($base==$before&&count($parts)===1&&$parts[0]['date']>$cutoff)continue;}
            $beforeDate=$before['date']??$before['saleDate']??$before['contractDate']??'';$afterDate=$after['date']??$after['saleDate']??$after['contractDate']??'';
            if(($beforeDate!==''&&$beforeDate<=$cutoff)||($afterDate!==''&&$afterDate<=$cutoff))jsonResponse(false,null,'The accounting period is closed. Reopen it before changing historical records.',409);
        }
    }
}
function validateWorkspaceDocument(array $document): void {
    $collections=['projects','expenses','steel','concrete','contractors','suppliers','labor','assets','apartments','payments','documents','projectPartners','projectInvestments','projectTransfers','creditNotes','partnerEquityTransfers','projectBudgets'];
    $projects=[];foreach($document['projects']??[] as $project)if(is_array($project)&&is_string($project['id']??null))$projects[$project['id']]=true;
    foreach($collections as $collection){
        $seen=[];$activeCorrections=[];$inventory=[];$physical=[];foreach($document[$collection]??[] as $row){
            if(!is_array($row)||!is_string($row['id']??null)||strlen($row['id'])>120||isset($seen[$row['id']]))jsonResponse(false,null,'Invalid or duplicate record identifier.',400);
            $seen[$row['id']]=true;
            if(isset($row['partialReversals'])&&!is_array($row['partialReversals']))jsonResponse(false,null,'Invalid partial reversal metadata.',400);
            if($collection==='projects'){foreach(['initialOwner','currentOwner','clientOwner'] as $k)if(isset($row[$k])&&(!is_string($row[$k])||strlen($row[$k])>500))jsonResponse(false,null,'Invalid business owner.',400);if(isset($row['transferHistory'])&&!is_array($row['transferHistory']))jsonResponse(false,null,'Invalid ownership history.',400);}
            if($collection!=='projects'){$pid=$row['projectId']??$row['assignedProjectId']??null;if((in_array($collection,['expenses','steel','concrete','labor','payments','apartments','projectInvestments','projectTransfers','creditNotes'],true)&&!$pid)||($pid&&!isset($projects[$pid])))jsonResponse(false,null,'A record references an unavailable project.',400);}
            if(isset($row['correctionOf'])){
             if(!is_string($row['correctionOf'])||$row['correctionOf']===$row['id'])jsonResponse(false,null,'Invalid correction reference.',400);
             $source=null;foreach($document[$collection]??[] as $original)if(($original['id']??'')===$row['correctionOf'])$source=$original;
             if(!$source||!isset($source['reversal'])||($source['projectId']??'')!==($row['projectId']??''))jsonResponse(false,null,'A correction must reference a reversed document in this project.',400);
             if(($collection==='apartments'?apartmentSaleDate($row):($row['date']??''))!==''&&($collection==='apartments'?apartmentSaleDate($row):$row['date'])<$source['reversal']['date'])jsonResponse(false,null,'A correction cannot be dated before the original reversal.',400);
             if($collection==='apartments')foreach(['unitNumber','floor','areaM2'] as $field)if(($row[$field]??null)!=($source[$field]??null))jsonResponse(false,null,'A corrected sale must reference the same physical unit and area.',400);
             if(!isset($row['reversal'])){if(isset($activeCorrections[$row['correctionOf']]))jsonResponse(false,null,'An active correction for this document already exists.',409);$activeCorrections[$row['correctionOf']]=true;}
            }
            foreach(['amount','paidAmount','totalAmount','daysWorked','daysReduction','dailyRate','advanceDeduction','kg','pricePerTon','quantityM3','pricePerM3','pumpCharge','purchaseCost','salvageValue','areaM2','areaSqm','buildingArea','salePrice','downPayment','exchangeRate','sharePercentage'] as $key)if(isset($row[$key])&&((!is_int($row[$key])&&!is_float($row[$key]))||!is_finite((float)$row[$key])||(float)$row[$key]<0||(float)$row[$key]>1000000000000))jsonResponse(false,null,'A record has an invalid amount.',400);
            if(isset($row['exchangeRate'])&&((float)$row['exchangeRate']<.0001||(float)$row['exchangeRate']>1000000))jsonResponse(false,null,'Exchange rate is out of range.',400);
            if(($row['currency']??'USD')==='AFN')foreach(['amount','totalAmount','salePrice','purchaseCost'] as $key)if(isset($row[$key])&&(float)$row[$key]/(float)($row['exchangeRate']??70)>1e12)jsonResponse(false,null,'Converted amount is out of range.',400);
            if(isset($row['currency'])&&!in_array($row['currency'],['USD','AFN'],true))jsonResponse(false,null,'Unsupported currency.',400);
            $total=$row['amount']??$row['totalAmount']??null;if($total!==null&&isset($row['paidAmount'])&&(float)$row['paidAmount']>(float)$total)jsonResponse(false,null,'Paid amount exceeds the bill.',400);
            foreach(['retentionPercentage','taxPercentage','sharePercentage'] as $key)if(isset($row[$key])&&((!is_int($row[$key])&&!is_float($row[$key]))||!is_finite((float)$row[$key])||(float)$row[$key]<0||(float)$row[$key]>100))jsonResponse(false,null,'Invalid percentage.',400);
            if((float)($row['retentionPercentage']??0)+(float)($row['taxPercentage']??0)>100)jsonResponse(false,null,'Withheld percentages exceed the bill.',400);
            if($collection==='expenses'){
             if(($row['partyType']??'')!=='contractor'&&((float)($row['retentionPercentage']??0)>0||(float)($row['taxPercentage']??0)>0))jsonResponse(false,null,'Withholding applies to contractor bills.',400);
             if(!empty($row['partyId'])&&($row['partyType']??'')==='contractor'){$found=false;foreach($document['contractors']??[] as $c)if(($c['id']??'')===$row['partyId']&&($c['projectId']??'')===($row['projectId']??''))$found=true;if(!$found)jsonResponse(false,null,'The contractor is not in this project.',400);}
             if(function_exists('invoiceAmounts')&&(float)($row['paidAmount']??0)>invoiceAmounts($row)['net'])jsonResponse(false,null,'Initial payment exceeds the net bill.',400);
            }
            if($collection==='apartments'){
             validateApartmentPlan($row);$root=apartmentInventoryRoot($row,$document['apartments']??[]);$identity=json_encode([$row['projectId'],(string)($row['unitNumber']??''),(string)($row['floor']??''),(string)($row['buildingSection']??'')]);
             if(!empty($row['unitNumber'])&&isset($physical[$identity])&&$physical[$identity]!==$root)jsonResponse(false,null,'Use the corrected sale action for an existing physical unit.',409);$physical[$identity]=$root;
             if(!isset($row['reversal'])&&isset($row['approvalStatus'])){if(isset($inventory[$root]))jsonResponse(false,null,'Only one active contract is allowed for this physical unit.',409);$inventory[$root]=true;}
            }
            if($collection==='payments'){if(isset($row['paymentType'])&&!in_array($row['paymentType'],['income','expense'],true))jsonResponse(false,null,'Invalid cash direction.',400);if(($row['relatedType']??'')==='client_advance'&&($row['paymentType']??'')!=='income')jsonResponse(false,null,'Client advances must be incoming receipts.',400);}
            if($collection==='payments'&&!empty($row['installmentId'])){
             if(!is_string($row['installmentId'])||($row['relatedType']??'')!=='apartment_sale')jsonResponse(false,null,'Installment reference requires an apartment receipt.',400);
             $parent=null;foreach($document['apartments']??[] as $unit)if(($unit['id']??'')===($row['relatedId']??'')&&($unit['projectId']??'')===($row['projectId']??''))$parent=$unit;
             $found=false;foreach($parent['installments']??[] as $inst)if(($inst['id']??'')===$row['installmentId'])$found=true;
             if(!$found||($row['currency']??'USD')!==($parent['currency']??'USD')||($row['currency']??'USD')==='AFN'&&(float)($row['exchangeRate']??70)!==(float)($parent['exchangeRate']??70))jsonResponse(false,null,'Installment must belong to this contract in the same currency.',400);
            }
            if(isset($row['creditAdjustments'])&&!is_array($row['creditAdjustments']))jsonResponse(false,null,'Invalid credit adjustments.',400);
            if($collection==='creditNotes')validateCredit($document,$row);
            if($collection==='projects'&&isset($row['inventoryAreaHistory'])&&!is_array($row['inventoryAreaHistory']))jsonResponse(false,null,'Invalid geometry history.',400);
            if($collection==='projectTransfers')validateTransfer($document,$row);
            if($collection==='labor'){
             $gross=round((float)($row['daysWorked']??0)*(float)($row['dailyRate']??0),2);$advance=(float)($row['advanceDeduction']??0);
             if(!is_finite($gross)||$gross>1e12||$advance>$gross)jsonResponse(false,null,'Invalid payroll total or advance deduction.',400);
             if(isset($row['approvalStatus'])&&(!in_array($row['paymentStatus']??'pending',['paid','pending'],true)||round($advance,2)!=$advance))jsonResponse(false,null,'Paid or pending payroll and a cent-precision advance are required.',400);
             if(!empty($row['paidDate'])&&($row['paidDate']<($row['date']??'')))jsonResponse(false,null,'Payroll payment cannot precede its accrual date.',400);
            }
            if($collection==='projectInvestments'&&isset($row['approvalStatus'])){
             if(!in_array($row['type']??'',['initial','additional'],true))jsonResponse(false,null,'Invalid investment type.',400);
             if(!empty($row['partnerId'])){$found=false;foreach($document['projectPartners']??[] as $partner)if(($partner['id']??'')===$row['partnerId']&&($partner['projectId']??'')===($row['projectId']??''))$found=true;if(!$found)jsonResponse(false,null,'Investment partner is not in this project.',400);}
            }
            foreach(['date','purchaseDate','contractDate','saleDate','paidDate','startDate'] as $key)if(!empty($row[$key])){$date=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$row[$key]);if(!$date||$date->format('Y-m-d')!==$row[$key])jsonResponse(false,null,'Invalid record date.',400);}
        }
    }
}
function enforceWorkspaceModuleLocks(array $user,array $current,array $incoming): void {
    if($user['is_master'])return;
    $locks=$current['appSettings']['dashboardDesign']['moduleSettings']??[];
    $modules=json_decode($user['allowed_modules_json']??'{}',true)?:[];
    foreach(['projects','expenses','steel','concrete','contractors','suppliers','labor','assets','apartments','payments','documents','projectPartners','projectInvestments','projectTransfers','creditNotes','partnerEquityTransfers','projectBudgets'] as $collection){
        $module=workspaceModule($collection);
        if($collection==='projects'){$strip=fn($r)=>array_diff_key($r,['inventoryAreaHistory'=>true]);$current[$collection]=array_map($strip,$current[$collection]??[]);$incoming[$collection]=array_map($strip,$incoming[$collection]??[]);}
        if((($locks[$module]['locked']??false)||($modules[$module]??true)===false)&&($current[$collection]??[])!=($incoming[$collection]??[]))jsonResponse(false,null,'This module is locked or disabled.',403);
    }
}
function enforceSettledCurrency(array $current,array $incoming): void {
    foreach(['expenses','steel','concrete','apartments'] as $collection){
        $old=[];foreach($current[$collection]??[] as $row)$old[$row['id']]=$row;
        foreach($incoming[$collection]??[] as $row){$before=$old[$row['id']]??null;if(!$before)continue;
            if((float)($before['paidAmount']??$before['downPayment']??0)<=0)continue;
            if(($before['currency']??'USD')!==($row['currency']??'USD')||(isset($before['exchangeRate'],$row['exchangeRate'])&&(float)$before['exchangeRate']!==(float)$row['exchangeRate']))jsonResponse(false,null,'Currency and exchange rate cannot change after payment. Correct the related transactions first.',409);
        }
    }
}

function validateExpenseQuantities(array $previous,array $document): void {
 $old=array_column($previous['expenses']??[],null,'id');
 foreach($document['expenses']??[] as $row){if(($old[$row['id']]??null)==$row||($row['approvalStatus']??'draft')!=='draft')continue;
  if(($row['quantity']??0)>0&&($row['unitPrice']??0)>0){$total=round((float)$row['quantity']*(float)$row['unitPrice'],2);if(!is_finite($total)||$total>1e12||abs(round((float)($row['amount']??0),2)-$total)>0.000001)jsonResponse(false,null,'Expense amount must match quantity multiplied by unit price.',400);}
 }
}
