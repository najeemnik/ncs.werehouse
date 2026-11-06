<?php
function migrate_scoped_indexes(PDO $db): void {
 if($db->query("SELECT version FROM schema_migrations WHERE version='018_scoped_indexes'")->fetchColumn())return;$sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
 $indexes=['idx_ledger_account_entries'=>['journal_details','shop_id,account_code,journal_id'],'idx_journal_period'=>['journal_headers','shop_id,currency_code,posting_date,id'],'idx_fund_source'=>['fund_transactions','shop_id,source_type,source_id'],'idx_audit_actor_event'=>['security_events','actor_id,event_type,created_at'],'idx_audit_branch_time'=>['security_events','shop_id,created_at,id'],'idx_stock_movement'=>['inventory_movements','shop_id,stock_id,id'],'idx_purchase_line_returns'=>['purchase_return_lines','shop_id,purchase_item_id'],'idx_sale_line_returns'=>['return_lines','shop_id,sale_item_id']];
 foreach($indexes as $name=>[$table,$columns]){if($sqlite)$db->exec("CREATE INDEX IF NOT EXISTS $name ON $table ($columns)");else{$q=$db->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?');$q->execute([$table,$name]);if(!$q->fetchColumn())$db->exec("CREATE INDEX $name ON $table ($columns)");}}
 $db->exec("INSERT INTO schema_migrations (version) VALUES ('018_scoped_indexes')");
}
