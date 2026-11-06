<?php
/** Owner-key-gated preparation. Only harmless probes precede the browser's denial checks. */
ini_set('display_errors','0');require_once __DIR__.'/includes/runtime_settings.php';
header('Cache-Control: no-store');header('X-Frame-Options: DENY');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
$https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||((getenv('NIKXOR_TRUST_PROXY')==='1')&&($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
if(!$https&&getenv('NIKXOR_ALLOW_LOCAL_INSTALL')!=='1'){http_response_code(403);exit('HTTPS is required.');}
$profile=hosting_profile();if(!$profile){http_response_code(404);exit('This installation does not use a protected hosting profile.');}
if(hosting_protection_valid()){if(hosting_installed()){http_response_code(404);exit('Not found.');}header('Location: '.hosting_url('install.php'),true,303);exit;}
// Use the host's normal session service until the protected application storage is verified.
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');hosting_validate_native_session();session_name('NSC_HOST_PREPARE');session_set_cookie_params(['secure'=>$https,'httponly'=>true,'samesite'=>'Strict','path'=>'/']);
if(!session_start()){http_response_code(503);exit('The hosting session service is not available.');}
$lang=($_GET['lang']??$_SESSION['host_lang']??'da')==='en'?'en':'da';$_SESSION['host_lang']=$lang;
function ht($en,$da){return $GLOBALS['lang']==='en'?$en:$da;}function he($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$_SESSION['host_csrf']??=bin2hex(random_bytes(32));$error='';$stage='key';$directory=hosting_container();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 try{
  if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['host_csrf'],$_POST['csrf']))throw new RuntimeException(ht('Refresh the form and try again.','فورم را تازه و دوباره تلاش کنید.'),403);
  $action=$_POST['action']??'';
  if($action==='start'){
   if((int)($_SESSION['host_attempts']??0)>=10&&time()-(int)($_SESSION['host_attempt_at']??0)<600)throw new RuntimeException(ht('Too many attempts. Try again later.','تلاش‌ها زیاد شد؛ بعداً کوشش کنید.'),429);
   if(time()-(int)($_SESSION['host_attempt_at']??0)>=600){$_SESSION['host_attempts']=0;$_SESSION['host_attempt_at']=time();}$_SESSION['host_attempts']=(int)($_SESSION['host_attempts']??0)+1;
   $key=$_POST['setup_key']??'';if(!is_string($key)||strlen($key)>128||!hash_equals($profile['setup_token_hash'],hash('sha256',trim($key))))throw new RuntimeException(ht('Incorrect private installation key.','کلید خصوصی نصب نادرست است.'),403);
   if(!hosting_root_rules_valid())throw new RuntimeException(ht('Upload the supplied htdocs .htaccess before continuing.','فایل .htaccess همراه بسته را در خود htdocs آپلود کنید.'),409);
   if(is_link($directory))throw new RuntimeException('Invalid storage path.',409);
   $marker=$directory.'/owner-profile.php';$fingerprint=hash('sha256',json_encode($profile,JSON_THROW_ON_ERROR));
   if(is_dir($directory)){
    if(!is_file($marker)||is_link($marker)||($saved=require $marker)!==$fingerprint)throw new RuntimeException(ht('The selected storage directory already contains unrelated files. Nothing was changed.','این پوشه از قبل فایل‌های نامربوط دارد؛ چیزی تغییر نکرد.'),409);
   }else{
    if(!@mkdir($directory,0755))throw new RuntimeException(ht('The protected directory could not be created.','پوشهٔ محافظت‌شده ساخته نشد.'),503);
    $owner=hosting_php_guard().'return '.var_export($fingerprint,true).';';if(file_put_contents($marker,$owner,LOCK_EX)!==strlen($owner))throw new RuntimeException('Storage marker write failed.',503);chmod($marker,0600);
   }
   if(file_put_contents($directory.'/.htaccess',hosting_deny_rules(),LOCK_EX)!==strlen(hosting_deny_rules()))throw new RuntimeException('Protection rules were not saved.',503);
   session_regenerate_id(true);$_SESSION['host_authorized_at']=time();$_SESSION['host_binding']=hosting_binding();$_SESSION['host_probe']=bin2hex(random_bytes(16));
   $name='probe-'.$_SESSION['host_probe'];$payload='Harmless hosting protection probe.';
   if(file_put_contents($directory.'/'.$name.'.txt',$payload,LOCK_EX)!==strlen($payload)||file_put_contents($directory.'/'.$name.'.php','<?php echo "Harmless hosting protection probe.";',LOCK_EX)===false)throw new RuntimeException('Probe preparation failed.',503);
   $stage='probe';
  }elseif($action==='activate'){
   if(empty($_SESSION['host_authorized_at'])||time()-(int)$_SESSION['host_authorized_at']>600||!hash_equals((string)($_SESSION['host_binding']??''),hosting_binding())||!preg_match('/^[a-f0-9]{32}$/D',$_SESSION['host_probe']??''))throw new RuntimeException(ht('Preparation expired or the profile changed. Enter the private key again.','آماده‌سازی پایان یافت یا تنظیمات تغییر کرد؛ کلید خصوصی را دوباره وارد کنید.'),403);
   if(($_POST['txt_status']??'')!=='403'||($_POST['php_status']??'')!=='403')throw new RuntimeException(ht('Both access checks must return 403. No application keys were created.','هر دو بررسی باید کُد ۴۰۳ بدهند؛ کلید برنامه ساخته نشد.'),409);
   if(!hosting_root_rules_valid()||is_link($directory)||!is_file($directory.'/.htaccess')||!hash_equals(hash('sha256',hosting_deny_rules()),hash_file('sha256',$directory.'/.htaccess')))throw new RuntimeException('Protection rules changed. Restart preparation.',409);
   $name='probe-'.$_SESSION['host_probe'];foreach(['txt','php'] as $ext){$path=$directory.'/'.$name.'.'.$ext;if(is_link($path)||!is_file($path))throw new RuntimeException('Protection probe is missing.',409);}
   $proof=['version'=>1,'binding'=>hosting_binding(),'txt_status'=>403,'php_status'=>403,'checked_at'=>gmdate('c')];$temp=tempnam($directory,'proof-');chmod($temp,0600);$body=hosting_php_guard().'return '.var_export($proof,true).';';
   if(file_put_contents($temp,$body,LOCK_EX)!==strlen($body)||!rename($temp,$directory.'/protection.php'))throw new RuntimeException('Protection proof could not be saved.',503);
   foreach(['txt','php'] as $ext)if(!unlink($directory.'/'.$name.'.'.$ext))throw new RuntimeException('Could not clear protection probe.',503);
   if(!hosting_protection_valid())throw new RuntimeException('Storage protection could not be validated.',503);
   hosting_subdirectory('runtime');hosting_subdirectory('private');
   $_SESSION=[];session_destroy();header('Location: '.hosting_url(hosting_installed()?'index.php':'install.php'),true,303);exit;
  }else throw new RuntimeException('Invalid preparation action.',400);
 }catch(Throwable $e){http_response_code(in_array($e->getCode(),[400,403,409,429,503],true)?$e->getCode():503);$error=$e instanceof RuntimeException?$e->getMessage():ht('Hosting preparation failed. No database was changed.','آماده‌سازی هاست تکمیل نشد؛ دیتابیس تغییر نکرد.');}
}
$nonce=base64_encode(random_bytes(18));header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'nonce-".$nonce."'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
?><!doctype html><html lang="<?= $lang==='en'?'en':'da' ?>" dir="<?= $lang==='en'?'ltr':'rtl' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= ht('Prepare hosting','آماده‌سازی هاست') ?></title><style>body{font:15px/1.8 Tahoma,Arial,sans-serif;background:#eef3f9;color:#20334c;margin:0;padding:16px}main{max-width:620px;margin:30px auto;background:white;padding:26px;border:1px solid #d9e3f0;border-radius:16px}h1{font-size:23px}input,button{box-sizing:border-box;font:inherit;padding:11px;border-radius:9px;border:1px solid #becbdb;width:100%;margin:8px 0}button{background:#2458bc;color:white;cursor:pointer}button:disabled{opacity:.5;cursor:default}.error{color:#a32635;background:#fff0f0;padding:12px}.result{background:#f0f4fa;padding:12px;border-radius:10px;margin:12px 0}.ok{color:#13633d}.no{color:#a32635}p{font-size:14px}a{color:#2458bc}</style></head><body><main>
<h1><?= ht('Prepare hosting','آماده‌سازی هاست') ?></h1><p><a href="?lang=en">English</a> · <a href="?lang=da"><?= ht('Dari','دری') ?></a></p>
<p><?= ht('This host uses a protected directory within the website. Continue only when both browser checks deny access. No application secrets or database credentials are created before these checks.','در این هاست، تنظیمات در پوشه‌ای محافظت‌شده داخل ویب‌سایت نگهداری می‌شود. تنها وقتی هر دو بررسی دسترسی را ببندند، ادامه بدهید. پیش از این بررسی‌ها، رمز دیتابیس یا کلید برنامه ساخته نمی‌شود.') ?></p>
<?php if($error):?><p class="error" role="alert"><?= he($error) ?></p><?php endif ?>
<?php if($stage==='key'):?><form method="post"><input type="hidden" name="csrf" value="<?= he($_SESSION['host_csrf']) ?>"><input type="hidden" name="action" value="start"><label><?= ht('Private installation key','کلید خصوصی نصب') ?><input name="setup_key" type="password" required autocomplete="off" maxlength="128"></label><p><?= ht('Read this key from INSTALL-KEY.php outside the htdocs folder in your downloaded package. Never upload that text file.','کلید را از فایل INSTALL-KEY.php بیرون از فولدر htdocs در بستهٔ دانلودشده بخوانید. آن فایل متنی را آپلود نکنید.') ?></p><button><?= ht('Check storage protection','بررسی محافظت محل تنظیمات') ?></button></form>
<?php else:?><div id="hostProbeResult" class="result" role="status"><?= ht('Checking browser access…','در حال بررسی دسترسی مرورگر…') ?></div>
<form method="post" id="activateHosting"><input type="hidden" name="csrf" value="<?= he($_SESSION['host_csrf']) ?>"><input type="hidden" name="action" value="activate"><input type="hidden" name="txt_status" value=""><input type="hidden" name="php_status" value=""><button disabled id="activateButton"><?= ht('Continue to installation','ادامهٔ نصب') ?></button></form>
<script nonce="<?= he($nonce) ?>">
(async()=>{const result=document.getElementById('hostProbeResult'),button=document.getElementById('activateButton'),form=document.getElementById('activateHosting');const base=<?= json_encode('./'.$profile['directory'].'/probe-'.$_SESSION['host_probe'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;try{const statuses=[];for(const ext of ['txt','php']){const url=new URL(base+'.'+ext,location.href);url.searchParams.set('check',String(Date.now()));const response=await fetch(url,{credentials:'same-origin',cache:'no-store',redirect:'error'});statuses.push(response.status);form.elements[ext+'_status'].value=String(response.status);}if(statuses.every(code=>code===403)){result.className='result ok';result.textContent=<?= json_encode(ht('Both files are blocked (403). You can continue.','دسترسی هر دو فایل بسته است (۴۰۳). می‌توانید ادامه بدهید.')) ?>;button.disabled=false;}else{result.className='result no';result.textContent=<?= json_encode(ht('Protection failed. Do not continue or store credentials here.','محافظت تأیید نشد؛ ادامه ندهید و رمزها را اینجا ذخیره نکنید.')) ?>;}}catch(_){result.className='result no';result.textContent=<?= json_encode(ht('The access checks did not complete. No application keys were created.','بررسی دسترسی تکمیل نشد؛ کلید برنامه ساخته نشد.')) ?>;}})();
</script><?php endif ?></main></body></html>
