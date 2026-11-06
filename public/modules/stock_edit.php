<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Edit Product in Stock / گدام
 * URL: http://localhost/nikxor/modules/stock_edit.php?id=X
 */

$pageTitle = 'Edit product';
require_once __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM stock WHERE id = :id AND " . shop_sql());
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('جنس مورد نظر در گدام یافت نشد.', 'danger');
    redirect(BASE_URL . 'modules/stock.php');
}

$error = '';
$categories = $pdo->query("SELECT * FROM categories WHERE " . shop_sql() . " ORDER BY name ASC")->fetchAll();
$partners = $pdo->query("SELECT * FROM partners WHERE status = 'active' AND " . shop_sql() . " ORDER BY name ASC")->fetchAll();

$currencyContext=currency_prepare_request(['edit_stock'=>['purchase_price'=>6,'sale_price'=>6,'expenses'=>6]],'edit_stock',true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی نشست، لطفاً دوباره ارسال کنید.';
    } else {
        $name              = trim($_POST['name'] ?? '');
        $categoryId        = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $specifications    = trim($_POST['specifications'] ?? '');
        $quantity          = (float)normalize_digits($_POST['quantity'] ?? '0');
        $purchasePrice     = (float)normalize_digits($_POST['purchase_price'] ?? '0');
        $salePrice         = (float)normalize_digits($_POST['sale_price'] ?? '0');
        $expenses          = (float)normalize_digits($_POST['expenses'] ?? '0');
        $countryOfOrigin   = trim($_POST['country_of_origin'] ?? 'چین');
        $supplierName      = trim($_POST['supplier_name'] ?? '');
        $warehouseLocation = trim($_POST['warehouse_location'] ?? '');
        $partnerId         = !empty($_POST['partner_id']) ? (int)$_POST['partner_id'] : null;
        $purchaseDate      = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : date('Y-m-d');

        $capitalAmount = ($purchasePrice + $expenses) * $quantity;

        if (empty($name)) {
            $error = 'نام جنس نمی تواند خالی باشد.';
        } elseif ($quantity < 0) {
            $error = 'تعداد موجودی نامعتبر است.';
        } else {
            try {
                // Re-read under the currency transaction lock; a stale form cannot overwrite a sale.
                $fresh=$pdo->prepare('SELECT * FROM stock WHERE id=? AND shop_id=?'.finance_lock());$fresh->execute([$id,shop_id()]);$item=$fresh->fetch();if(!$item)throw new InvalidArgumentException('Item no longer exists.');
                currency_assert_record('stock',$id,$currencyContext['code']);
                $sellingCurrency=currency_context(['currency_code'=>$_POST['sale_currency_code']??$item['sale_currency_code']])['code'];
                $quantity=inventory_quantity($_POST['quantity']??0,true);
                $unit=inventory_unit($_POST['unit']??$item['unit']??'unit');
                $sku=strtoupper(trim($_POST['sku']??$item['sku']??''))?:null;
                if(strlen($sku??'')>128)throw new InvalidArgumentException('Invalid SKU.');
                foreach(['purchase_price'=>'purchasePrice','expenses'=>'expenses','sale_price'=>'salePrice'] as $field=>$var) {
                    if(isset($currencyContext['original'][$field]) && dec_round($currencyContext['original'][$field],6)===currency_from_ledger($item[$field],$currencyContext['rate'],6)) $$var=$item[$field];
                }
                $sameValue=dec_round($item['quantity'],3)===$quantity && dec_round($purchasePrice,6)===dec_round($item['purchase_price'],6) && dec_round($expenses,6)===dec_round($item['expenses'],6);
                if(!$sameValue)throw new InvalidArgumentException(__t('Use a purchase or inventory count to change quantity or acquisition cost.','برای تغییر مقدار یا بهای خرید، از خرید یا شمارش موجودی استفاده کنید.','د مقدار لپاره پېرود یا شمېرنه وکاروئ.'));
                if($unit!==$item['unit']||(int)$partnerId!==(int)$item['partner_id'])throw new InvalidArgumentException(__t('Unit and ownership cannot be changed on an existing stock position. Create a new purchase position.','واحد و مالکیت جنس موجود تغییر نمی‌کند؛ خرید را در یک جایگاه تازه ثبت کنید.','د موجود توکي واحد او مالکیت مه بدلوئ.'));
                $capitalAmount=$sameValue?inventory_value($item):dec_mul(dec_add($purchasePrice,$expenses,6),$quantity);
                $identity=inventory_identity(array_merge($item,['name'=>$name,'category_id'=>$categoryId,'specifications'=>$specifications,'unit'=>$unit,'sku'=>$sku,'partner_id'=>$partnerId]));
                $updStmt = $pdo->prepare("UPDATE stock SET 
                    name = :name,
                    category_id = :cat_id,
                    specifications = :spec,
                    sale_price = :sale,
                    sale_currency_code = :sale_currency,
                    country_of_origin = :orig,
                    supplier_name = :supp,
                    warehouse_location = :loc,
                    unit = :unit,
                    sku = :sku,
                    identity_key = :identity,
                    partner_id = :pid,
                    purchase_date = :pdate
                    WHERE id = :id AND shop_id = :shop");

                $updStmt->execute([
                    ':name'   => $name,
                    ':cat_id' => $categoryId,
                    ':spec'   => $specifications,
                    ':sale'   => $salePrice,
                    ':sale_currency'=>$sellingCurrency,
                    ':orig'   => $countryOfOrigin,
                    ':supp'   => $supplierName,
                    ':loc'    => $warehouseLocation,
                    ':unit'   => $unit,
                    ':sku'    => $sku,
                    ':identity'=>$identity,
                    ':pid'    => $partnerId,
                    ':pdate'  => $purchaseDate,
                    ':shop' => shop_id(),
                    ':id'     => $id
                ]);

                currency_record('stock',$id,$currencyContext,['purchase_price'=>$purchasePrice,'expenses'=>$expenses,'inventory_value'=>$capitalAmount],'adjustment');
                currency_request_commit();
                log_activity($currentUser['id'], 'ویرایش جنس', "ویرایش مشخصات جنس «{$name}» با شناسه {$id}");
                set_flash("مشخصات جنس «{$name}» با موفقیت به روز گردید.", 'success');
                redirect(BASE_URL . 'modules/stock.php');
            } catch (Exception $e) {
                currency_request_rollback();
                $error = __t('Edit failed: ','تغییرات ذخیره نشد: ','سمون ناکام: ') . public_error($e);
            }
        }
    }
}
?>

<div class="card" style="max-width: 900px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-pen-to-square ml-1"></i> <?= __('edit_stock_title') ?>: <?= sanitize($item['name']) ?>
        </h3>
        <a href="<?= BASE_URL ?>modules/stock.php" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left ml-1"></i> <?= __('back_to_list') ?>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?= sanitize($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>
        <?php currency_fields(['purchase_price'=>6,'sale_price'=>6,'expenses'=>6],'ledger',$item['currency_code']); ?>
        <p class="alert alert-info"><?= __t('This adjusts current inventory only. Historical purchase records remain unchanged. Use New purchase to replenish stock.','این فرم فقط موجودی فعلی را اصلاح می‌کند. تاریخچهٔ خرید تغییر نمی‌کند. برای افزایش موجودی از خرید جدید استفاده کنید.','دا یوازې اوسنۍ موجودي سموي. پخواني پېرودونه نه بدلېږي.') ?></p>
        <div style="display:flex;gap:16px;flex-wrap:wrap"><label class="form-label"><?= __t('Measurement unit','واحد اندازه‌گیری','د اندازې واحد') ?><input class="form-control" name="unit" maxlength="24" required value="<?= sanitize($_POST['unit']??$item['unit']??'unit') ?>"></label><label class="form-label">SKU<input class="form-control" name="sku" maxlength="128" value="<?= sanitize($_POST['sku']??$item['sku']??'') ?>"></label></div>


        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="name"><?= __('product_name_label') ?> <span style="color: var(--danger);">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required 
                       value="<?= sanitize($_POST['name'] ?? $item['name']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="category_id"><?= __('category_label') ?></label>
                <select id="category_id" name="category_id" class="form-control">
                    <option value=""><?= $currentLang === 'en' ? '-- Uncategorized --' : ($currentLang === 'ps' ? '-- بې کټګورۍ --' : '-- بدون دسته بندی --') ?></option>
                    <?php 
                    $selectedCat = $_POST['category_id'] ?? $item['category_id'];
                    foreach ($categories as $cat): 
                    ?>
                        <option value="<?= $cat['id'] ?>" <?= ($selectedCat == $cat['id']) ? 'selected' : '' ?>>
                            <?= sanitize($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="warehouse_location"><?= __('stock_location_label') ?></label>
                <input type="text" id="warehouse_location" name="warehouse_location" class="form-control" 
                       value="<?= sanitize($_POST['warehouse_location'] ?? $item['warehouse_location']) ?>">
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="specifications"><?= __('specs_label') ?></label>
                <textarea id="specifications" name="specifications" class="form-control" rows="2"><?= sanitize($_POST['specifications'] ?? $item['specifications']) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="quantity"><?= __('stock_qty_label') ?> <span style="color: var(--danger);">*</span></label>
                <input type="number" id="quantity" name="quantity" class="form-control numeric-input qty-input" min="0" step="0.001" required
                       value="<?= sanitize($_POST['quantity'] ?? $item['quantity']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="purchase_price"><?= __('purchase_price_label') ?> (<?= get_currency_label() ?>): <span style="color: var(--danger);">*</span></label>
                <input type="number" step="any" id="purchase_price" name="purchase_price" class="form-control numeric-input price-input" min="0" required 
                       value="<?= sanitize($_POST['purchase_price'] ?? $item['purchase_price']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="sale_price" data-own-currency><?= __('selling_price_label') ?> (<?= get_currency_label() ?>):</label>
                <?= currency_select('sale_currency_code',$_POST['sale_currency_code']??$item['sale_currency_code'],'id="saleCurrency" onchange="updateLivePricing()"') ?>
                <input type="number" step="any" id="sale_price" name="sale_price" class="form-control numeric-input" min="0" 
                       value="<?= sanitize($_POST['sale_price'] ?? ($item['sale_price'] ?? '')) ?>" placeholder="<?= __('selling_price_placeholder') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="expenses"><?= __('extra_expenses_label') ?></label>
                <input type="number" step="any" id="expenses" name="expenses" class="form-control numeric-input" min="0" 
                       value="<?= sanitize($_POST['expenses'] ?? $item['expenses']) ?>">
            </div>

            <!-- Live Cost & Profit Calculation Card (محاسبه زنده نرخ تمام شد و مفاد) -->
            <div id="livePricingBox" style="grid-column: 1 / -1; background: var(--surface-muted); border: 1.5px solid var(--border-dark); border-radius: 10px; padding: 14px; margin-top: 4px;">
                <div style="font-weight: 800; font-size: 13px; color: var(--text-main); margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="fa-solid fa-calculator text-primary ml-1"></i> <?= __('live_calc_title') ?></span>
                    <span id="marginBadge" class="badge" style="background: var(--border-color); color: var(--text-muted); font-size: 11px;"><?= $currentLang === 'en' ? 'Calculating...' : ($currentLang === 'ps' ? 'حساب روان دی' : 'در حال محاسبه') ?></span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px;">
                    <div style="background: var(--surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: var(--text-muted); display: block;"><?= __('unit_cost_label') ?></span>
                        <strong id="liveUnitCost" style="font-size: 15px; color: var(--text-main);">۰ افغانی</strong>
                        <small style="font-size: 10px; color: var(--text-light); display: block;"><?= __('unit_cost_hint') ?></small>
                    </div>
                    <div style="background: var(--surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: var(--text-muted); display: block;"><?= __('unit_profit_label') ?></span>
                        <strong id="liveUnitProfit" style="font-size: 15px; color: var(--success);">۰ افغانی</strong>
                        <small style="font-size: 10px; color: var(--text-light); display: block;"><?= __('unit_profit_hint') ?></small>
                    </div>
                    <div style="background: var(--surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: var(--text-muted); display: block;"><?= __('total_capital_label') ?></span>
                        <strong id="liveTotalCapital" style="font-size: 15px; color: var(--info);">۰ افغانی</strong>
                        <small style="font-size: 10px; color: var(--text-light); display: block;"><?= __('total_capital_hint') ?></small>
                    </div>
                    <div style="background: var(--surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: var(--text-muted); display: block;"><?= __('total_profit_label') ?></span>
                        <strong id="liveTotalProfit" style="font-size: 15px; color: var(--success);">۰ افغانی</strong>
                        <small style="font-size: 10px; color: var(--text-light); display: block;"><?= __('total_profit_hint') ?></small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="country_of_origin"><?= __('country_origin_label') ?></label>
                <input type="text" id="country_of_origin" name="country_of_origin" class="form-control" 
                       value="<?= sanitize($_POST['country_of_origin'] ?? $item['country_of_origin']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="supplier_name"><?= __('supplier_name_label') ?></label>
                <input type="text" id="supplier_name" name="supplier_name" class="form-control" 
                       value="<?= sanitize($_POST['supplier_name'] ?? $item['supplier_name']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="partner_id"><?= __('related_partner_label') ?></label>
                <select id="partner_id" name="partner_id" class="form-control">
                    <option value=""><?= __('general_shop_capital') ?></option>
                    <?php 
                    $selectedPartner = $_POST['partner_id'] ?? $item['partner_id'];
                    foreach ($partners as $part): 
                    ?>
                        <option value="<?= $part['id'] ?>" <?= ($selectedPartner == $part['id']) ? 'selected' : '' ?>>
                            <?= sanitize($part['name']) ?> (<?= to_persian_num($part['share_percentage']) ?>٪ سهم)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="purchase_date"><?= __('purchase_date_label') ?></label>
                <input type="date" id="purchase_date" name="purchase_date" class="form-control" 
                       value="<?= sanitize($_POST['purchase_date'] ?? $item['purchase_date']) ?>">
            </div>
        </div>

        <div style="margin-top: 24px; display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                <i class="fa-solid fa-check"></i> <?= __('btn_save_changes') ?>
            </button>
            <a href="<?= BASE_URL ?>modules/stock.php" class="btn btn-secondary"><?= __('cancel') ?></a>
        </div>
    </form>
</div>

<script>
function updateLivePricing() {
    var isEnLang=document.documentElement.lang==='en';
    var appCurrency=document.getElementById('purchase_price').form.elements.currency_code.value;
    const saleCode=document.getElementById('saleCurrency').value;
    document.querySelector('label[for="sale_price"]').childNodes.forEach(n=>{if(n.nodeType===3)n.textContent=n.textContent.replace(/\b(AFN|USD|AED|EUR)\b/g,saleCode);});
    var qty = parseFloat(document.getElementById('quantity').value) || 0;
    var buy = parseFloat(document.getElementById('purchase_price').value) || 0;
    var exp = parseFloat(document.getElementById('expenses').value) || 0;
    var sale = parseFloat(document.getElementById('sale_price').value) || 0;

    var unitCost = buy + exp;
    var unitProfit = sale > 0 ? (sale - unitCost) : 0;
    var totalCap = unitCost * qty;
    var totalProf = unitProfit * qty;

    document.getElementById('liveUnitCost').textContent = (window.NikxorUI?NikxorUI.formatNumber(unitCost,6,true):(unitCost).toLocaleString()) + ' ' + appCurrency;
    document.getElementById('liveTotalCapital').textContent = (window.NikxorUI?NikxorUI.formatNumber(totalCap,6,true):(totalCap).toLocaleString()) + ' ' + appCurrency;

    var pElem = document.getElementById('liveUnitProfit');
    var tpElem = document.getElementById('liveTotalProfit');
    var badge = document.getElementById('marginBadge');

    if(document.getElementById('saleCurrency').value!==appCurrency){
        pElem.textContent=tpElem.textContent=isEnLang?'Not calculated':'محاسبه‌نشده';
        badge.textContent=isEnLang?'Different currencies':'ارزهای متفاوت';
    } else if (sale <= 0) {
        pElem.textContent = isEnLang ? 'Not set' : 'تعیین‌نشده';
        pElem.style.color = 'var(--text-muted)';
        tpElem.textContent = isEnLang ? 'Not set' : 'تعیین‌نشده';
        tpElem.style.color = 'var(--text-muted)';
        badge.textContent = isEnLang ? 'Sale price not entered' : 'نرخ فروش وارد نشده';
        badge.style.background = 'var(--surface-muted)';
        badge.style.color = 'var(--text-muted)';
    } else if (unitProfit >= 0) {
        var pct = unitCost > 0 ? ((unitProfit / unitCost) * 100).toFixed(1) : 0;
        pElem.textContent = '+' + (window.NikxorUI?NikxorUI.formatNumber(unitProfit,6,true):(unitProfit).toLocaleString()) + ' ' + appCurrency;
        pElem.style.color = 'var(--success)';
        tpElem.textContent = '+' + (window.NikxorUI?NikxorUI.formatNumber(totalProf,6,true):(totalProf).toLocaleString()) + ' ' + appCurrency;
        tpElem.style.color = 'var(--success)';
        badge.textContent = (isEnLang ? 'Profit: ' : 'مفاد: ') + pct + '% (+' + (window.NikxorUI?NikxorUI.formatNumber(unitProfit,6,true):(unitProfit).toLocaleString()) + ' ' + appCurrency + ')';
        badge.style.background = 'var(--success-soft)';
        badge.style.color = 'var(--success)';
    } else {
        pElem.textContent = (window.NikxorUI?NikxorUI.formatNumber(unitProfit,6,true):(unitProfit).toLocaleString()) + ' ' + appCurrency;
        pElem.style.color = 'var(--danger)';
        tpElem.textContent = (window.NikxorUI?NikxorUI.formatNumber(totalProf,6,true):(totalProf).toLocaleString()) + ' ' + appCurrency;
        tpElem.style.color = 'var(--danger)';
        badge.textContent = (isEnLang ? 'Warning: Loss per unit (' : 'هشدار: ضرر در هر دانه (') + (window.NikxorUI?NikxorUI.formatNumber(Math.abs(unitProfit),6,true):(Math.abs(unitProfit)).toLocaleString()) + ' ' + appCurrency + ')';
        badge.style.background = 'var(--danger-soft)';
        badge.style.color = 'var(--danger)';
    }
}

['quantity', 'purchase_price', 'expenses', 'sale_price'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', updateLivePricing);
        el.addEventListener('change', updateLivePricing);
    }
});

document.addEventListener('DOMContentLoaded', updateLivePricing);
document.addEventListener('currenciesready',updateLivePricing);
document.getElementById('purchase_price').form.addEventListener('currencychange',updateLivePricing);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
