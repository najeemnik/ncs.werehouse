<?php
/**
 * Phase 4 / Stage B - multi-shop foundation.
 *
 * Purely additive:
 *   - creates the `shops` register and the per-shop `shop_settings` container;
 *   - adds a `shop_id` scope column to the business tables;
 *   - assigns every historical row to shop #1, the shop that is already installed.
 *
 * No row is deleted, no amount is recalculated and no existing column is altered,
 * so the current single-shop installation keeps behaving exactly as before.
 */
function migrate_multi_shop(PDO $db) {
    if ($db->query("SELECT version FROM schema_migrations WHERE version='004_multi_shop'")->fetchColumn()) return;

    // Verified encrypted backup before the schema is touched (same helper as 003).
    // The backup is taken once: a settings marker prevents repeating it when a
    // restricted database account refuses ALTER TABLE and the step is retried.
    $markerKey = 'migration.004.backup';
    $marker = $db->prepare("SELECT setting_value FROM settings WHERE setting_key=?");
    $marker->execute([$markerKey]);
    $markerValue = (string)$marker->fetchColumn();
    if ($markerValue === '') {
        require_once __DIR__ . '/../config/config.php';
        require_once __DIR__ . '/../includes/backup_service.php';
        $passphrase = bin2hex(random_bytes(24));
        $dir = security_private_dir();
        $keyFile = $dir . '/migration-' . bin2hex(random_bytes(12)) . '.key';
        if (file_put_contents($keyFile, $passphrase, LOCK_EX) !== strlen($passphrase) || !chmod($keyFile, 0600) || file_get_contents($keyFile) !== $passphrase) {
            throw new RuntimeException('Migration recovery key could not be verified.');
        }
        $meta = backup_save_verified(backup_capture($db), $passphrase, 'before_multi_shop_migration');
        if (!rename($keyFile, $dir . '/migration-' . $meta['id'] . '.key')) {
            throw new RuntimeException('Migration recovery key could not be retained.');
        }
        unset($passphrase);
        $saved = (string)$meta['id'];
        try {
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?)")->execute([$markerKey, $saved]);
        } catch (Throwable $e) {
            $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?")->execute([$saved, $markerKey]);
        }
    }

    $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $pk = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $opts = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $tableExists = static function (string $table) use ($db, $sqlite): bool {
        if ($sqlite) {
            $q = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
            $q->execute([$table]);
            return (bool)$q->fetchColumn();
        }
        $q = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
        $q->execute([$table]);
        return (int)$q->fetchColumn() > 0;
    };
    $columnExists = static function (string $table, string $column) use ($db, $sqlite): bool {
        if (!preg_match('/^[a-z_]+$/', $table) || !preg_match('/^[a-z_]+$/', $column)) return true;
        if ($sqlite) {
            foreach ($db->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC) as $col) {
                if (strcasecmp((string)$col['name'], $column) === 0) return true;
            }
            return false;
        }
        $q = $db->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");
        $q->execute([$table,$column]);
        return (bool)$q->fetch(PDO::FETCH_ASSOC);
    };
    $indexExists = static function (string $table, string $index) use ($db, $sqlite): bool {
        if ($sqlite) {
            $q = $db->prepare("SELECT name FROM sqlite_master WHERE type='index' AND name=?");
            $q->execute([$index]);
            return (bool)$q->fetchColumn();
        }
        $q = $db->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?");
        $q->execute([$table, $index]);
        return (int)$q->fetchColumn() > 0;
    };

    if ($sqlite) $db->beginTransaction();
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS shops (
            id {$pk},
            name VARCHAR(150) NOT NULL,
            owner_user_id INT NULL,
            slug VARCHAR(60) DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            subscription_active INT NOT NULL DEFAULT 1,
            subscription_expires_at DATETIME NULL,
            employee_limit INT NOT NULL DEFAULT 0,
            can_add_employees INT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$opts}");

        $db->exec("CREATE TABLE IF NOT EXISTS shop_settings (
            shop_id INT NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(shop_id, setting_key)
        ){$opts}");

        // Shop #1 is the installation that already exists; it takes the current identity.
        if ((int)$db->query("SELECT COUNT(*) FROM shops")->fetchColumn() === 0) {
            $shopName = '';
            try {
                $q = $db->prepare("SELECT setting_value FROM settings WHERE setting_key='shop_name'");
                $q->execute();
                $shopName = trim((string)$q->fetchColumn());
            } catch (Throwable $e) {
                $shopName = '';
            }
            if ($shopName === '') $shopName = defined('DEFAULT_SHOP_NAME') ? DEFAULT_SHOP_NAME : 'Nik Smart Count - Warehousing';
            $ownerId = null;
            try {
                $owner = $db->query("SELECT id FROM users ORDER BY CASE WHEN role IN ('superadmin','super_admin') THEN 0 ELSE 1 END, id LIMIT 1")->fetchColumn();
                if ($owner !== false) $ownerId = (int)$owner;
            } catch (Throwable $e) {
                $ownerId = null;
            }
            $ins = $db->prepare("INSERT INTO shops (id,name,owner_user_id,slug,status,subscription_active,can_add_employees,employee_limit,created_by) VALUES (1,?,?,?, 'active', 1, 1, 3, ?)");
            $ins->execute([$shopName, $ownerId, 'main', $ownerId]);
        }

        // Scope column for every ledger/business table. Historical rows -> shop #1.
        $scoped = ['stock','categories','sales','sale_items','debts','installments','customers','expenses',
            'capital','partners','partnership_transactions','mudaraba_contracts','inventory_purchases',
            'inventory_purchase_items','currency_transactions','activity_logs','ai_usage_events'];
        foreach ($scoped as $table) {
            if (!$tableExists($table)) continue;
            if (!$columnExists($table, 'shop_id')) {
                $type = $sqlite ? 'INTEGER NOT NULL DEFAULT 1' : 'INT NOT NULL DEFAULT 1';
                $db->exec("ALTER TABLE `{$table}` ADD COLUMN shop_id {$type}");
            }
            $db->exec("UPDATE `{$table}` SET shop_id = 1 WHERE shop_id IS NULL OR shop_id = 0");
            $index = 'idx_' . $table . '_shop';
            if (!$indexExists($table, $index)) {
                $db->exec("CREATE INDEX `{$index}` ON `{$table}` (shop_id)");
            }
        }

        // Users: NULL means a central (Office 2) account that is not tied to one shop.
        if ($tableExists('users') && !$columnExists('users', 'shop_id')) {
            $type = $sqlite ? 'INTEGER NULL' : 'INT NULL';
            $db->exec("ALTER TABLE `users` ADD COLUMN shop_id {$type} DEFAULT NULL");
            if (!$indexExists('users', 'idx_users_shop')) {
                $db->exec("CREATE INDEX `idx_users_shop` ON `users` (shop_id)");
            }
        }

        $db->exec("INSERT INTO schema_migrations (version) VALUES ('004_multi_shop')");
        if ($sqlite) $db->commit();
    } catch (Throwable $e) {
        if ($sqlite && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
