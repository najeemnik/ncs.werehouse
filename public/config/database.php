<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Database Configuration & Connection Handler
 * 
 * Uses the explicitly configured MySQL or SQLite database; never falls back.
 */

ini_set('display_errors','0');ini_set('log_errors','1');
if(PHP_SAPI!=='cli')set_exception_handler(function(Throwable $e){
    error_log('Warehouse request failed: '.get_class($e).' code '.(string)$e->getCode());
    http_response_code(500);header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: no-store');
    echo function_exists('__t')?__t('The request could not be completed. Contact support.','درخواست تکمیل نشد؛ با مسؤول سیستم تماس بگیرید.','غوښتنه بشپړه نه شوه؛ له مسؤول سره اړیکه ونیسئ.'):'The request could not be completed. Contact support.';
});

require_once __DIR__.'/../includes/runtime_settings.php';
hosting_bootstrap_guard();
// Private host configuration lives outside the web root; env takes precedence.
$privatePath=getenv('NIKXOR_CONFIG_FILE') ?: runtime_directory().'/database.php';
$private=[];
if(is_file($privatePath)){
    $resolved=realpath($privatePath);$webRoot=realpath(__DIR__.'/..');
    if(!$resolved||(runtime_path_is_public($resolved)&&!hosting_internal_path($resolved)))throw new RuntimeException('Database configuration must be outside the web root.');
    $private=require $resolved;if(!is_array($private))throw new RuntimeException('Invalid private database configuration.');
}
if(PHP_SAPI!=='cli'&&!is_file($privatePath)&&!getenv('DB_NAME')&&!getenv('NIKXOR_DB_DRIVER')&&!is_file(runtime_directory().'/installed.lock')){header('Location: '.hosting_url('install.php'),true,303);exit;}
foreach (['HOST'=>'localhost','PORT'=>'3306','NAME'=>'','USER'=>'','PASS'=>'','CHARSET'=>'utf8mb4'] as $key=>$default) {
    defined('DB_'.$key) or define('DB_'.$key, (string)(getenv('DB_'.$key) !== false ? getenv('DB_'.$key) : ($private[$key]??$default)));
}
defined('NIKXOR_DATABASE_DRIVER') or define('NIKXOR_DATABASE_DRIVER',getenv('NIKXOR_DB_DRIVER')?:($private['DRIVER']??'mysql'));
defined('NIKXOR_DATABASE_PATH') or define('NIKXOR_DATABASE_PATH',getenv('NIKXOR_SQLITE_PATH')?:($private['SQLITE_PATH']??''));
unset($private);
/**
 * Automatically provisions database tables and seed data if missing in MySQL or SQLite
 */
function auto_provision_database_tables($pdo) {
    if (!$pdo) return;
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $hasStock = false;
        if ($driver === 'sqlite') {
            $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='stock'")->fetch();
            $hasStock = !empty($check);
        } else {
            $check = $pdo->query("SHOW TABLES LIKE 'stock'")->fetch();
            $hasStock = !empty($check);
        }

        if ($hasStock) {
            return;
        }

        // Attempt 1: Execute nikxor_database.sql file if running on MySQL
        $sqlPath = __DIR__ . '/../nikxor_database.sql';
        if ($driver === 'mysql' && file_exists($sqlPath)) {
            $sqlContent = file_get_contents($sqlPath);
            $lines = explode("\n", $sqlContent);
            $stmt = '';
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) {
                    continue;
                }
                $stmt .= $line . "\n";
                if (substr(rtrim($trimmed), -1) === ';') {
                    try {
                        $pdo->exec($stmt);
                    } catch (Exception $ex) {}
                    $stmt = '';
                }
            }
            if (!empty(trim($stmt))) {
                try {
                    $pdo->exec($stmt);
                } catch (Exception $ex) {}
            }
        }

        // Re-check if stock table is now present
        if ($driver === 'sqlite') {
            $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='stock'")->fetch();
            $hasStock = !empty($check);
        } else {
            $check = $pdo->query("SHOW TABLES LIKE 'stock'")->fetch();
            $hasStock = !empty($check);
        }

        // Attempt 2: Built-in resilient schema creator for both MySQL and SQLite
        if (!$hasStock) {
            provision_embedded_schema($pdo, $driver);
        }

        // Always run schema migration checks to ensure latest columns are present
        ensure_schema_migrations($pdo);
    } catch (Exception $e) {
        // Table provisioning fallback
    }
}

/**
 * Built-in resilient table generation and seeding (Zero-Configuration fallback)
 */
function provision_embedded_schema($pdo, $driver) {
    $isSqlite = ($driver === 'sqlite');
    $pk = $isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY";
    $opts = $isSqlite ? "" : " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $ins = $isSqlite ? "INSERT OR IGNORE INTO" : "INSERT IGNORE INTO";

    // 1. Users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id {$pk},
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(100) NOT NULL,
        role VARCHAR(20) DEFAULT 'admin',
        status VARCHAR(20) DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 2. Categories
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id {$pk},
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        is_sample TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 3. Partners
    $pdo->exec("CREATE TABLE IF NOT EXISTS partners (
        id {$pk},
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(30),
        share_percentage DECIMAL(5,2) DEFAULT 0.00,
        investment_amount DECIMAL(15,2) DEFAULT 0.00,
        join_date DATE,
        status VARCHAR(20) DEFAULT 'active',
        partner_type VARCHAR(30) DEFAULT 'general',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 4. Stock
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock (
        id {$pk},
        name VARCHAR(150) NOT NULL,
        category_id INT NULL,
        specifications TEXT,
        quantity INT NOT NULL DEFAULT 0,
        purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        expenses DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        country_of_origin VARCHAR(100) DEFAULT 'چین',
        supplier_name VARCHAR(100) DEFAULT 'شرکت وارداتی',
        warehouse_location VARCHAR(100) DEFAULT 'الماری ۱',
        capital_amount DECIMAL(15,2) DEFAULT 0.00,
        partner_id INT NULL,
        purchase_date DATE,
        barcode VARCHAR(50) DEFAULT '',
        sale_price DECIMAL(15,2) DEFAULT 0.00,
        mudaraba_id INT NULL,
        min_quantity INT DEFAULT 3,
        is_sample TINYINT(1) DEFAULT 0,
        damaged_qty INT NOT NULL DEFAULT 0,
        is_damaged TINYINT(1) DEFAULT 0,
        damage_notes VARCHAR(255) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 5. Customers
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id {$pk},
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(30),
        customer_type VARCHAR(50) DEFAULT 'پرچون',
        company VARCHAR(100) DEFAULT '',
        address TEXT,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 6. Sales
    $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
        id {$pk},
        invoice_no VARCHAR(50) NOT NULL UNIQUE,
        customer_name VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(30),
        total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        remaining_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        profit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        payment_type VARCHAR(30) NOT NULL DEFAULT 'نقد',
        notes TEXT,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 7. Sale Items
    $pdo->exec("CREATE TABLE IF NOT EXISTS sale_items (
        id {$pk},
        sale_id INT NOT NULL,
        stock_id INT NULL,
        product_name VARCHAR(150) NOT NULL,
        specifications TEXT,
        quantity INT NOT NULL DEFAULT 1,
        unit_purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        unit_expense DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        unit_sale_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        total_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        profit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 8. Debts
    $pdo->exec("CREATE TABLE IF NOT EXISTS debts (
        id {$pk},
        sale_id INT NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(30),
        total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        remaining_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        due_date DATE,
        status VARCHAR(30) DEFAULT 'باز',
        debt_type VARCHAR(30) DEFAULT 'receivable',
        person_type VARCHAR(30) DEFAULT 'customer',
        is_hasana TINYINT(1) DEFAULT 0,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 9. Installments
    $pdo->exec("CREATE TABLE IF NOT EXISTS installments (
        id {$pk},
        debt_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        payment_date DATE,
        payment_reason VARCHAR(150) DEFAULT '',
        capital_amount DECIMAL(15,2) DEFAULT 0.00,
        profit_amount DECIMAL(15,2) DEFAULT 0.00,
        notes TEXT,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 10. Partnership Transactions
    $pdo->exec("CREATE TABLE IF NOT EXISTS partnership_transactions (
        id {$pk},
        partner_id INT NOT NULL,
        transaction_type VARCHAR(30) NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        transaction_date DATE,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 11. Capital
    $pdo->exec("CREATE TABLE IF NOT EXISTS capital (
        id {$pk},
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        transaction_type VARCHAR(50) NOT NULL DEFAULT 'سرمایه',
        source_description VARCHAR(255),
        transaction_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 12. Expenses
    $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
        id {$pk},
        expense_type VARCHAR(20) NOT NULL DEFAULT 'مصارف',
        category VARCHAR(100) NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        expense_date DATE,
        description TEXT,
        stock_id INT DEFAULT NULL,
        purchase_price DECIMAL(15,2) DEFAULT NULL,
        sale_price DECIMAL(15,2) DEFAULT NULL,
        loss_qty INT DEFAULT 1,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 13. Settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 14. Activity Logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id {$pk},
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 15. Custom Features
    $pdo->exec("CREATE TABLE IF NOT EXISTS custom_features (
        id {$pk},
        feature_key VARCHAR(100) NOT NULL UNIQUE,
        title_fa VARCHAR(150),
        title_en VARCHAR(150),
        description_fa TEXT,
        description_en TEXT,
        formula_expression TEXT,
        is_locked TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // 16. Mudaraba Contracts
    $pdo->exec("CREATE TABLE IF NOT EXISTS mudaraba_contracts (
        id {$pk},
        partner_name VARCHAR(150) NOT NULL,
        phone VARCHAR(50) DEFAULT '',
        project_title VARCHAR(200) NOT NULL,
        country_source VARCHAR(100) DEFAULT '',
        capital_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        partner_profit_rate DECIMAL(5,2) NOT NULL DEFAULT 50.00,
        shop_profit_rate DECIMAL(5,2) NOT NULL DEFAULT 50.00,
        total_sales_amount DECIMAL(15,2) DEFAULT 0.00,
        total_profit_amount DECIMAL(15,2) DEFAULT 0.00,
        paid_profit_to_partner DECIMAL(15,2) DEFAULT 0.00,
        returned_capital DECIMAL(15,2) DEFAULT 0.00,
        start_date DATE,
        settlement_date DATE,
        status VARCHAR(30) DEFAULT 'active',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ){$opts}");

    // No default accounts, PINs, sample partners or business data.
    $sStmt=$pdo->prepare("{$ins} settings (setting_key,setting_value) VALUES (?,?)");
    foreach(['shop_name'=>'Nik Smart Count','screen_lock_enabled'=>'0'] as $k=>$v)$sStmt->execute([$k,$v]);

}

/**
 * Harmonizes MySQL database and table collations to prevent Error 1267 (Illegal mix of collations)
 * Safely disables foreign key checks during conversion and converts all tables to utf8mb4_unicode_ci
 */
function harmonize_mysql_collations($pdo) {
    if (!$pdo) return;
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return;
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
        $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
        
        $dbName = DB_NAME;
        try {
            $pdo->exec("ALTER DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (Exception $e) {}

        $tables = ['mudaraba_contracts', 'capital', 'stock', 'expenses', 'sales', 'sale_items', 'debts', 'installments', 'categories', 'partners', 'users', 'settings', 'activity_logs', 'custom_features', 'customers'];
        try {
            $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($allTables)) {
                $tables = array_unique(array_merge($tables, $allTables));
            }
        } catch (Exception $e) {}

        foreach ($tables as $t) {
            try {
                $pdo->exec("ALTER TABLE `{$t}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (Exception $e) {}
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (Exception $ge) {}
}

/**
 * Returns a singleton PDO connection
 *
 * @return PDO
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $pdoOptions = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Explicit backend selection. A failed production connection never changes databases.
    $driver=NIKXOR_DATABASE_DRIVER;
    try {
        if($driver==='sqlite') {
            $path=NIKXOR_DATABASE_PATH;
            if(!$path || $path[0]!=='/' || str_starts_with($path,realpath(__DIR__.'/..').'/'))throw new RuntimeException('Configure a private absolute SQLite path outside the web root.');
            $pdo=new PDO('sqlite:'.$path,null,null,$pdoOptions);
            $pdo->exec('PRAGMA foreign_keys=ON');$pdo->exec('PRAGMA busy_timeout=5000');
        } elseif($driver==='mysql') {
            if(DB_NAME==='' || !preg_match('/^[a-zA-Z0-9_]+$/D',DB_NAME))throw new RuntimeException('Configure DB_NAME.');
            $pdo=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,$pdoOptions);
        } else throw new RuntimeException('Unsupported database driver.');
        auto_provision_database_tables($pdo);
        return $pdo;
    } catch(Throwable $e) {
        error_log('Warehouse database connection failed: '.get_class($e));
        if(PHP_SAPI==='cli')throw new RuntimeException('Database unavailable; check private host configuration.');
        http_response_code(503);header('Content-Type: text/plain; charset=utf-8');
        exit('Database unavailable. Please contact the administrator.');
    }

}

// Global PDO connection reference
$pdo = getDBConnection();

/**
 * Ensures required columns and tables exist across SQLite and MySQL environments
 */
function ensure_schema_migrations($db) {
    if (!$db) return;
    try {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isSqlite = ($driver === 'sqlite');
        $pk = $isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY";
        $opts = $isSqlite ? "" : " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        // 1. Ensure all auxiliary tables exist
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS mudaraba_contracts (
                id {$pk},
                partner_name VARCHAR(150) NOT NULL,
                phone VARCHAR(50) DEFAULT '',
                project_title VARCHAR(200) NOT NULL,
                country_source VARCHAR(100) DEFAULT '',
                capital_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                partner_profit_rate DECIMAL(5,2) NOT NULL DEFAULT 50.00,
                shop_profit_rate DECIMAL(5,2) NOT NULL DEFAULT 50.00,
                total_sales_amount DECIMAL(15,2) DEFAULT 0.00,
                total_profit_amount DECIMAL(15,2) DEFAULT 0.00,
                paid_profit_to_partner DECIMAL(15,2) DEFAULT 0.00,
                returned_capital DECIMAL(15,2) DEFAULT 0.00,
                start_date DATE,
                settlement_date DATE,
                status VARCHAR(30) DEFAULT 'active',
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ){$opts}");
        } catch (Exception $e) {}

        try {
            $db->exec("CREATE TABLE IF NOT EXISTS custom_features (
                id {$pk},
                feature_key VARCHAR(100) UNIQUE,
                title_fa VARCHAR(150),
                title_en VARCHAR(150),
                description_fa TEXT,
                description_en TEXT,
                formula_expression TEXT,
                is_locked INT DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ){$opts}");
        } catch (Exception $e) {}

        // 2. Comprehensive column checks for ALL tables
        $tablesRequiredColumns = [
            'expenses' => [
                'expense_type'   => "VARCHAR(20) NOT NULL DEFAULT 'مصارف'",
                'stock_id'       => "INT NULL DEFAULT NULL",
                'purchase_price' => "DECIMAL(15,2) NULL DEFAULT 0.00",
                'sale_price'     => "DECIMAL(15,2) NULL DEFAULT 0.00",
                'loss_qty'       => "INT DEFAULT 1",
                'category'       => "VARCHAR(100) NOT NULL DEFAULT 'متفرقه'",
                'amount'         => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
                'expense_date'   => "DATE NULL",
                'description'    => "TEXT NULL",
                'created_by'     => "INT NULL",
                'created_at'     => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'stock' => [
                'barcode'            => "VARCHAR(50) DEFAULT ''",
                'sale_price'         => "DECIMAL(15,2) DEFAULT 0.00",
                'mudaraba_id'        => "INT NULL DEFAULT NULL",
                'min_quantity'       => "INT DEFAULT 3",
                'is_sample'          => "TINYINT(1) DEFAULT 0",
                'damaged_qty'        => "INT NOT NULL DEFAULT 0",
                'is_damaged'         => "TINYINT(1) DEFAULT 0",
                'damage_notes'       => "VARCHAR(255) DEFAULT ''",
                'partner_id'         => "INT NULL DEFAULT NULL",
                'purchase_date'      => "DATE NULL",
                'capital_amount'     => "DECIMAL(15,2) DEFAULT 0.00",
                'warehouse_location' => "VARCHAR(100) DEFAULT 'الماری ۱'",
                'country_of_origin'  => "VARCHAR(100) DEFAULT 'چین'",
                'supplier_name'      => "VARCHAR(100) DEFAULT 'شرکت وارداتی'",
                'expenses'           => "DECIMAL(15,2) DEFAULT 0.00",
                'purchase_price'     => "DECIMAL(15,2) DEFAULT 0.00",
                'quantity'           => "INT DEFAULT 0",
                'specifications'     => "TEXT NULL",
                'category_id'        => "INT NULL DEFAULT NULL",
                'created_at'         => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'debts' => [
                'debt_type'        => "VARCHAR(30) DEFAULT 'receivable'",
                'person_type'      => "VARCHAR(30) DEFAULT 'customer'",
                'is_hasana'        => "TINYINT(1) DEFAULT 0",
                'due_date'         => "DATE NULL",
                'notes'            => "TEXT NULL",
                'remaining_amount' => "DECIMAL(15,2) DEFAULT 0.00",
                'paid_amount'      => "DECIMAL(15,2) DEFAULT 0.00",
                'total_amount'     => "DECIMAL(15,2) DEFAULT 0.00",
                'customer_phone'   => "VARCHAR(30) NULL",
                'customer_name'    => "VARCHAR(100) NOT NULL DEFAULT ''",
                'sale_id'          => "INT NULL DEFAULT NULL",
                'status'           => "VARCHAR(30) DEFAULT 'باز'",
                'created_at'       => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'installments' => [
                'payment_reason' => "VARCHAR(150) DEFAULT ''",
                'capital_amount' => "DECIMAL(15,2) DEFAULT 0.00",
                'profit_amount'  => "DECIMAL(15,2) DEFAULT 0.00",
                'notes'          => "TEXT NULL",
                'payment_date'   => "DATE NULL",
                'created_by'     => "INT NULL DEFAULT NULL",
                'created_at'     => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'customers' => [
                'customer_type' => "VARCHAR(50) DEFAULT 'پرچون'",
                'company'       => "VARCHAR(100) DEFAULT ''",
                'address'       => "TEXT NULL",
                'notes'         => "TEXT NULL",
                'phone'         => "VARCHAR(30) NULL",
                'name'          => "VARCHAR(100) NOT NULL DEFAULT ''",
                'created_at'    => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'categories' => [
                'description' => "TEXT NULL",
                'is_sample'   => "TINYINT(1) DEFAULT 0",
                'created_at'  => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'partners' => [
                'share_percentage'  => "DECIMAL(5,2) DEFAULT 0.00",
                'investment_amount' => "DECIMAL(15,2) DEFAULT 0.00",
                'join_date'         => "DATE NULL",
                'status'            => "VARCHAR(20) DEFAULT 'active'",
                'partner_type'      => "VARCHAR(30) DEFAULT 'general'",
                'notes'             => "TEXT NULL",
                'created_at'        => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'sales' => [
                'profit'         => "DECIMAL(15,2) DEFAULT 0.00",
                'payment_type'   => "VARCHAR(30) DEFAULT 'نقد'",
                'notes'          => "TEXT NULL",
                'customer_phone' => "VARCHAR(30) NULL",
                'created_by'     => "INT NULL DEFAULT NULL",
                'created_at'     => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'sale_items' => [
                'stock_id'            => "INT NULL DEFAULT NULL",
                'specifications'      => "TEXT NULL",
                'unit_purchase_price' => "DECIMAL(15,2) DEFAULT 0.00",
                'unit_expense'        => "DECIMAL(15,2) DEFAULT 0.00",
                'unit_sale_price'     => "DECIMAL(15,2) DEFAULT 0.00",
                'profit'              => "DECIMAL(15,2) DEFAULT 0.00",
                'created_at'          => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'custom_features' => [
                'title_en'           => "VARCHAR(150) NULL",
                'description_en'     => "TEXT NULL",
                'formula_expression' => "TEXT NULL",
                'updated_at'         => "DATETIME NULL"
            ],
            'mudaraba_contracts' => [
                'partner_name'           => "VARCHAR(150) NOT NULL DEFAULT ''",
                'phone'                  => "VARCHAR(50) DEFAULT ''",
                'project_title'          => "VARCHAR(200) NOT NULL DEFAULT ''",
                'country_source'         => "VARCHAR(100) DEFAULT ''",
                'capital_amount'         => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
                'partner_profit_rate'    => "DECIMAL(5,2) NOT NULL DEFAULT 50.00",
                'shop_profit_rate'       => "DECIMAL(5,2) NOT NULL DEFAULT 50.00",
                'total_sales_amount'     => "DECIMAL(15,2) DEFAULT 0.00",
                'total_profit_amount'    => "DECIMAL(15,2) DEFAULT 0.00",
                'paid_profit_to_partner' => "DECIMAL(15,2) DEFAULT 0.00",
                'returned_capital'       => "DECIMAL(15,2) DEFAULT 0.00",
                'start_date'             => "DATE NULL",
                'settlement_date'        => "DATE NULL",
                'status'                 => "VARCHAR(30) DEFAULT 'active'",
                'notes'                  => "TEXT NULL",
                'created_at'             => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ],
            'capital' => [
                'amount'             => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
                'transaction_type'   => "VARCHAR(50) NOT NULL DEFAULT 'سرمایه'",
                'source_description' => "VARCHAR(255) NULL",
                'transaction_date'   => "DATE NULL",
                'created_at'         => "DATETIME DEFAULT CURRENT_TIMESTAMP"
            ]
        ];

        foreach ($tablesRequiredColumns as $tbl => $cols) {
            try {
                $existingCols = [];
                if ($isSqlite) {
                    $info = $db->query("PRAGMA table_info(`{$tbl}`)")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($info as $r) {
                        $existingCols[] = strtolower($r['name']);
                    }
                } else {
                    try {
                        $info = $db->query("SHOW COLUMNS FROM `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($info as $r) {
                            $existingCols[] = strtolower($r['Field']);
                        }
                    } catch (Exception $e) {}
                }

                foreach ($cols as $colName => $colDef) {
                    if (empty($existingCols) || !in_array(strtolower($colName), $existingCols)) {
                        try {
                            if ($isSqlite) {
                                $db->exec("ALTER TABLE `{$tbl}` ADD COLUMN `{$colName}` {$colDef}");
                            } else {
                                $db->exec("ALTER TABLE `{$tbl}` ADD `{$colName}` {$colDef}");
                            }
                        } catch (Exception $alterEx) {}
                    }
                }
            } catch (Exception $tableEx) {}
        }

        // 3. Ensure custom_features rows exist
        $defaultFeaturesList = [
            ['low_stock_alert', 'هشدار کمبود موجودی', 'Low Stock Alert Notification', 'نمایش بنر و هشدارهای خودکار کسری کالا در سربرگ صفحات', 'Automated top alerts and banners for items with critical low quantity', 1],
            ['view_low_stock', 'مشاهده تمام اجناس کمبود', 'View All Low Stock Items', 'دسترسی به جدول تحلیلی و گزارش فیلتر شده کالاهای رو به اتمام', 'Detailed list and smart analytics of products nearing zero quantity', 1],
            ['preview_profit_specs', 'پیش‌نمایش مشخصات و مفاد', 'Live Profit & Specs Preview', 'محاسبه زنده حاشیه مفاد تخمینی و جزئیات تخنیکی کالا در هنگام فروش', 'Real-time estimated margin, markup calculation, and technical specs badge on POS', 1],
            ['shelf_view', 'نمای قفسه‌ها / الماری', 'Shelf & Cabinet Visualizer', 'نمایش گرید بصری الماری‌ها، ردیف‌ها و قفسه‌های دکان و انبار', 'Visual rack, shelf, and cabinet locator grid layout for products', 1],
            ['capital_turnover', 'مدیریت گردش سرمایه دکان', 'Capital Flow & Turnover Management', 'ارزیابی سرعت بازگشت سرمایه، نقدینگی و تزریق وجوه نقدی جدید', 'Capital rotation cycles, cash injections, and cash velocity index', 1],
            ['top_selling_report', 'پرفروش‌ترین اجناس در این دوره', 'Top Selling Products Analytics', 'نمودار و جدول پرفروش‌ترین برندها، مدل‌ها و لوازم جانبی', 'Rankings and statistics of best performing models and accessories', 1],
            ['cash_debt_period_report', 'وضعیت نقدینگی و قرضه‌ها در این بازه', 'Liquidity & Outstanding Debts in Period', 'گزارش تحلیلی نقدینگی موجود در برابر مطالبات و بدهی‌های معوقه', 'Cash flow health audit comparing collected vs pending debts for selected timeframe', 1],
            ['warehouse_position', 'موقعیت در گدام / دکان', 'Warehouse & In-Shop Location Tracking', 'تفکیک دقیق ردیف انبار، کشو، الماری و بخش فروشگاهی', 'Detailed warehouse aisle, bay, drawer, and showroom placement tracking', 1],
            ['expenses_module', 'مدیریت مصارف عمومی دکان', 'General Shop Expenses Module', 'ثبت هزینه‌های عمومی دکان (کرایه، برق، مصارف متفرقه) - ثبت ضرر فعال می‌ماند', 'General overhead expenses tracking (losses logging remains active)', 1],
            ['customers_module', 'مدیریت مشتریان VIP و پیشرفته', 'Advanced Customers & VIP Club', 'دسته‌بندی عمده و پرچون، امتیاز مشتریان و فیلترهای تکمیلی', 'Wholesale vs retail categorization, VIP tiering, and detailed profile filters', 1],
            ['ai_advisor', 'هوش مصنوعی حسابدار و تحلیلگر دکان', 'Smart AI Shop Accountant & Advisor', 'مشاوره هوشمند، پیش‌بینی فروش و پاسخ فوری به سوالات حسابداری و سود دکان', 'AI powered financial advisor answering queries, forecast demands, and profit audit', 1],
            ['backup_module', 'پشتیبان‌گیری ابری و محلی (بک‌آپ)', 'Cloud & Local Database Backup Module', 'تهیه نسخه پشتیبان خودکار و دستی از دیتابیس دکان و بازگردانی اطلاعات', 'Automated & manual database backup export and restoration system', 1]
        ];

        $insWord = $isSqlite ? "INSERT OR IGNORE INTO" : "INSERT IGNORE INTO";
        $fInsert = $db->prepare("{$insWord} custom_features (feature_key, title_fa, title_en, description_fa, description_en, is_locked) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($defaultFeaturesList as $fRow) {
            try {
                $fInsert->execute($fRow);
            } catch (Exception $e) {}
        }
    } catch (Exception $e) {
        // Suppress errors during initial bootstrap
    }
}

ensure_schema_migrations($pdo);

// Additive business/currency/inventory schema; no historical amounts are rewritten.
require_once __DIR__ . '/../migrations/001_business_currency_inventory.php';
migrate_business_currency_inventory($pdo);
require_once __DIR__ . '/../migrations/002_independent_currencies.php';
migrate_independent_currencies($pdo);

require_once __DIR__ . '/../migrations/003_office2_management.php';
migrate_office2_management($pdo);

// Additive multi-shop foundation; existing rows are assigned to shop #1, nothing is rewritten.
require_once __DIR__ . '/../migrations/004_multi_shop.php';
try {
    migrate_multi_shop($pdo);
} catch (Throwable $e) {
    // Additive step: a restricted database account must not take the shop offline.
    error_log('Nik Smart Count migration 004 pending: ' . $e->getMessage());
}

// Per-shop feature permissions; a missing row simply inherits the global state.
require_once __DIR__ . '/../migrations/005_shop_features.php';
try {
    migrate_shop_features($pdo);
} catch (Throwable $e) {
    error_log('Nik Smart Count migration 005 pending: ' . $e->getMessage());
}

// Legacy brand removal: rewrites stored branding text only, never business records.
require_once __DIR__ . '/../migrations/006_brand_cleanup.php';
try {
    migrate_brand_cleanup($pdo);
} catch (Throwable $e) {
    error_log('Nik Smart Count migration 006 pending: ' . $e->getMessage());
}

require_once __DIR__."/../migrations/007_business_security.php";
migrate_business_security($pdo);

require_once __DIR__.'/../migrations/008_inventory_documents.php';migrate_inventory_documents($pdo);
require_once __DIR__.'/../migrations/009_branch_unique_keys.php';migrate_branch_unique_keys($pdo);
require_once __DIR__.'/../migrations/010_accounting.php';migrate_accounting($pdo);
require_once __DIR__.'/../migrations/011_reservations.php';migrate_reservations($pdo);
require_once __DIR__.'/../migrations/012_fund_requests.php';migrate_fund_requests($pdo);
require_once __DIR__.'/../migrations/013_partial_purchase_returns.php';migrate_partial_purchase_returns($pdo);
require_once __DIR__.'/../migrations/014_financial_workflows.php';migrate_financial_workflows($pdo);

require_once __DIR__.'/../migrations/015_live_documents.php';migrate_live_documents($pdo);

require_once __DIR__.'/../migrations/016_partner_reviews.php';migrate_partner_reviews($pdo);

require_once __DIR__.'/../migrations/017_purchase_openings.php';migrate_purchase_openings($pdo);

require_once __DIR__.'/../migrations/018_scoped_indexes.php';migrate_scoped_indexes($pdo);

require_once __DIR__.'/../migrations/019_currency_collations.php';migrate_currency_collations($pdo);

require_once __DIR__.'/../migrations/020_invoice_design.php';migrate_invoice_design($pdo);
require_once __DIR__.'/../migrations/021_invoice_verification.php';migrate_invoice_verification($pdo);
