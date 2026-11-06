<?php
/** Upload manifests contain files only; credentials, sessions and executable rules are excluded. */
function backup_files_root(): string {$root=defined('NIKXOR_UPLOAD_ROOT')?NIKXOR_UPLOAD_ROOT:BASE_DIR.'/uploads';if(!is_dir($root)&&!mkdir($root,0755,true))throw new RuntimeException('Upload directory unavailable.');return realpath($root);}
function backup_files_path(string $name): string {
 if(!preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_.-]+)*\.(?:png|jpg|jpeg|webp|gif|ico|pdf)$~iD',$name)||str_contains($name,'..')||preg_match('~(?:^|/)\.~',$name))throw new InvalidArgumentException('Unsafe backup file path.');$root=backup_files_root();$parts=explode('/',$name);$walk=$root;foreach($parts as $part){$walk.='/'.$part;if(is_link($walk))throw new InvalidArgumentException('Backup file paths cannot contain symbolic links.');}return $root.'/'.$name;
}
function backup_files_validate(array $files): array {
 if(count($files)>2000)throw new InvalidArgumentException('Too many backup files.');$bytes=0;$out=[];
 foreach($files as $name=>$file){if(!is_string($name)||!is_array($file))throw new InvalidArgumentException('Invalid backup file manifest.');backup_files_path($name);$raw=base64_decode($file['data']??'',true);if($raw===false||!is_string($file['sha256']??null)||!hash_equals($file['sha256'],hash('sha256',$raw)))throw new InvalidArgumentException('Backup upload checksum mismatch.');$bytes+=strlen($raw);if($bytes>8*1024*1024||strlen($raw)>8*1024*1024)throw new InvalidArgumentException('In-app uploaded-file backup is limited to 8 MB. Use host-managed backups for larger uploads.');$mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($raw);$allowed=['png'=>['image/png'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'webp'=>['image/webp'],'gif'=>['image/gif'],'ico'=>['image/x-icon','image/vnd.microsoft.icon'],'pdf'=>['application/pdf']];$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!in_array($mime,$allowed[$ext]??[],true))throw new InvalidArgumentException('Backup file content does not match its extension.');$out[$name]=['sha256'=>$file['sha256'],'data'=>$file['data']];}ksort($out);return $out;
}
function backup_files_capture(): array {
 $root=backup_files_root();$out=[];$size=0;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));foreach($it as $f){if($f->isLink())throw new RuntimeException('Symbolic links are not supported in upload backups.');if(!$f->isFile())continue;$relative=substr($f->getPathname(),strlen($root)+1);if(basename($relative)==='.htaccess'||str_starts_with(basename($relative),'.restore-'))continue;backup_files_path($relative);$size+=$f->getSize();if($size>8*1024*1024)throw new RuntimeException('Uploaded files exceed the in-app 8 MB backup limit.');$raw=file_get_contents($f->getPathname());$out[$relative]=['sha256'=>hash('sha256',$raw),'data'=>base64_encode($raw)];}return backup_files_validate($out);
}
function backup_files_install(array $files): void {
 $files=backup_files_validate($files);$existing=backup_files_capture();foreach($files as $name=>$file){$target=backup_files_path($name);if(!is_dir(dirname($target))&&!mkdir(dirname($target),0755,true))throw new RuntimeException('Cannot create upload directory.');$tmp=tempnam(dirname($target),'.restore-');try{chmod($tmp,0644);$raw=base64_decode($file['data'],true);if(file_put_contents($tmp,$raw,LOCK_EX)!==strlen($raw)||!hash_equals($file['sha256'],hash_file('sha256',$tmp))||!rename($tmp,$target))throw new RuntimeException('Uploaded-file restore failed.');}finally{if(is_file($tmp))unlink($tmp);}}
 foreach(array_diff_key($existing,$files) as $name=>$unused)if(!unlink(backup_files_path($name)))throw new RuntimeException('Could not remove superseded upload.');
}
function backup_files_recover(PDO $db): void {
 $path=security_private_dir().'/restore-files.pending';if(!is_file($path))return;$plan=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);if(!is_array($plan)||!preg_match('/^[a-f0-9]{32}$/D',$plan['id']??''))throw new RuntimeException('Restore recovery requires administrator attention.');$q=$db->prepare("SELECT setting_value FROM settings WHERE setting_key='backup.files_operation'");$q->execute();$committed=$q->fetchColumn();if($committed!==$plan['id'])backup_files_install($plan['originals']);if(!unlink($path))throw new RuntimeException('Could not clear restore recovery marker.');
}
function security_maintenance_guard(): void {
 global $pdo;if(isset($GLOBALS['_maintenance_lease']))return;$dir=runtime_directory();
 $handle=fopen($dir.'/maintenance.lock','c');if(is_file($dir.'/maintenance.flag')){if(!flock($handle,LOCK_EX|LOCK_NB))access_deny(503,'maintenance',__t('Recovery is in progress. Try again shortly.','بازیابی در جریان است؛ کمی بعد کوشش کنید.','بیا رغونه روانه ده.'));backup_files_recover($pdo);unlink($dir.'/maintenance.flag');flock($handle,LOCK_UN);}
if(!flock($handle,LOCK_SH|LOCK_NB))access_deny(503,'maintenance','Maintenance in progress.');
 if(is_file(security_private_dir().'/restore-files.pending')){flock($handle,LOCK_UN);if(!flock($handle,LOCK_EX|LOCK_NB))access_deny(503,'maintenance','Restore recovery pending.');backup_files_recover($pdo);flock($handle,LOCK_SH);}
 $GLOBALS['_maintenance_lease']=$handle;
}
function backup_restore(PDO $db,array $snapshot,$passphrase,?callable $transformCurrent=null){
 if(isset($snapshot['files']))backup_files_validate($snapshot['files']);$dir=runtime_directory();$flag=$dir.'/maintenance.flag';$handle=$GLOBALS['_maintenance_lease']??fopen($dir.'/maintenance.lock','c');
 $marker=@fopen($flag,'x');if(!$marker)throw new RuntimeException('Another recovery is in progress.');fwrite($marker,gmdate('c'));fclose($marker);flock($handle,LOCK_UN);
 try{$deadline=microtime(true)+10;while(!flock($handle,LOCK_EX|LOCK_NB)){if(microtime(true)>$deadline)throw new RuntimeException('Active requests did not finish; retry recovery later.');usleep(50000);}backup_files_recover($db);return backup_restore_database($db,$snapshot,$passphrase,$transformCurrent);}finally{if(is_file($flag))unlink($flag);flock($handle,LOCK_UN);unset($GLOBALS['_maintenance_lease']);fclose($handle);}
}
function backup_prune(int $keep): void {if($keep<2||$keep>365)throw new InvalidArgumentException('Invalid retention count.');$list=array_values(array_filter(backup_list(),fn($m)=>($m['kind']??'')==='scheduled'));foreach(array_slice($list,$keep) as $m){$base=security_private_dir().'/'.$m['id'];foreach(['.nxb','.json'] as $ext)if(is_file($base.$ext)&&!unlink($base.$ext))throw new RuntimeException('Could not apply local retention.');}}
