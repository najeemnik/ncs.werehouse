<?php
/**
 * NSC Apartment Units & Commercial Pre-sales API
 * Manages Units, Prices (USD & AFN), Client Information & Outstanding Receivables
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

sendSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getConnection();
$user = authenticateUser(false);
$tenantId = getTenantId($user);
requireModule($user, 'apartments', $method !== 'GET');

if ($method === 'GET') {
    $projectId = $_GET['projectId'] ?? null;
    $sql = "SELECT u.*, p.name as project_name, c.client_name, c.phone as client_phone 
            FROM apartment_units u 
            LEFT JOIN projects p ON u.project_id = p.id  AND p.tenant_id = :tid
            LEFT JOIN clients c ON u.client_id = c.id AND c.tenant_id = :tid";
    $sql .= " WHERE u.tenant_id = :tid";
    $params = ['tid' => $tenantId];
    if ($projectId) {
        $sql .= " AND u.project_id = :pid";
        $params['pid'] = $projectId;
    }
    $sql .= " ORDER BY u.floor_number ASC, u.unit_number ASC";
    $stmt = prepareStatement($db, $sql);
    $stmt->execute($params);
    jsonResponse(true, ['units' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $input = readJson();
    validateBusinessInput($db, $user, $input);

    $id = $input['id'] ?? generateUuid();
        guardUpsert($db, 'apartment_units', $id, $tenantId);
    $projectId = $input['projectId'] ?? '';
    $unitNumber = trim($input['unitNumber'] ?? '');
    $floor = (int)($input['floorNumber'] ?? 1);
    $area = (float)($input['areaSqm'] ?? 0);
    $unitType = $input['unitType'] ?? 'apartment';
    $priceUsd = (float)($input['priceUsd'] ?? 0);
    $priceAfn = (float)($input['priceAfn'] ?? 0);
    $totalPaid = (float)($input['totalPaidAfn'] ?? 0);
    $balance = max(0, $priceAfn - $totalPaid);
    $status = $input['status'] ?? 'available';

    if (empty($projectId) || empty($unitNumber)) {
        jsonResponse(false, null, 'شناسه پروژه و شماره پلاک الزامی هستند.', 400);
    }

    $stmt = prepareStatement($db, "
        INSERT INTO apartment_units (tenant_id, 
            id, project_id, unit_number, floor_number, area_sqm,
            unit_type, price_usd, price_afn, total_paid_afn,
            balance_receivable_afn, status, notes
        ) VALUES (:tenant_scope, 
            :id, :pid, :uno, :floor, :area,
            :type, :pusd, :pafn, :paid,
            :bal, :status, :notes
        ) ON DUPLICATE KEY UPDATE 
            floor_number = IF(tenant_id = VALUES(tenant_id), VALUES(floor_number), floor_number),
            area_sqm = IF(tenant_id = VALUES(tenant_id), VALUES(area_sqm), area_sqm),
            unit_type = IF(tenant_id = VALUES(tenant_id), VALUES(unit_type), unit_type),
            price_usd = IF(tenant_id = VALUES(tenant_id), VALUES(price_usd), price_usd),
            price_afn = IF(tenant_id = VALUES(tenant_id), VALUES(price_afn), price_afn),
            total_paid_afn = IF(tenant_id = VALUES(tenant_id), VALUES(total_paid_afn), total_paid_afn),
            balance_receivable_afn = IF(tenant_id = VALUES(tenant_id), VALUES(balance_receivable_afn), balance_receivable_afn),
            status = IF(tenant_id = VALUES(tenant_id), VALUES(status), status),
            notes = IF(tenant_id = VALUES(tenant_id), VALUES(notes), notes)
    ");

    $stmt->execute(['tenant_scope' => $tenantId, 
        'id' => $id,
        'pid' => $projectId,
        'uno' => $unitNumber,
        'floor' => $floor,
        'area' => $area,
        'type' => $unitType,
        'pusd' => $priceUsd,
        'pafn' => $priceAfn,
        'paid' => $totalPaid,
        'bal' => $balance,
        'status' => $status,
        'notes' => $input['notes'] ?? null
    ]);

    logAudit($db, $user['id'], $user['name'], 'update', 'apartments', $id, "ثبت/ویرایش پلاک: $unitNumber");
    jsonResponse(true, ['id' => $id, 'balanceReceivable' => $balance], 'اطلاعات واحد با موفقیت ثبت شد.');
}

jsonResponse(false, null, 'Action is not available.', 404);
