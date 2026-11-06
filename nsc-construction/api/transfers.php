<?php
function authorizeTransfer(array $user,array $r,string $action): void {
 $action=in_array($action,['approved','posted'],true)?'approve':$action;
 foreach(array_unique(array_filter([$r['projectId']??'',($r['kind']??'')==='ownership_transfer'?null:($r['toProjectId']??'')])) as $pid){
  if(!hasProjectAccess($user,$pid,'transfers','view')||($action==='submit'?!(hasProjectAccess($user,$pid,'transfers','create')||hasProjectAccess($user,$pid,'transfers','edit')):!hasProjectAccess($user,$pid,'transfers',$action)))reversalError('Transfer requires permission in both projects.',403,'permissionDenied');
 }
 if(($r['kind']??'')==='ownership_transfer'&&!hasProjectAccess($user,$r['projectId'],'projects','edit'))reversalError('Project edit permission is required for ownership transfer.',403,'permissionDenied');
}
function validateTransfer(array $doc,array $r): void {
 if(!in_array($r['kind']??'',['cash_transfer','material_transfer','ownership_transfer'],true))reversalError('Invalid transfer kind.',400,'validation');
 if(($r['kind']??'')==='ownership_transfer'){
  foreach(['previousOwner','newOwner'] as $k)if(!is_string($r[$k]??null)||trim($r[$k])===''||strlen($r[$k])>500)reversalError('Owner names are required.',400,'validation');
  if($r['previousOwner']===$r['newOwner']||(float)($r['amount']??0)!==0.0)reversalError('Ownership transfer changes the business owner without a cash amount.',400,'validation');return;
 }
 $found=false;foreach($doc['projects']??[] as $project)if(($project['id']??'')===($r['toProjectId']??''))$found=true;
 if(!$found||($r['projectId']??'')===$r['toProjectId'])reversalError('Select another project in this company.',400,'validation');
 if(($r['amount']??0)<=0||round((float)$r['amount'],2)!=(float)$r['amount'])reversalError('Positive cent-precision transfer value is required.',400,'validation');
 foreach(['method','toMethod'] as $k)if(!in_array($r[$k]??'',['Cash','Bank','Hawala','Personal'],true))reversalError('Invalid transfer payment method.',400,'validation');
 if($r['kind']==='material_transfer'){
  $codes=json_decode(file_get_contents(__DIR__.'/material-transfer-accounts.json'),true);
  if(!in_array($r['accountCode']??'',$codes,true)||!is_string($r['materialName']??null)||trim($r['materialName'])===''||strlen($r['materialName'])>500||!is_numeric($r['quantity']??null)||!is_finite((float)$r['quantity'])||$r['quantity']<=0||$r['quantity']>1e12||!is_string($r['unit']??null)||trim($r['unit'])===''||strlen($r['unit'])>80)reversalError('Material account, description, quantity and unit are required.',400,'validation');
 }
}
function activeSale(array $doc,string $pid): bool {foreach($doc['apartments']??[] as $r)if(($r['projectId']??'')===$pid&&($r['status']??'')==='sold'&&($r['approvalStatus']??'posted')==='posted'&&!isset($r['reversal']))return true;return false;}
function materialValue(array $doc,string $pid,string $code,string $date): float {
 $sum=0;foreach(['expenses','steel','concrete'] as $c)foreach($doc[$c]??[] as $r){if(($r['projectId']??'')!==$pid||($r['date']??'')>$date||($r['approvalStatus']??'posted')!=='posted')continue;$class=$c==='steel'?'5100':($c==='concrete'?'5200':($r['category']??''));if($class!==$code)continue;$v=(float)($r['amount']??$r['totalAmount']??0);if(($r['currency']??'USD')==='AFN')$v/=(float)($r['exchangeRate']??70);$sum+=$v;foreach($r['creditAdjustments']??[] as $credit)if($credit['date']<=$date){$cv=(float)$credit['amount'];if(($r['currency']??'USD')==='AFN')$cv/=(float)($r['exchangeRate']??70);$sum-=$cv;}if(isset($r['reversal'])&&$r['reversal']['date']<=$date)$sum-=$v;}
 foreach($doc['projectTransfers']??[] as $r){if(($r['kind']??'')!=='material_transfer'||($r['accountCode']??'')!==$code||($r['date']??'')>$date||($r['approvalStatus']??'draft')!=='posted')continue;$sign=($r['toProjectId']??'')===$pid?1:(($r['projectId']??'')===$pid?-1:0);$v=(float)$r['amount'];if(($r['currency']??'USD')==='AFN')$v/=(float)($r['exchangeRate']??70);$sum+=$sign*$v;if(isset($r['reversal'])&&$r['reversal']['date']<=$date)$sum-=$sign*$v;}
 return round($sum,2);
}
function postTransfer(array $user,array &$doc,array $r): void {
 validateTransfer($doc,$r);
 if($r['date']>(new DateTimeImmutable('now',new DateTimeZone('Asia/Kabul')))->format('Y-m-d'))reversalError('Transfers cannot be posted in the future.',400,'validation');
 if($r['kind']==='material_transfer'){
  $v=$r['currency']==='AFN'?$r['amount']/$r['exchangeRate']:$r['amount'];
  if(materialValue($doc,$r['projectId'],$r['accountCode'],(new DateTimeImmutable('now',new DateTimeZone('Asia/Kabul')))->format('Y-m-d'))+0.001<round($v,2))reversalError('Current material costs cannot cover a backdated transfer.',409,'transferStockLimit');
  foreach($doc['projectTransfers']??[] as $future)if(($future['date']??'')>=$r['date']&&materialValue($doc,$r['projectId'],$r['accountCode'],$future['date'])+0.001<round($v,2))reversalError('Transfer would overdraw a later material cost balance.',409,'transferStockLimit');
  if(activeSale($doc,$r['projectId'])||materialValue($doc,$r['projectId'],$r['accountCode'],$r['date'])+0.001<round($v,2))reversalError('Material value exceeds available project costs, or the source has active sales.',409,'transferStockLimit');
 }
 if($r['kind']==='ownership_transfer')foreach($doc['projects'] as &$project)if($project['id']===$r['projectId']){
  $last=empty($project['transferHistory'])?false:end($project['transferHistory']);if($last&&$last['date']>$r['date'])reversalError('Ownership history dates must be ordered.',409,'ownershipConflict');
  $owner=$project['currentOwner']??$project['clientOwner']??'';if($owner!==''&&$owner!==$r['previousOwner'])reversalError('Previous owner does not match this project.',409,'ownershipConflict');
  $project['initialOwner']=$project['initialOwner']??($owner?:$r['previousOwner']);$project['currentOwner']=$r['newOwner'];$project['clientOwner']=$r['newOwner'];$project['transferHistory'][]=['id'=>$r['id'],'date'=>$r['date'],'previousOwner'=>$r['previousOwner'],'newOwner'=>$r['newOwner']];
 }unset($project);
}
function reverseTransfer(array $user,array &$doc,array $r,string $date): void {
 if($r['kind']==='material_transfer'){
  $v=$r['currency']==='AFN'?$r['amount']/$r['exchangeRate']:$r['amount'];
  if(materialValue($doc,$r['toProjectId'],$r['accountCode'],(new DateTimeImmutable('now',new DateTimeZone('Asia/Kabul')))->format('Y-m-d'))+0.001<round($v,2))reversalError('Current destination costs cannot cover a backdated reversal.',409,'transferStockLimit');
  foreach($doc['projectTransfers']??[] as $future)if(($future['date']??'')>=$date&&materialValue($doc,$r['toProjectId'],$r['accountCode'],$future['date'])+0.001<round($v,2))reversalError('Reversal would overdraw a later material cost balance.',409,'transferStockLimit');
  if(activeSale($doc,$r['toProjectId'])||materialValue($doc,$r['toProjectId'],$r['accountCode'],$date)+0.001<round($v,2))reversalError('Destination costs have been consumed or allocated to sales.',409,'transferStockLimit');
 }
 if($r['kind']==='ownership_transfer')foreach($doc['projects'] as &$project)if($project['id']===$r['projectId']){
  $last=end($project['transferHistory']);if(!$last||$last['id']!==$r['id']||($project['currentOwner']??'')!==$r['newOwner'])reversalError('Reverse the latest ownership transfer first.',409,'ownershipConflict');
  $project['currentOwner']=$r['previousOwner'];$project['clientOwner']=$r['previousOwner'];array_pop($project['transferHistory']);
 }unset($project);
}
function protectProjectOwnership(array $old,array $new): void {
 $indexed=[];foreach($old['projects']??[] as $r)$indexed[$r['id']]=$r;
 foreach($new['projects']??[] as $r)if(isset($indexed[$r['id']]))foreach(['initialOwner','currentOwner','clientOwner','transferHistory'] as $k)if(($indexed[$r['id']][$k]??null)!=($r[$k]??null))reversalError('Use an approved ownership transfer to change the project owner.',403,'permissionDenied');
 foreach($new['projects']??[] as $r)if(!isset($indexed[$r['id']])&&!empty($r['transferHistory']))reversalError('Ownership history is controlled by the server.',403,'permissionDenied');
}
