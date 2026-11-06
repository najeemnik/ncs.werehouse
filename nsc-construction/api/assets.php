<?php
/**
 * NSC Assets, Machinery & Equipment Register API
 * Manages Heavy Machinery, Tools, Book Value & Depreciation Calculations
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

sendSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getConnection();
$user = authenticateUser(false);
$tenantId = getTenantId($user);
requireModule($user, 'assets', $method !== 'GET');

if ($method === 'GET') {
    $projectId = $_GET['projectId'] ?? null;
    $sql = "SELECT a.*, p.name as project_name 
            FROM assets_machinery a 
            LEFT JOIN projects p ON a.assigned_project_id = p.id AND p.tenant_id = :tid";
    $sql .= " WHERE a.tenant_id = :tid";
    $params = ['tid' => $tenantId];
    if ($projectId) {
        $sql .= " AND a.assigned_project_id = :pid";
        $params['pid'] = $projectId;
    }
    $sql .= " ORDER BY a.purchase_date DESC";
    $stmt = prepareStatement($db, $sql);
    $stmt->execute($params);
    jsonResponse(true, ['assets' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $input = readJson();
    validateBusinessInput($db, $user, $input);

    $id = $input['id'] ?? generateUuid();
        guardUpsert($db, 'assets_machinery', $id, $tenantId);
    $tag = trim($input['assetTag'] ?? 'AST-' . rand(100, 999));
    $name = trim($input['assetName'] ?? $input['name'] ?? '');
    $category = trim($input['category'] ?? 'تجهیزات عمومی');
    $projectId = $input['assignedProjectId'] ?? null;
    $purchaseDate = !empty($input['purchaseDate']) ? $input['purchaseDate'] : date('Y-m-d');
    $cost = (float)($input['purchaseCost'] ?? 0);
    $salvage = (float)($input['salvageValue'] ?? 0);
    $lifeYears = max(1, (int)($input['usefulLifeYears'] ?? 5));
    $status = $input['status'] ?? 'active';

    // محاسبه استهلاک خط مستقیم (Straight-line Depreciation)
    $yearsElapsed = max(0, (time() - strtotime($purchaseDate)) / (365 * 86400));
    $annualDepreciation = ($cost - $salvage) / $lifeYears;
    $totalDepreciation = min($cost - $salvage, $annualDepreciation * $yearsElapsed);
    $bookValue = max($salvage, $cost - $totalDepreciation);

    if (empty($name) || $cost <= 0) {
        jsonResponse(false, null, 'نام دارایی و بهای تمام‌شده الزامی است.', 400);
    }

    $stmt = prepareStatement($db, "
        INSERT INTO assets_machinery (tenant_id, 
            id, asset_tag_code, asset_name, category, assigned_project_id,
            purchase_date, purchase_cost, current_book_value, salvage_value,
            useful_life_years, serial_or_engine_no, location_site, status, notes
        ) VALUES (:tenant_scope, 
            :id, :tag, :name, :cat, :pid,
            :pdate, :cost, :bval, :salvage,
            :life, :serial, :loc, :status, :notes
        ) ON DUPLICATE KEY UPDATE 
            asset_name = IF(tenant_id = VALUES(tenant_id), VALUES(asset_name), asset_name),
            category = IF(tenant_id = VALUES(tenant_id), VALUES(category), category),
            assigned_project_id = IF(tenant_id = VALUES(tenant_id), VALUES(assigned_project_id), assigned_project_id),
            current_book_value = IF(tenant_id = VALUES(tenant_id), VALUES(current_book_value), current_book_value),
            status = IF(tenant_id = VALUES(tenant_id), VALUES(status), status),
            notes = IF(tenant_id = VALUES(tenant_id), VALUES(notes), notes)
    ");

    $stmt->execute(['tenant_scope' => $tenantId, 
        'id' => $id,
        'tag' => $tag,
        'name' => $name,
        'cat' => $category,
        'pid' => $projectId,
        'pdate' => $purchaseDate,
        'cost' => $cost,
        'bval' => $bookValue,
        'salvage' => $salvage,
        'life' => $lifeYears,
        'serial' => $input['serialNumber'] ?? null,
        'loc' => $input['location'] ?? null,
        'status' => $status,
        'notes' => $input['notes'] ?? null
    ]);

    logAudit($db, $user['id'], $user['name'], 'update', 'assets', $id, "ثبت/ویرایش دارایی: $name");
    jsonResponse(true, ['id' => $id, 'currentBookValue' => $bookValue], 'اطلاعات دارایی و ارزش دفتری با موفقیت ثبت شد.');
}

jsonResponse(false, null, 'Action is not available.', 404);
