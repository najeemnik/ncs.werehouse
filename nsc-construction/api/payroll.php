<?php
/**
 * NSC Labor Wages & Payroll Management API
 * Solves Excel Flaw #3: Labor Directly Connected to Project Actual Costs & P&L
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

sendSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getConnection();
$user = authenticateUser(false);
$tenantId = getTenantId($user);
requireModule($user, 'labor', $method !== 'GET');

if ($method === 'GET') {
    $projectId = $_GET['projectId'] ?? null;
    $sql = "SELECT lp.*, p.name as project_name 
            FROM labor_payroll lp 
            LEFT JOIN projects p ON lp.project_id = p.id AND p.tenant_id = :tid";
    $sql .= " WHERE lp.tenant_id = :tid";
    $params = ['tid' => $tenantId];
    if ($projectId) {
        $sql .= " AND lp.project_id = :pid";
        $params['pid'] = $projectId;
    }
    $sql .= " ORDER BY lp.payroll_date DESC LIMIT 200";
    $stmt = prepareStatement($db, $sql);
    $stmt->execute($params);
    jsonResponse(true, ['payroll' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $input = readJson();
    validateBusinessInput($db, $user, $input);

    $id = generateUuid();
    $payrollNo = 'PAY-' . date('ymd') . '-' . rand(100, 999);
    $projectId = $input['projectId'] ?? '';
    $workerName = trim($input['workerOrTeamLeader'] ?? $input['workerName'] ?? '');
    $workType = trim($input['workTypeDescription'] ?? $input['workType'] ?? 'کارگری عمومی');
    $daysOrUnits = (float)($input['daysOrWorkUnits'] ?? $input['days'] ?? 0);
    $rate = (float)($input['ratePerUnit'] ?? $input['rate'] ?? 0);
    $advance = (float)($input['advanceDeduction'] ?? $input['advance'] ?? 0);

    $gross = $daysOrUnits * $rate;
    $netPaid = max(0, $gross - $advance);
    $date = !empty($input['payrollDate']) ? $input['payrollDate'] : date('Y-m-d');
    $approvedBy = $input['approvedBy'] ?? $user['name'];

    if (empty($projectId) || empty($workerName) || $daysOrUnits <= 0 || $rate <= 0) {
        jsonResponse(false, null, 'پروژه، نام استادکار/کارگر، تعداد روز و نرخ روزانه الزامی هستند.', 400);
    }

    $db->beginTransaction();
    try {
        // حل نقص شماره ۳ اکسل: ایجاد خودکار سند پرداخت جهت درج مستقیم در هزینه واقعی پروژه
        $paymentVoucherId = generateUuid();
        $pvNo = 'PV-LAB-' . date('ymd') . '-' . rand(100, 999);

        $pvStmt = prepareStatement($db, "
            INSERT INTO payment_vouchers (tenant_id, 
                id, voucher_no, project_id, payee_type, payee_name,
                expense_category, amount, currency, exchange_rate, amount_in_base_afn,
                payment_method, payment_date, payment_status, approved_by,
                recorded_by_user_id, description
            ) VALUES (:tenant_scope, 
                :id, :vno, :pid, 'labor_wage', :wname,
                'labor', :amt, 'AFN', 1.0, :amt,
                'cash', :dt, 'paid', :appr,
                :uid, :desc
            )
        ");
        $pvStmt->execute(['tenant_scope' => $tenantId, 
            'id' => $paymentVoucherId,
            'vno' => $pvNo,
            'pid' => $projectId,
            'wname' => $workerName,
            'amt' => $netPaid,
            'dt' => $date,
            'appr' => $approvedBy,
            'uid' => $user['id'],
            'desc' => "معاش کارکرد $daysOrUnits روز $workerName ($workType) با کسر مساعده $advance AFN"
        ]);

        // ثبت در جدول دستمزد کارگران
        $stmt = prepareStatement($db, "
            INSERT INTO labor_payroll (tenant_id, 
                id, payroll_number, project_id, worker_or_team_leader,
                work_type_description, days_or_work_units, rate_per_unit,
                gross_amount, advance_deduction, net_amount_paid, currency,
                payment_voucher_id, payroll_date, approved_by, is_fully_settled, notes
            ) VALUES (:tenant_scope, 
                :id, :pno, :pid, :wname,
                :wtype, :units, :rate,
                :gross, :adv, :net, 'AFN',
                :pvid, :dt, :appr, 1, :notes
            )
        ");
        $stmt->execute(['tenant_scope' => $tenantId, 
            'id' => $id,
            'pno' => $payrollNo,
            'pid' => $projectId,
            'wname' => $workerName,
            'wtype' => $workType,
            'units' => $daysOrUnits,
            'rate' => $rate,
            'gross' => $gross,
            'adv' => $advance,
            'net' => $netPaid,
            'pvid' => $paymentVoucherId,
            'dt' => $date,
            'appr' => $approvedBy,
            'notes' => $input['notes'] ?? null
        ]);

        $db->commit();
        logAudit($db, $user['id'], $user['name'], 'create', 'labor', $id, "ثبت کارکرد $workerName خالص $netPaid AFN");
        jsonResponse(true, [
            'id' => $id, 
            'payrollNo' => $payrollNo, 
            'grossAmount' => $gross, 
            'netAmount' => $netPaid
        ], 'کارکرد کارگران ثبت و مبلغ خالص مستقیماً در مصارف پروژه درج شد.');
    } catch (Exception $e) {
        $db->rollBack();
        error_log($e->getMessage());
            jsonResponse(false, null, 'The operation failed.', 500);
    }
}

jsonResponse(false, null, 'Action is not available.', 404);
