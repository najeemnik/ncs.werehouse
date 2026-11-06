<?php
/** Primary-only account controls; URL secrecy never replaces session authorization. */
require_once __DIR__.'/db.php';require_once __DIR__.'/design.php';
sendSecurityHeaders();$db=Database::getConnection();$actor=authenticateUser();requireOffice7Owner($actor);
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,null,'Method is not allowed.',405);
$input=readJson(32768);$action=$input['action']??'';$id=$input['id']??'';$version=$input['version']??null;
if(!is_string($id)||!is_int($version)||$version<1||!in_array($action,['password','modules'],true))jsonResponse(false,null,'Invalid account update.',400);
if($action==='password'){
 $password=$input['password']??null;
 if(!is_string($password)||strlen($password)<12||strlen($password)>72||str_contains($password,"\0"))jsonResponse(false,null,'Password must contain 12–72 bytes.',400);
 // Verify the primary account for every password reset, not only its own reset.
 $bucket=hash('sha256','office-password:'.$actor['id'].':'.($_SERVER['REMOTE_ADDR']??''));
 $s=$db->prepare('INSERT INTO auth_rate_limits(bucket_key,attempts,window_started) VALUES(:key,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE attempts=IF(window_started<UTC_TIMESTAMP()-INTERVAL 15 MINUTE,1,attempts+1),window_started=IF(window_started<UTC_TIMESTAMP()-INTERVAL 15 MINUTE,UTC_TIMESTAMP(),window_started)');$s->execute(['key'=>$bucket]);
 $s=$db->prepare('SELECT attempts FROM auth_rate_limits WHERE bucket_key=:key');$s->execute(['key'=>$bucket]);if((int)$s->fetchColumn()>8)jsonResponse(false,null,'Please try again later.',429);
 if(!is_string($input['currentPassword']??null)||!password_verify($input['currentPassword'],$actor['password_hash']))jsonResponse(false,null,'The primary administrator password is incorrect.',400);
}
if($action==='modules'){
 if($id===$actor['id'])jsonResponse(false,null,'Primary administrator recovery access cannot be disabled.',403);
 $modules=$input['modules']??null;$catalog=uiCatalog();$routes=array_merge(array_column($catalog['modules'],'id'),['dashboard','reports','accounting','audit_logs','services','settings','staff','notifications','drive','backup','assistant','guide','scanner','about','projectAppearance','documentPrint','calculator','equity']);
 if(!is_array($modules)||(array_is_list($modules)&&count($modules))||count($modules)>100)jsonResponse(false,null,'Invalid service controls.',400);
 foreach($modules as $key=>$value)if(!is_bool($value)||(!in_array($key,$routes,true)&&!preg_match('/^custom_[a-zA-Z0-9-]{1,60}$/D',$key)))jsonResponse(false,null,'Invalid service control.',400);
}
$db->beginTransaction();
if($action==='password'){$s=$db->prepare('SELECT password_hash,session_version FROM users WHERE id=:id FOR UPDATE');$s->execute(['id'=>$actor['id']]);$current=$s->fetch();if(!$current||(int)$current['session_version']!==(int)$actor['session_version']||!password_verify($input['currentPassword'],$current['password_hash']))jsonResponse(false,null,'Administrator credentials changed. Sign in again.',409);}
$s=$db->prepare('SELECT * FROM users WHERE id=:id FOR UPDATE');$s->execute(['id'=>$id]);$target=$s->fetch();
if(!$target)jsonResponse(false,null,'User was not found.',404);
if($target['is_master']&&$id!==$actor['id'])jsonResponse(false,null,'Other primary accounts require host recovery.',403);
if((int)$target['session_version']!==$version)jsonResponse(false,null,'Another session changed this account. Refresh.',409);
if($action==='password'){
 $s=$db->prepare('UPDATE users SET password_hash=:hash,session_version=session_version+1 WHERE id=:id');$s->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$id]);
 $s=$db->prepare('UPDATE password_resets SET used_at=UTC_TIMESTAMP() WHERE user_id=:id AND used_at IS NULL');$s->execute(['id'=>$id]);
 logAudit($db,$actor['id'],$actor['name'],'update','office7',$id,'Password changed; other sessions and recovery tokens revoked');
}else{
 $s=$db->prepare('UPDATE users SET allowed_modules_json=:modules,session_version=session_version+1 WHERE id=:id');$s->execute(['modules'=>json_encode($modules,JSON_THROW_ON_ERROR),'id'=>$id]);
 logAudit($db,$actor['id'],$actor['name'],'update','office7',$id,json_encode(['serviceControls'=>['before'=>json_decode($target['allowed_modules_json']??'{}',true),'after'=>$modules]],JSON_THROW_ON_ERROR));
}
$s=$db->prepare('SELECT * FROM users WHERE id=:id');$s->execute(['id'=>$id]);$updated=$s->fetch();$db->commit();
if($id===$actor['id']){session_regenerate_id(true);$_SESSION['version']=(int)$updated['session_version'];}
if($action==='password'){$s=$db->prepare('DELETE FROM auth_rate_limits WHERE bucket_key=:key');$s->execute(['key'=>$bucket]);}
jsonResponse(true,['user'=>publicUser($updated)]);
