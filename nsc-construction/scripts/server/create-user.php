<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../../api/db.php';
$options=getopt('', ['username:','email:','name:','master','owner:','reset:']);
$password=rtrim(stream_get_contents(STDIN),"\r\n");
if(strlen($password)<12||strlen($password)>72||str_contains($password,"\0"))throw new RuntimeException('Password must be 12–72 bytes; provide it on stdin.');
$db=Database::getConnection();
if(isset($options['reset'])) {$s=$db->prepare('UPDATE users SET password_hash=:hash,is_locked=0,session_version=session_version+1 WHERE id=:id');$s->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$options['reset']]);if(!$s->rowCount())throw new RuntimeException('User not found');echo "Password reset; previous sessions revoked.\n";exit;}
foreach(['username','email','name'] as $field)if(empty($options[$field]))throw new RuntimeException('Missing '.$field);
if(!filter_var($options['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Invalid email');
$owner=$options['owner']??null;
if($owner){$s=$db->prepare('SELECT id FROM users WHERE id=:id AND owner_admin_id IS NULL AND is_locked=0');$s->execute(['id'=>$owner]);if(!$s->fetch())throw new RuntimeException('Invalid owner');}
if(!isset($options['master'])&&!$owner)throw new RuntimeException('Create company owners through the primary Office Seven account. CLI bootstrap is for the primary administrator only.');
$id=generateUuid();$s=$db->prepare('INSERT INTO users(id,owner_admin_id,name,username,email,password_hash,role,is_master,subscription_plan) VALUES (:id,:owner,:name,:username,:email,:hash,:role,:master,\'lifetime\')');
$s->execute(['id'=>$id,'owner'=>$owner,'name'=>$options['name'],'username'=>$options['username'],'email'=>$options['email'],'hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>isset($options['master'])?'master':($owner?'viewer':'admin'),'master'=>isset($options['master'])?1:0]);
echo "Created user: $id\n";
