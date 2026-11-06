<?php
/** Append-only partner share history. No company cash is manufactured by a private sale. */
function validateShareTransfers(array $user,array $previous,array $incoming): void {
 $totals=[];foreach($incoming['projectPartners']??[] as $p){$share=$p['sharePercentage']??0;if(!is_numeric($share)||!is_finite((float)$share)||$share<0||$share>100)jsonResponse(false,null,'Invalid partner share.',400);$pid=$p['projectId'];$totals[$pid]=($totals[$pid]??0)+(float)$share;}
 foreach($totals as $total)if($total>100.00000001)jsonResponse(false,null,'Partner shares cannot exceed 100 percent.',400);
 $old=array_column($previous['partnerEquityTransfers']??[],null,'id');$new=array_column($incoming['partnerEquityTransfers']??[],null,'id');
 foreach($old as $id=>$row)if(!isset($new[$id])||$new[$id]!=$row)jsonResponse(false,null,'Partner share history cannot be changed or deleted.',409);
 foreach($incoming['projectPartners']??[] as $partner)if(($partner['status']??'')==='exited'&&(float)($partner['sharePercentage']??0)>0)jsonResponse(false,null,'A partner with remaining shares has not exited.',400);
 $partners=array_column($previous['projectPartners']??[],null,'id');$after=array_column($incoming['projectPartners']??[],null,'id');$expected=[];
 foreach($partners as $id=>$partner)if(!isset($after[$id])){
  foreach($previous['projectInvestments']??[] as $investment)if(($investment['partnerId']??'')===$id)jsonResponse(false,null,'Partner investment history must be preserved. Sell shares to end the partnership.',409);
  foreach($previous['partnerEquityTransfers']??[] as $transfer)if(($transfer['fromPartnerId']??'')===$id||($transfer['toPartnerId']??'')===$id)jsonResponse(false,null,'Partner share history must be preserved.',409);
 }

 foreach($new as $id=>$row){if(isset($old[$id]))continue;$pid=$row['projectId']??'';$from=$row['fromPartnerId']??'';$to=$row['toPartnerId']??'';$percent=$row['transferredPercentage']??0;
  if(!hasProjectAccess($user,$pid,'equity','create')||!hasProjectAccess($user,$pid,'partners','edit'))jsonResponse(false,null,'Share transfers require partner edit and transfer create permissions.',403);
  $type=$row['transferType']??'';
  if($type==='new_partner'){
   if(!hasProjectAccess($user,$pid,'partners','create'))jsonResponse(false,null,'Creating a new buyer requires partner create permission.',403);
   if(isset($partners[$to])||!isset($after[$to])||($after[$to]['projectId']??'')!==$pid||!is_string($after[$to]['name']??null)||trim($after[$to]['name'])===''||strlen($after[$to]['name'])>800||($after[$to]['initialInvestment']??0)!=0)jsonResponse(false,null,'Invalid new buyer.',400);
   $partners[$to]=$after[$to];$partners[$to]['sharePercentage']=0;
   foreach($incoming['projectInvestments']??[] as $investment)if(($investment['partnerId']??'')===$to)jsonResponse(false,null,'A private share sale is not a company investment.',400);
  }
  if(!in_array($type,['partner_to_partner','new_partner'],true)||$from===$to||!isset($partners[$from],$partners[$to])||$partners[$from]['projectId']!==$pid||$partners[$to]['projectId']!==$pid)jsonResponse(false,null,'Transfer partners must belong to this project.',400);
  if(!is_int($percent)&&!is_float($percent)||!is_finite((float)$percent)||$percent<=0||$percent>(float)$partners[$from]['sharePercentage']+0.00000001)jsonResponse(false,null,'Transfer exceeds the selling partner share.',400);
  $price=$row['transferPrice']??0;if(!is_int($price)&&!is_float($price)||!is_finite((float)$price)||$price<0||$price>1e12||!in_array($row['currency']??'', ['USD','AFN'],true))jsonResponse(false,null,'Invalid private transfer price.',400);
  $date=$row['transferDate']??'';$parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;if(!$parsed||$parsed->format('Y-m-d')!==$date)jsonResponse(false,null,'Invalid transfer date.',400);
  $closed=$previous['appSettings']['closedThrough']??'';if($closed&&$date<=$closed)jsonResponse(false,null,'The accounting period is closed.',409);
  $partners[$from]['sharePercentage']=round($partners[$from]['sharePercentage']-$percent,8);$partners[$to]['sharePercentage']=round($partners[$to]['sharePercentage']+$percent,8);$expected[$from]=$partners[$from]['sharePercentage'];$expected[$to]=$partners[$to]['sharePercentage'];
 }
 foreach($partners as $id=>$partner)if(isset($after[$id])&&!isset($expected[$id])&&abs((float)($partner['sharePercentage']??0)-(float)($after[$id]['sharePercentage']??0))>.00000001){
  if(!hasProjectAccess($user,$partner['projectId'],'equity','create'))jsonResponse(false,null,'Share changes require equity permission.',403);
  foreach($old as $transfer)if(($transfer['fromPartnerId']??'')===$id||($transfer['toPartnerId']??'')===$id)jsonResponse(false,null,'Use a share transfer to change previously traded shares.',409);
 }
 foreach($expected as $id=>$share)if(!isset($after[$id])||abs((float)$after[$id]['sharePercentage']-$share)>0.00000001)jsonResponse(false,null,'Partner shares do not match the transfer history.',400);
}
