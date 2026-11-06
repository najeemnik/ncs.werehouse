<?php
function effectiveFinancialAmount(array $row): float {if(isset($row['reversal']))return 0;$sum=0;foreach($row['partialReversals']??[] as $p)$sum+=(float)$p['amount'];return max(0,round((float)($row['amount']??0)-$sum,2));}
function applyCashReversal(array &$row,array $input,array $event): void {
 $remaining=effectiveFinancialAmount($row);$amount=$input['amount']??$remaining;
 if(!is_numeric($amount)||!is_finite((float)$amount)||(float)$amount<=0||round((float)$amount,2)!=(float)$amount||(float)$amount>$remaining)reversalError('Reversal exceeds the remaining posted amount.',400,'partialLimit');
 foreach($row['partialReversals']??[] as $previous)if($event['date']<$previous['date'])reversalError('Reversal cannot precede the previous reversal.',400,'reverseInvalidDate');
 if(round((float)$amount,2)===$remaining){if(!empty($row['partialReversals']))$event['amount']=$remaining;$row['reversal']=$event;}else{$event['amount']=(float)$amount;$row['partialReversals'][]=$event;}
}
