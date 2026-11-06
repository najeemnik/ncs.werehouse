<?php
require_once __DIR__.'/currency.php';
function inventory_has_history() {
    global $pdo;
    return (bool)$pdo->query('SELECT id FROM inventory_purchase_items WHERE '.shop_sql().' LIMIT 1')->fetchColumn()
        || (bool)$pdo->query("SELECT id FROM currency_transactions WHERE entity_type='stock' AND ".shop_sql()." LIMIT 1")->fetchColumn();
}
function inventory_quantity($value,$allowZero=false) {
    $q=dec_round($value,3);
    if(dec_units($q,3)<($allowZero?0:1) || dec_units($q,3)>1000000000000 || dec_round($value,6)!==dec_round($q,6)) throw new InvalidArgumentException(__t('Quantity must be positive, with at most 3 decimal places.','تعداد باید مثبت و حداکثر سه رقم اعشار داشته باشد.','مقدار باید مثبت او تر درې اعشاري ځایونو وي.'));
    return $q;
}
function inventory_unit($unit) {
    $unit=trim((string)$unit) ?: 'unit';
    if(!preg_match('~^[\p{L}\p{N} ._/-]{1,24}$~uD',$unit)) throw new InvalidArgumentException('Invalid measurement unit.');
    return strtolower($unit);
}
function inventory_identity(array $item) {
    return hash('sha256',json_encode([mb_strtolower(trim($item['name']),'UTF-8'),(int)($item['category_id']??0),trim($item['specifications']??''),trim($item['barcode']??''),strtoupper(trim($item['sku']??'')),$item['unit'],(int)($item['partner_id']??0),(int)($item['mudaraba_id']??0),$item['currency_code']??'AFN'],JSON_UNESCAPED_UNICODE));
}
function inventory_value(array $row) {
    return $row['inventory_value']!==null ? dec_round($row['inventory_value']) : dec_mul(dec_add($row['purchase_price'],$row['expenses'],6),$row['quantity']);
}
function inventory_cost_for_quantity(array $row,$qty) {
    if(dec_units($row['quantity'],3)<=0) return '0.00';
    if(dec_units($qty,3)===dec_units($row['quantity'],3)) return inventory_value($row);
    return dec_div(dec_mul(inventory_value($row),$qty,5),$row['quantity']);
}
/** Find an existing cost position only within the same currency. */
function inventory_find_position(array $line,$lock='') {
    global $pdo;$identity=inventory_identity($line);
    $s=$pdo->prepare('SELECT * FROM stock WHERE currency_code=? AND '.shop_sql().' AND (identity_key=? OR (sku IS NOT NULL AND sku=?))'.$lock);
    $s->execute([$line['currency_code'],$identity,$line['sku']]);$matches=$s->fetchAll();
    if(count($matches)>1)throw new InvalidArgumentException('SKU and product identity refer to different items. Select the existing product.');
    $row=$matches[0]??null;
    if($row&&inventory_identity($row)!==$identity)throw new InvalidArgumentException('This SKU belongs to another product or ownership account.');
    if(!$row){
        $s=$pdo->prepare("SELECT * FROM stock WHERE name=? AND COALESCE(category_id,0)=? AND COALESCE(specifications,'')=? AND COALESCE(barcode,'')=? AND unit=? AND COALESCE(partner_id,0)=? AND COALESCE(mudaraba_id,0)=? AND currency_code=? AND ".shop_sql().($line['sku']?' AND (sku IS NULL OR sku=?)':'').$lock);
        $values=[$line['name'],$line['category_id']??0,$line['specifications'],$line['barcode'],$line['unit'],$line['partner_id']??0,$line['mudaraba_id']??0,$line['currency_code']];if($line['sku'])$values[]=$line['sku'];
        foreach($values as $key=>$value)$s->bindValue($key+1,$value,in_array($key,[1,5,6],true)?PDO::PARAM_INT:PDO::PARAM_STR);
        $s->execute();$matches=$s->fetchAll();
        if(count($matches)>1)throw new InvalidArgumentException('Several existing items match. Select the exact existing inventory item.');
        $row=$matches[0]??null;
    }
    return $row;
}
/** Restocking uses a weighted average; purchase line snapshots remain immutable. */
function inventory_save_purchase(array $post,$userId,$updateContractCapital=true) {
    global $pdo;
    // Reject truncated browser submissions instead of silently dropping lot lines.
    if(!empty($post['purchase_payload_expected']) && ($post['purchase_payload_complete']??'')!=='1')throw new InvalidArgumentException('Incomplete purchase form. Reduce the number of lines and try again.');
    $mode=$post['purchase_mode']??'single';
    if(!in_array($mode,['single','lot'],true)) throw new InvalidArgumentException('Invalid purchase mode.');
    $ctx=currency_context($post);
    $date=trim($post['purchase_date']??date('Y-m-d'));
    $dt=DateTime::createFromFormat('!Y-m-d',$date);
    if(!$dt || $dt->format('Y-m-d')!==$date) throw new InvalidArgumentException('Invalid purchase date.');
    $supplier=trim($post['supplier_name']??''); $notes=trim($post['notes']??'');
    $reference=trim($post['lot_reference']??'') ?: 'PUR-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
    if(strlen($reference)>100 || mb_strlen($supplier)>255) throw new InvalidArgumentException('Reference or supplier name is too long.');
    $request=trim($post['purchase_request_key']??'') ?: bin2hex(random_bytes(16));
    if(!preg_match('/^[a-zA-Z0-9_-]{16,64}$/D',$request)) throw new InvalidArgumentException('Invalid purchase request. Refresh the form.');
    $rawLines=$mode==='lot'?($post['items']??[]):[$post];
    if(!is_array($rawLines) || count($rawLines)<1 || count($rawLines)>100) throw new InvalidArgumentException('Enter between 1 and 100 purchase lines.');
    $lines=[]; $weights=[]; $qtyWeights=[]; $lineAmounts=[]; $hasValue=false;
    foreach(array_values($rawLines) as $i=>$raw) {
        if(!is_array($raw)) throw new InvalidArgumentException('Invalid purchase line.');
        $q=inventory_quantity($raw['quantity']??0);
        $price=dec_round($raw['purchase_price']??0,6);
        $sale=isset($raw['sale_price']) && $raw['sale_price']!==''?dec_round($raw['sale_price'],6):null;
        if(dec_units($price,6)<0 || ($sale!==null && dec_units($sale,6)<0)) throw new InvalidArgumentException('Prices cannot be negative.');
        $line=[
            'stock_id'=>(int)($raw['stock_id']??0),'name'=>trim($raw['name']??''),'category_id'=>!empty($raw['category_id'])?(int)$raw['category_id']:null,
            'quantity'=>$q,'unit'=>inventory_unit($raw['unit']??'unit'),'purchase_price'=>$price,'sale_price'=>$sale,
            'sku'=>strtoupper(trim($raw['sku']??'')) ?: null,'barcode'=>trim($raw['barcode']??''),'specifications'=>trim($raw['specifications']??''),
            'currency_code'=>$ctx['code'],'sale_currency_code'=>currency_context(['currency_code'=>$raw['sale_currency_code']??$post['sale_currency_code']??$ctx['code']])['code'],
            'country_of_origin'=>trim($post['country_of_origin']??''),'warehouse_location'=>trim($raw['warehouse_location']??$post['warehouse_location']??''),
            'partner_id'=>!empty($post['partner_id'])?(int)$post['partner_id']:null,'mudaraba_id'=>!empty($post['mudaraba_id'])?(int)$post['mudaraba_id']:null,'notes'=>trim($raw['notes']??'')
        ];
        if((!$line['stock_id'] && $line['name']==='') || mb_strlen($line['name'])>255 || strlen($line['sku']??'')>128 || strlen($line['barcode'])>128) throw new InvalidArgumentException('Enter a valid product name / SKU / serial number.');
        $lines[]=$line; $weights[]=ltrim(str_replace('.','',dec_mul($price,$q,9)),'0')?:'0'; $qtyWeights[]=dec_units($q,3);
        $lineAmounts[]=dec_units(dec_mul($price,$q)); if(dec_units($price,6)>0)$hasValue=true;
    }
    $purchase=$mode==='lot' && isset($post['lot_purchase_cost']) && $post['lot_purchase_cost']!=='' ? dec_round($post['lot_purchase_cost']) : dec_format_digits((string)array_sum($lineAmounts),2);
    $expense=$mode==='lot'?dec_round($post['additional_expenses']??0):dec_mul($post['expenses']??0,$lines[0]['quantity']);
    if(dec_units($purchase)<0 || dec_units($expense)<0) throw new InvalidArgumentException('Purchase cost and expenses cannot be negative.');
    $allocation=$hasValue?'value':'quantity';
    $allocationWeights=$allocation==='value'?$weights:$qtyWeights;
    $originalPurchase=money_allocate($purchase,$allocationWeights);
    $originalExpense=money_allocate($expense,$allocationWeights);
    $basePurchase=currency_to_ledger($purchase,$ctx['rate']); $baseExpense=currency_to_ledger($expense,$ctx['rate']);
    $allocatedPurchase=money_allocate($basePurchase,$allocationWeights);
    $allocatedExpense=money_allocate($baseExpense,$allocationWeights);
    $hash=hash('sha256',json_encode([$mode,trim($post['lot_reference']??''),$supplier,$date,$ctx['code'],$ctx['rate'],$purchase,$expense,$notes,$lines],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    $old=$pdo->prepare('SELECT id,reference,content_hash,base_total_amount FROM inventory_purchases WHERE request_key=? AND '.shop_sql()); $old->execute([$request]); $existing=$old->fetch();
    if($existing) { if($existing['content_hash']!==$hash) throw new InvalidArgumentException('This request was already saved with different values. Open a new purchase form.'); $existing['already_saved']=true; return $existing; }
    $ownsTransaction=!$pdo->inTransaction();
    if($ownsTransaction)$pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO inventory_purchases (reference,mode,supplier,purchase_date,currency_code,exchange_rate,purchase_amount,expense_amount,total_amount,base_purchase_amount,base_expense_amount,base_total_amount,allocation_method,request_key,content_hash,notes,created_by,accounting_mode,ledger_currency,shop_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'.shop_id().')')->execute([$reference,$mode,$supplier,$date,$ctx['code'],$ctx['rate'],$purchase,$expense,dec_add($purchase,$expense),$basePurchase,$baseExpense,dec_add($basePurchase,$baseExpense),$allocation,$request,$hash,$notes,$userId,'native',$ctx['code']]);
        $purchaseId=(int)$pdo->lastInsertId(); $seen=[];
        $lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        foreach($lines as $i=>$line) {
            $row=null;$quoteSource=null;
            if($line['stock_id']) {
                $s=$pdo->prepare('SELECT * FROM stock WHERE id=? AND '.shop_sql().$lock); $s->execute([$line['stock_id']]); $row=$s->fetch();
                if(!$row) throw new InvalidArgumentException('Selected inventory item no longer exists.');
                // Restocking must not silently transfer partnership / consignment ownership.
                if($line['partner_id']!==(empty($row['partner_id'])?null:(int)$row['partner_id']) || $line['mudaraba_id']!==(empty($row['mudaraba_id'])?null:(int)$row['mudaraba_id'])) throw new InvalidArgumentException('Use the existing item\'s partner and Mudaraba ownership when restocking.');
                if($line['unit']!==($row['unit']??'unit')) throw new InvalidArgumentException('Measurement unit must match the existing item.');
                $line['name']=$row['name']; $line['category_id']=$row['category_id']; $line['specifications']=$row['specifications']??''; $line['barcode']=$row['barcode']??'';
                if($line['sku'] && !empty($row['sku']) && $line['sku']!==$row['sku']) throw new InvalidArgumentException('SKU does not match the selected item.');
                $line['sku']=$line['sku']?:($row['sku']??null);
                $quoteSource=$row;
                if($row['currency_code']!==$ctx['code'])$row=inventory_find_position($line,$lock);
            } else {
                $row=inventory_find_position($line,$lock);
            }
            if($row && !$line['sku']) $line['sku']=$row['sku']??null;
            foreach(['categories'=>'category_id','partners'=>'partner_id','mudaraba_contracts'=>'mudaraba_id'] as $table=>$field) if(!empty($line[$field])) {
                $s=$pdo->prepare("SELECT id FROM {$table} WHERE id=? AND ".shop_sql());$s->execute([$line[$field]]);
                if(!$s->fetchColumn()) throw new InvalidArgumentException('Invalid '.$field.'.');
                if($table==='mudaraba_contracts'){$contract=partner_contract((int)$line[$field],true);if($contract['status']==='settled')throw new InvalidArgumentException(__t('Use a new contract or reopen it through a reviewed funding document.','از قرارداد تازه استفاده کنید یا آن را از سند تأییدشدهٔ دریافت سرمایه دوباره فعال کنید.','نوی تړون وکاروئ یا یې په تایید شوي سند بیا فعال کړئ.'));}
            }
            $catName=''; if($line['category_id']){$s=$pdo->prepare('SELECT name FROM categories WHERE id=? AND '.shop_sql());$s->execute([$line['category_id']]);$catName=$s->fetchColumn();}
            $lineValue=dec_add($allocatedPurchase[$i],$allocatedExpense[$i]);
            $qty=dec_add($row['quantity']??0,$line['quantity'],3);
            $oldValue=$row?inventory_value($row):'0.00'; $value=dec_add($oldValue,$lineValue);
            // Preserve total acquisition value, including cent-level allocation remainders.
            $oldExpense=$row?dec_mul($row['expenses'],$row['quantity']):'0.00';
            $unitExpense=dec_div(dec_add($oldExpense,$allocatedExpense[$i]),$qty,6);
            $unitPurchase=dec_sub(dec_div($value,$qty,6),$unitExpense,6);
            $quote=$row??$quoteSource;
            if($line['sale_price']===null&&$quote)$line['sale_currency_code']=$quote['sale_currency_code'];
            $sale=$line['sale_price']!==null?currency_to_ledger($line['sale_price'],$ctx['rate'],6):($quote['sale_price']??'0');
            $identity=inventory_identity($line);
            if($row) {
                $stockId=(int)$row['id'];
                $pdo->prepare('UPDATE stock SET quantity=?,purchase_price=?,expenses=?,sale_price=?,capital_amount=?,inventory_value=?,unit=?,sku=?,identity_key=?,sale_currency_code=? WHERE id=? AND '.shop_sql())->execute([$qty,$unitPurchase,$unitExpense,$sale,$value,$value,$line['unit'],$line['sku']?:($row['sku']??null),$identity,$line['sale_currency_code'],$stockId]);
            } else {
                $pdo->prepare('INSERT INTO stock (name,category_id,specifications,quantity,purchase_price,sale_price,expenses,barcode,country_of_origin,supplier_name,warehouse_location,capital_amount,inventory_value,partner_id,mudaraba_id,purchase_date,unit,sku,identity_key,created_at,currency_code,sale_currency_code,shop_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'.shop_id().')')->execute([$line['name'],$line['category_id'],$line['specifications'],$qty,$unitPurchase,$sale,$unitExpense,$line['barcode'],$line['country_of_origin'],$supplier,$line['warehouse_location'],$value,$value,$line['partner_id'],$line['mudaraba_id'],$date,$line['unit'],$line['sku'],$identity,date('Y-m-d H:i:s'),$ctx['code'],$line['sale_currency_code']]);
                $stockId=(int)$pdo->lastInsertId();
            }
            if(isset($seen[$stockId])) throw new InvalidArgumentException('The same product appears more than once. Combine its quantities in one line.');
            $seen[$stockId]=true;
            if($line['mudaraba_id']) {
                if($updateContractCapital)$pdo->prepare('UPDATE mudaraba_contracts SET capital_amount=capital_amount+? WHERE id=? AND currency_code=? AND '.shop_sql())->execute([$lineValue,$line['mudaraba_id'],$ctx['code']]);
                currency_record('mudaraba_contracts',$line['mudaraba_id'],$ctx,['capital_amount'=>$lineValue],'purchase');
            }
            $pdo->prepare('INSERT INTO inventory_purchase_items (purchase_id,stock_id,product_name,category_name,quantity,unit,sku,serial_number,purchase_price_original,original_purchase_amount,original_expense_amount,base_purchase_amount,base_expense_amount,base_total_amount,allocated_unit_cost,selling_price_original,notes,selling_currency_code,partner_id,mudaraba_id,shop_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'.shop_id().')')->execute([$purchaseId,$stockId,$line['name'],$catName,$line['quantity'],$line['unit'],$line['sku'],$line['barcode'],$line['purchase_price'],$originalPurchase[$i],$originalExpense[$i],$allocatedPurchase[$i],$allocatedExpense[$i],$lineValue,dec_div($lineValue,$line['quantity'],6),$line['sale_price']??currency_from_ledger($sale,$ctx['rate'],6),$line['notes'],$line['sale_currency_code'],$line['partner_id'],$line['mudaraba_id']]);
            inventory_movement($stockId,'purchase',$purchaseId,$line['quantity'],$lineValue,$line['currency_code']);
            currency_record('stock',$stockId,$ctx,['purchase_amount'=>$allocatedPurchase[$i],'expense_amount'=>$allocatedExpense[$i]],$row?'purchase':'create',['purchase_amount'=>$originalPurchase[$i],'expense_amount'=>$originalExpense[$i],'sale_price'=>$line['sale_price']??currency_from_ledger($sale,$ctx['rate'],6)]);
        }
        currency_record('inventory_purchase',$purchaseId,$ctx,['purchase_amount'=>$basePurchase,'expense_amount'=>$baseExpense,'total_amount'=>dec_add($basePurchase,$baseExpense)],'create',['purchase_amount'=>$purchase,'expense_amount'=>$expense,'total_amount'=>dec_add($purchase,$expense)]);
        if($ownsTransaction)$pdo->commit();
        log_activity($userId,'Inventory purchase',$reference.' ('.$mode.', '.$ctx['code'].')');
        return ['id'=>$purchaseId,'reference'=>$reference,'base_total_amount'=>dec_add($basePurchase,$baseExpense)];
    } catch(Throwable $e) {
        if($ownsTransaction && $pdo->inTransaction())$pdo->rollBack();
        if($e instanceof PDOException && in_array((string)$e->getCode(),['23000','23505'],true)) throw new InvalidArgumentException('Duplicate reference / SKU or simultaneous purchase. Check purchase history before retrying.');
        throw $e;
    }
}
