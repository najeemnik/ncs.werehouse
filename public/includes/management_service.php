<?php
require_once __DIR__.'/office2.php';
function management_businesses(array $actor): array {global $pdo;$rows=$pdo->query('SELECT * FROM businesses ORDER BY name,id')->fetchAll();return domain_is_central($actor)?$rows:array_values(array_filter($rows,fn($b)=>(int)$b['owner_user_id']===(int)$actor['id']));}
function management_business(int $id,array $actor): array {global $pdo;$q=$pdo->prepare('SELECT * FROM businesses WHERE id=?');$q->execute([$id]);$b=$q->fetch();if(!$b||(!domain_is_central($actor)&&!domain_owner($actor,$id)))throw new RuntimeException('Access denied.',403);return $b;}
function management_lock_business(int $business): array {
    global $pdo;$q=$pdo->prepare('SELECT * FROM businesses WHERE id=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$business]);$fresh=$q->fetch();if(!$fresh)throw new RuntimeException('Business not found.',404);return $fresh;
}
function management_business_usage(int $business): array {
    global $pdo;$q=$pdo->prepare('SELECT COUNT(*) FROM shops WHERE business_id=?');$q->execute([$business]);$branches=(int)$q->fetchColumn();
    $q=$pdo->prepare("SELECT COUNT(DISTINCT u.id) FROM users u JOIN branch_memberships m ON m.user_id=u.id JOIN shops s ON s.id=m.shop_id WHERE s.business_id=? AND u.role='staff'");$q->execute([$business]);return ['branches'=>$branches,'staff'=>(int)$q->fetchColumn()];
}
function management_limit(array $p,string $key,int $maximum=10000): int {
    $value=office2_text($p,$key,5);if($value==='')return 0;if(!ctype_digit($value)||(int)$value>$maximum)throw new InvalidArgumentException(__t('Enter a limit from 0 (unlimited) to 10000.','حد را بین صفر (نامحدود) تا ۱۰۰۰۰ بنویسید.','حد له صفر (بې حده) تر ۱۰۰۰۰ وټاکئ.'));return (int)$value;
}
function management_expiration(array $p): ?string {
    $date=office2_text($p,'subscription_expires_at',10);if($date==='')return null;$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException(__t('Invalid expiration date.','تاریخ انقضا نامعتبر است.','د پای نېټه ناسمه ده.'));return $date.' 23:59:59';
}
function management_user(int $id,array $actor): array {
    global $pdo;$q=$pdo->prepare('SELECT u.id,u.username,u.fullname,u.role,u.status,u.shop_id,s.business_id FROM users u LEFT JOIN shops s ON s.id=u.shop_id WHERE u.id=?');$q->execute([$id]);$u=$q->fetch();
    if(!$u||domain_is_central($u))throw new RuntimeException('Account not manageable here.',403);
    if(!domain_is_central($actor) && ((int)$actor['id']===$id||!domain_owner($actor,(int)$u['business_id'])||$u['role']!=='staff'))throw new RuntimeException('Access denied.',403);
    return $u;
}
function management_mutate(array $p): void {
    global $pdo;$actor=auth_user();if(!$actor||!csrf_verify($p['csrf_token']??''))throw new RuntimeException('Invalid session.',403);
    if(isset($p['policy_matrix_started'])&&((int)($p['policy_matrix_complete']??0)!==count(domain_catalog())||!is_array($p['policies']??null)||count($p['policies'])!==count(domain_catalog())))throw new InvalidArgumentException(__t('The permission form was incomplete. Refresh it and check the host input limit. No changes saved.','فورم صلاحیت‌ها ناقص رسید؛ آن را تازه کنید و حد ورودی هاست را بررسی کنید. تغییرات ذخیره نشد.','د اجازو فورمه نیمګړې وه؛ بدلونونه نه دي خوندي شوي.'));
    $action=$p['action']??'';$central=domain_is_central($actor);
    $pdo->beginTransaction();
    try{
        // Serialize membership, ownership and account-limit changes.
        $pdo->exec("UPDATE settings SET setting_value=setting_value WHERE setting_key='shop_name'");
        if($action==='create_business'){
            if(!$central)throw new RuntimeException('Central only.',403);
            $name=office2_text($p,'business_name',150,true);$username=office2_text($p,'username',50,true);$fullname=office2_text($p,'fullname',100,true);
            if(!preg_match('/^[a-zA-Z0-9_.@-]{3,50}$/D',$username))throw new InvalidArgumentException('Invalid username.');
            security_validate_new_password($p['password']??'',$p['confirm_password']??'');
            $pdo->prepare('INSERT INTO users (username,password,fullname,role,status,shop_id) VALUES (?,?,?,?,?,NULL)')->execute([$username,password_hash($p['password'],PASSWORD_DEFAULT),$fullname,'admin','active']);$uid=(int)$pdo->lastInsertId();
            $email=office2_text($p,'contact_email',190);if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException(__t('Invalid contact email.','ایمیل تماس نامعتبر است.','د اړیکې بریښنالیک ناسم دی.'));
            $pdo->prepare('INSERT INTO businesses (name,owner_user_id,contact_phone,contact_email,contact_address,subscription_active,subscription_expires_at,branch_limit,staff_limit,updated_at) VALUES (?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)')->execute([$name,$uid,office2_text($p,'contact_phone',40),$email,office2_text($p,'contact_address',1000),office2_bool($p+['subscription_active'=>'1'],'subscription_active'),management_expiration($p),management_limit($p,'branch_limit'),management_limit($p,'staff_limit')]);$bid=(int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO shops (name,owner_user_id,business_id,city,status,subscription_active,employee_limit,can_add_employees) VALUES (?,?,?,?,'active',1,10,1)")->execute([$name,$uid,$bid,office2_text($p,'city',100)]);$sid=(int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE users SET shop_id=? WHERE id=?')->execute([$sid,$uid]);
            $pdo->prepare('INSERT INTO branch_memberships (user_id,shop_id) VALUES (?,?)')->execute([$uid,$sid]);access_ensure_profile($uid);
            $pdo->prepare('UPDATE user_access SET must_change_password=1 WHERE user_id=?')->execute([$uid]);
            domain_audit('business_created','business',$bid,['name'=>$name,'owner_id'=>$uid]);
            // Explicit policy selection is accepted at account creation as well as editing.
            management_apply_requested_policies('business',$bid,$p);
        }elseif($action==='create_branch'){
            $b=management_business((int)($p['business_id']??0),$actor);
            if(!$central)domain_require('branches.create');
            $b=management_lock_business((int)$b['id']);if(!domain_business_operational($b))throw new RuntimeException(__t('This business is locked or its subscription has ended.','این تجارت قفل است یا اعتبار اشتراک آن پایان یافته است.','دا سوداګري بنده ده یا ګډون یې پای ته رسیدلی.'),403);$usage=management_business_usage((int)$b['id']);if((int)$b['branch_limit']>0&&$usage['branches']>=(int)$b['branch_limit'])throw new InvalidArgumentException(__t('The business branch limit is full.','حد شعبه‌های این تجارت تکمیل است.','د سوداګرۍ د څانګو حد پوره دی.'));
            $pdo->prepare("INSERT INTO shops (name,owner_user_id,business_id,city,status,subscription_active,employee_limit,can_add_employees) VALUES (?,?,?,?,'active',1,10,1)")->execute([office2_text($p,'branch_name',150,true),$b['owner_user_id'],$b['id'],office2_text($p,'city',100,true)]);$id=(int)$pdo->lastInsertId();domain_audit('branch_created','branch',$id,['business_id'=>$b['id']]);
        }elseif(in_array($action,['create_staff','edit_staff'],true)){
            if(!$central)domain_require('staff.'.($action==='create_staff'?'create':'edit'));
            $b=management_business((int)($p['business_id']??0),$actor);$old=$action==='edit_staff'?management_user((int)($p['user_id']??0),$actor):null;
            $b=management_lock_business((int)$b['id']);if(!domain_business_operational($b))throw new RuntimeException(__t('This business is locked or its subscription has ended.','این تجارت قفل است یا اعتبار اشتراک آن پایان یافته است.','دا سوداګري بنده ده یا ګډون یې پای ته رسیدلی.'),403);$usage=management_business_usage((int)$b['id']);if(!$old&&(int)$b['staff_limit']>0&&$usage['staff']>=(int)$b['staff_limit'])throw new InvalidArgumentException(__t('The business staff limit is full.','حد کارمندان این تجارت تکمیل است.','د سوداګرۍ د کارکوونکو حد پوره دی.'));
            if($old&&(int)$old['business_id']!==(int)$b['id'])throw new RuntimeException('Business mismatch.',403);
            $ids=$p['branch_ids']??[];if(!is_array($ids)||!$ids)throw new InvalidArgumentException('Choose at least one branch.');$ids=array_unique(array_map('intval',$ids));
            foreach($ids as $sid){$s=domain_branch($sid);if(!$s||(int)$s['business_id']!==(int)$b['id'])throw new RuntimeException('Invalid branch membership.',403);}
            $name=office2_text($p,'fullname',100,true);$status=$p['status']??'active';if(!in_array($status,['active','inactive'],true))throw new InvalidArgumentException('Invalid status.');
            $priorMemberships=[];if($old){$q=$pdo->prepare('SELECT shop_id FROM branch_memberships WHERE user_id=?');$q->execute([$old['id']]);$priorMemberships=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));}
            foreach($ids as $sid)if(!in_array($sid,$priorMemberships,true)){$s=domain_branch($sid);if(!(int)$s['can_add_employees']||domain_branch_employee_count($sid)>=(int)$s['employee_limit'])throw new InvalidArgumentException(__t('Branch employee limit reached.','حد کارمندان شعبه تکمیل است.','د څانګې د کارکوونکو حد پوره دی.'));}
            if(!$old){
                $username=office2_text($p,'username',50,true);if(!preg_match('/^[a-zA-Z0-9_.@-]{3,50}$/D',$username))throw new InvalidArgumentException('Invalid username.');security_validate_new_password($p['password']??'',$p['confirm_password']??'');
                $pdo->prepare('INSERT INTO users (username,password,fullname,role,status,shop_id) VALUES (?,?,?,?,?,?)')->execute([$username,password_hash($p['password'],PASSWORD_DEFAULT),$name,'staff',$status,$ids[0]]);$uid=(int)$pdo->lastInsertId();access_ensure_profile($uid);$pdo->prepare('UPDATE user_access SET must_change_password=1 WHERE user_id=?')->execute([$uid]);
            }else{$uid=(int)$old['id'];$pdo->prepare('UPDATE users SET fullname=?,status=?,shop_id=? WHERE id=?')->execute([$name,$status,$ids[0],$uid]);$pdo->prepare('DELETE FROM branch_memberships WHERE user_id=?')->execute([$uid]);office2_revoke($uid);}
            foreach($ids as $sid)$pdo->prepare('INSERT INTO branch_memberships (user_id,shop_id) VALUES (?,?)')->execute([$uid,$sid]);
            management_apply_requested_policies($central?'user':'owner_user',$uid,$p);
            domain_audit($action,'user',$uid,['fullname'=>$name,'status'=>$status,'branches'=>$ids],null,$old);
        }elseif($action==='update_business'){
            if(!$central)throw new RuntimeException('Central only.',403);$b=management_business((int)($p['business_id']??0),$actor);
            $name=office2_text($p,'business_name',150,true);$status=office2_text($p,'business_status',20,true);if(!in_array($status,['active','inactive','locked'],true))throw new InvalidArgumentException('Invalid business status.');
            $email=office2_text($p,'contact_email',190);if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException(__t('Invalid contact email.','ایمیل تماس نامعتبر است.','د اړیکې بریښنالیک ناسم دی.'));
            $branchLimit=management_limit($p,'branch_limit');$staffLimit=management_limit($p,'staff_limit');$b=management_lock_business((int)$b['id']);$usage=management_business_usage((int)$b['id']);
            if(($branchLimit>0&&$branchLimit<$usage['branches'])||($staffLimit>0&&$staffLimit<$usage['staff']))throw new InvalidArgumentException(__t('A new limit cannot be lower than the existing branch or staff count.','حد تازه نمی‌تواند از تعداد فعلی شعبه یا کارمند کمتر باشد.','نوی حد د اوسنیو څانګو یا کارکوونکو له شمېر کم نشي کېدای.'));
            $active=office2_bool($p,'subscription_active');$expires=management_expiration($p);
            $pdo->prepare('UPDATE businesses SET name=?,status=?,contact_phone=?,contact_email=?,contact_address=?,subscription_active=?,subscription_expires_at=?,branch_limit=?,staff_limit=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$name,$status,office2_text($p,'contact_phone',40),$email,office2_text($p,'contact_address',1000),$active,$expires,$branchLimit,$staffLimit,$b['id']]);
            domain_audit('business_updated','business',$b['id'],['name'=>$name,'status'=>$status,'subscription_active'=>$active,'subscription_expires_at'=>$expires,'branch_limit'=>$branchLimit,'staff_limit'=>$staffLimit],null,$b);
        }elseif($action==='save_policies'){
            $type=$p['scope_type']??'';$id=(int)($p['scope_id']??0);
            if($type==='business'){if(!$central)throw new RuntimeException('Central only.',403);management_business($id,$actor);}
            else{management_user($id,$actor);$type=$central?'user':'owner_user';}
            management_apply_requested_policies($type,$id,$p);
        }elseif($action==='switch_branch'){
            $sid=(int)($p['shop_id']??0);if(!domain_branch_allowed($actor,$sid))throw new RuntimeException('Branch access denied.',403);
            $_SESSION['shop_context_id']=$sid;domain_audit('branch_opened','branch',$sid);unset($GLOBALS['_ui_preferences']);
        }else throw new InvalidArgumentException('Unknown action.');
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function management_apply_requested_policies(string $type,int $id,array $p): void {
    $preset=$p['permission_preset']??'custom';
    if($preset==='inherit')return;
    if(!in_array($preset,['custom','full','read_only'],true))throw new InvalidArgumentException('Invalid permission preset.');
    if($preset==='custom'){management_policies($type,$id,$p['policies']??[]);return;}
    $policies=[];foreach(domain_catalog() as $feature=>$unused){$action=str_contains($feature,'.')?substr($feature,strrpos($feature,'.')+1):'module';$allowed=$preset==='full'||$action==='module'||in_array($action,['view','print'],true);$policies[$feature]=['mode'=>$allowed?'enabled':'locked','daily_limit'=>'','monthly_limit'=>''];}
    management_policies($type,$id,$policies);
    // A central quick setup also establishes ordinary tenant access. Employee AI still needs the owner's separate approval.
    if($type==='user'&&domain_is_central(auth_user())){foreach($policies as $feature=>$unused)if(explode('.',$feature)[0]==='ai')$policies[$feature]['mode']='locked';management_policies('owner_user',$id,$policies);}
}
function management_policies(string $type,int $id,$policies): void {
    if(!is_array($policies)||count($policies)>250)throw new InvalidArgumentException('Invalid policies.');
    foreach($policies as $k=>$v){if(!is_array($v))throw new InvalidArgumentException('Invalid policy.');domain_save_policy($type,$id,$k,$v);}
}
