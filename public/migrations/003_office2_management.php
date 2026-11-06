<?php
/** Additive Office 2 access metadata; no existing user or accounting row is rewritten. */
function migrate_office2_management(PDO $db) {
    if($db->query("SELECT version FROM schema_migrations WHERE version='003_office2_management'")->fetchColumn())return;
    // Reuse the existing encrypted backup service before touching the schema.
    require_once __DIR__.'/../config/config.php';
    require_once __DIR__.'/../includes/backup_service.php';
    $passphrase=bin2hex(random_bytes(24));$dir=security_private_dir();
    $keyFile=$dir.'/migration-'.bin2hex(random_bytes(12)).'.key';
    if(file_put_contents($keyFile,$passphrase,LOCK_EX)!==strlen($passphrase)||!chmod($keyFile,0600)||file_get_contents($keyFile)!==$passphrase)throw new RuntimeException('Migration recovery key could not be verified.');
    $meta=backup_save_verified(backup_capture($db),$passphrase,'before_office2_migration');
    if(!rename($keyFile,$dir.'/migration-'.$meta['id'].'.key'))throw new RuntimeException('Migration recovery key could not be retained.');
    unset($passphrase);
    $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
    $pk=$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT AUTO_INCREMENT PRIMARY KEY';
    $opts=$sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    if($sqlite)$db->beginTransaction();
    try{
        $db->exec("CREATE TABLE IF NOT EXISTS user_access (
            user_id INT PRIMARY KEY, is_locked INT NOT NULL DEFAULT 0,
            must_change_password INT NOT NULL DEFAULT 0,
            subscription_active INT NOT NULL DEFAULT 1, subscription_expires_at DATETIME NULL,
            ai_daily_limit INT NULL, ai_monthly_limit INT NULL,
            ai_day VARCHAR(10) NOT NULL DEFAULT '', ai_month VARCHAR(7) NOT NULL DEFAULT '',
            ai_day_used INT NOT NULL DEFAULT 0, ai_month_used INT NOT NULL DEFAULT 0,
            ai_reset_at DATETIME NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id)
        ){$opts}");
        $db->exec("CREATE TABLE IF NOT EXISTS user_feature_access (
            user_id INT NOT NULL, feature_key VARCHAR(100) NOT NULL, allowed INT NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(user_id,feature_key), FOREIGN KEY(user_id) REFERENCES users(id)
        ){$opts}");
        $db->exec("CREATE TABLE IF NOT EXISTS ai_usage_events (
            id {$pk}, user_id INT NOT NULL, request_key VARCHAR(64) NOT NULL,
            currency_code VARCHAR(3) NOT NULL, engine VARCHAR(30) NOT NULL DEFAULT 'local_rules',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id,request_key), FOREIGN KEY(user_id) REFERENCES users(id)
        ){$opts}");
        $sql=$sqlite?"INSERT OR IGNORE INTO settings (setting_key,setting_value) VALUES ('office2.write_lock','0')":"INSERT IGNORE INTO settings (setting_key,setting_value) VALUES ('office2.write_lock','0')";
        $db->exec($sql);
        // Keep the same legacy secondary secrets usable, but no longer store them
        // as plaintext. Existing users and their password verifiers are untouched.
        foreach(['super_admin_pin','admin_pin'] as $key){
            $q=$db->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$q->execute([$key]);$value=$q->fetchColumn();
            if(is_string($value)&&$value!==''&&password_get_info($value)['algoName']==='unknown')$db->prepare('UPDATE settings SET setting_value=? WHERE setting_key=?')->execute([password_hash($value,PASSWORD_DEFAULT),$key]);
        }
        $db->exec("INSERT INTO schema_migrations (version) VALUES ('003_office2_management')");
        if($sqlite)$db->commit();
    }catch(Throwable $e){if($sqlite&&$db->inTransaction())$db->rollBack();throw $e;}
}
