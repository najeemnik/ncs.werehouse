<?php
/**
 * NSC Material Procurement & Supply Chain API
 * Solves Excel Flaw #2: Material Purchases Directly Reflected in Actual Costs & P&L
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

sendSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getConnection();
$user = authenticateUser(false);
$tenantId = getTenantId($user);
requireModule($user, 'steel', $method !== 'GET');

if ($method === 'GET') {
    $projectId = $_GET['projectId'] ?? null;
    $sql = "SELECT m.*, p.name as project_name, s.supplier_name as supplier_official_name 
            FROM material_procurements m 
            LEFT JOIN projects p ON m.project_id = p.id  AND p.tenant_id = :tid
            LEFT JOIN suppliers s ON m.supplier_id = s.id AND s.tenant_id = :tid";
    $sql .= " WHERE m.tenant_id = :tid";
    $params = ['tid' => $tenantId];
    if ($projectId) {
        $sql .= " AND m.project_id = :pid";
        $params['pid'] = $projectId;
    }
    $sql .= " ORDER BY m.purchase_date DESC LIMIT 200";
    $stmt = prepareStatement($db, $sql);
    $stmt->execute($params);
    jsonResponse(true, ['materials' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $input = readJson();
    validateBusinessInput($db, $user, $input);

    $id = generateUuid();
    $procCode = 'MAT-' . date('ymd') . '-' . rand(100, 999);
    $projectId = $input['projectId'] ?? '';
    $materialName = trim($input['materialName'] ?? $input['itemDescription'] ?? '');
    $category = $input['materialCategory'] ?? 'مواد عمومی';
    $qty = (float)($input['quantity'] ?? 0);
    $unit = $input['unitOfMeasure'] ?? 'کیسه';
    $unitPrice = (float)($input['unitPrice'] ?? 0);
    $totalPrice = $qty * $unitPrice;
    $supplierName = trim($input['supplierName'] ?? 'تأمین‌کننده محلی');
    $supplierId = $input['supplierId'] ?? null;
    $date = !empty($input['purchaseDate']) ? $input['purchaseDate'] : date('Y-m-d');
    $isPaid = !empty($input['isPaid']) ? 1 : 0;

    if (empty($projectId) || empty($materialName) || $qty <= 0 || $unitPrice <= 0) {
        jsonResponse(false, null, 'پروژه، نام جنس، تعداد و قیمت واحد الزامی هستند.', 400);
    }

    $db->beginTransaction();
    try {
        $paymentVoucherId = null;

        // حل نقص شماره ۲ اکسل: ایجاد خودکار سند پرداخت جهت درج در مصارف واقعی پروژه
        if ($isPaid) {
            $paymentVoucherId = generateUuid();
            $pvNo = 'PV-MAT-' . date('ymd') . '-' . rand(100, 999);
            $pvStmt = prepareStatement($db, "
                INSERT INTO payment_vouchers (tenant_id, 
                    id, voucher_no, project_id, payee_type, payee_id, payee_name,
                    expense_category, amount, currency, exchange_rate, amount_in_base_afn,
                    payment_method, payment_date, payment_status, approved_by,
                    recorded_by_user_id, description
                ) VALUES (:tenant_scope, 
                    :id, :vno, :pid, 'supplier', :sid, :sname,
                    'materials', :amt, 'AFN', 1.0, :amt,
                    'cash', :dt, 'paid', 'مدیر تدارکات',
                    :uid, :desc
                )
            ");
            $pvStmt->execute(['tenant_scope' => $tenantId, 
                'id' => $paymentVoucherId,
                'vno' => $pvNo,
                'pid' => $projectId,
                'sid' => $supplierId,
                'sname' => $supplierName,
                'amt' => $totalPrice,
                'dt' => $date,
                'uid' => $user['id'],
                'desc' => "خرید $qty $unit $materialName با قیمت واحد $unitPrice AFN"
            ]);
        }

        // درج در جدول تدارکات مواد
        $stmt = prepareStatement($db, "
            INSERT INTO material_procurements (tenant_id, 
                id, procurement_code, project_id, supplier_id, supplier_name,
                material_category, item_description, quantity, unit_of_measure,
                unit_price, total_cost, currency, is_paid, payment_voucher_id,
                purchase_date, received_by, notes
            ) VALUES (:tenant_scope, 
                :id, :code, :pid, :sid, :sname,
                :cat, :item, :qty, :unit,
                :uprice, :total, 'AFN', :paid, :pvid,
                :pdate, :rec, :notes
            )
        ");
        $stmt->execute(['tenant_scope' => $tenantId, 
            'id' => $id,
            'code' => $procCode,
            'pid' => $projectId,
            'sid' => $supplierId,
            'sname' => $supplierName,
            'cat' => $category,
            'item' => $materialName,
            'qty' => $qty,
            'unit' => $unit,
            'uprice' => $unitPrice,
            'total' => $totalPrice,
            'paid' => $isPaid,
            'pvid' => $paymentVoucherId,
            'pdate' => $date,
            'rec' => $input['receivedBy'] ?? $user['name'],
            'notes' => $input['notes'] ?? null
        ]);

        // به‌روزرسانی خریدها و مانده تأمین‌کننده در صورت ثبت شناسه
        if (!empty($supplierId)) {
            prepareStatement($db, "
                UPDATE suppliers 
                SET total_purchases = total_purchases + :tot,
                    total_paid = total_paid + :paid_amt,
                    current_payable_balance = current_payable_balance + :remain
                WHERE id = :sid AND tenant_id = :tid
            ")->execute(['tenant_scope' => $tenantId, 
                'tot' => $totalPrice,
                'paid_amt' => $isPaid ? $totalPrice : 0,
                'remain' => $isPaid ? 0 : $totalPrice,
                'sid' => $supplierId,
                'tid' => $tenantId
            ]);
        }

        $db->commit();
        logAudit($db, $user['id'], $user['name'], 'create', 'materials', $id, "ثبت خرید مواد: $materialName ($totalPrice AFN)");
        jsonResponse(true, ['id' => $id, 'procCode' => $procCode, 'totalCost' => $totalPrice], 'خرید مصالح با موفقیت ثبت و مستقیماً به هزینه پروژه متصل شد.');
    } catch (Exception $e) {
        $db->rollBack();
        error_log($e->getMessage());
            jsonResponse(false, null, 'The operation failed.', 500);
    }
}

jsonResponse(false, null, 'Action is not available.', 404);
