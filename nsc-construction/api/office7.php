<?php
require_once __DIR__.'/db.php';sendSecurityHeaders();$db=Database::getConnection();$actor=authenticateUser();requireOffice7Owner($actor);$method=$_SERVER['REQUEST_METHOD'];$action=$_GET['action']??($method==='GET'?'tenants':'update_license');
if($method==='GET'){
 if($action==='tenants')jsonResponse(true,['users'=>array_map('publicUser',$db->query('SELECT * FROM users ORDER BY is_master DESC,created_at DESC,id ASC')->fetchAll()),'office7Link'=>office7Link($actor),'serverTime'=>gmdate('Y-m-d\TH:i:s\Z')]);
 if($action==='audit_logs'){$limit=max(1,min((int)($_GET['limit']??100),500));$s=$db->prepare('SELECT * FROM system_audit_logs WHERE module IN (\'licenses\',\'office7\') ORDER BY created_at DESC,id DESC LIMIT :lim');$s->bindValue(':lim',$limit,PDO::PARAM_INT);$s->execute();jsonResponse(true,['logs'=>$s->fetchAll()]);}
}
if($method==='POST'){$input=readJson(131072);
 if(in_array($action,['update_license','toggle_lock'],true))jsonResponse(true,['user'=>updateOfficeLicense($db,$actor,$input,$action)]);
 if($action==='rotate_link'){$version=$input['version']??null;if(!is_int($version)||$version<1)jsonResponse(false,null,'A current link version is required.',400);$db->beginTransaction();try{$s=$db->query('SELECT * FROM office7_control WHERE id=1 FOR UPDATE');$before=$s->fetch();if((int)$before['revision']!==$version){$db->rollBack();jsonResponse(false,null,'Another session changed this link. Refresh.',409);}$token=bin2hex(random_bytes(32));$s=$db->prepare('UPDATE office7_control SET path_token=:token,revision=revision+1 WHERE id=1');$s->execute(['token'=>$token]);logAudit($db,$actor['id'],$actor['name'],'update','office7',$actor['id'],'Private navigation link rotated');$db->commit();jsonResponse(true,['office7Link'=>'#/office7/'.$token,'revision'=>$version+1]);}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}}
 if($action==='change_gate_key')jsonResponse(false,null,'Gate-key authentication is removed. Use your signed-in primary administrator account.',410);
}
jsonResponse(false,null,'Action is not available.',404);
