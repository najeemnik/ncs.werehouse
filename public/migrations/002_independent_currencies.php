<?php
/** Add native currency denominations without rewriting any saved amounts or FX archives. */
function migrate_independent_currencies(PDO $db) {
    if($db->query("SELECT version FROM schema_migrations WHERE version='002_independent_currencies'")->fetchColumn())return;
    $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
    if($sqlite)$db->beginTransaction();
    try {
        $tables=['stock','sales','sale_items','expenses','debts','installments','capital','partners','mudaraba_contracts','losses'];
        $existingTables=$sqlite?$db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN):$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $tables=array_values(array_intersect($tables,$existingTables));
        $defs=[];
        foreach($tables as $table)$defs[$table]=['currency_code'=>"VARCHAR(3) NOT NULL DEFAULT 'AFN'"];
        $defs['stock']['sale_currency_code']="VARCHAR(3) NOT NULL DEFAULT 'AFN'";
        $defs['sales']['profit_known']='INTEGER NOT NULL DEFAULT 1';
        $defs['debts']['profit_known']='INTEGER NOT NULL DEFAULT 1';
        $defs['sale_items']+=['cost_currency_code'=>"VARCHAR(3) NOT NULL DEFAULT 'AFN'",'profit_known'=>'INTEGER NOT NULL DEFAULT 1'];
        $defs['expenses']['inventory_currency_code']="VARCHAR(3) NOT NULL DEFAULT 'AFN'";
        $defs['installments']['applied_to_balance']='INTEGER NOT NULL DEFAULT 1';
        $defs['installments']['split_known']='INTEGER NOT NULL DEFAULT 1';
        $defs['mudaraba_contracts']['profit_known']='INTEGER NOT NULL DEFAULT 1';
        $defs['currency_transactions']=['accounting_mode'=>"VARCHAR(16) NOT NULL DEFAULT 'legacy_afn'",'profit_known'=>'INTEGER NOT NULL DEFAULT 1','related_currency'=>'VARCHAR(3) NULL'];
        $defs['inventory_purchases']=['accounting_mode'=>"VARCHAR(16) NOT NULL DEFAULT 'legacy_afn'",'ledger_currency'=>"VARCHAR(3) NOT NULL DEFAULT 'AFN'"];
        $defs['inventory_purchase_items']=['selling_currency_code'=>'VARCHAR(3) NULL','partner_id'=>'INT NULL','mudaraba_id'=>'INT NULL'];
        $newSellingCurrency=false;
        foreach($defs as $table=>$columns) {
            $existing=$sqlite?$db->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_COLUMN,1):$db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
            foreach($columns as $name=>$def)if(!in_array($name,$existing,true)){
                $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$def}");
                if($table==='inventory_purchase_items'&&$name==='selling_currency_code')$newSellingCurrency=true;
            }
        }
        // Historical selling-price snapshots were entered in their purchase header's currency.
        if($newSellingCurrency)$db->exec('UPDATE inventory_purchase_items SET selling_currency_code=(SELECT currency_code FROM inventory_purchases WHERE inventory_purchases.id=inventory_purchase_items.purchase_id) WHERE EXISTS (SELECT 1 FROM inventory_purchases WHERE inventory_purchases.id=inventory_purchase_items.purchase_id)');
        // One SKU may have independent cost positions in different currencies. Never average them together.
        if($sqlite)$db->exec('DROP INDEX IF EXISTS idx_stock_sku');
        elseif($db->query("SHOW INDEX FROM stock WHERE Key_name='idx_stock_sku'")->fetch())$db->exec('DROP INDEX idx_stock_sku ON stock');
        $indexes=[['stock','idx_stock_sku_currency','sku,currency_code',true]];
        foreach($tables as $t)$indexes[]=[$t,'idx_'.$t.'_currency','currency_code',false];
        foreach($indexes as [$table,$name,$columns,$unique]) {
            $exists=$sqlite?$db->query("SELECT name FROM sqlite_master WHERE type='index' AND name='{$name}'")->fetchColumn():$db->query("SHOW INDEX FROM `{$table}` WHERE Key_name='{$name}'")->fetch();
            if(!$exists)$db->exec('CREATE '.($unique?'UNIQUE ':'')."INDEX {$name} ON {$table} ({$columns})");
        }
        $db->exec("INSERT INTO schema_migrations (version) VALUES ('002_independent_currencies')");
        if($sqlite)$db->commit();
    }catch(Throwable $e){if($sqlite&&$db->inTransaction())$db->rollBack();throw $e;}
}
