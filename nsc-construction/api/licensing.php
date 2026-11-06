<?php
/** Exclusive Office Seven identity and live account/company license enforcement. */
function office7Control(): ?array {
 static $control=null,$loaded=false;if($loaded)return $control;$loaded=true;$db=Database::getConnection();
 try{$control=$db->query('SELECT * FROM office7_control WHERE id=1')->fetch()?:null;}catch(PDOException $e){if($e->getCode()==='42S02')return null;throw $e;}
 if(!$control){$configured=getenv('OFFICE7_OWNER_USER_ID')?:'';if($configured!==''){$s=$db->prepare('SELECT id FROM users WHERE id=:id AND is_master=1 AND owner_admin_id IS NULL AND is_locked=0 AND office7_locked=0');$s->execute(['id'=>$configured]);$id=$s->fetchColumn();}else{$id=$db->query('SELECT id FROM users WHERE is_master=1 AND owner_admin_id IS NULL AND is_locked=0 AND office7_locked=0 ORDER BY created_at ASC,id ASC LIMIT 1')->fetchColumn();}if(!$id)return null;$s=$db->prepare('INSERT IGNORE INTO office7_control(id,owner_user_id,path_token) VALUES(1,:uid,:token)');$s->execute(['uid'=>$id,'token'=>bin2hex(random_bytes(32))]);$control=$db->query('SELECT * FROM office7_control WHERE id=1')->fetch()?:null;}
 return $control;
}
function isOffice7Owner(array $u): bool {return !empty($u['is_master'])&&empty($u['owner_admin_id'])&&($u['id']??null)===(office7Control()['owner_user_id']??null);}
function requireOffice7Owner(array $u): void {if(!isOffice7Owner($u))jsonResponse(false,null,'Private Office Seven access is required.',403);}
function office7Link(array $u): ?string {return isOffice7Owner($u)?'#/office7/'.office7Control()['path_token']:null;}
function licenseParent(array $u): ?array {
 if(empty($u['owner_admin_id']))return null;static $cache=[];$id=$u['owner_admin_id'];if(!array_key_exists($id,$cache)){$s=Database::getConnection()->prepare('SELECT * FROM users WHERE id=:id');$s->execute(['id'=>$id]);$cache[$id]=$s->fetch()?:null;}return $cache[$id];
}
function licenseExpiry(?string $value): ?DateTimeImmutable {if(!$value)return null;try{return new DateTimeImmutable($value,new DateTimeZone('UTC'));}catch(Throwable $e){throw new LogicException('Invalid stored license expiry');}}
function licenseInfo(array $u): array {
 $parent=licenseParent($u);$own=licenseExpiry($u['subscription_expires_at']??null);$company=licenseExpiry($parent['subscription_expires_at']??null);$effective=$own&&$company?($own<$company?$own:$company):($own??$company);$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
 $locked=!empty($u['is_locked'])||!empty($u['office7_locked']);$companyLocked=!empty($u['owner_admin_id'])&&(!$parent||!empty($parent['is_locked'])||!empty($parent['office7_locked']));$protected=isOffice7Owner($u);$expired=!$protected&&$effective&&$effective<=$now;
 return ['licenseStatus'=>$locked?'locked':($companyLocked?'companyLocked':($expired?'expired':'active')),'daysRemaining'=>$protected||!$effective?null:max(0,(int)ceil(($effective->getTimestamp()-$now->getTimestamp())/86400)),
 'ownDaysRemaining'=>$own?max(0,(int)ceil(($own->getTimestamp()-$now->getTimestamp())/86400)):null,'effectiveExpiresAt'=>$protected?null:$effective?->format('Y-m-d\TH:i:s\Z'),'subscriptionExpiresAt'=>$own?->format('Y-m-d\TH:i:s\Z'),
 'subscriptionExpiryDate'=>$own?->setTimezone(new DateTimeZone('Asia/Kabul'))->format('Y-m-d'),'companyExpiresAt'=>$company?->format('Y-m-d\TH:i:s\Z'),'isLockedBySuperAdmin'=>!empty($u['office7_locked']),'locallyDisabled'=>!empty($u['is_locked']),'companyLocked'=>$companyLocked,'licenseUnlimited'=>$protected||!$effective,'isOffice7Owner'=>$protected];
}
function assertLicenseAccess(array $u): void {$status=licenseInfo($u)['licenseStatus'];if($status!=='active'){if(session_status()===PHP_SESSION_ACTIVE&&!empty($_SESSION['uid'])){$_SESSION=[];session_destroy();}jsonResponse(false,['code'=>$status==='expired'?'licenseExpired':($status==='companyLocked'?'companyLocked':'accountLocked')],'Account or company access is locked or expired.',403);}}
function licenseDateInput($value): string {
 if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value))jsonResponse(false,null,'Invalid license expiry date.',400);
 $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('Asia/Kabul'));if(!$date||$date->format('Y-m-d')!==$value||$value<'2000-01-01'||$value>'2199-12-31')jsonResponse(false,null,'Invalid license expiry date.',400);
 return $date->setTime(23,59,59)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
function updateOfficeLicense(PDO $db,array $actor,array $input,string $action): array {
 requireOffice7Owner($actor);$id=$input['id']??$input['userId']??null;$version=$input['version']??null;
 if(!is_string($id)||!is_int($version)||$version<1)jsonResponse(false,null,'User and current version are required.',400);
 $note=$input['reason']??'Office Seven account access update';if(!is_string($note)||strlen($note)>4000||str_contains($note,"\0"))jsonResponse(false,null,'Invalid account note.',400);
 $db->beginTransaction();try{$s=$db->prepare('SELECT * FROM users WHERE id=:id FOR UPDATE');$s->execute(['id'=>$id]);$target=$s->fetch();if(!$target){$db->rollBack();jsonResponse(false,null,'User was not found.',404);}if(isOffice7Owner($target)){$db->rollBack();jsonResponse(false,null,'The primary administrator cannot be locked or expired here.',403);}if((int)$target['session_version']!==$version){$db->rollBack();jsonResponse(false,null,'Another administrator changed this account. Refresh before saving.',409);}
 $plan=$target['subscription_plan'];$expiry=$target['subscription_expires_at'];$locked=(bool)($target['office7_locked']??false);$localLocked=(bool)$target['is_locked'];
 if($action==='toggle_lock'){if(!is_bool($input['isLocked']??null)){$db->rollBack();jsonResponse(false,null,'A lock state is required.',400);}$locked=$input['isLocked'];if(array_key_exists('clearLocalDisable',$input)&&!is_bool($input['clearLocalDisable'])){$db->rollBack();jsonResponse(false,null,'Invalid local activation state.',400);}if(!$locked&&!empty($input['clearLocalDisable']))$localLocked=false;}
 else{$operation=$input['operation']??'set_days';if(!in_array($operation,['set_days','add_days','set_date','unlimited'],true)){$db->rollBack();jsonResponse(false,null,'Invalid license operation.',400);}
 $plan=$input['plan']??'pro';if(!in_array($plan,['trial','pro','enterprise','lifetime'],true)){$db->rollBack();jsonResponse(false,null,'Invalid license plan.',400);}
 if($operation==='unlimited'){$plan='lifetime';$expiry=null;}
 elseif($operation==='set_date'){$expiry=licenseDateInput($input['expiryDate']??null);if($plan==='lifetime')$plan='pro';}
 else{$days=$input['days']??null;if(!is_int($days)||$days<($operation==='add_days'?1:0)||$days>36500){$db->rollBack();jsonResponse(false,null,'Days must be a whole number from 0 to 36500.',400);}if($operation==='add_days'&&!$expiry){$db->rollBack();jsonResponse(false,null,'Choose a finite license before adding days.',400);}$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$base=$operation==='add_days'&&licenseExpiry($expiry)>$now?licenseExpiry($expiry):$now;$expiry=$base->modify('+'.$days.' days')->format('Y-m-d H:i:s');if($plan==='lifetime')$plan='pro';}
 }
 $s=$db->prepare('UPDATE users SET subscription_plan=:plan,subscription_expires_at=:expiry,office7_locked=:locked,is_locked=:localLocked,session_version=session_version+1 WHERE id=:id');$s->execute(['plan'=>$plan,'expiry'=>$expiry,'locked'=>$locked?1:0,'localLocked'=>$localLocked?1:0,'id'=>$id]);
 logAudit($db,$actor['id'],$actor['name'],$action==='toggle_lock'?($locked?'lock':'unlock'):'update','licenses',$id,json_encode(['before'=>['plan'=>$target['subscription_plan'],'expiry'=>$target['subscription_expires_at'],'locked'=>(bool)($target['office7_locked']??false),'locallyDisabled'=>(bool)$target['is_locked'],'version'=>(int)$target['session_version']],'after'=>['plan'=>$plan,'expiry'=>$expiry,'locked'=>$locked,'locallyDisabled'=>$localLocked,'version'=>$version+1],'reason'=>$note],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
 $s=$db->prepare('SELECT * FROM users WHERE id=:id');$s->execute(['id'=>$id]);$user=$s->fetch();$db->commit();return publicUser($user);
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
