<?php
/** Shared security helpers; existing users and password hashes remain authoritative. */
function security_epoch(){return get_setting('security.session_epoch','0');}
function security_user_version($id){return get_setting('security.user_version.'.(int)$id,'0');}
function security_forget_auth(){foreach(['authenticated','user_id','user_username','user_fullname','user_role','auth_started','auth_seen','auth_version','auth_epoch','auth_fingerprint','super_admin_verified','is_super_admin','screen_locked','office2_version','central_mfa_at','shop_context_id'] as $key)unset($_SESSION[$key]);unset($GLOBALS['_auth_user']);}
function security_establish_session(array $user) {
    session_regenerate_id(true);
    security_forget_auth();
    $_SESSION['authenticated']=true;$_SESSION['user_id']=(int)$user['id'];$_SESSION['user_username']=$user['username'];$_SESSION['user_fullname']=$user['fullname'];$_SESSION['user_role']=$user['role'];
    $_SESSION['auth_started']=time();$_SESSION['auth_seen']=time();$_SESSION['auth_version']=security_user_version($user['id']);$_SESSION['auth_epoch']=security_epoch();$_SESSION['auth_fingerprint']=hash('sha256',$user['password']);$_SESSION['screen_locked']=false;
    unset($_SESSION['csrf_token'],$GLOBALS['_ui_preferences']);
    $chosen=$_SESSION['app_lang_pending']??null;
    ui_apply_language_choice((int)$user['id']);
    $preferences=ui_preferences($user['id']);
    $_SESSION['app_lang']=is_string($chosen)&&isset(get_supported_languages()[$chosen])?$chosen:$preferences['language'];
    app_set_preference_cookie('app_lang',$_SESSION['app_lang'],time()+86400*365);
}
function security_password_matches($input,$stored) {
    if(!is_string($input)||strlen($input)>1024||!is_string($stored))return false;
    return password_verify($input,$stored);
}
function security_pin_matches($input,$stored) {
    if(!is_string($input)||!is_string($stored)||$stored===''||strlen($input)>256)return false;
    $value=str_replace([' ','-','_'],'',normalize_numbers(trim($input)));
    return password_get_info($stored)['algoName']!=='unknown'?(password_verify($input,$stored)||password_verify($value,$stored)):(hash_equals($stored,$input)||hash_equals($stored,$value));
}
function security_attempt($purpose,$identity,$limit=6,$window=600) {
    global $pdo;
    $bucket=(int)floor(time()/$window);$key='security.rate.'.$bucket.'.'.substr(hash('sha256',$purpose.'|'.$identity),0,40);
    if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')$sql='INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=CAST(setting_value AS INTEGER)+1';
    else $sql='INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=CAST(setting_value AS UNSIGNED)+1';
    $pdo->prepare($sql)->execute([$key,'1']);$stmt=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$stmt->execute([$key]);
    return (int)$stmt->fetchColumn()<=$limit;
}
function security_clear_attempts($purpose,$identity,$window=600){global $pdo;$key='security.rate.'.(int)floor(time()/$window).'.'.substr(hash('sha256',$purpose.'|'.$identity),0,40);$pdo->prepare('DELETE FROM settings WHERE setting_key=?')->execute([$key]);}
function security_validate_new_password($password,$confirmation) {
    if(!is_string($password)||!is_string($confirmation)||!hash_equals($password,$confirmation))throw new InvalidArgumentException(__t('The password confirmation does not match.','تکرار رمز مطابقت ندارد.','د رمز تایید سمون نه خوري.'));
    if(mb_strlen($password,'UTF-8')<12||strlen($password)>72||$password!==trim($password)||preg_match('/[\x00-\x1f\x7f]/',$password)||preg_match('/^\p{N}+$/u',$password))throw new InvalidArgumentException(__t('Use at least 12 characters, not digits only; maximum 72 bytes. Do not add spaces at the start or end.','حداقل ۱۲ کاراکتر و غیر از اعدادِ تنها؛ حداکثر ۷۲ بایت. اول و آخر رمز فاصله نگذارید.','لږ تر لږه ۱۲ توري، یوازې شمېرې نه؛ تر ۷۲ بایټه. په پیل او پای کې تشه مه ږدئ.'));
}
function security_current_password($password) {
    global $pdo;$user=auth_user();if(!$user||!is_string($password))return false;
    if(!security_attempt('reauth',(string)$user['id'],8))return false;
    $stmt=$pdo->prepare('SELECT password FROM users WHERE id=? AND status=?');$stmt->execute([$user['id'],'active']);
    $valid=security_password_matches($password,$stmt->fetchColumn());
    if($valid)security_clear_attempts('reauth',(string)$user['id']);
    return $valid;
}
function security_change_password($current,$next,$confirmation) {
    global $pdo;$user=auth_user();if(!$user||!security_current_password($current))throw new InvalidArgumentException(__t('Current password is incorrect, or too many attempts.','رمز فعلی نادرست است یا تلاش‌های زیادی انجام شده است.','اوسنی رمز ناسم دی یا ډېرې هڅې شوې دي.'));
    security_validate_new_password($next,$confirmation);
    $stmt=$pdo->prepare('SELECT * FROM users WHERE id=?');$stmt->execute([$user['id']]);$record=$stmt->fetch();
    if(security_password_matches($next,$record['password']))throw new InvalidArgumentException(__t('Choose a different new password.','رمز جدید باید با رمز فعلی متفاوت باشد.','نوی رمز باید له اوسني څخه توپیر ولري.'));
    $hash=password_hash($next,PASSWORD_DEFAULT);$version=(int)security_user_version($user['id'])+1;
    $pdo->beginTransaction();
    try{
        $q=$pdo->prepare('UPDATE users SET password=? WHERE id=? AND password=?');$q->execute([$hash,$user['id'],$record['password']]);
        if($q->rowCount()!==1||!update_setting('security.user_version.'.$user['id'],(string)$version))throw new RuntimeException('Concurrent password update.');
        $pdo->prepare('UPDATE user_access SET must_change_password=0,updated_at=CURRENT_TIMESTAMP WHERE user_id=?')->execute([$user['id']]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw new RuntimeException(__t('Password was not changed. Please refresh and try again.','رمز تغییر نکرد؛ صفحه را تازه و دوباره تلاش کنید.','رمز بدل نه شو؛ پاڼه تازه او بیا هڅه وکړئ.'));}
    $record['password']=$hash;security_establish_session($record);
    log_activity($user['id'],'Password changed','Own password updated; other sessions invalidated.');
}
function security_safe_return($url) {
    if(!is_string($url)||strlen($url)>2000||str_contains($url,'\\')||preg_match('/[\r\n\x00]/',$url))return BASE_URL.'index.php';
    $decoded=rawurldecode($url);
    if(str_contains($decoded,'..')||str_starts_with($decoded,'//')||!str_starts_with($decoded,BASE_URL)||parse_url($decoded,PHP_URL_HOST)||str_contains($decoded,':'))return BASE_URL.'index.php';
    $parts=parse_url($url);$query=[];
    if(isset($parts['query']))parse_str($parts['query'],$query);
    foreach(['sid','nikxor_sid','PHPSESSID',session_name()] as $key)unset($query[$key]);
    return ($parts['path']??BASE_URL.'index.php').($query?'?'.http_build_query($query):'');
}
function security_private_dir() {
    if(!getenv('NIKXOR_PRIVATE_DIR')&&hosting_profile())return hosting_subdirectory('private');
    $documentRoot=$_SERVER['DOCUMENT_ROOT']??BASE_DIR;$root=$documentRoot!==''?realpath($documentRoot):realpath(BASE_DIR.'/..');
    if(!$root)throw new RuntimeException('Web root could not be resolved safely.');
    $configured = getenv('NIKXOR_PRIVATE_DIR')
    ?: dirname(BASE_DIR).'/var/private';
    $dir=$configured?:dirname($root).'/.nikxor-private-'.substr(hash('sha256',BASE_DIR),0,12);
    if(!is_dir($dir)&&!@mkdir($dir,0700,true))throw new RuntimeException(__t('Private backup storage is not writable. Configure NIKXOR_PRIVATE_DIR outside the web root.','محل خصوصی بکاپ قابل نوشتن نیست؛ NIKXOR_PRIVATE_DIR باید بیرون از پوشهٔ عمومی تنظیم شود.','د خصوصي شاتړ ځای ته لیکل نه کېږي؛ NIKXOR_PRIVATE_DIR د ویب له ریښې بهر وټاکئ.'));
    $dir=realpath($dir);
    if(!$dir||$dir===$root||str_starts_with($dir.DIRECTORY_SEPARATOR,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)||!is_writable($dir))throw new RuntimeException(__t('Backup storage must be private and outside the web root.','ذخیرهٔ بکاپ باید خصوصی و خارج از پوشهٔ عمومی باشد.','شاتړ باید خصوصي او د ویب له ریښې بهر وي.'));
    @chmod($dir,0700);return $dir;
}
function security_screen_enabled(){return get_setting('security.screen_enabled.'.(int)($_SESSION['user_id']??0),get_setting('screen_lock_enabled','1'))==='1';}
function security_screen_pin(){return get_setting('security.screen_pin.'.(int)($_SESSION['user_id']??0),'');}
function security_dummy_hash(){static $hash=null;if($hash===null){$hash=get_setting('security.dummy_hash','');if($hash===''){$hash=password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT);update_setting('security.dummy_hash',$hash);}}return $hash;}
