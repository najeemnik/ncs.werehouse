<?php
require_once __DIR__.'/../includes/management_service.php';auth_require();$actor=auth_user();$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST')try{management_mutate($_POST);redirect(BASE_URL.'index.php');}catch(Throwable $e){http_response_code(403);$error=__t('Branch access denied.','اجازهٔ ورود به این شعبه را ندارید.','څانګې ته لاسرسی نشته.');}
$start=$_GET['from']??date('Y-m-01');$end=$_GET['to']??date('Y-m-d');
foreach([$start,$end] as $d){$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$d);if(!$dt||$dt->format('Y-m-d')!==$d){http_response_code(422);exit('Invalid date.');}}
if($start>$end){http_response_code(422);exit('Invalid date range.');}
$pageTitle=__t('Business & branches overview','نمای تجارت‌ها و شعبه‌ها','د سوداګریو او څانګو کتنه');require __DIR__.'/../includes/header.php';
?>
<h2><?= sanitize($pageTitle) ?></h2><form method="get" class="card form-grid"><label><?= __t('From','از تاریخ','له نېټې') ?><input class="form-control" type="date" name="from" value="<?= sanitize($start) ?>"></label><label><?= __t('To','تا تاریخ','تر نېټې') ?><input class="form-control" type="date" name="to" value="<?= sanitize($end) ?>"></label><button class="btn btn-primary"><?= __t('Apply','نمایش','وښایئ') ?></button></form>
<?php if($error):?><p role="alert"><?= sanitize($error) ?></p><?php endif ?>
<div class="branch-grid"><?php foreach(domain_branches($actor) as $s):?>
<section class="card"><h3><?= sanitize($s['name']) ?></h3><p><?= sanitize($s['city']) ?></p>
<table class="table"><thead><tr><th><?= __t('Currency','ارز','اسعار') ?></th><th><?= __t('Sales','فروش','پلور') ?></th><th><?= __t('Expenses','مصارف','لګښتونه') ?></th><th><?= __t('Outstanding','باقی‌داری','پاتې پور') ?></th></tr></thead><tbody>
<?php foreach(['AFN','USD','AED','EUR'] as $code):$tot=[];
foreach(['sales'=>['sales','total_amount','created_at'],'expenses'=>[live_documents_source('expenses'),'amount','expense_date'],'outstanding'=>['debts','remaining_amount',null]] as $key=>[$table,$col,$date]){
 $module=$key==='outstanding'?'debts':$key;if(!domain_can($module.'.view',$actor,(int)$s['id'])){$tot[$key]='—';continue;}
 $col=$table==='sales'?sale_net_sql($col):$col;$sql="SELECT COALESCE(SUM($col),0) FROM $table WHERE shop_id=? AND currency_code=?";$args=[$s['id'],$code];if($table==='sales')$sql.=' AND NOT EXISTS(SELECT 1 FROM sales_returns sr WHERE sr.sale_id=sales.id AND sr.shop_id=sales.shop_id AND sr.status=\'posted\')';if($date){$sql.=" AND $date>=? AND $date<=?";$args[]=$start;$args[]=$end.' 23:59:59';}$q=$pdo->prepare($sql);$q->execute($args);$tot[$key]=number_format((float)$q->fetchColumn(),2);
} ?><tr><td><?= $code ?></td><td><?= $tot['sales'] ?></td><td><?= $tot['expenses'] ?></td><td><?= $tot['outstanding'] ?></td></tr><?php endforeach ?></tbody></table>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="switch_branch"><input type="hidden" name="shop_id" value="<?= (int)$s['id'] ?>"><button class="btn btn-primary"><?= __t('Open branch','بازکردن شعبه','څانګه پرانیزئ') ?></button></form></section><?php endforeach ?></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
