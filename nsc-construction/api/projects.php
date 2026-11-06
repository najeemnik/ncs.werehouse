<?php
/**
 * NSC Project Master & Budget API
 * Manages Projects, Budgets, and Cost Allocation
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

sendSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getConnection();
$user = authenticateUser(false);
$tenantId = getTenantId($user);
requireModule($user, 'projects', $method !== 'GET');

if ($method === 'GET') {
    $projectId = $_GET['id'] ?? null;

    if ($projectId) {
        // دریافت مشخصات یک پروژه همراه با بودجه‌ها و مصارف واقعی
        $stmt = prepareStatement($db, "SELECT * FROM projects WHERE id = :id AND tenant_id = :tid LIMIT 1");
        $stmt->execute(['tenant_scope' => $tenantId, 'id' => $projectId, 'tid' => $tenantId]);
        $project = $stmt->fetch();

        if (!$project) {
            jsonResponse(false, null, 'پروژه مورد نظر یافت نشد.', 404);
        }

        // بودجه‌ها
        $bStmt = prepareStatement($db, "SELECT * FROM project_budgets WHERE project_id = :pid AND tenant_id = :tid");
        $bStmt->execute(['tenant_scope' => $tenantId, 'pid' => $projectId, 'tid' => $tenantId]);
        $budgets = $bStmt->fetchAll();

        // محاسبه مجموع مصارف واقعی پرداخت‌شده برای این پروژه
        $expStmt = prepareStatement($db, "
            SELECT expense_category, SUM(amount_in_base_afn) as total_spent 
            FROM payment_vouchers 
            WHERE project_id = :pid AND tenant_id = :tid AND payment_status != 'cancelled'
            GROUP BY expense_category
        ");
        $expStmt->execute(['tenant_scope' => $tenantId, 'pid' => $projectId, 'tid' => $tenantId]);
        $actualCosts = $expStmt->fetchAll();

        // مجموع عواید دریافتی
        $revStmt = prepareStatement($db, "
            SELECT SUM(amount_in_base_afn) as total_revenue 
            FROM revenue_entries 
            WHERE project_id = :pid AND tenant_id = :tid
        ");
        $revStmt->execute(['tenant_scope' => $tenantId, 'pid' => $projectId, 'tid' => $tenantId]);
        $revRow = $revStmt->fetch();
        $totalRevenue = (float)($revRow['total_revenue'] ?? 0);

        jsonResponse(true, [
            'project' => $project,
            'budgets' => $budgets,
            'actualCosts' => $actualCosts,
            'totalRevenue' => $totalRevenue
        ]);
    } else {
        // لیست تمام پروژه‌ها
        $stmt = prepareStatement($db, "
            SELECT p.*, 
                   COALESCE((SELECT SUM(amount_in_base_afn) FROM payment_vouchers WHERE project_id = p.id AND tenant_id = :tid AND payment_status != 'cancelled'), 0) as total_spent,
                   COALESCE((SELECT SUM(amount_in_base_afn) FROM revenue_entries WHERE project_id = p.id AND tenant_id = :tid), 0) as total_income
            FROM projects p 
            WHERE p.tenant_id = :tid
            ORDER BY p.created_at DESC
        ");
        $stmt->execute(['tenant_scope' => $tenantId, 'tid' => $tenantId]);
        $projects = $stmt->fetchAll();
        jsonResponse(true, ['projects' => $projects]);
    }
}

if ($method === 'POST') {
    $input = readJson();
    validateBusinessInput($db, $user, $input);
    $action = $_GET['action'] ?? 'save_project';

    if ($action === 'save_project') {
        $id = $input['id'] ?? generateUuid();
        guardUpsert($db, 'projects', $id, $tenantId);
        $projectCode = trim($input['projectCode'] ?? $input['code'] ?? 'P-' . rand(100, 999));
        $name = trim($input['name'] ?? '');

        if (empty($name)) {
            jsonResponse(false, null, 'نام پروژه الزامی است.', 400);
        }

        $stmt = prepareStatement($db, "
            INSERT INTO projects (tenant_id, 
                id, project_code, name, client_name, contract_value, 
                approved_budget, currency, location, start_date, expected_finish, 
                status, project_manager, total_area_sqm, floors_count, notes, created_by
            ) VALUES (:tenant_scope, 
                :id, :code, :name, :client, :contract, 
                :budget, :curr, :loc, :sdate, :edate, 
                :status, :pm, :area, :floors, :notes, :created_by
            ) ON DUPLICATE KEY UPDATE 
                name = IF(tenant_id = VALUES(tenant_id), VALUES(name), name),
                client_name = IF(tenant_id = VALUES(tenant_id), VALUES(client_name), client_name),
                contract_value = IF(tenant_id = VALUES(tenant_id), VALUES(contract_value), contract_value),
                approved_budget = IF(tenant_id = VALUES(tenant_id), VALUES(approved_budget), approved_budget),
                currency = IF(tenant_id = VALUES(tenant_id), VALUES(currency), currency),
                location = IF(tenant_id = VALUES(tenant_id), VALUES(location), location),
                start_date = IF(tenant_id = VALUES(tenant_id), VALUES(start_date), start_date),
                expected_finish = IF(tenant_id = VALUES(tenant_id), VALUES(expected_finish), expected_finish),
                status = IF(tenant_id = VALUES(tenant_id), VALUES(status), status),
                project_manager = IF(tenant_id = VALUES(tenant_id), VALUES(project_manager), project_manager),
                notes = IF(tenant_id = VALUES(tenant_id), VALUES(notes), notes)
        ");

        $stmt->execute(['tenant_scope' => $tenantId, 
            'id' => $id,
            'code' => $projectCode,
            'name' => $name,
            'client' => $input['clientName'] ?? null,
            'contract' => (float)($input['contractValue'] ?? 0),
            'budget' => (float)($input['approvedBudget'] ?? $input['budget'] ?? 0),
            'curr' => $input['currency'] ?? 'AFN',
            'loc' => $input['location'] ?? null,
            'sdate' => !empty($input['startDate']) ? $input['startDate'] : null,
            'edate' => !empty($input['expectedFinish']) ? $input['expectedFinish'] : null,
            'status' => $input['status'] ?? 'active',
            'pm' => $input['projectManager'] ?? null,
            'area' => (float)($input['totalAreaSqm'] ?? 0),
            'floors' => (int)($input['floorsCount'] ?? 0),
            'notes' => $input['notes'] ?? null,
            'created_by' => $user['id']
        ]);

        logAudit($db, $user['id'], $user['name'], 'update', 'projects', $id, "ثبت/ویرایش پروژه: $name");
        jsonResponse(true, ['id' => $id, 'projectCode' => $projectCode], 'اطلاعات پروژه با موفقیت ثبت شد.');
    }

    if ($action === 'save_budget') {
        $id = $input['id'] ?? generateUuid();
        guardUpsert($db, 'project_budgets', $id, $tenantId);
        $projectId = $input['projectId'] ?? '';
        $categoryCode = $input['categoryCode'] ?? 'materials';
        $categoryName = $input['categoryName'] ?? 'مصالح ساختمانی';
        $amount = (float)($input['budgetedAmount'] ?? 0);

        if (empty($projectId)) {
            jsonResponse(false, null, 'شناسه پروژه مشخص نیست.', 400);
        }

        $stmt = prepareStatement($db, "
            INSERT INTO project_budgets (tenant_id, id, project_id, category_code, category_name, budgeted_amount, currency) VALUES (:tenant_scope, :id, :pid, :cat, :cname, :amt, 'AFN')
            ON DUPLICATE KEY UPDATE 
                budgeted_amount = IF(tenant_id = VALUES(tenant_id), VALUES(budgeted_amount), budgeted_amount),
                category_name = IF(tenant_id = VALUES(tenant_id), VALUES(category_name), category_name)
        ");
        $stmt->execute(['tenant_scope' => $tenantId, 
            'id' => $id,
            'pid' => $projectId,
            'cat' => $categoryCode,
            'cname' => $categoryName,
            'amt' => $amount
        ]);

        jsonResponse(true, ['id' => $id], 'سرفصل بودجه با موفقیت بروزرسانی شد.');
    }
}

jsonResponse(false, null, 'Action is not available.', 404);
