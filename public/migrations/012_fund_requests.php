<?php
/** Bind account creation and its opening journal to one retry-safe request. */
function migrate_fund_requests(PDO $db): void {
 if($db->query("SELECT version FROM schema_migrations WHERE version='012_fund_requests'")->fetchColumn())return;
 $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
 $columns=$sqlite?array_column($db->query('PRAGMA table_info(fund_accounts)')->fetchAll(),'name'):array_column($db->query('SHOW COLUMNS FROM fund_accounts')->fetchAll(),'Field');
 foreach(['request_key'=>'VARCHAR(64) NULL','content_hash'=>'VARCHAR(64) NULL'] as $column=>$type)if(!in_array($column,$columns,true))$db->exec("ALTER TABLE fund_accounts ADD COLUMN $column $type");
 if($sqlite)$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_fund_request ON fund_accounts (shop_id,request_key)');
 elseif(!$db->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='fund_accounts' AND index_name='idx_fund_request'")->fetchColumn())$db->exec('CREATE UNIQUE INDEX idx_fund_request ON fund_accounts (shop_id,request_key)');
 $db->exec("INSERT INTO schema_migrations (version) VALUES ('012_fund_requests')");
}
