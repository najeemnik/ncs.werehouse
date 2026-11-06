<?php
function apartmentSaleDate(array $unit): string {return $unit['saleDate']??$unit['contractDate']??'';}
function paymentInApartmentCurrency(array $unit,array $payment): float {
 $usd=($payment['currency']??'USD')==='AFN'?effectiveFinancialAmount($payment)/(float)($payment['exchangeRate']??70):effectiveFinancialAmount($payment);
 return round(($unit['currency']??'USD')==='AFN'?$usd*(float)($unit['exchangeRate']??70):$usd,2);
}
function apartmentInstallmentBalances(array $unit,array $payments): array {
 $direct=[];$general=0;
 foreach($payments as $p)if(($p['relatedType']??'')==='apartment_sale'&&($p['relatedId']??'')===$unit['id']&&($p['projectId']??'')===($unit['projectId']??'')&&($p['approvalStatus']??'posted')==='posted'&&!isset($p['reversal'])){
  $amount=paymentInApartmentCurrency($unit,$p);if(!empty($p['installmentId']))$direct[$p['installmentId']]=round(($direct[$p['installmentId']]??0)+$amount,2);else $general=round($general+$amount,2);
 }
 $general=max(0,round($general-(float)($unit['downPayment']??0),2));$plans=$unit['installments']??[];
 usort($plans,fn($a,$b)=>(($a['installmentNumber']??0)<=>($b['installmentNumber']??0))?:strcmp($a['id'],$b['id']));$result=[];
 foreach($plans as $inst){$assigned=$direct[$inst['id']]??0;$extra=min($general,max(0,(float)$inst['amount']-$assigned));$general=round($general-$extra,2);$result[$inst['id']]=max(0,round((float)$inst['amount']-$assigned-$extra,2));}
 $credit=creditedTotal($unit);foreach(array_reverse($plans) as $inst){$reduce=min($credit,$result[$inst['id']]);$result[$inst['id']]=round($result[$inst['id']]-$reduce,2);$credit=round($credit-$reduce,2);}
 return $result;
}
function validateApartmentPlan(array $unit): void {
 if(!isset($unit['approvalStatus']))return; // Historical receipts require reviewed migration.
 $sale=(float)($unit['salePrice']??$unit['totalPrice']??0);$down=(float)($unit['downPayment']??0);
 if($down>$sale)jsonResponse(false,null,'Down payment exceeds the sale price.',400);
 $plans=$unit['installments']??[];if(!is_array($plans)||!array_is_list($plans)||count($plans)>1000)jsonResponse(false,null,'Invalid installment schedule.',400);
 $seen=[];$numbers=[];$sum=0;
 foreach($plans as $inst){
  if(!is_array($inst)||!is_string($inst['id']??null)||$inst['id']===''||strlen($inst['id'])>120||isset($seen[$inst['id']]))jsonResponse(false,null,'Invalid installment identifier.',400);$seen[$inst['id']]=true;
  $number=$inst['installmentNumber']??null;if(!is_int($number)||$number<1||$number>1000000||isset($numbers[$number]))jsonResponse(false,null,'Invalid installment number.',400);$numbers[$number]=true;
  $amount=$inst['amount']??null;if(!is_numeric($amount)||!is_finite((float)$amount)||(float)$amount<=0||(float)$amount>1e12||round((float)$amount,2)!=(float)$amount)jsonResponse(false,null,'Invalid installment amount.',400);
  if(($inst['currency']??$unit['currency']??'USD')!==($unit['currency']??'USD'))jsonResponse(false,null,'Installments use the contract currency.',400);
  $date=$inst['dueDate']??'';$parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;if(!$parsed||$parsed->format('Y-m-d')!==$date||$date<apartmentSaleDate($unit))jsonResponse(false,null,'Invalid installment date.',400);
  if((float)($inst['paidAmount']??0)!==0.0||!empty($inst['paymentHistory']))jsonResponse(false,null,'Record installment receipts as separate payment documents.',400);
  $sum=round($sum+(float)$amount,2);
 }
 if(round($sum+$down,2)>round($sale,2))jsonResponse(false,null,'Installments and down payment exceed the sale price.',400);
}

function apartmentInventoryRoot(array $unit,array $units): string {
 $seen=[];$current=$unit;
 while(!empty($current['correctionOf'])&&!isset($seen[$current['id']])){$seen[$current['id']]=true;$parent=null;foreach($units as $candidate)if(($candidate['id']??'')===$current['correctionOf']&&($candidate['projectId']??'')===($unit['projectId']??''))$parent=$candidate;if(!$parent)break;$current=$parent;}
 return $current['id'];
}
