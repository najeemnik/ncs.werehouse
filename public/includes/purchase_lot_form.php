<?php
$lotLines=is_array($_POST['items']??null)?array_values(array_filter($_POST['items'],'is_array')):[];
if(!$lotLines)$lotLines=[['quantity'=>'1','unit'=>'unit']];
$inputLimit=(int)ini_get('max_input_vars');
$lotMaxLines=$inputLimit>0?max(1,min(100,(int)floor(($inputLimit-40)/12))):100;
?>
<div class="card">
 <div class="card-header"><h3 class="card-title"><?= __t('Lot / bulk purchase','خرید لات / عمده','د ټولګې پېرود') ?></h3></div>
 <?php if($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>
 <form method="POST" id="lotPurchaseForm" data-max-lines="<?= $lotMaxLines ?>">
 <input type="hidden" name="purchase_payload_expected" value="1">
 <?= csrf_field() ?><input type="hidden" name="purchase_mode" value="lot"><input type="hidden" name="purchase_request_key" value="<?= sanitize($_POST['purchase_request_key']??bin2hex(random_bytes(16))) ?>">
 <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px">
 <?php foreach(['lot_reference'=>__t('Lot reference (auto if empty)','مرجع لات (خالی: خودکار)','د ټولګې شمېره'),'supplier_name'=>__t('Supplier','تأمین‌کننده','عرضه کوونکی')] as $field=>$label): ?>
 <label class="form-label"><?= $label ?><input class="form-control" name="<?= $field ?>" maxlength="<?= $field==='lot_reference'?100:255 ?>" value="<?= sanitize($_POST[$field]??'') ?>"></label>
 <?php endforeach; ?>
 <label class="form-label"><?= __t('Purchase date','تاریخ خرید','د پېرود نېټه') ?><input class="form-control" name="purchase_date" type="date" required value="<?= sanitize($_POST['purchase_date']??date('Y-m-d')) ?>"></label>
 <label class="form-label"><?= __('location') ?><input class="form-control" name="warehouse_location" value="<?= sanitize($_POST['warehouse_location']??'') ?>"></label>
 <label class="form-label"><?= __t('Partner (optional)','شریک (اختیاری)','شریک (اختیاري)') ?><select class="form-control" name="partner_id"><option value="">—</option><?php foreach($partners as $p): ?><option value="<?= $p['id'] ?>" <?= ($_POST['partner_id']??'')==$p['id']?'selected':'' ?>><?= sanitize($p['name'].' — '.$p['currency_code']) ?></option><?php endforeach; ?></select></label>
 <label class="form-label"><?= __t('Mudaraba contract (optional)','قرارداد مضاربه (اختیاری)','مضاربت (اختیاري)') ?><select class="form-control" name="mudaraba_id"><option value="">—</option><?php foreach($mudarabaContracts as $c): ?><option value="<?= $c['id'] ?>" <?= ($_POST['mudaraba_id']??'')==$c['id']?'selected':'' ?>><?= sanitize($c['project_title'].' — '.$c['partner_name']) ?></option><?php endforeach; ?></select></label>
 </div>
 <?php currency_fields(['lot_purchase_cost'=>2,'additional_expenses'=>2,'purchase_price'=>6,'sale_price'=>6],'original'); ?>
 <p style="color:var(--text-muted)"><?= __t('Enter the total purchase cost and shared expenses. Costs are allocated in proportion to quantity × purchase price; if all prices are zero, quantity is used. Cent remainders are distributed deterministically. Existing products are replenished using weighted-average cost.','قیمت مجموعی و مصارف مشترک را وارد کنید. هزینه به تناسب تعداد × قیمت خرید تقسیم می‌شود؛ اگر تمام قیمت‌ها صفر باشد، بر اساس تعداد. باقی‌ماندهٔ اعشار به‌طور دقیق تقسیم می‌شود. برای جنس موجود، میانگین وزنی قیمت محاسبه می‌شود.','ټول لګښت او ګډ مصارف ولیکئ. لګښت د مقدار او بیې پر تناسب وېشل کېږي؛ که بیې صفر وي د مقدار پر تناسب وېشل کېږي.') ?></p>
 <div id="lotLines"></div>
 <button type="button" class="btn btn-secondary" id="addLotLine" title="<?= __t('Maximum lines','حداکثر سطرها','د کرښو حد') ?>: <?= $lotMaxLines ?>"><i class="fa-solid fa-plus"></i> <?= __t('Add product line','افزودن سطر جنس','د توکي کرښه زیاتول') ?></button>
 <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px;margin-top:20px">
 <label class="form-label"><?= __t('Total lot purchase cost','قیمت خرید مجموعی لات','د ټولګې د پېرود ټول لګښت') ?><input class="form-control" name="lot_purchase_cost" type="number" min="0" step="0.01" required value="<?= sanitize($_POST['lot_purchase_cost']??'') ?>"></label>
 <label class="form-label"><?= __t('Additional shared expenses','مصارف اضافی مشترک','اضافي ګډ مصارف') ?><input class="form-control" name="additional_expenses" type="number" min="0" step="0.01" required value="<?= sanitize($_POST['additional_expenses']??'0') ?>"></label>
 <label class="form-label"><?= __t('Purchase notes','یادداشت خرید','د پېرود یادښت') ?><textarea class="form-control" name="notes"><?= sanitize($_POST['notes']??'') ?></textarea></label>
 </div>
 <p id="lotTotal" class="alert alert-info"></p>
 <input type="hidden" name="purchase_payload_complete" value="1">
 <button type="submit" class="btn btn-primary"><?= __t('Save purchase and update inventory','ثبت خرید و به‌روزرسانی موجودی','پېرود ثبت او موجودي تازه کړئ') ?></button>
 </form>
</div>
<template id="lotLineTemplate">
 <div class="lot-line" style="border:1px solid var(--border-color);border-radius:8px;padding:16px;margin-bottom:14px">
 <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px"><strong class="lot-line-number"></strong><button class="btn btn-danger btn-sm remove-lot-line" type="button"><?= __t('Remove','حذف','لرې کول') ?></button></div>
 <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
 <label class="form-label"><?= __t('Existing product / new product','جنس موجود / جدید','موجود / نوی توکی') ?><select class="form-control" data-field="stock_id"><option value=""><?= __t('New product','جنس جدید','نوی توکی') ?></option><?php foreach($inventoryOptions as $p): ?><option value="<?= $p['id'] ?>"><?= sanitize($p['name'].(!empty($p['sku'])?' — '.$p['sku']:'').' — '.$p['currency_code']) ?></option><?php endforeach; ?></select></label>
 <label class="form-label"><?= __('product_name_label') ?><input class="form-control" data-field="name" maxlength="255" required></label>
 <label class="form-label"><?= __('category_label') ?><select class="form-control" data-field="category_id"><option value="">—</option><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option><?php endforeach; ?></select></label>
 <label class="form-label"><?= __t('Quantity','تعداد / مقدار','مقدار') ?><input class="form-control" data-field="quantity" type="number" min="0.001" step="0.001" required value="1"></label>
 <label class="form-label"><?= __t('Measurement unit','واحد اندازه‌گیری','د اندازې واحد') ?><input class="form-control" data-field="unit" maxlength="24" list="lotUnits" required value="unit"></label>
 <label class="form-label"><?= __t('Purchase price per unit (allocation weight)','قیمت خرید فی واحد (مبنای تقسیم)','د واحد بیه (د وېش لپاره)') ?><input class="form-control" data-field="purchase_price" type="number" min="0" step="0.000001" value="0" required></label>
 <label class="form-label"><?= __t('Selling price per unit','قیمت فروش فی واحد','د واحد د پلور بیه') ?><input class="form-control" data-field="sale_price" type="number" min="0" step="0.000001" value="0" required></label>
 <label class="form-label" data-own-currency><?= __t('Selling currency','ارز قیمت فروش','د پلور اسعار') ?><?= currency_select('sale_currency_code',null,'data-field="sale_currency_code"') ?></label>
 <label class="form-label">SKU<input class="form-control" data-field="sku" maxlength="128"></label>
 <label class="form-label"><?= __t('Serial / barcode (optional)','سریال / بارکد (اختیاری)','سریال / بارکوډ (اختیاري)') ?><input class="form-control" data-field="barcode" maxlength="128"></label>
 <label class="form-label"><?= __('specs_label') ?><input class="form-control" data-field="specifications"></label>
 <label class="form-label"><?= __t('Notes','یادداشت','یادښت') ?><input class="form-control" data-field="notes"></label>
 </div></div>
</template>
<datalist id="lotUnits"><option>unit</option><option>box</option><option>pack</option><option>kg</option><option>g</option><option>l</option><option>m</option><option>pair</option></datalist>
<script>
(function(){
 const form=document.getElementById('lotPurchaseForm'),container=document.getElementById('lotLines');
 const catalog=<?= json_encode($inventoryOptions,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
 const saved=<?= json_encode(array_values($lotLines),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
 let counter=0;
 function add(data={}){if(container.children.length>=Number(form.dataset.maxLines))return;const line=document.getElementById('lotLineTemplate').content.firstElementChild.cloneNode(true),n=counter++;
 line.querySelector('.lot-line-number').textContent='#'+(n+1);
 line.querySelectorAll('[data-field]').forEach(el=>{el.name=`items[${n}][${el.dataset.field}]`;if(data[el.dataset.field]!==undefined)el.value=data[el.dataset.field];});
 line.querySelector('.remove-lot-line').onclick=()=>{if(container.children.length>1){line.remove();document.getElementById('addLotLine').disabled=false;total();}};
 line.querySelector('[data-field="stock_id"]').onchange=function(){const p=catalog.find(p=>String(p.id)===this.value);if(!p)return;for(const key of ['name','category_id','unit','sku','barcode','specifications'])line.querySelector(`[data-field="${key}"]`).value=p[key]??'';for(const key of ['partner_id','mudaraba_id'])form.elements[key].value=p[key]??'';};
 container.appendChild(line);document.getElementById('addLotLine').disabled=container.children.length>=Number(form.dataset.maxLines);if(window.MoneyForm)window.MoneyForm.initialize(form);}
 function total(){const cost=Number(form.elements.lot_purchase_cost.value)||0,expenses=Number(form.elements.additional_expenses.value)||0;document.getElementById('lotTotal').textContent=<?= json_encode(__t('Total acquisition cost: ','قیمت تمام‌شدهٔ مجموعی: ','ټول لګښت: ')) ?>+(cost+expenses).toFixed(2)+' '+form.elements.currency_code.value;}
 saved.forEach(add);document.getElementById('addLotLine').onclick=()=>add();form.addEventListener('input',total);form.addEventListener('currencychange',total);total();
})();
</script>
