<?php
if(!defined('NSC_INTERNAL_MIGRATION')){http_response_code(404);exit;}
function nscMigrateDatabase(PDO $db):void {
if(!$db->query("SELECT GET_LOCK('nsc-schema-migration',10)")->fetchColumn()) throw new RuntimeException('Another migration is running');
try {
    $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $schemas=require __DIR__.'/schema-sql.php';$db->exec($schemas['base']);
    $tables=['company_profiles','fiscal_periods','projects','project_budgets','treasury_accounts','suppliers','subcontractors','clients','apartment_units','payment_vouchers','material_procurements','labor_payroll','revenue_entries','treasury_transactions','petty_cash_expenses','assets_machinery','project_transfers','journal_entries','journal_lines','steel_procurements','concrete_castings','project_partners','partner_investments','project_documents','system_audit_logs'];
    foreach($tables as $table) {
        $s=$db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:t AND column_name=\'tenant_id\'');$s->execute(['t'=>$table]);
        if(!$s->fetchColumn()) $db->exec("ALTER TABLE `$table` ADD tenant_id VARCHAR(36) NULL, ADD INDEX idx_tenant_id (tenant_id)");
    }
    $s=$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='session_version'");
    if(!$s->fetchColumn()) $db->exec('ALTER TABLE users ADD session_version INT UNSIGNED NOT NULL DEFAULT 1');
    $s=$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='office7_locked'");
    if(!$s->fetchColumn())$db->exec('ALTER TABLE users ADD office7_locked TINYINT(1) NOT NULL DEFAULT 0');
    $db->exec('CREATE TABLE IF NOT EXISTS office7_control(id TINYINT UNSIGNED PRIMARY KEY,owner_user_id VARCHAR(36) NOT NULL,path_token CHAR(64) NOT NULL,revision BIGINT UNSIGNED NOT NULL DEFAULT 1,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $s=$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='profile_json'");if(!$s->fetchColumn())$db->exec('ALTER TABLE users ADD profile_json LONGTEXT NULL');
    $db->exec("CREATE TABLE IF NOT EXISTS public_page(id TINYINT UNSIGNED PRIMARY KEY,revision BIGINT UNSIGNED NOT NULL DEFAULT 0,content LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $s=$db->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES(:v)');$s->execute(['v'=>'005-profiles-and-public-introduction']);
    // Known demonstration passwords cannot remain active after migration.
    if(!$db->query("SELECT COUNT(*) FROM schema_migrations WHERE version='002-secure-tenants-sessions-workspaces'")->fetchColumn())foreach($db->query('SELECT id,password_hash FROM users')->fetchAll() as $u) {
        foreach(['password','password123','admin123','master123'] as $known) if(password_verify($known,$u['password_hash'])) {
            $s=$db->prepare('UPDATE users SET is_locked=1,session_version=session_version+1 WHERE id=:id');$s->execute(['id'=>$u['id']]);break;
        }
    }
    $db->exec('CREATE TABLE IF NOT EXISTS auth_rate_limits (bucket_key CHAR(64) PRIMARY KEY, attempts INT UNSIGNED NOT NULL, window_started DATETIME NOT NULL, INDEX(window_started)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE IF NOT EXISTS uploaded_files (id VARCHAR(36) PRIMARY KEY,tenant_id VARCHAR(36) NOT NULL,project_id VARCHAR(36) NOT NULL,original_name VARCHAR(255) NOT NULL,mime_type VARCHAR(100) NOT NULL,file_size BIGINT UNSIGNED NOT NULL,created_by VARCHAR(36) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(tenant_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE IF NOT EXISTS financial_requests (tenant_id VARCHAR(36) NOT NULL,request_id VARCHAR(80) NOT NULL,request_hash CHAR(64) NOT NULL,action VARCHAR(80) NOT NULL,result LONGTEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(tenant_id,request_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE IF NOT EXISTS password_resets (token_hash CHAR(64) PRIMARY KEY,user_id VARCHAR(36) NOT NULL,expires_at DATETIME NOT NULL,used_at DATETIME NULL,INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE IF NOT EXISTS tenant_workspaces (tenant_id VARCHAR(36) PRIMARY KEY, revision BIGINT UNSIGNED NOT NULL DEFAULT 0, document LONGTEXT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE IF NOT EXISTS workspace_revisions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id VARCHAR(36) NOT NULL,revision BIGINT UNSIGNED NOT NULL,document LONGTEXT NOT NULL,created_by VARCHAR(36) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY(tenant_id,revision)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $s=$db->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES(:v)');$s->execute(['v'=>'002-secure-tenants-sessions-workspaces']);
    $s=$db->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES(:v)');$s->execute(['v'=>'003-private-office7-per-user-licenses']);
    // Remove retired client-only passwords/API keys while preserving financial history.
    if(!$db->query("SELECT COUNT(*) FROM schema_migrations WHERE version='004-remove-retired-client-credentials'")->fetchColumn())foreach(['tenant_workspaces','workspace_revisions'] as $table)$db->exec("UPDATE `$table` SET document=JSON_REMOVE(document,'$.appSettings.sectionProtection','$.appSettings.geminiApiKey') WHERE (document LIKE '%sectionProtection%' OR document LIKE '%geminiApiKey%') AND JSON_VALID(document) AND (JSON_CONTAINS_PATH(document,'one','$.appSettings.sectionProtection','$.appSettings.geminiApiKey'))");
    $s=$db->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES(:v)');$s->execute(['v'=>'004-remove-retired-client-credentials']);
    if(!$db->query("SELECT COUNT(*) FROM schema_migrations WHERE version='006-restore-smart-dashboard'")->fetchColumn()){
      $db->exec("UPDATE tenant_workspaces SET document=JSON_ARRAY_APPEND(document,'$.appSettings.dashboardDesign.cards',JSON_OBJECT('id','smart','kind','smart','visible',JSON_EXTRACT('true','$'),'width',12)),revision=revision+1 WHERE JSON_VALID(document) AND JSON_TYPE(JSON_EXTRACT(document,'$.appSettings.dashboardDesign.cards'))='ARRAY' AND JSON_LENGTH(JSON_EXTRACT(document,'$.appSettings.dashboardDesign.cards'))<30 AND JSON_SEARCH(document,'one','smart',NULL,'$.appSettings.dashboardDesign.cards[*].kind') IS NULL");
      $s=$db->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES(:v)');$s->execute(['v'=>'006-restore-smart-dashboard']);
    }
    $s=$db->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='uploaded_files' AND column_name='project_id'");if((int)$s->fetchColumn()<80)$db->exec('ALTER TABLE uploaded_files MODIFY project_id VARCHAR(80) NOT NULL');
    $s=$db->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES(:v)');$s->execute(['v'=>'007-upload-workspace-project-identifiers']);
    $s=$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='uploaded_files' AND column_name='media_kind'");if(!$s->fetchColumn())$db->exec('ALTER TABLE uploaded_files ADD media_kind VARCHAR(20) NULL');
    $s=$db->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES(:v)');$s->execute(['v'=>'008-private-project-images']);
    // Security tables also hold Dari/Pashto JSON and names. Never inherit a latin1 host default.
    $securityTables=['schema_migrations','office7_control','public_page','auth_rate_limits','uploaded_files','financial_requests','password_resets','tenant_workspaces','workspace_revisions'];
    foreach($securityTables as $table){$s=$db->prepare('SELECT engine,table_collation FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:t');$s->execute(['t'=>$table]);$meta=$s->fetch();if(strtolower($meta['engine'])!=='innodb'||$meta['table_collation']!=='utf8mb4_unicode_ci')$db->exec("ALTER TABLE `$table` ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");}
    $s=$db->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES(:v)');$s->execute(['v'=>'009-unicode-transactional-security-tables']);
    $db->exec($schemas['studio']);
    $s=$db->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES(:v)');$s->execute(['v'=>'010-visual-business-studio']);
} finally {$db->query("SELECT RELEASE_LOCK('nsc-schema-migration')");}

}
