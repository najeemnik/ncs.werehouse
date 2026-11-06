<?php
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../public/includes/management_service.php';
$name=getenv('NIKXOR_ADMIN_USERNAME');$password=getenv('NIKXOR_ADMIN_PASSWORD');
if(!$name||!preg_match('/^[a-zA-Z0-9_.@-]{3,50}$/D',$name)||!$password)throw new RuntimeException('Set a valid NIKXOR_ADMIN_USERNAME and NIKXOR_ADMIN_PASSWORD securely.');
security_validate_new_password($password,$password);
if($pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('superadmin','super_admin') AND shop_id IS NULL")->fetchColumn())throw new RuntimeException('Central administrator already exists; no credentials changed.');
$pdo->prepare("INSERT INTO users (username,password,fullname,role,status,shop_id) VALUES (?,?,?,'superadmin','active',NULL)")->execute([$name,password_hash($password,PASSWORD_DEFAULT),'Ahmad Najeem Nik']);access_ensure_profile((int)$pdo->lastInsertId());
echo "Central administrator created. No password printed.\n";
