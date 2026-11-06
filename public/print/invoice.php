<?php
/** One renderer for current invoices, immutable snapshots, preview and Word export. */
require_once __DIR__.'/../includes/functions.php';require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/invoice_render.php';
auth_require();
$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT);$sample=($_GET['demo']??'')==='1'||$id===0;
if($sample){$brand=invoice_effective_brand(shop_id());$layout=invoice_layout($brand);[$sale,$items]=invoice_preview_data($layout);}
else{
 if(!$id||$id<1){http_response_code(404);exit(__t('Invoice not found.','بل یافت نشد.','بل ونه موندل شو.'));}
 $q=$pdo->prepare('SELECT * FROM sales WHERE id=? AND shop_id=?');$q->execute([$id,shop_id()]);$sale=$q->fetch();if(!$sale){http_response_code(404);exit(__t('Invoice not found.','بل یافت نشد.','بل ونه موندل شو.'));}
 $q=$pdo->prepare('SELECT * FROM sale_items WHERE sale_id=? AND shop_id=? ORDER BY id');$q->execute([$id,shop_id()]);$items=$q->fetchAll();
 $snapshot=$sale['invoice_snapshot']?json_decode($sale['invoice_snapshot'],true,32,JSON_THROW_ON_ERROR):null;
 if($snapshot){if(($snapshot['version']??0)!==1||!is_array($snapshot['brand']??null))throw new RuntimeException('Invalid saved invoice snapshot.');$brand=$snapshot['brand'];$layout=invoice_layout_validate($snapshot['layout']??null);}
 else{$brand=invoice_effective_brand(shop_id());$layout=invoice_layout($brand);}
 $context=currency_saved_context('sales',$id);currency_display_override($context['code'],$context['rate'],$context['mode']);$sale['currency_code']=$context['code'];
 $q=$pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM sales_returns WHERE sale_id=? AND shop_id=? AND status IN ('posted','partial')");$q->execute([$id,shop_id()]);$sale['returned_amount']=$q->fetchColumn();
}
if(isset($_GET['format'])){if(!is_string($_GET['format'])||!in_array($_GET['format'],['a5','a4','thermal','doc'],true)){http_response_code(400);exit('Invalid print format.');}$brand['invoice_format']=$_GET['format'];}
$word=($_GET['export']??'')==='doc'||(($brand['invoice_format']??'')==='doc'&&isset($_GET['download']));
if($word){domain_require('sales.export');header('Content-Type: application/msword; charset=UTF-8');header('Content-Disposition: attachment; filename="invoice-'.preg_replace('/[^a-zA-Z0-9_-]/','',$sale['invoice_no']).'.doc"');}
else header('Content-Type: text/html; charset=UTF-8');
invoice_render($sale,$items,$brand,$layout,$sample,$word);
