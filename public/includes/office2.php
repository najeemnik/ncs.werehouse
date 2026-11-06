<?php
/** Office 2 services: existing accounts, roles, feature locks, sessions and audit table. */
require_once __DIR__.'/auth.php';
require_once __DIR__.'/office2_shops.php';
function office2_url(){return BASE_URL.'admin/office_2.php';}
function office2_authorized() {
    $u=auth_user();return $u && domain_is_central($u) && totp_session_valid() && empty($_SESSION['screen_locked']) && !(int)access_profile($u['id'])['must_change_password'];
}
function office2_actor() {
    if(!office2_authorized())throw new RuntimeException(__t('Office 2 authorization is required.','مجوز دفتر دو لازم است.','د دفتر دو اجازه اړینه ده.'),403);
    return auth_user();
}
function office2_require() {
    auth_require('admin');
    if(!office2_authorized()){
        if(access_json_request()||($_SERVER['REQUEST_METHOD']??'GET')!=='GET')access_deny(403,'office2_required',__t('Office 2 authorization is required.','مجوز دفتر دو لازم است.','د دفتر دو اجازه اړینه ده.'));
        redirect(office2_url());
    }
}
function office2_step_up($secret) {
    if(!auth_check()||!domain_is_central(auth_user())||(int)access_profile(auth_user()['id'])['must_change_password']||!empty($_SESSION['screen_locked']))return false;
    if(!security_attempt('office2-step-up',(string)auth_user()['id']))return false;
    if(!security_pin_matches($secret,get_setting('super_admin_pin','')))return false;
    session_regenerate_id(true);unset($_SESSION['csrf_token']);$_SESSION['super_admin_verified']=true;$_SESSION['office2_version']=get_setting('office2.credential_version','0');
    security_clear_attempts('office2-step-up',(string)auth_user()['id']);
    office2_audit('step_up',auth_user()['id']);return true;
}
function office2_audit($action,$target=0,array $details=[],$actor=null) {
    global $pdo;
    // Explicit metadata only. Callers never pass passwords, PINs, prompts or hashes.
    $payload=['target_user'=>(int)$target]+$details;
    $pdo->prepare('INSERT INTO activity_logs (user_id,action,details,ip_address,created_at) VALUES (?,?,?,?,?)')->execute([$actor??(int)($_SESSION['user_id']??0),'Office 2: '.$action,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),substr($_SERVER['REMOTE_ADDR']??'local',0,50),gmdate('Y-m-d H:i:s')]);
}
function office2_setting($key,$value){if(!update_setting($key,(string)$value))throw new RuntimeException('Administrative setting could not be saved.');}
function office2_transaction(callable $fn) {
    global $pdo;$pdo->beginTransaction();
    try{
        // Serialize administrative changes, including the last-super-admin check.
        $pdo->exec($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?"INSERT OR IGNORE INTO settings (setting_key,setting_value) VALUES ('office2.write_lock','0')":"INSERT IGNORE INTO settings (setting_key,setting_value) VALUES ('office2.write_lock','0')");
        $pdo->exec("UPDATE settings SET setting_value=setting_value WHERE setting_key='office2.write_lock'");
        $result=$fn();$pdo->commit();return $result;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function office2_roles() {
    global $pdo;$roles=['staff','admin','superadmin'];
    foreach($pdo->query('SELECT DISTINCT role FROM users')->fetchAll(PDO::FETCH_COLUMN) as $role)if(is_string($role)&&preg_match('/^[a-z_]{1,20}$/D',$role))$roles[]=$role;
    return array_values(array_unique($roles));
}
function office2_text(array $p,$key,$max,$required=false) {
    $v=$p[$key]??'';
    if(!is_string($v)||mb_strlen($v,'UTF-8')>$max||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$v))throw new InvalidArgumentException(__t('Invalid input.','ورودی نامعتبر است.','ننوت ناسمه ده.'));
    $v=trim($v);if($required&&$v==='')throw new InvalidArgumentException(__t('Required fields are missing.','فیلدهای ضروری را تکمیل کنید.','اړینې خانې ډکې کړئ.'));return $v;
}
function office2_bool(array $p,$key){$v=$p[$key]??'0';if(!in_array($v,['0','1',0,1],true))throw new InvalidArgumentException('Invalid toggle.');return (int)$v;}
function office2_target($id) {
    global $pdo;
    if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)throw new InvalidArgumentException('Invalid account.');
    $q=$pdo->prepare('SELECT id,username,fullname,role,status,shop_id,created_at FROM users WHERE id=?');$q->execute([(int)$id]);$u=$q->fetch();
    if(!$u)throw new InvalidArgumentException(__t('Account not found.','حساب یافت نشد.','حساب ونه موندل شو.'));return $u;
}
function office2_reauthenticate(array $p) {
    if(!security_current_password($p['current_password']??''))throw new InvalidArgumentException(__t('Current administrator password is incorrect, or too many attempts.','رمز فعلی مدیر نادرست است یا تلاش‌های زیادی انجام شده است.','د مدیر اوسنی رمز ناسم دی یا ډېرې هڅې شوې دي.'));
}
function office2_revoke($id){office2_setting('security.user_version.'.(int)$id,(int)security_user_version($id)+1);}
function office2_guard_admin(array $old,$newRole,$status,$locked) {
    if((int)$old['id']===(int)($_SESSION['user_id']??0)&&($newRole!==$old['role']||$status!=='active'||$locked))throw new InvalidArgumentException(__t('You cannot remove your own administrative access. Use another authorized administrator.','دسترسی مدیریتی خود را نمی‌توانید قطع کنید؛ مدیر مجاز دیگری باید این کار را انجام دهد.','خپل مدیریتي لاسرسی مه بندوئ؛ بل مجاز مدیر دې دا کار وکړي.'));
    if(access_super_role($old['role'])&&(!access_super_role($newRole)||$status!=='active'||$locked)){
        global $pdo;$q=$pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN user_access a ON a.user_id=u.id WHERE u.id<>? AND u.role IN ('superadmin','super_admin') AND u.status='active' AND COALESCE(a.is_locked,0)=0 AND COALESCE(a.must_change_password,0)=0");$q->execute([$old['id']]);
        if((int)$q->fetchColumn()===0)throw new InvalidArgumentException(__t('The last active Super Admin must be retained.','آخرین سوپر ادمین فعال باید حفظ شود.','وروستی فعال سوپر مدیر باید وساتل شي.'));
    }
}
function office2_set_feature($id,$key,$allowed) {
    global $pdo;
    if(!is_string($key)||!isset(get_lockable_features()[$key]))throw new InvalidArgumentException('Unknown feature.');
    if($allowed==='inherit'){domain_legacy_policy('user',(int)$id,$key,'inherit');$pdo->prepare('DELETE FROM user_feature_access WHERE user_id=? AND feature_key=?')->execute([$id,$key]);return;}
    if(!in_array($allowed,['0','1',0,1,'hidden'],true))throw new InvalidArgumentException('Invalid feature permission.');
    $sql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO user_feature_access (user_id,feature_key,allowed) VALUES (?,?,?) ON CONFLICT(user_id,feature_key) DO UPDATE SET allowed=excluded.allowed,updated_at=CURRENT_TIMESTAMP':'INSERT INTO user_feature_access (user_id,feature_key,allowed) VALUES (?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed),updated_at=CURRENT_TIMESTAMP';
    $pdo->prepare($sql)->execute([$id,$key,$allowed==='1'||$allowed===1?1:0]);$feature=domain_legacy_feature($key);if(isset(domain_catalog()[$feature]))domain_save_policy('user',(int)$id,$feature,['enabled'=>$allowed==='1'||$allowed===1?'1':'0','visibility'=>$allowed==='hidden'?'hidden':'locked']);
}
function office2_history($userId=null,$action=null,$limit=150) {
    global $pdo;$where="action LIKE 'Office 2: %'";$params=[];
    if($action!==null){$where.=' AND action=?';$params[]='Office 2: '.$action;}
    if($userId!==null){$where.=' AND details LIKE ?';$params[]='%"target_user":'.(int)$userId.',%';}
    $q=$pdo->prepare('SELECT id,user_id,action,details,created_at FROM activity_logs WHERE '.$where.' ORDER BY id DESC LIMIT '.min(500,max(1,(int)$limit)));$q->execute($params);return $q->fetchAll();
}
function office2_users() {
    global $pdo;
    return $pdo->query('SELECT u.id,u.username,u.fullname,u.role,u.status,u.created_at,COALESCE(a.is_locked,0) AS is_locked,COALESCE(a.must_change_password,0) AS must_change_password,COALESCE(a.subscription_active,1) AS subscription_active,a.subscription_expires_at FROM users u LEFT JOIN user_access a ON a.user_id=u.id ORDER BY u.id')->fetchAll();
}
function office2_user_details($id) {
    global $pdo;$u=office2_target($id);$p=access_profile($u['id']);$q=$pdo->prepare('SELECT feature_key,allowed FROM user_feature_access WHERE user_id=?');$q->execute([$u['id']]);
    $u['access']=$p;$u['features']=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    $policy=domain_policy('user',(int)$u['id'],'ai');$branch=domain_branch((int)($u['shop_id']??0));$isOwner=$branch&&(int)$branch['business_owner']===(int)$u['id'];$u['features']['ai_advisor']=(int)($policy['enabled']??($isOwner?1:0));$p['ai_daily_limit']=$policy['daily_limit']??null;$p['ai_monthly_limit']=$policy['monthly_limit']??null;$u['access']=$p;
    $since=$p['ai_reset_at']??'1970-01-01 00:00:00';$day=(new DateTimeImmutable('today',new DateTimeZone('Asia/Kabul')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');$month=(new DateTimeImmutable('first day of this month 00:00:00',new DateTimeZone('Asia/Kabul')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');$resetId=(int)get_setting('security.ai_reset_after.'.(int)$u['id'],'0');$counts=[];foreach(['today'=>max($since,$day),'month'=>max($since,$month),'lifetime'=>'1970-01-01 00:00:00'] as $name=>$from){$q=$pdo->prepare("SELECT COUNT(*) FROM security_events WHERE actor_id=? AND event_type='advisor_request' AND created_at>=? AND id>?");$q->execute([$u['id'],$from,$name==='lifetime'?0:$resetId]);$counts[$name]=(int)$q->fetchColumn();}$u['ai_usage']=$counts+['engine'=>'configured_provider'];return $u;
}
function office2_invoice_fields() {
    return ['shop_name','invoice_title','shop_slogan','shop_phone_central','shop_phone_secondary','shop_phone_whatsapp','shop_address_central','shop_address_secondary','currency_title','invoice_footer_note','invoice_thank_you','invoice_signature_shop_text','invoice_signature_cust_text','invoice_logo_position','invoice_watermark_opacity','invoice_watermark_size','invoice_accent_color','invoice_border_style','invoice_format','auto_print_invoice'];
}
function office2_invoice_toggles(){return ['invoice_show_phone_secondary','invoice_show_whatsapp','invoice_show_address_secondary','invoice_show_terms','invoice_show_signatures','invoice_show_barcode','invoice_watermark_enabled'];}
function office2_mutate($action,array $p,array $files=[]) {
    global $pdo;$actor=office2_actor();
    if(!csrf_verify($p['csrf_token']??''))throw new RuntimeException(__t('Invalid form session. Refresh and try again.','نشست فرم نامعتبر است؛ صفحه را تازه کنید.','د فورم ناسته ناسمه ده؛ پاڼه تازه کړئ.'),403);
    if(!is_string($action))throw new InvalidArgumentException('Invalid action.');
    $targetActions=['edit_user','account_state','reset_password','save_permissions','save_subscription','renew_subscription','save_ai','reset_ai','assign_user_branch'];
    $target=in_array($action,$targetActions,true)?office2_target($p['user_id']??'') : null;
    if(in_array($action,['reset_password','change_master_pin','factory_reset_database','reset_initial_setup','create_shop','create_shop_employee'],true)||($action==='edit_user'&&($p['role']??'')!==$target['role'])||($action==='create_user'&&access_super_role($p['role']??'')))office2_reauthenticate($p);
    if($action==='factory_reset_database')return office2_factory_reset($p,$actor);
    return office2_transaction(function()use($action,$p,$files,$actor,$target,$pdo){
        unset($GLOBALS['_auth_user']);office2_actor();
        if($target)$target=office2_target($target['id']);
        $id=$target?(int)$target['id']:0;
        switch($action){
            case 'create_user':case 'edit_user':
                $username=office2_text($p,'username',50,true);$fullname=office2_text($p,'fullname',100,true);$role=office2_text($p,'role',20,true);
                if(strlen($username)>100||!preg_match('/^[\pL\pN_.@-]{3,50}$/uD',$username)||!in_array($role,office2_roles(),true))throw new InvalidArgumentException(__t('Invalid username or role.','نام کاربری یا نقش نامعتبر است.','کارن نوم یا دنده ناسمه ده.'));
                $q=$pdo->prepare('SELECT id FROM users WHERE LOWER(username)=LOWER(?) AND id<>?');$q->execute([$username,$id]);if($q->fetchColumn())throw new InvalidArgumentException(__t('Username already exists.','نام کاربری قبلاً ثبت شده است.','کارن نوم مخکې ثبت شوی.'));
                if($action==='create_user'){
                    if(!access_super_role($role))throw new InvalidArgumentException(__t('Business users must be created with a business and assigned branch. Use “Owners, employees & permissions”.','یوزر تجارت باید با تجارت و شعبهٔ تعیین‌شده ساخته شود؛ از «مالکین، کارمندان و صلاحیت‌ها» استفاده کنید.','د سوداګرۍ کارن باید څانګه ولري؛ د مالکانو او کارکوونکو برخه وکاروئ.'));
                    $password=$p['password']??'';security_validate_new_password($password,$p['confirm_password']??'');
                    $pdo->prepare('INSERT INTO users (username,password,fullname,role,status) VALUES (?,?,?,?,?)')->execute([$username,password_hash($password,PASSWORD_DEFAULT),$fullname,$role,'active']);$id=(int)$pdo->lastInsertId();access_ensure_profile($id);
                    $pdo->prepare('UPDATE user_access SET must_change_password=1 WHERE user_id=?')->execute([$id]);
                }else{
                    if(access_super_role($role)&&(int)($target['shop_id']??0)>0)throw new InvalidArgumentException(__t('A central role cannot keep a tenant branch. Create a separate central account instead.','نقش مرکزی نمی‌تواند شعبهٔ تجارت داشته باشد؛ حساب مرکزی جدا بسازید.','مركزي دنده نشي کولای سوداګریزه څانګه ولري.'));
                    if(!access_super_role($role)&&!(int)($target['shop_id']??0))throw new InvalidArgumentException(__t('Assign a business branch before giving this account a tenant role.','پیش از نقش تجاری، برای این حساب شعبه تعیین کنید.','دمخه څانګه وټاکئ.'));
                    office2_guard_admin($target,$role,$target['status'],(int)access_profile($id)['is_locked']);
                    $pdo->prepare('UPDATE users SET username=?,fullname=?,role=? WHERE id=?')->execute([$username,$fullname,$role,$id]);office2_revoke($id);
                }
                office2_audit($action,$id,['role'=>$role]);break;
            case 'assign_user_branch':
                if(domain_is_central($target))throw new InvalidArgumentException('Central accounts do not use a tenant branch.');
                $branchId=filter_var($p['shop_id']??0,FILTER_VALIDATE_INT);$branch=$branchId?domain_branch((int)$branchId):null;
                if(!$branch)throw new InvalidArgumentException(__t('Choose a valid business branch.','یک شعبهٔ معتبر تجارت انتخاب کنید.','د سوداګرۍ سمه څانګه وټاکئ.'));
                $pdo->prepare('UPDATE users SET shop_id=? WHERE id=?')->execute([(int)$branchId,$id]);$pdo->prepare('DELETE FROM branch_memberships WHERE user_id=?')->execute([$id]);$pdo->prepare('INSERT INTO branch_memberships (user_id,shop_id) VALUES (?,?)')->execute([$id,(int)$branchId]);
                access_ensure_profile($id);office2_revoke($id);office2_audit('assign_user_branch',$id,['shop_id'=>(int)$branchId,'business_id'=>(int)$branch['business_id']]);break;
            case 'account_state':
                $status=office2_text($p,'status',20,true);$locked=office2_bool($p,'is_locked');if(!in_array($status,['active','inactive'],true))throw new InvalidArgumentException('Invalid account status.');
                office2_guard_admin($target,$target['role'],$status,$locked);access_ensure_profile($id);
                $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status,$id]);$pdo->prepare('UPDATE user_access SET is_locked=?,updated_at=CURRENT_TIMESTAMP WHERE user_id=?')->execute([$locked,$id]);office2_revoke($id);office2_audit($action,$id,['status'=>$status,'locked'=>$locked]);break;
            case 'reset_password':
                if($id===(int)$actor['id'])throw new InvalidArgumentException(__t('Use Change my password for your own account.','برای حساب خود از «تغییر رمز من» استفاده کنید.','د خپل حساب لپاره خپل رمز بدلول وکاروئ.'));
                $password=$p['new_password']??'';security_validate_new_password($password,$p['confirm_password']??'');
                $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);access_ensure_profile($id);$pdo->prepare('UPDATE user_access SET must_change_password=1,updated_at=CURRENT_TIMESTAMP WHERE user_id=?')->execute([$id]);office2_revoke($id);office2_audit($action,$id,['forced_change'=>true]);break;
            case 'save_permissions':
                if(!is_array($p['features']??null)||count($p['features'])>100)throw new InvalidArgumentException('Invalid permission selection.');
                foreach($p['features'] as $key=>$allowed)office2_set_feature($id,$key,$allowed);
                office2_audit($action,$id,['features'=>array_keys($p['features'])]);break;
            case 'save_subscription':case 'renew_subscription':
                if($action==='renew_subscription'){
                    $days=office2_text($p,'days',4,true);if(!ctype_digit($days)||(int)$days<1||(int)$days>3650)throw new InvalidArgumentException('Invalid renewal period.');
                    $prior=access_profile($id)['subscription_expires_at'];$start=new DateTimeImmutable($prior&&strcmp($prior,date('Y-m-d H:i:s'))>0?$prior:'now');
                    $p['expires_at']=$start->modify('+'.(int)$days.' days')->format('Y-m-d');$p['subscription_active']='1';
                }
                $active=office2_bool($p,'subscription_active');$date=office2_text($p,'expires_at',10);$expires=null;
                if($date!==''){$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException(__t('Invalid expiration date.','تاریخ انقضا نامعتبر است.','د پای نېټه ناسمه ده.'));$expires=$date.' 23:59:59';}
                $old=access_profile($id);access_ensure_profile($id);
                $pdo->prepare('UPDATE user_access SET subscription_active=?,subscription_expires_at=?,updated_at=CURRENT_TIMESTAMP WHERE user_id=?')->execute([$active,$expires,$id]);
                office2_audit('subscription',$id,['operation'=>$action,'old_active'=>(int)$old['subscription_active'],'old_expires'=>$old['subscription_expires_at'],'active'=>$active,'expires'=>$expires]);break;
            case 'save_ai':
                $limits=[];foreach(['ai_daily_limit','ai_monthly_limit'] as $key){$v=office2_text($p,$key,8);if($v!==''&&(!ctype_digit($v)||(int)$v>1000000))throw new InvalidArgumentException(__t('Usage limits must be between 0 and 1000000, or blank for unlimited.','سهمیه باید بین صفر و یک میلیون باشد؛ خالی یعنی بدون محدودیت.','ونډه له صفر تر یو میلیون؛ تشه بې محدودیت ده.'));$limits[]=$v===''?null:(int)$v;}
                access_ensure_profile($id);office2_set_feature($id,'ai_advisor',(string)office2_bool($p,'ai_enabled'));
                domain_save_policy('user',$id,'ai',['enabled'=>(string)office2_bool($p,'ai_enabled'),'visibility'=>'locked','daily_limit'=>$limits[0]??'','monthly_limit'=>$limits[1]??'']);
                foreach(['ai.view','ai.create','ai.ask'] as $feature)domain_save_policy('user',$id,$feature,['enabled'=>(string)office2_bool($p,'ai_enabled'),'visibility'=>'locked']);
                $pdo->prepare('UPDATE user_access SET ai_daily_limit=?,ai_monthly_limit=?,updated_at=CURRENT_TIMESTAMP WHERE user_id=?')->execute([$limits[0],$limits[1],$id]);office2_audit($action,$id,['enabled'=>office2_bool($p,'ai_enabled'),'daily'=>$limits[0],'monthly'=>$limits[1]]);break;
            case 'reset_ai':
                office2_setting('security.ai_reset_after.'.$id,(string)$pdo->query('SELECT COALESCE(MAX(id),0) FROM security_events')->fetchColumn());$usage=$pdo->prepare("SELECT feature,period_key,used FROM service_usage WHERE scope_type='user' AND scope_id=? AND feature IN ('ai','ai.create','ai.ask') AND period_key IN (?,?)");$usage->execute([$id,date('Y-m-d'),date('Y-m')]);$previous=$usage->fetchAll();$pdo->prepare("UPDATE service_usage SET used=0 WHERE scope_type='user' AND scope_id=? AND feature IN ('ai','ai.create','ai.ask') AND period_key IN (?,?)")->execute([$id,date('Y-m-d'),date('Y-m')]);domain_audit('advisor_allowance_reset','user',$id,['previous_counters'=>$previous,'history_retained'=>true]);
                access_ensure_profile($id);$pdo->prepare('UPDATE user_access SET ai_day=?,ai_month=?,ai_day_used=0,ai_month_used=0,ai_reset_at=?,updated_at=CURRENT_TIMESTAMP WHERE user_id=?')->execute([date('Y-m-d'),date('Y-m'),gmdate('Y-m-d H:i:s'),$id]);office2_audit($action,$id,['history_retained'=>true]);break;
            case 'toggle_feature_lock':case 'bulk_lock_features':
                $catalog=get_lockable_features();$keys=[];$lock=0;
                if($action==='toggle_feature_lock'){$key=office2_text($p,'feature_key',100,true);if(!isset($catalog[$key]))throw new InvalidArgumentException('Unknown feature.');$keys=[$key];$lock=office2_bool($p,'is_locked');}
                else{$mode=$p['mode']??'';if(!in_array($mode,['lock_all','unlock_all','lock_expenses','unlock_expenses'],true))throw new InvalidArgumentException('Invalid bulk action.');$keys=str_ends_with($mode,'expenses')?['expenses_module','shop_expenses_entry']:array_keys($catalog);$lock=str_starts_with($mode,'lock_')?1:0;}
                foreach($keys as $key){domain_legacy_policy('system',0,$key,(string)(1-$lock));$pdo->prepare('UPDATE custom_features SET is_locked=?,updated_at=CURRENT_TIMESTAMP WHERE feature_key=?')->execute([$lock,$key]);office2_setting('feat_lock_'.$key,$lock);}
                office2_audit($action,0,['features'=>$keys,'locked'=>$lock]);break;
            case 'change_master_pin':
                $changed=false;foreach(['new_pin'=>'super_admin_pin','new_admin_pin'=>'admin_pin'] as $field=>$key){$secret=$p[$field]??'';if($secret==='')continue;security_validate_new_password($secret,$p[$field.'_confirm']??'');office2_setting($key,password_hash($secret,PASSWORD_DEFAULT));$changed=true;}
                if(!$changed)throw new InvalidArgumentException(__t('Enter a new access password.','رمز دسترسی جدید را وارد کنید.','نوی لاسرسي رمز ولیکئ.'));
                $version=(int)get_setting('office2.credential_version','0')+1;office2_setting('office2.credential_version',$version);$_SESSION['office2_version']=(string)$version;office2_audit($action,0,['other_office_sessions_revoked'=>true]);break;
            case 'save_system_theme':
                $theme=$p['active_theme']??'';if(!in_array($theme,['classic','dark_gold','emerald','glass'],true))throw new InvalidArgumentException('Invalid default theme.');office2_setting('active_theme',$theme);office2_audit($action,0,['theme'=>$theme]);break;
            case 'save_settings':
                $enums=['invoice_logo_position'=>['right','center','left','none'],'invoice_watermark_opacity'=>['0.04','0.07','0.12','0.18'],'invoice_watermark_size'=>['small','medium','large'],'invoice_border_style'=>['modern','classic','minimal'],'invoice_format'=>['a5','thermal','doc'],'auto_print_invoice'=>['0','1']];
                $values=[];foreach(office2_invoice_fields() as $key)if(isset($p[$key])){$v=office2_text($p,$key,in_array($key,['invoice_footer_note','invoice_thank_you','shop_address_central','shop_address_secondary'],true)?2000:255,$key==='shop_name');if(isset($enums[$key])&&!in_array($v,$enums[$key],true))throw new InvalidArgumentException('Invalid invoice option.');if($key==='invoice_accent_color'&&!preg_match('/^#[0-9a-fA-F]{6}$/D',$v))throw new InvalidArgumentException('Invalid invoice color.');$values[$key]=$v;}
                foreach($values as $key=>$v)office2_setting($key,$v);foreach(office2_invoice_toggles() as $key)office2_setting($key,office2_bool($p,$key));
                if(isset($files['shop_logo'])||!empty($p['remove_logo']))ui_save_branding(['remove_logo'=>$p['remove_logo']??''],isset($files['shop_logo'])?['business_logo'=>$files['shop_logo']]:[]);
                office2_audit($action,0,['fields'=>array_keys($values)]);break;
            case 'reset_initial_setup':
                office2_setting('system_initialized','0');office2_audit($action,0,['passwords_preserved'=>true]);break;
            case 'lock_panel':
                unset($_SESSION['super_admin_verified'],$_SESSION['office2_version']);office2_audit($action,$actor['id']);return ['user_id'=>$actor['id'],'sign_out'=>true];
            case 'create_shop':case 'create_shop_employee':case 'shop_state':case 'shop_subscription':case 'shop_renew':
            case 'shop_employees':case 'save_shop_branding':case 'save_shop_features':case 'open_shop':case 'leave_shop':
                return office2_shop_action($action,$p,$files,$actor);
            default:throw new InvalidArgumentException(__t('Unknown administration action.','عملیات مدیریتی نامعتبر است.','مدیریتي عمل ناسم دی.'));
        }
        return ['user_id'=>$id];
    });
}
function office2_factory_reset(array $p,array $actor) {
    global $pdo;
    if(($p['confirmation']??'')!=='RESET BUSINESS DATA'||office2_bool($p,'acknowledged')!==1)throw new InvalidArgumentException(__t('Confirm the destructive operation explicitly.','عملیات حذف را به‌صورت صریح تأیید کنید.','د ړنګولو عمل په څرګنده تایید کړئ.'));
    require_once __DIR__.'/backup_service.php';$passphrase=$p['backup_passphrase']??'';backup_passphrase($passphrase);
    $snapshot=backup_capture($pdo);
    $business=['journal_details','journal_headers','ledger_accounts','fund_transactions','fund_accounts','financial_periods','financial_corrections','fx_documents','purchase_return_lines','purchase_returns','return_lines','customer_refunds','partnership_accounts','partnership_return_reviews','partnership_purchase_reviews','partner_profit_reviews','purchase_opening_reviews','inventory_transfers','inventory_movements','suppliers','partners','installment_payments','installments','debt_payments','debts','currency_transactions','sale_items','sales','expenses','losses','inventory_purchase_items','inventory_purchases','stock','partnership_transactions','mudaraba_transactions','mudaraba_contracts','capital','customers'];
    $transform=function(array $current)use($business){
        unset($GLOBALS['_auth_user']);office2_actor();
        foreach($business as $table)if(isset($current['tables'][$table]))$current['tables'][$table]=[];
        $current['row_count']=array_sum(array_map('count',$current['tables']));return $current;
    };
    // Existing validated restore provides encryption, a verified recovery copy,
    // rollback, exact row/FK checks and session revocation. No default passwords.
    $recovery=backup_restore($pdo,$snapshot,$passphrase,$transform);
    office2_audit('factory_reset_database',0,['recovery_id'=>$recovery['id'],'accounts_preserved'=>true],$actor['id']);
    return ['sign_out'=>true,'recovery_id'=>$recovery['id']];
}
