<?php
/** Historical uniqueness must not couple independent branches/businesses. */
function migrate_branch_unique_keys(PDO $db): void {
    if($db->query("SELECT version FROM schema_migrations WHERE version='009_branch_unique_keys'")->fetchColumn())return;
    $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
    if($sqlite){
        // Rebuild only tables with inline UNIQUE columns; indexes and column data are retained.
        $fk=(int)$db->query('PRAGMA foreign_keys')->fetchColumn();$db->exec('PRAGMA foreign_keys=OFF');$db->beginTransaction();
        try{
            foreach(['categories','inventory_purchases','sales'] as $table){
                $q=$db->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=?");$q->execute([$table]);$sql=$q->fetchColumn();$q->closeCursor();
                if(!preg_match('/\bUNIQUE\b/i',$sql))continue;
                if(preg_match('/\bUNIQUE\s*\(/i',$sql))throw new RuntimeException('Custom table uniqueness requires a reviewed migration.');
                $indexes=$db->prepare("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name=? AND sql IS NOT NULL");$indexes->execute([$table]);$indexes=$indexes->fetchAll(PDO::FETCH_COLUMN);
                $temporary=$table.'_scope_migration';$create=preg_replace('/^CREATE TABLE\s+(?:IF NOT EXISTS\s+)?["`\[]?'.preg_quote($table,'/').'["`\]]?/i','CREATE TABLE '.$temporary,$sql,1);
                $create=preg_replace('/\bUNIQUE\b/i','',$create);$db->exec($create);$db->exec("INSERT INTO $temporary SELECT * FROM $table");$db->exec("DROP TABLE $table");$db->exec("ALTER TABLE $temporary RENAME TO $table");foreach($indexes as $index)$db->exec($index);
            }
            foreach(['idx_stock_sku','idx_stock_identity'] as $index)$db->exec('DROP INDEX IF EXISTS '.$index);
            foreach(['stock'=>['idx_stock_sku'=>'sku','idx_stock_identity'=>'identity_key'],'categories'=>['idx_categories_scope_name'=>'name'],'inventory_purchases'=>['idx_purchase_scope_reference'=>'reference','idx_purchase_scope_request'=>'request_key'],'sales'=>['idx_sales_scope_invoice'=>'invoice_no']] as $table=>$indexes)foreach($indexes as $name=>$cols)$db->exec("CREATE UNIQUE INDEX $name ON $table (shop_id,$cols)");
            $db->exec("INSERT INTO schema_migrations (version) VALUES ('009_branch_unique_keys')");$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}finally{$db->exec('PRAGMA foreign_keys='.$fk);}
    }else{
        foreach(['stock'=>['sku','identity_key'],'categories'=>['name'],'inventory_purchases'=>['reference','request_key'],'sales'=>['invoice_no']] as $table=>$columns){
            foreach($columns as $col){
                $q=$db->prepare("SELECT INDEX_NAME FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND non_unique=0 AND index_name<>'PRIMARY' GROUP BY INDEX_NAME HAVING COUNT(*)=1 AND MAX(COLUMN_NAME)=?");$q->execute([$table,$col]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $index){if(!preg_match('/^[a-zA-Z0-9_]+$/D',$index))throw new RuntimeException('Unexpected index identifier.');$db->exec("ALTER TABLE `$table` DROP INDEX `$index`");}
                $name='idx_scope_'.$table.'_'.$col;$q=$db->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?');$q->execute([$table,$name]);if(!(int)$q->fetchColumn())$db->exec("CREATE UNIQUE INDEX `$name` ON `$table` (shop_id,`$col`)");
            }
        }$db->exec("INSERT INTO schema_migrations (version) VALUES ('009_branch_unique_keys')");
    }
}
