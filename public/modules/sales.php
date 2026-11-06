<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Multi-Item Sales POS Module (سیستم صدور بل چند جنسی با سرچ سریع)
 * URL: http://localhost/nikxor/modules/sales.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();
$currencyContext = currency_prepare_request(['create_sale'=>['paid_amount'=>2]]);

function pos_text_value(string $name,string $fallback=''): string {$value=$_POST[$name]??$fallback;return is_string($value)&&mb_check_encoding($value,'UTF-8')?trim($value):$fallback;}
$invoiceSnapshot=invoice_snapshot(shop_id());
$invoiceDesignRevision=hash('sha256',json_encode($invoiceSnapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
$invoiceCustomColumns=array_values(array_filter($invoiceSnapshot['layout']['columns'],fn($c)=>$c['source']==='custom'));
$error = '';
$success = '';

// Generate next invoice number
$nextInvNo = generate_invoice_number();

// Handle Multi-Item Sale Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_sale') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست، لطفاً مجدداً صفحه را بارگذاری و تلاش نمایید.';
    } else {
        $invoiceNo     = pos_text_value('invoice_no',$nextInvNo);
        $customerName  = pos_text_value('customer_name');
        $customerPhone = pos_text_value('customer_phone');
        $paymentType   = pos_text_value('payment_type','نقد');
        $paymentType=['cash'=>'نقد','credit'=>'قرض','installment'=>'قسط وار'][$paymentType]??$paymentType;
        $paidAmount    = dec_round($_POST['paid_amount'] ?? '0');
        $notes         = pos_text_value('notes');
        $itemsJson     = $_POST['items_json'] ?? '';

        if(!is_string($_POST['invoice_design_revision']??null)||!hash_equals($invoiceDesignRevision,$_POST['invoice_design_revision']))$error=__t('The invoice design changed or this form is outdated. Review the current fields and submit again.','دیزاین بل تغییر کرده یا فورم قدیمی است؛ خانه‌های فعلی را بررسی و دوباره ثبت کنید.','د بل بڼه بدله شوې یا فورمه پخوانۍ ده؛ اوسني خانې وګورئ او بیا یې ثبت کړئ.');
        if(!in_array($paymentType,['نقد','قرض','قسط وار'],true))$error=__t('Choose a valid payment type.','نوع معتبر پرداخت را انتخاب کنید.','د تادیې سم ډول وټاکئ.');
        if (empty($customerName)) {
            $customerName = 'مشتری آزاد';
        }

        $items = is_string($itemsJson)&&strlen($itemsJson)<=262144?json_decode($itemsJson,true,16):null;

        if($error!==''){
            // Invalid payment modes never create financial records.
        } elseif (!is_array($items) || count($items) === 0) {
            $error = 'لطفاً حداقل یک قلم جنس را از طریق سرچ سریع به بل اضافه کنید.';
        } elseif (count($items) > 30) {
            $error = 'حداکثر تعداد اقلام مجاز در یک بل ۳۰ قلم می‌باشد.';
        } else {
            try {
                $saleRequestKey=sale_request_key(pos_text_value('sale_request_key'));
                $saleContentHash=sale_request_hash([
                    'invoice_no'=>$invoiceNo,'customer_name'=>$customerName,'customer_phone'=>$customerPhone,
                    'payment_type'=>$paymentType,'paid_amount'=>$paidAmount,'notes'=>$notes,'items'=>$items,
                    'currency'=>$currencyContext['code'],'payment_currency'=>pos_text_value('payment_currency_code',$currencyContext['code']),
                    'cash_account'=>pos_text_value('cash_account_id'),'design_revision'=>$invoiceDesignRevision
                ]);
                if($existingSale=sale_request_existing(shop_id(),$saleRequestKey,$saleContentHash))redirect(BASE_URL.'print/invoice.php?id='.$existingSale);
                $pdo->beginTransaction();
                if($existingSale=sale_request_existing(shop_id(),$saleRequestKey,$saleContentHash,true)){$pdo->rollBack();redirect(BASE_URL.'print/invoice.php?id='.$existingSale);}

                $grandTotal = '0.00';
                $grandProfit = '0.00'; $profitKnown=true;
                $originalTotal = '0.00';
                $processedItems = []; $seenProducts=[]; $weights=[];
                $stockLock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
                foreach ($items as $idx => $it) {
                    $pId=(int)($it['id']??0);
                    $qty=inventory_quantity($it['qty']??0);
                    $originalPrice=dec_round($it['price']??0,6);
                    if(!empty($it['needs_price']))throw new InvalidArgumentException(__t('Enter the selling price in the selected currency.','قیمت فروش را به ارز انتخابی وارد کنید.','د پلور بیه په ټاکلي اسعارو ولیکئ.'));
                    if($pId<=0 || dec_units($originalPrice,6)<0 || isset($seenProducts[$pId])) throw new InvalidArgumentException(__t('Invalid or duplicate product line.','سطر جنس نامعتبر یا تکراری است.','د توکي کرښه ناسمه یا تکراري ده.'));
                    $seenProducts[$pId]=true;
                    $pStmt=$pdo->prepare('SELECT * FROM stock WHERE id=? AND shop_id='.(int)shop_id().$stockLock);$pStmt->execute([$pId]);$prod=$pStmt->fetch();
                    if(!$prod || dec_units(dec_sub($prod['quantity'],$prod['reserved_qty'],3),3)<dec_units($qty,3)) throw new InvalidArgumentException(__t('Insufficient stock for line ','موجودی ناکافی برای سطر ','د کرښې لپاره موجودي کمه ده: ').($idx+1));
                    $originalLine=dec_mul($originalPrice,$qty);
                    $originalTotal=dec_add($originalTotal,$originalLine);$weights[]=dec_units($originalLine);
                    $cost=inventory_cost_for_quantity($prod,$qty);
                    $processedItems[]=[
                        'stock_id'=>$pId,'name'=>$prod['name'],'specs'=>$prod['specifications']??'',
                        'invoice_fields'=>json_encode(invoice_custom_values($it['custom_fields']??[],$invoiceSnapshot['layout']),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                        'qty'=>$qty,'unit'=>$prod['unit']??'unit','buy_price'=>$prod['purchase_price'],'unit_exp'=>$prod['expenses'],
                        'sale_price'=>currency_to_ledger($originalPrice,$currencyContext['rate'],6),
                        'original_price'=>$originalPrice,'original_total'=>$originalLine,'cost'=>$cost,
                        'cost_currency'=>$prod['currency_code'],'profit_known'=>$prod['currency_code']===$currencyContext['code'],
                        'new_qty'=>dec_sub($prod['quantity'],$qty,3),'new_value'=>dec_sub(inventory_value($prod),$cost)
                    ];
                }
                $grandTotal=currency_to_ledger($originalTotal,$currencyContext['rate']);
                $lineTotals=money_allocate($grandTotal,array_sum($weights)>0?$weights:array_fill(0,count($weights),1));
                foreach($processedItems as $i=>&$line) {
                    $line['total']=$lineTotals[$i];$line['profit']=$line['profit_known']?dec_sub($line['total'],$line['cost']):'0.00';
                    $profitKnown=$profitKnown&&$line['profit_known'];
                    $grandProfit=dec_add($grandProfit,$line['profit']);
                }
                unset($line);

                $currencyContext['profit_known']=$profitKnown;
                $paymentContext=currency_context(['currency_code'=>$_POST['payment_currency_code']??$currencyContext['code']]);
                $samePaymentCurrency=$paymentContext['code']===$currencyContext['code'];
                $receivedAmount=dec_round($paidAmount);
                // A foreign receipt is recorded separately, never deducted from this invoice.
                if(!$samePaymentCurrency){$paidAmount='0.00';$remainingAmount=$grandTotal;}
                elseif ($paymentType === 'نقد') {
                    $paidAmount = $grandTotal;
                    $remainingAmount = 0.0;
                } else {
                    if (dec_units($paidAmount) > dec_units($grandTotal)) {
                        throw new InvalidArgumentException(__t('The received amount exceeds the invoice total. Check the amount before completing the sale.','مبلغ دریافت‌شده از مجموع بل بیشتر است؛ پیش از تکمیل فروش مبلغ را بررسی کنید.','ترلاسه شوې اندازه د بل له ټول څخه ډېره ده؛ اندازه وګورئ.'));
                    }
                    $remainingAmount = dec_sub($grandTotal,$paidAmount);
                }

                if($samePaymentCurrency)$receivedAmount=$paidAmount;
                $originalPaid=dec_round($paidAmount);
                $originalRemaining=dec_sub($originalTotal,$originalPaid);
                $exactNow = date('Y-m-d H:i:s');

                // 1. Insert master sales record with exact timestamp
                $saleStmt = $pdo->prepare("INSERT INTO sales
                    (invoice_no, customer_name, customer_phone, total_amount, paid_amount, remaining_amount, profit, payment_type, notes, created_by, created_at, shop_id, sale_request_key, sale_content_hash)
                    VALUES (:inv, :cname, :cphone, :tot, :paid, :rem, :prof, :ptype, :notes, :uid, :cdate, " . shop_id() . ", :request_key, :content_hash)");

                $saleStmt->execute([
                    ':inv'   => $invoiceNo,
                    ':cname' => $customerName,
                    ':cphone'=> $customerPhone,
                    ':tot'   => $grandTotal,
                    ':paid'  => $paidAmount,
                    ':rem'   => $remainingAmount,
                    ':prof'  => $grandProfit,
                    ':ptype' => $paymentType,
                    ':notes' => $notes,
                    ':uid'   => $currentUser['id'],
                    ':cdate' => $exactNow,
                    ':request_key' => $saleRequestKey,
                    ':content_hash' => $saleContentHash
                ]);

                $saleId = $pdo->lastInsertId();
                $pdo->prepare('UPDATE sales SET invoice_snapshot=?,verification_nonce=? WHERE id=? AND shop_id=?')->execute([json_encode($invoiceSnapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),bin2hex(random_bytes(16)),$saleId,shop_id()]);

                // 2. Insert items and decrement stock
                $insItemStmt = $pdo->prepare("INSERT INTO sale_items
                    (sale_id, stock_id, product_name, specifications, quantity, unit_purchase_price, unit_expense, unit_sale_price, total_price, profit, unit, cost_amount, cost_currency_code, profit_known, invoice_fields, shop_id)
                    VALUES (:sid, :stock_id, :pname, :specs, :qty, :buy, :exp, :sprice, :tot, :prof, :unit, :cost, :cost_currency, :known, :invoice_fields, " . shop_id() . ")");

                $updStockStmt = $pdo->prepare("UPDATE stock SET quantity = :new_qty, inventory_value = :new_value, capital_amount = :new_value2 WHERE id = :id AND " . shop_sql());

                foreach ($processedItems as $pItem) {
                    $insItemStmt->execute([
                        ':sid'      => $saleId,
                        ':stock_id' => $pItem['stock_id'],
                        ':pname'    => $pItem['name'],
                        ':specs'    => $pItem['specs'],
                        ':qty'      => $pItem['qty'],
                        ':buy'      => $pItem['buy_price'],
                        ':exp'      => $pItem['unit_exp'],
                        ':sprice'   => $pItem['sale_price'],
                        ':tot'      => $pItem['total'],
                        ':prof'     => $pItem['profit'],
                        ':unit'     => $pItem['unit'],
                        ':cost'     => $pItem['cost'],
                        ':cost_currency'=>$pItem['cost_currency'],':known'=>$pItem['profit_known']?1:0,':invoice_fields'=>$pItem['invoice_fields']
                    ]);

                    currency_record('sale_items',(int)$pdo->lastInsertId(),array_merge($currencyContext,['profit_known'=>$pItem['profit_known'],'cost_currency'=>$pItem['cost_currency']]),['unit_sale_price'=>$pItem['sale_price'],'total_price'=>$pItem['total']],'create',['unit_sale_price'=>$pItem['original_price'],'total_price'=>$pItem['original_total']]);
                    $updStockStmt->execute([
                        ':new_qty' => $pItem['new_qty'],
                        ':new_value' => $pItem['new_value'],
                        ':new_value2' => $pItem['new_value'],
                        ':id'   => $pItem['stock_id']
                    ]);

                    inventory_movement((int)$pItem['stock_id'],'sale',$saleId,'-'.$pItem['qty'],'-'.$pItem['cost'],$pItem['cost_currency']);
                    // Automatically accumulate sales & profit in Mudaraba contract if linked
                    if (!empty($pItem['stock_id'])) {
                        $mudCheck = $pdo->prepare("SELECT mudaraba_id FROM stock WHERE id = :id AND " . shop_sql());
                        $mudCheck->execute([':id' => $pItem['stock_id']]);
                        $linkedMudarabaId = $mudCheck->fetchColumn();
                        if ($linkedMudarabaId) {
                            $updMud = $pdo->prepare("UPDATE mudaraba_contracts
                                SET total_sales_amount = total_sales_amount + :sales,
                                    total_profit_amount = total_profit_amount + :profit,
                                    profit_known = CASE WHEN :known = 0 THEN 0 ELSE profit_known END
                                WHERE id = :mid AND currency_code = :code AND " . shop_sql());
                            $updMud->execute([
                                ':sales'  => $pItem['total'],
                                ':profit' => $pItem['profit'],
                                ':mid'    => $linkedMudarabaId,':code'=>$currencyContext['code'],':known'=>$pItem['profit_known']?1:0
                            ]);
                            if(!$pItem['profit_known'])$pdo->prepare('UPDATE mudaraba_contracts SET profit_known=0 WHERE id=? AND (currency_code=? OR currency_code=?) AND shop_id=?')->execute([$linkedMudarabaId,$currencyContext['code'],$pItem['cost_currency'],shop_id()]);
                            currency_record('mudaraba_contracts',$linkedMudarabaId,array_merge($currencyContext,['profit_known'=>$pItem['profit_known'],'cost_currency'=>$pItem['cost_currency']]),['add_sales'=>$pItem['total'],'add_profit'=>$pItem['profit']],'sale');
                        }
                    }
                }

                // 3. Create debt if customer has remaining balance
                if ($remainingAmount > 0) {
                    $debtStmt = $pdo->prepare("INSERT INTO debts
                        (sale_id, customer_name, customer_phone, total_amount, paid_amount, remaining_amount, status, notes, debt_type, person_type, created_at, shop_id)
                        VALUES (:sid, :cname, :cphone, :tot, :paid, :rem, 'باز', :notes, 'receivable', 'customer', :cdate, " . shop_id() . ")");

                    $debtStmt->execute([
                        ':sid'    => $saleId,
                        ':cname'  => $customerName,
                        ':cphone' => $customerPhone,
                        ':tot'    => $grandTotal,
                        ':paid'   => $paidAmount,
                        ':rem'    => $remainingAmount,
                        ':notes'  => ($paymentType === 'قسط وار' ? 'فروش قسطی - ' : 'فروش قرضه - ') . "بل چند جنسی {$invoiceNo}",
                        ':cdate'  => $exactNow
                    ]);

                    $debtId = $pdo->lastInsertId();
                    currency_record('debts',$debtId,$currencyContext,['total_amount'=>$grandTotal,'paid_amount'=>$paidAmount,'remaining_amount'=>$remainingAmount],'create',['total_amount'=>$originalTotal,'paid_amount'=>$originalPaid,'remaining_amount'=>$originalRemaining]);

                    if ($receivedAmount > 0) {
                        $instStmt = $pdo->prepare("INSERT INTO installments
                            (debt_id, amount, payment_date, notes, created_by, shop_id)
                            VALUES (:did, :amt, :pdate, :notes, :uid, " . shop_id() . ")");
                        $instStmt->execute([
                            ':did'   => $debtId,
                            ':amt'   => $receivedAmount,
                            ':pdate' => date('Y-m-d'),
                            ':notes' => 'پیش پرداخت نقد بل ' . $invoiceNo,
                            ':uid'   => $currentUser['id']
                        ]);
                        $receiptId=(int)$pdo->lastInsertId();
                        $pdo->prepare('UPDATE installments SET applied_to_balance=?,split_known=? WHERE id=? AND shop_id=?')->execute([$samePaymentCurrency?1:0,0,$receiptId,shop_id()]);
                        currency_record('installments',$receiptId,$paymentContext,['amount'=>$receivedAmount]);
                    }
                }

                currency_record('sales',$saleId,$currencyContext,['total_amount'=>$grandTotal,'paid_amount'=>$paidAmount,'remaining_amount'=>$remainingAmount,'profit'=>$grandProfit],'create',['total_amount'=>$originalTotal,'paid_amount'=>$originalPaid,'remaining_amount'=>$originalRemaining]);
                $pdo->commit();
                log_activity($currentUser['id'], 'فروش چند جنسی', "صدور بل {$invoiceNo} شامل " . count($processedItems) . " قلم به ارزش " . currency_format($grandTotal,$currencyContext['code']));

                set_flash("بل فروش «{$invoiceNo}» با " . count($processedItems) . " قلم با موفقیت ثبت گردید.", 'success');
                redirect(BASE_URL . "print/invoice.php?id={$saleId}");
            } catch (Exception $e) {
                if($pdo->inTransaction()) $pdo->rollBack();
                // A concurrent retry may have committed the identical invoice after our unique-key check.
                if($e instanceof PDOException&&isset($saleRequestKey,$saleContentHash)&&($existingSale=sale_request_existing(shop_id(),$saleRequestKey,$saleContentHash)))redirect(BASE_URL.'print/invoice.php?id='.$existingSale);
                $error = __t('Sale could not be saved: ','فروش ذخیره نشد: ','پلور خوندي نه شو: ') . public_error($e);
            }
        }
    }
}

// Fetch all in-stock products for the fast live search engine
$allStockProducts = $pdo->query("SELECT s.id, s.name, s.unit, s.sku, s.currency_code, s.sale_currency_code, s.inventory_value, s.barcode, s.specifications, (s.quantity-s.reserved_qty) AS quantity, s.sale_price, s.purchase_price, s.expenses, s.mudaraba_id, m.partner_name as mudaraba_partner_name, c.name as category_name
                                  FROM stock s
                                  LEFT JOIN categories c ON s.category_id = c.id
                                  LEFT JOIN mudaraba_contracts m ON s.mudaraba_id = m.id
                                  WHERE s.quantity > 0 AND ".shop_sql('s')."
                                  ORDER BY s.name ASC")->fetchAll();

$isProfitSpecsLocked = is_feature_locked('preview_profit_specs');
foreach($allStockProducts as &$product){if($isProfitSpecsLocked){$product['purchase_price']=null;$product['expenses']=null;$product['inventory_value']=null;}if(!domain_can('mudaraba.view')){$product['mudaraba_id']=null;$product['mudaraba_partner_name']=null;}}unset($product);

$posRestore=[];
if($error!==''&&is_array($items??null)){
 $safeItems=[];foreach(array_slice($items,0,30) as $it){if(!is_array($it)||!is_scalar($it['id']??null)||!is_scalar($it['qty']??null)||!is_scalar($it['price']??null))continue;$custom=[];foreach($invoiceCustomColumns as $c){$v=is_array($it['custom_fields']??null)?($it['custom_fields'][$c['id']]??''):'';if(is_string($v)&&mb_check_encoding($v,'UTF-8'))$custom[$c['id']]=mb_substr($v,0,250);} $safeItems[]=['id'=>(int)$it['id'],'qty'=>is_finite((float)$it['qty'])?(float)$it['qty']:0,'price'=>is_finite((float)$it['price'])?(float)$it['price']:0,'custom_fields'=>$custom];}
 $posRestore=['items'=>$safeItems,'customer_name'=>pos_text_value('customer_name'),'customer_phone'=>pos_text_value('customer_phone'),'notes'=>pos_text_value('notes'),'payment_type'=>$paymentType??'قرض','paid_amount'=>pos_text_value('paid_amount','0'),'payment_currency'=>pos_text_value('payment_currency_code',$currencyContext['code']),'cash_account'=>pos_text_value('cash_account_id')];
}
$pageTitle = 'فروختن جنس (بل چند جنسی جدید)';
require_once __DIR__ . '/../includes/header.php';
?>

<form id="posForm" method="POST" action="" onsubmit="return validatePosSubmission();">
<div class="pos-layout">
    <!-- Main POS Section -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">
                <i class="fa-solid fa-cart-shopping ml-1"></i> <?= __('new_multiproduct_sale') ?>
            </h3>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="badge badge-primary" style="font-size: 13px;"><?= sprintf(__('invoice_badge'), sanitize($nextInvNo)) ?></span>
                <span class="badge badge-secondary" style="font-size: 12px;">
                    <i class="fa-regular fa-clock ml-1"></i> <?= date('H:i') ?> - <?= date('Y/m/d') ?>
                </span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

            <?= csrf_field() ?><?php funds_select(); ?>
            <input type="hidden" name="action" value="create_sale">
            <input type="hidden" name="sale_request_key" value="<?= sanitize(pos_text_value('sale_request_key',bin2hex(random_bytes(16)))) ?>">
            <?php currency_fields(['paid_amount'=>2]); ?>
            <input type="hidden" name="invoice_no" value="<?= sanitize($nextInvNo) ?>">
            <input type="hidden" id="items_json" name="items_json" value="[]">
            <input type="hidden" name="invoice_design_revision" value="<?= sanitize($invoiceDesignRevision) ?>">

            <!-- Customer Details Header (Standard Clean Inputs) -->
            <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 14px; margin-bottom: 16px;">
                <div class="form-group">
                    <label class="form-label" for="customer_name"><?= __('buyer_name_label') ?></label>
                    <input type="text" id="customer_name" name="customer_name" class="form-control"
                           placeholder="<?= __('customer_name') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="customer_phone"><?= __('buyer_phone_label') ?></label>
                    <input type="text" id="customer_phone" name="customer_phone" class="form-control numeric-input"
                           placeholder="07xxxxxxxx">
                </div>
            </div>

            <!-- Fast Live Search Bar (Persian, English & Pashto) -->
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>
                        <i class="fa-solid fa-magnifying-glass ml-1" style="color: var(--info);"></i>
                        <strong><?= __('fast_search_label') ?></strong>
                    </span>
                    <span style="font-size: 11.5px; color: var(--text-muted);"><?= __('fast_search_sub') ?></span>
                </label>

                <div class="pos-search-wrapper">
                    <div style="position: relative;">
                        <input type="text" id="pos_search_input" class="form-control"
                               placeholder="<?= __('pos_search_placeholder') ?>"
                               autocomplete="off" style="padding-left: 36px; font-size: 14.5px; border: 2px solid #38bdf8;">
                        <i class="fa-solid fa-barcode" style="position: absolute; <?= $currentDir === 'rtl' ? 'left: 12px;' : 'right: 12px;' ?> top: 50%; transform: translateY(-50%); color: var(--text-light); font-size: 16px;"></i>
                    </div>
                    <!-- Live Search Dropdown -->
                    <div id="pos_search_results" class="pos-search-dropdown"></div>
                </div>
            </div>

            <!-- Multi-Item Invoice Table (1 to 20+ items) -->
            <div class="table-responsive" style="border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 16px; min-height: 180px; background: var(--surface);">
                <table class="pos-items-table" id="posTable">
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">#</th>
                            <th style="text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;"><?= __('pos_item_name') ?></th>
                            <th style="width: 85px; text-align: center;"><?= __('pos_in_stock') ?></th>
                            <th style="width: 110px; text-align: center;"><?= __('quantity') ?></th>
                            <th style="width: 130px; text-align: center;"><?= sprintf(__('pos_unit_price'), get_currency_label()) ?></th>
                            <th style="width: 120px; text-align: center;"><?= __('pos_total_price') ?></th>
                            <th style="width: 45px; text-align: center;"><?= __('delete') ?></th>
                        </tr>
                    </thead>
                    <tbody id="posTableBody">
                        <tr id="emptyPosRow">
                            <td colspan="7" style="text-align: center; padding: 36px 12px; color: var(--text-light);">
                                <i class="fa-solid fa-basket-shopping" style="font-size: 28px; margin-bottom: 8px; display: block; color: var(--border-dark);"></i>
                                <?= __('empty_pos_notice') ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Additional Invoice Note -->
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="notes"><?= __('invoice_notes_label') ?></label>
                <input type="text" id="notes" name="notes" class="form-control"
                       placeholder="<?= __('description') ?>">
            </div>
    </div>

    <!-- Financial Calculation & Checkout Column -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <!-- Payment & Checkout Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-calculator ml-1"></i> <?= __('checkout_card_title') ?>
                </h3>
            </div>

            <div style="display: flex; flex-direction: column; gap: 14px;">
                <!-- Summary List -->
                <div style="background: var(--surface-muted); padding: 14px; border-radius: 8px; border: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text-muted);">
                        <span><?= __('total_invoice_items') ?></span>
                        <strong id="posTotalQtyBadge" style="color: var(--text-main);">0 <?= __('items') ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 15px; font-weight: 700; border-top: 1px dashed var(--border-dark); padding-top: 8px;">
                        <span><?= __('invoice_grand_total') ?></span>
                        <span id="posGrandTotalTxt" style="color: var(--info); font-size: 18px;">0 <?= get_currency_label() ?></span>
                    </div>
                </div>

                <!-- Payment Type -->
                <div class="form-group">
                    <label class="form-label" for="payment_type"><?= __('payment_type_label') ?></label>
                    <select id="payment_type" name="payment_type" class="form-control" onchange="onPaymentTypeChange();">
                        <option value="نقد"><?= __('pay_cash') ?></option>
                        <option value="قرض"><?= __('pay_credit') ?></option>
                        <option value="قسط وار"><?= __('pay_installment') ?></option>
                    </select>
                </div>

                <div class="form-group" data-own-currency>
                    <label class="form-label"><?= __t('Payment currency','ارز دریافت پول','د تادیې اسعار') ?></label>
                    <?= currency_select('payment_currency_code',null,'id="paymentCurrency" form="posForm" onchange="this.dataset.manual=1;recalcTotals();"') ?>
                    <small id="crossPaymentNote" style="display:none;color:var(--warning)"><?= __t('Different currency: receipt will not automatically settle this invoice.','ارز متفاوت است؛ این دریافت، بل را خودکار تسویه نمی‌کند.','اسعار توپیر لري؛ دا رسید بل په اتومات ډول نه تصفیه کوي.') ?></small>
                </div>
                <!-- Paid Amount -->
                <div class="form-group" id="paidAmountGroup" data-own-currency style="display: none;">
                    <label class="form-label" for="paid_amount"><?= sprintf(__('paid_cash_amount'), get_currency_label()) ?></label>
                    <input type="text" id="paid_amount" name="paid_amount" class="form-control numeric-input"
                           placeholder="0" oninput="recalcTotals();">
                </div>

                <!-- Remaining Debt -->
                <div id="remainingDebtBox" style="display: none; background: var(--danger-soft); border: 1px solid #fecaca; border-radius: 8px; padding: 12px;">
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--danger);">
                        <span><?= __('customer_balance_due') ?></span>
                        <strong id="posRemainingTxt" style="font-size: 16px;">0 <?= get_currency_label() ?></strong>
                    </div>
                    <span style="font-size: 11px; color: var(--danger); display: block; margin-top: 4px;">
                        <?= __('balance_auto_debt_note') ?>
                    </span>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center; padding: 14px; font-size: 15px;">
                    <i class="fa-solid fa-receipt ml-1"></i> <?= __('complete_and_print_btn') ?>
                </button>
            </div>
        </div>

        <!-- Profit & Specs Preview Card (Lockable Feature) -->
        <div class="card" data-permission="sales.profit_preview" <?= domain_visibility('sales.profit_preview')==='hidden'?'hidden':'' ?>>
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h4 class="card-title" style="font-size: 14px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-chart-line" style="color: var(--success);"></i> <?= __('live_profit_specs_title') ?>
                    <?php if ($isProfitSpecsLocked): ?>
                        <span class="badge-lock-tag" onclick="showLockModal('preview_profit_specs', 'Live Profit & Specs Preview', <?= sanitize(json_for_html(__('live_profit_specs_title'))) ?>);">
                            <i class="fa-solid fa-lock"></i> <?= __('locked') ?>
                        </span>
                    <?php endif; ?>
                </h4>
            </div>

            <?php if ($isProfitSpecsLocked): ?>
                <div class="locked-blur-wrapper">
                    <div class="locked-blur-content" style="padding: 12px; display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span><?= __('estimated_trade_profit') ?></span>
                            <strong>— <?= get_currency_label() ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span><?= __('gross_profit_margin') ?></span>
                            <strong>—</strong>
                        </div>
                    </div>
                    <div class="locked-overlay-card" style="padding: 16px;">
                        <h4 style="font-size: 14px;"><i class="fa-solid fa-lock mr-1"></i> <?= __('live_profit_locked') ?></h4>
                        <p class="en-notice" style="font-size: 11.5px; margin-bottom: 8px;">
                            <?= __('feature_locked_desc') ?>
                        </p>
                        <a href="https://wa.me/93783788278?text=Hello%20Ahmad%20Najeem%20Nik,%20I%20would%20like%20to%20unlock%20Live%20Profit%20Preview" target="_blank" class="btn-whatsapp-direct" style="padding: 6px 14px; font-size: 12px;">
                            <i class="fa-brands fa-whatsapp"></i> <?= __('contact_dev_whatsapp') ?>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div style="padding: 6px 0;">
                    <div style="display: flex; justify-content: space-between; font-size: 13.5px; margin-bottom: 6px;">
                        <span style="color: var(--text-muted);"><?= __('live_invoice_profit_label') ?></span>
                        <strong id="posLiveProfitTxt" style="color: var(--success); font-size: 15px;">0 <?= get_currency_label() ?></strong>
                    </div>
                    <div style="font-size: 11px; color: var(--text-light);">
                        <?= __('live_profit_calc_hint') ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</form>

<!-- Raw Stock Catalog Data for Fast Client-Side Search -->
<script>
var stockCatalog = <?= json_for_html($allStockProducts, JSON_UNESCAPED_UNICODE) ?>;
var currentInvoiceItems = [];

var POS_CURRENCY = <?= json_for_html(get_currency_label()) ?>;
var POS_MUDARABA_TAG = <?= json_for_html(__('mudaraba_partner_tag')) ?>;
var POS_STOCK_PREFIX = <?= json_for_html(__('stock_available_prefix')) ?>;
var POS_UNIT_LABEL = <?= json_for_html(get_current_lang() === 'en' ? ' units' : (get_current_lang() === 'ps' ? ' دانې' : ' عدد')) ?>;
var POS_ITEM_LABEL = <?= json_for_html(get_current_lang() === 'en' ? ' items' : (get_current_lang() === 'ps' ? ' قلمه' : ' قلم')) ?>;
var POS_EMPTY_SEARCH = <?= json_for_html(__('no_items_matched_search')) ?>;
var POS_EMPTY_TABLE = <?= json_for_html(__('no_items_in_pos_yet')) ?>;
var POS_PRICE_PLACEHOLDER = <?= json_for_html(__('custom_unit_price_placeholder')) ?>;
var POS_DELETE_TITLE = <?= json_for_html(__('delete')) ?>;
var POS_ALERT_MAX_STOCK = <?= json_for_html(__('max_stock_reached_alert')) ?>;
var POS_ALERT_INSUFFICIENT = <?= json_for_html(__('insufficient_stock_alert')) ?>;
var POS_CUSTOM_FIELDS = <?= json_for_html(array_map(fn($c)=>['id'=>$c['id'],'label'=>invoice_column_label($c),'required'=>$c['required']],$invoiceCustomColumns)) ?>;
var POS_ALERT_ADD_ITEM = <?= json_for_html(__('add_at_least_one_item_alert')) ?>;

// DOM References
var searchInput   = document.getElementById('pos_search_input');
var searchResults = document.getElementById('pos_search_results');
var tableBody     = document.getElementById('posTableBody');
var grandTotalTxt = document.getElementById('posGrandTotalTxt');
var totalQtyBadge = document.getElementById('posTotalQtyBadge');
var itemsJsonInput= document.getElementById('items_json');
var paymentTypeSel= document.getElementById('payment_type');
var paidGroup     = document.getElementById('paidAmountGroup');
var paidInput     = document.getElementById('paid_amount');
var remainingBox  = document.getElementById('remainingDebtBox');
var remainingTxt  = document.getElementById('posRemainingTxt');
var liveProfitTxt = document.getElementById('posLiveProfitTxt');

document.getElementById('posForm').addEventListener('currencychange',function(event){
    POS_CURRENCY=event.detail.code;
    const payment=document.getElementById('paymentCurrency');if(!payment.dataset.manual)payment.value=POS_CURRENCY;
    renderInvoiceTable();searchInput.dispatchEvent(new Event('input'));
});
document.addEventListener('currenciesready',()=>{POS_CURRENCY=document.getElementById('posForm').elements.currency_code.value;const p=document.getElementById('paymentCurrency');if(!p.dataset.manual)p.value=POS_CURRENCY;recalcTotals();});
// Fast Live Search (Supports Persian, Pashto, Dari & English)
searchInput.addEventListener('input', function() {
    var query = this.value.trim().toLowerCase();
    if (query.length === 0) {
        searchResults.style.display = 'none';
        searchResults.innerHTML = '';
        return;
    }

    // Filter products
    var matches = stockCatalog.filter(function(p) {
        var nameMatch  = (p.name || '').toLowerCase().includes(query);
        var specMatch  = (p.specifications || '').toLowerCase().includes(query);
        var barMatch   = (p.barcode || '').toLowerCase().includes(query);
        var catMatch   = (p.category_name || '').toLowerCase().includes(query);
        return nameMatch || specMatch || barMatch || catMatch || (p.sku || '').toLowerCase().includes(query);
    });

    if (matches.length === 0) {
        searchResults.innerHTML = '<div style="padding: 14px; text-align: center; color: var(--text-light); font-size: 13px;">' + POS_EMPTY_SEARCH + '</div>';
        searchResults.style.display = 'block';
        return;
    }

    var html = '';
    matches.slice(0, 15).forEach(function(p) {
        var mudTag = p.mudaraba_partner_name ? ' <span style="background: var(--success-soft); color: var(--success); border: 1px solid #6ee7b7; border-radius: 4px; padding: 1px 5px; font-size: 10px; font-weight: 700;">' + POS_MUDARABA_TAG + escapeHtml(p.mudaraba_partner_name) + '</span>' : '';
        html += '<div class="pos-search-item" onclick="addItemToInvoice(' + p.id + ');">' +
                '  <div class="item-info">' +
                '    <span class="item-title">' + escapeHtml(p.name) + mudTag + '</span>' +
                '    <span class="item-meta">' + (p.category_name ? escapeHtml(p.category_name) + ' &bull; ' : '') + POS_STOCK_PREFIX + p.quantity + ' ' + escapeHtml(p.unit || 'unit') + ' · '+<?= json_for_html(__t('Cost account: ','حساب خرید: ','د پېرود حساب: ')) ?>+escapeHtml(p.currency_code) + (p.specifications ? ' &bull; ' + escapeHtml(p.specifications) : '') + '</span>' +
                '  </div>' +
                '  <div class="item-price">' + formatNumber(Number(p.sale_price)) + ' ' + escapeHtml(p.sale_currency_code) + '</div>' +
                '</div>';
    });

    searchResults.innerHTML = html;
    searchResults.style.display = 'block';
});

// Close search dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.style.display = 'none';
    }
});

// Add Item To Invoice Table
function addItemToInvoice(productId) {
    var product = stockCatalog.find(function(p) { return p.id == productId; });
    if (!product) return;

    // Check if already in table
    var existing = currentInvoiceItems.find(function(it) { return it.id == productId; });
    if (existing) {
        if (existing.qty < product.quantity) {
            existing.qty = Math.min(Number(product.quantity), existing.qty + 1);
        } else {
            alert(POS_ALERT_MAX_STOCK);
        }
    } else {
        currentInvoiceItems.push({
            id: product.id,
            name: product.name,
            specs: product.specifications || '',
            custom_fields: {},
            stock_qty: product.quantity,
            qty: Math.min(1, Number(product.quantity)),
            unit: product.unit || 'unit',
            price: product.sale_currency_code===POS_CURRENCY?Number(product.sale_price):0,
            needs_price: product.sale_currency_code!==POS_CURRENCY,
            cost_currency: product.currency_code,
            unit_cost: (Number(product.purchase_price)||0)+(Number(product.expenses)||0),
            inventory_value: Number(product.inventory_value??((Number(product.purchase_price)+Number(product.expenses))*Number(product.quantity)))
        });
    }

    searchInput.value = '';
    searchResults.style.display = 'none';
    searchInput.focus();
    renderInvoiceTable();
}

// Remove item from invoice
function removeItemFromInvoice(index) {
    currentInvoiceItems.splice(index, 1);
    renderInvoiceTable();
}

// Update quantity
function updateItemQty(index, val) {
    var item = currentInvoiceItems[index];
    if (!item) return;
    val = ('' + val).replace(/[۰-۹]/g, function(d) { return d.charCodeAt(0) - 1776; });
    var num = Math.round(parseFloat(val) * 1000) / 1000;
    if (isNaN(num) || num <= 0) num = Math.min(1,Number(item.stock_qty));
    if (num > item.stock_qty) {
        alert(POS_ALERT_INSUFFICIENT + item.stock_qty);
        num = item.stock_qty;
    }
    item.qty = num;
    var qtyInp = document.getElementById('pos_qty_' + index);
    if (qtyInp && qtyInp.value != num) qtyInp.value = num;
    var lineTotalEl = document.getElementById('line_total_' + index);
    if (lineTotalEl) {
        lineTotalEl.textContent = formatNumber(item.qty * item.price);
    }
    recalcTotals();
}

// Live free price updater - supports unrestricted pricing, decimals, and live calculation
function updateItemPriceLive(index, val) {
    var item = currentInvoiceItems[index];
    if (!item) return;
    val = ('' + val).replace(/[۰-۹]/g, function(d) { return d.charCodeAt(0) - 1776; });
    var num = parseFloat(val);
    if (isNaN(num) || num < 0) num = 0;
    item.price = num;
    item.needs_price = false;
    var lineTotalEl = document.getElementById('line_total_' + index);
    if (lineTotalEl) {
        lineTotalEl.textContent = formatNumber(item.qty * item.price);
    }
    recalcTotals();
}

// Render Table Rows
function renderInvoiceTable() {
    if (currentInvoiceItems.length === 0) {
        tableBody.innerHTML = '<tr id="emptyPosRow"><td colspan="7" style="text-align: center; padding: 36px 12px; color: var(--text-light);"><i class="fa-solid fa-basket-shopping" style="font-size: 28px; margin-bottom: 8px; display: block; color: var(--border-dark);"></i>' + POS_EMPTY_TABLE + '</td></tr>';
        recalcTotals();
        return;
    }

    var rows = '';
    currentInvoiceItems.forEach(function(item, idx) {
        var lineTotal = item.qty * item.price;
        rows += '<tr>' +
                '  <td style="text-align: center; color: var(--text-muted); font-weight: 700;">' + (idx + 1) + '</td>' +
                '  <td>' +
                '    <div style="font-weight: 700; color: var(--text-main); font-size: 13.5px;">' + escapeHtml(item.name) + '</div>' +
                '    ' + (item.specs ? '<div style="font-size: 11px; color: var(--text-muted);">' + escapeHtml(item.specs) + '</div>' : '') +
                posCustomInputs(item,idx) +
                '  </td>' +
                '  <td style="text-align: center;"><span class="badge badge-secondary" style="font-size: 11px;">' + item.stock_qty + ' ' + escapeHtml(item.unit) + '</span></td>' +
                '  <td style="text-align: center;">' +
                '    <div style="display: inline-flex; align-items: center; gap: 4px;">' +
                '      <button type="button" class="btn btn-sm btn-secondary" style="padding: 2px 8px;" onclick="updateItemQty(' + idx + ', ' + (item.qty - 1) + ');">-</button>' +
                '      <input type="number" id="pos_qty_' + idx + '" class="pos-qty-input" value="' + item.qty + '" min="0.001" step="0.001" max="' + item.stock_qty + '" oninput="updateItemQty(' + idx + ', this.value);">' +
                '      <button type="button" class="btn btn-sm btn-secondary" style="padding: 2px 8px;" onclick="updateItemQty(' + idx + ', ' + (item.qty + 1) + ');">+</button>' +
                '    </div>' +
                '  </td>' +
                '  <td style="text-align: center;">' +
                '    <input type="number" step="any" min="0" id="pos_price_' + idx + '" class="form-control" style="width: 125px; display: inline-block; text-align: center; font-weight: 700; color: var(--text-main); padding: 6px 8px; border: 1.5px solid var(--text-light); border-radius: 6px;" value="' + (item.price > 0 ? item.price : '') + '" placeholder="' + POS_PRICE_PLACEHOLDER + '" oninput="updateItemPriceLive(' + idx + ', this.value);">' +
                '  </td>' +
                '  <td style="text-align: center; font-weight: 800; color: var(--info); font-size: 14px;" id="line_total_' + idx + '">' +
                '    ' + formatNumber(lineTotal) +
                '  </td>' +
                '  <td style="text-align: center;">' +
                '    <button type="button" class="btn btn-sm btn-danger" style="padding: 4px 8px;" onclick="removeItemFromInvoice(' + idx + ');" title="' + POS_DELETE_TITLE + '">' +
                '      <i class="fa-solid fa-trash-can"></i>' +
                '    </button>' +
                '  </td>' +
                '</tr>';
    });

    tableBody.innerHTML = rows;
    recalcTotals();
}

function posCustomInputs(item,index) {
    return POS_CUSTOM_FIELDS.map(function(field){
        return '<label style="display:block;margin-top:7px;font-size:11px">'+escapeHtml(field.label)+(field.required?' *':'')+'<input type="text" class="form-control pos-custom-field" data-line="'+index+'" data-custom-field="'+escapeHtml(field.id)+'" aria-label="'+escapeHtml(field.label)+'" maxlength="250" '+(field.required?'required ':'')+'value="'+escapeHtml((item.custom_fields||{})[field.id]||'')+'"></label>';
    }).join('');
}
tableBody.addEventListener('input',function(event){
    const input=event.target.closest('[data-custom-field]');if(!input)return;
    const item=currentInvoiceItems[Number(input.dataset.line)];if(!item)return;
    item.custom_fields=item.custom_fields||{};item.custom_fields[input.dataset.customField]=input.value;recalcTotals();
});

// Recalculate invoice totals
function recalcTotals() {
    var totalQty = 0;
    var grandTotal = 0;
    var totalProfit = 0;var profitKnown=true;

    currentInvoiceItems.forEach(function(item) {
        totalQty += item.qty;
        var lineTotal = Math.round((item.qty * item.price + Number.EPSILON) * 100) / 100;
        grandTotal += lineTotal;
        if(item.cost_currency===POS_CURRENCY){const cost=item.qty>=Number(item.stock_qty)?item.inventory_value:Math.round((item.inventory_value*item.qty/Number(item.stock_qty)+Number.EPSILON)*100)/100;totalProfit+=lineTotal-cost;}else profitKnown=false;
    });

    totalQtyBadge.textContent = totalQty + POS_ITEM_LABEL;
    grandTotalTxt.textContent = formatNumber(grandTotal) + ' ' + POS_CURRENCY;
    itemsJsonInput.value = JSON.stringify(currentInvoiceItems);

    if (liveProfitTxt) {
        liveProfitTxt.textContent = profitKnown?formatNumber(totalProfit)+' '+POS_CURRENCY:<?= json_for_html(__t('Not calculated: different currencies','محاسبه‌نشده: ارزهای متفاوت','نه دی حساب شوی: بېلابېل اسعار')) ?>;
    }

    var pType = paymentTypeSel.value;
    var paymentCode=document.getElementById('paymentCurrency').value;
    var sameCurrency=paymentCode===POS_CURRENCY;
    document.getElementById('crossPaymentNote').style.display=sameCurrency?'none':'block';
    document.querySelector('label[for="paid_amount"]').textContent=<?= json_for_html(__t('Amount received','مبلغ دریافت‌شده','ترلاسه شوی مبلغ')) ?>+' ('+paymentCode+')';
    if (pType === 'نقد' && sameCurrency) {
        paidGroup.style.display = 'none';
        remainingBox.style.display = 'none';
        paidInput.value = grandTotal.toFixed(2);
    } else {
        paidGroup.style.display = 'block';
        remainingBox.style.display = 'block';
        var paid = parseFloat(paidInput.value) || 0;
        var rem = sameCurrency?Math.max(0,grandTotal-paid):grandTotal;
        remainingTxt.textContent = formatNumber(rem) + ' ' + POS_CURRENCY;
    }
}

function onPaymentTypeChange() {
    var pType = paymentTypeSel.value;
    if (pType !== 'نقد' && paidInput.value === '') {
        paidInput.value = '0';
    }
    recalcTotals();
}

function validatePosSubmission() {
    if(currentInvoiceItems.some(item=>item.needs_price)){alert(<?= json_for_html(__t('Enter each selling price in the selected currency.','قیمت فروش هر جنس را به ارز انتخابی وارد کنید.','د هر توکي بیه په ټاکلي اسعارو ولیکئ.')) ?>);return false;}
    if (currentInvoiceItems.length === 0) {
        alert(POS_ALERT_ADD_ITEM);
        searchInput.focus();
        return false;
    }
    return true;
}

function formatNumber(num) {
    return window.NikxorUI?NikxorUI.formatNumber(num,2):Number(num).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
const posRestore=<?= json_for_html($posRestore) ?>;
if(posRestore.items?.length)document.addEventListener('DOMContentLoaded',()=>{
 document.getElementById('customer_name').value=posRestore.customer_name;document.getElementById('customer_phone').value=posRestore.customer_phone;document.getElementById('notes').value=posRestore.notes;
 paymentTypeSel.value=['نقد','قرض','قسط وار'].includes(posRestore.payment_type)?posRestore.payment_type:'قرض';paidInput.value=posRestore.paid_amount;
 const payment=document.getElementById('paymentCurrency');payment.value=posRestore.payment_currency;payment.dataset.manual='1';document.getElementById('posForm').elements.cash_account_id.value=posRestore.cash_account;
 for(const stored of posRestore.items){const product=stockCatalog.find(p=>Number(p.id)===stored.id);if(!product||Number(product.quantity)<=0)continue;addItemToInvoice(product.id);const item=currentInvoiceItems.find(p=>Number(p.id)===stored.id);item.qty=Number.isFinite(stored.qty)&&stored.qty>0?Math.min(Number(product.quantity),stored.qty):1;item.price=Number.isFinite(stored.price)&&stored.price>=0?stored.price:0;item.needs_price=false;item.custom_fields=stored.custom_fields;}
 renderInvoiceTable();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
