<?php
/** Nullable keys preserve historical invoices while making new POS submissions retry-safe. */
function migrate_sale_idempotency(PDO $db): void {
    if($db->query("SELECT version FROM schema_migrations WHERE version='023_sale_idempotency'")->fetchColumn())return;
    $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
    $columns=$sqlite?array_column($db->query('PRAGMA table_info(sales)')->fetchAll(),'name'):array_column($db->query('SHOW COLUMNS FROM sales')->fetchAll(),'Field');
    if($sqlite)$db->beginTransaction();
    try{
        if(!in_array('sale_request_key',$columns,true))$db->exec('ALTER TABLE sales ADD COLUMN sale_request_key VARCHAR(64) NULL');
        if(!in_array('sale_content_hash',$columns,true))$db->exec('ALTER TABLE sales ADD COLUMN sale_content_hash VARCHAR(64) NULL');
        if($sqlite)$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_sale_request ON sales (shop_id,sale_request_key)');
        else{
            $q=$db->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='sales' AND index_name='idx_sale_request'");
            if(!(int)$q->fetchColumn())$db->exec('CREATE UNIQUE INDEX idx_sale_request ON sales (shop_id,sale_request_key)');
        }
        $db->exec("INSERT INTO schema_migrations (version) VALUES ('023_sale_idempotency')");
        if($sqlite)$db->commit();
    }catch(Throwable $e){if($sqlite&&$db->inTransaction())$db->rollBack();throw $e;}
}
