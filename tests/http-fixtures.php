<?php
require __DIR__.'/bootstrap.php';
if($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn())throw new RuntimeException('HTTP fixtures require a fresh empty test database.');
function makeUser($name,$role,$shop=null){global $pdo;$pass=bin2hex(random_bytes(12));$pdo->prepare('INSERT INTO users (username,password,fullname,role,status,shop_id) VALUES (?,?,?,?,?,?)')->execute([$name,password_hash($pass,PASSWORD_DEFAULT),$name,$role,'active',$shop]);$id=(int)$pdo->lastInsertId();access_ensure_profile($id);return ['id'=>$id,'username'=>$name,'password'=>$pass];}
$central=makeUser('test-central','superadmin');$owner=makeUser('test-owner','admin');
$pdo->prepare('INSERT INTO businesses (name,owner_user_id) VALUES (?,?)')->execute(['Test trade',$owner['id']]);$bid=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO shops (name,business_id,owner_user_id,city,status,subscription_active,employee_limit,can_add_employees) VALUES (?,?,?,'Kabul','active',1,10,1)")->execute(['Test Kabul',$bid,$owner['id']]);$branch=(int)$pdo->lastInsertId();
$pdo->prepare('UPDATE users SET shop_id=? WHERE id=?')->execute([$branch,$owner['id']]);
$staff=makeUser('test-staff','staff',$branch);$pdo->prepare('INSERT INTO branch_memberships (user_id,shop_id) VALUES (?,?)')->execute([$staff['id'],$branch]);
$secret='JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';file_put_contents(security_private_dir().'/totp-'.$central['id'].'.key',$secret);chmod(security_private_dir().'/totp-'.$central['id'].'.key',0600);
$fixturePath=getenv('NIKXOR_HTTP_FIXTURE_FILE')?:'/data/accounts.json';file_put_contents($fixturePath,json_encode(['central'=>$central,'owner'=>$owner,'staff'=>$staff,'branch_id'=>$branch,'business_id'=>$bid,'totp_test_secret'=>$secret],JSON_THROW_ON_ERROR));chmod($fixturePath,0600);chown($fixturePath,1000);chgrp($fixturePath,1000);
echo "Synthetic HTTP fixtures created; credentials stored privately.\n";
