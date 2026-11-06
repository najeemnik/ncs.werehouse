<?php
require_once __DIR__.'/db.php';require_once __DIR__.'/design.php';require_once __DIR__.'/approvals.php';require_once __DIR__.'/equity.php';require_once __DIR__.'/features.php';sendSecurityHeaders();$db=Database::getConnection();$user=authenticateUser();$tenant=getTenantId($user);
$scope=(string)($_GET['company']??'');
if($scope!=='') {
 if(!isOffice7Owner($user))jsonResponse(false,null,'Master access is required.',403);
 $s=$db->prepare('SELECT id FROM users WHERE id=:id AND owner_admin_id IS NULL');$s->execute(['id'=>$scope]);if(!$s->fetch())jsonResponse(false,null,'Company was not found.',404);$tenant=$scope;
}
$method=$_SERVER['REQUEST_METHOD'];

if($method==='GET') {
    $s=$db->prepare('SELECT revision,document FROM tenant_workspaces WHERE tenant_id=:tid');$s->execute(['tid'=>$tenant]);$r=$s->fetch();
    jsonResponse(true,['revision'=>(int)($r['revision']??0),'document'=>$r?visibleWorkspace($user,json_decode($r['document'],true)):null]);
}
if(!in_array($method,['PUT','POST'],true))jsonResponse(false,null,'Method is not allowed.',405);
$input=readJson(10485760);if($method==='POST'&&($input['action']??'')==='validate_design'){if(!isOffice7Owner($user))jsonResponse(false,null,'Private Office Seven access is required.',403);validateDesign($input['design']??null);jsonResponse(true,['valid'=>true]);}$revision=$input['revision']??null;$document=$method==='POST'?[]:($input['document']??null);
if(!is_int($revision)||$revision<0||!is_array($document)||($method==='PUT'&&array_is_list($document))) jsonResponse(false,null,'Invalid workspace.',400);
$allowed=['projects','expenses','steel','concrete','contractors','suppliers','labor','assets','apartments','payments','documents','auditLogs','activityEvents','projectPartners','projectInvestments','projectTransfers','creditNotes','partnerEquityTransfers','projectBudgets','notifications','appSettings','currentProjId'];
if(array_diff(array_keys($document),$allowed))jsonResponse(false,null,'Unsupported workspace field.',400);
foreach($document as $key=>$value) {
    if($key==='currentProjId') {if(!is_string($value))jsonResponse(false,null,'Invalid project selection.',400);continue;}
    if(!is_array($value))jsonResponse(false,null,'Invalid workspace collection.',400);
}
// User identities, passwords, roles and subscriptions are never stored in workspace JSON.
$encoded=json_encode($document,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
$db->beginTransaction();
try {
    $s=$db->prepare('INSERT IGNORE INTO tenant_workspaces(tenant_id,revision,document) VALUES(:tid,0,\'{}\')');$s->execute(['tid'=>$tenant]);
    $s=$db->prepare('SELECT revision,document FROM tenant_workspaces WHERE tenant_id=:tid FOR UPDATE');$s->execute(['tid'=>$tenant]);$stored=$s->fetch();$current=(int)$stored['revision'];
    if($current!==$revision){$db->rollBack();jsonResponse(false,['revision'=>$current],'Another session changed this workspace. Reload before editing.',409);}
    $previous=json_decode($stored['document'],true);
    if($method==='POST') {
        $document=$previous;transitionApproval($user,$document,$input);
    } else {
    $controls=json_decode($user['allowed_modules_json']??'{}',true)?:[];
    if(!isOffice7Owner($user) && (($document['appSettings']['dashboardDesign']??null)!=(visibleWorkspace($user,$previous)['appSettings']['dashboardDesign']??null)||($document['appSettings']['designHistory']??[])!=(visibleWorkspace($user,$previous)['appSettings']['designHistory']??[]))) { $db->rollBack();jsonResponse(false,null,'Dashboard design is controlled by Office Seven.',403); }
    if(!isCompanyOwner($user)) {$document=mergeStaffWorkspace($user,json_decode($stored['document'],true),$document);$encoded=json_encode($document,JSON_THROW_ON_ERROR);}
    if(isCompanyOwner($user)&&!$user['is_master']&&workspaceNeedsFiltering($user,$previous)){
       $ownerIncoming=$document;$document=mergeStaffWorkspace($user,$previous,$document);foreach($ownerIncoming as $key=>$value)if(!workspaceModule($key))$document[$key]=$value;
       foreach(['auditLogs','activityEvents'] as $key){$oldIds=array_fill_keys(array_column($previous[$key]??[],'id'),true);$document[$key]=array_merge($previous[$key]??[],array_values(array_filter($ownerIncoming[$key]??[],fn($row)=>is_array($row)&&is_string($row['id']??null)&&!isset($oldIds[$row['id']]))));}
       foreach(['dashboardDesign','designHistory'] as $key){if(array_key_exists($key,$previous['appSettings']??[]))$document['appSettings'][$key]=$previous['appSettings'][$key];else unset($document['appSettings'][$key]);}
    }
    if(!isOffice7Owner($user)&&(($controls['settings']??true)===false||!hasProjectAccess($user,'*','settings','edit'))){
      foreach(['companyName'=>$user['company_name'],'language'=>'en','closedThrough'=>'','defaultCurrency'=>'USD','exchangeRateUSDToAFN'=>70] as $key=>$fallback)if(($document['appSettings'][$key]??$fallback)!=($previous['appSettings'][$key]??$fallback))jsonResponse(false,null,'Company settings are disabled for this account.',403);
    }
    validateWorkspaceDocument($document);
    protectApprovals($previous,$document);protectProjectOwnership($previous,$document);protectInventoryGeometry($previous,$document);syncInventoryGeometry($previous,$document);
    }
    $removedStudioProjects=array_diff(array_column($previous['projects']??[],'id'),array_column($document['projects']??[],'id'));foreach($removedStudioProjects as $pid){$s=$db->prepare('SELECT COUNT(*) FROM studio_records WHERE tenant_id=:t AND project_id=:p');$s->execute(['t'=>$tenant,'p'=>$pid]);if($s->fetchColumn())jsonResponse(false,null,'Projects with custom business records must be kept for history.',409);}
    validateNewFeatures($user,$previous,$document,$tenant);
    validateExpenseQuantities($previous,$document);
    validateShareTransfers($user,$previous,$document);
    validateOfficeSettings($document);validateExtraFields($previous,$document);enforceConfiguredActions($user,$previous,$document);
    enforceWorkspaceModuleLocks($user,$previous,$document);
    enforceSettledCurrency($previous,$document);
    validateWorkspaceDocument($document);
    enforceClosedPeriod($previous,$document,$method==='POST'&&(($input['status']??'')==='reversed'||($input['collection']??'')==='creditNotes'));
    $encoded=json_encode($document,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if(isOffice7Owner($user)&&isset($document['appSettings']['companyProfile'])&&($document['appSettings']['companyProfile']??[])!=($previous['appSettings']['companyProfile']??[])){$s=$db->prepare('UPDATE users SET profile_json=:profile WHERE id=:id');$s->execute(['profile'=>json_encode($document['appSettings']['companyProfile'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'id'=>$tenant]);}
    $next=$current+1;
    $s=$db->prepare('UPDATE tenant_workspaces SET revision=:revision,document=:doc WHERE tenant_id=:tid');$s->execute(['revision'=>$next,'doc'=>$encoded,'tid'=>$tenant]);
    $s=$db->prepare('INSERT INTO workspace_revisions(tenant_id,revision,document,created_by) VALUES(:tid,:revision,:doc,:uid)');$s->execute(['tid'=>$tenant,'revision'=>$next,'doc'=>$encoded,'uid'=>$user['id']]);
    logAudit($db,$user['id'],$user['name'],'update',$method==='POST'?$input['collection']:'workspace',$method==='POST'?$input['id']:null,$method==='POST'?'Financial workflow: '.$input['status'].'; company '.$tenant.'; revision '.$next:'Workspace revision '.$next);
    $db->commit();jsonResponse(true,['revision'=>$next,'document'=>visibleWorkspace($user,$document)]);
} catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
