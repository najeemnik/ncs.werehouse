<?php
require_once __DIR__.'/db.php';require_once __DIR__.'/features.php';sendSecurityHeaders();$db=Database::getConnection();$actor=authenticateUser();$officeOwner=isOffice7Owner($actor);$tenant=getTenantId($actor);
if(!$actor['is_master'] && ($actor['role']!=='admin'||$actor['owner_admin_id']))jsonResponse(false,null,'Company owner access is required.',403);
if($_SERVER['REQUEST_METHOD']==='GET') {
    if(!$officeOwner)requireModule($actor,'staff');
    $s=$db->prepare($officeOwner?'SELECT * FROM users ORDER BY created_at DESC':'SELECT * FROM users WHERE id=:id OR owner_admin_id=:owner ORDER BY created_at DESC');$s->execute($officeOwner?[]:['id'=>$tenant,'owner'=>$tenant]);
    jsonResponse(true,['users'=>array_map('publicUser',$s->fetchAll())]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,null,'Method is not allowed.',405);
 $input=readJson(131072);
if($officeOwner && !empty($input['companyId'])) { $s=$db->prepare('SELECT id FROM users WHERE id=:id AND owner_admin_id IS NULL');$s->execute(['id'=>$input['companyId']]);if(!$s->fetch())jsonResponse(false,null,'Company was not found.',404);$tenant=$input['companyId']; }
if(!$officeOwner)requireModule($actor,'staff');
$action=$input['action']??'create';$id=$input['id']??null;
if(!$officeOwner&&!hasProjectAccess($actor,'*','staff',$action==='disable'?'delete':($action==='update'?'edit':'create')))jsonResponse(false,null,'This staff operation is disabled.',403);
if($id){$s=$db->prepare('SELECT * FROM users WHERE id=:id');$s->execute(['id'=>$id]);$target=$s->fetch();if(!$target||(!$officeOwner&&$target['owner_admin_id']!==$tenant))jsonResponse(false,null,'User was not found.',404);if($target['is_master'])jsonResponse(false,null,'Master accounts must be managed through the host administrator.',403);}
if($action==='disable'){
 if(!$id)jsonResponse(false,null,'User id is required.',400);
 $db->beginTransaction();
 $s=$db->prepare('UPDATE users SET is_locked=1,session_version=session_version+1 WHERE id=:id');$s->execute(['id'=>$id]);
 $s=$db->prepare('UPDATE password_resets SET used_at=UTC_TIMESTAMP() WHERE user_id=:id AND used_at IS NULL');$s->execute(['id'=>$id]);
 logAudit($db,$actor['id'],$actor['name'],'lock','users',$id,'Account disabled; sessions and recovery tokens revoked');$db->commit();jsonResponse(true);
}
if(!in_array($action,['create','update','create_company'],true))jsonResponse(false,null,'Invalid action.',400);
if($action==='create_company'&&!$officeOwner)jsonResponse(false,null,'Master access is required.',403);
if($action==='update'&&!$id)jsonResponse(false,null,'User id is required.',400);
$name=trim((string)($input['name']??''));$username=strtolower(trim((string)($input['username']??'')));$email=strtolower(trim((string)($input['email']??'')));$password=(string)($input['password']??'');
if(strlen((string)($input['phone']??''))>40||strlen($email)>150)jsonResponse(false,null,'Contact information is too long.',400);
if(!$name||strlen($name)>150||!preg_match('/^[a-z0-9_.@-]{3,80}$/D',$username)||!filter_var($email,FILTER_VALIDATE_EMAIL))jsonResponse(false,null,'Name, username and valid email are required.',400);
if(($action!=='update'||$password!=='')&&(strlen($password)<12||strlen($password)>72||str_contains($password,"\0")))jsonResponse(false,null,'Password must contain 12–72 bytes.',400);
if($action==='update'&&$password!=='')jsonResponse(false,null,'Use Office Seven account controls to change passwords.',403);
$permissions=$input['permissions']??['projectGrants'=>[]];validateGrants($permissions);
$role=$action==='create_company'?'admin':($input['role']??'viewer');
if(!in_array($role,['admin','accountant','viewer','project_manager','procurement'],true)||($role==='admin'&&$action!=='create_company'&&($target['owner_admin_id']??null)))jsonResponse(false,null,'Invalid role.',400);
$profile=$input['companyProfile']??null;if($profile!==null){if(!$officeOwner||($action!=='create_company'&&(!isset($target)||$target['owner_admin_id'])))jsonResponse(false,null,'Company profiles are controlled by Office Seven.',403);validateCompanyProfile($profile);}
if(isset($input['disabled'])&&!is_bool($input['disabled']))jsonResponse(false,null,'Invalid account state.',400);
$locked=array_key_exists('disabled',$input)?(int)$input['disabled']:(int)($target['is_locked']??0);
if($action==='update'&&!$officeOwner&&$locked!==(int)$target['is_locked']&&!hasProjectAccess($actor,'*','staff','delete'))jsonResponse(false,null,'Changing account lock state is disabled.',403);
$db->beginTransaction();
if($action==='update') {
 $s=$db->prepare('UPDATE users SET name=:name,username=:username,email=:email,phone=:phone,permissions_json=:permissions,is_locked=:locked,session_version=session_version+1 WHERE id=:id');
 $s->execute(['name'=>$name,'username'=>$username,'email'=>$email,'phone'=>$input['phone']??null,'permissions'=>json_encode($permissions,JSON_THROW_ON_ERROR),'locked'=>$locked,'id'=>$id]);
 if($password!==''){$s=$db->prepare('UPDATE users SET password_hash=:hash WHERE id=:id');$s->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$id]);}
} else {
 $id=generateUuid();$owner=$action==='create_company'?null:$tenant;
 $company=$action==='create_company'?($input['companyName']??$name):null;if($owner){$companyStatement=$db->prepare('SELECT company_name FROM users WHERE id=:id');$companyStatement->execute(['id'=>$owner]);$company=$companyStatement->fetchColumn()?:$actor['company_name'];}
 $s=$db->prepare('INSERT INTO users(id,owner_admin_id,name,username,email,password_hash,role,phone,company_name,permissions_json,subscription_plan,is_locked) VALUES(:id,:owner,:name,:username,:email,:hash,:role,:phone,:company,:permissions,\'lifetime\',:locked)');
 $s->execute(['id'=>$id,'owner'=>$owner,'name'=>$name,'username'=>$username,'email'=>$email,'hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role,'phone'=>$input['phone']??null,'company'=>$company,'permissions'=>json_encode($permissions,JSON_THROW_ON_ERROR),'locked'=>$locked]);
}
if($profile!==null){$s=$db->prepare('UPDATE users SET profile_json=:profile,company_name=:company,company_address=:address WHERE id=:id');$s->execute(['profile'=>json_encode($profile,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'company'=>trim((string)($input['companyName']??$target['company_name']??$name)),'address'=>$profile['address']??'','id'=>$id]);
 $s=$db->prepare('SELECT revision,document FROM tenant_workspaces WHERE tenant_id=:id FOR UPDATE');$s->execute(['id'=>$id]);$w=$s->fetch();$doc=$w?json_decode($w['document'],true):[];$doc['appSettings']['companyProfile']=$profile;$doc['appSettings']['companyName']=trim((string)($input['companyName']??$target['company_name']??$name));$doc['appSettings']['language']=$doc['appSettings']['language']??'en';$rev=$w?(int)$w['revision']+1:0;
 $s=$db->prepare('INSERT INTO tenant_workspaces(tenant_id,revision,document) VALUES(:id,:r,:d) ON DUPLICATE KEY UPDATE revision=VALUES(revision),document=VALUES(document)');$s->execute(['id'=>$id,'r'=>$rev,'d'=>json_encode($doc,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);}
logAudit($db,$actor['id'],$actor['name'],$action==='create_company'?'create':$action,'users',$id,'Account or project grants changed');
$s=$db->prepare('SELECT * FROM users WHERE id=:id');$s->execute(['id'=>$id]);$updated=$s->fetch();$db->commit();jsonResponse(true,['user'=>publicUser($updated)]);
