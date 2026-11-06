<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Mudaraba Partnership & Foreign Sourcing Module (حساب اختصاصی مضاربت و تجارت کالای خارجی)
 * 100% Multilingual Isolated: English, Dari, Pashto
 */

require_once __DIR__.'/../includes/listing.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
// Legacy payout/progress handlers predate the cash ledger. Never let them
// change financial totals without a reviewed partnership settlement document.
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&in_array($_POST['action']??'',['payout_partner','update_progress'],true))access_deny(409,'partnership_settlement',__t('Partnership payout and manual progress require a reviewed settlement workflow. These legacy actions are unavailable; no money or contract totals have changed.','پرداخت شریک و تغییر دستی حساب مضاربت به جریان تسویهٔ بررسی‌شده نیاز دارد. این عملیات قدیمی بسته است؛ پول و ارقام قرارداد تغییر نکرده است.','د شریک تادیه او لاسي حساب د بیاکتل شوې تصفیې سند غواړي.'));
$currentUser = auth_user();
$currentLang = get_current_lang();
$currentDir  = get_current_dir();

$pageTitle = 'حساب اختصاصی مضاربت و سرمایه‌گذاری خارجی';
$error = '';
$success = '';

// Self-healing schema for Mudaraba & Capital tables across SQLite & MySQL (XAMPP)
function ensure_mudaraba_full_schema($db) {
    if (!$db) return;
    try {
        if (function_exists('harmonize_mysql_collations')) {
            harmonize_mysql_collations($db);
        }
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isSqlite = ($driver === 'sqlite');

        // 1. Check & heal mudaraba_contracts table
        $mColsNeeded = [
            'partner_name'           => "VARCHAR(150) NOT NULL DEFAULT ''",
            'phone'                  => "VARCHAR(50) DEFAULT ''",
            'project_title'          => "VARCHAR(200) NOT NULL DEFAULT ''",
            'country_source'         => "VARCHAR(100) DEFAULT ''",
            'capital_amount'         => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'partner_profit_rate'    => "DECIMAL(5,2) NOT NULL DEFAULT 50.00",
            'shop_profit_rate'       => "DECIMAL(5,2) NOT NULL DEFAULT 50.00",
            'total_sales_amount'     => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'total_profit_amount'    => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'paid_profit_to_partner' => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'returned_capital'       => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'start_date'             => "DATE NULL",
            'settlement_date'        => "DATE NULL",
            'status'                 => "VARCHAR(30) DEFAULT 'active'",
            'notes'                  => "TEXT NULL",
            'created_at'             => "DATETIME DEFAULT CURRENT_TIMESTAMP"
        ];

        $mExisting = [];
        try {
            if ($isSqlite) {
                $cols = $db->query("PRAGMA table_info(mudaraba_contracts)")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) $mExisting[] = strtolower($c['name']);
            } else {
                $cols = $db->query("SHOW COLUMNS FROM `mudaraba_contracts`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) $mExisting[] = strtolower($c['Field']);
            }
        } catch (Exception $e) {}

        foreach ($mColsNeeded as $col => $def) {
            if (!in_array(strtolower($col), $mExisting)) {
                try {
                    if ($isSqlite) {
                        $db->exec("ALTER TABLE mudaraba_contracts ADD COLUMN `{$col}` {$def}");
                    } else {
                        $colType = $def;
                        if (strpos($colType, 'VARCHAR') !== false || strpos($colType, 'TEXT') !== false) {
                            $colType .= " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
                        }
                        $db->exec("ALTER TABLE `mudaraba_contracts` ADD `{$col}` {$colType}");
                    }
                } catch (Exception $e) {}
            }
        }

        // 2. Check & heal capital table
        $cColsNeeded = [
            'amount'             => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'transaction_type'   => "VARCHAR(50) NOT NULL DEFAULT 'سرمایه'",
            'source_description' => "VARCHAR(255) NULL",
            'transaction_date'   => "DATE NULL",
            'created_at'         => "DATETIME DEFAULT CURRENT_TIMESTAMP"
        ];

        $cExisting = [];
        try {
            if ($isSqlite) {
                $cols = $db->query("PRAGMA table_info(capital)")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) $cExisting[] = strtolower($c['name']);
            } else {
                $cols = $db->query("SHOW COLUMNS FROM `capital`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) $cExisting[] = strtolower($c['Field']);
            }
        } catch (Exception $e) {}

        foreach ($cColsNeeded as $col => $def) {
            if (!in_array(strtolower($col), $cExisting)) {
                try {
                    if ($isSqlite) {
                        $db->exec("ALTER TABLE capital ADD COLUMN `{$col}` {$def}");
                    } else {
                        $colType = $def;
                        if (strpos($colType, 'VARCHAR') !== false || strpos($colType, 'TEXT') !== false) {
                            $colType .= " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
                        }
                        $db->exec("ALTER TABLE `capital` ADD `{$col}` {$colType}");
                    }
                } catch (Exception $e) {}
            }
        }

        // 3. Check & heal stock table for mudaraba_id & damaged_qty
        $sColsNeeded = [
            'mudaraba_id' => "INT NULL DEFAULT NULL",
            'damaged_qty' => "INT NOT NULL DEFAULT 0",
            'is_damaged'  => "TINYINT(1) NOT NULL DEFAULT 0"
        ];
        $sExisting = [];
        try {
            if ($isSqlite) {
                $cols = $db->query("PRAGMA table_info(stock)")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) $sExisting[] = strtolower($c['name']);
            } else {
                $cols = $db->query("SHOW COLUMNS FROM `stock`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) $sExisting[] = strtolower($c['Field']);
            }
        } catch (Exception $e) {}

        foreach ($sColsNeeded as $col => $def) {
            if (!in_array(strtolower($col), $sExisting)) {
                try {
                    if ($isSqlite) {
                        $db->exec("ALTER TABLE stock ADD COLUMN `{$col}` {$def}");
                    } else {
                        $db->exec("ALTER TABLE `stock` ADD `{$col}` {$def}");
                    }
                } catch (Exception $e) {}
            }
        }
    } catch (Exception $ge) {}
}
ensure_mudaraba_full_schema($pdo);
if (function_exists('harmonize_mysql_collations')) {
    harmonize_mysql_collations($pdo);
}

$currencyContext=currency_prepare_request([
    'add_mudaraba'=>['purchase_price'=>6,'sale_price'=>6,'expenses'=>6,'capital_amount'=>2],
    'add_contract_item'=>['purchase_price'=>6,'sale_price'=>6,'expenses'=>6],
    'update_progress'=>['add_sales'=>2,'add_profit'=>2],
    'payout_partner'=>['payout_profit'=>2,'return_capital'=>2],
    'edit_contract'=>['capital_amount'=>2,'paid_profit_to_partner'=>2,'returned_capital'=>2,'total_sales_amount'=>2,'total_profit_amount'=>2]
]);

// Fetch all categories for stock goods registration
$categories = $pdo->query("SELECT * FROM categories WHERE " . shop_sql() . " ORDER BY name ASC")->fetchAll();

// 1. Handle Add New Mudaraba Contract + Auto Save Goods into Warehouse (Stock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_mudaraba') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!');
    } else {
        $partnerName       = trim($_POST['partner_name'] ?? '');
        $phone             = trim($_POST['phone'] ?? '');
        $countrySource     = trim($_POST['country_source'] ?? 'دوبی (امارات)');
        $partnerRate       = (float)clean_number($_POST['partner_profit_rate'] ?? '50', true);
        $shopRate          = (float)clean_number($_POST['shop_profit_rate'] ?? '50', true);
        $startDate         = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
        $settleDate        = !empty($_POST['settlement_date']) ? $_POST['settlement_date'] : null;
        $notes             = trim($_POST['notes'] ?? '');

        // Product / Warehouse fields
        $productName       = trim($_POST['product_name'] ?? '');
        $categoryId        = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $specifications    = trim($_POST['specifications'] ?? '');
        $quantity          = (float)clean_number($_POST['quantity'] ?? '0', true);
        $purchasePrice     = (float)clean_number($_POST['purchase_price'] ?? '0', true);
        $salePrice         = (float)clean_number($_POST['sale_price'] ?? '0', true);
        $expenses          = (float)clean_number($_POST['expenses'] ?? '0', true);
        $barcode           = trim($_POST['barcode'] ?? '');
        $warehouseLocation = trim($_POST['warehouse_location'] ?? '');

        $unitCost          = $purchasePrice + $expenses;
        $calculatedCapital = $unitCost * $quantity;
        $manualCapital     = (float)clean_number($_POST['capital_amount'] ?? '0', true);
        $capitalAmount     = ($calculatedCapital > 0) ? $calculatedCapital : $manualCapital;

        $projectTitle = !empty($productName) 
            ? ($productName . ' (' . ($currentLang === 'en' ? $quantity . ' units' : to_persian_num($quantity) . ' عدد') . ')') 
            : trim($_POST['project_title'] ?? __t('Commercial Mudaraba Goods Import', 'خرید کالای تجاری مضاربت', 'د مضاربت سوداګریز توکي'));

        if (empty($partnerName)) {
            $error = __t('Please enter investor partner name.', 'لطفاً نام شریک سرمایه‌گذار مضاربت را وارد نمایید.', 'د مضاربت د شریک نوم ولیکئ.');
        } elseif (empty($projectTitle)) {
            $error = __t('Please specify goods name or project title.', 'لطفاً نام جنس یا عنوان پروژه مضاربت را مشخص نمایید.', 'د جنس یا پروژې نوم ولیکئ.');
        } elseif ($capitalAmount <= 0) {
            $error = __t('Mudaraba capital must be greater than zero.', 'مقدار سرمایه مضاربت باید بیشتر از صفر باشد.', 'د مضاربت پانګه باید له صفر زیاته وي.');
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Insert Mudaraba Contract
                $stmt = $pdo->prepare("INSERT INTO mudaraba_contracts 
                    (partner_name, phone, project_title, country_source, capital_amount, partner_profit_rate, shop_profit_rate, start_date, settlement_date, status, notes, shop_id)
                    VALUES (:pname, :phone, :title, :country, :capital, :prate, :srate, :sdate, :edate, 'active', :notes, " . shop_id() . ")");
                $stmt->execute([
                    ':pname'   => $partnerName,
                    ':phone'   => $phone,
                    ':title'   => $projectTitle,
                    ':country' => $countrySource,
                    ':capital' => $capitalAmount,
                    ':prate'   => $partnerRate,
                    ':srate'   => $shopRate,
                    ':sdate'   => $startDate,
                    ':edate'   => $settleDate,
                    ':notes'   => $notes
                ]);

                $contractId = (int)$pdo->lastInsertId();

                // Reuse the purchase journal and weighted-average inventory path.
                if (!empty($productName) && $quantity > 0) {
                    $purchaseData=array_merge($currencyContext['raw_post'],['name'=>$productName,'mudaraba_id'=>$contractId,'country_of_origin'=>$countrySource,'supplier_name'=>"خرید از پول مضاربت با ".$partnerName,'purchase_date'=>$startDate]);
                    $purchase=inventory_save_purchase($purchaseData,$currentUser['id'],false);
                    if($calculatedCapital>0) {
                        $capitalAmount=$purchase['base_total_amount'];
                        $pdo->prepare('UPDATE mudaraba_contracts SET capital_amount=? WHERE id=? AND shop_id=?')->execute([$capitalAmount,$contractId,shop_id()]);
                    }
                }
                currency_record('mudaraba_contracts',$contractId,$currencyContext,['capital_amount'=>$capitalAmount],'create',['capital_amount'=>currency_from_ledger($capitalAmount,$currencyContext['rate'])]);

                $pdo->commit();
                log_activity($currentUser['id'], 'ثبت قرارداد و مال مضاربت', "ثبت قرارداد مضاربت «{$partnerName}» و ثبت جنس «{$productName}» به تعداد {$quantity} در گدام");
                set_flash(sprintf(__t('Mudaraba contract with «%s» registered and inventory stored directly in warehouse.', 'قرارداد مضاربت با «%s» ثبت شد و جنس مربوطه مستقیماً در گدام ذخیره گردید.', 'له «%s» سره د مضاربت تړون ثبت او توکي ګودام ته داخل شول.'), $partnerName), 'success');
                redirect(BASE_URL . 'modules/mudaraba.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = __t('Error registering contract and stock: ', 'خطا در ثبت قرارداد و گدام: ', 'د تړون په ثبت کې تېروتنه: ') . public_error($e);
            }
        }
    }
}

// 2. Handle Adding Extra Goods into Existing Mudaraba Contract
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_contract_item') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!');
    } else {
        $contractId        = (int)($_POST['contract_id'] ?? 0);
        $productName       = trim($_POST['product_name'] ?? '');
        $categoryId        = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $specifications    = trim($_POST['specifications'] ?? '');
        $quantity          = (float)clean_number($_POST['quantity'] ?? '0', true);
        $purchasePrice     = (float)clean_number($_POST['purchase_price'] ?? '0', true);
        $salePrice         = (float)clean_number($_POST['sale_price'] ?? '0', true);
        $expenses          = (float)clean_number($_POST['expenses'] ?? '0', true);
        $barcode           = trim($_POST['barcode'] ?? '');
        $countrySource     = trim($_POST['country_source'] ?? '');
        $warehouseLocation = trim($_POST['warehouse_location'] ?? '');
        $purchaseDate      = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : date('Y-m-d');

        if ($contractId <= 0 || empty($productName) || $quantity <= 0) {
            $error = __t('Please enter valid product name and quantity.', 'لطفاً نام جنس و تعداد معتبر را وارد نمایید.', 'د توکي نوم او شمېر سم ولیکئ.');
        } else {
            try {
                $pdo->beginTransaction();

                $cStmt = $pdo->prepare("SELECT partner_name, country_source FROM mudaraba_contracts WHERE id = :id AND " . shop_sql());
                $cStmt->execute([':id' => $contractId]);
                $cRow = $cStmt->fetch();

                if (!$cRow) {
                    throw new Exception(__t('Mudaraba contract not found.', 'قرارداد مضاربت یافت نشد.', 'د مضاربت تړون ونه موندل شو.'));
                }

                $partnerName = $cRow['partner_name'];
                if (empty($countrySource)) {
                    $countrySource = $cRow['country_source'];
                }

                $purchaseData=array_merge($currencyContext['raw_post'],['name'=>$productName,'mudaraba_id'=>$contractId,'country_of_origin'=>$countrySource,'supplier_name'=>"خرید از پول مضاربت با ".$partnerName,'purchase_date'=>$purchaseDate]);
                $purchase=inventory_save_purchase($purchaseData,$currentUser['id']);
                // The shared purchase service records this event once, in its own currency.

                $pdo->commit();
                log_activity($currentUser['id'], 'افزودن جنس به مضاربت', "افزودن جنس «{$productName}» به تعداد {$quantity} به قرارداد مضاربت #{$contractId}");
                set_flash(sprintf(__t('Item «%s» added to Mudaraba contract and stored in warehouse.', 'جنس «%s» با موفقیت به قرارداد مضاربت افزوده و در گدام ذخیره شد.', 'توکی «%s» په تړون او ګودام کې ثبت شو.'), $productName), 'success');
                redirect(BASE_URL . 'modules/mudaraba.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = __t('Error registering item: ', 'خطا در ثبت جنس: ', 'د توکي په ثبت کې تېروتنه: ') . public_error($e);
            }
        }
    }
}

// 3. Handle Record Sales / Profit Progress on Mudaraba Contract
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_progress') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!');
    } else {
        $contractId   = (int)($_POST['contract_id'] ?? 0);
        $addSales     = (float)clean_number($_POST['add_sales'] ?? '0', true);
        $addProfit    = (float)clean_number($_POST['add_profit'] ?? '0', true);

        if ($contractId > 0 && ($addSales > 0 || $addProfit > 0)) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE mudaraba_contracts 
                SET total_sales_amount = total_sales_amount + :sales,
                    total_profit_amount = total_profit_amount + :profit
                WHERE id = :id AND currency_code = :code AND " . shop_sql());
            $stmt->execute([
                ':sales'  => $addSales,
                ':profit' => $addProfit,
                ':id'     => $contractId,':code'=>$currencyContext['code']
            ]);

            currency_record('mudaraba_contracts',$contractId,$currencyContext,['add_sales'=>$addSales,'add_profit'=>$addProfit],'progress');
            $pdo->commit();
            log_activity($currentUser['id'], 'ثبت فروش مضاربت', "ثبت فروش {$addSales} {$currencyContext['code']} و مفاد {$addProfit} {$currencyContext['code']} برای قرارداد مضاربت #{$contractId}");
            set_flash(__t('Sales revenue and profit added to Mudaraba account successfully.', 'فروش و مفاد با موفقیت به حساب مضاربت افزوده شد.', 'پلور او ګټه په بریا سره اضافه شوه.'), 'success');
            redirect(BASE_URL . 'modules/mudaraba.php');
        } else {
            $error = __t('Invalid sales or profit values entered.', 'مقادیر وارد شده نامعتبر است.', 'ناسم ارقام داخل شوي.');
        }
    }
}

// 4. Handle Payout / Settlement to Partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'payout_partner') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash(__t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!'), 'danger');
        redirect(BASE_URL . 'modules/mudaraba.php');
    } else {
        $contractId  = (int)($_POST['contract_id'] ?? 0);
        $payProfit   = (float)clean_number($_POST['payout_profit'] ?? '0', true);
        $returnCap   = (float)clean_number($_POST['return_capital'] ?? '0', true);
        $markSettled = !empty($_POST['mark_settled']) ? 'settled' : 'active';
        $pDate       = !empty($_POST['payout_date']) ? $_POST['payout_date'] : date('Y-m-d');

        if ($contractId > 0 && ($payProfit > 0 || $returnCap > 0 || $markSettled === 'settled')) {
            try {
                if (function_exists('harmonize_mysql_collations')) {
                    harmonize_mysql_collations($pdo);
                }
                ensure_mudaraba_full_schema($pdo);

                $pdo->beginTransaction();
                $cStmt = $pdo->prepare("SELECT partner_name, project_title, currency_code FROM mudaraba_contracts WHERE id = :id AND " . shop_sql());
                $cStmt->execute([':id' => $contractId]);
                $cData = $cStmt->fetch();
                $pName = $cData['partner_name'] ?? "Partner #{$contractId}";
                $pProj = $cData['project_title'] ?? "Mudaraba Project";

                if(!$cData)throw new InvalidArgumentException('Contract not found.');
                if($cData['currency_code']!==$currencyContext['code'])$markSettled='active';
                $statusSql = ($markSettled === 'settled') ? ", status = :mstatus, settlement_date = :sdate" : "";
                $stmt = $pdo->prepare("UPDATE mudaraba_contracts 
                    SET paid_profit_to_partner = COALESCE(paid_profit_to_partner, 0) + :profit,
                        returned_capital = COALESCE(returned_capital, 0) + :cap
                        {$statusSql}
                    WHERE id = :id AND currency_code = :code AND " . shop_sql());
                $updParams = [
                    ':profit'    => $payProfit,
                    ':cap'       => $returnCap,
                    ':id'        => $contractId,':code'=>$currencyContext['code']
                ];
                if ($markSettled === 'settled') {
                    $updParams[':mstatus'] = 'settled';
                    $updParams[':sdate']   = $pDate;
                }
                $stmt->execute($updParams);

                $totalPaidOut = dec_add($payProfit,$returnCap);
                $payoutCapitalId=null;
                if ($totalPaidOut > 0) {
                    $descParts = [];
                    if ($returnCap > 0) {
                        $descParts[] = "استرداد اصل سرمایه: " . currency_format($returnCap,$currencyContext['code']);
                    }
                    if ($payProfit > 0) {
                        $descParts[] = "پرداخت سهم سود: " . currency_format($payProfit,$currencyContext['code']);
                    }
                    $capDesc = "پرداخت وجه مضاربت به «{$pName}» ({$pProj}) — " . implode(' | ', $descParts);

                    try {
                        $capStmt = $pdo->prepare("INSERT INTO capital (amount, transaction_type, source_description, transaction_date, created_at, shop_id)
                                                  VALUES (:amt, :ttype, :desc, :tdate, :cdate, " . shop_id() . ")");
                        $capStmt->execute([
                            ':amt'   => $totalPaidOut,
                            ':ttype' => 'برداشت',
                            ':desc'  => $capDesc,
                            ':tdate' => $pDate,
                            ':cdate' => date('Y-m-d H:i:s')
                        ]);
                        $payoutCapitalId=(int)$pdo->lastInsertId();
                    } catch (Exception $capEx) {
                        try {
                            $pdo->prepare("INSERT INTO capital (amount, transaction_type, source_description, shop_id) VALUES (?, ?, ?, ?)")
                                ->execute([$totalPaidOut, 'برداشت', $capDesc, shop_id()]);
                            $payoutCapitalId=(int)$pdo->lastInsertId();
                        } catch (Exception $capFallback) {}
                    }
                }

                currency_record('mudaraba_contracts',$contractId,$currencyContext,['payout_profit'=>$payProfit,'return_capital'=>$returnCap],'payout');
                if($payoutCapitalId)currency_record('capital',$payoutCapitalId,$currencyContext,['amount'=>$totalPaidOut]);
                $pdo->commit();
                log_activity($currentUser['id'], 'پرداخت به شریک مضاربت', "پرداخت سود {$payProfit} {$currencyContext['code']} و استرداد سرمایه {$returnCap} {$currencyContext['code']} برای قرارداد مضاربت #{$contractId} («{$pName}»)");
                set_flash(sprintf(__t('Payment of %s disbursed to partner and logged in capital ledger.', 'مبلغ پرداختی (%s) با موفقیت در حساب مضاربت ثبت و از سرمایه اصلی دکان کسر گردید.', 'د (%s) تادیه شریک ته ورکړل شوه او له پانګې کمه شوه.'), currency_format($totalPaidOut,$currencyContext['code'])), 'success');
                redirect(BASE_URL . 'modules/mudaraba.php');
            } catch (Exception $e) {
                if($pdo->inTransaction())$pdo->rollBack();
                set_flash(__t('Error registering partner payout: ', 'خطا در ثبت پرداخت به شریک: ', 'د تادیې په ثبت کې تېروتنه: ') . public_error($e), 'danger');
                redirect(BASE_URL . 'modules/mudaraba.php');
            }
        }
    }
}

// 5. Handle Delete Mudaraba Contract
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_contract') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash(__t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!'), 'danger');
    } else {
        $delId = (int)($_POST['contract_id'] ?? 0);
        if ($delId > 0) {
            $pdo->prepare("UPDATE stock SET mudaraba_id = NULL WHERE mudaraba_id = :id AND " . shop_sql())->execute([':id' => $delId]);
            $pdo->prepare("DELETE FROM mudaraba_contracts WHERE id = :id AND " . shop_sql())->execute([':id' => $delId]);
            log_activity($currentUser['id'], 'حذف قرارداد مضاربت', "حذف قرارداد مضاربت #{$delId}");
            set_flash(__t('Mudaraba contract deleted successfully.', 'قرارداد مضاربت با موفقیت حذف گردید.', 'د مضاربت تړون په بریالیتوب ړنګ شو.'), 'success');
            redirect(BASE_URL . 'modules/mudaraba.php');
        }
    }
}

// 6. Handle Edit Contract
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_contract') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash(__t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!'), 'danger');
        redirect(BASE_URL . 'modules/mudaraba.php');
    } else {
        $cId         = (int)($_POST['contract_id'] ?? 0);
        $partnerName = trim($_POST['partner_name'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $title       = trim($_POST['project_title'] ?? '');
        $country     = trim($_POST['country_source'] ?? '');
        $capital     = (float)clean_number($_POST['capital_amount'] ?? '0', true);
        $pRate       = (float)clean_number($_POST['partner_profit_rate'] ?? '50', true);
        $sRate       = (float)clean_number($_POST['shop_profit_rate'] ?? '50', true);
        $paidProfit  = (float)clean_number($_POST['paid_profit_to_partner'] ?? '0', true);
        $returnedCap = (float)clean_number($_POST['returned_capital'] ?? '0', true);
        $salesAmt    = (float)clean_number($_POST['total_sales_amount'] ?? '0', true);
        $profitAmt   = (float)clean_number($_POST['total_profit_amount'] ?? '0', true);
        $sDate       = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
        $eDate       = !empty($_POST['settlement_date']) ? $_POST['settlement_date'] : null;
        $status      = trim($_POST['status'] ?? 'active');
        $notes       = trim($_POST['notes'] ?? '');

        if ($cId <= 0 || empty($partnerName) || empty($title)) {
            set_flash(__t('Please specify partner name and project title.', 'لطفاً نام شریک و عنوان پروژه را مشخص نمایید.', 'د شریک او پروژې نوم ولیکئ.'), 'danger');
            redirect(BASE_URL . 'modules/mudaraba.php');
        } else {
            try {
                $pdo->beginTransaction();
                currency_assert_record('mudaraba_contracts',$cId,$currencyContext['code']);
                $oldStmt = $pdo->prepare("SELECT * FROM mudaraba_contracts WHERE id = :id AND " . shop_sql().finance_lock());
                $oldStmt->execute([':id' => $cId]);
                $oldContract = $oldStmt->fetch();

                if(!$oldContract)throw new InvalidArgumentException('Contract unavailable.');
                if(partner_account($cId)&&(dec_round($pRate)!==dec_round($oldContract['partner_profit_rate'])||dec_round($sRate)!==dec_round($oldContract['shop_profit_rate'])))throw new InvalidArgumentException(__t('Reviewed profit-sharing terms are fixed; use a new approved contract for new terms.','شرایط تقسیم مفادِ تأییدشده ثابت است؛ برای شرایط تازه قرارداد تازه بسازید.','تایید شوې د ګټې وېش شرطونه ثابت دي؛ نوی تړون وکاروئ.'));
                if(partner_account($cId)&&$status!==$oldContract['status'])throw new InvalidArgumentException(__t('Change contract status through settlement accounts.','وضعیت قرارداد را از حساب تسویه تغییر دهید.','د تړون حالت د تصفیې له حسابه بدل کړئ.'));
                foreach(['capital_amount'=>$capital,'paid_profit_to_partner'=>$paidProfit,'returned_capital'=>$returnedCap,'total_sales_amount'=>$salesAmt,'total_profit_amount'=>$profitAmt] as $field=>$value)if(dec_round($oldContract[$field])!==dec_round($value))throw new InvalidArgumentException(__t('Edit contact details here. Financial totals change only through purchase, sale, return or reviewed partnership settlement documents.','اینجا مشخصات تماس را تغییر دهید. ارقام مالی تنها از سند خرید، فروش، برگشت یا تسویهٔ بررسی‌شده تغییر می‌کنند.','دلته د اړیکې معلومات بدل کړئ؛ مالي شمېرې خپل سند غواړي.'));

                $oldPayoutTotal = ((float)($oldContract['paid_profit_to_partner'] ?? 0)) + ((float)($oldContract['returned_capital'] ?? 0));
                $newPayoutTotal = $paidProfit + $returnedCap;
                $payoutDelta    = $newPayoutTotal - $oldPayoutTotal;

                $stmt = $pdo->prepare("UPDATE mudaraba_contracts SET 
                    partner_name = :pname,
                    phone = :phone,
                    project_title = :title,
                    country_source = :country,
                    capital_amount = :capital,
                    partner_profit_rate = :prate,
                    shop_profit_rate = :srate,
                    paid_profit_to_partner = :paidprofit,
                    returned_capital = :retcap,
                    total_sales_amount = :salesamt,
                    total_profit_amount = :profitamt,
                    start_date = :sdate,
                    settlement_date = :edate,
                    status = :status,
                    notes = :notes
                    WHERE id = :id");
                $stmt->execute([
                    ':pname'      => $partnerName,
                    ':phone'      => $phone,
                    ':title'      => $title,
                    ':country'    => $country,
                    ':capital'    => $capital,
                    ':prate'      => $pRate,
                    ':srate'      => $sRate,
                    ':paidprofit' => $paidProfit,
                    ':retcap'     => $returnedCap,
                    ':salesamt'   => $salesAmt,
                    ':profitamt'  => $profitAmt,
                    ':sdate'      => $sDate,
                    ':edate'      => $eDate,
                    ':status'     => $status,
                    ':notes'      => $notes,
                    ':id'         => $cId
                ]);

                if (abs($payoutDelta) > 0.01) {
                    if ($payoutDelta > 0) {
                        $capStmt = $pdo->prepare("INSERT INTO capital (amount, transaction_type, source_description, transaction_date, created_at, shop_id)
                                                  VALUES (:amt, 'برداشت', :desc, :tdate, :cdate, " . shop_id() . ")");
                        $capStmt->execute([
                            ':amt'   => $payoutDelta,
                            ':desc'  => "اصلاح مالی مضاربت #{$cId} («{$partnerName}»): افزایش پرداخت به شریک",
                            ':tdate' => date('Y-m-d'),
                            ':cdate' => date('Y-m-d H:i:s')
                        ]);
                    } else {
                        $capStmt = $pdo->prepare("INSERT INTO capital (amount, transaction_type, source_description, transaction_date, created_at, shop_id)
                                                  VALUES (:amt, 'سرمایه', :desc, :tdate, :cdate, " . shop_id() . ")");
                        $capStmt->execute([
                            ':amt'   => abs($payoutDelta),
                            ':desc'  => "اصلاح مالی مضاربت #{$cId} («{$partnerName}»): تعدیل و برگشت به سرمایه دکان",
                            ':tdate' => date('Y-m-d'),
                            ':cdate' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                if(abs($payoutDelta)>0)currency_record('capital',(int)$pdo->lastInsertId(),$currencyContext,['amount'=>abs($payoutDelta)],'create');
                currency_record('mudaraba_contracts',$cId,$currencyContext,['capital_amount'=>$capital,'paid_profit_to_partner'=>$paidProfit,'returned_capital'=>$returnedCap,'total_sales_amount'=>$salesAmt,'total_profit_amount'=>$profitAmt],'edit');
                $pdo->commit();
                log_activity($currentUser['id'], 'ویرایش قرارداد مضاربت', "ویرایش مشخصات و حساب مضاربت #{$cId} («{$partnerName}»)");
                set_flash(__t('Mudaraba contract details updated successfully.', 'مشخصات و حساب قرارداد مضاربت با موفقیت ذخیره و به‌روزرسانی گردید.', 'د مضاربت تړون معلومات په بریالیتوب سم شول.'), 'success');
                redirect(BASE_URL . 'modules/mudaraba.php');
            } catch (Exception $e) {
                if($pdo->inTransaction())$pdo->rollBack();
                set_flash(__t('Error editing contract: ', 'خطا در ویرایش قرارداد: ', 'د تړون په سمون کې تېروتنه: ') . public_error($e), 'danger');
                redirect(BASE_URL . 'modules/mudaraba.php');
            }
        }
    }
}

// 7. Handle Bulk Delete Mudaraba Contracts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete_contracts') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash(__t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!'), 'danger');
        redirect(BASE_URL . 'modules/mudaraba.php');
    }

    $selectedIds = $_POST['selected_ids'] ?? [];
    if (!is_array($selectedIds) || empty($selectedIds)) {
        set_flash(__t('Please select at least one contract to delete.', 'لطفاً حداقل یک قرارداد مضاربت را برای حذف گروهی انتخاب نمایید.', 'لږترلږه یو تړون وټاکئ.'), 'warning');
        redirect(BASE_URL . 'modules/mudaraba.php');
    }

    $cleanIds = array_filter(array_map('intval', $selectedIds), function($id) { return $id > 0; });
    if (empty($cleanIds)) {
        set_flash(__t('Invalid contract IDs selected.', 'شناسه‌های انتخاب شده نامعتبر می‌باشند.', 'ناسم پیژندونه.'), 'danger');
        redirect(BASE_URL . 'modules/mudaraba.php');
    }

    try {
        $pdo->beginTransaction();
        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        
        $unlinkStmt = $pdo->prepare("UPDATE stock SET mudaraba_id = NULL WHERE mudaraba_id IN ($placeholders) AND " . shop_sql());
        $unlinkStmt->execute($cleanIds);

        $delStmt = $pdo->prepare("DELETE FROM mudaraba_contracts WHERE id IN ($placeholders) AND " . shop_sql());
        $delStmt->execute($cleanIds);
        $deletedCount = $delStmt->rowCount();

        $pdo->commit();
        log_activity($currentUser['id'], 'حذف گروهی قراردادهای مضاربت', "حذف تعداد {$deletedCount} قرارداد مضاربت به صورت گروهی");
        set_flash(sprintf(__t('%s Mudaraba contract(s) deleted successfully.', 'تعداد %s قرارداد مضاربت با موفقیت حذف گردیدند.', '%s د مضاربت تړونونه ړنګ شول.'), ($currentLang === 'en' ? $deletedCount : to_persian_num($deletedCount))), 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash(__t('Error bulk deleting contracts: ', 'خطا در حذف گروهی قراردادها: ', 'د تړونونو په ړنګولو کې تېروتنه: ') . public_error($e), 'danger');
    }
    redirect(BASE_URL . 'modules/mudaraba.php');
}

// Fetch all Mudaraba contracts
$contracts = currency_contract_views($pdo->query("SELECT * FROM mudaraba_contracts WHERE " . shop_sql() . " ORDER BY id DESC")->fetchAll());
$contractAccountWhere=currency_sql('s');
$contractsProfitKnown=!array_filter($contracts,fn($c)=>!$c['profit_known']);

// Calculate aggregate metrics strictly isolated for Mudaraba
$totalActiveCapital  = 0;
$totalMudarabaSales  = 0;
$totalMudarabaProfit = 0;
$totalPartnerShare   = 0;
$totalShopShare      = 0;
$totalPaidToPartners = 0;

foreach ($contracts as $c) {
    if ($c['status'] === 'active') {
        $totalActiveCapital += (float)$c['capital_amount'];
    }
    $totalMudarabaSales  += (float)$c['total_sales_amount'];
    $totalMudarabaProfit += (float)$c['total_profit_amount'];
    
    $pShare = ((float)$c['total_profit_amount'] * (float)$c['partner_profit_rate']) / 100;
    $sShare = ((float)$c['total_profit_amount'] * (float)$c['shop_profit_rate']) / 100;
    
    $totalPartnerShare   += $pShare;
    $totalShopShare      += $sShare;
    $totalPaidToPartners += (float)$c['paid_profit_to_partner'];
}

$pendingPartnerPayout = max(0, $totalPartnerShare - $totalPaidToPartners);
$list=listing_count(listing_state(['newest'=>['id DESC','Newest first','تازه‌ترین اول'],'oldest'=>['id ASC','Oldest first','قدیمی‌ترین اول']]),count($contracts));if($list['sort']==='oldest')usort($contracts,fn($a,$b)=>(int)$a['id']<=>(int)$b['id']);$contracts=array_slice($contracts,$list['offset'],$list['size']);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 20px; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-handshake-angle text-primary"></i>
                <?= __t('Dedicated Mudaraba Account (Foreign Sourcing & Goods Investment)', 'حساب اختصاصی مضاربت (خریداری و فروش کالای خارجی شرکتی)', 'د مضاربت ځانګړی حساب (بهرني توکي او پانګونه)') ?>
            </h1>
            <p style="color: var(--text-muted); font-size: 13px; margin: 4px 0 0 0;">
                <?= __t('Log partner investments, import foreign electronics, record goods in inventory, and allocate profit using agreed contract terms.', 'ثبت سرمایهٔ شریک، خرید اجناس، ثبت در گدام و تخصیص مفاد طبق شرایط توافق‌شده', 'د شریک پانګه، د بهرنیو توکو اخیستل او په ګودام کې ثبتول.') ?>
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button onclick="document.getElementById('addContractModal').style.display='flex'" class="btn btn-primary" style="padding: 9px 18px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, var(--info), var(--info)); border: none; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
                <i class="fa-solid fa-file-circle-plus" style="font-size: 15px;"></i>
                <span><?= __t('New Mudaraba Contract & Goods Entry', 'عقد قرارداد جدید و ثبت مال در گدام', 'نوی قرارداد او په ګودام کې مال ثبتول') ?></span>
            </button>
            <a href="<?= BASE_URL ?>modules/stock.php?filter=mudaraba" class="btn" style="padding: 9px 16px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; background: var(--success-soft); color: var(--success); border: 1.5px solid #a7f3d0; box-shadow: 0 2px 6px rgba(16, 185, 129, 0.1);">
                <i class="fa-solid fa-boxes-stacked" style="color: var(--success); font-size: 15px;"></i>
                <span><?= __t('View Mudaraba Stock in Warehouse', 'مشاهده مال‌های مضاربت در گدام', 'په ګودام کې د مضاربت توکي کتل') ?></span>
            </a>
            <a href="<?= BASE_URL ?>modules/partners.php" class="btn" style="padding: 9px 16px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; background: var(--surface-muted); color: var(--text-main); border: 1.5px solid var(--border-dark);">
                <i class="fa-solid fa-user-group" style="color: var(--text-muted); font-size: 15px;"></i>
                <span><?= __t('General Store Partners', 'شرکاء عمومی دکان', 'د هټۍ عمومي شریکان') ?></span>
            </a>
        </div>
    </div>

    <!-- MUDARABA EXCLUSIVE METRICS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: linear-gradient(145deg, var(--surface), var(--success-soft)); border: 1.5px solid #bbf7d0; border-radius: 14px; padding: 18px; box-shadow: 0 4px 12px rgba(22, 101, 52, 0.05); position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12.5px; color: var(--success); font-weight: 700;"><?= __t('Total Active Mudaraba Capital', 'مجموع سرمایه فعال مضاربت', 'ټوله فعاله مضاربت پانګه') ?></span>
                <div style="width: 36px; height: 36px; background: var(--success-soft); color: var(--success); border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa-solid fa-vault"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: var(--success);"><?= format_money($totalActiveCapital) ?></div>
            <div style="font-size: 11.5px; color: var(--success); margin-top: 6px;"><?= __t('Dedicated capital from foreign partners', 'سرمایه اختصاصی شرکاء خارجی', 'د بهرنیو شریکانو پانګه') ?></div>
        </div>

        <div style="background: linear-gradient(145deg, var(--surface), var(--info-soft)); border: 1.5px solid #bfdbfe; border-radius: 14px; padding: 18px; box-shadow: 0 4px 12px rgba(29, 78, 216, 0.05); position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12.5px; color: var(--info); font-weight: 700;"><?= __t('Mudaraba Total Goods Revenue', 'مجموع فروشات اجناس مضاربت', 'د توکو ټول پلور') ?></span>
                <div style="width: 36px; height: 36px; background: #dbeafe; color: var(--info); border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: var(--info);"><?= format_money($totalMudarabaSales) ?></div>
            <div style="font-size: 11.5px; color: var(--info); margin-top: 6px;"><?= __t('Auto-updated upon sales invoice completion', 'ثبت خودکار هنگام صدور بل فروش', 'د بل پر مهال اتومات ثبتېږي') ?></div>
        </div>

        <div style="background: linear-gradient(145deg, var(--surface), var(--purple-soft)); border: 1.5px solid #e9d5ff; border-radius: 14px; padding: 18px; box-shadow: 0 4px 12px rgba(126, 34, 206, 0.05); position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12.5px; color: var(--purple); font-weight: 700;"><?= __t('Partners Profit Share', 'سهم مفاد شرکاء مضاربت', 'د شریکانو د ګټې ونډه') ?></span>
                <div style="width: 36px; height: 36px; background: var(--purple-soft); color: var(--purple); border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: var(--purple);"><?= currency_profit_html($totalPartnerShare,$contractsProfitKnown) ?></div>
            <div style="font-size: 11.5px; color: var(--purple); margin-top: 6px;"><?= __t('Unsettled partner balance: ', 'مانده تسویه نشده: ', 'پاتې نا تصفیه شوې: ') ?><strong style="color: <?= $pendingPartnerPayout > 0 ? 'var(--danger)' : 'var(--success)' ?>;"><?= currency_profit_html($pendingPartnerPayout,$contractsProfitKnown) ?></strong></div>
        </div>

        <div style="background: linear-gradient(145deg, var(--surface), var(--warning-soft)); border: 1.5px solid #fde68a; border-radius: 14px; padding: 18px; box-shadow: 0 4px 12px rgba(180, 83, 9, 0.05); position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 12.5px; color: var(--warning); font-weight: 700;"><?= __t('Store Net Profit from Mudaraba', 'سهم مفاد خالص دکان از مضاربت', 'له مضاربت څخه د هټۍ خالص ګټه') ?></span>
                <div style="width: 36px; height: 36px; background: var(--warning-soft); color: var(--warning); border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa-solid fa-coins"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: var(--warning);"><?= currency_profit_html($totalShopShare,$contractsProfitKnown) ?></div>
            <div style="font-size: 11.5px; color: var(--warning); margin-top: 6px;"><?= __t('Realized manager & marketing share', 'درآمد قطعی حاصل از عامل و فروشندگی', 'د هټۍ د کار او پلور ګټه') ?></div>
        </div>
    </div>

    <!-- CONTRACTS TABLE -->
    <div style="margin-top: 20px;">
        <h2 style="font-size: 16px; font-weight: 700; color: var(--text-main); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-list-check text-primary"></i> <?= __t('Mudaraba Contracts & Warehouse Inventory Records', 'لیست قراردادهای مضاربت و اجناس ثبت‌شده در گدام', 'د مضاربت تړونونه او په ګودام کې ثبت شوي توکي') ?>
        </h2>

        <!-- BULK SELECTION ACTION BAR -->
        <div id="mudarabaBulkActionBar" class="card" style="display: none; background: var(--info-soft); border: 1.5px solid var(--info); margin-bottom: 16px; padding: 12px 18px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.12);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <span style="font-weight: 800; color: var(--info); font-size: 14px;">
                        <i class="fa-solid fa-square-check text-blue-600 ml-1"></i> <span id="mudarabaSelectedCountText">0</span> <?= __t('contracts selected', 'قرارداد مضاربت انتخاب گردید', 'ټاکل شوي تړونونه') ?>
                    </span>
                    <button type="button" onclick="selectAllMudarabaRows(true)" class="btn btn-sm btn-secondary" style="background: var(--surface);">
                        <i class="fa-solid fa-list-check ml-1"></i> <?= __t('Select All', 'انتخاب همه', 'ټول وټاکئ') ?>
                    </button>
                    <button type="button" onclick="selectAllMudarabaRows(false)" class="btn btn-sm btn-secondary" style="background: var(--surface);">
                        <i class="fa-solid fa-xmark ml-1"></i> <?= __t('Deselect All', 'لغو انتخاب‌ها', 'ټاکنه لغوه کړئ') ?>
                    </button>
                </div>
                <div>
                    <button type="button" onclick="confirmBulkDeleteMudaraba()" class="btn btn-danger btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                        <i class="fa-solid fa-trash-can"></i> <?= __t('Delete Selected Contracts', 'حذف قراردادهای انتخاب شده', 'ټاکل شوي تړونونه ړنګ کړئ') ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Hidden Form for Bulk Deletion of Mudaraba Contracts -->
        <form id="mudarabaBulkDeleteForm" method="POST" action="" style="display: none;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="bulk_delete_contracts">
            <div id="mudarabaBulkDeleteInputsContainer"></div>
        </form>

        <div class="table-responsive">
            <table class="table" style="min-width: 1200px;">
                <thead>
                    <tr>
                        <th style="width: 38px; text-align: center;">
                            <input type="checkbox" id="selectAllMudarabaHeader" onchange="toggleAllMudaraba(this)" title="<?= __t('Select All', 'انتخاب همه', 'ټول ټاکل') ?>">
                        </th>
                        <th style="width: 40px; text-align: center;">#</th>
                        <th style="min-width: 140px; white-space: nowrap;"><?= __t('Investor Partner', 'شریک سرمایه‌گذار', 'پانګوال شریک') ?></th>
                        <th style="min-width: 220px; white-space: nowrap;"><?= __t('Product Specs & Project Title', 'شرح کالا و مشخصات پروژه', 'د توکو مشخصات او پروژه') ?></th>
                        <th style="text-align: center; white-space: nowrap;"><?= __t('Origin Country', 'کشور مبدا', 'اصلي هېواد') ?></th>
                        <th style="text-align: center; white-space: nowrap;"><?= __t('Initial Capital', 'سرمایه اولیه', 'لومړنۍ پانګه') ?></th>
                        <th style="text-align: center; white-space: nowrap;"><?= __t('Profit Ratio (Partner / Shop)', 'نسبت مفاد (شریک / دکان)', 'د ګټې نسبت (شریک / هټۍ)') ?></th>
                        <th style="text-align: center; white-space: nowrap;"><?= __t('Sales to Date', 'فروش تا اکنون', 'تر دې دمه پلور') ?></th>
                        <th style="text-align: center; white-space: nowrap;"><?= __t('Realized Profit', 'مفاد تحقق‌یافته', 'ترلاسه شوې ګټه') ?></th>
                        <th style="text-align: center; white-space: nowrap;"><?= __t('Partner Share / Paid', 'سهم شریک / پرداختی', 'د شریک ونډه / ورکړل شوې') ?></th>
                        <th style="text-align: center; white-space: nowrap;"><?= __t('Status', 'وضعیت', 'حالت') ?></th>
                        <th style="text-align: center; min-width: 210px; white-space: nowrap;"><?= __t('Actions', 'عملیات', 'کړنې') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contracts)): ?>
                        <tr>
                            <td colspan="12" style="text-align: center; padding: 36px 12px; color: var(--text-light);">
                                <i class="fa-solid fa-handshake-slash" style="font-size: 32px; display: block; margin-bottom: 8px; color: var(--border-dark);"></i>
                                <?= __t('No Mudaraba contracts registered yet. Click "New Mudaraba Contract" above to register your first partnership.', 'تاکنون هیچ قرارداد مضاربتی ثبت نشده است. با کلیک بر روی «عقد قرارداد جدید و ثبت مال در گدام» اولین سرمایه‌گذاری را ثبت نمایید.', 'تر اوسه هېڅ تړون نه دی ثبت شوی. لومړی تړون ثبت کړئ.') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($contracts as $idx => $c): 
                            $pShare = ((float)$c['total_profit_amount'] * (float)$c['partner_profit_rate']) / 100;
                            $remPShare = max(0, $pShare - (float)$c['paid_profit_to_partner']);
                            
                            $stkStmt = $pdo->prepare("SELECT s.*, c.name as category_name 
                                                      FROM stock s 
                                                      LEFT JOIN categories c ON s.category_id = c.id 
                                                      WHERE s.mudaraba_id = :mid AND {$contractAccountWhere} AND ".shop_sql('s')."
                                                      ORDER BY s.id DESC");
                            $stkStmt->execute([':mid' => $c['id']]);
                            $cItems = $stkStmt->fetchAll();
                        ?>
                            <tr style="background: var(--surface);">
                                <td style="text-align: center;">
                                    <input type="checkbox" class="mudaraba-chk" value="<?= $c['id'] ?>" onchange="updateMudarabaBulkBar()">
                                </td>
                                <td style="text-align: center; font-weight: 700; color: var(--text-muted);"><?= $currentLang === 'en' ? ($idx + 1) : to_persian_num($idx + 1) ?></td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 14px;">
                                        <?= sanitize($c['partner_name']) ?>
                                    </div>
                                    <?php if (!empty($c['phone'])): ?>
                                        <div style="font-size: 11.5px; color: var(--text-muted); direction: ltr; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>; margin-top: 2px;">
                                            <i class="fa-solid fa-phone" style="font-size: 10px;"></i> <?= sanitize($c['phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="min-width: 220px;">
                                    <div style="font-weight: 800; color: var(--info); font-size: 14px; margin-bottom: 4px;">
                                        <?= sanitize($c['project_title']) ?>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <div style="font-size: 12px; color: var(--text-muted); white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa-regular fa-calendar-check text-slate-400"></i>
                                            <span><?= __t('Start Date:', 'تاریخ آغاز:', 'د پیل نېټه:') ?></span>
                                            <strong style="color: var(--text-main); direction: ltr; font-family: inherit;"><?= $currentLang === 'en' ? $c['start_date'] : to_persian_num($c['start_date']) ?></strong>
                                        </div>
                                        <div>
                                            <button type="button" onclick="toggleContractStock(<?= $c['id'] ?>)" class="btn btn-sm btn-secondary" style="padding: 2px 8px; font-size: 11px; white-space: nowrap; background: var(--surface-muted); border: 1px solid var(--border-dark);">
                                                <i class="fa-solid fa-boxes-stacked ml-1 text-primary"></i> <?= sprintf(__t('Warehouse Items (%s)', 'اجناس در گدام (%s)', 'په ګودام کې توکي (%s)'), ($currentLang === 'en' ? count($cItems) : to_persian_num(count($cItems)))) ?>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <span class="badge" style="background: var(--surface-muted); color: var(--text-main); font-size: 12px;">
                                        <i class="fa-solid fa-globe ml-1 text-primary"></i> <?= sanitize(display_country($c['country_source'] ?: 'Foreign')) ?>
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: 800; color: var(--success); font-size: 14px; white-space: nowrap;">
                                    <?= format_money($c['capital_amount'], false) ?>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <span style="font-weight: 700; color: var(--text-main);">
                                        <?= $currentLang === 'en' ? ((float)$c['partner_profit_rate'] . '% / ' . (float)$c['shop_profit_rate'] . '%') : (to_persian_num((float)$c['partner_profit_rate']) . '٪ / ' . to_persian_num((float)$c['shop_profit_rate']) . '٪') ?>
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: 700; color: var(--text-main); font-size: 13px; white-space: nowrap;">
                                    <?= format_money($c['total_sales_amount'], false) ?>
                                </td>
                                <td style="text-align: center; font-weight: 800; color: var(--info); font-size: 13.5px; white-space: nowrap;">
                                    <?= currency_profit_html($c['total_profit_amount'],$c['profit_known']) ?>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <div style="font-weight: 800; color: var(--purple); font-size: 13px;">
                                        <?= __t('Partner Profit: ', 'سهم سود: ', 'د ګټې ونډه: ') ?><?= currency_profit_html($pShare,$c['profit_known']) ?>
                                    </div>
                                    <div style="font-size: 11.5px; color: var(--success); font-weight: 700; margin-top: 2px;">
                                        <?= __t('Paid Profit: ', 'سود پرداختی: ', 'ورکړل شوې ګټه: ') ?><?= format_money($c['paid_profit_to_partner'], false) ?>
                                    </div>
                                    <?php if ((float)($c['returned_capital'] ?? 0) > 0): ?>
                                        <div style="font-size: 11px; color: var(--info); font-weight: 700; margin-top: 1px;">
                                            <?= __t('Principal Returned: ', 'اصل مسترد: ', 'بېرته ورکړل شوې: ') ?><?= format_money($c['returned_capital'], false) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php 
                                    $remCapital = max(0, (float)$c['capital_amount'] - (float)($c['returned_capital'] ?? 0));
                                    $remProfit = max(0, $pShare - (float)($c['paid_profit_to_partner'] ?? 0));
                                    $totalRem = $remCapital + $remProfit;
                                    ?>
                                    <div style="font-size: 11.5px; color: <?= $totalRem > 0 ? 'var(--danger)' : 'var(--success)' ?>; font-weight: 800; margin-top: 3px; background: <?= $totalRem > 0 ? 'var(--danger-soft)' : 'var(--success-soft)' ?>; border-radius: 4px; padding: 2px 4px;">
                                        <?= __t('Total Due: ', 'طلب کل: ', 'ټولټال طلب: ') ?><?= currency_profit_html($totalRem,$c['profit_known']) ?>
                                    </div>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <?php if ($c['status'] === 'active'): ?>
                                        <span class="badge badge-success"><?= __t('Active Contract', 'جاری و فعال', 'فعال تړون') ?></span>
                                    <?php elseif ($c['status'] === 'settled'): ?>
                                        <span class="badge badge-secondary"><?= __t('Fully Settled', 'تسویه شده', 'تصفیه شوی') ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"><?= sanitize(display_mudaraba_status($c['status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 4px; justify-content: center; flex-wrap: wrap;">
                                        <!-- Edit Contract Button -->
                                        <button type="button" class="btn btn-sm btn-secondary" style="background: var(--surface-muted); color: var(--text-main); padding: 4px 8px;"
                                                data-contract="<?= htmlspecialchars(json_for_html($c['_native']), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="openEditContractModalFromBtn(this)"
                                                title="<?= __t('Edit Contract Details & Accounts', 'ویرایش قرارداد و حساب مالی', 'د تړون او حساب سمول') ?>">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>

                                        <!-- Add More Stock to this contract -->
                                        <button type="button" class="btn btn-sm btn-secondary" style="background: var(--info-soft); color: var(--info); border-color: #bae6fd; padding: 4px 8px;"
                                                data-add-stock="<?= htmlspecialchars(json_for_html([
                                                    'id' => (int)$c['id'],'currency_code'=>$c['currency_code'],
                                                    'partner_name' => $c['partner_name'],
                                                    'country_source' => $c['country_source'] ?? ''
                                                ]), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="openAddStockModalFromBtn(this)" title="<?= __t('Add New Item to Contract', 'افزودن جنس جدید به این قرارداد', 'تړون ته نوی جنس اضافه کړئ') ?>">
                                            <i class="fa-solid fa-plus"></i> <?= __t('Item', 'مال', 'مال') ?>
                                        </button>

                                        <!-- Update Sales Progress Button -->
                                        <button type="button" class="btn btn-sm btn-primary" style="padding: 4px 8px;"
                                                data-progress="<?= htmlspecialchars(json_for_html([
                                                    'id' => (int)$c['id'],'currency_code'=>$c['currency_code'],
                                                    'project_title' => $c['project_title'],
                                                    'partner_rate' => (float)$c['partner_profit_rate'],
                                                    'shop_rate' => (float)$c['shop_profit_rate']
                                                ]), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="openProgressModalFromBtn(this)" title="<?= __t('Log Manual Sales & Profit', 'ثبت دستی فروش و مفاد', 'پلور او ګټه ثبت کړئ') ?>">
                                            <i class="fa-solid fa-chart-line"></i>
                                        </button>

                                        <!-- Payout to Partner Button -->
                                        <button type="button" class="btn btn-sm btn-secondary" style="background: var(--success); color: var(--surface); padding: 4px 8px;"
                                                data-payout="<?= htmlspecialchars(json_for_html([
                                                    'id' => (int)$c['id'],'currency_code'=>$c['currency_code'],
                                                    'partner_name' => $c['partner_name'],
                                                    'project_title' => $c['project_title'],
                                                    'rem_profit' => $c['profit_known']?(float)$remPShare:0,'profit_known'=>$c['profit_known'],
                                                    'rem_cap' => max(0, (float)$c['capital_amount'] - (float)($c['returned_capital'] ?? 0)),
                                                    'paid_profit' => (float)($c['paid_profit_to_partner'] ?? 0),
                                                    'returned_capital' => (float)($c['returned_capital'] ?? 0)
                                                ]), ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="openPayoutModalFromBtn(this)" title="<?= __t('Payout & Settle with Partner', 'پرداخت و تسویه با شریک', 'له شریک سره تصفیه او پیسې ورکول') ?>">
                                            <i class="fa-solid fa-money-bill-wave"></i>
                                        </button>

                                        <!-- Print Mudaraba Statement Button -->
                                        <a href="<?= BASE_URL ?>print/mudaraba.php?id=<?= $c['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding: 4px 8px;" title="<?= __t('Print Mudaraba Contract', 'چاپ سند و قرارداد مضاربت', 'د مضاربت سند چاپ کړئ') ?>">
                                            <i class="fa-solid fa-print"></i>
                                        </a>

                                        <!-- Delete Button -->
                                        <form method="POST" action="" style="display: inline; margin: 0;" onsubmit="return confirm('<?= __t('Are you sure you want to delete this Mudaraba contract? (Warehouse goods will be unlinked from Mudaraba).', 'آیا از حذف این قرارداد مضاربت مطمئن هستید؟ (اجناس گدام از مضاربت جدا می‌شوند)', 'ایا باوري یاست چې دا تړون ړنګ کړئ؟') ?>');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_contract">
                                            <input type="hidden" name="contract_id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" style="padding: 4px 8px;" title="<?= __t('Delete Contract', 'حذف قرارداد', 'تړون ړنګ کړئ') ?>">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <!-- Expandable Stock Items Table for this Contract -->
                            <tr id="contract_stock_row_<?= $c['id'] ?>" style="display: none; background: var(--surface-muted);">
                                <td colspan="12" style="padding: 12px 18px;">
                                    <div style="background: var(--surface); border: 1.5px solid var(--border-dark); border-radius: 8px; padding: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.03);">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                            <h4 style="margin: 0; font-size: 13.5px; font-weight: 800; color: var(--success); display: flex; align-items: center; gap: 6px;">
                                                <i class="fa-solid fa-boxes-stacked"></i>
                                                <?= sprintf(__t('Warehouse items tied to Mudaraba contract with «%s»:', 'اجناس موجود در گدام مربوط به حساب مضاربت با «%s»:', 'په ګودام کې له «%s» سره د مضاربت اړوند توکي:'), sanitize($c['partner_name'])) ?>
                                            </h4>
                                            <button type="button" onclick="openAddStockModal(<?= $c['id'] ?>, <?= sanitize(json_for_html($c['partner_name'])) ?>, <?= sanitize(json_for_html($c['country_source'])) ?>)" class="btn btn-sm btn-primary" style="font-size: 11.5px; padding: 3px 10px;">
                                                <i class="fa-solid fa-plus ml-1"></i> <?= __t('Register New Item for this Contract', 'ثبت قلم جنس جدید برای این قرارداد', 'د دې تړون لپاره نوی جنس ثبت کړئ') ?>
                                            </button>
                                        </div>

                                        <?php if (empty($cItems)): ?>
                                            <div style="padding: 12px; text-align: center; color: var(--text-light); font-size: 12.5px;">
                                                <?= __t('No inventory items logged for this contract yet. Use button above to register imported goods.', 'هنوز جنسی برای این قرارداد در گدام ثبت نشده است. می‌توانید با دکمه بالا جنس جدید ثبت نمایید.', 'تر اوسه هېڅ جنس نه دی ثبت شوی.') ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table" style="margin: 0; font-size: 12.5px;">
                                                    <thead>
                                                        <tr style="background: var(--surface-muted);">
                                                            <th>#</th>
                                                            <th><?= __t('Product Name', 'اسم جنس', 'د توکي نوم') ?></th>
                                                            <th><?= __t('Category', 'دسته‌بندی', 'کټګوري') ?></th>
                                                            <th><?= __t('Specifications','مشخصات (اندازه، رنگ، بسته‌بندی)','ځانګړنې') ?></th>
                                                            <th style="text-align: center;"><?= __t('Stock Remaining', 'موجودی فعلی در گدام', 'اوسنۍ موجودي') ?></th>
                                                            <th style="text-align: center;"><?= __t('Cost Price', 'نرخ خرید', 'پېرود نرخ') ?></th>
                                                            <th style="text-align: center;"><?= __t('Selling Price', 'نرخ پیشنهادی فروش', 'د پلور نرخ') ?></th>
                                                            <th style="text-align: center;"><?= __t('Stock Total Value', 'ارزش موجودی گدام', 'د ګودام ارزښت') ?></th>
                                                            <th style="text-align: center;"><?= __t('Location', 'موقعیت در دکان', 'ځای په هټۍ کې') ?></th>
                                                            <th style="text-align: center;"><?= __t('Warehouse Tag', 'برچسب در گدام', 'په ګودام کې نښه') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $subIdx = 1;
                                                        foreach ($cItems as $sItem): 
                                                            $totalItemVal = inventory_value($sItem);
                                                        ?>
                                                            <tr>
                                                                <td><?= $currentLang === 'en' ? $subIdx++ : to_persian_num($subIdx++) ?></td>
                                                                <td style="font-weight: 700; color: var(--text-main);">
                                                                    <?= sanitize($sItem['name']) ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge badge-secondary"><?= sanitize($sItem['category_name'] ? $sItem['category_name'] : __t('Uncategorized', 'بدون دسته', 'بې کټګورۍ')) ?></span>
                                                                </td>
                                                                <td style="color: var(--text-muted); max-width: 200px;">
                                                                    <?= sanitize($sItem['specifications'] ?: '-') ?>
                                                                </td>
                                                                <td style="text-align: center; font-weight: 800;">
                                                                    <?php if ((float)$sItem['quantity'] <= 0): ?>
                                                                        <span class="badge badge-danger"><?= __t('Sold Out (0)', 'تمام شده (۰)', 'خلاص شوی (۰)') ?></span>
                                                                    <?php else: ?>
                                                                        <span style="color: var(--success); font-size: 13.5px;"><?= display_qty_unit($sItem['quantity'],$sItem['unit']) ?></span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td style="text-align: center;"><?= format_money($sItem['purchase_price'], false) ?></td>
                                                                <td style="text-align: center; color: var(--info); font-weight: 700;"><?= currency_format($sItem['sale_price'],$sItem['sale_currency_code']) ?></td>
                                                                <td style="text-align: center; font-weight: 800; color: var(--success);"><?= format_money($totalItemVal, false) ?></td>
                                                                <td style="text-align: center;"><?= sanitize(display_stock_location($sItem['warehouse_location'] ?: '-')) ?></td>
                                                                <td style="text-align: center;">
                                                                    <span class="badge" style="background: var(--success-soft); color: var(--success); border: 1px solid #6ee7b7; font-size: 11px;">
                                                                        <?= sprintf(__t('Mudaraba with %s', 'حساب مضاربت با %s', 'له %s سره مضاربت'), sanitize($c['partner_name'])) ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL 1: ADD MUDARABA CONTRACT & AUTO SAVE GOODS TO STOCK -->
<div id="addContractModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: var(--surface); border-radius: 16px; max-width: 680px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px; position: relative; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 16px; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-handshake text-primary"></i> <?= __t('New Mudaraba Contract & Goods Intake', 'عقد قرارداد جدید مضاربت و ثبت مال در گدام', 'د مضاربت نوی تړون او مال ثبتول') ?>
            </h3>
            <button type="button" onclick="document.getElementById('addContractModal').style.display='none'" style="background: none; border: none; font-size: 18px; color: var(--text-muted); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_mudaraba">
            <?php currency_fields(['purchase_price'=>6,'sale_price'=>6,'expenses'=>6,'capital_amount'=>2]); ?>
            <input type="hidden" name="purchase_request_key" value="<?= bin2hex(random_bytes(16)) ?>">
            <label class="form-label"><?= __t('Measurement unit','واحد اندازه‌گیری','د اندازې واحد') ?><input class="form-control" name="unit" maxlength="24" value="unit" required></label>
            <label class="form-label">SKU<input class="form-control" name="sku" maxlength="128"></label>

            <!-- Section 1: Partner Info -->
            <div style="background: var(--surface-muted); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; margin-bottom: 14px;">
                <div style="font-weight: 800; font-size: 13px; color: var(--text-main); margin-bottom: 8px;">
                    <i class="fa-solid fa-user-tie text-primary ml-1"></i> <?= __t('1. Investor Partner Profile (Rabb-ul-Mal):', '۱. مشخصات شریک سرمایه‌گذار (رب‌المال):', '۱. د پانګوال شریک مشخصات:') ?>
                </div>
                <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Partner Name: *', 'نام شریک: *', 'د شریک نوم: *') ?></label>
                        <input type="text" name="partner_name" class="form-control" required placeholder="<?= __t('e.g. Haji Habibullah', 'مثلاً: حاجی نصرت‌الله', 'لکه: حاجي نصرت الله') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Phone Number:', 'شماره تماس:', 'د تلیفون شمېره:') ?></label>
                        <input type="text" name="phone" class="form-control" placeholder="07xxxxxxxx" style="direction: ltr; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
                    </div>
                </div>
            </div>

            <!-- Section 2: Goods & Warehouse Specifications -->
            <div style="background: var(--success-soft); border: 1.5px solid #86efac; border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                <div style="font-weight: 800; font-size: 13px; color: var(--success); margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="fa-solid fa-boxes-stacked ml-1"></i> <?= __t('2. Purchased Commercial Goods (Auto-saved to Warehouse):', '۲. مشخصات مال خریداری‌شده (ثبت مستقیم در گدام):', '۲. پېرل شوي توکي (مستقیم ګودام ته داخلېږي):') ?></span>
                    <span class="badge" style="background: var(--success-soft); color: var(--success); font-size: 11px;"><?= __t('Direct Intake into Stock', 'ذخیره مستقیم در گدام دکان', 'مستقیم ګودام ته داخلول') ?></span>
                </div>

                <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Product Name & Model: *', 'نام جنس و مدل: *', 'د جنس نوم او ماډل: *') ?></label>
                        <input type="text" id="m_product_name" name="product_name" class="form-control" required placeholder="<?= __t('Product name or trade item', 'نام جنس یا کالای تجارتی', 'د سوداګریز توکي نوم') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Warehouse Category:', 'دسته‌بندی در گدام:', 'په ګودام کې کټګوري:') ?></label>
                        <select name="category_id" class="form-control">
                            <option value=""><?= __t('-- Uncategorized --', '-- بدون دسته‌بندی --', '-- بې کټګورۍ --') ?></option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Product specifications (size, color, model, packaging):','مشخصات جنس (اندازه، رنگ، مدل، بسته‌بندی):','ځانګړنې (اندازه، رنګ، ماډل، بسته):') ?></label>
                    <textarea name="specifications" class="form-control" rows="2" placeholder="<?= __t('e.g. medium size, blue, box of 12','مثلاً: اندازهٔ متوسط، آبی، بستهٔ ۱۲ عددی','لکه: منځنۍ اندازه، شین رنګ، د ۱۲ توکو بسته') ?>"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Quantity Purchased: *', 'تعداد خریداری شده: *', 'د پېرل شوو شمېر: *') ?></label>
                        <input type="number" id="m_quantity" name="quantity" class="form-control" min="0.001" value="1" required oninput="recalcMudarabaCapital()" step="0.001">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Unit Cost Price (%s): *', 'نرخ خرید فی دانه (%s): *', 'د یوه دانې د پېرود نرخ (%s): *'), get_currency_label()) ?></label>
                        <input type="number" step="any" id="m_purchase_price" name="purchase_price" class="form-control" min="0" required placeholder="0" oninput="recalcMudarabaCapital()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Target Sale Price (%s):', 'نرخ فروش فی دانه (%s):', 'د پلور نرخ (%s):'), get_currency_label()) ?></label>
                        <input type="number" step="any" name="sale_price" class="form-control" min="0" placeholder="0">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Freight & Customs Fee (%s):', 'مصارف سرجمع فی دانه (%s):', 'ګمرک او لګښت (%s):'), get_currency_label()) ?></label>
                        <input type="number" step="any" id="m_expenses" name="expenses" class="form-control" min="0" value="0" placeholder="0" oninput="recalcMudarabaCapital()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Country of Origin:', 'کشور مبدأ خریداری:', 'اصلي هېواد:') ?></label>
                        <select name="country_source" class="form-control">
                            <option value="دوبی (امارات)"><?= __t('Dubai (UAE)', 'دوبی (امارات)', 'دوبۍ (امارات)') ?></option>
                            <option value="چین"><?= __t('China', 'چین', 'چین') ?></option>
                            <option value="ترکیه"><?= __t('Turkey', 'ترکیه', 'ترکیه') ?></option>
                            <option value="ایران"><?= __t('Iran', 'ایران', 'ایران') ?></option>
                            <option value="پاکستان"><?= __t('Pakistan', 'پاکستان', 'پاکستان') ?></option>
                            <option value="جاپان"><?= __t('Japan', 'جاپان', 'جاپان') ?></option>
                            <option value="سایر"><?= __t('Other Countries', 'سایر کشورها', 'نور هېوادونه') ?></option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Storage Shelf / Cabinet:', 'موقعیت در گدام/دکان:', 'ځای په ګودام کې:') ?></label>
                        <input type="text" name="warehouse_location" class="form-control" placeholder="<?= __t('e.g. Cabinet 1, Shelf B', 'قفسه A، الماری ۱...', 'المارۍ ۱...') ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px; margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;"><?= __t('Barcode / Serial (Optional):', 'بارکد / سریال (اختیاری):', 'بارکوډ یا سریال شمېره:') ?></label>
                    <input type="text" name="barcode" class="form-control" placeholder="Barcode / Serial">
                </div>
            </div>

            <!-- Section 3: Financials & Shariah Profit Split -->
            <div style="background: var(--purple-soft); border: 1px solid #e9d5ff; border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                <div style="font-weight: 800; font-size: 13px; color: #581c87; margin-bottom: 8px;">
                    <i class="fa-solid fa-scale-balanced text-primary ml-1"></i> <?= __t('3. Partner Capital & Agreed Profit Split:', '۳. سرمایهٔ شریک و تقسیم مفاد توافق‌شده:', '۳. د شریک پانګه او موافقه شوی د ګټې وېش:') ?>
                </div>

                <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Total Partner Capital Amount (%s): *', 'مجموع سرمایه نقدی شریک (%s): *', 'د شریک ټوله پانګه (%s): *'), get_currency_label()) ?></label>
                        <input type="number" step="any" id="m_capital_amount" name="capital_amount" class="form-control" required style="font-weight: 800; color: var(--success); font-size: 14px;">
                        <small style="color: var(--text-muted); font-size: 11px;"><?= __t('* Calculated automatically from quantity x total unit cost.', '* خودبخود از ضرب تعداد در تمام‌شد جنس محاسبه می‌گردد.', '* اتومات له ټولټال لګښت څخه محاسبه کېږي.') ?></small>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Mudaraba Start Date:', 'تاریخ شروع مضاربت:', 'د پیل نېټه:') ?></label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div style="background: var(--surface); border: 1px solid #d8b4fe; border-radius: 8px; padding: 10px;">
                    <span style="font-size: 12px; font-weight: 700; color: var(--purple); display: block; margin-bottom: 6px;">
                        <?= __t('Agreed Profit Sharing Ratio from Goods Sales:', 'نسبت توافقی تقسیم سود حاصل از فروش کالا:', 'د پلور د ګټې توافقي وېش:') ?>
                    </span>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <span style="font-size: 11.5px; color: var(--text-muted);"><?= __t('Investor Partner Share (Rabb-ul-Mal):', 'سهم سود شریک (رب‌المال):', 'د شریک د ګټې سلنه:') ?></span>
                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                                <input type="number" id="mudaraba_p_rate" name="partner_profit_rate" class="form-control" value="50" min="0" max="100" style="text-align: center; font-weight: bold;" oninput="document.getElementById('mudaraba_s_rate').value = Math.max(0, 100 - parseFloat(this.value || 0));">
                                <span style="font-weight: bold;">%</span>
                            </div>
                        </div>
                        <div>
                            <span style="font-size: 11.5px; color: var(--text-muted);"><?= __t('Store Manager Share (Mudarib):', 'سهم سود دکان (عامل / مضارب):', 'د هټۍ د ګټې سلنه:') ?></span>
                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                                <input type="number" id="mudaraba_s_rate" name="shop_profit_rate" class="form-control" value="50" min="0" max="100" style="text-align: center; font-weight: bold;">
                                <span style="font-weight: bold;">%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Special Terms & Agreement Notes:', 'توضیحات و شرایط ویژه قرارداد:', 'د تړون ځانګړي شرایط:') ?></label>
                <textarea name="notes" class="form-control" rows="2" placeholder="<?= __t('Optional notes regarding settlement deadlines, currency exchange rates...', 'توضیحات اختیاری در مورد توافقات، نرخ ارز، نحوه تصفیه...', 'د تصفیې او نرخونو په اړه یادښت...') ?>"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('addContractModal').style.display='none'" class="btn btn-secondary">
                    <?= __t('Cancel', 'انصراف', 'لغوه') ?>
                </button>
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                    <i class="fa-solid fa-check ml-1"></i> <?= __t('Save Contract & Register Goods to Stock', 'ثبت قرارداد و ذخیره جنس در گدام', 'تړون او توکي په ګودام کې ثبت کړئ') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: ADD EXTRA GOODS TO EXISTING CONTRACT -->
<div id="addStockModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: var(--surface); border-radius: 16px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px; position: relative; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 15px; font-weight: 800; color: var(--text-main); margin: 0;">
                <i class="fa-solid fa-plus text-primary ml-1"></i> <?= __t('Add Imported Item to Contract: ', 'ثبت جنس جدید برای قرارداد مضاربت: ', 'تړون ته نوی جنس اضافه کړئ: ') ?><span id="add_stock_partner_name" style="color: var(--info);"></span>
            </h3>
            <button type="button" onclick="document.getElementById('addStockModal').style.display='none'" style="background: none; border: none; font-size: 18px; color: var(--text-muted); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_contract_item">
            <?php currency_fields(['purchase_price'=>6,'sale_price'=>6,'expenses'=>6]); ?>
            <input type="hidden" name="purchase_request_key" value="<?= bin2hex(random_bytes(16)) ?>">
            <label class="form-label"><?= __t('Measurement unit','واحد اندازه‌گیری','د اندازې واحد') ?><input class="form-control" name="unit" maxlength="24" value="unit" required></label>
            <label class="form-label">SKU<input class="form-control" name="sku" maxlength="128"></label>
            <input type="hidden" id="add_stock_contract_id" name="contract_id" value="">

            <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Product Name & Model: *', 'نام جنس و مدل: *', 'د جنس نوم او ماډل: *') ?></label>
                    <input type="text" name="product_name" class="form-control" required placeholder="<?= __t('Product name or SKU...', 'نام یا کد جنس...', 'د توکي نوم یا کوډ...') ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Category:', 'دسته‌بندی:', 'کټګوري:') ?></label>
                    <select name="category_id" class="form-control">
                        <option value=""><?= __t('-- Uncategorized --', '-- بدون دسته‌بندی --', '-- بې کټګورۍ --') ?></option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Specifications (Color, Model, Serial):', 'مشخصات جنس (رنگ، مدل، سریال):', 'مشخصات (رنګ، ماډل):') ?></label>
                <input type="text" name="specifications" class="form-control" placeholder="<?= __t('Appearance and technical specs', 'مشخصات ظاهری و فنی', 'تخنیکي مشخصات') ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Quantity (units): *', 'تعداد (عدد): *', 'شمېر (دانې): *') ?></label>
                    <input type="number" name="quantity" class="form-control" min="0.001" value="1" required step="0.001">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Cost Price (%s): *', 'نرخ خرید (%s): *', 'د پېرود نرخ (%s): *'), get_currency_label()) ?></label>
                    <input type="number" step="any" name="purchase_price" class="form-control" min="0" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Selling Price (%s):', 'نرخ فروش (%s):', 'د پلور نرخ (%s):'), get_currency_label()) ?></label>
                    <input type="number" step="any" name="sale_price" class="form-control" min="0">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Storage Location in Shop:', 'موقعیت در گدام:', 'ځای په ګودام کې:') ?></label>
                    <input type="text" name="warehouse_location" class="form-control" placeholder="<?= __t('Shelf, Cabinet...', 'قفسه، الماری...', 'المارۍ...') ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Purchase Date:', 'تاریخ خریداری:', 'د پېرود نېټه:') ?></label>
                    <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('addStockModal').style.display='none'" class="btn btn-secondary">
                    <?= __t('Cancel', 'انصراف', 'لغوه') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus ml-1"></i> <?= __t('Add Item to Warehouse', 'افزودن جنس به گدام', 'توکی ګودام ته داخل کړئ') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 3: RECORD SALES PROGRESS -->
<div id="progressModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: var(--surface); border-radius: 16px; max-width: 460px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 15px; font-weight: 800; color: var(--text-main); margin: 0;">
                <i class="fa-solid fa-chart-line text-primary ml-1"></i> <?= __t('Log Manual Sales & Profit for Mudaraba', 'ثبت دستی فروش و مفاد مضاربت', 'د پلور او ګټې لاسي ثبتول') ?>
            </h3>
            <button type="button" onclick="document.getElementById('progressModal').style.display='none'" style="background: none; border: none; font-size: 18px; color: var(--text-muted); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_progress">
            <?php currency_fields(['add_sales'=>2,'add_profit'=>2]); ?>
            <input type="hidden" id="progress_contract_id" name="contract_id" value="">

            <div style="background: var(--surface-muted); padding: 10px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; color: var(--text-muted);">
                <?= __t('Contract: ', 'قرارداد: ', 'تړون: ') ?><strong id="progress_project_title" style="color: var(--info);"></strong>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Sales Revenue Added (%s):', 'مبلغ فروش اضافه شده (%s):', 'اضافه شوی پلور (%s):'), get_currency_label()) ?></label>
                <input type="number" step="any" min="0" name="add_sales" class="form-control" required placeholder="0">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Net Profit Realized from this Sale (%s):', 'مفاد خالص تحقق‌یافته از این فروش (%s):', 'له دې پلور خالص ګټه (%s):'), get_currency_label()) ?></label>
                <input type="number" step="any" min="0" id="progress_add_profit" name="add_profit" class="form-control" required placeholder="0" style="color: var(--info); font-weight: bold;" oninput="recalcProgressSplit();">
            </div>

            <div id="progress_split_box" style="display: none; background: var(--success-soft); border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px; margin-bottom: 14px; font-size: 12px;">
                <div style="color: var(--success); font-weight: 700; margin-bottom: 4px;"><?= __t('Profit split according to contract ratio:', 'تقسیم مفاد بر اساس درصد قرارداد:', 'د تړون له مخې د ګټې وېش:') ?></div>
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
                    <span><?= __t('Partner Share', 'سهم سرمایه‌گذار', 'د شریک ونډه') ?> (<span id="progress_p_rate">50</span>%): <strong id="progress_p_share" style="color: var(--purple);">0</strong> <span data-currency-label><?= get_currency_label() ?></span></span>
                    <span><?= __t('Store Share', 'سهم دکان', 'د هټۍ ونډه') ?> (<span id="progress_s_rate">50</span>%): <strong id="progress_s_share" style="color: var(--success);">0</strong> <span data-currency-label><?= get_currency_label() ?></span></span>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('progressModal').style.display='none'" class="btn btn-secondary">
                    <?= __t('Cancel', 'انصراف', 'لغوه') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus ml-1"></i> <?= __t('Add to Sales Statistics', 'افزودن به آمار فروش', 'د پلور په شمېرو کې ثبت کړئ') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 4: PAYOUT TO PARTNER -->
<div id="payoutModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: var(--surface); border-radius: 16px; max-width: 460px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 15px; font-weight: 800; color: var(--text-main); margin: 0;">
                <i class="fa-solid fa-money-bill-wave text-success ml-1"></i> <?= __t('Disburse Payment & Settle with Partner', 'پرداخت و تسویه با شریک مضاربت', 'شریک ته پیسې ورکول او تصفیه کول') ?>
            </h3>
            <button type="button" onclick="document.getElementById('payoutModal').style.display='none'" style="background: none; border: none; font-size: 18px; color: var(--text-muted); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="payout_partner">
            <?php currency_fields(['payout_profit'=>2,'return_capital'=>2]); ?>
            <input type="hidden" id="payout_contract_id" name="contract_id" value="">

            <div style="background: var(--surface-muted); padding: 10px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; color: var(--text-main);">
                <?= __t('Partner: ', 'شریک: ', 'شریک: ') ?><strong id="payout_partner_name"></strong><br>
                <span style="font-size: 11.5px; color: var(--text-muted);">
                    <?= __t('Remaining Profit: ', 'مانده سهم سود: ', 'پاتې ګټه: ') ?><strong id="payout_rem_profit" style="color: var(--purple);"></strong> |
                    <?= __t('Remaining Principal Capital: ', 'مانده اصل سرمایه: ', 'پاتې اصلي پانګه: ') ?><strong id="payout_rem_cap" style="color: var(--success);"></strong>
                </span>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Profit Payout to Partner (%s):', 'پرداخت سود به شریک (%s):', 'شریک ته د ګټې تادیه (%s):'), get_currency_label()) ?></label>
                <input type="number" step="any" min="0" id="payout_profit_inp" name="payout_profit" class="form-control" value="0" oninput="updatePayoutTotal();">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Principal Capital Return (%s):', 'استرداد / بازگرداندن اصل سرمایه (%s):', 'د اصلي پانګې بېرته ورکړه (%s):'), get_currency_label()) ?></label>
                <input type="number" step="any" min="0" id="return_cap_inp" name="return_capital" class="form-control" value="0" oninput="updatePayoutTotal();">
            </div>

            <!-- Preview Total Payout -->
            <div style="background: var(--success-soft); border: 1.5px solid #a7f3d0; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 12.5px; font-weight: 700; color: var(--success);">
                    <i class="fa-solid fa-coins ml-1"></i> <?= __t('Total Payout (Deducted from Store Capital):', 'مجموع پرداختی به شریک (کسر از سرمایه دکان):', 'ټولټال ورکړه (له پانګې کمه شوې):') ?>
                </span>
                <strong id="payout_total_preview" style="font-size: 15px; font-weight: 900; color: var(--success);">0 <?= get_currency_label() ?></strong>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Payment / Receipt Date:', 'تاریخ پرداخت / رسید:', 'د ورکړې نېټه:') ?></label>
                <input type="date" name="payout_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <div style="background: var(--success-soft); border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px; margin-bottom: 14px; font-size: 12px; color: var(--success);">
                <i class="fa-solid fa-shield-check ml-1"></i> <?= __t('Per accounting standards, this disbursement will deduct directly from general store capital to balance ledger cash flow.', 'با ثبت پرداخت، این مبلغ مطابق استاندارد حسابداری مستقیماً از سرمایه عمومی دکان کسر و ترازنامه متوازن می‌شود.', 'دا پیسې به د محاسبې د معیار له مخې د هټۍ له عمومي پانګې کمې شي.') ?>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" name="mark_settled" value="1">
                    <span><?= __t('Mark contract as fully settled and closed', 'قرارداد کاملاً تسویه و خاتمه یافته علامت‌گذاری شود', 'تړون په بشپړه توګه تصفیه او ختم وټاکئ') ?></span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('payoutModal').style.display='none'" class="btn btn-secondary">
                    <?= __t('Cancel', 'انصراف', 'لغوه') ?>
                </button>
                <button type="submit" class="btn btn-primary" style="background: var(--success); border-color: var(--success);">
                    <i class="fa-solid fa-check ml-1"></i> <?= __t('Confirm & Disburse Funds', 'ثبت پرداخت وجه', 'ورکړه ثبت کړئ') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 5: EDIT MUDARABA CONTRACT -->
<div id="editContractModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
    <div style="background: var(--surface); border-radius: 16px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); padding: 24px; position: relative; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="font-size: 16px; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-pen-to-square text-primary"></i> <?= __t('Edit Mudaraba Contract', 'ویرایش قرارداد مضاربت', 'د مضاربت تړون سمول') ?>
            </h3>
            <button type="button" onclick="closeEditContractModal();" style="background: none; border: none; font-size: 18px; color: var(--text-muted); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_contract">
            <?php currency_fields(['capital_amount'=>2,'paid_profit_to_partner'=>2,'returned_capital'=>2,'total_sales_amount'=>2,'total_profit_amount'=>2]); ?>
            <input type="hidden" name="contract_id" id="edit_contract_id" value="0">

            <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Partner Name (Investor): *', 'نام شریک (سرمایه‌گذار): *', 'د شریک نوم: *') ?></label>
                    <input type="text" name="partner_name" id="edit_c_partner_name" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Phone Number:', 'شماره تماس:', 'د تلیفون شمېره:') ?></label>
                    <input type="text" name="phone" id="edit_c_phone" class="form-control" style="direction: ltr; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Project Title & Description: *', 'شرح کالا و عنوان پروژه: *', 'د پروژې نوم او تفصیل: *') ?></label>
                <input type="text" name="project_title" id="edit_c_project_title" class="form-control" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Country of Origin:', 'کشور مبدا:', 'اصلي هېواد:') ?></label>
                    <input type="text" name="country_source" id="edit_c_country_source" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= sprintf(__t('Total Principal Capital (%s): *', 'مقدار کل سرمایه (%s): *', 'ټوله اصلي پانګه (%s): *'), get_currency_label()) ?></label>
                    <input type="number" step="any" name="capital_amount" readonly id="edit_c_capital_amount" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Partner Profit Share (%):', 'سهم مفاد شریک (٪):', 'د شریک د ګټې ونډه (%):') ?></label>
                    <input type="number" step="any" name="partner_profit_rate" id="edit_c_partner_rate" class="form-control" required oninput="document.getElementById('edit_c_shop_rate').value = Math.max(0, 100 - parseFloat(this.value || 0));">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Store Profit Share (%):', 'سهم مفاد دکان (٪):', 'د هټۍ د ګټې ونډه (%):') ?></label>
                    <input type="number" step="any" name="shop_profit_rate" id="edit_c_shop_rate" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Start Date:', 'تاریخ شروع:', 'د پیل نېټه:') ?></label>
                    <input type="date" name="start_date" id="edit_c_start_date" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Settlement Due Date:', 'موعد تسویه:', 'د تصفیې نېټه:') ?></label>
                    <input type="date" name="settlement_date" id="edit_c_settlement_date" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Contract Status:', 'وضعیت قرارداد:', 'د تړون حالت:') ?></label>
                    <select name="status" id="edit_c_status" class="form-control">
                        <option value="active"><?= __t('Active & Operational', 'جاری و فعال', 'فعال او جاري') ?></option>
                        <option value="settled"><?= __t('Fully Settled', 'تسویه شده', 'تصفیه شوی') ?></option>
                        <option value="cancelled"><?= __t('Cancelled', 'لغو شده', 'لغوه شوی') ?></option>
                    </select>
                </div>
            </div>

            <!-- Financials and Paid-out adjustments -->
            <div style="background: var(--surface-muted); border: 1.5px solid var(--border-dark); border-radius: 10px; padding: 12px; margin-bottom: 14px;">
                <div style="font-weight: 800; font-size: 13px; color: var(--text-main); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-coins text-amber-500"></i> <?= __t('Financial Ledger, Paid Profit & Returned Capital:', 'مبالغ مالی، سود پرداختی و استرداد سرمایه (رسیدها):', 'مالي مبالغ او ورکړل شوې پیسې:') ?>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px; color: var(--success);">
                            <?= __t('Profit Disbursed to Partner:', 'سود پرداخت شده به شریک (رسید):', 'شریک ته ورکړل شوې ګټه:') ?>
                        </label>
                        <input type="number" step="any" min="0" name="paid_profit_to_partner" readonly id="edit_c_paid_profit" class="form-control" style="font-weight: bold; color: var(--success);">
                        <span style="font-size: 11px; color: var(--text-muted);"><?= __t('(Profits already handed over to partner)', '(مفادی که تا کنون به شریک تسلیم شده)', '(هغه ګټه چې ورکړل شوې)') ?></span>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px; color: var(--info);">
                            <?= __t('Principal Capital Returned:', 'اصل سرمایه استرداد شده (پس داده شده):', 'بېرته ورکړل شوې اصلي پانګه:') ?>
                        </label>
                        <input type="number" step="any" min="0" name="returned_capital" readonly id="edit_c_returned_capital" class="form-control" style="font-weight: bold; color: var(--info);">
                        <span style="font-size: 11px; color: var(--text-muted);"><?= __t('(Portion of capital refunded to partner)', '(بخشی از اصل سرمایه که مسترد شده)', '(بېرته ورکړل شوې برخه)') ?></span>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px; color: var(--text-muted);">
                            <?= __t('Total Sales Logged:', 'مجموع فروشات ثبت شده:', 'ټول ثبت شوی پلور:') ?>
                        </label>
                        <input type="number" step="any" min="0" name="total_sales_amount" readonly id="edit_c_total_sales" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12px; color: var(--text-muted);">
                            <?= __t('Total Project Gross Profit:', 'مجموع مفاد کل پروژه:', 'د پروژې ټوله ګټه:') ?>
                        </label>
                        <input type="number" step="any" min="0" name="total_profit_amount" readonly id="edit_c_total_profit" class="form-control">
                    </div>
                </div>
                <div style="margin-top: 8px; font-size: 11.5px; color: var(--info); background: var(--info-soft); padding: 6px 10px; border-radius: 6px;">
                    <i class="fa-solid fa-info-circle ml-1"></i> <?= __t('Adjusting payouts above automatically balances store capital records in accordance with accounting principles.', 'با ویرایش مبالغ بالا، سیستم مطابق اصول حسابداری گردش سرمایه دکان را به صورت خودکار متوازن و تنظیم می‌نماید.', 'د دې ارقامو بدلون اتومات د هټۍ له پانګې سره توازن پیدا کوي.') ?>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" style="font-weight: 700; font-size: 12.5px;"><?= __t('Agreement Notes & Remarks:', 'یادداشت و توضیحات:', 'توضیحات او یادښت:') ?></label>
                <input type="text" name="notes" id="edit_c_notes" class="form-control">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="closeEditContractModal();" class="btn btn-secondary">
                    <?= __t('Cancel', 'انصراف', 'لغوه') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check ml-1"></i> <?= __t('Save Changes', 'ذخیره تغییرات', 'بدلونونه خوندي کړئ') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const isEnglish = <?= json_for_html($currentLang === 'en') ?>;
const currencyLabel = <?= json_for_html(get_currency_label()) ?>;

function openEditContractModalFromBtn(btn) {
    try {
        const raw = btn.getAttribute('data-contract');
        const data = JSON.parse(raw);
        openEditContractModal(data);
    } catch (e) {
        console.error("Error opening edit contract modal:", e);
    }
}

function openEditContractModal(data) {
    MoneyForm.setCurrency(document.getElementById('edit_c_capital_amount').form,data.currency_code);
    document.getElementById('edit_contract_id').value = data.id || 0;
    document.getElementById('edit_c_partner_name').value = data.partner_name || '';
    document.getElementById('edit_c_phone').value = data.phone || '';
    document.getElementById('edit_c_project_title').value = data.project_title || '';
    document.getElementById('edit_c_country_source').value = data.country_source || '';
    MoneyForm.setLedger(document.getElementById('edit_c_capital_amount').form,'capital_amount',data.capital_amount || 0);
    document.getElementById('edit_c_partner_rate').value = data.partner_profit_rate || 50;
    document.getElementById('edit_c_shop_rate').value = data.shop_profit_rate || 50;
    if (document.getElementById('edit_c_paid_profit')) {
        MoneyForm.setLedger(document.getElementById('edit_c_paid_profit').form,'paid_profit_to_partner',data.paid_profit_to_partner || 0);
    }
    if (document.getElementById('edit_c_returned_capital')) {
        MoneyForm.setLedger(document.getElementById('edit_c_returned_capital').form,'returned_capital',data.returned_capital || 0);
    }
    if (document.getElementById('edit_c_total_sales')) {
        MoneyForm.setLedger(document.getElementById('edit_c_total_sales').form,'total_sales_amount',data.total_sales_amount || 0);
    }
    if (document.getElementById('edit_c_total_profit')) {
        MoneyForm.setLedger(document.getElementById('edit_c_total_profit').form,'total_profit_amount',data.total_profit_amount || 0);
    }
    document.getElementById('edit_c_start_date').value = data.start_date || '';
    document.getElementById('edit_c_settlement_date').value = data.settlement_date || '';
    document.getElementById('edit_c_status').value = data.status || 'active';
    document.getElementById('edit_c_notes').value = data.notes || '';
    document.getElementById('editContractModal').style.display = 'flex';
}

function closeEditContractModal() {
    document.getElementById('editContractModal').style.display = 'none';
}

// Bulk Selection Logic for Mudaraba Contracts
function toggleAllMudaraba(headerMaster) {
    const checkboxes = document.querySelectorAll('.mudaraba-chk');
    checkboxes.forEach(cb => cb.checked = headerMaster.checked);
    updateMudarabaBulkBar();
}

function selectAllMudarabaRows(checkAll) {
    const checkboxes = document.querySelectorAll('.mudaraba-chk');
    checkboxes.forEach(cb => cb.checked = checkAll);
    const master = document.getElementById('selectAllMudarabaHeader');
    if (master) master.checked = checkAll;
    updateMudarabaBulkBar();
}

function updateMudarabaBulkBar() {
    const checkboxes = document.querySelectorAll('.mudaraba-chk:checked');
    const bar = document.getElementById('mudarabaBulkActionBar');
    const countText = document.getElementById('mudarabaSelectedCountText');
    const count = checkboxes.length;

    if (count > 0) {
        bar.style.display = 'block';
        countText.textContent = isEnglish ? count.toLocaleString('en-US') : count.toLocaleString('fa-IR');
    } else {
        bar.style.display = 'none';
    }
}

function confirmBulkDeleteMudaraba() {
    const checkboxes = document.querySelectorAll('.mudaraba-chk:checked');
    if (checkboxes.length === 0) {
        alert(isEnglish ? 'Please select at least one contract to delete.' : 'لطفاً حداقل یک قرارداد مضاربت را برای حذف انتخاب نمایید.');
        return;
    }

    const msg = isEnglish 
        ? `Are you sure you want to delete ${checkboxes.length} selected Mudaraba contract(s)? Warehouse goods will be unlinked from Mudaraba.`
        : `آیا از حذف تعداد ${checkboxes.length} قرارداد مضاربت انتخاب شده به صورت گروهی اطمینان کامل دارید؟ اجناس گدام از این مضاربت‌ها جدا خواهند شد.`;

    if (!confirm(msg)) {
        return;
    }

    const container = document.getElementById('mudarabaBulkDeleteInputsContainer');
    container.innerHTML = '';
    checkboxes.forEach(cb => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'selected_ids[]';
        hidden.value = cb.value;
        container.appendChild(hidden);
    });

    document.getElementById('mudarabaBulkDeleteForm').submit();
}

function toggleContractStock(contractId) {
    var row = document.getElementById('contract_stock_row_' + contractId);
    if (!row) return;
    if (row.style.display === 'none' || row.style.display === '') {
        row.style.display = 'table-row';
    } else {
        row.style.display = 'none';
    }
}

function recalcMudarabaCapital() {
    var qty = parseFloat(document.getElementById('m_quantity').value || 0);
    var buy = parseFloat(document.getElementById('m_purchase_price').value || 0);
    var exp = parseFloat(document.getElementById('m_expenses').value || 0);
    var total = qty * (buy + exp);
    if (total > 0) {
        document.getElementById('m_capital_amount').value = total.toFixed(2);
    }
}

function openAddStockModalFromBtn(btn) {
    try {
        const data = JSON.parse(btn.getAttribute('data-add-stock') || '{}');
        openAddStockModal(data.id, data.partner_name, data.country_source);
    } catch (e) {
        console.error("Error opening add stock modal:", e);
    }
}

function openAddStockModal(contractId, partnerName, countrySource) {
    document.getElementById('add_stock_contract_id').value = contractId || 0;
    document.getElementById('add_stock_partner_name').textContent = partnerName || '';
    document.getElementById('addStockModal').style.display = 'flex';
}

let currentProgressPartnerRate = 50;
let currentProgressShopRate = 50;

function openProgressModalFromBtn(btn) {
    try {
        const data = JSON.parse(btn.getAttribute('data-progress') || '{}');
        document.getElementById('progress_contract_id').value = data.id || 0;
        document.getElementById('progress_project_title').textContent = data.project_title || '';
        currentProgressPartnerRate = parseFloat(data.partner_rate || 50);
        currentProgressShopRate = parseFloat(data.shop_rate || 50);
        document.getElementById('progress_p_rate').textContent = currentProgressPartnerRate;
        document.getElementById('progress_s_rate').textContent = currentProgressShopRate;
        document.getElementById('progress_add_profit').value = '';
        document.getElementById('progress_split_box').style.display = 'none';
        document.getElementById('progressModal').style.display = 'flex';
    } catch (e) {
        console.error("Error opening progress modal:", e);
    }
}

function openProgressModal(id, title) {
    document.getElementById('progress_contract_id').value = id || 0;
    document.getElementById('progress_project_title').textContent = title || '';
    document.getElementById('progressModal').style.display = 'flex';
}

function recalcProgressSplit() {
    var prof = parseFloat(document.getElementById('progress_add_profit').value || 0);
    var box = document.getElementById('progress_split_box');
    if (prof > 0) {
        box.style.display = 'block';
        var pShare = (prof * currentProgressPartnerRate) / 100;
        var sShare = (prof * currentProgressShopRate) / 100;
        document.getElementById('progress_p_share').textContent = isEnglish ? pShare.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) : pShare.toLocaleString('fa-IR',{minimumFractionDigits:2,maximumFractionDigits:2});
        document.getElementById('progress_s_share').textContent = isEnglish ? sShare.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) : sShare.toLocaleString('fa-IR',{minimumFractionDigits:2,maximumFractionDigits:2});
    } else {
        box.style.display = 'none';
    }
}

function openPayoutModalFromBtn(btn) {
    try {
        const data = JSON.parse(btn.getAttribute('data-payout') || '{}');
        const form=document.getElementById('payout_profit_inp').form;form.dataset.balanceCurrency=data.currency_code;form.dataset.profitKnown=data.profit_known;MoneyForm.setCurrency(form,data.currency_code);
        document.getElementById('payout_contract_id').value = data.id || 0;
        document.getElementById('payout_partner_name').textContent = data.partner_name || '';
        document.getElementById('payout_rem_profit').dataset.ledger=data.rem_profit||0;
        document.getElementById('payout_rem_cap').dataset.ledger=data.rem_cap||0;
        MoneyForm.setLedger(document.getElementById('payout_profit_inp').form,'payout_profit',(data.rem_profit > 0) ? data.rem_profit : 0);
        document.getElementById('return_cap_inp').value = 0;
        updatePayoutTotal();
        document.getElementById('payoutModal').style.display = 'flex';
    } catch (e) {
        console.error("Error opening payout modal:", e);
    }
}

function openPayoutModal(id, partnerName, remProfit, remCap) {
    document.getElementById('payout_contract_id').value = id || 0;
    document.getElementById('payout_partner_name').textContent = partnerName || '';
    document.getElementById('payout_rem_profit').dataset.ledger=remProfit||0;
    document.getElementById('payout_rem_cap').dataset.ledger=remCap||0;
    MoneyForm.setLedger(document.getElementById('payout_profit_inp').form,'payout_profit',(remProfit > 0) ? remProfit : 0);
    document.getElementById('return_cap_inp').value = 0;
    updatePayoutTotal();
    document.getElementById('payoutModal').style.display = 'flex';
}

function updatePayoutTotal() {
    const form=document.getElementById('payout_profit_inp').form;
    for(const id of ['payout_rem_profit','payout_rem_cap']){const el=document.getElementById(id);el.textContent=(id==='payout_rem_profit'&&form.dataset.profitKnown==='0'?'—':MoneyForm.displayLedger(form,el.dataset.ledger||0))+' '+(form.dataset.balanceCurrency||MoneyForm.code(form));}
    var pProfit = parseFloat(document.getElementById('payout_profit_inp').value || 0);
    var rCap = parseFloat(document.getElementById('return_cap_inp').value || 0);
    var tot = pProfit + rCap;
    var el = document.getElementById('payout_total_preview');
    if (el) el.textContent = tot.toLocaleString(isEnglish?'en-US':'fa-IR',{minimumFractionDigits:2,maximumFractionDigits:2})+' '+MoneyForm.code(form);
}

document.getElementById('payout_profit_inp').form.addEventListener('currencychange',updatePayoutTotal);
document.getElementById('m_purchase_price').form.addEventListener('currencychange',recalcMudarabaCapital);
document.getElementById('progress_add_profit').form.addEventListener('currencychange',recalcProgressSplit);
<?php if (!empty($_GET['contract_id'])): ?>
window.addEventListener('DOMContentLoaded', function() {
    toggleContractStock(<?= (int)$_GET['contract_id'] ?>);
});
<?php endif; ?>
</script>

<?php listing_controls_form($list);listing_footer($list);require_once __DIR__ . '/../includes/footer.php'; ?>
