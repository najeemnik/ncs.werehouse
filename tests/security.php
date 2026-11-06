<?php
require __DIR__.'/bootstrap.php';
$pdo->exec('DELETE FROM service_usage');$pdo->exec('DELETE FROM service_policies');$pdo->exec('DELETE FROM branch_memberships');
// Synthetic credentials are generated per test, never taken from the upload.
function userFixture($username,$role,$shop=null){global $pdo;$pdo->prepare('INSERT INTO users (username,password,fullname,role,status,shop_id) VALUES (?,?,?,?,?,?)')->execute([$username,password_hash(bin2hex(random_bytes(18)),PASSWORD_DEFAULT),$username,$role,'active',$shop]);return (int)$pdo->lastInsertId();}
$prefix='test'.bin2hex(random_bytes(4));$central=userFixture($prefix.'central','superadmin');
$owner=userFixture($prefix.'owner','admin');$other=userFixture($prefix.'other','admin');
function businessFixture($owner,$name){global $pdo;$pdo->prepare('INSERT INTO businesses (name,owner_user_id) VALUES (?,?)')->execute([$name,$owner]);return (int)$pdo->lastInsertId();}
$a=businessFixture($owner,'Synthetic A');$b=businessFixture($other,'Synthetic B');
function branchFixture($business,$owner,$name){global $pdo;$pdo->prepare("INSERT INTO shops (name,business_id,owner_user_id,city,status,subscription_active,employee_limit,can_add_employees) VALUES (?,?,?,'Kabul','active',1,10,1)")->execute([$name,$business,$owner]);return (int)$pdo->lastInsertId();}
$a1=branchFixture($a,$owner,'A1');$a2=branchFixture($a,$owner,'A2');$b1=branchFixture($b,$other,'B1');
$pdo->prepare('UPDATE users SET shop_id=? WHERE id=?')->execute([$a1,$owner]);$pdo->prepare('UPDATE users SET shop_id=? WHERE id=?')->execute([$b1,$other]);
$staff=userFixture($prefix.'staff','staff',$a1);$pdo->prepare('INSERT INTO branch_memberships (user_id,shop_id) VALUES (?,?)')->execute([$staff,$a1]);
function fixtureUser($id){global $pdo;$q=$pdo->prepare('SELECT * FROM users WHERE id=?');$q->execute([$id]);return $q->fetch();}
$cu=fixtureUser($central);$ou=fixtureUser($owner);$su=fixtureUser($staff);
security_establish_session($ou);
check(count(domain_branches($ou))===2,'owner sees both own branches');
check(!domain_branch_allowed($ou,$b1),'owner cannot open another business');
check(!domain_branch_allowed($su,$a2),'staff cannot open unassigned sibling branch');
check(!domain_branch_allowed($su,$b1),'staff cannot open another business');
check(domain_is_central($cu),'central role recognized');
check(!domain_is_central($ou),'owner cannot become central by admin role');
$_SESSION['super_admin_verified']=true;check(!office2_authorized(),'legacy PIN session cannot promote owner to Office 2');
$_SESSION['shop_context_id']=$b1;$caught=false;try{shop_id();}catch(RuntimeException $e){$caught=true;}check($caught,'forged branch session rejected');unset($_SESSION['shop_context_id']);
security_establish_session($su);
check(domain_can('sales.create'),'staff can sell in assigned branch');
check(!domain_can('ai.ask'),'employee AI denied without both approvals');
$policy=['enabled'=>'1','visibility'=>'locked','daily_limit'=>'2','monthly_limit'=>'3'];
foreach(['ai','ai.ask'] as $feature)domain_save_policy('user',$staff,$feature,$policy);
check(!domain_can('ai.ask'),'central approval alone insufficient');
foreach(['ai','ai.ask'] as $feature)domain_save_policy('owner_user',$staff,$feature,$policy);
check(domain_can('ai.ask'),'both approvals enable employee AI');
domain_consume('ai.ask');domain_consume('ai.ask');$caught=false;try{domain_consume('ai.ask');}catch(RuntimeException $e){$caught=$e->getCode()===429;}check($caught,'daily quota blocks third request');
$q=$pdo->prepare("SELECT used FROM service_usage WHERE scope_type='user' AND scope_id=? AND feature='ai.ask' AND period_key=?");$q->execute([$staff,date('Y-m-d')]);check((int)$q->fetchColumn()===2,'rejected reservation does not partially consume quota');
domain_save_policy('business',$a,'ai',['enabled'=>'0','visibility'=>'hidden']);check(!domain_can('ai.ask'),'business restriction overrides staff approval');check(domain_visibility('ai')==='hidden','hidden service policy exposed correctly');
security_establish_session($ou);$caught=false;try{management_user($other,$ou);}catch(RuntimeException $e){$caught=$e->getCode()===403;}check($caught,'owner cannot edit another owner');
$caught=false;try{management_mutate(['csrf_token'=>csrf_token(),'action'=>'create_staff','business_id'=>$b,'branch_ids'=>[$b1],'fullname'=>'Intruder']);}catch(RuntimeException $e){$caught=$e->getCode()===403;}check($caught,'cross-business employee creation rejected');
$pdo->prepare("UPDATE shops SET status='locked' WHERE id=?")->execute([$a1]);check(!domain_can('sales.view',$su,$a1),'locked branch blocks its staff');
$pdo->prepare("UPDATE shops SET status='active' WHERE id=?")->execute([$a1]);
$pdo->prepare('DELETE FROM branch_memberships WHERE user_id=?')->execute([$staff]);check(!domain_branch_allowed($su,$a1),'membership revocation takes effect immediately');
check((int)$pdo->query('SELECT COUNT(*) FROM security_events')->fetchColumn()>0,'permission changes produce audit events');
check(security_password_matches('abc ',password_hash('abc ',PASSWORD_DEFAULT)),'password bytes preserved');check(!security_password_matches('abc',password_hash('abc ',PASSWORD_DEFAULT)),'password normalization cannot change secret');
check($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite'||$pdo->query('PRAGMA integrity_check')->fetchColumn()==='ok','database integrity preserved');
echo "Security scenarios completed.\n";
// Parent-account restrictions are a ceiling, not something an owner can delegate around.
$pdo->prepare('INSERT INTO branch_memberships (user_id,shop_id) VALUES (?,?)')->execute([$staff,$a1]);
security_establish_session($ou);$globalBefore=$pdo->query("SELECT setting_value FROM settings WHERE setting_key='shop_name'")->fetchColumn();update_setting('shop_name','Private branch branding');
check($pdo->query("SELECT setting_value FROM settings WHERE setting_key='shop_name'")->fetchColumn()===$globalBefore,'owner branding cannot overwrite global identity');
domain_save_policy('business',$a,'ai',['enabled'=>'1','visibility'=>'locked']);domain_save_policy('user',$owner,'ai',['enabled'=>'0','visibility'=>'hidden']);check(!domain_can('ai.ask',$su,$a1),'central restriction on owner also restricts employees');domain_save_policy('user',$owner,'ai',['enabled'=>'1','visibility'=>'locked']);
access_ensure_profile($owner);$pdo->prepare('UPDATE user_access SET is_locked=1 WHERE user_id=?')->execute([$owner]);check(!domain_can('sales.view',$su,$a1),'locked owner blocks employees');$pdo->prepare('UPDATE user_access SET is_locked=0 WHERE user_id=?')->execute([$owner]);
require_once __DIR__.'/../public/includes/totp.php';$secret='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';putenv('NIKXOR_CENTRAL_TOTP_'.$central.'='.$secret);security_establish_session($cu);$code=totp_code($secret,intdiv(time(),30));check(totp_verify($code,$cu),'central TOTP accepted');check(!totp_verify($code,$cu),'central TOTP cannot be replayed');security_establish_session($ou);
domain_save_policy('user',$staff,'sales.create',['enabled'=>'1','visibility'=>'locked','daily_limit'=>'','monthly_limit'=>'0']);security_establish_session($su);$caught=false;try{domain_consume('sales.create');}catch(RuntimeException $e){$caught=$e->getCode()===429;}check($caught,'monthly zero allowance blocks use without daily cap');security_establish_session($ou);
$name='Shared-category-'.bin2hex(random_bytes(4));$pdo->prepare('INSERT INTO categories (name,shop_id) VALUES (?,?)')->execute([$name,$a1]);$pdo->prepare('INSERT INTO categories (name,shop_id) VALUES (?,?)')->execute([$name,$b1]);check(true,'different businesses can use the same category name');$caught=false;try{$pdo->prepare('INSERT INTO categories (name,shop_id) VALUES (?,?)')->execute([$name,$a1]);}catch(PDOException $e){$caught=true;}check($caught,'duplicate category still blocked within one branch');

$caught=false;try{management_mutate(['csrf_token'=>csrf_token(),'action'=>'save_policies','scope_type'=>'user','scope_id'=>$staff,'policy_matrix_started'=>'1','policies'=>[]]);}catch(InvalidArgumentException $e){$caught=true;}check($caught,'truncated permission matrix is rejected before changes');
