<?php
function migrate_invoice_verification(PDO $db): void {
 $q=$db->prepare('SELECT 1 FROM schema_migrations WHERE version=?');$q->execute(['021_invoice_verification']);if($q->fetchColumn())return;
 $cols=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?array_column($db->query('PRAGMA table_info(sales)')->fetchAll(),'name'):array_column($db->query('SHOW COLUMNS FROM sales')->fetchAll(),'Field');if(!in_array('verification_nonce',$cols,true))$db->exec('ALTER TABLE sales ADD COLUMN verification_nonce VARCHAR(32) NULL');
 $db->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute(['021_invoice_verification']);
}
