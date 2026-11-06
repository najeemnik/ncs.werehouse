<?php
/** Run via a private scheduler; credentials are environment-only and never logged. */
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../public/includes/functions.php';require __DIR__.'/../public/includes/backup_service.php';
foreach(['NIKXOR_BACKUP_PASSPHRASE','NIKXOR_GOOGLE_CLIENT_ID','NIKXOR_GOOGLE_CLIENT_SECRET','NIKXOR_GOOGLE_REFRESH_TOKEN','NIKXOR_GOOGLE_DRIVE_FOLDER'] as $name)if(!runtime_value($name))throw new RuntimeException('Missing secure setting: '.$name);
function google_request($url,$body,array $headers): array {
    $allowed=['https://oauth2.googleapis.com/token','https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart'];if(!in_array($url,$allowed,true))throw new RuntimeException('Unexpected destination.');
    $c=curl_init($url);curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>120]);$response=curl_exec($c);$status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);if($response===false||$status<200||$status>=300)throw new RuntimeException('Google request failed; HTTP '.$status.'. No credential values logged.');return json_decode($response,true,32,JSON_THROW_ON_ERROR);
}
$token=google_request('https://oauth2.googleapis.com/token',http_build_query(['client_id'=>runtime_value('NIKXOR_GOOGLE_CLIENT_ID'),'client_secret'=>runtime_value('NIKXOR_GOOGLE_CLIENT_SECRET'),'refresh_token'=>runtime_value('NIKXOR_GOOGLE_REFRESH_TOKEN'),'grant_type'=>'refresh_token']),['Content-Type: application/x-www-form-urlencoded']);
if(empty($token['access_token']))throw new RuntimeException('No Google access token returned.');
$snapshot=backup_capture($pdo,true);$meta=backup_save_verified($snapshot,runtime_value('NIKXOR_BACKUP_PASSPHRASE'),'scheduled');$dir=security_private_dir();$file=$dir.'/'.$meta['id'].'.nxb';
$boundary='nsc-'.bin2hex(random_bytes(20));$metadata=json_encode(['name'=>'warehouse-'.gmdate('Ymd-His').'.nxb','parents'=>[runtime_value('NIKXOR_GOOGLE_DRIVE_FOLDER')]],JSON_THROW_ON_ERROR);
$body='--'.$boundary."\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n".$metadata."\r\n--".$boundary."\r\nContent-Type: application/octet-stream\r\n\r\n".file_get_contents($file)."\r\n--".$boundary."--\r\n";
$result=google_request('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart',$body,['Authorization: Bearer '.$token['access_token'],'Content-Type: multipart/related; boundary='.$boundary]);
if(empty($result['id']))throw new RuntimeException('Backup upload not confirmed.');
backup_prune((int)runtime_value('NIKXOR_BACKUP_KEEP','14'));
echo "Encrypted database and uploaded-file backup uploaded successfully.\n";
$to=runtime_value('NIKXOR_BACKUP_NOTIFY_EMAIL');$from=runtime_value('NIKXOR_MAIL_FROM');if($to&&$from){if(!filter_var($to,FILTER_VALIDATE_EMAIL)||!filter_var($from,FILTER_VALIDATE_EMAIL)||strpbrk($to.$from,"\r\n"))throw new RuntimeException('Invalid mail configuration.');if(!mail($to,'Warehouse backup completed','Encrypted database backup completed at '.gmdate('c').'. Includes supported uploaded files; keep a separate copy of private setup and recovery keys.','From: '.$from))throw new RuntimeException('Backup uploaded, but email notification failed.');}
