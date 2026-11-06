<?php
require_once __DIR__.'/office2.php';
function management_businesses(array $actor): array {global $pdo;$rows=$pdo->query('SELECT * FROM businesses ORDER BY name,id')->fetchAll();return domain_is_central($actor)?$rows:array_values(array_filter($rows,fn($b)=>(int)$b['owner_user_id']===(int)$actor['id']));}
function management_business(int $id,array $actor): array {global $pdo;$q=$pdo->prepare('SELECT * FROM businesses WHERE id=?');$q->execute([$id]);$b=$q->fetch();if(!$b||(!domain_is_central($actor)&&!domain_owner($actor,$id)))throw new RuntimeException('Access denied.',403);return $b;}
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
            $pdo->prepare('INSERT INTO businesses (name,owner_user_id) VALUES (?,?)')->execute([$name,$uid]);$bid=(int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO shops (name,owner_user_id,business_id,city,status,subscription_active,employee_limit,can_add_employees) VALUES (?,?,?,?,'active',1,10,1)")->execute([$name,$uid,$bid,office2_text($p,'city',100)]);$sid=(int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE users SET shop_id=? WHERE id=?')->execute([$sid,$uid]);
            $pdo->prepare('INSERT INTO branch_memberships (user_id,shop_id) VALUES (?,?)')->execute([$uid,$sid]);access_ensure_profile($uid);
            $pdo->prepare('UPDATE user_access SET must_change_password=1 WHERE user_id=?')->execute([$uid]);
            domain_audit('business_created','business',$bid,['name'=>$name,'owner_id'=>$uid]);
            // Explicit policy selection is accepted at account creation as well as editing.
            if(isset($p['policies']))management_policies('business',$bid,$p['policies']);
        }elseif($action==='create_branch'){
            $b=management_business((int)($p['business_id']??0),$actor);
            if(!$central)domain_require('branches.create');
            $pdo->prepare("INSERT INTO shops (name,owner_user_id,business_id,city,status,subscription_active,employee_limit,can_add_employees) VALUES (?,?,?,?,'active',1,10,1)")->execute([office2_text($p,'branch_name',150,true),$b['owner_user_id'],$b['id'],office2_text($p,'city',100,true)]);$id=(int)$pdo->lastInsertId();domain_audit('branch_created','branch',$id,['business_id'=>$b['id']]);
        }elseif(in_array($action,['create_staff','edit_staff'],true)){
            if(!$central)domain_require('staff.'.($action==='create_staff'?'create':'edit'));
            $b=management_business((int)($p['business_id']??0),$actor);$old=$action==='edit_staff'?management_user((int)($p['user_id']??0),$actor):null;
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
            if(isset($p['policies']))management_policies($central?'user':'owner_user',$uid,$p['policies']);
            domain_audit($action,'user',$uid,['fullname'=>$name,'status'=>$status,'branches'=>$ids],null,$old);
        }elseif($action==='save_policies'){
            $type=$p['scope_type']??'';$id=(int)($p['scope_id']??0);
            if($type==='business'){if(!$central)throw new RuntimeException('Central only.',403);management_business($id,$actor);}
            else{management_user($id,$actor);$type=$central?'user':'owner_user';}
            management_policies($type,$id,$p['policies']??[]);
        }elseif($action==='switch_branch'){
            $sid=(int)($p['shop_id']??0);if(!domain_branch_allowed($actor,$sid))throw new RuntimeException('Branch access denied.',403);
            $_SESSION['shop_context_id']=$sid;domain_audit('branch_opened','branch',$sid);unset($GLOBALS['_ui_preferences']);
        }else throw new InvalidArgumentException('Unknown action.');
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function management_policies(string $type,int $id,$policies): void {
    if(!is_array($policies)||count($policies)>250)throw new InvalidArgumentException('Invalid policies.');
    foreach($policies as $k=>$v){if(!is_array($v))throw new InvalidArgumentException('Invalid policy.');domain_save_policy($type,$id,$k,$v);}
}
