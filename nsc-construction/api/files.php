<?php
require_once __DIR__.'/db.php';sendSecurityHeaders($_SERVER['REQUEST_METHOD']==='POST');$db=Database::getConnection();$user=authenticateUser();$tenant=getTenantId($user);
if($_SERVER['REQUEST_METHOD']==='GET'){
 $s=$db->prepare(isOffice7Owner($user)?'SELECT * FROM uploaded_files WHERE id=:id':'SELECT * FROM uploaded_files WHERE id=:id AND tenant_id=:tid');$s->execute(isOffice7Owner($user)?['id'=>$_GET['id']??'']:['id'=>$_GET['id']??'','tid'=>$tenant]);$row=$s->fetch();
 if(!$row||!hasProjectAccess($user,$row['project_id'],'documents','view'))jsonResponse(false,null,'Document was not found.',404);
 $path=UPLOAD_DIR.'/'.$row['id'].'.bin';if(!is_file($path))jsonResponse(false,null,'Document was not found.',404);
 header('Content-Type: application/octet-stream');header('Content-Length: '.$row['file_size']);header("Content-Disposition: attachment; filename=\"document\"; filename*=UTF-8''".rawurlencode($row['original_name']));readfile($path);exit;
}
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,null,'Method is not allowed.',405);
$scope=(string)($_GET['company']??'');if($scope!==''){requireOffice7Owner($user);$s=$db->prepare('SELECT id FROM users WHERE id=:id AND owner_admin_id IS NULL');$s->execute(['id'=>$scope]);if(!$s->fetch())jsonResponse(false,null,'Company was not found.',404);$tenant=$scope;}
$pid=(string)($_POST['projectId']??'');if(!hasProjectAccess($user,$pid,'documents','create'))jsonResponse(false,null,'Document access is not granted.',403);
$s=$db->prepare('SELECT document FROM tenant_workspaces WHERE tenant_id=:tid');$s->execute(['tid'=>$tenant]);$doc=json_decode($s->fetchColumn()?:'{}',true);$projectFound=false;foreach($doc['projects']??[] as $p)if($p['id']===$pid)$projectFound=true;
if(!$projectFound)jsonResponse(false,null,'Project was not found.',404);
$file=$_FILES['file']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK||$file['size']<1||$file['size']>MAX_FILE_SIZE_BYTES||!is_uploaded_file($file['tmp_name']))jsonResponse(false,null,'Invalid file or file exceeds 10 MB.',400);
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);if(!in_array($mime,['image/jpeg','image/png','image/webp','application/pdf'],true))jsonResponse(false,null,'Only JPEG, PNG, WebP and PDF files are allowed.',400);
$name=basename((string)$file['name']);if(!preg_match('//u',$name)||str_contains($name,"\0"))jsonResponse(false,null,'Invalid file name.',400);preg_match('/^.{0,200}/us',$name,$safeName);$name=$safeName[0]?:'document';$id=generateUuid();
if(!is_dir(UPLOAD_DIR)&&!mkdir(UPLOAD_DIR,0700,true))throw new RuntimeException('Upload directory is unavailable');
if(!move_uploaded_file($file['tmp_name'],UPLOAD_DIR.'/'.$id.'.bin'))throw new RuntimeException('Upload failed');chmod(UPLOAD_DIR.'/'.$id.'.bin',0600);
try{$db->beginTransaction();$s=$db->prepare('INSERT INTO uploaded_files(id,tenant_id,project_id,original_name,mime_type,file_size,created_by) VALUES(:id,:tid,:pid,:name,:mime,:size,:uid)');$s->execute(['id'=>$id,'tid'=>$tenant,'pid'=>$pid,'name'=>$name,'mime'=>$mime,'size'=>$file['size'],'uid'=>$user['id']]);logAudit($db,$user['id'],$user['name'],'create','documents',$id,'Document uploaded');$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();unlink(UPLOAD_DIR.'/'.$id.'.bin');throw $e;}
jsonResponse(true,['id'=>$id,'url'=>'/api/files.php?id='.rawurlencode($id)]);
