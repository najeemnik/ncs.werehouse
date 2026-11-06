<?php
/**
 * Phase 4 / Stage B - per-shop feature permissions.
 *
 * Purely additive: one small table that records, for a given shop, whether a
 * feature is explicitly allowed or denied. A missing row means "inherit the
 * global licence state", so nothing changes for existing installations.
 */
function migrate_shop_features(PDO $db) {
    if ($db->query("SELECT version FROM schema_migrations WHERE version='005_shop_features'")->fetchColumn()) return;

    $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
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

    // Depends on the shops register from migration 004; skip quietly until it exists.
    if (!$tableExists('shops')) return;

    $opts = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $db->exec("CREATE TABLE IF NOT EXISTS shop_feature_access (
        shop_id INT NOT NULL,
        feature_key VARCHAR(100) NOT NULL,
        allowed INT NOT NULL DEFAULT 1,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(shop_id, feature_key)
    ){$opts}");

    $db->exec("INSERT INTO schema_migrations (version) VALUES ('005_shop_features')");
}
