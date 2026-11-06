<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Warehouse Location & Shelf View (نمای گدام و قفسه ها)
 * 100% Multilingual Isolated: English, Dari, Pashto
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();
$accountWhere=currency_sql();
$currentLang = get_current_lang();
$currentDir  = get_current_dir();

$pageTitle = __t('Warehouse & Shelf View', 'نمای گدام و موقعیت اجناس', 'د ګودام او الماریو لید');
require_once __DIR__ . '/../includes/header.php';

$isLocked = is_feature_locked('shelf_view');
?>

<?php if ($isLocked): ?>
<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title" style="display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-warehouse ml-1"></i> <?= __t('Warehouse Shelves & Cabinet Breakdown View', 'نمای تفکیکی قفسه‌ها و الماری‌ها', 'د الماریو او ویترینونو جلا لید') ?>
            <span class="badge-lock-tag"><i class="fa-solid fa-lock text-amber-500"></i> <?= __t('Locked', 'قفل می‌باشد', 'قفل دی') ?></span>
        </h3>
        <a href="<?= BASE_URL ?>modules/stock.php" class="btn btn-secondary btn-sm"><?= __t('Return to Inventory', 'بازگشت به گدام', 'ګودام ته ستنېدل') ?></a>
    </div>
</div>

<div class="locked-blur-wrapper">
    <div class="locked-blur-content" style="padding: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
            <div class="card" style="padding: 20px; border: 1px solid var(--border-dark);">
                <h4><?= __t('Showcase Cabinet 1', 'الماری ویترین ۱', 'د ویترین المارۍ ۱') ?></h4>
                <p>Product A - 10 units</p>
                <p>Product B - 2 units</p>
            </div>
            <div class="card" style="padding: 20px; border: 1px solid var(--border-dark);">
                <h4><?= __t('Accessories Shelf', 'الماری لوازم جانبی', 'د فرعي توکو المارۍ') ?></h4>
                <p>Product C - 50 units</p>
                <p>Product D - 30 units</p>
            </div>
        </div>
    </div>
    <div class="locked-overlay-card">
        <h3><i class="fa-solid fa-lock mr-2"></i> <?= __t('This Feature is Locked', 'این گزینه قفل می‌باشد', 'دا برخه قفل ده') ?></h3>
        <p style="font-size: 14px; margin-bottom: 8px;"><?= __t('Contact Ahmad Najeem Nik (+93783788278) to unlock full access.', 'جهت درخواست و فعال‌سازی این گزینه با احمد نجیم نیک تماس بگیرید', 'د پرانیستلو لپاره له احمد نجیم نیک سره اړیکه ونیسئ') ?></p>
        <p class="en-notice"><?= __t('This feature is currently locked. To request access and unlock this feature, please contact Ahmad Najeem Nik.', 'این قابلیت در نسخه دمو قفل است. جهت دسترسی کامل تماس بگیرید.', 'دا برخه قفل ده.') ?></p>
        <a href="https://wa.me/93783788278?text=Hello%20Ahmad%20Najeem%20Nik,%20I%20would%20like%20to%20unlock%20Shelf%20Visual%20View" target="_blank" class="btn-whatsapp-direct">
            <i class="fa-brands fa-whatsapp"></i> <?= __t('WhatsApp Ahmad Najeem Nik (+93783788278)', 'واتس‌اپ احمد نجیم نیک (+93783788278)', 'د احمد نجیم نیک واټس اپ (+93783788278)') ?>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; exit; endif; ?>

<?php
// Fetch all stock grouped by location
$stmt = $pdo->query("SELECT s.*, c.name as category_name 
                     FROM stock s 
                     LEFT JOIN categories c ON s.category_id = c.id
                     WHERE {$accountWhere} AND ".shop_sql('s')."
                     ORDER BY s.warehouse_location ASC, s.name ASC");
$allStock = $stmt->fetchAll();

$locations = [];
$totalAllVal = 0;
$totalAllQty = 0;

foreach ($allStock as $item) {
    $loc = trim($item['warehouse_location'] ?? 'نامشخص');
    if ($loc === '') $loc = 'نامشخص';

    if (!isset($locations[$loc])) {
        $locations[$loc] = [
            'items'     => [],
            'total_qty' => 0,
            'total_val' => 0.00
        ];
    }

    $unitCost = (float)$item['purchase_price'] + (float)$item['expenses'];
    $itemVal = (float)inventory_value($item);

    $locations[$loc]['items'][] = $item;
    $locations[$loc]['total_qty'] += (float)$item['quantity'];
    $locations[$loc]['total_val'] += $itemVal;

    $totalAllQty += (float)$item['quantity'];
    $totalAllVal += $itemVal;
}
?>

<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h3 class="card-title" style="margin-bottom: 4px;">
                <i class="fa-solid fa-warehouse ml-1"></i> <?= __t('Warehouse & Showcase Visual Breakdown', 'نمای تفکیکی گدام و الماری ها', 'د ګودام او الماریو جلا لید') ?>
            </h3>
            <p style="font-size: 12.5px; color: var(--text-muted);">
                <?= sprintf(__t('Item locations across shelves, showcases, and cabinets (Total: %s items valued at %s)', 'تفکیک موقعیت اجناس در الماری ها، ویترین ها و قفسه های دکان (مجموع کل: %s قلم جنس به ارزش %s)', 'د توکو موقعیت (ټولټال: %s قلمه د %s په ارزښت)'), display_qty_unit($totalAllQty), format_money($totalAllVal)) ?>
            </p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>modules/stock_add.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> <?= __t('Add New Item', 'ثبت جنس جدید', 'نوی جنس ثبت کړئ') ?>
            </a>
            <a href="<?= BASE_URL ?>modules/stock.php" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-list"></i> <?= __t('Inventory Table', 'لست جدول گدام', 'د ګودام جدول') ?>
            </a>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 20px;">
    <?php if (empty($locations)): ?>
        <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-muted);">
            <?= __t('No items registered in warehouse yet.', 'هنوز جنسی در گدام ثبت نشده است.', 'تر اوسه په ګودام کې هېڅ توکی نه دی ثبت شوی.') ?>
        </div>
    <?php else: ?>
        <?php foreach ($locations as $locName => $locData): ?>
            <div class="card" style="border: 1px solid var(--border-dark); box-shadow: 0 2px 4px rgba(0,0,0,0.03);">
                <div class="card-header" style="background-color: var(--surface-muted); border-bottom: 2px solid var(--text-main); padding: 12px 16px;">
                    <div>
                        <h4 style="font-size: 14px; font-weight: 800; color: var(--text-main); margin: 0;">
                            <i class="fa-solid fa-boxes-packing ml-1"></i> <?= sanitize(display_stock_location($locName)) ?>
                        </h4>
                        <span style="font-size: 11.5px; color: var(--text-muted);">
                            <?= sprintf(__t('%s distinct products', '%s جنس مختلف', '%s بېلابېل توکي'), ($currentLang === 'en' ? count($locData['items']) : to_persian_num(count($locData['items'])))) ?>
                        </span>
                    </div>
                    <div style="text-align: <?= $currentDir === 'rtl' ? 'left' : 'right' ?>;">
                        <span class="badge badge-primary"><?= display_qty_unit($locData['total_qty']) ?></span>
                    </div>
                </div>

                <div style="padding: 12px 0;">
                    <table class="table" style="font-size: 12px; margin: 0;">
                        <thead>
                            <tr>
                                <th><?= __t('Product Name', 'اسم جنس', 'د توکي نوم') ?></th>
                                <th style="width: 70px; text-align: center;"><?= __t('Stock', 'موجودی', 'موجودي') ?></th>
                                <th style="width: 95px; text-align: center;"><?= __t('Cost Price', 'نرخ تمام شد', 'تمام شوی نرخ') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locData['items'] as $it): 
                                $unitCost = (float)$it['purchase_price'] + (float)$it['expenses'];
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>modules/stock_edit.php?id=<?= $it['id'] ?>" style="color: var(--text-main); font-weight: 600; text-decoration: none;">
                                            <?= sanitize($it['name']) ?>
                                        </a>
                                        <div style="font-size: 10px; color: var(--text-muted);">
                                            <?= sanitize($it['category_name'] ? display_category_name($it['category_name']) : __t('Uncategorized', 'بدون دسته', 'بې کټګورۍ')) ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ((float)$it['quantity'] <= 0): ?>
                                            <span class="badge badge-danger"><?= __t('Out of Stock', 'تمام شده (۰)', 'خلاص شوی (۰)') ?></span>
                                        <?php elseif ((float)$it['quantity'] <= 3): ?>
                                            <span class="badge badge-warning"><?= $currentLang === 'en' ? $it['quantity'] : to_persian_num($it['quantity']) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success"><?= $currentLang === 'en' ? $it['quantity'] : to_persian_num($it['quantity']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;"><?= format_money($unitCost) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="background-color: var(--surface-muted); padding: 8px 16px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; font-size: 11.5px; font-weight: 700; color: var(--text-main);">
                    <span><?= __t('Total value in this location:', 'ارزش مجموعی موجودی این بخش:', 'د دې برخې ټولټال ارزښت:') ?></span>
                    <span><?= format_money($locData['total_val']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
