<?php
function creditSourceCollections():array{return ['expenses','steel','concrete','apartments','labor'];}
function sourceGross(array $r,string $c=''):float{return round($c==='labor'?(float)($r['daysWorked']??0)*(float)($r['dailyRate']??0):(float)($r['amount']??$r['totalAmount']??$r['salePrice']??0),2);}
function creditedTotal(array $r):float{return round(array_sum(array_column($r['creditAdjustments']??[],'amount')),2);}
function adjustedGross(array $r,string $c=''):float{return max(0,round(sourceGross($r,$c)-creditedTotal($r),2));}
function adjustedInvoiceAmounts(array $r):array{$a=invoiceAmounts($r);$credit=creditedTotal($r);foreach(['retention','tax','net'] as $k)$a[$k]=max(0,round($a[$k]-($a['gross']>0?round($a[$k]*$credit/$a['gross'],2):0),2));$a['gross']=adjustedGross($r);return $a;}
function creditSource(array $doc,array $r):array{$c=$r['sourceCollection']??'';if(!in_array($c,creditSourceCollections(),true))reversalError('Invalid credit source.',400,'validation');foreach($doc[$c]??[] as $i=>$s)if(($s['id']??'')===($r['sourceId']??'')&&($s['projectId']??'')===($r['projectId']??''))return [$c,$i,$s];reversalError('Credit source is unavailable in this project.',400,'creditSourceInvalid');}
function validateCredit(array $doc,array $r):void{
 [$c,$i,$s]=creditSource($doc,$r);$currency=$s['currency']??($c==='labor'?'AFN':'USD');
 if(isset($r['daysReduction'])&&(float)$r['daysReduction']!==0.0){$days=(float)$r['daysReduction'];$used=array_sum(array_column($s['creditAdjustments']??[],'daysReduction'));if($c!=='labor'||!is_numeric($r['daysReduction'])||!is_finite($days)||$days<0||$days>round((float)($s['daysWorked']??0)-$used,8)||round($days*(float)($s['dailyRate']??0),2)!=(float)($r['amount']??0))reversalError('Payroll day correction must use remaining days and the original daily rate.',400,'creditDaysInvalid');}
 if(($r['currency']??'')!==$currency||(float)($r['exchangeRate']??70)!==(float)($s['exchangeRate']??70)||!is_string($r['reason']??null)||!preg_match('/^.{5,1000}$/us',trim($r['reason']))||!is_numeric($r['amount']??null)||$r['amount']<=0||round((float)$r['amount'],2)!=(float)$r['amount'])reversalError('Credit needs source currency, rate, cent precision and reason.',400,'validation');
}
function authorizeCredit(array $u,array $r,bool $amend):void{
 $module=workspaceModule($r['sourceCollection']??'');if(!$module||!hasProjectAccess($u,$r['projectId']??'',$module,'view')||($amend&&!hasProjectAccess($u,$r['projectId']??'',$module,'reverse')))reversalError('Credit requires access to its source and separate source amendment permission.',403,'permissionDenied');
}
function postCredit(array $u,array &$doc,array $r):void{
 validateCredit($doc,$r);authorizeCredit($u,$r,true);[$c,$i,$s]=creditSource($doc,$r);
 if(($s['approvalStatus']??'')!=='posted'||isset($s['reversal'])||$c==='apartments'&&($s['status']??'')!=='sold')reversalError('Credit requires an active posted source.',409,'creditSourceInvalid');
 $date=$r['date']??'';$sourceDate=$c==='apartments'?apartmentSaleDate($s):($s['date']??'');$today=(new DateTimeImmutable('now',new DateTimeZone('Asia/Kabul')))->format('Y-m-d');
 if($date<$sourceDate||$date>$today||$date<=($doc['appSettings']['closedThrough']??''))reversalError('Credit must be dated in an open period after its source.',400,'reverseInvalidDate');
 foreach($s['creditAdjustments']??[] as $e)if($e['date']>$date)reversalError('Credit cannot predate an earlier adjustment.',400,'reverseInvalidDate');
 if($r['amount']>adjustedGross($s,$c))reversalError('Credit exceeds the remaining source amount.',409,'creditLimit');
 if(in_array($c,['expenses','steel','concrete'],true)){$code=$c==='steel'?'5100':($c==='concrete'?'5200':($s['category']??''));foreach($doc['projectTransfers']??[] as $t)if(($t['kind']??'')==='material_transfer'&&($t['projectId']??'')===$r['projectId']&&($t['accountCode']??'')===$code&&($t['approvalStatus']??'draft')==='posted'&&!isset($t['reversal']))reversalError('Reverse outgoing material value before reducing this cost.',409,'transferStockLimit');}
 $s['creditAdjustments'][]=['id'=>generateUuid(),'creditNoteId'=>$r['id'],'date'=>$date,'amount'=>(float)$r['amount'],'daysReduction'=>(float)($r['daysReduction']??0),'reason'=>trim($r['reason']),'actorId'=>$u['id'],'actorName'=>$u['name'],'at'=>gmdate('c')];
 $types=['expenses'=>['expense','retention_release','tax_remittance'],'steel'=>['material_steel','steel'],'concrete'=>['material_concrete','concrete'],'apartments'=>['apartment_sale'],'labor'=>['payroll']][$c];$paid=[];
 foreach($doc['payments']??[] as $pay)if(($pay['projectId']??'')===$s['projectId']&&($pay['relatedId']??'')===$s['id']&&in_array($pay['relatedType']??'',$types,true)&&($pay['approvalStatus']??'posted')==='posted'){$type=$c==='expenses'?$pay['relatedType']:'total';foreach(array_merge($pay['partialReversals']??[],isset($pay['reversal'])?[$pay['reversal']]:[]) as $refund)if($date<$refund['date'])reversalError('Credit cannot predate its settlement refunds.',400,'receiptInvalidDate');$usd=($pay['currency']??'USD')==='AFN'?effectiveFinancialAmount($pay)/(float)($pay['exchangeRate']??70):effectiveFinancialAmount($pay);$paid[$type]=round(($paid[$type]??0)+($r['currency']==='AFN'?$usd*$r['exchangeRate']:$usd),2);}
 if($c==='expenses'){$a=adjustedInvoiceAmounts($s);foreach(['expense'=>'net','retention_release'=>'retention','tax_remittance'=>'tax'] as $type=>$key)if(($paid[$type]??0)>$a[$key])reversalError('Refund excess settlement before reducing the bill.',409,'creditNeedsRefund');}
 else{$limit=adjustedGross($s,$c);if($c==='labor'){$advance=(float)($s['advanceDeduction']??0);$limit=round($limit-$advance,2);if($limit<0||($s['paymentStatus']??'')==='paid'&&(float)$r['amount']>0)reversalError('Payroll reduction must preserve applied advances and refund recorded cash first.',409,'creditNeedsRefund');}if(($paid['total']??0)>$limit)reversalError('Refund excess receipt or payment first.',409,'creditNeedsRefund');}
 $doc[$c][$i]=$s;
}
function reverseCredit(array $u,array &$doc,array $r,array $event):void{
 authorizeCredit($u,$r,true);[$c,$i,$s]=creditSource($doc,$r);if(isset($s['reversal']))reversalError('Source has already been reversed.',409,'creditSourceInvalid');
 $active=[];foreach($doc['creditNotes']??[] as $n)if(($n['sourceCollection']??'')===$c&&($n['sourceId']??'')===$s['id']&&($n['projectId']??'')===$s['projectId']&&($n['approvalStatus']??'draft')==='posted'&&!isset($n['reversal']))$active[]=$n;
 $last=null;$ids=array_column($active,'id');foreach($s['creditAdjustments']??[] as $e)if($e['amount']>0&&in_array($e['creditNoteId'],$ids,true))$last=$e['creditNoteId'];
 if($last!==$r['id'])reversalError('Reverse the latest active credit first.',409,'creditLatest');
 foreach($s['creditAdjustments']??[] as $e)if($event['date']<$e['date'])reversalError('Credit reversal cannot predate an adjustment.',400,'reverseInvalidDate');
 $s['creditAdjustments'][]=array_merge($event,['creditNoteId'=>$r['id'],'amount'=>-(float)$r['amount'],'daysReduction'=>-(float)($r['daysReduction']??0),'restoresCredit'=>true]);$doc[$c][$i]=$s;
}
