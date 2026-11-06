<?php
/** Execute the real POS handler twice in separate PHP requests against one isolated DB. */
$mode=getenv('NSC_SALE_MODE')?:'setup';$statePath=getenv('NSC_SALE_STATE');if(!$statePath)throw new RuntimeException('NSC_SALE_STATE is required.');
if($mode==='setup'){
    require __DIR__.'/security.php';
    $pdo->prepare("INSERT INTO stock (name,quantity,purchase_price,expenses,sale_price,capital_amount,inventory_value,currency_code,sale_currency_code,unit,shop_id) VALUES ('Idempotency synthetic','5.000','10.000000','0.000000','15.000000','50.00','50.00','AFN','AFN','unit',?)")->execute([$a1]);
    $stock=(int)$pdo->lastInsertId();security_establish_session($ou);
    $items=[['id'=>$stock,'qty'=>'2.000','price'=>'15.00','custom_fields'=>[]]];
    $post=['action'=>'create_sale','csrf_token'=>csrf_token(),'sale_request_key'=>'sale-'.bin2hex(random_bytes(8)),'invoice_no'=>'NSC-IDEMP-'.bin2hex(random_bytes(4)),'customer_name'=>'Synthetic walk-in','customer_phone'=>'','payment_type'=>'credit','paid_amount'=>'0.00','payment_currency_code'=>'AFN','currency_code'=>'AFN','cash_account_id'=>'','notes'=>'Synthetic retry test','items_json'=>json_encode($items,JSON_THROW_ON_ERROR),'invoice_design_revision'=>hash('sha256',json_encode(invoice_snapshot($a1),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))];
    file_put_contents($statePath,json_encode(['session'=>session_id(),'branch'=>$a1,'stock'=>$stock,'post'=>$post],JSON_THROW_ON_ERROR));
}else{
    $state=json_decode(file_get_contents($statePath),true,32,JSON_THROW_ON_ERROR);session_id($state['session']);require __DIR__.'/bootstrap.php';$post=$state['post'];
}
if($mode==='verify'){
    $count=(int)$pdo->query("SELECT COUNT(*) FROM sales WHERE invoice_no=".$pdo->quote($post['invoice_no']))->fetchColumn();check($count===1,'real POS retry creates exactly one invoice');
    $q=$pdo->prepare('SELECT quantity,inventory_value FROM stock WHERE id=? AND shop_id=?');$q->execute([$state['stock'],$state['branch']]);$row=$q->fetch();check(dec_round($row['quantity'],3)==='3.000'&&dec_round($row['inventory_value'])==='30.00','real POS retry decrements stock and value exactly once');
    $q=$pdo->prepare("SELECT COUNT(*) FROM journal_headers h JOIN sales s ON s.id=CAST(h.source_id AS INTEGER) WHERE h.source_type='sale' AND s.invoice_no=?");$q->execute([$post['invoice_no']]);check((int)$q->fetchColumn()===1,'real POS retry posts one accounting document');
    check((int)$pdo->query("SELECT COUNT(*) FROM inventory_movements WHERE document_type='sale' AND document_id=(SELECT id FROM sales WHERE invoice_no=".$pdo->quote($post['invoice_no']).')')->fetchColumn()===1,'real POS retry writes one stock movement');
    echo "Real POS idempotency scenarios completed.\n";exit;
}
if($mode==='altered')$post['paid_amount']='0.01';
$_SERVER['SCRIPT_NAME']='/modules/sales.php';$_SERVER['REQUEST_METHOD']='POST';$_SERVER['REQUEST_URI']='/modules/sales.php';$_POST=$post;
require __DIR__.'/../public/modules/sales.php';
