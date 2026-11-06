<?php
require_once __DIR__.'/design.php';
function validateRaster($value,int $max): void {
 if($value==='')return;
 if(!is_string($value)||strlen($value)>$max||!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#D',$value,$m))designInvalid();
 $bytes=base64_decode($m[2],true);if($bytes===false)designInvalid();$info=@getimagesizefromstring($bytes);if(!$info||!in_array($info['mime']??'',['image/jpeg','image/png','image/webp'],true)||$info[0]*$info[1]>4000000)designInvalid();
}
function validateCompanyProfile($value): void {
 designObject($value,['legalName','registrationNumber','taxNumber','address','city','phone','email','whatsapp','website','bankName','bankAccount','bankHolder','authorizedSignatory','receiptHeader','receiptFooter','logo']);
 foreach($value as $key=>$v){if($key==='logo')validateRaster($v,65000);else{designString($v,in_array($key,['receiptHeader','receiptFooter'])?2000:500);if($key==='email'&&$v!==''&&!filter_var($v,FILTER_VALIDATE_EMAIL))designInvalid();}}
}
function validateDocumentTemplate($v): void {
 $blocks=['headerBlock','detailsBlock','itemsBlock','notesBlock','signaturesBlock','footerBlock'];
 designObject($v,['paper','orientation','accent','fontSize','margin','logoWidth','order','hidden','texts']);
 designEnum($v['paper']??null,['A4','A5','Letter','80mm']);designEnum($v['orientation']??null,['portrait','landscape']);designColor($v['accent']??null);
 foreach(['fontSize'=>[9,22],'margin'=>[0,35],'logoWidth'=>[0,180]] as $key=>$range)designNumber($v[$key]??null,...$range);
 foreach(['order','hidden'] as $key){designList($v[$key]??null,6);if(count($v[$key])!==count(array_unique($v[$key])))designInvalid();foreach($v[$key] as $x)designEnum($x,$blocks);}
 if(count($v['order'])!==6||in_array('itemsBlock',$v['hidden'])||in_array('detailsBlock',$v['hidden']))designInvalid();
 designObject($v['texts']??null,$blocks);foreach($v['texts'] as $text)designLocal($text);
}
function validateCompanySettings(array $settings): void {
 if(array_is_list($settings)&&count($settings))designInvalid();
 if(isset($settings['companyName']))designString($settings['companyName'],500);
 if(isset($settings['language']))designEnum($settings['language'],['en','fa','ps']);
 if(isset($settings['defaultCurrency']))designEnum($settings['defaultCurrency'],['USD','AFN']);
 if(isset($settings['exchangeRateUSDToAFN']))designNumber($settings['exchangeRateUSDToAFN'],.0001,1000000);
 if(isset($settings['closedThrough'])){designString($settings['closedThrough'],10);if($settings['closedThrough']!==''){$date=DateTimeImmutable::createFromFormat('!Y-m-d',$settings['closedThrough']);if(!$date||$date->format('Y-m-d')!==$settings['closedThrough'])designInvalid();}}
}
function validateNewFeatures(array $user,array $before,array $doc,string $tenant): void {
 validateCompanySettings($doc['appSettings']??[]);
 foreach(uiCatalog()['modules'] as $module){$old=array_column($before[$module['collection']]??[],null,'id');foreach($doc[$module['collection']]??[] as $row)foreach($module['fields'] as $field){$key=$field['key'];if(!array_key_exists($key,$row)||$row[$key]===null||isset($old[$row['id']])&&array_key_exists($key,$old[$row['id']])&&$old[$row['id']][$key]===$row[$key])continue;if(in_array($field['type'],['text','textarea','select','date','url'])&&!is_string($row[$key]))jsonResponse(false,null,'Invalid field value.',400);}}

 foreach(['companyProfile','documentTemplates'] as $key)if(!isOffice7Owner($user)&&($doc['appSettings'][$key]??[])!=($before['appSettings'][$key]??[]))jsonResponse(false,null,'Document settings are controlled by Office Seven.',403);
 if(isset($doc['appSettings']['companyProfile']))validateCompanyProfile($doc['appSettings']['companyProfile']);
 if(isset($doc['appSettings']['documentTemplates'])){designObject($doc['appSettings']['documentTemplates'],['salesBill','cashReceipt','goodsReceipt','presaleBill']);foreach($doc['appSettings']['documentTemplates'] as $v)validateDocumentTemplate($v);}
 $projectBefore=array_column($before['projects']??[],null,'id');$controls=json_decode($user['allowed_modules_json']??'{}',true)?:[];
 foreach($doc['projects']??[] as $p){foreach(['partners'=>'partners','initialGeneralExpenses'=>'expenses'] as $field=>$module)if(($p[$field]??[])!=($projectBefore[$p['id']][$field]??[])&&!hasProjectAccess($user,$p['id'],$module,isset($projectBefore[$p['id']])?'edit':'create'))jsonResponse(false,null,'Embedded project data requires the related module permission.',403);if(isset($p['defaultExchangeRate']))designNumber($p['defaultExchangeRate'],.0001,1000000);if(!isOffice7Owner($user)&&($controls['projectAppearance']??true)===false&&($p['appearance']??null)!=($projectBefore[$p['id']]['appearance']??null))jsonResponse(false,null,'Project appearance is disabled.',403);if(isset($p['progressPercentage']))designNumber($p['progressPercentage'],0,100);if(isset($p['appearance'])){$v=$p['appearance'];designObject($v,['background','logo','fit','size','opacity','logoSize']);foreach(['background'=>700000,'logo'=>65000] as $k=>$limit)if(isset($v[$k])){if(is_string($v[$k])&&preg_match('#^/api/media\.php\?id=([a-f0-9-]{36})$#D',$v[$k],$m)){$s=Database::getConnection()->prepare('SELECT id FROM uploaded_files WHERE id=:id AND tenant_id=:tid AND project_id=:pid AND media_kind=:kind');$s->execute(['id'=>$m[1],'tid'=>$tenant,'pid'=>$p['id'],'kind'=>$k]);if(!$s->fetch())designInvalid();}else validateRaster($v[$k],$limit);}if(isset($v['fit']))designEnum($v['fit'],['cover','contain','repeat']);foreach(['size'=>[10,300],'opacity'=>[0,100],'logoSize'=>[20,120]] as $k=>$range)if(isset($v[$k]))designNumber($v[$k],...$range);}}
 $old=array_column($before['projectBudgets']??[],null,'id');$accounts=array_column(uiCatalog()['accounts']??[],null,'code');
 foreach($doc['projectBudgets']??[] as $b){if(($old[$b['id']]??null)==$b||!isset($b['components']))continue;designList($b['components'],150);$sum=0;$ids=[];$rate=$b['exchangeRate']??70;designNumber($rate,.0001,1000000);
  foreach($b['components'] as $l){designObject($l,['id','accountCode','description','quantity','unit','unitPrice','currency']);designString($l['id']??'',80);if(empty($l['id'])||isset($ids[$l['id']]))designInvalid();$ids[$l['id']]=true;designString($l['description']??'',500);designString($l['unit']??'',40);designNumber($l['quantity']??null,0,1000000000);designNumber($l['unitPrice']??null,0,1000000000);designEnum($l['currency']??null,['USD','AFN']);if($l['quantity']*$l['unitPrice']>1e12)designInvalid();
   $code=$l['accountCode']??'';$visited=[];while($code!==$b['accountCode']&&isset($accounts[$code])&&!isset($visited[$code])){$visited[$code]=true;$code=$accounts[$code]['parentCode']??'';}if($code!==$b['accountCode'])designInvalid();
   $sum+=round($l['quantity']*$l['unitPrice']/($l['currency']==='AFN'?$rate:1)*100);
  }
  if(($b['currency']??'')!=='USD'||abs($sum/100-($b['amount']??-1))>.005)jsonResponse(false,null,'Budget total does not match its components.',400);
 }
}
