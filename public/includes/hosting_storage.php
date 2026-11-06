<?php
/** Explicit, web-server-protected storage for hosts without a writable private parent. */
function hosting_profile_file(): string {
    $test=getenv('NIKXOR_HOST_PROFILE');
    if(PHP_SAPI==='cli'&&getenv('NIKXOR_TEST_MODE')==='1'&&is_string($test)&&$test!=='')return $test;
    return __DIR__.'/../config/hosting.php';
}
function hosting_profile(): ?array {
    $file=hosting_profile_file();if(!is_file($file))return null;
    if(is_link($file))throw new RuntimeException('Invalid hosting profile.');
    $p=require $file;
    if(!is_array($p)||($p['version']??null)!==1||($p['mode']??'')!=='protected'||!preg_match('/^nsc-private-[a-f0-9]{24}$/D',$p['directory']??'')||!preg_match('/^[a-f0-9]{64}$/D',$p['setup_token_hash']??''))throw new RuntimeException('Invalid hosting profile.');
    return $p;
}
function hosting_public_root(): string {$root=realpath(dirname(dirname(hosting_profile_file())));if(!$root)throw new RuntimeException('Hosting root unavailable.');return $root;}
function hosting_root_rule(): string {return 'RewriteRule ^nsc-private-[a-f0-9]{24}(?:/|$) - [F,L]';}
function hosting_deny_rules(): string {return "# Nik Smart Count protected storage v1\n<IfModule mod_autoindex.c>\nIndexIgnore *\n</IfModule>\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n";}
function hosting_container(): string {$p=hosting_profile();if(!$p)throw new RuntimeException('No protected hosting profile.');return hosting_public_root().'/'.$p['directory'];}
function hosting_root_rules_valid(): bool {$path=hosting_public_root().'/.htaccess';return is_file($path)&&!is_link($path)&&str_contains((string)file_get_contents($path),hosting_root_rule());}
function hosting_binding(): string {$p=hosting_profile();$root=hosting_public_root();$served=$_SERVER['DOCUMENT_ROOT']??'';return hash('sha256',json_encode(['version'=>1,'profile'=>$p,'root'=>$root,'root_rules_hash'=>is_file($root.'/.htaccess')?hash_file('sha256',$root.'/.htaccess'):'','document_root'=>is_string($served)&&$served!==''?realpath($served):$root],JSON_THROW_ON_ERROR));}
function hosting_protection_valid(): bool {
    if(!hosting_profile()||!hosting_root_rules_valid())return false;
    $dir=hosting_container();if(is_link($dir)||!is_dir($dir))return false;
    $rules=$dir.'/.htaccess';$proof=$dir.'/protection.php';
    if(!is_file($rules)||is_link($rules)||!hash_equals(hash('sha256',hosting_deny_rules()),hash_file('sha256',$rules))||!is_file($proof)||is_link($proof))return false;
    $p=require $proof;return is_array($p)&&($p['version']??0)===1&&is_string($p['binding']??null)&&hash_equals(hosting_binding(),$p['binding'])&&($p['txt_status']??0)===403&&($p['php_status']??0)===403;
}
function hosting_installed(): bool {return hosting_profile()&&is_file(hosting_container().'/runtime/installed.lock');}
function hosting_internal_path(string $path): bool {
    if(!hosting_protection_valid())return false;$root=realpath(hosting_container());$resolved=realpath($path);return $root&&$resolved&&$resolved!==$root&&str_starts_with($resolved,$root.DIRECTORY_SEPARATOR)&&!is_link($path);
}
function hosting_subdirectory(string $name): string {
    if(!in_array($name,['runtime','private','sessions'],true)||!hosting_protection_valid())throw new RuntimeException('Protected hosting storage has not been verified. Open prepare-host.php.');
    $dir=hosting_container().'/'.$name;if(is_link($dir)||(!is_dir($dir)&&!@mkdir($dir,0700,true))||!hosting_internal_path($dir)||!is_writable($dir))throw new RuntimeException('Protected hosting storage is unavailable.');return realpath($dir);
}
function hosting_url(string $file): string {
    $script=str_replace('\\','/',$_SERVER['SCRIPT_NAME']??'/index.php');$base='';foreach(['/admin/','/modules/','/includes/','/config/','/print/'] as $part){$pos=strpos($script,$part);if($pos!==false){$base=substr($script,0,$pos);break;}}
    if($base===''&&!preg_match('~/(?:admin|modules|includes|config|print)/~',$script))$base=rtrim(dirname($script),'/\\.');
    return ($base?:'').'/'.$file;
}
function hosting_bootstrap_guard(): void {
    if(PHP_SAPI==='cli'||!hosting_profile())return;
    if(!hosting_protection_valid()){header('Location: '.hosting_url('prepare-host.php'),true,303);exit;}
}
function hosting_php_guard(): string {return "<?php\nif(PHP_SAPI!=='cli' && realpath(\$_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}\n";}
function hosting_write_secret(string $path,string $bytes): void {
    if(is_link($path))throw new RuntimeException('Invalid secret path.');$contents=hosting_php_guard().'return '.var_export(base64_encode($bytes),true).';';$h=@fopen($path,'x');if(!$h)throw new RuntimeException('Private key already exists or cannot be created.');
    @chmod($path,0600);$saved=false;try{if(fwrite($h,$contents)!==strlen($contents)||!fflush($h))throw new RuntimeException('Private key was not saved.');$saved=true;}finally{fclose($h);if(!$saved)@unlink($path);}
}
function hosting_read_secret(string $path): string {
    if(is_link($path)||!is_file($path)||filesize($path)>4096)throw new RuntimeException('Invalid private key.');$encoded=require $path;if(!is_string($encoded)||($decoded=base64_decode($encoded,true))===false)throw new RuntimeException('Invalid private key.');return $decoded;
}
/** Use the provider's native session service; never put raw session records in a public folder. */
function hosting_validate_native_session(): void {
    if(ini_get('session.save_handler')!=='files')return;
    $setting=session_save_path();$parts=explode(';',$setting);$path=end($parts);if($path==='')$path=sys_get_temp_dir();
    $real=realpath($path);$normalized=$real?:rtrim(str_replace('\\','/',$path),'/');
    if($normalized===''||(!str_starts_with($normalized,'/')&&!preg_match('/^[A-Z]:\//i',$normalized))||str_contains($normalized,'/../'))throw new RuntimeException('The native session storage path could not be verified.');
    foreach([hosting_public_root(),realpath($_SERVER['DOCUMENT_ROOT']??hosting_public_root())] as $root)if($root&&($normalized===$root||str_starts_with($normalized,rtrim($root,'/').'/')))throw new RuntimeException('Native PHP sessions must be outside the public web directory.');
}
