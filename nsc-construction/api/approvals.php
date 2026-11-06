<?php
require_once __DIR__.'/partial-reversals.php';
require_once __DIR__.'/transfers.php';
require_once __DIR__.'/apartment-finance.php';
require_once __DIR__.'/credits.php';require_once __DIR__.'/geometry.php';
// Workflow authority lives on the server; workspace snapshots cannot forge it.
function financialCollections(): array {return ['expenses','steel','concrete','labor','payments','apartments','projectInvestments','projectTransfers','creditNotes'];}
function approvalMetadata(): array {return ['approvalStatus','approvalHistory','reversal','partialReversals','creditAdjustments'];}
function protectApprovals(array $current,array &$incoming): void {
 foreach(financialCollections() as $collection){
  $old=[];foreach($current[$collection]??[] as $r)$old[$r['id']]=$r;
  $new=[];if(!isset($incoming[$collection])){foreach($old as $r)if(isset($r['approvalStatus'])&&$r['approvalStatus']!=='draft')jsonResponse(false,null,'A locked financial document cannot be deleted.',409);continue;}foreach($incoming[$collection] as &$r){
   $id=$r['id'];$new[$id]=true;$before=$old[$id]??null;
   if(!$before){
    if(($r['approvalStatus']??'draft')!=='draft'||!empty($r['approvalHistory'])||isset($r['reversal'])||!empty($r['partialReversals'])||!empty($r['creditAdjustments']))jsonResponse(false,null,'New financial documents must start as drafts.',403);
    $r['approvalStatus']='draft';$r['approvalHistory']=[];
   }else{
    foreach(approvalMetadata() as $key)if(($before[$key]??null)!=($r[$key]??null))jsonResponse(false,null,'Use the approval action to change workflow status or history.',403);
    if(isset($before['approvalStatus'])&&$before['approvalStatus']!=='draft'&&$before!=$r)jsonResponse(false,null,'Submitted, approved and posted documents are locked.',409);
   }
  }unset($r);
  foreach($old as $id=>$r)if(!isset($new[$id])&&isset($r['approvalStatus'])&&$r['approvalStatus']!=='draft')jsonResponse(false,null,'A locked financial document cannot be deleted.',409);
 }
}
function invoiceAmounts(array $row): array {
 $gross=round((float)($row['amount']??$row['totalAmount']??$row['salePrice']??0),2);
 $retention=($row['partyType']??'')==='contractor'?round($gross*(float)($row['retentionPercentage']??0)/100,2):0;
 $tax=($row['partyType']??'')==='contractor'?min(max(0,round($gross-$retention,2)),round($gross*(float)($row['taxPercentage']??0)/100,2)):0;
 return ['gross'=>$gross,'retention'=>$retention,'tax'=>$tax,'net'=>max(0,round($gross-$retention-$tax,2))];
}
function transitionApproval(array $user,array &$doc,array $input): void {
 $collection=$input['collection']??'';$id=$input['id']??'';$target=$input['status']??'';
 if(!in_array($collection,financialCollections(),true)||!is_string($id)||!in_array($target,['submitted','approved','posted','draft','reversed'],true))jsonResponse(false,null,'Invalid approval action.',400);
 $index=null;foreach($doc[$collection]??[] as $i=>$r)if($r['id']===$id){$index=$i;break;}
 if($index===null)jsonResponse(false,null,'Document was not found.',404);
 $row=$doc[$collection][$index];if($collection==='projectTransfers')authorizeTransfer($user,$row,$target==='submitted'?'submit':($target==='reversed'?'reverse':($target==='draft'?'approve':$target)));$pid=$row['projectId']??'';if($collection==='creditNotes')authorizeCredit($user,$row,false);$module=workspaceModule($collection);
 if(!hasProjectAccess($user,$pid,$module,'view'))jsonResponse(false,null,'Document access is not permitted.',403);
 if($target==='reversed'){reverseFinancialDocument($user,$doc,$collection,$index,$input);return;}
 if(isset($row['reversal']))jsonResponse(false,null,'A reversed document cannot change workflow state.',409);
 $from=$row['approvalStatus']??'legacy';
 $permitted=['draft'=>['submitted'],'submitted'=>['approved','draft'],'approved'=>['posted','draft'],'posted'=>[],'legacy'=>[]];
 if(!in_array($target,$permitted[$from]??[],true))jsonResponse(false,null,'Invalid document state transition.',409);
 $canSubmit=hasProjectAccess($user,$pid,$module,'edit')||hasProjectAccess($user,$pid,$module,'create');
 if($target==='submitted'?!$canSubmit:!hasProjectAccess($user,$pid,$module,'approve'))jsonResponse(false,null,'Approval permission is required for this project and module.',403);
 if($target!=='draft'){
  $date=$collection==='apartments'?($row['saleDate']??$row['contractDate']??''):($row['date']??'');
  if($date===''&&($collection!=='apartments'||in_array($row['status']??'',['sold','reserved'],true)))jsonResponse(false,null,'A financial document date is required.',400);
 }
 if($target!=='draft'&&$collection==='apartments'&&($row['status']??'')==='sold'&&(float)($row['salePrice']??$row['totalPrice']??0)<=0)jsonResponse(false,null,'A sale needs a positive price.',400);
 if($target!=='draft'&&$collection!=='apartments'&&!($collection==='projectTransfers'&&($row['kind']??'')==='ownership_transfer')){
  $total=$collection==='labor'?(float)($row['daysWorked']??0)*(float)($row['dailyRate']??0):(float)($row['amount']??$row['totalAmount']??0);
  if(round($total,2)<=0)jsonResponse(false,null,'A financial document needs a positive amount.',400);
 }
 if($target!=='draft'&&$collection==='projectInvestments'){
  $partner=null;foreach($doc['projectPartners']??[] as $candidate)if(($candidate['id']??'')===($row['partnerId']??'')&&($candidate['projectId']??'')===$pid)$partner=$candidate;
  if(!$partner)reversalError('Select a partner from this project before submitting an investment.',400,'investmentPartnerInvalid');
 }
 if($target==='posted'&&$collection==='payments'){
  if(in_array($row['relatedType']??'',['expense','retention_release','tax_remittance','material_steel','steel','material_concrete','concrete','apartment_sale','payroll'],true)&&empty($row['relatedId']))jsonResponse(false,null,'A related financial document is required.',400);
  $related=['expense'=>'expenses','retention_release'=>'expenses','tax_remittance'=>'expenses','material_steel'=>'steel','steel'=>'steel','material_concrete'=>'concrete','concrete'=>'concrete','apartment_sale'=>'apartments','payroll'=>'labor'][$row['relatedType']??'']??null;
  if($related){$parent=null;foreach($doc[$related]??[] as $candidate)if($candidate['id']===$row['relatedId']&&$candidate['projectId']===$pid)$parent=$candidate;
   if(!$parent||isset($parent['reversal'])||isset($parent['approvalStatus'])&&$parent['approvalStatus']!=='posted')reversalError('Post the related financial document before its payment.',409,'receiptNeedsSale');
   if($related==='apartments'){
    if(!in_array($parent['status']??'',['sold','reserved'],true))reversalError('Post a sale or reservation before receiving payment.',409,'receiptNeedsSale');
    if(($row['date']??'')<apartmentSaleDate($parent))reversalError('Receipt cannot precede its contract.',400,'receiptInvalidDate');
    if(!empty($row['installmentId'])){
     $remaining=apartmentInstallmentBalances($parent,array_filter($doc['payments']??[],fn($p)=>$p['id']!==$id));
     if(!array_key_exists($row['installmentId'],$remaining))reversalError('Installment is not in this contract.',400,'installmentInvalid');
     if(paymentInApartmentCurrency($parent,$row)>$remaining[$row['installmentId']])reversalError('Receipt exceeds the outstanding installment.',409,'receiptLimit');
    }
   }
   if($related==='labor'){
    if(($parent['paymentStatus']??'pending')==='paid')reversalError('Payroll already includes its full cash settlement.',409,'receiptLimit');
    if(($row['currency']??'AFN')!==($parent['currency']??'AFN')||(float)($row['exchangeRate']??70)!==(float)($parent['exchangeRate']??70)||$row['date']<($parent['date']??''))reversalError('Payroll payment must use its contract currency, rate and date.',400,'validation');
   }
   foreach($doc['payments']??[] as $prior)if(($prior['relatedId']??'')===$parent['id']&&($prior['projectId']??'')===$pid){foreach(array_merge($prior['partialReversals']??[],isset($prior['reversal'])?[$prior['reversal']]:[]) as $refund)if(($row['date']??'')<$refund['date'])reversalError('Replacement settlement cannot predate recorded refunds.',400,'receiptInvalidDate');}
   foreach($parent['creditAdjustments']??[] as $adjustment)if(($row['date']??'')<$adjustment['date'])reversalError('Settlement cannot predate source adjustments.',400,'receiptInvalidDate');
   $amounts=adjustedInvoiceAmounts($parent);$type=$row['relatedType']??'';
   $total=$related==='expenses'?($type==='retention_release'?$amounts['retention']:($type==='tax_remittance'?$amounts['tax']:$amounts['net'])):($related==='labor'?round(adjustedGross($parent,'labor')-(float)($parent['advanceDeduction']??0),2):$amounts['gross']);$paid=0;
   foreach($doc['payments']??[] as $p)if(($p['id']===$id||($p['approvalStatus']??'posted')==='posted')&&!isset($p['reversal'])&&($related!=='expenses'||($p['relatedType']??'')===$type)&&($p['relatedId']??'')===$parent['id']&&($p['projectId']??'')===$pid){$usd=($p['currency']??'USD')==='AFN'?effectiveFinancialAmount($p)/(float)($p['exchangeRate']??70):effectiveFinancialAmount($p);$paid+=($parent['currency']??($related==='labor'?'AFN':'USD'))==='AFN'?$usd*(float)($parent['exchangeRate']??70):$usd;}
   if(round($paid,2)>round($total,2))reversalError('Posted payments would exceed the related document total.',409,'receiptLimit');
  }
 }
 if($collection==='creditNotes'&&$target==='posted')postCredit($user,$doc,$row);
 if($collection==='apartments'&&$target==='posted'&&($row['status']??'')==='sold')initializeSaleGeometry($doc,$row);
 if($collection==='projectTransfers'&&$target==='posted')postTransfer($user,$doc,$row);
 $row['approvalStatus']=$target;$row['approvalHistory'][]=['from'=>$from,'to'=>$target,'actorId'=>$user['id'],'actorName'=>$user['name'],'at'=>gmdate('c')];
 $doc[$collection][$index]=$row;
}

function reverseFinancialDocument(array $user,array &$doc,string $collection,int $index,array $input): void {
 if(!in_array($collection,['expenses','steel','concrete','payments','apartments','labor','projectInvestments','projectTransfers','creditNotes'],true))jsonResponse(false,null,'Reversal is not supported for this document type yet.',400);
 $row=$doc[$collection][$index];$project=$row['projectId']??'';
 if(!hasProjectAccess($user,$project,workspaceModule($collection),'reverse'))reversalError('Separate reversal permission is required for this project and module.',403,'permissionDenied');
 if(($row['approvalStatus']??'')!=='posted'||isset($row['reversal']))reversalError('Only a posted document can be reversed once.',409,'reverseUnavailable');
 if($collection==='apartments'&&($row['status']??'')!=='sold')reversalError('Only a posted sale can be reversed.',409,'reverseUnavailable');
 $reason=$input['reason']??null;$date=$input['date']??null;
 if(!is_string($reason)||!preg_match('/^.{5,1000}$/us',trim($reason)))reversalError('A reversal reason of 5–1000 characters is required.',400,'reversalReasonHint');
 if(!is_string($date))reversalError('A valid reversal date is required.',400,'reverseInvalidDate');
 $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
 if(!$parsed||$parsed->format('Y-m-d')!==$date)reversalError('A valid reversal date is required.',400,'reverseInvalidDate');
 $today=(new DateTimeImmutable('now',new DateTimeZone('Asia/Kabul')))->format('Y-m-d');
 if($date<($collection==='apartments'?apartmentSaleDate($row):($row['date']??''))||$date>$today)reversalError('Reversal date must be on or after the document date and no later than today.',400,'reverseInvalidDate');
 if($collection==='labor'&&($row['paymentStatus']??'')==='paid'&&!empty($row['paidDate'])&&$date<$row['paidDate'])reversalError('Payroll cannot reverse before its recorded payment date.',400,'reverseInvalidDate');
 if($date<=($doc['appSettings']['closedThrough']??''))reversalError('The reversal must be in an open accounting period.',409,'reverseClosed');
 if(in_array($collection,['expenses','steel','concrete','apartments','labor'],true)) {
  $types=['expenses'=>['expense','retention_release','tax_remittance'],'steel'=>['material_steel','steel'],'concrete'=>['material_concrete','concrete'],'apartments'=>['apartment_sale'],'labor'=>['payroll']][$collection];
  foreach($doc['payments']??[] as $p)if(($p['relatedId']??'')===$row['id']&&($p['projectId']??'')===$project&&in_array($p['relatedType']??'',$types,true)&&($p['approvalStatus']??'posted')==='posted'){
   if(!isset($p['reversal']))reversalError('Reverse all posted payments for this bill first.',409,'reverseNeedsPayments');
   if($p['reversal']['date']>$date)reversalError('The bill reversal cannot precede its payment reversals.',409,'reverseInvalidDate');
  }
 }
 if(in_array($collection,['expenses','steel','concrete'],true)){
  $code=$collection==='steel'?'5100':($collection==='concrete'?'5200':($row['category']??''));foreach($doc['projectTransfers']??[] as $t)if(($t['kind']??'')==='material_transfer'&&($t['projectId']??'')===$project&&($t['accountCode']??'')===$code&&($t['approvalStatus']??'draft')==='posted'&&!isset($t['reversal']))reversalError('Reverse outgoing material cost transfers before reversing this source cost.',409,'transferStockLimit');
 }
 foreach($doc['creditNotes']??[] as $n)if(($n['sourceCollection']??'')===$collection&&($n['sourceId']??'')===$row['id']&&($n['projectId']??'')===$project&&($n['approvalStatus']??'draft')==='posted'){if(!isset($n['reversal']))reversalError('Reverse active credits before reversing the original.',409,'creditLatest');if($n['reversal']['date']>$date)reversalError('Original reversal cannot predate its credit reversals.',400,'reverseInvalidDate');}
 $event=['id'=>generateUuid(),'date'=>$date,'reason'=>trim($reason),'actorId'=>$user['id'],'actorName'=>$user['name'],'at'=>gmdate('c')];
 if(in_array($collection,['payments','projectInvestments'],true))applyCashReversal($row,$input,$event);else{if(isset($input['amount']))reversalError('Partial reversal is supported for cash documents.',400,'partialLimit');if($collection==='creditNotes')reverseCredit($user,$doc,$row,$event);if($collection==='projectTransfers')reverseTransfer($user,$doc,$row,$date);$row['reversal']=$event;}
 $doc[$collection][$index]=$row;
}

function reversalError(string $message,int $status,string $code): void {jsonResponse(false,['code'=>$code],$message,$status);}
