<?php
/** Private settings for hosting without process-environment configuration. */
require_once __DIR__.'/hosting_storage.php';
function runtime_path_is_public(string $path): bool {
    $roots=[realpath(__DIR__.'/..')];$served=$_SERVER['DOCUMENT_ROOT']??'';if(is_string($served)&&$served!=='')$roots[]=realpath($served);
    foreach(array_filter($roots) as $root)if($path===$root||str_starts_with($path.DIRECTORY_SEPARATOR,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR))return true;
    return false;
}
function runtime_directory(): string {
    $root=realpath(__DIR__.'/..');if(!getenv('NIKXOR_RUNTIME_DIR')&&hosting_profile())return hosting_subdirectory('runtime');$dir=getenv('NIKXOR_RUNTIME_DIR')?:dirname($root).'/var';
    if(!is_dir($dir)&&!@mkdir($dir,0700,true))throw new RuntimeException('Private configuration storage is unavailable.');
    $real=realpath($dir);if(!$real||(runtime_path_is_public($real)&&!hosting_internal_path($real))||!is_writable($real))throw new RuntimeException('Private configuration must be outside the web root.');return $real;
}
function runtime_keys(): array {return ['NIKXOR_PUBLIC_URL','NIKXOR_AI_ENABLED','NIKXOR_GEMINI_API_KEY','NIKXOR_GEMINI_MODEL','NIKXOR_BACKUP_PASSPHRASE','NIKXOR_GOOGLE_CLIENT_ID','NIKXOR_GOOGLE_CLIENT_SECRET','NIKXOR_GOOGLE_REFRESH_TOKEN','NIKXOR_GOOGLE_DRIVE_FOLDER','NIKXOR_BACKUP_NOTIFY_EMAIL','NIKXOR_MAIL_FROM','NIKXOR_SUPPORT_EMAIL','NIKXOR_BACKUP_KEEP'];}
function runtime_master_key(): string {
    $dir=runtime_directory();$lockPath=$dir.'/settings-key.lock';if(is_link($lockPath))throw new RuntimeException('Invalid key lock path.');
    $lock=@fopen($lockPath,'c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Private key could not be locked.');
    try{
        $php=$dir.'/settings.key.php';$legacy=$dir.'/settings.key';
        if(is_link($php)||is_link($legacy))throw new RuntimeException('Invalid private key path.');
        if(is_file($php))$key=hosting_read_secret($php);
        elseif(is_file($legacy))$key=file_get_contents($legacy);
        elseif(hosting_profile()){hosting_write_secret($php,random_bytes(32));$key=hosting_read_secret($php);}
        else{$h=@fopen($legacy,'x');if(!$h)throw new RuntimeException('Private key could not be created.');chmod($legacy,0600);try{if(fwrite($h,random_bytes(32))!==32)throw new RuntimeException('Private key write failed.');}finally{fclose($h);}$key=file_get_contents($legacy);}
        if(strlen($key)!==32)throw new RuntimeException('Invalid private encryption key.');return $key;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}

function runtime_values(): array {
    if(isset($GLOBALS['_runtime_settings']))return $GLOBALS['_runtime_settings'];$path=runtime_directory().'/services.json';if(!is_file($path))return $GLOBALS['_runtime_settings']=[];
    if(is_link($path))throw new RuntimeException('Invalid configuration path.');$envelope=json_decode(file_get_contents($path),true,8,JSON_THROW_ON_ERROR);
    $nonce=base64_decode($envelope['nonce']??'',true);$cipher=base64_decode($envelope['cipher']??'',true);if($nonce===false||strlen($nonce)!==SODIUM_CRYPTO_SECRETBOX_NONCEBYTES||$cipher===false)throw new RuntimeException('Private settings are damaged.');
    $plain=sodium_crypto_secretbox_open($cipher,$nonce,runtime_master_key());if($plain===false)throw new RuntimeException('Private settings could not be verified.');$values=json_decode($plain,true,8,JSON_THROW_ON_ERROR);if(!is_array($values))throw new RuntimeException('Invalid private settings.');return $GLOBALS['_runtime_settings']=$values;
}
function runtime_value(string $name,string $default=''): string {
    $env=getenv($name);if($env!==false)return (string)$env;if(!in_array($name,runtime_keys(),true))return $default;return (string)(runtime_values()[$name]??$default);
}
function runtime_save(array $input): void {
    $lock=fopen(runtime_directory().'/services.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Could not lock private settings.');
    try{unset($GLOBALS['_runtime_settings']);$values=runtime_values();foreach($input as $key=>$value){if(!in_array($key,runtime_keys(),true)||!is_string($value)||strlen($value)>8192||str_contains($value,"\0"))throw new InvalidArgumentException('Invalid integration setting.');if($value!=='')$values[$key]=$value;}
        foreach(['NIKXOR_SUPPORT_EMAIL','NIKXOR_BACKUP_NOTIFY_EMAIL','NIKXOR_MAIL_FROM'] as $key)if(!empty($values[$key])&&!filter_var($values[$key],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Invalid email address.');
        if(isset($values['NIKXOR_AI_ENABLED'])&&!in_array($values['NIKXOR_AI_ENABLED'],['0','1'],true))throw new InvalidArgumentException('Invalid AI state.');
        if(!empty($values['NIKXOR_BACKUP_PASSPHRASE'])&&strlen($values['NIKXOR_BACKUP_PASSPHRASE'])<12)throw new InvalidArgumentException('Backup passphrase requires at least 12 characters.');
        if(isset($values['NIKXOR_BACKUP_KEEP'])&&(!ctype_digit($values['NIKXOR_BACKUP_KEEP'])||(int)$values['NIKXOR_BACKUP_KEEP']<2||(int)$values['NIKXOR_BACKUP_KEEP']>365))throw new InvalidArgumentException('Keep 2–365 backup copies.');
        $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);$cipher=sodium_crypto_secretbox(json_encode($values,JSON_THROW_ON_ERROR),$nonce,runtime_master_key());$tmp=tempnam(runtime_directory(),'settings-');chmod($tmp,0600);if(file_put_contents($tmp,json_encode(['nonce'=>base64_encode($nonce),'cipher'=>base64_encode($cipher)],JSON_THROW_ON_ERROR),LOCK_EX)===false||!rename($tmp,runtime_directory().'/services.json'))throw new RuntimeException('Private settings were not saved.');$GLOBALS['_runtime_settings']=$values;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
