<?php
function migrate_purchase_openings(PDO $db): void {
 if($db->query("SELECT version FROM schema_migrations WHERE version='017_purchase_openings'")->fetchColumn())return;$ddlSuffix=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';$pk=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT AUTO_INCREMENT PRIMARY KEY';
 $db->exec("CREATE TABLE IF NOT EXISTS purchase_opening_reviews (id $pk,shop_id INT NOT NULL,purchase_id INT NOT NULL,verified_paid DECIMAL(20,2) NOT NULL,verified_payable DECIMAL(20,2) NOT NULL,review_note TEXT NOT NULL,created_by INT NOT NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,UNIQUE(shop_id,purchase_id))$ddlSuffix");
 $db->exec("INSERT INTO schema_migrations (version) VALUES ('017_purchase_openings')");
}
