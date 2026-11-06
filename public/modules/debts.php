<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Debts & Loans Module (قرضه ها: طلب من از مردم / قرضداری های من / قرض الحسنه)
 * 100% Language Isolation Supported: English, Dari, Pashto
 */

require_once __DIR__.'/../includes/listing.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && ($_POST['action']??'')==='pay_installment'){set_flash(__t('Use Cash & bank to record a linked receipt or payment.','برای ثبت دریافت یا پرداخت مربوط، از صندوق و بانک استفاده کنید.','د نغدو او بانک برخه وکاروئ.'),'info');redirect(BASE_URL.'modules/treasury.php');}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && dec_units($_POST['initial_paid']??0)>0)access_deny(409,'use_treasury',__t('Create the account without a payment, then settle it in Cash & bank.','حساب را بدون پرداخت بسازید؛ سپس در صندوق و بانک تسویه کنید.','لومړی حساب جوړ، بیا یې په نغدو برخه کې تصفیه کړئ.'));
$accountWhere=currency_sql();
$currentLang = get_current_lang();
$currentDir  = get_current_dir();

$error = '';
$success = '';

$currencyContext=currency_prepare_request(['create_debt'=>['total_amount'=>2,'initial_paid'=>2],'pay_installment'=>['amount'=>2,'capital_amount'=>2,'profit_amount'=>2],'edit_debt'=>['total_amount'=>2]],'',($_POST['action']??'')!=='pay_installment');

// Handle Manual Debt Registration (New Debt / Payable / Hasana)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_debt') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error! Please refresh and try again.', 'خطای امنیتی نشست!');
    } else {
        $personName  = trim($_POST['person_name'] ?? '');
        $personPhone = trim($_POST['person_phone'] ?? '');
        $debtType    = trim($_POST['debt_type'] ?? 'receivable'); // receivable or payable
        $isHasana    = isset($_POST['is_hasana']) ? 1 : 0;
        $amount      = (float)normalize_digits($_POST['total_amount'] ?? '0');
        $initialPaid = (float)normalize_digits($_POST['initial_paid'] ?? '0');
        $dueDate     = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $notes       = trim($_POST['notes'] ?? '');
        $exactNow    = date('Y-m-d H:i:s');

        if (empty($personName) || $amount <= 0) {
            $error = __t('Please enter valid contact name and amount.', 'لطفاً نام شخص و مبلغ قرضه را به درستی وارد نمایید.');
        } else {
            try {
                $initialContext=currency_context(['currency_code'=>$_POST['payment_currency_code']??$currencyContext['code']]);
                $sameInitial=$initialContext['code']===$currencyContext['code'];$receivedInitial=$initialPaid;
                if(!$sameInitial)$initialPaid=0;
                if($initialPaid>$amount)throw new InvalidArgumentException(__t('Initial payment exceeds the account amount.','پیش‌پرداخت از مبلغ حساب بیشتر است.','لومړنۍ تادیه د حساب له مبلغ زیاته ده.'));
                $rem = max(0, $amount - $initialPaid);
                $status = ($rem <= 0) ? 'تسویه' : 'باز';

                $stmt = $pdo->prepare("INSERT INTO debts 
                    (customer_name, customer_phone, total_amount, paid_amount, remaining_amount, status, notes, debt_type, is_hasana, due_date, created_at, shop_id) 
                    VALUES (:name, :phone, :tot, :paid, :rem, :status, :notes, :dtype, :hasana, :due, :cdate, " . shop_id() . ")");
                
                $stmt->execute([
                    ':name'   => $personName,
                    ':phone'  => $personPhone,
                    ':tot'    => $amount,
                    ':paid'   => $initialPaid,
                    ':rem'    => $rem,
                    ':status' => $status,
                    ':notes'  => $notes,
                    ':dtype'  => $debtType,
                    ':hasana' => $isHasana,
                    ':due'    => $dueDate,
                    ':cdate'  => $exactNow
                ]);

                $newDebtId = $pdo->lastInsertId();
                currency_record('debts',$newDebtId,$currencyContext,['total_amount'=>$amount,'paid_amount'=>$initialPaid,'remaining_amount'=>$rem],'create',['paid_amount'=>$currencyContext['original']['initial_paid']??$initialPaid]);

                if ($receivedInitial > 0) {
                    $iStmt = $pdo->prepare("INSERT INTO installments (debt_id, amount, payment_date, notes, payment_reason, capital_amount, profit_amount, created_by, shop_id) 
                                            VALUES (:did, :amt, :pdate, :notes, :reason, :cap, :prof, :uid, " . shop_id() . ")");
                    $iStmt->execute([
                        ':did'    => $newDebtId,
                        ':amt'    => $receivedInitial,
                        ':pdate'  => date('Y-m-d'),
                        ':notes'  => 'پیش‌پرداخت اولیه هنگام ثبت حساب',
                        ':reason' => 'پیش‌پرداخت نقد اولیه',
                        ':cap'    => $initialPaid,
                        ':prof'   => 0,
                        ':uid'    => $currentUser['id']
                    ]);
                    $receiptId=(int)$pdo->lastInsertId();
                    $pdo->prepare('UPDATE installments SET applied_to_balance=?,split_known=? WHERE id=? AND shop_id=?')->execute([$sameInitial?1:0,$sameInitial?1:0,$receiptId,shop_id()]);
                    currency_record('installments',$receiptId,$initialContext,['amount'=>$receivedInitial,'capital_amount'=>$initialPaid,'profit_amount'=>0]);
                }

                currency_request_commit();
                $logTitle = ($debtType === 'payable') ? 'ثبت بدهی دکان' : 'ثبت طلب از شخص';
                if ($isHasana) $logTitle .= ' (قرض‌الحسنه)';
                log_activity($currentUser['id'], $logTitle, "ثبت مبلغ " . currency_format($amount,$currencyContext['code']) . " به نام {$personName}");

                set_flash(__t('Debt account registered successfully.', 'قرضه مورد نظر با موفقیت در سیستم ثبت گردید.'), 'success');
                redirect(BASE_URL . "modules/debts.php?type={$debtType}");
            } catch (Exception $e) {
                currency_request_rollback();
                $error = __t('Error registering debt: ', 'خطا در ثبت قرضه: ') . public_error($e);
            }
        }
    }
}

// Handle Payment / Installment Receipt (با تفکیک دقیق اصل سرمایه دکان و مفاد خالص)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_installment') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!');
    } else {
        $debtId  = (int)($_POST['debt_id'] ?? 0);
        $amount  = (float)normalize_digits($_POST['amount'] ?? '0');
        $payDate = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
        $notes   = trim($_POST['notes'] ?? '');

        $reasonCategory = trim($_POST['payment_reason'] ?? '');
        if ($reasonCategory === 'سایر' && !empty($_POST['custom_reason'])) {
            $reasonCategory = trim($_POST['custom_reason']);
        }
        if (empty($reasonCategory)) {
            $reasonCategory = 'وصولی قرضه';
        }

        $capitalAmount = (float)normalize_digits($_POST['capital_amount'] ?? '0');
        $profitAmount  = (float)normalize_digits($_POST['profit_amount'] ?? '0');

        $manualSplit=dec_units(dec_add($capitalAmount,$profitAmount))===dec_units($amount) && $amount>0;
        if ($debtId <= 0 || $amount <= 0) {
            $error = __t('Please enter a valid installment payment amount.', 'لطفاً مبلغ قسط معتبر وارد نمایید.');
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT * FROM debts WHERE id = :id AND " . shop_sql());
                $stmt->execute([':id' => $debtId]);
                $debt = $stmt->fetch();

                if (!$debt) {
                    throw new Exception(__t('Debt record not found.', 'رکورد قرضه یافت نشد.'));
                }

                if(!$manualSplit&&(dec_units($capitalAmount)!==0||dec_units($profitAmount)!==0))throw new InvalidArgumentException(__t('Capital and profit must add up to the payment.','مجموع سرمایه و مفاد باید برابر پرداخت باشد.','پانګه او ګټه باید له تادیې سره برابر وي.'));
                $sameCurrency=$debt['currency_code']===$currencyContext['code'];
                if($sameCurrency&&dec_units($amount)>dec_units($debt['remaining_amount']))throw new InvalidArgumentException(__t('Payment exceeds the remaining balance.','مبلغ پرداخت از باقی‌ماندهٔ حساب بیشتر است.','تادیه له پاتې حساب زیاته ده.'));
                $splitKnown=$manualSplit||($sameCurrency&&(bool)$debt['profit_known']);
                if(!$manualSplit){$capitalAmount=$splitKnown?$amount:0;$profitAmount=0;}
                $newPaid=$sameCurrency?dec_add($debt['paid_amount'],$amount):$debt['paid_amount'];
                $newRemaining=$sameCurrency?max(0,dec_sub($debt['total_amount'],$newPaid)):$debt['remaining_amount'];
                $newStatus = $sameCurrency?(($newRemaining <= 0) ? 'تسویه' : 'باز'):$debt['status'];

                $upd = $pdo->prepare("UPDATE debts SET paid_amount = :paid, remaining_amount = :rem, status = :st WHERE id = :id AND " . shop_sql());
                $upd->execute([
                    ':paid' => $newPaid,
                    ':rem'  => $newRemaining,
                    ':st'   => $newStatus,
                    ':id'   => $debtId
                ]);

                $inst = $pdo->prepare("INSERT INTO installments 
                    (debt_id, amount, payment_date, notes, payment_reason, capital_amount, profit_amount, created_by, shop_id) 
                    VALUES (:did, :amt, :pdate, :notes, :reason, :cap, :prof, :uid, " . shop_id() . ")");
                $inst->execute([
                    ':did'    => $debtId,
                    ':amt'    => $amount,
                    ':pdate'  => $payDate,
                    ':notes'  => $notes,
                    ':reason' => $reasonCategory,
                    ':cap'    => $capitalAmount,
                    ':prof'   => $profitAmount,
                    ':uid'    => $currentUser['id']
                ]);

                $receiptId=(int)$pdo->lastInsertId();
                $pdo->prepare('UPDATE installments SET applied_to_balance=?,split_known=? WHERE id=? AND shop_id=?')->execute([$sameCurrency?1:0,$splitKnown?1:0,$receiptId,shop_id()]);
                currency_record('installments',$receiptId,$currencyContext,['amount'=>$amount,'capital_amount'=>$capitalAmount,'profit_amount'=>$profitAmount]);
                $pdo->commit();
                log_activity($currentUser['id'], 'رسیدگی قسط', "دریافت قسط " . currency_format($amount,$currencyContext['code']) . " بابت حساب {$debt['customer_name']}");
                set_flash($sameCurrency?__t('Payment recorded.','پرداخت ثبت شد.') : __t('Payment recorded in its own currency. The original debt balance has not been reduced.','پرداخت به ارز خودش ثبت شد؛ ماندهٔ قرضهٔ اصلی کم نشده است.'), 'success');
                redirect(BASE_URL . 'modules/debts.php?type=' . ($debt['debt_type'] ?? 'receivable'));
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = __t('Error recording payment: ', 'خطا در ثبت قسط: ') . public_error($e);
            }
        }
    }
}

// Handle Edit Debt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_debt') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!');
    } else {
        $editId      = (int)($_POST['debt_id'] ?? 0);
        $personName  = trim($_POST['person_name'] ?? '');
        $personPhone = trim($_POST['person_phone'] ?? '');
        $totalAmount = (float)normalize_digits($_POST['total_amount'] ?? '0');
        $dueDate     = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $status      = trim($_POST['status'] ?? 'باز');
        $notes       = trim($_POST['notes'] ?? '');

        if ($editId <= 0 || empty($personName) || $totalAmount <= 0) {
            $error = __t('Please enter valid contact name and amount.', 'لطفاً نام شخص و مبلغ معتبر وارد نمایید.');
        } else {
            try {
                $ex = $pdo->prepare("SELECT * FROM debts WHERE id = :id AND " . shop_sql());
                $ex->execute([':id' => $editId]);
                $curr = $ex->fetch();
                if ($curr) {
                    currency_assert_record('debts',$editId,$currencyContext['code']);
                    $paid = (float)$curr['paid_amount'];
                    $rem = max(0, $totalAmount - $paid);
                    if ($rem <= 0) {
                        $status = 'تسویه';
                    }

                    $uStmt = $pdo->prepare("UPDATE debts SET 
                        customer_name = :name,
                        customer_phone = :phone,
                        total_amount = :tot,
                        remaining_amount = :rem,
                        due_date = :due,
                        status = :status,
                        notes = :notes
                        WHERE id = :id AND " . shop_sql());
                    $uStmt->execute([
                        ':name'   => $personName,
                        ':phone'  => $personPhone,
                        ':tot'    => $totalAmount,
                        ':rem'    => $rem,
                        ':due'    => $dueDate,
                        ':status' => $status,
                        ':notes'  => $notes,
                        ':id'     => $editId
                    ]);

                    currency_record('debts',$editId,$currencyContext,['total_amount'=>$totalAmount,'remaining_amount'=>$rem],'edit');
                    currency_request_commit();
                    log_activity($currentUser['id'], 'ویرایش حساب قرضه', "ویرایش حساب قرضه #{$editId} به نام {$personName}");
                    set_flash(__t("Debt account «{$personName}» updated successfully.", "حساب قرضه «{$personName}» با موفقیت ویرایش گردید."), 'success');
                    redirect(BASE_URL . 'modules/debts.php?type=' . ($curr['debt_type'] ?? 'receivable'));
                }
            } catch (Exception $e) {
                currency_request_rollback();
                $error = __t('Error editing debt: ', 'خطا در ویرایش قرضه: ') . public_error($e);
            }
        }
    }
}

// Handle Single Delete Debt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_debt') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!');
    } else {
        $delId = (int)($_POST['debt_id'] ?? 0);
        if ($delId > 0) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM installments WHERE debt_id = :id AND " . shop_sql())->execute([':id' => $delId]);
                $pdo->prepare("DELETE FROM debts WHERE id = :id AND " . shop_sql())->execute([':id' => $delId]);
                $pdo->commit();

                log_activity($currentUser['id'], 'حذف حساب قرضه', "حذف حساب قرضه #{$delId}");
                set_flash(__t('Debt account deleted successfully.', 'حساب قرضه مورد نظر با موفقیت حذف گردید.'), 'success');
                redirect(BASE_URL . 'modules/debts.php?type=' . ($activeType ?? 'receivable'));
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = __t('Error deleting debt: ', 'خطا در حذف قرضه: ') . public_error($e);
            }
        }
    }
}

// Handle Bulk Delete Debts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete_debts') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!');
    } else {
        $selectedIds = $_POST['selected_ids'] ?? [];
        if (!is_array($selectedIds) || empty($selectedIds)) {
            set_flash(__t('No debts selected for deletion.', 'هیچ قرضه‌ای برای حذف انتخاب نشده است.'), 'warning');
            redirect(BASE_URL . 'modules/debts.php?type=' . ($activeType ?? 'receivable'));
        }

        $validIds = array_values(array_filter(array_map('intval', $selectedIds), fn($id) => $id > 0));
        if (empty($validIds)) {
            set_flash(__t('Invalid debt IDs provided.', 'شناسه‌های ارسالی معتبر نمی‌باشند.'), 'danger');
            redirect(BASE_URL . 'modules/debts.php?type=' . ($activeType ?? 'receivable'));
        }

        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM installments WHERE debt_id IN ($placeholders) AND " . shop_sql())->execute($validIds);
            $pdo->prepare("DELETE FROM debts WHERE id IN ($placeholders) AND " . shop_sql())->execute($validIds);
            $pdo->commit();

            log_activity($currentUser['id'], 'حذف گروهی قرضه', "حذف گروهی تعداد " . count($validIds) . " رکورد قرضه");
            set_flash(sprintf(__t('%d debt accounts deleted successfully.', 'تعداد %d حساب قرضه با موفقیت حذف گردیدند.'), count($validIds)), 'success');
            redirect(BASE_URL . 'modules/debts.php?type=' . ($activeType ?? 'receivable'));
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash(__t('Error deleting selected debts: ', 'خطا در حذف گروهی: ') . public_error($e), 'danger');
            redirect(BASE_URL . 'modules/debts.php?type=' . ($activeType ?? 'receivable'));
        }
    }
}

// Active Filter Type: receivable, payable, hasana
$activeType = $_GET['type'] ?? 'receivable';
$statusFilter = $_GET['status'] ?? 'باز';

// Base Query
$sql = "SELECT d.*, s.invoice_no, s.total_amount as sale_total, s.profit as sale_profit 
        FROM debts d 
        LEFT JOIN sales s ON d.sale_id = s.id AND d.shop_id=s.shop_id 
        WHERE ".currency_sql('d')." AND ".shop_sql('d');
$params = [];

if ($activeType === 'hasana') {
    $sql .= " AND d.is_hasana = 1";
} elseif ($activeType === 'payable') {
    $sql .= " AND d.debt_type = 'payable' AND (d.is_hasana = 0 OR d.is_hasana IS NULL)";
} else {
    $sql .= " AND (d.debt_type = 'receivable' OR d.debt_type IS NULL) AND (d.is_hasana = 0 OR d.is_hasana IS NULL)";
}

if ($statusFilter !== 'all') {
    $sql .= " AND d.status = :status";
    $params[':status'] = $statusFilter;
}

$sorts=['newest'=>['d.id DESC','Newest first','تازه‌ترین اول'],'oldest'=>['d.id ASC','Oldest first','قدیمی‌ترین اول'],'amount_high'=>['d.remaining_amount DESC,d.id DESC','Amount: high to low','مبلغ: زیاد به کم'],'amount_low'=>['d.remaining_amount ASC,d.id ASC','Amount: low to high','مبلغ: کم به زیاد']];
[$list,$debts]=listing_query($pdo,$sql,$params,$sorts);

// Collect all installments and statistics for the loaded debts
$debtIds = array_column($debts, 'id');
$installmentsMap = [];
$debtStatsMap = [];

if (!empty($debtIds)) {
    $inClause = implode(',', array_map('intval', $debtIds));
    $iStmt = $pdo->query("SELECT i.*, u.fullname AS creator_name 
                          FROM installments i 
                          LEFT JOIN users u ON i.created_by = u.id 
                          WHERE i.debt_id IN ($inClause) AND ".shop_sql('i')." 
                          ORDER BY i.payment_date DESC, i.id DESC");
    while ($inst = $iStmt->fetch()) {
        $did = $inst['debt_id'];
        $installmentsMap[$did][] = $inst;
        if (!isset($debtStatsMap[$did])) {
            $debtStatsMap[$did] = ['capital' => 0, 'profit' => 0, 'total' => 0];
        }
        if($inst['currency_code']!==currency_base_code() || !$inst['applied_to_balance'])continue;
        $debtStatsMap[$did]['capital'] += (float)($inst['capital_amount'] ?? 0);
        $debtStatsMap[$did]['profit']  += (float)($inst['profit_amount'] ?? 0);
        $debtStatsMap[$did]['total']   += (float)($inst['amount'] ?? 0);
    }
}

// Calculations for top statistics
$totReceivables = (float)($pdo->query("SELECT SUM(remaining_amount) AS tot FROM debts WHERE {$accountWhere} AND (debt_type = 'receivable' OR debt_type IS NULL) AND status = 'باز' AND " . shop_sql())->fetch()['tot'] ?? 0);
$totPayables    = (float)($pdo->query("SELECT SUM(remaining_amount) AS tot FROM debts WHERE {$accountWhere} AND debt_type = 'payable' AND status = 'باز' AND " . shop_sql())->fetch()['tot'] ?? 0);
$totHasana      = (float)($pdo->query("SELECT SUM(remaining_amount) AS tot FROM debts WHERE {$accountWhere} AND is_hasana = 1 AND status = 'باز' AND " . shop_sql())->fetch()['tot'] ?? 0);

$pageTitle = 'مدیریت قرضه ها، طلبات و تفکیک سرمایه و مفاد';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Statistics Overview -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px;">
    <!-- 1. Receivables -->
    <a href="?type=receivable" style="text-decoration: none; color: inherit;">
        <div class="stat-card" style="border: 2px solid <?= $activeType === 'receivable' ? 'var(--info)' : 'var(--border-color)' ?>;">
            <div class="stat-header">
                <span class="stat-title"><?= __t('Store Receivables (Customers Owe Us)', 'طلب من از مردم (مشتریان)', 'له پیرودونکو څخه زما طلب') ?></span>
                <div class="stat-icon icon-debt" style="background: var(--info-soft); color: var(--info);">
                    <i class="fa-solid fa-arrow-down-long"></i>
                </div>
            </div>
            <div class="stat-value" style="color: var(--info);"><?= format_money($totReceivables) ?></div>
            <div class="stat-desc"><?= __t('Amounts store needs to collect from buyers', 'مبالغی که دکان باید از خریداران وصول کند', 'هغه پیسې چې هټۍ باید له پیرودونکو ترلاسه کړي') ?></div>
        </div>
    </a>

    <!-- 2. Payables -->
    <a href="?type=payable" style="text-decoration: none; color: inherit;">
        <div class="stat-card" style="border: 2px solid <?= $activeType === 'payable' ? 'var(--danger)' : 'var(--border-color)' ?>;">
            <div class="stat-header">
                <span class="stat-title"><?= __t('Store Payables (We Owe Creditors)', 'قرضداری‌های من (طلب مردم از من)', 'خلکو ته زما د پورونو کتاب') ?></span>
                <div class="stat-icon icon-debt" style="background: var(--danger-soft); color: var(--danger);">
                    <i class="fa-solid fa-arrow-up-long"></i>
                </div>
            </div>
            <div class="stat-value" style="color: var(--danger);"><?= format_money($totPayables) ?></div>
            <div class="stat-desc"><?= __t('Amounts owed to suppliers, wholesalers, and creditors', 'طلب شرکت‌ها، عمده‌فروشان و اشخاص از دکان', 'پر هټۍ د شرکتونو او ټولیزو پلورونکو پورونه') ?></div>
        </div>
    </a>

    <!-- 3. Benevolent Loans (Hasana) -->
    <a href="?type=hasana" style="text-decoration: none; color: inherit;">
        <div class="stat-card" style="border: 2px solid <?= $activeType === 'hasana' ? 'var(--success)' : 'var(--border-color)' ?>;">
            <div class="stat-header">
                <span class="stat-title"><?= __t('Benevolent Loans (Zero Profit Qard Hasan)', 'قرض‌الحسنه (اعتمادی و بدون سود)', 'قرض الحسنه (بې ګټې او باوري)') ?></span>
                <div class="stat-icon icon-profit" style="background: #d1fae5; color: var(--success);">
                    <i class="fa-solid fa-handshake-angle"></i>
                </div>
            </div>
            <div class="stat-value" style="color: var(--success);"><?= format_money($totHasana) ?></div>
            <div class="stat-desc"><?= __t('Trusted benevolent financial aids with zero interest', 'کمک‌های مالی اعتمادی و بدون هیچ‌گونه سود', 'باوري او بې سوده مالي مرستې') ?></div>
        </div>
    </a>
</div>

<!-- Header Control Card -->
<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <h3 class="card-title" style="margin: 0;">
                <?php if ($activeType === 'payable'): ?>
                    <i class="fa-solid fa-file-invoice-dollar ml-1" style="color: var(--danger);"></i> <?= __t('Payables Ledger (Shop Owes Suppliers)', 'دفتر قرضداری‌های من به مردم', 'خلکو ته زما د پورونو کتاب') ?>
                <?php elseif ($activeType === 'hasana'): ?>
                    <i class="fa-solid fa-handshake-angle ml-1" style="color: var(--success);"></i> <?= __t('Benevolent Loans Ledger (Zero-Profit Qard Hasan)', 'دفتر قرض‌الحسنه (بدون سود)', 'د قرض‌الحسنه کتاب (بې سوده)') ?>
                <?php else: ?>
                    <i class="fa-solid fa-hand-holding-dollar ml-1" style="color: var(--info);"></i> <?= __t('Receivables Ledger (Customers Owe Shop)', 'دفتر طلبات من از مردم و مشتریان', 'له خلکو او پیرودونکو څخه د طلبونو کتاب') ?>
                <?php endif; ?>
            </h3>
        </div>

        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn btn-primary" onclick="openNewDebtModal();">
                <i class="fa-solid fa-plus"></i> <?= __t('Register New Debt / Loan', 'ثبت قرضه / طلب جدید', 'نوی پور / طلب ثبتول') ?>
            </button>
            <a href="?type=<?= $activeType ?>&status=باز" class="btn btn-sm <?= $statusFilter === 'باز' ? 'btn-primary' : 'btn-secondary' ?>">
                <?= __t('Open Accounts', 'حساب‌های باز', 'خلاص حسابونه') ?>
            </a>
            <a href="?type=<?= $activeType ?>&status=تسویه" class="btn btn-sm <?= $statusFilter === 'تسویه' ? 'btn-primary' : 'btn-secondary' ?>">
                <?= __t('Settled Accounts', 'تسویه شده', 'تصفیه شوي') ?>
            </a>
            <a href="?type=<?= $activeType ?>&status=all" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
                <?= __t('All', 'همه', 'ټول') ?>
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span><?= sanitize($error) ?></span>
    </div>
<?php endif; ?>

<!-- Bulk Selection Action Bar for Debts -->
<div id="debtBulkActionBar" class="card" style="display: none; background: var(--info-soft); border: 1.5px solid var(--info); margin-bottom: 16px; padding: 12px 18px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.12);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span style="font-weight: 800; color: var(--info); font-size: 14px;">
                <i class="fa-solid fa-square-check text-blue-600 ml-1"></i> <span id="debtSelectedCountText">0</span> <?= __t('debt accounts selected', 'حساب قرضه انتخاب گردید', 'د پور حسابونه وټاکل شول') ?>
            </span>
            <button type="button" onclick="selectAllDebtRows(true)" class="btn btn-sm btn-secondary" style="background: var(--surface);">
                <i class="fa-solid fa-list-check ml-1"></i> <?= __t('Select All', 'انتخاب همه', 'ټول ټاکل') ?>
            </button>
            <button type="button" onclick="selectAllDebtRows(false)" class="btn btn-sm btn-secondary" style="background: var(--surface);">
                <i class="fa-solid fa-xmark ml-1"></i> <?= __t('Deselect All', 'لغو انتخاب‌ها', 'ټاکنه لغوه کول') ?>
            </button>
        </div>
        <div>
            <button type="button" onclick="confirmBulkDeleteDebts()" class="btn btn-danger btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                <i class="fa-solid fa-trash-can"></i> <?= __t('Delete Selected Items', 'حذف موارد انتخاب شده', 'ټاکل شوي ړنګول') ?>
            </button>
        </div>
    </div>
</div>

<!-- Hidden Form for Bulk Deletion of Debts -->
<form id="debtBulkDeleteForm" method="POST" action="" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_delete_debts">
    <div id="debtBulkDeleteInputsContainer"></div>
</form>

<!-- Debts Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 38px; text-align: center;">
                        <input type="checkbox" id="selectAllDebtsHeader" onchange="toggleAllDebts(this)" title="<?= __t('Select All', 'انتخاب همه', 'ټول ټاکل') ?>">
                    </th>
                    <th style="width: 40px;">#</th>
                    <th><?= __t('Person / Account Name', 'طرف حساب / شخص', 'د حساب / کس نوم') ?></th>
                    <th><?= __t('Phone Number', 'شماره تماس', 'د تلیفون شمېره') ?></th>
                    <th><?= __t('Classification', 'نوعیت حساب', 'د حساب ډول') ?></th>
                    <th><?= __t('Total Debt', 'مجموع قرضه', 'ټولټال پور') ?></th>
                    <th><?= __t('Paid & Breakdown (Capital / Profit)', 'رسیدگی شده و تفکیک (سرمایه / سود)', 'تادیه شوې او بېلوالی (پانګه / ګټه)') ?></th>
                    <th><?= __t('Remaining Balance', 'باقی‌مانده', 'پاتې پیسې') ?></th>
                    <th><?= __t('Status', 'وضعیت', 'حالت') ?></th>
                    <th><?= __t('Date Recorded', 'تاریخ ثبت', 'د ثبت نېټه') ?></th>
                    <th style="min-width: 170px;"><?= __t('Actions', 'عملیات', 'کړنې') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($debts)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 28px;">
                            <?= __t('No debt records found in this section.', 'هیچ رکوردی در این بخش یافت نشد.', 'په دې برخه کې هېڅ ریکارډ ونه موندل شو.') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $dNum = 1;
                    foreach ($debts as $debt): 
                        $dId = $debt['id'];
                        $stats = $debtStatsMap[$dId] ?? ['capital' => (float)$debt['paid_amount'], 'profit' => 0, 'total' => (float)$debt['paid_amount']];
                    ?>
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" class="debt-chk" value="<?= $debt['id'] ?>" onchange="updateDebtBulkBar()">
                            </td>
                            <td><?= $currentLang === 'en' ? $dNum++ : to_persian_num($dNum++) ?></td>
                            <td>
                                <strong><?= sanitize($debt['customer_name']) ?></strong>
                                <?php if (!empty($debt['invoice_no'])): ?>
                                    <div style="font-size: 11px; margin-top: 2px;">
                                        <?= __t('Inv: ', 'بل: ', 'بل: ') ?><a href="<?= BASE_URL ?>print/invoice.php?id=<?= $debt['sale_id'] ?>" target="_blank" style="color: var(--info); text-decoration: underline;"><?= sanitize($debt['invoice_no']) ?></a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize($debt['customer_phone'] ?: '-') ?></td>
                            <td>
                                <?php if (!empty($debt['is_hasana'])): ?>
                                    <span class="badge" style="background: var(--success-soft); color: var(--success); border: 1px solid #86efac;"><?= __t('Benevolent Loan', 'قرض‌الحسنه', 'قرض الحسنه') ?></span>
                                <?php elseif ($debt['debt_type'] === 'payable'): ?>
                                    <span class="badge badge-danger"><?= __t('Payable', 'قرضداری من', 'زما پور') ?></span>
                                <?php else: ?>
                                    <span class="badge badge-primary"><?= __t('Receivable', 'طلب من', 'زما طلب') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= format_money($debt['total_amount']) ?></strong></td>
                            <td>
                                <div style="font-weight: 700; color: var(--success);"><?= format_money($debt['paid_amount']) ?></div>
                                <?php if ((float)$debt['paid_amount'] > 0): ?>
                                    <div style="display: flex; gap: 4px; margin-top: 4px; flex-wrap: wrap;">
                                        <span style="background: var(--info-soft); color: var(--info); font-size: 10.5px; padding: 2px 6px; border-radius: 4px; border: 1px solid #bae6fd;" title="<?= __t('Shop Principal Capital Share', 'سهم اصل سرمایه دکان', 'د هټۍ د اصلي پانګې ونډه') ?>">
                                            <?= __t('Capital: ', 'سرمایه: ', 'پانګه: ') ?><?= currency_profit_html($stats['capital'],$debt['profit_known']) ?>
                                        </span>
                                        <span style="background: var(--success-soft); color: var(--success); font-size: 10.5px; padding: 2px 6px; border-radius: 4px; border: 1px solid #bbf7d0;" title="<?= __t('Shop Net Profit Share', 'سهم مفاد خالص دکان', 'د هټۍ د خالصې ګټې ونډه') ?>">
                                            <?= __t('Profit: ', 'مفاد: ', 'ګټه: ') ?><?= currency_profit_html($stats['profit'],$debt['profit_known']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: <?= (float)$debt['remaining_amount'] > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                    <?= format_money($debt['remaining_amount']) ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($debt['status'] === 'تسویه'): ?>
                                    <span class="badge badge-success"><?= __t('Fully Settled', 'تسویه کامل', 'بشپړه تصفیه') ?></span>
                                <?php else: ?>
                                    <span class="badge badge-warning"><?= __t('Active (Open)', 'جاری (باز)', 'خلاص (پاتې)') ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 11px; color: var(--text-muted);">
                                <?= !empty($debt['created_at']) ? ($currentLang === 'en' ? date('Y-m-d H:i', strtotime($debt['created_at'])) : to_persian_num(date('H:i - Y/m/d', strtotime($debt['created_at'])))) : '-' ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap;">
                                    <?php if ((float)$debt['remaining_amount'] > 0): ?>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="openPayModal(<?= $debt['id'] ?>, <?= sanitize(json_for_html($debt['customer_name'])) ?>, <?= (float)$debt['remaining_amount'] ?>, <?= sanitize(json_for_html($debt['debt_type'])) ?>, <?= (float)($debt['sale_total'] ?? 0) ?>, <?= (float)($debt['sale_profit'] ?? 0) ?>);" 
                                                title="<?= __t('Receive & Settle Payment', 'رسیدگی و ثبت قسط', 'د قسط وصول او تصفیه') ?>">
                                            <i class="fa-solid fa-money-bill-wave"></i> <?= __t('Receive', 'رسیدگی', 'تصفیه') ?>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-secondary" 
                                            onclick="openHistoryModal(<?= $debt['id'] ?>);" 
                                            title="<?= __t('View Installments Breakdown & History', 'مشاهده تاریخچه اقساط و تفکیک', 'د تاریخچې او وېش لیدل') ?>">
                                        <i class="fa-solid fa-list-check"></i> <?= __t('Breakdown', 'تفکیک', 'بېلوالی') ?>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary" 
                                            onclick='openEditDebtModal(<?= json_for_html([
                                                'id' => (int)$debt['id'],
                                                'customer_name' => $debt['customer_name'],'currency_code'=>$debt['currency_code'],
                                                'customer_phone' => $debt['customer_phone'] ?? '',
                                                'total_amount' => (float)$debt['total_amount'],
                                                'paid_amount' => (float)$debt['paid_amount'],
                                                'remaining_amount' => (float)$debt['remaining_amount'],
                                                'due_date' => $debt['due_date'] ?? '',
                                                'status' => $debt['status'],
                                                'notes' => $debt['notes'] ?? '',
                                                'debt_type' => $debt['debt_type'] ?? 'receivable'
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);'
                                            title="<?= __t('Edit Debt Account', 'ویرایش حساب قرضه', 'د پور حساب سمول') ?>">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <form method="POST" action="" onsubmit="return confirm('<?= __t('Are you sure you want to delete this debt account? All its installments will also be removed.', 'آیا از حذف این حساب قرضه اطمینان کامل دارید؟ تمام اقساط آن نیز حذف خواهد شد.', 'ایا تاسو باوري یاست چې دا حساب ړنګ کړئ؟ ټول قسطونه به هم ړنګ شي.') ?>');" style="display: inline; margin: 0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_debt">
                                        <input type="hidden" name="debt_id" value="<?= $debt['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="<?= __t('Delete Debt Account', 'حذف حساب قرضه', 'د پور حساب ړنګول') ?>">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal 1: Register New Debt / Loan -->
<div id="newDebtModal" class="feature-modal-backdrop" onclick="if(event.target === this) closeNewDebtModal();">
    <div class="feature-modal-box" style="max-width: 520px; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <button type="button" class="feature-modal-close" onclick="closeNewDebtModal();">&times;</button>
        <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 16px; color: var(--text-main);">
            <i class="fa-solid fa-plus-circle ml-1" style="color: var(--info);"></i> <?= __t('Register New Debt / Loan Account', 'ثبت قرضه / طلب جدید', 'نوی پور یا طلب ثبتول') ?>
        </h3>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_debt">
            <?php currency_fields(['total_amount'=>2,'initial_paid'=>2]); ?>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label"><?= __t('Account Classification:', 'نوعیت این حساب:', 'د حساب ډول:') ?></label>
                <select name="debt_type" class="form-control" id="modalDebtType">
                    <option value="receivable" <?= $activeType === 'receivable' ? 'selected' : '' ?>><?= __t('Receivable (Customer owes shop)', 'طلب من از طرف حساب (او به من بدهکار است)', 'زما طلب (کس پر هټۍ پوروړی دی)') ?></option>
                    <option value="payable" <?= $activeType === 'payable' ? 'selected' : '' ?>><?= __t('Payable (Shop owes supplier / contact)', 'قرضداری من به طرف حساب (من به او بدهکارم)', 'زما پور (هټۍ شرکت یا کس ته پوروړې ده)') ?></option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 12px; background: var(--success-soft); padding: 10px; border-radius: 6px; border: 1px solid #bbf7d0;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                    <input type="checkbox" name="is_hasana" value="1" <?= $activeType === 'hasana' ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span style="font-weight: 700; color: var(--success); font-size: 13px;"><?= __t('This is a Benevolent Loan (Qard Hasan - Zero Profit)', 'این یک «قرض‌الحسنه» (اعتمادی و بدون سود) است', 'دا قرض الحسنه ده (بې له کومې ګټې)') ?></span>
                </label>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group">
                    <label class="form-label"><?= __t('Person / Company Name:', 'نام طرف حساب:', 'د کس / شرکت نوم:') ?></label>
                    <input type="text" name="person_name" class="form-control" required placeholder="<?= __t('Full Name', 'نام و تخلص', 'بشپړ نوم') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __t('Phone Number:', 'شماره تماس:', 'د تلیفون شمېره:') ?></label>
                    <input type="text" name="person_phone" class="form-control numeric-input" placeholder="07xxxxxxxx">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group">
                    <label class="form-label"><?= sprintf(__t('Total Debt Amount (%s):', 'مبلغ کل قرضه (%s):', 'د پور ټوله اندازه (%s):'), get_currency_label()) ?></label>
                    <input type="number" step="any" name="total_amount" class="form-control numeric-input" required placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= sprintf(__t('Initial Down Payment (%s):', 'مبلغ پرداخت شده اولیه (%s):', 'لومړنۍ مخکې تادیه (%s):'), get_currency_label()) ?></label>
                    <?= currency_select('payment_currency_code',null,'id="initialPaymentCurrency"') ?>
                    <input type="number" step="any" name="initial_paid" class="form-control numeric-input" value="0" placeholder="0">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label"><?= __t('Due Date / Settlement Deadline (Optional):', 'تاریخ موعد بازپرداخت (اختیاری):', 'د تادیې د وعدې نېټه (اختیاري):') ?></label>
                <input type="date" name="due_date" class="form-control">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label"><?= __t('Additional Notes & Terms:', 'یادداشت و جزئیات:', 'اضافي توضیحات او شرایط:') ?></label>
                <input type="text" name="notes" class="form-control" placeholder="<?= __t('Reason, purchased items, or warranty', 'علت قرضه، کالای خریداری شده، یا ضمانت', 'د پور لامل یا توکي') ?>">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 10px;">
                <i class="fa-solid fa-floppy-disk ml-1"></i> <?= __t('Save Debt Account', 'ذخیره در دفتر حسابات', 'د پور حساب خوندي کول') ?>
            </button>
        </form>
    </div>
</div>

<!-- Modal 2: Pay Installment Modal with Categorization & Capital/Profit Split -->
<div id="payModal" class="feature-modal-backdrop" onclick="if(event.target === this) closePayModal();">
    <div class="feature-modal-box" style="max-width: 520px; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <button type="button" class="feature-modal-close" onclick="closePayModal();">&times;</button>
        <h3 id="payModalTitle" style="font-size: 16px; font-weight: 800; margin-bottom: 14px; color: var(--text-main);">
            <i class="fa-solid fa-money-bill-transfer ml-1" style="color: var(--success);"></i> <?= __t('Receive / Settle Debt Payment', 'رسیدگی و ثبت قسط', 'د پور قسط وصولول او تصفیه') ?>
        </h3>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="pay_installment">
            <?php currency_fields(['amount'=>2,'capital_amount'=>2,'profit_amount'=>2]); ?>
            <div id="paymentCurrencyNotice" class="alert alert-warning" style="display:none"><?= __t('Different currency: this payment does not reduce the original balance.','ارز متفاوت است؛ این پرداخت ماندهٔ حساب اصلی را کم نمی‌کند.','اسعار توپیر لري؛ دا تادیه اصلي پاتې حساب نه کموي.') ?></div>
            <input type="hidden" id="payDebtId" name="debt_id" value="0">
            <input type="hidden" id="paySaleTotal" value="0">
            <input type="hidden" id="paySaleProfit" value="0">

            <!-- Summary header -->
            <div style="background: var(--surface-muted); padding: 12px 16px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <span style="font-size: 13px; color: var(--text-muted);"><?= __t('Account Contact:', 'طرف حساب:', 'د حساب کس:') ?> <strong id="payCustomerName" style="color: var(--text-main);">-</strong></span>
                    <span style="font-size: 13px; color: var(--danger);"><?= __t('Remaining:', 'باقی‌مانده:', 'پاتې:') ?> <strong id="payRemainingAmt">0 <?= get_currency_label() ?></strong></span>
                </div>
            </div>

            <!-- Amount Input -->
            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label" style="font-weight: 700; color: var(--text-main);"><?= sprintf(__t('Payment Amount (%s):', 'مبلغ پرداختی این قسط (%s):', 'د دې قسط اندازه (%s):'), get_currency_label()) ?></label>
                <input type="number" step="any" id="payAmount" name="amount" class="form-control numeric-input" required min="1" oninput="recalcBreakdown();" style="font-size: 16px; font-weight: 700; color: var(--text-main);">
            </div>

            <!-- Category / Reason -->
            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label" style="font-weight: 700; color: var(--text-main);">
                    <i class="fa-solid fa-tag ml-1" style="color: var(--info);"></i> <?= __t('Payment Reason / Category:', 'از درک چی بوده؟ (دلیل / منبع پرداخت):', 'د تادیې لامل:') ?>
                </label>
                <select name="payment_reason" id="payReasonSelect" class="form-control" onchange="toggleCustomReason(this.value);" style="font-size: 13px;">
                    <option value="وصولی نسیه فروش کالا"><?= __t('Sales Receivable Payment', 'وصولی قرضهٔ فروش کالا', 'د پلور تادیه') ?></option>
                    <option value="وصولی نسیه لوازم جانبی"><?= __t('Accessories Receivable Payment', 'وصولی نسیه لوازم جانبی و قطعات', 'د لوازمو پلور قسط') ?></option>
                    <option value="تسویه حساب بل دکان"><?= __t('Shop Invoice Settlement', 'تسویه حساب بل دکان', 'د هټۍ د بل تصفیه') ?></option>
                    <option value="برگشت قرض نقدی / دستی"><?= __t('Cash Loan Return', 'برگشت قرض نقدی / دستی', 'د نغدي پور بېرته ورکړه') ?></option>
                    <option value="بازپرداخت بدهی دکان به عمده‌فروش"><?= __t('Payment to Wholesaler (Capital)', 'بازپرداخت بدهی دکان به عمده‌فروش (سرمایه)', 'عمده پلورونکي ته د پور تادیه') ?></option>
                    <option value="سایر"><?= __t('Other (Custom notes)', 'سایر موارد (تایپ یادداشت دستی)', 'نور') ?></option>
                </select>
                <div id="customReasonWrapper" style="display: none; margin-top: 8px;">
                    <input type="text" name="custom_reason" id="customReasonInput" class="form-control" placeholder="<?= __t('Short description of payment source...', 'توضیح کوتاه منبع این پرداخت...', 'د تادیې لنډ وضاحت...') ?>">
                </div>
            </div>

            <!-- Capital vs Net Profit Breakdown Card -->
            <div style="background: var(--info-soft); border: 1.5px solid #bae6fd; border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 13px; font-weight: 800; color: var(--info);">
                        <i class="fa-solid fa-scale-balanced ml-1"></i> <?= __t('Capital & Net Profit Categorization:', 'کته‌گوری‌بندی سرمایه و مفاد خالص:', 'د پانګې او خالصې ګټې وېش:') ?>
                    </span>
                    <span id="splitRatioBadge" style="font-size: 11px; background: var(--surface); padding: 2px 8px; border-radius: 4px; color: var(--info); border: 1px solid #7dd3fc;">
                        <?= __t('Balanced Split', 'توزیع متعادل', 'انډول وېش') ?>
                    </span>
                </div>

                <!-- Quick Presets -->
                <div style="display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-sm" onclick="setSplitMode('auto');" style="background: var(--info); color: var(--surface); font-size: 11px; padding: 3px 8px; border: none; border-radius: 4px;">
                        <i class="fa-solid fa-wand-magic-sparkles ml-1"></i> <?= __t('Smart Calculate Invoice Profit', 'محاسبه هوشمند سود بل', 'د بل د ګټې هوښیاره محاسبه') ?>
                    </button>
                    <button type="button" class="btn btn-sm" onclick="setSplitMode('full_capital');" style="background: var(--border-color); color: var(--text-main); font-size: 11px; padding: 3px 8px; border: none; border-radius: 4px;">
                        <?= __t('100% Principal Capital', '۱۰۰٪ اصل سرمایه دکان', '۱۰۰٪ اصلي پانګه') ?>
                    </button>
                    <button type="button" class="btn btn-sm" onclick="setSplitMode('full_profit');" style="background: var(--border-color); color: var(--text-main); font-size: 11px; padding: 3px 8px; border: none; border-radius: 4px;">
                        <?= __t('100% Net Profit', '۱۰۰٪ مفاد خالص دکان', '۱۰۰٪ خالص ګټه') ?>
                    </button>
                </div>

                <!-- Two Interactive Inputs -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label class="form-label" style="font-size: 12px; color: var(--info); margin-bottom: 4px;">
                            <?= __t('Principal Capital Share:', 'سهم اصل سرمایه دکان:', 'د اصلي پانګې ونډه:') ?>
                        </label>
                        <input type="number" step="any" id="payCapital" name="capital_amount" class="form-control numeric-input" style="background: var(--surface); border-color: #7dd3fc; font-weight: 700; color: var(--info);" oninput="syncFromCapital();">
                        <span style="font-size: 10px; color: var(--text-muted);"><?= __t('(Wholesale purchase cost)', '(هزینه خرید/سرمایه اولیه کالا)', '(د توکو لومړنی لګښت)') ?></span>
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 12px; color: var(--success); margin-bottom: 4px;">
                            <?= __t('Net Profit Share:', 'سهم مفاد خالص دکان:', 'د خالصې ګټې ونډه:') ?>
                        </label>
                        <input type="number" step="any" id="payProfit" name="profit_amount" class="form-control numeric-input" style="background: var(--surface); border-color: #86efac; font-weight: 700; color: var(--success);" oninput="syncFromProfit();">
                        <span style="font-size: 10px; color: var(--text-muted);"><?= __t('(Net profit from payment)', '(سود حاصله از این وصولی)', '(له دې تادیې خالص ګټه)') ?></span>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                <div class="form-group">
                    <label class="form-label"><?= __t('Payment Date:', 'تاریخ رسیدگی:', 'د تادیې نېټه:') ?></label>
                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __t('Receipt Notes / Transfer Ref:', 'توضیحات رسید / شماره حواله:', 'د تادیې تفصیل / د حوالې شمېره:') ?></label>
                    <input type="text" name="notes" class="form-control" placeholder="<?= __t('Receipt number or transfer reference', 'شماره رسید، حواله، یا نام شخص', 'د رسید یا حوالې شمېره') ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="width: 100%; justify-content: center; padding: 12px; font-weight: 700; font-size: 14px;">
                <i class="fa-solid fa-check ml-1"></i> <?= __t('Save Payment with Capital & Profit Split', 'ثبت پرداخت با تفکیک سرمایه و مفاد', 'د پانګې او ګټې په بېلوالي تادیه ثبتول') ?>
            </button>
        </form>
    </div>
</div>

<!-- Modal 3: View Installment History & Breakdown -->
<div id="historyModal" class="feature-modal-backdrop" onclick="if(event.target === this) closeHistoryModal();">
    <div class="feature-modal-box" style="max-width: 680px; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <button type="button" class="feature-modal-close" onclick="closeHistoryModal();">&times;</button>
        <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 14px; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-receipt" style="color: var(--info);"></i>
            <span><?= __t('Installments History & Capital/Profit Breakdown - ', 'ریز اقساط و تفکیک سرمایه/مفاد - ', 'د قسطونو تاریخچه او وېش - ') ?><strong id="histCustomerName" style="color: var(--info);"></strong></span>
        </h3>

        <!-- Summary Cards -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px;">
            <div style="background: var(--surface-muted); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 14px;">
                <div style="font-size: 11px; color: var(--text-muted);"><?= __t('Total Paid Amount:', 'مجموع کل پرداختی:', 'ټولټال تادیه شوي:') ?></div>
                <div id="histTotPaid" style="font-size: 15px; font-weight: 800; color: var(--text-main); margin-top: 4px;">0 <?= get_currency_label() ?></div>
            </div>
            <div style="background: var(--info-soft); border: 1px solid #bae6fd; border-radius: 8px; padding: 10px 14px;">
                <div style="font-size: 11px; color: var(--info);"><?= __t('Recovered Principal Capital:', 'کل اصل سرمایه وصول شده:', 'بېرته راټوله شوې اصلي پانګه:') ?></div>
                <div id="histTotCapital" style="font-size: 15px; font-weight: 800; color: var(--info); margin-top: 4px;">0 <?= get_currency_label() ?></div>
            </div>
            <div style="background: var(--success-soft); border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 14px;">
                <div style="font-size: 11px; color: var(--success);"><?= __t('Realized Net Profit:', 'کل مفاد خالص بدست‌آمده:', 'ټولټال ترلاسه شوې ګټه:') ?></div>
                <div id="histTotProfit" style="font-size: 15px; font-weight: 800; color: var(--success); margin-top: 4px;">0 <?= get_currency_label() ?></div>
            </div>
        </div>

        <!-- History Table -->
        <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
            <table class="table" style="font-size: 12.5px;">
                <thead style="position: sticky; top: 0; background: var(--surface); z-index: 1;">
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th><?= __t('Date', 'تاریخ', 'نېټه') ?></th>
                        <th><?= __t('Payment Reason', 'از درک چی بوده', 'د تادیې لامل') ?></th>
                        <th><?= __t('Capital Share', 'سهم سرمایه', 'د پانګې ونډه') ?></th>
                        <th><?= __t('Profit Share', 'سهم مفاد', 'د ګټې ونډه') ?></th>
                        <th><?= __t('Total Installment', 'کل قسط', 'ټول قسط') ?></th>
                        <th><?= __t('Notes / Recorded By', 'یادداشت / ثبت‌کننده', 'توضیحات / ثبتوونکی') ?></th>
                    </tr>
                </thead>
                <tbody id="histTableBody">
                    <!-- Populated by JavaScript -->
                </tbody>
            </table>
        </div>

        <div style="margin-top: 14px; text-align: <?= $currentDir === 'rtl' ? 'left' : 'right' ?>;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeHistoryModal();">
                <?= __t('Close Window', 'بستن پنجره', 'کړکۍ وتړئ') ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal 4: Edit Debt Modal -->
<div id="editDebtModal" class="feature-modal-backdrop" onclick="if(event.target === this) closeEditDebtModal();">
    <div class="feature-modal-box" style="max-width: 520px; text-align: <?= $currentDir === 'rtl' ? 'right' : 'left' ?>;">
        <button type="button" class="feature-modal-close" onclick="closeEditDebtModal();">&times;</button>
        <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 16px; color: var(--text-main);">
            <i class="fa-solid fa-pen-to-square ml-1" style="color: var(--info);"></i> <?= __t('Edit Debt Account Details', 'ویرایش حساب قرضه', 'د پور حساب سمول') ?>
        </h3>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_debt">
            <?php currency_fields(['total_amount'=>2]); ?>
            <input type="hidden" name="debt_id" id="editDebtId" value="0">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group">
                    <label class="form-label"><?= __t('Person / Contact Name:', 'نام طرف حساب:', 'د حساب کس نوم:') ?></label>
                    <input type="text" name="person_name" id="editPersonName" class="form-control" required placeholder="<?= __t('Name', 'نام شخص', 'نوم') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __t('Phone Number:', 'شماره تماس:', 'د تلیفون شمېره:') ?></label>
                    <input type="text" name="person_phone" id="editPersonPhone" class="form-control numeric-input" placeholder="07xxxxxxxx">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group">
                    <label class="form-label"><?= sprintf(__t('Total Debt Amount (%s):', 'مجموع کل قرضه (%s):', 'د پور ټوله اندازه (%s):'), get_currency_label()) ?></label>
                    <input type="number" step="any" name="total_amount" id="editTotalAmount" class="form-control numeric-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __t('Account Status:', 'وضعیت حساب:', 'د حساب حالت:') ?></label>
                    <select name="status" id="editStatus" class="form-control">
                        <option value="باز"><?= __t('Active (Open)', 'جاری (باز)', 'خلاص (پاتې)') ?></option>
                        <option value="تسویه"><?= __t('Fully Settled', 'تسویه کامل', 'بشپړه تصفیه') ?></option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label"><?= __t('Due Date / Settlement Deadline:', 'موعد بازپرداخت:', 'د تادیې نېټه:') ?></label>
                <input type="date" name="due_date" id="editDueDate" class="form-control">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label"><?= __t('Additional Notes & Details:', 'یادداشت و جزئیات:', 'اضافي توضیحات:') ?></label>
                <input type="text" name="notes" id="editNotes" class="form-control">
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center; padding: 10px;">
                    <i class="fa-solid fa-check ml-1"></i> <?= __t('Save Changes', 'ذخیره تغییرات', 'بدلونونه خوندي کړئ') ?>
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeEditDebtModal();">
                    <?= __t('Cancel', 'انصراف', 'لغوه') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Embedded Installments JSON Map for Fast Instant Client-Side Viewing -->
<script>
const initialPayment=document.getElementById('initialPaymentCurrency');
initialPayment.addEventListener('change',()=>initialPayment.dataset.manual='1');
initialPayment.form.addEventListener('currencychange',()=>{if(!initialPayment.dataset.manual)initialPayment.value=MoneyForm.code(initialPayment.form);});
const installmentsData = <?= json_for_html($installmentsMap, JSON_UNESCAPED_UNICODE) ?>;
const debtsData = <?= json_for_html(array_column($debts, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
const isEnglish = <?= json_for_html($currentLang === 'en') ?>;
const currencyLabel = <?= json_for_html(get_currency_label()) ?>;

function openEditDebtModal(data) {
    MoneyForm.setCurrency(document.getElementById('editTotalAmount').form,data.currency_code);
    document.getElementById('editDebtId').value = data.id;
    document.getElementById('editPersonName').value = data.customer_name || '';
    document.getElementById('editPersonPhone').value = data.customer_phone || '';
    MoneyForm.setLedger(document.getElementById('editTotalAmount').form,'total_amount',data.total_amount || 0);
    document.getElementById('editDueDate').value = data.due_date || '';
    document.getElementById('editStatus').value = data.status || 'باز';
    document.getElementById('editNotes').value = data.notes || '';
    document.getElementById('editDebtModal').style.display = 'flex';
}

function closeEditDebtModal() {
    document.getElementById('editDebtModal').style.display = 'none';
}

function toggleAllDebts(headerMaster) {
    const checkboxes = document.querySelectorAll('.debt-chk');
    checkboxes.forEach(cb => cb.checked = headerMaster.checked);
    updateDebtBulkBar();
}

function selectAllDebtRows(checkAll) {
    const checkboxes = document.querySelectorAll('.debt-chk');
    checkboxes.forEach(cb => cb.checked = checkAll);
    const master = document.getElementById('selectAllDebtsHeader');
    if (master) master.checked = checkAll;
    updateDebtBulkBar();
}

function updateDebtBulkBar() {
    const checkboxes = document.querySelectorAll('.debt-chk:checked');
    const bar = document.getElementById('debtBulkActionBar');
    const countText = document.getElementById('debtSelectedCountText');
    const count = checkboxes.length;

    if (count > 0) {
        bar.style.display = 'block';
        countText.textContent = isEnglish ? count.toLocaleString('en-US') : count.toLocaleString('fa-IR');
    } else {
        bar.style.display = 'none';
    }
}

function confirmBulkDeleteDebts() {
    const checkboxes = document.querySelectorAll('.debt-chk:checked');
    if (checkboxes.length === 0) {
        alert(isEnglish ? 'Please select at least one debt account to delete.' : 'لطفاً حداقل یک قرضه را برای حذف انتخاب نمایید.');
        return;
    }

    const msg = isEnglish 
        ? `Are you sure you want to delete ${checkboxes.length} selected debt account(s)? All related installments will also be removed.`
        : `آیا از حذف تعداد ${checkboxes.length} حساب قرضه انتخاب شده به صورت گروهی اطمینان کامل دارید؟ تمامی اقساط مربوطه نیز حذف خواهند شد.`;

    if (!confirm(msg)) {
        return;
    }

    const container = document.getElementById('debtBulkDeleteInputsContainer');
    container.innerHTML = '';
    checkboxes.forEach(cb => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'selected_ids[]';
        hidden.value = cb.value;
        container.appendChild(hidden);
    });

    document.getElementById('debtBulkDeleteForm').submit();
}

function openNewDebtModal() {
    document.getElementById('newDebtModal').style.display = 'flex';
}
function closeNewDebtModal() {
    document.getElementById('newDebtModal').style.display = 'none';
}

function refreshPaymentCurrency(form){
 document.getElementById('payRemainingAmt').textContent=MoneyForm.displayLedger(form,form.dataset.remainingLedger||0)+' '+(form.dataset.debtCurrency||currencyLabel);
 const same=MoneyForm.code(form)===form.dataset.debtCurrency;
 if(same)form.elements.amount.max=form.dataset.remainingLedger;else form.elements.amount.removeAttribute('max');
 document.getElementById('paymentCurrencyNotice').style.display=same?'none':'block';
}
document.getElementById('payAmount').form.addEventListener('currencychange',function(){refreshPaymentCurrency(this);recalcBreakdown();});
function openPayModal(id, name, remaining, type, saleTotal, saleProfit) {
    document.getElementById('payDebtId').value = id;
    document.getElementById('payCustomerName').textContent = name;
    const form=document.getElementById('payAmount').form;form.dataset.remainingLedger=remaining;form.dataset.debtCurrency=debtsData[id].currency_code;form.dataset.profitKnown=debtsData[id].profit_known;MoneyForm.setCurrency(form,form.dataset.debtCurrency);refreshPaymentCurrency(form);
    MoneyForm.setLedger(document.getElementById('payAmount').form,'amount',remaining,remaining);
    document.getElementById('paySaleTotal').value = saleTotal || 0;
    document.getElementById('paySaleProfit').value = saleProfit || 0;

    const modalTitle = isEnglish
        ? ((type === 'payable') ? 'Settle Debt to ' + name : 'Receive Payment from ' + name)
        : ((type === 'payable') ? 'پرداخت بدهی به ' + name : 'دریافت طلب از ' + name);

    document.getElementById('payModalTitle').innerHTML = '<i class="fa-solid fa-money-bill-transfer ml-1" style="color: var(--success);"></i> ' + NikxorUI.escape(modalTitle);

    recalcBreakdown();
    document.getElementById('payModal').style.display = 'flex';
}
function closePayModal() {
    document.getElementById('payModal').style.display = 'none';
}

function toggleCustomReason(val) {
    const wrap = document.getElementById('customReasonWrapper');
    if (val === 'سایر') {
        wrap.style.display = 'block';
        document.getElementById('customReasonInput').focus();
    } else {
        wrap.style.display = 'none';
    }
}

function recalcBreakdown() {
    const form=document.getElementById('payAmount').form;
    if(MoneyForm.code(form)!==form.dataset.debtCurrency||form.dataset.profitKnown==='0'){
        document.getElementById('payCapital').value='0';document.getElementById('payProfit').value='0';
        document.getElementById('splitRatioBadge').textContent=isEnglish?'No automatic split':'تفکیک خودکار ندارد';return;
    }
    const amt = parseFloat(document.getElementById('payAmount').value) || 0;
    const sTot = parseFloat(document.getElementById('paySaleTotal').value) || 0;
    const sProf = parseFloat(document.getElementById('paySaleProfit').value) || 0;

    if (amt <= 0) {
        document.getElementById('payCapital').value = 0;
        document.getElementById('payProfit').value = 0;
        document.getElementById('splitRatioBadge').textContent = isEnglish ? 'Amount: 0' : 'مبلغ صفر';
        return;
    }

    if (sTot > 0 && sProf > 0) {
        const profitRatio = sProf / sTot;
        const prof = Math.round(amt * profitRatio * 100) / 100;
        const cap = Math.round((amt - prof) * 100) / 100;
        document.getElementById('payCapital').value = cap;
        document.getElementById('payProfit').value = prof;
        const capPct = Math.round((cap / amt) * 100);
        const profPct = 100 - capPct;
        document.getElementById('splitRatioBadge').textContent = isEnglish 
            ? `Capital: ${capPct}% | Profit: ${profPct}%`
            : `سرمایه: ${capPct}% | مفاد: ${profPct}%`;
    } else {
        document.getElementById('payCapital').value = amt;
        document.getElementById('payProfit').value = 0;
        document.getElementById('splitRatioBadge').textContent = isEnglish ? '100% Principal Capital' : '۱۰۰٪ اصل سرمایه';
    }
}

function setSplitMode(mode) {
    const amt = parseFloat(document.getElementById('payAmount').value) || 0;
    if (mode === 'auto') {
        recalcBreakdown();
    } else if (mode === 'full_capital') {
        document.getElementById('payCapital').value = amt;
        document.getElementById('payProfit').value = 0;
        document.getElementById('splitRatioBadge').textContent = isEnglish ? '100% Principal Capital' : '۱۰۰٪ اصل سرمایه';
    } else if (mode === 'full_profit') {
        document.getElementById('payCapital').value = 0;
        document.getElementById('payProfit').value = amt;
        document.getElementById('splitRatioBadge').textContent = isEnglish ? '100% Net Profit' : '۱۰۰٪ مفاد خالص';
    }
}

function syncFromCapital() {
    const amt = parseFloat(document.getElementById('payAmount').value) || 0;
    const cap = parseFloat(document.getElementById('payCapital').value) || 0;
    const prof = Math.max(0, amt - cap);
    document.getElementById('payProfit').value = prof;
    if (amt > 0) {
        const capPct = Math.round((cap / amt) * 100);
        document.getElementById('splitRatioBadge').textContent = isEnglish 
            ? `Capital: ${capPct}% | Profit: ${100 - capPct}%`
            : `سرمایه: ${capPct}% | مفاد: ${100 - capPct}%`;
    }
}

function syncFromProfit() {
    const amt = parseFloat(document.getElementById('payAmount').value) || 0;
    const prof = parseFloat(document.getElementById('payProfit').value) || 0;
    const cap = Math.max(0, amt - prof);
    document.getElementById('payCapital').value = cap;
    if (amt > 0) {
        const capPct = Math.round((cap / amt) * 100);
        document.getElementById('splitRatioBadge').textContent = isEnglish 
            ? `Capital: ${capPct}% | Profit: ${100 - capPct}%`
            : `سرمایه: ${capPct}% | مفاد: ${100 - capPct}%`;
    }
}


function openHistoryModal(debtId) {
    const debt = debtsData[debtId] || {};
    const records = installmentsData[debtId] || [];

    document.getElementById('histCustomerName').textContent = debt.customer_name || (isEnglish ? 'Debt Record' : 'پرونده قرضه');

    const totals={};
    let totPaid = 0;
    let totCapital = 0;
    let totProfit = 0;

    let html = '';
    if (records.length === 0) {
        html = `<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 20px;">${isEnglish ? 'No installments recorded yet.' : 'هیچ پرداختی تا کنون ثبت نشده است.'}</td></tr>`;
    } else {
        records.forEach((r, idx) => {
            const amt = parseFloat(r.amount) || 0;
            const cap = parseFloat(r.capital_amount) || 0;
            const prof = parseFloat(r.profit_amount) || 0;
            const code=r.currency_code||'AFN';if(!totals[code])totals[code]={paid:0,capital:0,profit:0,known:true};
            totals[code].paid+=amt;totals[code].capital+=cap;totals[code].profit+=prof;totals[code].known=totals[code].known&&Boolean(Number(r.split_known));

            let reasonLabel = r.payment_reason || (isEnglish ? 'Installment Payment' : 'وصولی قرضه');
            if (isEnglish) {
                if (reasonLabel === 'وصولی نسیه فروش کالا') reasonLabel = 'Sales Payment';
                else if (reasonLabel === 'وصولی نسیه فروش موبایل') reasonLabel = 'Mobile Sale Installment';
                else if (reasonLabel === 'وصولی نسیه لوازم جانبی') reasonLabel = 'Accessories Installment';
                else if (reasonLabel === 'تسویه حساب بل دکان') reasonLabel = 'Invoice Settlement';
                else if (reasonLabel === 'برگشت قرض نقدی / دستی') reasonLabel = 'Cash Loan Return';
                else if (reasonLabel === 'بازپرداخت بدهی دکان به عمده‌فروش') reasonLabel = 'Payment to Wholesaler';
                else if (reasonLabel === 'پیش‌پرداخت اولیه هنگام ثبت حساب') reasonLabel = 'Initial Down Payment';
                else if (reasonLabel === 'پیش‌پرداخت نقد اولیه') reasonLabel = 'Initial Cash Down Payment';
                else if (reasonLabel === 'وصولی قرضه') reasonLabel = 'Debt Collection';
            }

            if(!Number(r.applied_to_balance))reasonLabel+=' — '+(isEnglish?'Unapplied':'تسویه‌نشده');
            html += `<tr>
                <td>${idx + 1}</td>
                <td><strong>${NikxorUI.escape(r.payment_date || '-')}</strong></td>
                <td><span class="badge" style="background: var(--surface-muted); color: var(--text-main); border: 1px solid var(--border-dark);">${NikxorUI.escape(reasonLabel)}</span></td>
                <td style="color: var(--info); font-weight: 700;">${Number(r.split_known)?Number(cap).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}):'—'} ${code}</td>
                <td style="color: var(--success); font-weight: 700;">${Number(r.split_known)?Number(prof).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}):'—'} ${code}</td>
                <td style="font-weight: 800;">${Number(amt).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})} ${code}</td>
                <td style="font-size: 11px; color: var(--text-muted);">${NikxorUI.escape(r.notes || '-')} <span style="font-size: 10px; color: var(--text-light);">(${NikxorUI.escape(r.creator_name || (isEnglish ? 'System' : 'سیستم'))})</span></td>
            </tr>`;
        });
    }

    for(const [id,key] of [['histTotPaid','paid'],['histTotCapital','capital'],['histTotProfit','profit']]){
        document.getElementById(id).textContent=Object.entries(totals).map(([code,t])=>(key!=='paid'&&!t.known?'—':t[key].toFixed(2))+' '+code).join(' | ')||'—';
    }
    document.getElementById('histTableBody').innerHTML = html;

    document.getElementById('historyModal').style.display = 'flex';
}

function closeHistoryModal() {
    document.getElementById('historyModal').style.display = 'none';
}
</script>

<?php if(isset($list)){listing_controls_form($list);listing_footer($list);}require_once __DIR__ . '/../includes/footer.php'; ?>
