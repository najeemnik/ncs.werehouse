<?php
/**
 * Barcode & QR guide (Phase 4 - item 9).
 * Explains how one item is identified, and prints real, scannable labels.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/barcode.php';
require_once __DIR__ . '/../includes/qrcode.php';

$pageTitle = __t('Barcode & QR guide', 'راهنمای بارکد و QR', 'د بارکوډ او QR لارښود');
require_once __DIR__ . '/../includes/header.php';

// Only the items of the active shop.
$items = $pdo->query("SELECT s.id, s.name, s.sku, s.barcode, s.unit, s.quantity, s.sale_price, s.currency_code
                      FROM stock s
                      WHERE " . shop_sql('s') . "
                      ORDER BY s.name ASC")->fetchAll();

$selectedId = (int)($_GET['id'] ?? 0);
$selected   = null;
foreach ($items as $item) {
    if ((int)$item['id'] === $selectedId) { $selected = $item; break; }
}

$sizeKey = in_array($_GET['size'] ?? '', ['S', 'M', 'L'], true) ? $_GET['size'] : 'M';
$sizes   = ['S' => [30, 20], 'M' => [40, 30], 'L' => [50, 40]];
[$labelW, $labelH] = $sizes[$sizeKey];
$showName  = ($_GET['name'] ?? '1') === '1';
$showPrice = ($_GET['price'] ?? '1') === '1';

$withSku    = count(array_filter($items, static fn($i) => trim((string)$i['sku']) !== ''));
$withBarcode = count(array_filter($items, static fn($i) => trim((string)$i['barcode']) !== ''));
$missing    = array_values(array_filter($items, static fn($i) => trim((string)$i['sku']) === '' && trim((string)$i['barcode']) === ''));
$shopName   = get_setting('shop_name', '');

$itemCode = '';
$labelQr  = '';
if ($selected) {
    $itemCode = trim((string)($selected['sku'] ?? '')) !== ''
        ? (string)$selected['sku']
        : (trim((string)($selected['barcode'] ?? '')) !== '' ? (string)$selected['barcode'] : 'ITM-' . (int)$selected['id']);
    $qrLines = [$selected['name']];
    $qrLines[] = __t('Code: ', 'کد: ', 'کوډ: ') . $itemCode;
    if ($showPrice) $qrLines[] = __t('Price: ', 'قیمت: ', 'بیه: ') . currency_format($selected['sale_price'], $selected['currency_code']);
    if ($shopName !== '') $qrLines[] = $shopName;
    // A 1D barcode needs at least ~0.25 mm per module to stay scannable on paper.
    $barcodeModules = nik_barcode_modules($itemCode);
    $barcodeWidthMm = max(10.0, (float)$labelW - 8.0);
    $barcodeModuleMm = $barcodeModules > 0 ? $barcodeWidthMm / $barcodeModules : 0.0;
    $renderBarcode = $barcodeModules > 0 && $barcodeModuleMm >= 0.25;
    $labelQr = nik_qr_svg(implode("\n", $qrLines), ['size_mm' => $renderBarcode ? 16 : 24, 'level' => 'M', 'color' => '#0f172a', 'alt' => $itemCode, 'class' => 'label-qr']);
}
?>
<style>
    .guide-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
    .guide-step { border: 1px solid var(--border-dark); border-radius: 12px; padding: 14px; background: var(--surface); }
    .guide-step b { display: block; margin-bottom: 6px; color: var(--primary); }
    .label-sheet { display: flex; justify-content: center; padding: 18px 0; }
    .label-card { width: <?= (int)$labelW ?>mm; min-height: <?= (int)$labelH ?>mm; background: #fff; color: #0f172a;
        border: 1px dashed #94a3b8; border-radius: 6px; padding: 3mm; display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: 1.5mm; text-align: center; }
    .label-shop { font-size: 7px; letter-spacing: .3px; color: #64748b; }
    .label-name { font-size: 9px; font-weight: 700; line-height: 1.25; }
    .label-code { font-size: 8px; font-family: monospace; letter-spacing: .4px; }
    .label-price { font-size: 8px; font-weight: 700; }
    .label-body { display: flex; flex-direction: column; align-items: center; gap: 1mm; width: 100%; }
    .label-barcode { width: 100%; }
    .label-barcode svg { width: 100%; height: auto; display: block; }
    .label-meta { display: flex; flex-direction: column; align-items: center; gap: .5mm; }
    @media print {
        .app-sidebar, .app-topbar, .no-print, .shop-context-bar, .alert { display: none !important; }
        .app-main { margin: 0 !important; padding: 0 !important; }
        body { background: #fff !important; }
        .card { box-shadow: none !important; border: none !important; }
        .label-sheet { padding: 0 !important; }
        .label-card { border: none !important; }
    }
</style>

<div class="page-heading no-print" style="margin-bottom:14px">
    <h2 style="margin:0 0 4px"><i class="fa-solid fa-barcode ml-1"></i> <?= __t('Barcode & QR guide', 'راهنمای بارکد و QR', 'د بارکوډ او QR لارښود') ?></h2>
    <p style="margin:0;color:var(--text-muted);font-size:12.5px"><?= __t('How one item is identified, and how to print a scannable label for it.', 'هر جنس چطور شناسایی می‌شود و چطور برچسب قابل اسکن برایش چاپ کنیم.', 'هر توکی څنګه پېژندل کېږي او د سکن وړ لیبل څنګه چاپ کړو.') ?></p>
</div>

<div class="guide-grid no-print" style="margin-bottom:16px">
    <div class="guide-step">
        <b><i class="fa-solid fa-barcode"></i> <?= __t('1) Barcode = one SKU', '۱) بارکد = یک کد جنس', '۱) بارکوډ = یو توکی کوډ') ?></b>
        <?= __t('A barcode is a row of bars that stores one short code: the SKU or the manufacturer serial. It is fast to scan at the till but cannot hold a full sentence.', 'بارکد خطی، فقط یک کد کوتاه را نگه می‌دارد: SKU یا سریال کارخانه. در فروش سریع اسکن می‌شود ولی متن کامل را نگه نمی‌دارد.', 'بارکوډ یوازې لنډ کوډ ساتي: SKU یا فابریکې سریال. په پلور کې ژر سکن کېږي خو بشپړ متن نه ساتي.') ?>
    </div>
    <div class="guide-step">
        <b><i class="fa-solid fa-qrcode"></i> <?= __t('2) QR = details', '۲) QR = جزئیات', '۲) QR = جزیات') ?></b>
        <?= __t('A QR square holds a short readable summary: item name, code, price and shop. It is used on the invoice and on item labels.', 'مربع QR یک خلاصهٔ خوانا نگه می‌دارد: نام جنس، کد، قیمت و دکان. در بل و برچسب جنس استفاده می‌شود.', 'مربع QR یو لنډ لوستل کېدونکی خلاصه ساتي: نوم، کوډ، بیه او دوکان.') ?>
    </div>
    <div class="guide-step">
        <b><i class="fa-solid fa-list-check"></i> <?= __t('3) Order of identification', '۳) ترتیب شناسایی', '۳) د پېژندنې ترتیب') ?></b>
        <?= __t('Sales search checks item name, specifications, SKU and barcode together, so scanning or typing both reach the same item.', 'جست‌وجوی فروش هم‌زمان نام، مشخصات، SKU و بارکد را می‌گردد؛ پس اسکن یا تایپ هر دو به همان جنس می‌رسند.', 'د پلور لټون نوم، مشخصات، SKU او بارکوډ یو ځای ګوري.') ?>
    </div>
</div>

<section class="card no-print" style="margin-bottom:16px">
    <div class="card-header"><h3 class="card-title"><?= __t('Identification status of this shop', 'وضعیت شناسه‌ها در این فروشگاه', 'د دې دوکان د پېژندنې حالت') ?></h3></div>
    <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
        <div class="stat-card"><div class="stat-header"><span class="stat-title"><?= __t('Items', 'اجناس', 'توکي') ?></span><i class="fa-solid fa-boxes-stacked stat-icon"></i></div><strong class="stat-value"><?= ui_number(count($items), 0) ?></strong></div>
        <div class="stat-card"><div class="stat-header"><span class="stat-title"><?= __t('With SKU', 'با SKU', 'د SKU سره') ?></span><i class="fa-solid fa-hashtag stat-icon"></i></div><strong class="stat-value"><?= ui_number($withSku, 0) ?></strong></div>
        <div class="stat-card"><div class="stat-header"><span class="stat-title"><?= __t('With barcode', 'با بارکد', 'د بارکوډ سره') ?></span><i class="fa-solid fa-barcode stat-icon"></i></div><strong class="stat-value"><?= ui_number($withBarcode, 0) ?></strong></div>
        <div class="stat-card"><div class="stat-header"><span class="stat-title"><?= __t('Without any code', 'بدون هیچ کد', 'بې کوډ') ?></span><i class="fa-solid fa-triangle-exclamation stat-icon"></i></div><strong class="stat-value"><?= ui_number(count($missing), 0) ?></strong></div>
    </div>
    <?php if ($missing): ?>
        <p style="margin:10px 0 6px;color:var(--text-muted);font-size:12.5px"><?= __t('These items have no SKU and no barcode yet; open the item and add one.', 'این اجناس هنوز SKU و بارکد ندارند؛ جنس را باز کنید و یکی اضافه کنید.', 'دغه توکي SKU او بارکوډ نه لري؛ توکی پرانیزئ او یو اضافه کړئ.') ?></p>
        <div class="table-responsive"><table class="table"><thead><tr><th><?= __t('Item', 'جنس', 'توکی') ?></th><th class="text-end"><?= __t('Action', 'عملیات', 'عمل') ?></th></tr></thead><tbody>
        <?php foreach (array_slice($missing, 0, 8) as $row): ?>
            <tr><td><?= sanitize($row['name']) ?></td><td class="text-end"><a class="btn btn-sm btn-secondary" href="<?= BASE_URL ?>modules/stock_edit.php?id=<?= (int)$row['id'] ?>"><i class="fa-solid fa-pen"></i> <?= __t('Add code', 'افزودن کد', 'کوډ ورزیات کړئ') ?></a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>

<section class="card no-print" style="margin-bottom:16px">
    <div class="card-header"><h3 class="card-title"><?= __t('Build an item label', 'ساخت برچسب جنس', 'د توکي لیبل جوړول') ?></h3></div>
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
        <div style="min-width:240px;flex:1 1 320px">
            <label class="form-label" for="itemPick"><?= __t('Choose an item', 'یک جنس انتخاب کنید', 'یو توکی وټاکئ') ?></label>
            <select class="form-control" id="itemPick" name="id">
                <option value="0">— <?= __t('select', 'انتخاب', 'ټاکل') ?> —</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?= (int)$item['id'] ?>" <?= (int)$item['id'] === $selectedId ? 'selected' : '' ?>><?= sanitize($item['name']) ?><?= trim((string)$item['sku']) !== '' ? ' · ' . sanitize($item['sku']) : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="labelSize"><?= __t('Label size', 'اندازهٔ برچسب', 'د لیبل اندازه') ?></label>
            <select class="form-control" id="labelSize" name="size">
                <?php foreach (['S' => '30×20 mm', 'M' => '40×30 mm', 'L' => '50×40 mm'] as $key => $text): ?>
                    <option value="<?= $key ?>" <?= $sizeKey === $key ? 'selected' : '' ?>><?= $text ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <label style="display:inline-flex;align-items:center;gap:6px;margin-bottom:10px"><input type="hidden" name="name" value="0"><input type="checkbox" name="name" value="1" <?= $showName ? 'checked' : '' ?>><?= __t('Show name', 'نمایش نام', 'نوم ښودل') ?></label>
        <label style="display:inline-flex;align-items:center;gap:6px;margin-bottom:10px"><input type="hidden" name="price" value="0"><input type="checkbox" name="price" value="1" <?= $showPrice ? 'checked' : '' ?>><?= __t('Show price', 'نمایش قیمت', 'بیه ښودل') ?></label>
        <button class="btn btn-primary" type="submit" style="margin-bottom:1px"><i class="fa-solid fa-eye"></i> <?= __t('Preview', 'پیش‌نمایش', 'مخکتنه') ?></button>
    </form>
</section>

<?php if ($selected): ?>
<section class="card" style="margin-bottom:16px">
    <div class="card-header no-print">
        <h3 class="card-title"><?= __t('Label preview', 'پیش‌نمایش برچسب', 'د لیبل مخکتنه') ?> — <?= sanitize($selected['name']) ?></h3>
        <div style="display:flex;gap:8px">
            <a class="btn btn-sm btn-secondary" href="<?= BASE_URL ?>modules/stock_edit.php?id=<?= (int)$selected['id'] ?>"><i class="fa-solid fa-pen"></i> <?= __t('Edit item', 'ویرایش جنس', 'توکی سمول') ?></a>
            <button type="button" class="btn btn-sm btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> <?= __t('Print label', 'چاپ برچسب', 'لیبل چاپ کړئ') ?></button>
        </div>
    </div>

    <div class="label-sheet">
        <div class="label-card">
            <?php if ($shopName !== ''): ?><div class="label-shop"><?= sanitize($shopName) ?></div><?php endif; ?>
            <?php if ($showName): ?><div class="label-name"><?= sanitize($selected['name']) ?></div><?php endif; ?>
            <div class="label-body">
                <?php if ($renderBarcode): ?>
                    <div class="label-barcode"><?= nik_barcode_svg($itemCode, ['size_mm' => $barcodeWidthMm, 'height_mm' => 9, 'quiet' => 10]) ?></div>
                <?php endif; ?>
                <?= $labelQr ?>
                <div class="label-meta">
                    <div class="label-code"><?= sanitize($itemCode) ?></div>
                    <?php if ($showPrice): ?><div class="label-price"><?= sanitize(currency_format($selected['sale_price'], $selected['currency_code'])) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info no-print" style="margin-bottom:0;display:flex;flex-direction:column;gap:6px">
        <span><?= __t('Scan this label with a phone camera or a USB barcode scanner before printing on paper; every label is generated from the real code of the item.', 'این برچسب را قبل از چاپ با دوربین موبایل یا اسکنر USB امتحان کنید؛ هر برچسب از کد واقعی همان جنس ساخته می‌شود.', 'دا لیبل د چاپ مخکې د موبایل کامرې یا USB سکنر سره وآزمویئ؛ هر لیبل د اصلي کوډ څخه جوړ شوی.') ?></span>
        <?php if (!$renderBarcode): ?>
            <span><i class="fa-solid fa-circle-info"></i> <?= __t('This code is too long for a scannable 1D barcode at this label width, so the label uses the QR only. Choose a larger label or a shorter SKU to print bars.', 'این کد برای پهنای این برچسب بلند است و بارکد خطی خوانا نمی‌شود؛ پس برچسب فقط QR دارد. برای چاپ خطی، برچسب بزرگ‌تر یا SKU کوتاه‌تر انتخاب کنید.', 'دا کوډ د دې لیبل لپاره اوږد دی، نو لیبل یوازې QR لري.') ?></span>
        <?php else: ?>
            <span><i class="fa-solid fa-ruler-horizontal"></i> <?= __t('Barcode module width:', 'پهنای هر خط بارکد:', 'د بارکوډ د هرې کرښې پلنوالی:') ?> <strong><?= sanitize(number_format($barcodeModuleMm, 2)) ?> mm</strong></span>
        <?php endif; ?>
    </div>
</section>
<?php else: ?>
<section class="card" style="margin-bottom:16px">
    <p style="margin:0;color:var(--text-muted)"><?= __t('Select an item above to see its label, barcode and QR.', 'برای دیدن برچسب، بارکد و QR، یک جنس را از بالا انتخاب کنید.', 'د لیبل، بارکوډ او QR لپاره پورته یو توکی وټاکئ.') ?></p>
</section>
<?php endif; ?>

<section class="card no-print">
    <h3 class="card-title"><?= __t('How the invoice QR is built', 'QR بل چطور ساخته می‌شود', 'د بل QR څنګه جوړېږي') ?></h3>
    <p style="color:var(--text-muted);font-size:12.5px"><?= __t('The invoice QR is not decorative: it contains the shop name, phones, address, invoice number, total and the small print line "Nik Smart Count - Warehousing". Its size is chosen so every square stays at least 0.44 mm, and the whole design (logo, colours, format, watermark, QR on/off) is set per shop in Office 2 → Shops & branding.', 'QR بل تزئینی نیست: نام دکان، تلفن‌ها، آدرس، شمارهٔ بل، مبلغ و خط کوچک «Nik Smart Count - Warehousing» را دارد. اندازه‌اش طوری انتخاب می‌شود که هر خانه حداقل ۰٫۴۴ میلی‌متر بماند؛ کل دیزاین (لوگو، رنگ، فرمت، واترمارک، روشن/خاموش بودن QR) برای هر فروشگاه در دفتر دو → فروشگاه‌ها و هویت تنظیم می‌شود.', 'د بل QR تزئیني نه دی: د دوکان نوم، ټیلیفونونه، پته، د بل شمېره، اندازه او د «Nik Smart Count - Warehousing» کرښه لري.') ?></p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-secondary" href="<?= BASE_URL ?>admin/office_2.php?tab=shops"><i class="fa-solid fa-store"></i> <?= __t('Shop branding', 'هویت فروشگاه', 'د دوکان برانډ') ?></a>
        <a class="btn btn-secondary" href="<?= BASE_URL ?>print/invoice.php?demo=1" target="_blank" rel="noopener"><i class="fa-solid fa-file-invoice"></i> <?= __t('Invoice preview', 'پیش‌نمایش بل', 'د بل مخکتنه') ?></a>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
