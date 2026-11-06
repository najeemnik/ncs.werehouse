<?php
/** Idempotent, additive migration; legacy monetary columns remain AFN. */
function migrate_business_currency_inventory(PDO $db) {
    $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $pk = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at DATETIME DEFAULT CURRENT_TIMESTAMP){$suffix}");
    if ($db->query("SELECT version FROM schema_migrations WHERE version='001_business_currency_inventory'")->fetchColumn()) return;
    if ($sqlite) $db->beginTransaction();
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS currencies (code VARCHAR(3) PRIMARY KEY, name VARCHAR(80) NOT NULL, enabled INTEGER NOT NULL DEFAULT 0, rate_to_afn DECIMAL(24,8) NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP){$suffix}");
        $insert = $sqlite ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $s=$db->prepare("{$insert} INTO currencies (code,name,enabled,rate_to_afn) VALUES (?,?,?,?)");
        foreach ([['AFN','Afghan Afghani',1,'1.00000000'],['USD','US Dollar',0,null],['AED','UAE Dirham',0,null],['EUR','Euro',0,null]] as $r) $s->execute($r);
        $db->exec("CREATE TABLE IF NOT EXISTS currency_rate_history (id {$pk}, currency_code VARCHAR(3) NOT NULL, rate_to_afn DECIMAL(24,8) NOT NULL, changed_by INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP){$suffix}");
        $db->exec("CREATE TABLE IF NOT EXISTS currency_transactions (id {$pk}, entity_type VARCHAR(40) NOT NULL, entity_id INT NOT NULL, event_type VARCHAR(40) NOT NULL, currency_code VARCHAR(3) NOT NULL, exchange_rate DECIMAL(24,8) NOT NULL, ledger_currency VARCHAR(3) NOT NULL DEFAULT 'AFN', original_amounts TEXT NOT NULL, ledger_amounts TEXT NOT NULL, created_by INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP){$suffix}");
        $db->exec("CREATE TABLE IF NOT EXISTS inventory_purchases (id {$pk}, reference VARCHAR(100) NOT NULL UNIQUE, mode VARCHAR(12) NOT NULL, supplier VARCHAR(255) NOT NULL DEFAULT '', purchase_date DATE NOT NULL, currency_code VARCHAR(3) NOT NULL, exchange_rate DECIMAL(24,8) NOT NULL, purchase_amount DECIMAL(24,2) NOT NULL, expense_amount DECIMAL(24,2) NOT NULL, total_amount DECIMAL(24,2) NOT NULL, base_purchase_amount DECIMAL(24,2) NOT NULL, base_expense_amount DECIMAL(24,2) NOT NULL, base_total_amount DECIMAL(24,2) NOT NULL, allocation_method VARCHAR(20) NOT NULL, request_key VARCHAR(64) NOT NULL UNIQUE, content_hash VARCHAR(64) NOT NULL, notes TEXT NULL, created_by INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP){$suffix}");
        $db->exec("CREATE TABLE IF NOT EXISTS inventory_purchase_items (id {$pk}, purchase_id INT NOT NULL, stock_id INT NULL, product_name VARCHAR(255) NOT NULL, category_name VARCHAR(255) NULL, quantity DECIMAL(24,3) NOT NULL, unit VARCHAR(24) NOT NULL, sku VARCHAR(128) NULL, serial_number VARCHAR(128) NULL, purchase_price_original DECIMAL(24,6) NOT NULL, original_purchase_amount DECIMAL(24,2) NOT NULL, original_expense_amount DECIMAL(24,2) NOT NULL, base_purchase_amount DECIMAL(24,2) NOT NULL, base_expense_amount DECIMAL(24,2) NOT NULL, base_total_amount DECIMAL(24,2) NOT NULL, allocated_unit_cost DECIMAL(24,6) NOT NULL, selling_price_original DECIMAL(24,6) NOT NULL, notes TEXT NULL){$suffix}");
        $columns = [
            'stock'=>['unit'=>"VARCHAR(24) NOT NULL DEFAULT 'unit'",'sku'=>'VARCHAR(128) NULL','identity_key'=>'VARCHAR(64) NULL','inventory_value'=>'DECIMAL(24,2) NULL'],
            'expenses'=>['inventory_cost'=>'DECIMAL(24,2) NULL'],
            'sale_items'=>['unit'=>"VARCHAR(24) NOT NULL DEFAULT 'unit'",'cost_amount'=>'DECIMAL(24,2) NULL']
        ];
        foreach ($columns as $table=>$defs) {
            $existing = $sqlite ? $db->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_COLUMN,1) : $db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
            foreach($defs as $name=>$def) if(!in_array($name,$existing,true)) $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$def}");
        }
        // Widen numeric precision only on MySQL. SQLite numeric affinity already accepts fractional quantities.
        if (!$sqlite) {
            $widen = ['stock'=>['quantity'=>3,'damaged_qty'=>3,'purchase_price'=>6,'sale_price'=>6,'expenses'=>6], 'sale_items'=>['quantity'=>3,'unit_purchase_price'=>6,'unit_expense'=>6,'unit_sale_price'=>6], 'expenses'=>['loss_qty'=>3]];
            foreach($widen as $table=>$fields) {
                $info=$db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                foreach($info as $col) if(isset($fields[$col['Field']])) {
                    $scale=$fields[$col['Field']]; $type=strtolower($col['Type']);
                    if (preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)/',$type) || (preg_match('/decimal\((\d+),(\d+)\)/',$type,$m) && (int)$m[2]<$scale && (int)$m[1]-(int)$m[2]<=24-$scale)) {
                        $db->exec("ALTER TABLE `{$table}` MODIFY `{$col['Field']}` DECIMAL(24,{$scale}) " . ($col['Null']==='YES'?'NULL':'NOT NULL') . " DEFAULT 0");
                    }
                }
            }
        }
        if (!$sqlite) {
            foreach($db->query('SHOW COLUMNS FROM stock')->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $width=['name'=>255,'barcode'=>128,'supplier_name'=>255,'warehouse_location'=>255][$col['Field']]??null;
                if($width && preg_match('/varchar\((\d+)\)/i',$col['Type'],$m) && (int)$m[1]<$width) {
                    $default=$col['Default']===null ? '' : ' DEFAULT '.$db->quote($col['Default']);
                    $db->exec("ALTER TABLE stock MODIFY `{$col['Field']}` VARCHAR({$width}) ".($col['Null']==='YES'?'NULL':'NOT NULL').$default);
                }
            }
        }
        foreach ([['stock','idx_stock_sku','sku',true],['stock','idx_stock_identity','identity_key',true],['currency_transactions','idx_currency_entity','entity_type,entity_id,id',false],['inventory_purchase_items','idx_purchase_items','purchase_id',false]] as [$table,$name,$cols,$unique]) {
            $exists=$sqlite ? $db->query("SELECT name FROM sqlite_master WHERE type='index' AND name='{$name}'")->fetchColumn() : $db->query("SHOW INDEX FROM `{$table}` WHERE Key_name='{$name}'")->fetch();
            if(!$exists) $db->exec('CREATE '.($unique?'UNIQUE ':'')."INDEX {$name} ON {$table} ({$cols})");
        }
        $db->prepare("{$insert} INTO settings (setting_key,setting_value) VALUES (?,?)")->execute(['base_currency','AFN']);
        $db->prepare("INSERT INTO schema_migrations (version) VALUES (?)")->execute(['001_business_currency_inventory']);
        if ($sqlite) $db->commit();
    } catch (Throwable $e) { if ($sqlite && $db->inTransaction()) $db->rollBack(); throw $e; }
}
