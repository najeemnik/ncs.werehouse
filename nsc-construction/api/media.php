<?php
require_once __DIR__.'/db.php';sendSecurityHeaders($_SERVER['REQUEST_METHOD']==='POST');$db=Database::getConnection();$user=authenticateUser();$tenant=getTenantId($user);
if($_SERVER['REQUEST_METHOD']==='GET'){
 $s=$db->prepare(isOffice7Owner($user)?'SELECT * FROM uploaded_files WHERE id=:id AND media_kind IS NOT NULL':'SELECT * FROM uploaded_files WHERE id=:id AND tenant_id=:tid AND media_kind IS NOT NULL');$s->execute(isOffice7Owner($user)?['id'=>$_GET['id']??'']:['id'=>$_GET['id']??'','tid'=>$tenant]);$row=$s->fetch();
 if(!$row||!in_array($row['mime_type'],['image/jpeg','image/png','image/webp'],true))jsonResponse(false,null,'Image was not found.',404);
 if(!isOffice7Owner($user)){$s=$db->prepare('SELECT document FROM tenant_workspaces WHERE tenant_id=:tid');$s->execute(['tid'=>$tenant]);$visible=visibleWorkspace($user,json_decode($s->fetchColumn()?:'{}',true));if(!in_array($row['project_id'],array_column($visible['projects']??[],'id'),true))jsonResponse(false,null,'Image was not found.',404);}
 $path=UPLOAD_DIR.'/'.$row['id'].'.bin';if(!is_file($path))jsonResponse(false,null,'Image was not found.',404);header('Content-Type: '.$row['mime_type']);header('Content-Length: '.$row['file_size']);readfile($path);exit;
}
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,null,'Method is not allowed.',405);
$scope=(string)($_GET['company']??'');if($scope!==''){requireOffice7Owner($user);$s=$db->prepare('SELECT id FROM users WHERE id=:id AND owner_admin_id IS NULL');$s->execute(['id'=>$scope]);if(!$s->fetch())jsonResponse(false,null,'Company was not found.',404);$tenant=$scope;}
$pid=(string)($_POST['projectId']??'');$kind=$_POST['kind']??'';$controls=json_decode($user['allowed_modules_json']??'{}',true)?:[];
if(!in_array($kind,['background','logo'],true))jsonResponse(false,null,'Invalid image purpose.',400);
if(($controls['projectAppearance']??true)===false||!hasProjectAccess($user,$pid,'projects','edit'))jsonResponse(false,null,'Project editing access is required.',403);
$s=$db->prepare('SELECT document FROM tenant_workspaces WHERE tenant_id=:tid');$s->execute(['tid'=>$tenant]);$doc=json_decode($s->fetchColumn()?:'{}',true);if(!in_array($pid,array_column($doc['projects']??[],'id'),true))jsonResponse(false,null,'Project was not found.',404);
$file=$_FILES['file']??null;$limit=$kind==='logo'?100000:700000;
if(!$file||$file['error']!==UPLOAD_ERR_OK||$file['size']<1||$file['size']>$limit||!is_uploaded_file($file['tmp_name']))jsonResponse(false,null,'Invalid image size.',400);
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$dimensions=@getimagesize($file['tmp_name']);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)||!$dimensions||$dimensions[0]*$dimensions[1]>4000000)jsonResponse(false,null,'Only bounded raster images are allowed.',400);
$id=generateUuid();if(!is_dir(UPLOAD_DIR)&&!mkdir(UPLOAD_DIR,0700,true))throw new RuntimeException('Upload directory is unavailable');if(!move_uploaded_file($file['tmp_name'],UPLOAD_DIR.'/'.$id.'.bin'))throw new RuntimeException('Upload failed');chmod(UPLOAD_DIR.'/'.$id.'.bin',0600);
try{$db->beginTransaction();$s=$db->prepare('INSERT INTO uploaded_files(id,tenant_id,project_id,original_name,mime_type,file_size,created_by,media_kind) VALUES(:id,:tid,:pid,:name,:mime,:size,:uid,:kind)');$s->execute(['id'=>$id,'tid'=>$tenant,'pid'=>$pid,'name'=>'project-'.$kind,'mime'=>$mime,'size'=>$file['size'],'uid'=>$user['id'],'kind'=>$kind]);logAudit($db,$user['id'],$user['name'],'create','project_image',$id,'Project '.$kind.' image uploaded');$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();unlink(UPLOAD_DIR.'/'.$id.'.bin');throw $e;}
jsonResponse(true,['id'=>$id,'url'=>'/api/media.php?id='.$id]);
