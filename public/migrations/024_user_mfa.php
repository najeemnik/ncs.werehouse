<?php
/** Per-user trusted authenticator devices; only random-token and user-agent hashes are stored. */
function migrate_user_mfa(PDO $db): void {
    if($db->query("SELECT version FROM schema_migrations WHERE version='024_user_mfa'")->fetchColumn())return;
    $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$pk=$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT AUTO_INCREMENT PRIMARY KEY';
    if($sqlite)$db->beginTransaction();
    try{
        $db->exec("CREATE TABLE IF NOT EXISTS user_mfa_devices (id $pk,user_id INT NOT NULL,token_hash VARCHAR(64) NOT NULL,agent_hash VARCHAR(64) NOT NULL,device_label VARCHAR(100) NOT NULL DEFAULT '',created_at DATETIME NOT NULL,expires_at DATETIME NOT NULL,last_used_at DATETIME NULL".($sqlite?'':',UNIQUE KEY uq_mfa_token (token_hash),KEY idx_mfa_user_expiry (user_id,expires_at)').")".($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'));
        if($sqlite){$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_mfa_token ON user_mfa_devices (token_hash)');$db->exec('CREATE INDEX IF NOT EXISTS idx_mfa_user_expiry ON user_mfa_devices (user_id,expires_at)');}
        $db->exec("INSERT INTO schema_migrations (version) VALUES ('024_user_mfa')");if($sqlite)$db->commit();
    }catch(Throwable $e){if($sqlite&&$db->inTransaction())$db->rollBack();throw $e;}
}
