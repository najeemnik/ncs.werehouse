<?php
require_once '/app/api/db.php';$db=Database::getConnection();
foreach([['test-owner-a',null,'owner-a','admin',0],['test-owner-b',null,'owner-b','admin',0],['test-master',null,'master','master',1],['test-staff','test-owner-a','staff','viewer',0]] as [$id,$owner,$username,$role,$master]){
 $s=$db->prepare('INSERT INTO users(id,owner_admin_id,name,username,email,password_hash,role,is_master,subscription_plan,permissions_json) VALUES (:id,:owner,:name,:username,:email,:hash,:role,:master,\'lifetime\',\'{"allowedTabs":["reports","projects"]}\') ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),is_locked=0');
 $s->execute(['id'=>$id,'owner'=>$owner,'name'=>$username,'username'=>$username,'email'=>$username.'@example.test','hash'=>password_hash('Test-passphrase-2026!',PASSWORD_DEFAULT),'role'=>$role,'master'=>$master]);
}
foreach(['a','b'] as $tenant){
 $s=$db->prepare('INSERT INTO projects(id,tenant_id,project_code,name) VALUES(:id,:tid,:code,:name) ON DUPLICATE KEY UPDATE tenant_id=VALUES(tenant_id)');$s->execute(['id'=>'project-'.$tenant,'tid'=>'test-owner-'.$tenant,'code'=>'P-'.$tenant,'name'=>'Project '.$tenant]);
 $s=$db->prepare('INSERT INTO treasury_accounts(id,tenant_id,account_type,account_name,current_balance,currency) VALUES(:id,:tid,\'bank\',:name,10000,\'AFN\') ON DUPLICATE KEY UPDATE current_balance=10000');$s->execute(['id'=>'account-'.$tenant,'tid'=>'test-owner-'.$tenant,'name'=>'Bank '.$tenant]);
}
echo "Test fixtures created.\n";
$db->exec('DELETE FROM auth_rate_limits');
