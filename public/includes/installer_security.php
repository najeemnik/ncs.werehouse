<?php
/** Out-of-band key authorization, kept separate from administrator credentials. */
require_once __DIR__.'/runtime_settings.php';
function installer_expected_key_hash(): ?string {
    $profile=hosting_profile();if($profile)return $profile['setup_token_hash'];
    $path=runtime_directory().'/install-token.key';
    if(is_link($path)||!is_file($path)||filesize($path)>256)return null;
    $key=trim((string)file_get_contents($path));
    return preg_match('/^[a-f0-9]{64}$/D',$key)?hash('sha256',$key):null;
}
function installer_key_matches($input): bool {
    if(!is_string($input)||strlen($input)>128)return false;
    $key=trim($input);$expected=installer_expected_key_hash();
    return (bool)preg_match('/^[a-f0-9]{64}$/D',$key)&&is_string($expected)&&hash_equals($expected,hash('sha256',$key));
}
function installer_authorization_binding(): ?string {
    $hash=installer_expected_key_hash();return $hash===null?null:hash('sha256',$hash.'|'.runtime_directory());
}
function installer_session_authorized(): bool {
    $binding=installer_authorization_binding();$at=$_SESSION['install_key_verified_at']??null;
    return is_int($at)&&$at<=time()&&time()-$at<900&&is_string($binding)&&is_string($_SESSION['install_key_binding']??null)&&hash_equals($binding,$_SESSION['install_key_binding']);
}
/** Read-only preflight. It never loads application migrations or changes accounts. */
function installer_connection_has_accounts(PDO $db): bool {
    $driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $exists=$driver==='sqlite'?$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn():$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='users'")->fetchColumn();
    return (bool)$exists&&(bool)$db->query('SELECT 1 FROM users LIMIT 1')->fetchColumn();
}
function installer_database_has_accounts(string $directory): bool {
    $path=$directory.'/database.php';if(is_link($path))throw new RuntimeException('Invalid database configuration.');
    $config=is_file($path)?require $path:[];if(!is_array($config))throw new RuntimeException('Invalid database configuration.');
    $options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
    $driver=getenv('NIKXOR_DB_DRIVER')?:($config['DRIVER']??'mysql');
    if($driver==='sqlite'){
        $file=getenv('NIKXOR_SQLITE_PATH')?:($config['SQLITE_PATH']??'');
        // A preflight must not create a new empty SQLite file.
        if(!is_string($file)||!is_file($file)||is_link($file))return false;
        $db=new PDO('sqlite:'.$file,null,null,$options);
    }elseif($driver==='mysql'){
        $db=new PDO('mysql:host='.(getenv('DB_HOST')?:($config['HOST']??'localhost')).';port='.(getenv('DB_PORT')?:($config['PORT']??3306)).';dbname='.(getenv('DB_NAME')?:($config['NAME']??'')).';charset=utf8mb4',getenv('DB_USER')?:($config['USER']??''),getenv('DB_PASS')!==false?getenv('DB_PASS'):($config['PASS']??''),$options);
    }else throw new RuntimeException('Unsupported database driver.');
    return installer_connection_has_accounts($db);
}
