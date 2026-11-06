<?php
/** Normalize only known currency-code columns from earlier release tables. */
function migrate_currency_collations(PDO $db): void {
 if($db->query("SELECT version FROM schema_migrations WHERE version='019_currency_collations'")->fetchColumn())return;
 if($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
  $columns=['inventory_movements'=>['currency_code'],'sales_returns'=>['currency_code'],'fund_accounts'=>['currency_code'],'fund_transactions'=>['currency_code'],'journal_headers'=>['currency_code'],'purchase_returns'=>['currency_code'],'customer_refunds'=>['currency_code'],'fx_documents'=>['source_currency','target_currency']];
  $q=$db->prepare('SELECT COLLATION_NAME,COLUMN_DEFAULT,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
  foreach($columns as $table=>$names)foreach($names as $name){$q->execute([$table,$name]);$column=$q->fetch();if(!$column)throw new RuntimeException('Currency column missing during migration.');if($column['COLLATION_NAME']==='utf8mb4_unicode_ci')continue;$default='';if($column['COLUMN_DEFAULT']!==null){$value=trim($column['COLUMN_DEFAULT'],"'");if(!in_array($value,['AFN','USD','AED','EUR'],true))throw new RuntimeException('Unexpected currency column default.');$default=' DEFAULT '.$db->quote($value);}$nullable=$column['IS_NULLABLE']==='YES'?' NULL':' NOT NULL';$db->exec("ALTER TABLE $table MODIFY $name VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci$nullable$default");}
 }
 $db->exec("INSERT INTO schema_migrations (version) VALUES ('019_currency_collations')");
}
