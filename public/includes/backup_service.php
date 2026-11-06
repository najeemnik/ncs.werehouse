<?php
/** Encrypted, versioned database backups. Uploaded SQL is never executed. */
require_once __DIR__.'/security.php';
require_once __DIR__.'/backup_files.php';
function backup_identifier($name){if(!is_string($name)||!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D',$name))throw new RuntimeException('Unsupported database identifier.');return '`'.$name.'`';}
function backup_schema(PDO $db) {
    $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$schema=[];
    if($sqlite)$tables=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    else{
        $rows=$db->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);$tables=[];
        foreach($rows as $r){if(strtolower($r['ENGINE'])!=='innodb')throw new RuntimeException(__t('Safe backup and restore require transactional database tables.','بکاپ و بازیابی امن به جدول‌های تراکنشی نیاز دارد.','خوندي شاتړ او بیا رغونه تراکنشي جدولونو ته اړتیا لري.'));$tables[]=$r['TABLE_NAME'];}
    }
    foreach($tables as $table){
        $quoted=backup_identifier($table);$cols=$sqlite?$db->query('PRAGMA table_info('.$quoted.')')->fetchAll(PDO::FETCH_ASSOC):$db->query('SHOW COLUMNS FROM '.$quoted)->fetchAll(PDO::FETCH_ASSOC);
        $schema[$table]=[];
        foreach($cols as $column){$name=$sqlite?$column['name']:$column['Field'];backup_identifier($name);$schema[$table][]=['name'=>$name,'type'=>$sqlite?$column['type']:$column['Type'],'nullable'=>$sqlite?!$column['notnull']:$column['Null']==='YES','pk'=>$sqlite?(int)$column['pk']:($column['Key']==='PRI'?1:0)];}
    }
    return $schema;
}
function backup_capture(PDO $db,bool $includeFiles=false) {
    $own=!$db->inTransaction();if($own)$db->beginTransaction();
    try{
        $schema=backup_schema($db);$tables=[];$count=0;
        foreach($schema as $table=>$cols){
            $rows=$db->query('SELECT * FROM '.backup_identifier($table))->fetchAll(PDO::FETCH_ASSOC);
            foreach($rows as &$row)foreach($row as &$value)if(is_string($value)&&!mb_check_encoding($value,'UTF-8'))$value=['__binary'=>base64_encode($value)];unset($row,$value);
            $tables[$table]=$rows;$count+=count($rows);
        }
        $snapshot=['format'=>'nikxor-database','version'=>1,'driver'=>$db->getAttribute(PDO::ATTR_DRIVER_NAME),'created_at'=>gmdate('c'),'schema'=>$schema,'tables'=>$tables,'row_count'=>$count];
        if($includeFiles)$snapshot['files']=backup_files_capture();if($own)$db->commit();return $snapshot;
    }catch(Throwable $e){if($own&&$db->inTransaction())$db->rollBack();throw $e;}
}
function backup_passphrase($passphrase){if(!is_string($passphrase)||mb_strlen($passphrase,'UTF-8')<12||strlen($passphrase)>256||trim($passphrase)===''||str_contains($passphrase,"\0"))throw new InvalidArgumentException(__t('Use a backup passphrase of at least 12 characters (maximum 256 bytes). Keep it safe; it cannot be recovered.','برای بکاپ رمز حداقل ۱۲ کاراکتری (حداکثر ۲۵۶ بایت) انتخاب کنید و نگه دارید؛ قابل بازیابی نیست.','د شاتړ لپاره لږ تر لږه ۱۲ توري (تر ۲۵۶ بایټه) رمز وټاکئ او خوندي یې وساتئ؛ بیا نه ترلاسه کېږي.'));}
function backup_encrypt(array $snapshot,$passphrase) {
    backup_passphrase($passphrase);
    if(!function_exists('openssl_encrypt')||!function_exists('gzencode'))throw new RuntimeException(__t('OpenSSL and zlib are required for encrypted backups.','برای بکاپ رمزشده OpenSSL و zlib لازم است.','د رمز شوي شاتړ لپاره OpenSSL او zlib اړین دي.'));
    $plain=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
    if(strlen($plain)>64*1024*1024)throw new RuntimeException(__t('This database exceeds the safe in-app backup limit. Use a server-managed backup.','حجم دیتابیس از حد امن بکاپ داخل برنامه بیشتر است؛ از بکاپ مدیریت‌شدهٔ سرور استفاده کنید.','ډیټابیس د خوندي شاتړ له اندازې لوی دی؛ د سرور شاتړ وکاروئ.'));
    $salt=random_bytes(32);$iv=random_bytes(12);$key=hash_pbkdf2('sha256',$passphrase,$salt,600000,32,true);$tag='';
    $cipher=openssl_encrypt(gzencode($plain,6),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'NIKXOR-BACKUP-1',16);unset($key,$plain);
    if($cipher===false)throw new RuntimeException('Backup encryption failed.');
    return 'NIKXOR1:'.json_encode(['salt'=>base64_encode($salt),'iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'data'=>base64_encode($cipher)],JSON_THROW_ON_ERROR);
}
function backup_decrypt($contents,$passphrase) {
    backup_passphrase($passphrase);
    $error=__t('Invalid backup, incorrect passphrase, or damaged file. Only encrypted Nik Smart Count - Warehousing backups are supported.','فایل نامعتبر، رمز نادرست یا بکاپ آسیب‌دیده است. فقط بکاپ رمزشدهٔ نیکزور پشتیبانی می‌شود.','فایل ناسم، رمز غلط یا شاتړ خراب دی؛ یوازې د نیکزور رمز شوی شاتړ منل کېږي.');
    if(!is_string($contents)||strlen($contents)>16*1024*1024||!str_starts_with($contents,'NIKXOR1:'))throw new InvalidArgumentException($error);
    try{$data=json_decode(substr($contents,8),true,8,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new InvalidArgumentException($error);}
    if(!is_array($data)||array_keys($data)!==['salt','iv','tag','data'])throw new InvalidArgumentException($error);
    foreach($data as $key=>$value){if(!is_string($value)||($data[$key]=base64_decode($value,true))===false)throw new InvalidArgumentException($error);}
    if(strlen($data['salt'])!==32||strlen($data['iv'])!==12||strlen($data['tag'])!==16)throw new InvalidArgumentException($error);
    $key=hash_pbkdf2('sha256',$passphrase,$data['salt'],600000,32,true);$zipped=openssl_decrypt($data['data'],'aes-256-gcm',$key,OPENSSL_RAW_DATA,$data['iv'],$data['tag'],'NIKXOR-BACKUP-1');unset($key);
    $plain=$zipped===false?false:@gzdecode($zipped,64*1024*1024);
    if($plain===false)throw new InvalidArgumentException($error);
    try{$snapshot=json_decode($plain,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new InvalidArgumentException($error);}
    if(!is_array($snapshot)||($snapshot['format']??'')!=='nikxor-database'||($snapshot['version']??0)!==1)throw new InvalidArgumentException($error);
    return $snapshot;
}
function backup_save_verified(array $snapshot,$passphrase,$kind='manual') {
    $contents=backup_encrypt($snapshot,$passphrase);$dir=security_private_dir();$id=bin2hex(random_bytes(16));$file=$dir.'/'.$id.'.nxb';
    if(file_put_contents($file,$contents,LOCK_EX)!==strlen($contents))throw new RuntimeException(__t('Backup could not be written.','بکاپ ذخیره نشد.','شاتړ خوندي نه شو.'));
    chmod($file,0600);$digest=hash('sha256',$contents);
    try{
        if(!hash_equals($digest,hash_file('sha256',$file)))throw new RuntimeException('Backup verification failed.');
        $verified=backup_decrypt(file_get_contents($file),$passphrase);
        if($verified!==$snapshot)throw new RuntimeException('Backup content verification failed.');
        $meta=['id'=>$id,'created_at'=>$snapshot['created_at'],'driver'=>$snapshot['driver'],'tables'=>count($snapshot['tables']),'rows'=>$snapshot['row_count'],'bytes'=>strlen($contents),'sha256'=>$digest,'kind'=>$kind,'created_by'=>(int)($_SESSION['user_id']??0)];
        if(file_put_contents($dir.'/'.$id.'.json',json_encode($meta,JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Backup metadata could not be written.');chmod($dir.'/'.$id.'.json',0600);return $meta;
    }catch(Throwable $e){if(is_file($file))unlink($file);throw $e;}
}
function backup_list() {
    $dir=security_private_dir();$out=[];
    foreach(glob($dir.'/*.json')?:[] as $path){
        if(!preg_match('/^[a-f0-9]{32}\.json$/D',basename($path)))continue;
        $meta=json_decode(file_get_contents($path),true);if(!is_array($meta)||!preg_match('/^[a-f0-9]{32}$/D',$meta['id']??''))continue;
        if(is_file($dir.'/'.$meta['id'].'.nxb'))$out[]=$meta;
    }
    usort($out,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));return $out;
}
function backup_download($id) {
    if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id)){http_response_code(404);exit;}
    $dir=security_private_dir();$file=$dir.'/'.$id.'.nxb';$meta=is_file($dir.'/'.$id.'.json')?json_decode(file_get_contents($dir.'/'.$id.'.json'),true):null;
    if(!$meta||!is_file($file)||!hash_equals($meta['sha256'],hash_file('sha256',$file))){http_response_code(404);exit;}
    while(ob_get_level())ob_end_clean();
    header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="nikxor-'.substr($id,0,12).'.nxb"');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Content-Length: '.filesize($file));readfile($file);exit;
}
function backup_validate(PDO $db,array $snapshot) {
    $schema=backup_schema($db);
    if(($snapshot['driver']??'')!==$db->getAttribute(PDO::ATTR_DRIVER_NAME)||($snapshot['schema']??[])!==$schema||!is_array($snapshot['tables']??null)||array_keys($snapshot['tables'])!==array_keys($schema))throw new InvalidArgumentException(__t('Backup schema or database type is incompatible. No data has been changed.','ساختار یا نوع دیتابیس این بکاپ سازگار نیست؛ هیچ اطلاعاتی تغییر نکرد.','د شاتړ جوړښت یا ډیټابیس ډول برابر نه دی؛ معلومات نه دي بدل شوي.'));
    $total=0;$hasAdmin=false;
    foreach($schema as $table=>$cols){
        $names=array_column($cols,'name');$rows=$snapshot['tables'][$table];if(!is_array($rows)||!array_is_list($rows))throw new InvalidArgumentException('Invalid backup rows.');
        foreach($rows as $row){
            if(!is_array($row)||array_keys($row)!==$names)throw new InvalidArgumentException('Invalid backup columns.');
            foreach($row as $column=>$value){
                if(!is_null($value)&&!is_scalar($value)&&!(is_array($value)&&array_keys($value)===['__binary']&&is_string($value['__binary'])&&base64_decode($value['__binary'],true)!==false))throw new InvalidArgumentException('Invalid backup value.');
                if(in_array($column,['currency_code','ledger_currency','cost_currency_code','inventory_currency_code','sale_currency_code','selling_currency_code'],true)&&$value!==null&&!in_array($value,['AFN','USD','AED','EUR'],true))throw new InvalidArgumentException('Unsupported currency in backup.');
            }
            if($table==='users'&&in_array($row['role'],['admin','superadmin','super_admin'],true)&&$row['status']==='active'&&password_get_info($row['password'])['algoName']!=='unknown'){
                $access=null;foreach($snapshot['tables']['user_access']??[] as $candidate)if(is_array($candidate)&&($candidate['user_id']??null)==$row['id']){$access=$candidate;break;}
                if(empty($access['is_locked'])&&(in_array($row['role'],['superadmin','super_admin'],true)||((int)($access['subscription_active']??1)===1&&(empty($access['subscription_expires_at'])||strcmp($access['subscription_expires_at'],date('Y-m-d H:i:s'))>0))))$hasAdmin=true;
            }
        }
        $total+=count($rows);
    }
    if(!$hasAdmin||$total!==($snapshot['row_count']??-1))throw new InvalidArgumentException(__t('The backup is incomplete or has no active administrator account.','بکاپ ناقص است یا حساب مدیر فعال ندارد.','شاتړ نیمګړی دی یا فعال مدیر نه لري.'));
}
function backup_foreign_keys_valid(PDO $db) {
    if($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return !$db->query('PRAGMA foreign_key_check')->fetch();
    $keys=$db->query('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION')->fetchAll(PDO::FETCH_ASSOC);$groups=[];
    foreach($keys as $key)$groups[$key['TABLE_NAME'].'.'.$key['CONSTRAINT_NAME']][]=$key;
    foreach($groups as $keys){$first=$keys[0];$join=[];$present=[];foreach($keys as $key){$join[]='c.'.backup_identifier($key['COLUMN_NAME']).'=p.'.backup_identifier($key['REFERENCED_COLUMN_NAME']);$present[]='c.'.backup_identifier($key['COLUMN_NAME']).' IS NOT NULL';}
        $sql='SELECT COUNT(*) FROM '.backup_identifier($first['TABLE_NAME']).' c LEFT JOIN '.backup_identifier($first['REFERENCED_TABLE_NAME']).' p ON '.implode(' AND ',$join).' WHERE '.implode(' AND ',$present).' AND p.'.backup_identifier($first['REFERENCED_COLUMN_NAME']).' IS NULL';if((int)$db->query($sql)->fetchColumn()>0)return false;
    }return true;
}
/** Only the known additive Office 2 tables may be absent in a Phase 2 backup. */
function backup_upgrade_office2(PDO $db,array $snapshot) {
    $current=backup_schema($db);$added=['user_access','user_feature_access','ai_usage_events'];
    $old=array_diff_key($current,array_flip($added));
    if(($snapshot['schema']??null)===$old&&isset($snapshot['tables'])&&is_array($snapshot['tables'])&&array_keys($snapshot['tables'])===array_keys($old)){
        $versions=array_column($snapshot['tables']['schema_migrations']??[],'version');
        if(!in_array('002_independent_currencies',$versions,true)||in_array('003_office2_management',$versions,true))return $snapshot;
        $tables=[];foreach($current as $name=>$columns)$tables[$name]=$snapshot['tables'][$name]??[];
        $snapshot['schema']=$current;$snapshot['tables']=$tables;
    }
    return $snapshot;
}
function backup_restore_database(PDO $db,array $snapshot,$passphrase,?callable $transformCurrent=null) {
    $snapshot=backup_upgrade_office2($db,$snapshot);
    backup_validate($db,$snapshot);$sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$fk=null;
    if(!$sqlite){$fk=$db->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn();$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');}
    $db->beginTransaction();
    try{
        if(!$sqlite)$db->exec('SET FOREIGN_KEY_CHECKS=0');
        if($sqlite){
            $db->exec('PRAGMA defer_foreign_keys=ON');
            // Acquire the SQLite write lock without changing a saved value.
            $db->exec("UPDATE settings SET setting_value=setting_value WHERE setting_key='office2.write_lock'");
        }else foreach(array_keys($snapshot['schema']) as $table)$db->query('SELECT 1 FROM '.backup_identifier($table).' FOR UPDATE')->fetchAll();
        $current=backup_capture($db,isset($snapshot['files']));
        // A business-only reset derives its retained accounts/settings from the
        // locked current snapshot, not a stale copy captured before the transaction.
        if($transformCurrent){$snapshot=$transformCurrent($current);backup_validate($db,$snapshot);}
        $filePlan=null;if(isset($snapshot['files'])){$filePlan=['id'=>bin2hex(random_bytes(16)),'originals'=>$current['files']];$pending=security_private_dir().'/restore-files.pending';$h=fopen($pending,'x');if(!$h)throw new RuntimeException('File recovery already pending.');chmod($pending,0600);$bytes=json_encode($filePlan,JSON_THROW_ON_ERROR);if(fwrite($h,$bytes)!==strlen($bytes)){fclose($h);throw new RuntimeException('Recovery marker could not be saved.');}fclose($h);}
        // A fully verified recovery copy exists BEFORE the first data-changing statement.
        $recovery=backup_save_verified($current,$passphrase,'before_restore');unset($current);
        foreach(array_reverse(array_keys($snapshot['schema'])) as $table)$db->exec('DELETE FROM '.backup_identifier($table));
        foreach($snapshot['schema'] as $table=>$cols){
            $names=array_column($cols,'name');$stmt=$db->prepare('INSERT INTO '.backup_identifier($table).' ('.implode(',',array_map('backup_identifier',$names)).') VALUES ('.implode(',',array_fill(0,count($names),'?')).')');
            foreach($snapshot['tables'][$table] as $row){
                foreach(array_values($row) as $i=>$value){
                    $type=PDO::PARAM_STR;
                    if(is_null($value))$type=PDO::PARAM_NULL;
                    elseif(is_int($value)||is_bool($value))$type=PDO::PARAM_INT;
                    elseif(is_float($value))$value=json_encode($value,JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
                    elseif(is_array($value)){$value=base64_decode($value['__binary'],true);$type=PDO::PARAM_LOB;}
                    $stmt->bindValue($i+1,$value,$type);
                }$stmt->execute();
            }
        }
        if(!backup_foreign_keys_valid($db))throw new RuntimeException('Restore failed relationship validation.');
        $actual=backup_capture($db);
        foreach($snapshot['tables'] as $table=>$rows){
            $expectedRows=array_map(fn($r)=>json_encode($r,JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR),$rows);
            $actualRows=array_map(fn($r)=>json_encode($r,JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR),$actual['tables'][$table]);
            sort($expectedRows,SORT_STRING);sort($actualRows,SORT_STRING);
            if($expectedRows!==$actualRows)throw new RuntimeException('Restore content verification failed.');
        }
        unset($actual);

        if($filePlan){backup_files_install($snapshot['files']);if(!update_setting('backup.files_operation',$filePlan['id']))throw new RuntimeException('File restore marker failed.');}
        if(!update_setting('security.session_epoch',bin2hex(random_bytes(24))))throw new RuntimeException('Session invalidation failed.');
        $db->commit();if($filePlan&&is_file($pending))unlink($pending);unset($GLOBALS['_currency_rows'],$GLOBALS['_ui_preferences'],$GLOBALS['_auth_user']);return $recovery;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if(!empty($filePlan)){backup_files_install($filePlan['originals']);if(is_file($pending))unlink($pending);}throw new RuntimeException(__t('Restore was cancelled safely. The original database was retained. Check backup compatibility and available storage.','بازیابی به‌صورت امن لغو شد و دیتابیس اصلی حفظ گردید. سازگاری بکاپ و فضای ذخیره را بررسی کنید.','بیا رغونه په خوندي ډول لغوه شوه؛ اصلي ډیټابیس وساتل شو. د شاتړ جوړښت او ځای وګورئ.'));}
    finally{if(!$sqlite)$db->exec('SET FOREIGN_KEY_CHECKS='.(int)$fk);}
}
