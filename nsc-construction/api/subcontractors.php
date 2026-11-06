<?php
/**
 * NSC Subcontractors & Trade Contracts API
 * Manages Subcontractors, Certified Work, Paid Amounts, Retention & Balances
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

sendSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getConnection();
$user = authenticateUser(false);
$tenantId = getTenantId($user);
requireModule($user, 'contractors', $method !== 'GET');

if ($method === 'GET') {
    $projectId = $_GET['projectId'] ?? null;
    $sql = "SELECT s.*, p.name as project_name 
            FROM subcontractors s 
            LEFT JOIN projects p ON s.project_id = p.id AND p.tenant_id = :tid";
    $sql .= " WHERE s.tenant_id = :tid";
    $params = ['tid' => $tenantId];
    if ($projectId) {
        $sql .= " AND s.project_id = :pid";
        $params['pid'] = $projectId;
    }
    $sql .= " ORDER BY s.created_at DESC";
    $stmt = prepareStatement($db, $sql);
    $stmt->execute($params);
    jsonResponse(true, ['subcontractors' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $input = readJson();
    validateBusinessInput($db, $user, $input);

    $id = $input['id'] ?? generateUuid();
        guardUpsert($db, 'subcontractors', $id, $tenantId);
    $name = trim($input['name'] ?? $input['contractorName'] ?? '');
    $trade = trim($input['specialty'] ?? $input['tradeSpecialty'] ?? 'قراردادی عمومی');
    $phone = trim($input['phone'] ?? '');
    $projectId = $input['projectId'] ?? null;
    $contractValue = (float)($input['contractValue'] ?? 0);
    $certifiedAmount = (float)($input['certifiedAmount'] ?? 0);
    $paidAmount = (float)($input['paidAmount'] ?? 0);
    $retentionAmount = (float)($input['retentionAmount'] ?? 0);
    $remaining = max(0, $certifiedAmount - $paidAmount);
    $status = $input['status'] ?? 'active';

    if (empty($name)) {
        jsonResponse(false, null, 'نام قراردادی الزامی است.', 400);
    }

    $stmt = prepareStatement($db, "
        INSERT INTO subcontractors (tenant_id, 
            id, contractor_name, trade_specialty, phone, project_id,
            contract_value, certified_work_amount, paid_amount,
            retention_amount, remaining_balance, status
        ) VALUES (:tenant_scope, 
            :id, :name, :trade, :phone, :pid,
            :cval, :cert, :paid,
            :ret, :rem, :status
        ) ON DUPLICATE KEY UPDATE 
            contractor_name = IF(tenant_id = VALUES(tenant_id), VALUES(contractor_name), contractor_name),
            trade_specialty = IF(tenant_id = VALUES(tenant_id), VALUES(trade_specialty), trade_specialty),
            phone = IF(tenant_id = VALUES(tenant_id), VALUES(phone), phone),
            contract_value = IF(tenant_id = VALUES(tenant_id), VALUES(contract_value), contract_value),
            certified_work_amount = IF(tenant_id = VALUES(tenant_id), VALUES(certified_work_amount), certified_work_amount),
            paid_amount = IF(tenant_id = VALUES(tenant_id), VALUES(paid_amount), paid_amount),
            retention_amount = IF(tenant_id = VALUES(tenant_id), VALUES(retention_amount), retention_amount),
            remaining_balance = IF(tenant_id = VALUES(tenant_id), VALUES(remaining_balance), remaining_balance),
            status = IF(tenant_id = VALUES(tenant_id), VALUES(status), status)
    ");

    $stmt->execute(['tenant_scope' => $tenantId, 
        'id' => $id,
        'name' => $name,
        'trade' => $trade,
        'phone' => $phone,
        'pid' => $projectId,
        'cval' => $contractValue,
        'cert' => $certifiedAmount,
        'paid' => $paidAmount,
        'ret' => $retentionAmount,
        'rem' => $remaining,
        'status' => $status
    ]);

    logAudit($db, $user['id'], $user['name'], 'update', 'contractors', $id, "ثبت/ویرایش مقاطعه‌کار: $name");
    jsonResponse(true, ['id' => $id], 'اطلاعات پیمانکار با موفقیت ثبت شد.');
}

jsonResponse(false, null, 'Action is not available.', 404);
