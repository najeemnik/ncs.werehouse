<?php
/** One-time bootstrap. Configuration and locks live outside the public directory. */
ini_set('display_errors','0');ini_set('log_errors','1');error_reporting(E_ALL);
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
define('NSC_INTERNAL_SETTINGS',true);require_once __DIR__.'/../api/private-settings.php';
define('NSC_INTERNAL_FIRST_INSTALL',true);$credentialFile=__DIR__.'/../api/first-install-credentials.php';$credentials=require (is_file($credentialFile)?$credentialFile:__DIR__.'/../api/first-install-credentials.example.php');if(!preg_match('/^[a-f0-9]{64}$/D',(string)$credentials['keyHash'])||!str_starts_with((string)$credentials['passwordHash'],'$2y$')){http_response_code(503);exit('Setup is not configured. Configure private bootstrap hashes on the server or create the first administrator using the CLI.');}
$testing=getenv('APP_ENV')==='testing';
$secure=in_array(strtolower((string)($_SERVER['HTTPS']??'')),['on','1'],true);
$proxies=array_filter(array_map('trim',explode(',',getenv('TRUSTED_PROXY_IPS')?:'')));
if(!$secure&&in_array($_SERVER['REMOTE_ADDR']??'',$proxies,true))$secure=($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https';
if(!$testing&&!$secure){http_response_code(426);exit('HTTPS is required. Open the secure https:// address of your site.');}
if($secure)header('Strict-Transport-Security: max-age=31536000');
$host=$_SERVER['HTTP_HOST']??'';
if(!preg_match('/^[a-zA-Z0-9.-]+(?::[0-9]{1,5})?$/D',$host)){http_response_code(400);exit('Invalid site address.');}
$origin=($secure?'https://':'http://').$host;
if(($_SERVER['HTTP_ORIGIN']??$origin)!==$origin||($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site'){http_response_code(403);exit('Open setup from this site.');}
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true)){http_response_code(405);exit;}
$configPath=nscPrivateConfigPath();$existing=nscReadPrivateSettings();
if(($existing['_setupPhase']??'')==='completed'){http_response_code(410);exit('Setup is complete and locked. Sign in from the home page.');}
if($existing&&(!hash_equals($credentials['keyHash'],(string)($existing['_setupKeyHash']??''))||($existing['_setupPhase']??'')!=='pending')){http_response_code(409);exit('This host is already configured. Setup cannot replace its configuration.');}
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');session_name('nsc_bootstrap');
session_set_cookie_params(['lifetime'=>0,'path'=>'/setup/','secure'=>!$testing,'httponly'=>true,'samesite'=>'Strict']);session_start();
$_SESSION['csrf']??=bin2hex(random_bytes(32));
if(isset($_GET['key'])){
 if(!is_string($_GET['key'])||!hash_equals($credentials['keyHash'],hash('sha256',$_GET['key']))){http_response_code(403);exit('Invalid private setup key.');}
 session_regenerate_id(true);$_SESSION['setupVerified']=time();header('Location: ./',true,303);exit;
}
$verified=isset($_SESSION['setupVerified'])&&time()-$_SESSION['setupVerified']<1800;
$error='';$success=false;$officeLink='';
function setupText(string $value):string{return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function setupWriteConfig(string $path,array $values):void{
 $root=realpath(dirname(__DIR__));$dir=realpath(dirname($path));
 if(!$dir||!is_writable($dir)||$dir===$root||str_starts_with($dir,$root.DIRECTORY_SEPARATOR))throw new RuntimeException('Private configuration directory unavailable');
 $tmp=tempnam($dir,'.nsc-config-');if($tmp===false)throw new RuntimeException('Cannot create private configuration');
 try{chmod($tmp,0600);$data="<?php\nif(!defined('NSC_INTERNAL_SETTINGS')){http_response_code(403);exit;}\nreturn ".var_export($values,true).";\n";
  if(file_put_contents($tmp,$data,LOCK_EX)!==strlen($data)||!rename($tmp,$path))throw new RuntimeException('Cannot save private configuration');
 }finally{if(is_file($tmp))unlink($tmp);}
}
if($method==='POST'){
 if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit('Invalid request token. Reload setup.');}
 if(!$verified){
  if(!is_string($_POST['key']??null)||!hash_equals($credentials['keyHash'],hash('sha256',$_POST['key']))){http_response_code(403);$error='کلید خصوصی راه‌اندازی درست نیست. / Invalid private setup key.';}
  else{session_regenerate_id(true);$_SESSION['setupVerified']=time();header('Location: ./',true,303);exit;}
 }else{
  $lock=null;$db=null;
  try{
   foreach(['dbHost','dbName','dbUser','dbPassword'] as $field)if(!is_string($_POST[$field]??null))throw new InvalidArgumentException('Invalid connection fields');
   $dbHost=trim($_POST['dbHost']);$dbName=trim($_POST['dbName']);$dbUser=trim($_POST['dbUser']);$dbPassword=$_POST['dbPassword'];
   if(!preg_match('/^[a-zA-Z0-9.-]{1,253}$/D',$dbHost)||!preg_match('/^[a-zA-Z0-9_]{1,64}$/D',$dbName)||!preg_match('/^[a-zA-Z0-9_]{1,80}$/D',$dbUser)||$dbPassword===''||strlen($dbPassword)>1024)throw new InvalidArgumentException('Invalid connection fields');
   $privateDir=realpath(dirname($configPath));$publicRoot=realpath(dirname(__DIR__));
   if(!$privateDir||!is_writable($privateDir)||$privateDir===$publicRoot||str_starts_with($privateDir,$publicRoot.DIRECTORY_SEPARATOR))throw new RuntimeException('Private configuration directory unavailable');
   $lock=fopen($configPath.'.install-lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Setup is busy');chmod($configPath.'.install-lock',0600);
   $existing=nscReadPrivateSettings();
   if($existing&&(($existing['_setupPhase']??'')!=='pending'||!hash_equals($credentials['keyHash'],(string)($existing['_setupKeyHash']??''))))throw new RuntimeException('Setup is locked');
   if($existing&&($existing['DB_HOST']!==$dbHost||$existing['DB_NAME']!==$dbName||$existing['DB_USER']!==$dbUser))throw new RuntimeException('Pending setup uses a different database');
   $db=new PDO('mysql:host='.$dbHost.';port=3306;dbname='.$dbName.';charset=utf8mb4',$dbUser,$dbPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
   $tableCount=(int)$db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn();
   if($tableCount&&!$existing)throw new RuntimeException('Database must be empty; existing data will not be changed');
   if($existing){$hasUsers=$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='users'")->fetchColumn();if($hasUsers&&(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()>0)throw new RuntimeException('Database already has users; setup cannot reset them');}
   $values=['DB_HOST'=>$dbHost,'DB_PORT'=>'3306','DB_NAME'=>$dbName,'DB_USER'=>$dbUser,'DB_PASS'=>$dbPassword,'APP_ENV'=>$testing?'testing':'production','APP_ORIGIN'=>$origin,'UPLOAD_DIR'=>$privateDir.'/nsc-private-uploads/','_setupPhase'=>'pending','_setupKeyHash'=>$credentials['keyHash']];
   setupWriteConfig($configPath,$values);
   define('NSC_INTERNAL_MIGRATION',true);require_once __DIR__.'/../api/schema-migration.php';nscMigrateDatabase($db);
   // The filesystem lock serializes bootstrap. Account creation is transactional.
   $db->beginTransaction();if((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()>0)throw new RuntimeException('Database already has users');
   $b=random_bytes(16);$b[6]=chr(ord($b[6])&15|64);$b[8]=chr(ord($b[8])&63|128);$id=vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4));
   $s=$db->prepare("INSERT INTO users(id,name,username,email,password_hash,role,is_master,subscription_plan) VALUES(:id,:name,:username,:email,:hash,'master',1,'lifetime')");
   $s->execute(['id'=>$id,'name'=>$credentials['name'],'username'=>$credentials['username'],'email'=>$credentials['email'],'hash'=>$credentials['passwordHash']]);
   $token=bin2hex(random_bytes(32));$s=$db->prepare('INSERT INTO office7_control(id,owner_user_id,path_token) VALUES(1,:id,:token)');$s->execute(['id'=>$id,'token'=>$token]);
   $s=$db->prepare("INSERT INTO system_audit_logs(tenant_id,user_id,user_name,action,module,record_id,ip_address,user_agent,details) VALUES(:tenant,:user,:name,'create','office7',:record,:ip,'NSC first setup','Primary Office Seven account created; installer locked')");
   $s->execute(['tenant'=>$id,'user'=>$id,'name'=>$credentials['name'],'record'=>$id,'ip'=>$_SERVER['REMOTE_ADDR']??'']);
   $db->commit();$values['_setupPhase']='completed';setupWriteConfig($configPath,$values);
   $officeLink='/#/office7/'.$token;$success=true;$_SESSION=[];session_destroy();
  }catch(Throwable $e){if($db&&$db->inTransaction())$db->rollBack();error_log('NSC first setup failed: '.get_class($e));http_response_code(400);$error='راه‌اندازی تکمیل نشد. مشخصات دیتابیس را چک کن؛ دیتابیس باید خالی باشد و پوشهٔ بیرون htdocs قابل نوشتن باشد. / Setup failed. Check the connection, an empty database, and write access outside htdocs.';}
  finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}
 }
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>NSC · راه‌اندازی دفتر هفت</title><style>html{font:16px system-ui;color:#172b43;background:#eaf0f7}body{margin:0;padding:40px 16px}main{max-width:560px;margin:auto;background:#fff;padding:28px;border-radius:20px;box-shadow:0 16px 48px #142c4914}h1{font-size:24px}p{line-height:1.8;color:#40566d}label{display:block;margin:18px 0 6px}input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #b8c8d9;border-radius:10px;font:inherit;direction:ltr}button,a.primary{display:block;margin-top:24px;background:#1d4ed8;color:white;border:0;padding:13px;border-radius:10px;font:inherit;text-align:center;text-decoration:none;cursor:pointer;width:100%;box-sizing:border-box}.error{color:#9f1239;background:#fff1f2;padding:12px;border-radius:10px}.success{color:#065f46;background:#ecfdf5;padding:12px;border-radius:10px}code{direction:ltr;display:inline-block}</style></head><body><main><h1>راه‌اندازی دفتر هفت · Office Seven</h1>
<?php if($success):?><p class="success">حساب مدیر اصلی ساخته شد و راه‌اندازی بسته شد. / Primary account created; setup is now locked.</p><p>نام استفاده‌کننده: <code><?=setupText($credentials['username'])?></code><br>رمز ورود همان رمز خصوصی است که همراه نسخه برایت فرستاده شده.</p><a class="primary" href="<?=setupText($officeLink)?>">ورود دفتر هفت / Open Office Seven</a><p>این لینک خصوصی را نگه‌دار. بعد از ورود می‌توانی مالکین را بسازی و رمز خود را تغییر بدهی.</p>
<?php else:?><?php if($error):?><p class="error" role="alert"><?=setupText($error)?></p><?php endif?><form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=setupText($_SESSION['csrf'])?>">
<?php if(!$verified):?><p>کلید خصوصی راه‌اندازی را وارد کن. این صفحه فقط حساب اول را در دیتابیس خالی می‌سازد.</p><label for="key">کلید راه‌اندازی / Private setup key</label><input id="key" name="key" type="password" required minlength="64" maxlength="64" autocomplete="off"><button>ادامه / Continue</button>
<?php else:?><p>دیتابیس ساخته‌شده را وصل کن. رمز vPanel را فقط همین‌جا، روی سایت HTTPS خودت درج کن. حساب اول <code><?=setupText($credentials['username'])?></code> ساخته می‌شود.</p>
<?php foreach(['dbHost'=>['MySQL Host','sql211.ezyro.com'],'dbName'=>['Database Name','ezyro_42752204_nsc'],'dbUser'=>['MySQL Username','ezyro_42752204']] as $name=>[$title,$value]):?><label for="<?=setupText($name)?>"><?=setupText($title)?></label><input id="<?=setupText($name)?>" name="<?=setupText($name)?>" value="<?=setupText(is_string($_POST[$name]??null)?$_POST[$name]:$value)?>" required><?php endforeach?><label for="dbPassword">MySQL Password · رمز vPanel</label><input id="dbPassword" name="dbPassword" type="password" required autocomplete="new-password"><button>وصل‌کردن دیتابیس و ساخت حساب اول / Install</button>
<?php endif?></form><?php endif?></main></body></html>
