<?php
/** RFC 6238 SHA-1 TOTP for central and tenant users, plus 30-day trusted devices. */
function totp_decode(string $s): string {
    $s=strtoupper(str_replace(' ','',$s));if(!preg_match('/^[A-Z2-7]{16,128}$/D',$s))throw new RuntimeException('Invalid TOTP secret configuration.');
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';foreach(str_split($s) as $c)$bits.=str_pad(decbin(strpos($alphabet,$c)),5,'0',STR_PAD_LEFT);$out='';foreach(str_split($bits,8) as $byte)if(strlen($byte)===8)$out.=chr(bindec($byte));return $out;
}
function totp_code(string $secret,int $counter): string {
    $hash=hash_hmac('sha1',pack('N2',intdiv($counter,4294967296),$counter%4294967296),totp_decode($secret),true);$offset=ord($hash[19])&15;$n=unpack('N',substr($hash,$offset,4))[1]&0x7fffffff;return str_pad((string)($n%1000000),6,'0',STR_PAD_LEFT);
}
/** Return a fresh, unbiased Base32 secret without modulo padding. */
function totp_secret_generate(): string {$alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';foreach(str_split(random_bytes(20)) as $byte)$bits.=str_pad(decbin(ord($byte)),8,'0',STR_PAD_LEFT);$out='';for($i=0;$i<strlen($bits);$i+=5){$chunk=substr($bits,$i,5);if(strlen($chunk)===5)$out.=$alphabet[bindec($chunk)];}return $out;}
function totp_secret(int $user): string {
    $secret=getenv('NIKXOR_CENTRAL_TOTP_'.$user);if($secret)return $secret;
    $dir=security_private_dir();$guarded=$dir.'/totp-'.$user.'.key.php';if(is_file($guarded))return trim(hosting_read_secret($guarded));$path=$dir.'/totp-'.$user.'.key';return is_file($path)?trim(file_get_contents($path)):'';
}
function totp_secret_save(int $user,string $secret): void {
    totp_decode($secret);$dir=security_private_dir();$guarded=$dir.'/totp-'.$user.'.key.php';$legacy=$dir.'/totp-'.$user.'.key';if(is_file($guarded)||is_file($legacy))throw new RuntimeException('Authenticator is already configured.');hosting_write_secret($guarded,$secret);
}
function totp_secret_remove(int $user): void {foreach([security_private_dir().'/totp-'.$user.'.key.php',security_private_dir().'/totp-'.$user.'.key'] as $path)if(is_file($path)&&!is_link($path)&&!unlink($path))throw new RuntimeException('Authenticator reset could not be completed.');}
function mfa_available(array $u): bool {return access_account_available($u)&&domain_can('settings.mfa',$u,(int)($u['shop_id']??0));}
function mfa_required(array $u): bool {return domain_is_central($u)||(mfa_available($u)&&totp_secret((int)$u['id'])!=='');}
function totp_session_valid(?array $u=null): bool {
    $u=$u??($GLOBALS['_auth_user']??null);if(!$u)return false;$key=domain_is_central($u)?'central_mfa_at':'user_mfa_at';return !empty($_SESSION[$key])&&time()-(int)$_SESSION[$key]<1800;
}
function mfa_access_valid(array $u): bool {
    if(!mfa_available($u))return false;if(totp_session_valid($u))return true;if(!mfa_required($u)||!trusted_device_valid($u))return false;
    $_SESSION[domain_is_central($u)?'central_mfa_at':'user_mfa_at']=time();return true;
}
function totp_verify(string $code,array $u,bool $trust=false): bool {
    global $pdo;if(!mfa_available($u)||!preg_match('/^[0-9]{6}$/D',$code)||!security_attempt('user-totp',(string)$u['id'],6,300))return false;
    $secret=totp_secret((int)$u['id']);if(!$secret)return false;
    $now=intdiv(time(),30);$matched=null;foreach([$now-1,$now,$now+1] as $step)if(hash_equals(totp_code($secret,$step),$code))$matched=$step;
    if($matched===null)return false;
    $pdo->beginTransaction();try{
        $key='security.totp_counter.'.$u['id'];$ins=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE':'INSERT IGNORE';$pdo->prepare("$ins INTO settings (setting_key,setting_value) VALUES (?,'0')")->execute([$key]);
        $cast=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INTEGER':'UNSIGNED';$q=$pdo->prepare("UPDATE settings SET setting_value=? WHERE setting_key=? AND CAST(setting_value AS $cast)<?");$q->execute([(string)$matched,$key,$matched]);if($q->rowCount()!==1){$pdo->rollBack();return false;}$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    session_regenerate_id(true);unset($_SESSION['csrf_token']);$_SESSION[domain_is_central($u)?'central_mfa_at':'user_mfa_at']=time();if($trust)trusted_device_issue($u);domain_audit('mfa_verified','session');return true;
}
function totp_enable(array $u,string $secret,string $code): bool {
    global $pdo;if(!mfa_available($u)||totp_secret((int)$u['id'])!==''||!preg_match('/^[0-9]{6}$/D',$code)||!security_attempt('mfa-enroll',(string)$u['id'],6,300))return false;
    $secret=strtoupper(str_replace(' ','',$secret));totp_decode($secret);$now=intdiv(time(),30);$matched=null;foreach([$now-1,$now,$now+1] as $step)if(hash_equals(totp_code($secret,$step),$code))$matched=$step;if($matched===null)return false;
    totp_secret_save((int)$u['id'],$secret);update_setting('security.totp_counter.'.$u['id'],(string)$matched);trusted_device_revoke_user((int)$u['id']);session_regenerate_id(true);unset($_SESSION['csrf_token']);$_SESSION[domain_is_central($u)?'central_mfa_at':'user_mfa_at']=time();domain_audit('mfa_enabled','user',$u['id']);return true;
}
function totp_disable(array $u,string $password,string $code): bool {
    if(domain_is_central($u)||!security_current_password($password)||!totp_verify($code,$u))return false;totp_secret_remove((int)$u['id']);trusted_device_revoke_user((int)$u['id']);unset($_SESSION['user_mfa_at']);domain_audit('mfa_disabled','user',$u['id']);return true;
}
function totp_uri(array $u,string $secret): string {$label='Nik Smart Count:'.$u['username'];return 'otpauth://totp/'.rawurlencode($label).'?'.http_build_query(['secret'=>$secret,'issuer'=>'Nik Smart Count','algorithm'=>'SHA1','digits'=>6,'period'=>30]);}
function trusted_agent_hash(): string {return hash('sha256',substr((string)($_SERVER['HTTP_USER_AGENT']??'unknown'),0,500));}
function trusted_cookie_parts(): ?array {$raw=$_COOKIE['nsc_mfa_trust']??'';if(!is_string($raw)||!preg_match('/^([1-9][0-9]*)\.([A-Za-z0-9_-]{43})$/D',$raw,$m))return null;return [(int)$m[1],$m[2]];}
function trusted_device_valid(array $u): bool {
    global $pdo;$parts=trusted_cookie_parts();if(!$parts||$parts[0]!==(int)$u['id'])return false;[$id,$token]=$parts;$q=$pdo->prepare('SELECT id,expires_at,last_used_at FROM user_mfa_devices WHERE user_id=? AND token_hash=? AND agent_hash=?');$q->execute([$id,hash('sha256',$token),trusted_agent_hash()]);$row=$q->fetch();if(!$row||strcmp($row['expires_at'],gmdate('Y-m-d H:i:s'))<=0)return false;
    if(!$row['last_used_at']||strtotime($row['last_used_at'].' UTC')<time()-86400)$pdo->prepare('UPDATE user_mfa_devices SET last_used_at=? WHERE id=?')->execute([gmdate('Y-m-d H:i:s'),$row['id']]);return true;
}
function trusted_device_issue(array $u): string {
    global $pdo;$token=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$now=time();$expires=$now+2592000;$label=substr((string)($_SERVER['HTTP_USER_AGENT']??'Trusted device'),0,100);
    $pdo->prepare('INSERT INTO user_mfa_devices (user_id,token_hash,agent_hash,device_label,created_at,expires_at,last_used_at) VALUES (?,?,?,?,?,?,?)')->execute([(int)$u['id'],hash('sha256',$token),trusted_agent_hash(),$label,gmdate('Y-m-d H:i:s',$now),gmdate('Y-m-d H:i:s',$expires),gmdate('Y-m-d H:i:s',$now)]);
    $value=$u['id'].'.'.$token;setcookie('nsc_mfa_trust',$value,['expires'=>$expires,'path'=>'/','secure'=>app_request_is_https(),'httponly'=>true,'samesite'=>'Strict']);return $value;
}
function trusted_device_revoke_user(int $user): void {global $pdo;$pdo->prepare('DELETE FROM user_mfa_devices WHERE user_id=?')->execute([$user]);$parts=trusted_cookie_parts();if($parts&&$parts[0]===$user)setcookie('nsc_mfa_trust','',['expires'=>time()-3600,'path'=>'/','secure'=>app_request_is_https(),'httponly'=>true,'samesite'=>'Strict']);}
function trusted_device_forget(): void {global $pdo;$parts=trusted_cookie_parts();if($parts)$pdo->prepare('DELETE FROM user_mfa_devices WHERE user_id=? AND token_hash=?')->execute([$parts[0],hash('sha256',$parts[1])]);setcookie('nsc_mfa_trust','',['expires'=>time()-3600,'path'=>'/','secure'=>app_request_is_https(),'httponly'=>true,'samesite'=>'Strict']);}
function trusted_devices(int $user): array {global $pdo;$q=$pdo->prepare('SELECT id,device_label,created_at,expires_at,last_used_at FROM user_mfa_devices WHERE user_id=? ORDER BY id DESC');$q->execute([$user]);return $q->fetchAll();}
