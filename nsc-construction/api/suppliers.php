<?php
/**
 * NSC Suppliers & Vendor Ledger API
 * Solves Excel Flaw #4: Automatic Calculation of Purchases, Payments & Payable Balances
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

sendSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getConnection();
$user = authenticateUser(false);
$tenantId = getTenantId($user);
requireModule($user, 'suppliers', $method !== 'GET');

if ($method === 'GET') {
    $stmt = prepareStatement($db, "SELECT * FROM suppliers WHERE tenant_id = :tid ORDER BY supplier_name ASC");
    $stmt->execute(['tid' => $tenantId]);
    jsonResponse(true, ['suppliers' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $input = readJson();
    validateBusinessInput($db, $user, $input);

    $id = $input['id'] ?? generateUuid();
        guardUpsert($db, 'suppliers', $id, $tenantId);
    $name = trim($input['name'] ?? $input['supplierName'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $category = trim($input['materialCategory'] ?? $input['category'] ?? 'مصالح ساختمانی');
    $opening = (float)($input['openingBalance'] ?? 0);
    $purchases = (float)($input['totalPurchases'] ?? 0);
    $paid = (float)($input['totalPaid'] ?? 0);
    $payable = ($opening + $purchases) - $paid;

    if (empty($name)) {
        jsonResponse(false, null, 'نام عرضه‌کننده الزامی است.', 400);
    }

    $stmt = prepareStatement($db, "
        INSERT INTO suppliers (tenant_id, 
            id, supplier_name, contact_person, phone, material_category,
            opening_balance, total_purchases, total_paid, current_payable_balance
        ) VALUES (:tenant_scope, 
            :id, :name, :contact, :phone, :cat,
            :open, :pur, :paid, :pay
        ) ON DUPLICATE KEY UPDATE 
            supplier_name = IF(tenant_id = VALUES(tenant_id), VALUES(supplier_name), supplier_name),
            phone = IF(tenant_id = VALUES(tenant_id), VALUES(phone), phone),
            material_category = IF(tenant_id = VALUES(tenant_id), VALUES(material_category), material_category),
            total_purchases = IF(tenant_id = VALUES(tenant_id), VALUES(total_purchases), total_purchases),
            total_paid = IF(tenant_id = VALUES(tenant_id), VALUES(total_paid), total_paid),
            current_payable_balance = IF(tenant_id = VALUES(tenant_id), VALUES(current_payable_balance), current_payable_balance)
    ");

    $stmt->execute(['tenant_scope' => $tenantId, 
        'id' => $id,
        'name' => $name,
        'contact' => $input['contactPerson'] ?? null,
        'phone' => $phone,
        'cat' => $category,
        'open' => $opening,
        'pur' => $purchases,
        'paid' => $paid,
        'pay' => $payable
    ]);

    logAudit($db, $user['id'], $user['name'], 'update', 'suppliers', $id, "ثبت/ویرایش عرضه‌کننده: $name");
    jsonResponse(true, ['id' => $id, 'currentPayableBalance' => $payable], 'اطلاعات تأمین‌کننده و مانده حساب با موفقیت ثبت شد.');
}

jsonResponse(false, null, 'Action is not available.', 404);
