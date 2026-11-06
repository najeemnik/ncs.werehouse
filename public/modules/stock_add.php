<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Add Product to Stock / گدام
 * URL: http://localhost/nikxor/modules/stock_add.php
 */

$pageTitle = 'ثبت جنس جدید در گدام';
require_once __DIR__ . '/../includes/header.php';

$error = '';
$success = '';

// Fetch categories, partners & active mudaraba contracts for selection dropdowns
$categories = $pdo->query("SELECT * FROM categories WHERE " . shop_sql() . " ORDER BY name ASC")->fetchAll();
$partners = $pdo->query("SELECT * FROM partners WHERE status = 'active' AND " . shop_sql() . " ORDER BY name ASC")->fetchAll();
$mudarabaContracts = $pdo->query("SELECT id, partner_name, project_title FROM mudaraba_contracts WHERE status = 'active' ORDER BY id DESC")->fetchAll();

$purchaseMode = ($_GET['mode'] ?? $_POST['purchase_mode'] ?? 'single') === 'lot' ? 'lot' : 'single';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error. Please refresh the form.', 'خطای نشست؛ فرم را دوباره باز کنید.', 'د ناستې تېروتنه؛ فورمه تازه کړئ.');
    } else {
        try {
            $purchase = inventory_save_purchase($_POST, $currentUser['id']);
            set_flash(__t('Purchase saved and inventory updated: ', 'خرید ثبت و موجودی به‌روز شد: ', 'پېرود ثبت او موجودي تازه شوه: ') . $purchase['reference'], 'success');
            redirect(BASE_URL . 'modules/purchases.php?id=' . $purchase['id']);
        } catch (Throwable $e) { $error = public_error($e); }
    }
}
$inventoryOptions = $pdo->query('SELECT id,name,category_id,unit,sku,barcode,specifications,partner_id,mudaraba_id,currency_code FROM stock ORDER BY name')->fetchAll();

?>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
<a class="btn <?= $purchaseMode==='single'?'btn-primary':'btn-secondary' ?>" href="?mode=single"><?= __t('Individual purchase','خرید تکی','انفرادي پېرود') ?></a>
<a class="btn <?= $purchaseMode==='lot'?'btn-primary':'btn-secondary' ?>" href="?mode=lot"><?= __t('Lot / bulk purchase','خرید لات / عمده','د ټولګې پېرود') ?></a>
<a class="btn btn-secondary" href="purchases.php"><?= __t('Purchase history','تاریخچهٔ خرید','د پېرود تاریخچه') ?></a>
</div>
<?php if ($purchaseMode==='lot') { require __DIR__.'/../includes/purchase_lot_form.php'; require __DIR__.'/../includes/footer.php'; return; } ?>
<div class="card" style="max-width: 900px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-plus-circle ml-1"></i> <?= __('register_new_stock_title') ?>
        </h3>
        <a href="<?= BASE_URL ?>modules/stock.php" class="btn btn-secondary btn-sm">
            <i class="fa-solid <?= $currentDir === 'rtl' ? 'fa-arrow-right' : 'fa-arrow-left' ?> ml-1"></i> <?= __('cancel') ?>
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
<label><?= __t('Supplier account (optional)','حساب عرضه‌کننده (اختیاری)','د عرضه کوونکي حساب') ?><select class="form-control" name="supplier_id"><option value="">—</option><?php $sq=$pdo->prepare("SELECT id,name FROM suppliers WHERE shop_id=? AND status='active' ORDER BY name");$sq->execute([shop_id()]);foreach($sq->fetchAll() as $supplier):?><option value="<?= (int)$supplier['id'] ?>"><?= sanitize($supplier['name']) ?></option><?php endforeach ?></select></label>
        <input type="hidden" name="purchase_mode" value="single">
        <input type="hidden" name="purchase_request_key" value="<?= sanitize($_POST['purchase_request_key'] ?? bin2hex(random_bytes(16))) ?>">
        <?php currency_fields(['purchase_price'=>6,'sale_price'=>6,'expenses'=>6], 'original'); ?>
        <div class="form-group"><label class="form-label"><?= __t('Existing product (restock) or new product','جنس موجود (افزایش موجودی) یا جنس جدید','موجود توکی یا نوی توکی') ?></label><select name="stock_id" id="purchaseStockId" class="form-control"><option value=""><?= __t('New product','جنس جدید','نوی توکی') ?></option><?php foreach($inventoryOptions as $option): ?><option value="<?= $option['id'] ?>" <?= ($_POST['stock_id']??'')==$option['id']?'selected':'' ?>><?= sanitize($option['name'].(!empty($option['sku'])?' — '.$option['sku']:'').' — '.$option['currency_code']) ?></option><?php endforeach; ?></select></div>
        <div class="form-grid"><div class="form-group"><label class="form-label"><?= __t('Measurement unit','واحد اندازه‌گیری','د اندازې واحد') ?></label><input class="form-control" name="unit" id="purchaseUnit" list="purchaseUnits" maxlength="24" required value="<?= sanitize($_POST['unit']??'unit') ?>"><datalist id="purchaseUnits"><option>unit</option><option>box</option><option>pack</option><option>kg</option><option>g</option><option>l</option><option>m</option><option>pair</option></datalist></div><div class="form-group"><label class="form-label">SKU (<?= __t('optional','اختیاری','اختیاري') ?>)</label><input class="form-control" name="sku" id="purchaseSku" maxlength="128" value="<?= sanitize($_POST['sku']??'') ?>"></div></div>


        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <!-- Product Name -->
            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="name">
                    <?= __('product_name_label') ?> <span style="color: var(--danger);">*</span>
                </label>
                <input type="text" id="name" name="name" class="form-control" required 
                       value="<?= sanitize($_POST['name'] ?? '') ?>" 
                       placeholder="<?= __('search_item_placeholder') ?>">
            </div>

            <!-- Category -->
            <div class="form-group">
                <label class="form-label" for="category_id"><?= __('category_label') ?></label>
                <div style="display: flex; gap: 6px;">
                    <select id="category_id" name="category_id" class="form-control">
                        <option value="">-- <?= __('all_categories') ?> --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= sanitize($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="<?= BASE_URL ?>modules/categories.php" class="btn btn-secondary" title="<?= __('manage') ?>" target="_blank">
                        <i class="fa-solid fa-gear"></i>
                    </a>
                </div>
            </div>

            <!-- Warehouse Location -->
            <div class="form-group">
                <label class="form-label" for="warehouse_location" style="display: flex; justify-content: space-between; align-items: center;">
                    <span><?= __('warehouse_loc_label') ?></span>
                    <?php if (is_feature_locked('warehouse_position')): ?>
                        <span class="badge-lock-tag" onclick="showLockModal('warehouse_position', 'Warehouse/Shelf Position', '<?= __('location') ?>');" style="cursor: pointer; font-size: 10px; padding: 1px 6px;"><i class="fa-solid fa-lock text-amber-500"></i> <?= __('locked') ?></span>
                    <?php endif; ?>
                </label>
                <input type="text" id="warehouse_location" name="warehouse_location" class="form-control" 
                       value="<?= sanitize($_POST['warehouse_location'] ?? '') ?>" 
                       placeholder="A1, B2, Shelf...">
            </div>

            <!-- Specifications -->
            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="specifications"><?= __('specs_label') ?></label>
                <textarea id="specifications" name="specifications" class="form-control" rows="2" 
                          placeholder="<?= __t('Size, color, model, packaging...','اندازه، رنگ، مدل، بسته‌بندی...','اندازه، رنګ، ماډل، بسته...') ?>"><?= sanitize($_POST['specifications'] ?? '') ?></textarea>
            </div>

            <!-- Quantity -->
            <div class="form-group">
                <label class="form-label" for="quantity">
                    <?= __('quantity_label') ?> <span style="color: var(--danger);">*</span>
                </label>
                <input type="number" id="quantity" name="quantity" class="form-control numeric-input qty-input" min="0.001" step="0.001" required
                       value="<?= sanitize($_POST['quantity'] ?? '1') ?>">
            </div>

            <!-- Purchase Price -->
            <div class="form-group">
                <label class="form-label" for="purchase_price">
                    <?= sprintf(__('purchase_price_label'), get_currency_label()) ?> <span style="color: var(--danger);">*</span>
                </label>
                <input type="number" step="any" id="purchase_price" name="purchase_price" class="form-control numeric-input price-input" min="0" required 
                       value="<?= sanitize($_POST['purchase_price'] ?? '') ?>" placeholder="0">
            </div>

            <!-- Sale Price -->
            <div class="form-group">
                <label class="form-label" for="sale_price" data-own-currency><?= sprintf(__('sale_price_label'), get_currency_label()) ?></label>
                <?= currency_select('sale_currency_code',$_POST['sale_currency_code']??currency_default_entry(),'id="saleCurrency" onchange="updateLivePricing()"') ?>
                <input type="number" step="any" id="sale_price" name="sale_price" class="form-control numeric-input" min="0" 
                       value="<?= sanitize($_POST['sale_price'] ?? '') ?>" placeholder="0">
            </div>

            <!-- Expenses per item -->
            <div class="form-group">
                <label class="form-label" for="expenses"><?= __('expenses_label') ?></label>
                <input type="number" step="any" id="expenses" name="expenses" class="form-control numeric-input" min="0" 
                       value="<?= sanitize($_POST['expenses'] ?? '0') ?>" placeholder="0">
            </div>

            <!-- Live Cost & Profit Calculation Card -->
            <div id="livePricingBox" style="grid-column: 1 / -1; background: var(--surface-muted); border: 1.5px solid var(--border-dark); border-radius: 10px; padding: 14px; margin-top: 4px;">
                <div style="font-weight: 800; font-size: 13px; color: var(--text-main); margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="fa-solid fa-calculator text-primary ml-1"></i> <?= __('live_cost_calc_title') ?></span>
                    <span id="marginBadge" class="badge" style="background: var(--border-color); color: var(--text-muted); font-size: 11px;"><?= __('waiting_for_price') ?></span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px;">
                    <div style="background: var(--surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: var(--text-muted); display: block;"><?= __('unit_cost_label') ?></span>
                        <strong id="liveUnitCost" style="font-size: 15px; color: var(--text-main);">0 <?= get_currency_label() ?></strong>
                        <small style="font-size: 10px; color: var(--text-light); display: block;"><?= __('unit_cost_formula') ?></small>
                    </div>
                    <div style="background: var(--surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: var(--text-muted); display: block;"><?= __('est_profit_label') ?></span>
                        <strong id="liveUnitProfit" style="font-size: 15px; color: var(--success);">0 <?= get_currency_label() ?></strong>
                        <small style="font-size: 10px; color: var(--text-light); display: block;"><?= __('est_profit_formula') ?></small>
                    </div>
                    <div style="background: var(--surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: var(--text-muted); display: block;"><?= __('total_capital_label') ?></span>
                        <strong id="liveTotalCapital" style="font-size: 15px; color: var(--info);">0 <?= get_currency_label() ?></strong>
                        <small style="font-size: 10px; color: var(--text-light); display: block;"><?= __('total_capital_formula') ?></small>
                    </div>
                    <div style="background: var(--surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px;">
                        <span style="font-size: 11px; color: var(--text-muted); display: block;"><?= __('total_batch_profit') ?></span>
                        <strong id="liveTotalProfit" style="font-size: 15px; color: var(--success);">0 <?= get_currency_label() ?></strong>
                        <small style="font-size: 10px; color: var(--text-light); display: block;"><?= __('total_batch_profit_formula') ?></small>
                    </div>
                </div>
            </div>

            <!-- Barcode / optional serial -->
            <div class="form-group">
                <label class="form-label" for="barcode"><?= __('barcode_label') ?></label>
                <input type="text" id="barcode" name="barcode" class="form-control" 
                       value="<?= sanitize($_POST['barcode'] ?? '') ?>" placeholder="Serial / Barcode">
            </div>

            <!-- Country of Origin -->
            <div class="form-group">
                <label class="form-label" for="country_of_origin"><?= __('country_origin_label') ?></label>
                <input type="text" id="country_of_origin" name="country_of_origin" class="form-control" 
                       value="<?= sanitize($_POST['country_of_origin'] ?? '') ?>" placeholder="Dubai, China...">
            </div>

            <!-- Supplier -->
            <div class="form-group">
                <label class="form-label" for="supplier_name"><?= __('supplier_label') ?></label>
                <input type="text" id="supplier_name" name="supplier_name" class="form-control" 
                       value="<?= sanitize($_POST['supplier_name'] ?? '') ?>" placeholder="Supplier / Vendor">
            </div>

            <!-- Mudaraba Contract Link -->
            <div class="form-group" style="grid-column: 1 / -1; background: var(--success-soft); border: 1.5px dashed #86efac; padding: 12px; border-radius: 8px;">
                <label class="form-label" for="mudaraba_id" style="color: var(--success); font-weight: 800; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-handshake-angle"></i> <?= __('mudaraba_question') ?>
                </label>
                <select id="mudaraba_id" name="mudaraba_id" class="form-control" style="background: var(--surface); border-color: #86efac; font-weight: 600;">
                    <option value=""><?= __('mudaraba_default_opt') ?></option>
                    <?php foreach ($mudarabaContracts as $mc): ?>
                        <option value="<?= $mc['id'] ?>" <?= (isset($_POST['mudaraba_id']) && $_POST['mudaraba_id'] == $mc['id']) ? 'selected' : '' ?>>
                            <?= sanitize($mc['partner_name']) ?> (<?= sanitize($mc['project_title']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Associated Partner -->
            <div class="form-group">
                <label class="form-label" for="partner_id"><?= __('nav_partners') ?>:</label>
                <select id="partner_id" name="partner_id" class="form-control">
                    <option value="">-- <?= __('card_shop_capital') ?> --</option>
                    <?php foreach ($partners as $part): ?>
                        <option value="<?= $part['id'] ?>" <?= (isset($_POST['partner_id']) && $_POST['partner_id'] == $part['id']) ? 'selected' : '' ?>>
                            <?= sanitize($part['name']) ?> (<?= to_persian_num($part['share_percentage']) ?>% share)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Purchase Date -->
            <div class="form-group">
                <label class="form-label" for="purchase_date"><?= __('date') ?>:</label>
                <input type="date" id="purchase_date" name="purchase_date" class="form-control" 
                       value="<?= sanitize($_POST['purchase_date'] ?? date('Y-m-d')) ?>">
            </div>
        </div>

        <div class="form-group"><label class="form-label"><?= __t('Purchase notes','یادداشت خرید','د پېرود یادښت') ?></label><textarea class="form-control" name="notes" rows="2"><?= sanitize($_POST['notes']??'') ?></textarea></div>
        <div style="margin-top: 24px; display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                <i class="fa-solid fa-floppy-disk"></i> <?= __('save_item_btn') ?>
            </button>
            <a href="<?= BASE_URL ?>modules/stock.php" class="btn btn-secondary"><?= __('cancel') ?></a>
        </div>
    </form>
</div>

<script>
var appCurrency = <?= json_for_html(get_currency_label()) ?>;
var isEnLang = <?= json_for_html($currentLang === 'en') ?>;

function updateLivePricing() {
    appCurrency=document.getElementById('purchase_price').form.elements.currency_code.value;
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
        badge.textContent = (isEnLang ? 'Warning: Unit Loss (' : 'هشدار: ضرر در هر دانه (') + (window.NikxorUI?NikxorUI.formatNumber(Math.abs(unitProfit),6,true):(Math.abs(unitProfit)).toLocaleString()) + ' ' + appCurrency + ')';
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

<script>
const inventoryOptions = <?= json_for_html($inventoryOptions,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
document.getElementById('purchaseStockId').addEventListener('change',function(){
 const p=inventoryOptions.find(p=>String(p.id)===this.value); if(!p)return;
 for(const [name,key] of Object.entries({name:'name',category_id:'category_id',specifications:'specifications',barcode:'barcode',unit:'unit',sku:'sku',partner_id:'partner_id',mudaraba_id:'mudaraba_id'})){const input=this.form.elements[name];if(input)input.value=p[key]??'';}
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
