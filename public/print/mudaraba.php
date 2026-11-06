<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Mudaraba Formal Contract & Statement Printable Sheet (قرارداد و صورتحساب رسمی مضاربت)
 * URL: http://localhost/nikxor/print/mudaraba.php?id=X
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();

$contractId = (int)($_GET['id'] ?? 0);
if ($contractId <= 0) {
    die('شناسه قرارداد نامعتبر است.');
}

$stmt = $pdo->prepare("SELECT * FROM mudaraba_contracts WHERE id = :id AND " . shop_sql());
$stmt->execute([':id' => $contractId]);
$c = $stmt->fetch();

if (!$c) {
    die('قرارداد مضاربت یافت نشد.');
}

$contractCurrency=currency_saved_context('mudaraba_contracts',$contractId);
currency_display_override($contractCurrency['code'],$contractCurrency['rate'],$contractCurrency['mode']);

$shopName       = get_setting('shop_name', DEFAULT_SHOP_NAME);
$phoneCentral   = get_setting('shop_phone_central', '0791510046 — اشکان');
$phoneSecondary = get_setting('shop_phone_secondary', '0747230143 — احمد ضیا');
$addressCentral = get_setting('shop_address_central', '');

$partnerShare = ((float)$c['total_profit_amount'] * (float)$c['partner_profit_rate']) / 100;
$shopShare    = ((float)$c['total_profit_amount'] * (float)$c['shop_profit_rate']) / 100;
$remPartnerProfit = max(0, $partnerShare - (float)$c['paid_profit_to_partner']);
$remCapital = max(0, (float)$c['capital_amount'] - (float)$c['returned_capital']);

// Fetch all payment receipts recorded in capital for this contract
$payoutRecords = [];
try {
    $pStmt = $pdo->prepare("SELECT * FROM capital 
        WHERE transaction_type = 'برداشت' 
          AND (source_description LIKE :pat1 OR (source_description LIKE :pat2 AND source_description LIKE '%مضاربت%'))
          AND ".shop_sql()."
        ORDER BY COALESCE(transaction_date, created_at) DESC, id DESC");
    $pStmt->execute([
        ':pat1' => "%#{$contractId}%",
        ':pat2' => "%{$c['partner_name']}%"
    ]);
    $payoutRecords = $pStmt->fetchAll();
} catch (Exception $pEx) {}

// Fetch all stock products tied to this contract
$stockStmt = $pdo->prepare("SELECT s.*, c.name as category_name 
                            FROM stock s 
                            LEFT JOIN categories c ON s.category_id = c.id 
                            WHERE s.mudaraba_id = :mid AND ".shop_sql('s')." 
                            ORDER BY s.id ASC");
$stockStmt->execute([':mid' => $contractId]);
$cItems = $stockStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>سند رسمی قرارداد مضاربت #<?= $c['id'] ?> - <?= sanitize($c['partner_name']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/vazirmatn/font.css">
    <style>
        * { box-sizing: border-box; font-family: 'Vazirmatn', Tahoma, sans-serif; }
        body { background: #f8fafc; margin: 0; padding: 20px; color: #0f172a; direction: rtl; }
        .sheet {
            background: #ffffff;
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 20mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-radius: 8px;
            position: relative;
        }
        @media print {
            body { background: transparent; padding: 0; }
            .sheet { box-shadow: none; border-radius: 0; margin: 0; padding: 15mm; width: 100%; }
            .no-print { display: none !important; }
        }
        .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 20px; }
        .tbl { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .tbl th, .tbl td { border: 1px solid #cbd5e1; padding: 10px; font-size: 13.5px; }
        .tbl th { background: #f1f5f9; text-align: right; width: 35%; color: #334155; }
        .tbl td { font-weight: 700; color: #0f172a; }
        .highlight { background: #f0fdf4; color: #166534; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; border: none; }
        .btn-primary { background: #0284c7; color: white; }
        .btn-secondary { background: #e2e8f0; color: #1e293b; }
    </style>
</head>
<body>

<div class="no-print" style="max-width: 210mm; margin: 0 auto 16px auto; display: flex; justify-content: space-between; align-items: center;">
    <a href="<?= BASE_URL ?>modules/mudaraba.php" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-right"></i> بازگشت به حساب مضاربت
    </a>
    <button onclick="window.print()" class="btn btn-primary">
        <i class="fa-solid fa-print"></i> پرینت و ذخیره PDF رسمی
    </button>
</div>

<div class="sheet">
    <div class="header">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">بسم الله الرحمن الرحیم</div>
        <h1 style="font-size: 20px; font-weight: 900; margin: 4px 0;"><?= sanitize($shopName) ?></h1>
        <h2 style="font-size: 16px; font-weight: 700; color: #0284c7; margin: 6px 0;">سند و صورتحساب رسمی قرارداد شرعی مضاربت</h2>
        <div style="font-size: 12px; color: #475569;">
            شماره قرارداد: <strong>MDR-<?= str_pad($c['id'], 4, '0', STR_PAD_LEFT) ?></strong> | تاریخ ثبت: <?= sanitize($c['start_date']) ?>
        </div>
    </div>

    <table class="tbl">
        <tr>
            <th>طرف اول (سرمایه‌گذار / رب‌المال):</th>
            <td><?= sanitize($c['partner_name']) ?> <?= !empty($c['phone']) ? '(' . sanitize($c['phone']) . ')' : '' ?></td>
        </tr>
        <tr>
            <th>طرف دوم (عامل / مضارب):</th>
            <td>مدیریت <?= sanitize($shopName) ?> (احمد ضیا و شرکاء)</td>
        </tr>
        <tr>
            <th>شرح کالا و محموله خریداری شده:</th>
            <td><?= sanitize($c['project_title']) ?></td>
        </tr>
        <tr>
            <th>کشور مبدا خریداری:</th>
            <td><?= sanitize($c['country_source'] ?: 'واردات خارجی') ?></td>
        </tr>
        <tr class="highlight">
            <th>اصل سرمایه تحویل داده شده:</th>
            <td style="font-size: 16px; font-weight: 800;"><?= format_money($c['capital_amount']) ?></td>
        </tr>
        <tr>
            <th>نسبت تقسیم مفاد شرعی:</th>
            <td>سهم سرمایه‌گذار: <strong><?= (float)$c['partner_profit_rate'] ?>٪</strong> | سهم عامل (دکان): <strong><?= (float)$c['shop_profit_rate'] ?>٪</strong></td>
        </tr>
        <tr>
            <th>مجموع فروش کالا تا کنون:</th>
            <td><?= format_money($c['total_sales_amount']) ?></td>
        </tr>
        <tr>
            <th>مجموع مفاد حاصله از فروش کالا:</th>
            <td style="color: #0284c7;"><?= currency_profit_html(currency_document_amount($c['total_profit_amount']),$c['profit_known']) ?></td>
        </tr>
        <tr>
            <th>کل سهم مفاد سرمایه‌گذار:</th>
            <td style="color: #7e22ce;"><?= currency_profit_html(currency_document_amount($partnerShare),$c['profit_known']) ?></td>
        </tr>
        <tr>
            <th>سود پرداخت شده به سرمایه‌گذار (رسید سود):</th>
            <td style="color: #166534;"><?= format_money($c['paid_profit_to_partner']) ?></td>
        </tr>
        <tr>
            <th>اصل سرمایه بازگردانده شده (استرداد به شریک):</th>
            <td style="color: #2563eb;"><?= format_money($c['returned_capital']) ?></td>
        </tr>
        <tr style="background: #f0fdf4;">
            <th>مجموع کل پرداختی‌های انجام شده به شریک:</th>
            <td style="color: #15803d; font-size: 15px; font-weight: 900;">
                <?= format_money((float)$c['paid_profit_to_partner'] + (float)$c['returned_capital']) ?>
            </td>
        </tr>
        <tr>
            <th>باقیمانده مفاد قابل پرداخت به شریک:</th>
            <td style="color: <?= $remPartnerProfit > 0 ? '#b91c1c' : '#166534' ?>; font-weight: bold;">
                <?= !$c['profit_known']?currency_profit_html(0,false):($remPartnerProfit>0?format_money($remPartnerProfit):'تسویه شده (۰)') ?>
            </td>
        </tr>
        <tr>
            <th>مانده اصل سرمایه پیش دکان:</th>
            <td style="color: <?= $remCapital > 0 ? '#ea580c' : '#166534' ?>; font-weight: bold;">
                <?= $remCapital > 0 ? format_money($remCapital) : 'استرداد کامل (۰)' ?>
            </td>
        </tr>
        <?php if (!empty($c['settlement_date'])): ?>
        <tr>
            <th>تاریخ پرداخت / موعد تسویه:</th>
            <td><?= sanitize($c['settlement_date']) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>وضعیت فعلی قرارداد:</th>
            <td>
                <?php if ($c['status'] === 'settled'): ?>
                    <span style="color: #166534; font-weight: 800;">تسویه کامل و خاتمه یافته</span>
                <?php elseif ($c['status'] === 'active'): ?>
                    <span style="color: #0284c7; font-weight: 800;">جاری و در حال گردش</span>
                <?php else: ?>
                    <span><?= sanitize($c['status']) ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php if (!empty($c['notes'])): ?>
        <tr>
            <th>توضیحات و شروط خاص:</th>
            <td><?= nl2br(sanitize($c['notes'])) ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <h3><?= __t('Separate currency accounts','حساب‌های جداگانهٔ ارزها','د اسعارو جلا حسابونه') ?></h3>
    <?= currency_contract_accounts_html($c) ?>
    <?php if (!empty($cItems)): ?>
    <div style="margin-top: 24px;">
        <h3 style="font-size: 14px; font-weight: 800; color: #0f172a; margin-bottom: 8px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px;">
            لیست اجناس و اقلام خریداری شده و ثبت‌شده در گدام (اقلام تحت پوشش این قرارداد مضاربت):
        </h3>
        <table class="tbl" style="margin-top: 6px;">
            <thead>
                <tr style="background: #f1f5f9;">
                    <th style="width: 30px; text-align: center;">#</th>
                    <th style="text-align: right; width: 25%;">نام و مدل جنس</th>
                    <th style="text-align: right; width: 15%;">دسته‌بندی</th>
                    <th style="text-align: right;">مشخصات فنی</th>
                    <th style="text-align: center; width: 60px;">تعداد</th>
                    <th style="text-align: center; width: 90px;">نرخ تمام‌شد</th>
                    <th style="text-align: center; width: 90px;">نرخ فروش</th>
                    <th style="text-align: center; width: 100px;">مجموع سرمایه</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $pIdx = 1;
                foreach ($cItems as $item): 
                    $costTotal = inventory_value($item);
                ?>
                <tr>
                    <td style="text-align: center; font-weight: normal;"><?= $pIdx++ ?></td>
                    <td style="font-weight: bold;"><?= sanitize($item['name']) ?></td>
                    <td style="font-weight: normal;"><?= sanitize($item['category_name'] ?? 'متفرقه') ?></td>
                    <td style="font-size: 12px; font-weight: normal; color: #334155;"><?= sanitize($item['specifications'] ?: '-') ?></td>
                    <td style="text-align: center;"><?= display_qty_unit($item['quantity'],$item['unit']??'unit') ?></td>
                    <td style="text-align: center; font-weight: normal;"><?= currency_format($item['purchase_price']+$item['expenses'],$item['currency_code']) ?></td>
                    <td style="text-align: center; font-weight: normal; color: #0284c7;"><?= currency_format($item['sale_price'],$item['sale_currency_code']) ?></td>
                    <td style="text-align: center; font-weight: bold; color: #166534;"><?= currency_format($costTotal,$item['currency_code']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($payoutRecords)): ?>
    <div style="margin-top: 20px;">
        <h3 style="font-size: 13.5px; font-weight: 800; color: #166534; margin-bottom: 8px; border-bottom: 1px solid #bbf7d0; padding-bottom: 4px;">
            <i class="fa-solid fa-receipt"></i> صورت و تاریخچه رسیدهای وجه پرداخت‌شده به شریک (کسر از سرمایه دکان):
        </h3>
        <table class="tbl" style="margin-top: 6px;">
            <thead>
                <tr style="background: #f0fdf4;">
                    <th style="width: 30px; text-align: center;">#</th>
                    <th style="width: 100px;">تاریخ پرداخت</th>
                    <th>شرح و تفصیل پرداخت</th>
                    <th style="width: 120px; text-align: center;">مبلغ رسید</th>
                    <th style="width: 90px; text-align: center;">وضعیت</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rcIdx = 1;
                foreach ($payoutRecords as $rc): 
                    $rcDate = !empty($rc['transaction_date']) ? $rc['transaction_date'] : date('Y-m-d', strtotime($rc['created_at']));
                ?>
                <tr>
                    <td style="text-align: center;"><?= to_persian_num($rcIdx++) ?></td>
                    <td><strong><?= to_persian_num($rcDate) ?></strong></td>
                    <td style="font-size: 11.5px;"><?= sanitize($rc['source_description']) ?></td>
                    <td style="text-align: center; font-weight: bold; color: #15803d;"><?= currency_format($rc['amount'],$rc['currency_code']) ?></td>
                    <td style="text-align: center; color: #166534; font-size: 11px; font-weight: bold;">تسلیم گردید</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div style="margin-top: 30px; font-size: 12px; line-height: 1.8; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;">
        <strong>ماده شرعی مضاربت:</strong> طرفین با رضایت کامل توافق نمودند که سرمایه فوق جهت خرید و فروش کالای متذکره به کار گرفته شده و سود حاصله طبق فیصدی‌های توافق‌شده تقسیم گردد. سود و زیان احتمالی مطابق با موازین فقهی و شریعت اسلامی تطبیق می‌گردد.
    </div>

    <div style="display: flex; justify-content: space-between; margin-top: 50px; padding: 0 30px;">
        <div style="text-align: center;">
            <div style="font-size: 13px; font-weight: 700; margin-bottom: 50px;">امضاء و شست سرمایه‌گذار (رب‌المال):</div>
            <div>................................................</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 13px; font-weight: 700; margin-bottom: 50px;">مهر و امضاء مدیریت فروشگاه (مضارب):</div>
            <div>................................................</div>
        </div>
    </div>

    <div style="position: absolute; bottom: 15mm; left: 20mm; right: 20mm; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px;">
        نشانی: <?= sanitize($addressCentral) ?> | تلفن: <?= sanitize($phoneCentral) ?>
        <div style="margin-top: 3px; font-size: 8px; color: #cbd5e1;">چاپ شده توسط <strong style="color: #94a3b8;">Nik Smart Count - Warehousing</strong></div>
    </div>
</div>

</body>
</html>
