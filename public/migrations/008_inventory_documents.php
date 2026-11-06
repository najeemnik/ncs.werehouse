<?php
function migrate_inventory_documents(PDO $db): void {
    if($db->query("SELECT version FROM schema_migrations WHERE version='008_inventory_documents'")->fetchColumn())return;
    $ddlSuffix=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';$pk=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT AUTO_INCREMENT PRIMARY KEY';
    $db->exec("CREATE TABLE IF NOT EXISTS inventory_movements (id $pk,shop_id INT NOT NULL,stock_id INT NOT NULL,document_type VARCHAR(40) NOT NULL,document_id VARCHAR(80) NOT NULL,quantity_delta DECIMAL(18,3) NOT NULL,value_delta DECIMAL(18,2) NOT NULL,currency_code VARCHAR(3) NOT NULL,actor_id INT NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP)$ddlSuffix");
    $db->exec("CREATE TABLE IF NOT EXISTS inventory_transfers (id $pk,business_id INT NOT NULL,source_shop_id INT NOT NULL,destination_shop_id INT NOT NULL,stock_id INT NOT NULL,quantity DECIMAL(18,3) NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'pending',notes TEXT,created_by INT NOT NULL,received_by INT NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,received_at DATETIME NULL)$ddlSuffix");
    $db->exec("CREATE TABLE IF NOT EXISTS sales_returns (id $pk,shop_id INT NOT NULL,sale_id INT NOT NULL,reason TEXT NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'posted',created_by INT NOT NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,UNIQUE(shop_id,sale_id))$ddlSuffix");
    $db->exec("INSERT INTO schema_migrations (version) VALUES ('008_inventory_documents')");
}
