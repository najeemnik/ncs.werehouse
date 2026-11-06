<?php
/** Additive migration. Existing shops stay separate businesses until explicitly linked. */
function migrate_business_security(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY,applied_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
    if($db->query("SELECT version FROM schema_migrations WHERE version='007_business_security'")->fetchColumn())return;
    $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
    $pk=$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT AUTO_INCREMENT PRIMARY KEY';
    $db->exec("CREATE TABLE IF NOT EXISTS businesses (id $pk,name VARCHAR(150) NOT NULL,owner_user_id INT NULL,status VARCHAR(20) NOT NULL DEFAULT 'active',created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $columns=$sqlite?array_column($db->query('PRAGMA table_info(shops)')->fetchAll(),'name'):array_column($db->query('SHOW COLUMNS FROM shops')->fetchAll(),'Field');
    foreach(['business_id'=>'INT NULL','city'=>"VARCHAR(100) NOT NULL DEFAULT ''"] as $col=>$type)if(!in_array($col,$columns,true))$db->exec("ALTER TABLE shops ADD COLUMN $col $type");
    $db->exec("CREATE TABLE IF NOT EXISTS branch_memberships (user_id INT NOT NULL,shop_id INT NOT NULL,PRIMARY KEY(user_id,shop_id))");
    $db->exec("CREATE TABLE IF NOT EXISTS service_policies (scope_type VARCHAR(20) NOT NULL,scope_id INT NOT NULL,feature VARCHAR(80) NOT NULL,enabled INT NOT NULL DEFAULT 0,visibility VARCHAR(10) NOT NULL DEFAULT 'hidden',daily_limit INT NULL,monthly_limit INT NULL,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(scope_type,scope_id,feature))");
    $db->exec("CREATE TABLE IF NOT EXISTS service_usage (scope_type VARCHAR(20) NOT NULL,scope_id INT NOT NULL,feature VARCHAR(80) NOT NULL,period_key VARCHAR(12) NOT NULL,used INT NOT NULL DEFAULT 0,PRIMARY KEY(scope_type,scope_id,feature,period_key))");
    $db->exec("CREATE TABLE IF NOT EXISTS security_events (id $pk,business_id INT NOT NULL DEFAULT 0,shop_id INT NOT NULL DEFAULT 0,actor_id INT NULL,event_type VARCHAR(60) NOT NULL,entity_type VARCHAR(60) NOT NULL,entity_id VARCHAR(80) NULL,before_json TEXT NULL,after_json TEXT NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS daily_closings (id $pk,shop_id INT NOT NULL,currency_code VARCHAR(3) NOT NULL,closing_date VARCHAR(10) NOT NULL,expected_amount DECIMAL(18,2) NOT NULL,actual_amount DECIMAL(18,2) NOT NULL,difference_amount DECIMAL(18,2) NOT NULL,notes TEXT,created_by INT NOT NULL,approved_by INT NULL,status VARCHAR(20) NOT NULL DEFAULT 'submitted',created_at DATETIME DEFAULT CURRENT_TIMESTAMP,UNIQUE(shop_id,currency_code,closing_date))");
    $columns=$sqlite?array_column($db->query('PRAGMA table_info(customers)')->fetchAll(),'name'):array_column($db->query('SHOW COLUMNS FROM customers')->fetchAll(),'Field');
    foreach(['relationship_type'=>"VARCHAR(20) NOT NULL DEFAULT 'registered'",'trade_type'=>"VARCHAR(20) NOT NULL DEFAULT 'retail'"] as $col=>$type)if(!in_array($col,$columns,true))$db->exec("ALTER TABLE customers ADD COLUMN $col $type");
    $ins=$sqlite?'INSERT OR IGNORE':'INSERT IGNORE';
    foreach($db->query('SELECT id,name,owner_user_id FROM shops WHERE business_id IS NULL')->fetchAll() as $row){
        $db->prepare('INSERT INTO businesses (name,owner_user_id) VALUES (?,?)')->execute([$row['name'],$row['owner_user_id']]);$id=(int)$db->lastInsertId();
        $db->prepare('UPDATE shops SET business_id=? WHERE id=?')->execute([$id,$row['id']]);
    }
    $db->exec("UPDATE users SET shop_id=1 WHERE shop_id IS NULL AND role NOT IN ('superadmin','super_admin')");
    foreach($db->query('SELECT id,shop_id FROM users WHERE shop_id IS NOT NULL')->fetchAll() as $u)$db->prepare("$ins INTO branch_memberships (user_id,shop_id) VALUES (?,?)")->execute([$u['id'],$u['shop_id']]);
    // Quarantine legacy demonstration passwords; never grant access merely to force a change.
    foreach($db->query('SELECT id,password FROM users')->fetchAll() as $u){
        foreach(['admin123','afghanwashere'] as $knownDemo)if(password_verify($knownDemo,$u['password'])){
            $db->prepare('UPDATE user_access SET is_locked=1,must_change_password=1 WHERE user_id=?')->execute([$u['id']]);break;
        }
    }
    $db->exec("$ins INTO schema_migrations (version) VALUES ('007_business_security')");
}
