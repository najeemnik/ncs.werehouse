<?php
/** Additive invoice layouts and immutable sale-time print snapshots. */
function migrate_invoice_design(PDO $db): void {
 $v='020_invoice_design';$q=$db->prepare('SELECT 1 FROM schema_migrations WHERE version=?');$q->execute([$v]);if($q->fetchColumn())return;
 $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$suffix=$sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
 $db->exec("CREATE TABLE IF NOT EXISTS business_settings (business_id INT NOT NULL,setting_key VARCHAR(100) NOT NULL,setting_value TEXT NOT NULL,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(business_id,setting_key))$suffix");
 foreach(['sales'=>['invoice_snapshot'=>'TEXT NULL'],'sale_items'=>['invoice_fields'=>'TEXT NULL']] as $table=>$columns){$existing=$sqlite?array_column($db->query("PRAGMA table_info($table)")->fetchAll(),'name'):array_column($db->query("SHOW COLUMNS FROM $table")->fetchAll(),'Field');foreach($columns as $name=>$type)if(!in_array($name,$existing,true))$db->exec("ALTER TABLE $table ADD COLUMN $name $type");}
 $ins=$sqlite?'INSERT OR IGNORE':'INSERT IGNORE';$s=$db->prepare("$ins INTO business_settings (business_id,setting_key,setting_value) VALUES (?,?,?)");
 foreach($db->query('SELECT id,name FROM businesses')->fetchAll() as $b)$s->execute([$b['id'],'shop_name',$b['name']]);
 // Only the original shop may inherit the installation's legacy contact identity.
 $first=$db->query('SELECT business_id FROM shops WHERE id=1')->fetchColumn();if($first){$keys=['shop_logo','shop_slogan','shop_email','shop_phone_central','shop_phone_secondary','shop_phone_whatsapp','shop_address_central','shop_address_secondary','invoice_title','invoice_footer_note','invoice_thank_you'];$read=$db->prepare('SELECT setting_value FROM settings WHERE setting_key=?');foreach($keys as $key){$read->execute([$key]);$value=$read->fetchColumn();if($value!==false)$s->execute([$first,$key,$value]);}}
 $db->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$v]);
}
