<?php
require_once __DIR__ . '/db.php';
sendSecurityHeaders(); startSecureSession(); $db=Database::getConnection();
$method=$_SERVER['REQUEST_METHOD'];$action=$_GET['action']??'login';
if($method==='GET') {
    if(empty($_SESSION['uid'])) jsonResponse(true,['user'=>null,'csrf'=>$_SESSION['csrf']]);
    $user=authenticateUser();jsonResponse(true,['user'=>publicUser($user),'csrf'=>$_SESSION['csrf']]);
}
if($method!=='POST') jsonResponse(false,null,'Method is not allowed.',405);
requireCsrf();$input=readJson(8192);
if($action==='logout') { $_SESSION=[];session_destroy();setcookie('nsc_session','',['expires'=>time()-3600,'path'=>'/','secure'=>APP_ENV==='production','httponly'=>true,'samesite'=>'Strict']);jsonResponse(true); }
if($action==='request_reset') {
    $email=strtolower(trim((string)($input['email']??'')));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))jsonResponse(false,null,'Invalid email address.',400);
    $ipBucket=hash('sha256','reset-ip:'.($_SERVER['REMOTE_ADDR']??''));
    $s=$db->prepare('INSERT INTO auth_rate_limits(bucket_key,attempts,window_started) VALUES(:key,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE attempts=IF(window_started<UTC_TIMESTAMP()-INTERVAL 1 HOUR,1,attempts+1),window_started=IF(window_started<UTC_TIMESTAMP()-INTERVAL 1 HOUR,UTC_TIMESTAMP(),window_started)');$s->execute(['key'=>$ipBucket]);
    $s=$db->prepare('SELECT attempts FROM auth_rate_limits WHERE bucket_key=:key');$s->execute(['key'=>$ipBucket]);if((int)$s->fetchColumn()>60)jsonResponse(false,null,'Please try again later.',429);
    $bucket=hash('sha256','reset:'.($_SERVER['REMOTE_ADDR']??'').':'.$email);
    $s=$db->prepare('INSERT INTO auth_rate_limits(bucket_key,attempts,window_started) VALUES(:key,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE attempts=IF(window_started<UTC_TIMESTAMP()-INTERVAL 1 HOUR,1,attempts+1),window_started=IF(window_started<UTC_TIMESTAMP()-INTERVAL 1 HOUR,UTC_TIMESTAMP(),window_started)');$s->execute(['key'=>$bucket]);
    $s=$db->prepare('SELECT attempts FROM auth_rate_limits WHERE bucket_key=:key');$s->execute(['key'=>$bucket]);
    if((int)$s->fetchColumn()>5)jsonResponse(false,null,'Please try again later.',429);
    $from=getenv('NSC_MAIL_FROM')?:'';
    if(!filter_var($from,FILTER_VALIDATE_EMAIL)){error_log('NSC password reset mail is not configured');jsonResponse(false,null,'Password recovery is unavailable. Contact the administrator.',503);}
    $s=$db->prepare('SELECT id FROM users WHERE email=:email AND is_locked=0');$s->execute(['email'=>$email]);$u=$s->fetch();
    if($u) {
        $token=bin2hex(random_bytes(32));
        $s=$db->prepare('INSERT INTO password_resets(token_hash,user_id,expires_at) VALUES(:hash,:uid,UTC_TIMESTAMP()+INTERVAL 15 MINUTE)');$s->execute(['hash'=>hash('sha256',$token),'uid'=>$u['id']]);
        $ok=mail($email,'NSC password recovery',"Use this recovery code within 15 minutes:\n".$token."\nIf you did not request this, ignore this message.",'From: '.$from);
        if(!$ok){error_log('NSC password reset delivery failed');$s=$db->prepare('UPDATE password_resets SET used_at=UTC_TIMESTAMP() WHERE token_hash=:hash');$s->execute(['hash'=>hash('sha256',$token)]);}
    }
    jsonResponse(true,null,'If this account exists, recovery instructions have been sent.');
}
if($action==='complete_reset') {
    $token=(string)($input['token']??'');$password=(string)($input['password']??'');
    if(!preg_match('/^[a-f0-9]{64}$/D',$token)||strlen($password)<12||strlen($password)>72||str_contains($password,"\0"))jsonResponse(false,null,'Invalid recovery code or password length.',400);
    $db->beginTransaction();
    $s=$db->prepare('SELECT * FROM password_resets WHERE token_hash=:hash AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE');$s->execute(['hash'=>hash('sha256',$token)]);$reset=$s->fetch();
    if(!$reset){$db->rollBack();jsonResponse(false,null,'Recovery code is invalid or expired.',400);}
    $s=$db->prepare('UPDATE users SET password_hash=:hash,session_version=session_version+1 WHERE id=:id AND is_locked=0');$s->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$reset['user_id']]);
    $s=$db->prepare('UPDATE password_resets SET used_at=UTC_TIMESTAMP() WHERE user_id=:id AND used_at IS NULL');$s->execute(['id'=>$reset['user_id']]);
    logAudit($db,$reset['user_id'],'Account recovery','update','auth',$reset['user_id'],'Password reset; sessions revoked');$db->commit();jsonResponse(true);
}
if($action!=='login') jsonResponse(false,null,'Action is not available.',404);
$username=strtolower(trim((string)($input['username']??'')));$password=(string)($input['password']??'');
if(!$username||strlen($username)>150||!$password||strlen($password)>1024||str_contains($password,"\0")) jsonResponse(false,null,'Username and password are required.',400);
// Persistent atomic rate limits apply equally to existing and unknown accounts.
$keys=[hash('sha256','account:'.$username),hash('sha256','ip:'.($_SERVER['REMOTE_ADDR']??''))];
foreach($keys as $key) {
    $s=$db->prepare('INSERT INTO auth_rate_limits (bucket_key,attempts,window_started) VALUES (:key,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE attempts=IF(window_started < UTC_TIMESTAMP() - INTERVAL 15 MINUTE,1,attempts+1),window_started=IF(window_started < UTC_TIMESTAMP() - INTERVAL 15 MINUTE,UTC_TIMESTAMP(),window_started)');$s->execute(['key'=>$key]);
    $s=$db->prepare('SELECT attempts FROM auth_rate_limits WHERE bucket_key=:key');$s->execute(['key'=>$key]);
    if((int)$s->fetchColumn()>($key===$keys[0]?10:60)) {header('Retry-After: 900');jsonResponse(false,null,'Too many attempts. Try again later.',429);}
}
$s=$db->prepare('SELECT * FROM users WHERE username=:username OR email=:email LIMIT 1');$s->execute(['username'=>$username,'email'=>$username]);$u=$s->fetch();
$dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$valid=password_verify($password,$u['password_hash']??$dummy);
if(!$u||!$valid||$u['is_locked']) jsonResponse(false,null,'Username or password is incorrect.',401);
assertLicenseAccess($u);
if(password_needs_rehash($u['password_hash'],PASSWORD_DEFAULT)) {$s=$db->prepare('UPDATE users SET password_hash=:hash WHERE id=:id');$s->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$u['id']]);}
$s=$db->prepare('DELETE FROM auth_rate_limits WHERE bucket_key=:key');$s->execute(['key'=>$keys[0]]);
$s=$db->prepare('UPDATE auth_rate_limits SET attempts=GREATEST(0,attempts-1) WHERE bucket_key=:key');$s->execute(['key'=>$keys[1]]);
session_regenerate_id(true);$_SESSION=['uid'=>$u['id'],'version'=>(int)$u['session_version'],'issued_at'=>time(),'last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];
$s=$db->prepare('UPDATE users SET last_login_at=UTC_TIMESTAMP() WHERE id=:id');$s->execute(['id'=>$u['id']]);
logAudit($db,$u['id'],$u['name'],'login','auth',$u['id'],'Successful sign in');
jsonResponse(true,['user'=>publicUser($u),'csrf'=>$_SESSION['csrf']]);
