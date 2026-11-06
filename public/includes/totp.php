<?php
/** RFC 6238 SHA-1 TOTP; central administration always requires a second factor. */
function totp_decode(string $s): string {
    $s=strtoupper(str_replace(' ','',$s));if(!preg_match('/^[A-Z2-7]{16,128}$/D',$s))throw new RuntimeException('Invalid TOTP secret configuration.');
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';foreach(str_split($s) as $c)$bits.=str_pad(decbin(strpos($alphabet,$c)),5,'0',STR_PAD_LEFT);$out='';foreach(str_split($bits,8) as $byte)if(strlen($byte)===8)$out.=chr(bindec($byte));return $out;
}
function totp_code(string $secret,int $counter): string {
    $hash=hash_hmac('sha1',pack('N2',intdiv($counter,4294967296),$counter%4294967296),totp_decode($secret),true);$offset=ord($hash[19])&15;$n=unpack('N',substr($hash,$offset,4))[1]&0x7fffffff;return str_pad((string)($n%1000000),6,'0',STR_PAD_LEFT);
}
function totp_secret(int $user): string {
    $secret=getenv('NIKXOR_CENTRAL_TOTP_'.$user);if($secret)return $secret;
    $dir=security_private_dir();$guarded=$dir.'/totp-'.$user.'.key.php';if(is_file($guarded))return trim(hosting_read_secret($guarded));$path=$dir.'/totp-'.$user.'.key';return is_file($path)?trim(file_get_contents($path)):'';
}
function totp_verify(string $code,array $u): bool {
    global $pdo;if(!domain_is_central($u)||!preg_match('/^[0-9]{6}$/D',$code)||!security_attempt('central-totp',(string)$u['id'],6,300))return false;
    $secret=totp_secret((int)$u['id']);if(!$secret)return false;
    $now=intdiv(time(),30);$matched=null;foreach([$now-1,$now,$now+1] as $step)if(hash_equals(totp_code($secret,$step),$code))$matched=$step;
    if($matched===null)return false;
    $pdo->beginTransaction();try{
        $key='security.totp_counter.'.$u['id'];$ins=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE':'INSERT IGNORE';$pdo->prepare("$ins INTO settings (setting_key,setting_value) VALUES (?,'0')")->execute([$key]);
        $cast=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INTEGER':'UNSIGNED';$q=$pdo->prepare("UPDATE settings SET setting_value=? WHERE setting_key=? AND CAST(setting_value AS $cast)<?");$q->execute([(string)$matched,$key,$matched]);if($q->rowCount()!==1){$pdo->rollBack();return false;}$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    session_regenerate_id(true);unset($_SESSION['csrf_token']);$_SESSION['central_mfa_at']=time();domain_audit('central_verified','session');return true;
}
function totp_session_valid(): bool {return !empty($_SESSION['central_mfa_at'])&&time()-(int)$_SESSION['central_mfa_at']<1800;}
