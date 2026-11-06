<?php
require_once __DIR__.'/db.php';sendSecurityHeaders();$db=Database::getConnection();$user=authenticateUser();
if($_SERVER['REQUEST_METHOD']!=='GET')jsonResponse(false,null,'Method is not allowed.',405);
$tenant=getTenantId($user);$project=(string)($_GET['project']??'');
if(!hasProjectAccess($user,$project,'audit_logs'))jsonResponse(false,null,'Activity history access is not granted.',403);
$limit=max(1,min((int)($_GET['limit']??200),500));
// Employee history contains only their own actions; company owners can review their company.
$sql='SELECT id,user_name,action,module,record_id,created_at FROM system_audit_logs WHERE tenant_id=:tenant';$params=['tenant'=>$tenant];
if(!isCompanyOwner($user)){$sql.=' AND user_id=:user';$params['user']=$user['id'];}
$sql.=' ORDER BY created_at DESC,id DESC LIMIT '.$limit;
$s=$db->prepare($sql);$s->execute($params);jsonResponse(true,['logs'=>$s->fetchAll()]);
