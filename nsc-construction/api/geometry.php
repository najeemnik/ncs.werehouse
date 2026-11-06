<?php
function projectInventoryArea(array $doc,string $pid):float{$roots=[];foreach($doc['apartments']??[] as $r)if(($r['projectId']??'')===$pid){$root=apartmentInventoryRoot($r,$doc['apartments']);if($root!==$r['id'])continue;$roots[$root]=(float)(($r['areaM2']??0)?:($r['areaSqm']??0));} $total=round(array_sum($roots),2);if($total>0)return $total;foreach($doc['projects']??[] as $p)if($p['id']===$pid)return round((float)($p['buildingArea']??0),2);return 0;}
function historicalSale(array $doc,string $pid):bool{foreach($doc['apartments']??[] as $r)if(($r['projectId']??'')===$pid&&($r['status']??'')==='sold'&&($r['approvalStatus']??'posted')==='posted')return true;return false;}
function protectInventoryGeometry(array $old,array $new):void{
 $projects=[];foreach($old['projects']??[] as $p)$projects[$p['id']]=$p;
 foreach($new['projects']??[] as $p)if(($projects[$p['id']]['inventoryAreaHistory']??null)!=($p['inventoryAreaHistory']??null))reversalError('Inventory area history is server controlled.',403,'permissionDenied');
 $units=[];foreach($new['apartments']??[] as $u)$units[$u['id']]=$u;
 foreach($old['apartments']??[] as $u)if(($u['status']??'')==='sold'&&($u['approvalStatus']??'posted')==='posted'){if(!isset($units[$u['id']]))reversalError('Historical sale geometry cannot be removed.',409,'geometryLocked');foreach(['projectId','unitNumber','floor','buildingSection','areaM2','areaSqm','status','contractDate','saleDate','salePrice','totalPrice','currency','exchangeRate'] as $k)if(($units[$u['id']][$k]??null)!=($u[$k]??null))reversalError('Sold unit geometry is fixed; use a separate contract correction.',409,'geometryLocked');}
}
function syncInventoryGeometry(array $old,array &$new):void{
 $today=(new DateTimeImmutable('now',new DateTimeZone('Asia/Kabul')))->format('Y-m-d');
 foreach($new['projects']??[] as $i=>$p){$pid=$p['id'];if(!historicalSale($old,$pid))continue;$before=projectInventoryArea($old,$pid);$after=projectInventoryArea($new,$pid);$history=!empty($p['inventoryAreaHistory'])?$p['inventoryAreaHistory']:[['date'=>'0001-01-01','totalAreaM2'=>$before]];if($before!==$after){if(($history[count($history)-1]['date']??'')===$today)$history[count($history)-1]['totalAreaM2']=$after;else $history[]=['date'=>$today,'totalAreaM2'=>$after];}$new['projects'][$i]['inventoryAreaHistory']=$history;}
}
function initializeSaleGeometry(array &$doc,array $u):void{
 foreach($doc['projects'] as $i=>$p)if($p['id']===$u['projectId']){if(empty($p['inventoryAreaHistory']))$doc['projects'][$i]['inventoryAreaHistory']=[['date'=>'0001-01-01','totalAreaM2'=>projectInventoryArea($doc,$p['id'])]];$history=$doc['projects'][$i]['inventoryAreaHistory'];$latest=end($history);if($latest['date']>apartmentSaleDate($u))reversalError('Sale cannot predate the latest inventory geometry change.',409,'geometryDate');}
}
