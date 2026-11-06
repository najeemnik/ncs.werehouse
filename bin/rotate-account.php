<?php
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../public/includes/management_service.php';
$name=getenv('NIKXOR_RESET_USERNAME');$password=getenv('NIKXOR_RESET_PASSWORD');if(!$name||!$password)throw new RuntimeException('Supply account name and new password through private environment variables.');security_validate_new_password($password,$password);
$q=$pdo->prepare('SELECT id FROM users WHERE username=?');$q->execute([$name]);$id=(int)$q->fetchColumn();if(!$id)throw new RuntimeException('Account not found.');
$pdo->beginTransaction();try{$pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);access_ensure_profile($id);$pdo->prepare('UPDATE user_access SET is_locked=0,must_change_password=1 WHERE user_id=?')->execute([$id]);office2_revoke($id);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
echo "Account password rotated; old sessions revoked.\n";
