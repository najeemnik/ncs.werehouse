<?php
/** Shared tenant and account policy mutations, including legacy compatibility. */
function management_apply_requested_policies(string $type,int $id,array $p): void {
    $preset=$p['permission_preset']??'custom';
    if($preset==='inherit')return;
    if(!in_array($preset,['custom','full','read_only'],true))throw new InvalidArgumentException('Invalid permission preset.');
    if($preset==='custom'){ $policies=$p['policies']??[];if(!is_array($policies)||count($policies)!==count(domain_catalog())||(int)($p['policy_matrix_complete']??0)!==count(domain_catalog()))throw new InvalidArgumentException('Incomplete policy matrix.');foreach($policies as $feature=>$policy){if(!isset(domain_catalog()[$feature])||!is_array($policy))throw new InvalidArgumentException('Invalid policy matrix.');if(($policy['mode']??'')==='inherit')unset($policies[$feature]);}management_policies($type,$id,$policies);return;}
    $policies=[];foreach(domain_catalog() as $feature=>$unused){$action=str_contains($feature,'.')?substr($feature,strrpos($feature,'.')+1):'module';$allowed=$preset==='full'||$action==='module'||in_array($action,['view','print'],true)||str_starts_with($feature,'dashboard.');$policies[$feature]=['mode'=>$allowed?'enabled':'locked','daily_limit'=>'','monthly_limit'=>''];}
    management_policies($type,$id,$policies);
    // A central quick setup also establishes ordinary tenant access. Employee AI still needs the owner's separate approval.
    if($type==='user'&&domain_is_central(auth_user())){foreach($policies as $feature=>$unused)if(explode('.',$feature)[0]==='ai')$policies[$feature]['mode']='locked';management_policies('owner_user',$id,$policies);}
}
function management_policies(string $type,int $id,$policies): void {
    if(!is_array($policies)||count($policies)>250)throw new InvalidArgumentException('Invalid policies.');
    foreach($policies as $k=>$v){if(!is_array($v))throw new InvalidArgumentException('Invalid policy.');domain_save_policy($type,$id,$k,$v);if($type==='user')management_sync_legacy_user_feature($id,$k,$v);}
}

function management_sync_legacy_user_feature(int $user,string $feature,array $policy): void {
    global $pdo;static $map=null;if($map===null){$map=[];foreach(get_lockable_features() as $legacy=>$unused)$map[domain_legacy_feature($legacy)]=$legacy;}
    if(!isset($map[$feature]))return;$legacy=$map[$feature];$mode=$policy['mode']??'locked';$allowed=$mode==='enabled'?'1':($mode==='hidden'?'hidden':'0');
    access_ensure_profile($user);
    if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$pdo->prepare('INSERT INTO user_feature_access (user_id,feature_key,allowed,updated_at) VALUES (?,?,?,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed),updated_at=CURRENT_TIMESTAMP')->execute([$user,$legacy,$allowed]);
    else $pdo->prepare('INSERT INTO user_feature_access (user_id,feature_key,allowed,updated_at) VALUES (?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(user_id,feature_key) DO UPDATE SET allowed=excluded.allowed,updated_at=CURRENT_TIMESTAMP')->execute([$user,$legacy,$allowed]);
}
