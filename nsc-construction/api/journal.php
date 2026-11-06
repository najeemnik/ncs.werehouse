<?php
/**
 * NSC Double-Entry General Journal & Adjustments API
 * Solves Excel Flaw #5: Strict Balanced Journal System (Total Debits == Total Credits)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

sendSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getConnection();
$user = authenticateUser(false);
$tenantId = getTenantId($user);
requireModule($user, 'accounting', $method !== 'GET');

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'entries';

    if ($action === 'chart_of_accounts') {
        $stmt = $db->query("SELECT * FROM chart_of_accounts ORDER BY account_code ASC");
        jsonResponse(true, ['accounts' => $stmt->fetchAll()]);
    }

    if ($action === 'entries') {
        $stmt = prepareStatement($db, "
            SELECT j.*, p.name as project_name 
            FROM journal_entries j 
            LEFT JOIN projects p ON j.project_id = p.id  AND p.tenant_id = :tid
            WHERE j.tenant_id = :tid ORDER BY j.entry_date DESC LIMIT 100
        ");
        $stmt->execute(['tid' => $tenantId]);
        $entries = $stmt->fetchAll();

        foreach ($entries as &$entry) {
            $lStmt = prepareStatement($db, "
                SELECT jl.*, ca.account_name, ca.account_code 
                FROM journal_lines jl 
                LEFT JOIN chart_of_accounts ca ON jl.account_id = ca.id 
                WHERE jl.journal_entry_id = :jid AND jl.tenant_id = :tid
            ");
            $lStmt->execute(['tenant_scope' => $tenantId, 'tid' => $tenantId, 'jid' => $entry['id']]);
            $entry['lines'] = $lStmt->fetchAll();
        }

        jsonResponse(true, ['entries' => $entries]);
    }
}

if ($method === 'POST') {
    $input = readJson();
    validateBusinessInput($db, $user, $input);

    $id = generateUuid();
    $voucherNo = 'JV-' . date('ymd') . '-' . rand(100, 999);
    $date = !empty($input['entryDate']) ? $input['entryDate'] : date('Y-m-d');
    $narration = trim($input['narration'] ?? 'سند تعدیلات حسابداری');
    $projectId = $input['projectId'] ?? null;
    $lines = $input['lines'] ?? [];

    if (empty($lines) || count($lines) < 2) {
        jsonResponse(false, null, 'سند روزنامچه باید حداقل دو ردیف حساب (بدهکار و بستانکار) داشته باشد.', 400);
    }

    $totalDebit = 0;
    $totalCredit = 0;
    foreach ($lines as $line) {
        $totalDebit += (float)($line['debitAmount'] ?? $line['debit'] ?? 0);
        $totalCredit += (float)($line['creditAmount'] ?? $line['credit'] ?? 0);
    }

    // بررسی اصل تعادل حسابداری دوطرفه (Total Debits == Total Credits)
    if (abs($totalDebit - $totalCredit) > 0.01) {
        jsonResponse(false, null, "سند تراز نیست! مجموع بدهکار ($totalDebit) با بستانکار ($totalCredit) برابر نمی‌باشد.", 400);
    }

    $db->beginTransaction();
    try {
        $stmt = prepareStatement($db, "
            INSERT INTO journal_entries (tenant_id, 
                id, voucher_number, entry_date, project_id, narration,
                total_debit, total_credit, is_balanced, is_posted, created_by_user_id
            ) VALUES (:tenant_scope, 
                :id, :vno, :dt, :pid, :nar,
                :deb, :cred, 1, 1, :uid
            )
        ");
        $stmt->execute(['tenant_scope' => $tenantId, 
            'id' => $id,
            'vno' => $voucherNo,
            'dt' => $date,
            'pid' => $projectId,
            'nar' => $narration,
            'deb' => $totalDebit,
            'cred' => $totalCredit,
            'uid' => $user['id']
        ]);

        $lineStmt = prepareStatement($db, "
            INSERT INTO journal_lines (tenant_id, 
                id, journal_entry_id, account_id, debit_amount, credit_amount, memo
            ) VALUES (:tenant_scope, 
                :lid, :jid, :aid, :deb, :cred, :memo
            )
        ");

        foreach ($lines as $line) {
            $lid = generateUuid();
            $lineStmt->execute(['tenant_scope' => $tenantId, 
                'lid' => $lid,
                'jid' => $id,
                'aid' => $line['accountId'],
                'deb' => (float)($line['debitAmount'] ?? $line['debit'] ?? 0),
                'cred' => (float)($line['creditAmount'] ?? $line['credit'] ?? 0),
                'memo' => $line['memo'] ?? null
            ]);
        }

        $db->commit();
        logAudit($db, $user['id'], $user['name'], 'create', 'journal', $id, "ثبت سند روزنامچه $voucherNo به مبلغ $totalDebit AFN");
        jsonResponse(true, ['id' => $id, 'voucherNumber' => $voucherNo], 'سند حسابداری با رعایت تراز دوطرفه با موفقیت ثبت گردید.');
    } catch (Exception $e) {
        $db->rollBack();
        error_log($e->getMessage());
            jsonResponse(false, null, 'The operation failed.', 500);
    }
}

jsonResponse(false, null, 'Action is not available.', 404);
