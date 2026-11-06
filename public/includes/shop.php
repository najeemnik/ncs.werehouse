<?php
/**
 * Multi-shop context (Phase 4 / Stage B).
 *
 * The first shop of the installation keeps its id = 1, so a single-shop
 * database behaves exactly as before: every row already carries shop_id = 1
 * and every helper below resolves to that same shop.
 *
 * Rules used across the application:
 *   - a user account without shop_id (Office 2 / central account) works on shop #1
 *     unless it explicitly selected another shop in the session;
 *   - a user account tied to a shop (users.shop_id) always works on that shop;
 *   - shop_sql('alias') returns a ready SQL fragment such as "s.shop_id=1".
 */

/** The shop the current request works on. */
function shop_current_id(): int {
    global $pdo;
    $preview=preview_state();if($preview)return (int)$preview['branch_id'];
    if(empty($_SESSION['user_id']))return 0;
    $q=$pdo->prepare('SELECT id,role,shop_id FROM users WHERE id=?');$q->execute([(int)$_SESSION['user_id']]);$u=$q->fetch();
    if(!$u)return 0;
    $candidate=(int)($_SESSION['shop_context_id']??$u['shop_id']??0);
    if($candidate>0 && function_exists('domain_branch_allowed') && domain_branch_allowed($u,$candidate))return $candidate;
    if(isset($_SESSION['shop_context_id']))throw new RuntimeException('Branch access revoked.',403);
    return 0;
}

/** Short alias used by the modules. */
function shop_id(): int {
    return shop_current_id();
}

/**
 * True once migration 004 has added the shop columns.
 *
 * The modules call shop_sql() on every query, so the check is done once per
 * request and cached. On a database that has not been migrated yet every
 * shop filter degrades to "1=1", which keeps the single shop fully working
 * instead of returning an SQL error.
 */
function shop_schema_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    global $pdo;
    if (!($pdo instanceof PDO)) return $ready = false;
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach ($pdo->query('PRAGMA table_info(stock)')->fetchAll() as $column) {
                if (strcasecmp((string)$column['name'], 'shop_id') === 0) return $ready = true;
            }
            return $ready = false;
        }
        $found = $pdo->query("SHOW COLUMNS FROM `stock` LIKE 'shop_id'");
        return $ready = (bool)($found && $found->fetch(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        return $ready = false;
    }
}

/** SQL fragment for a column reference, e.g. shop_sql('s') => "s.shop_id=1". */
function shop_sql(string $alias = ''): string {
    if ($alias !== '' && !preg_match('/^[a-z_][a-z0-9_]*$/i', $alias)) {
        throw new InvalidArgumentException('Invalid shop query alias.');
    }
    if (!shop_schema_ready()) throw new RuntimeException('Branch schema unavailable.');
    return ($alias !== '' ? $alias . '.' : '') . 'shop_id=' . shop_current_id();
}

/** The shop row, or null when it does not exist. */
function shop_find(int $shopId): ?array {
    global $pdo;
    if ($shopId <= 0 || !($pdo instanceof PDO)) return null;
    try {
        $q = $pdo->prepare('SELECT * FROM shops WHERE id=?');
        $q->execute([$shopId]);
        $row = $q->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/** All shops, newest last (Office 2 list). */
function shop_list(): array {
    global $pdo;
    try {
        return $pdo->query('SELECT * FROM shops ORDER BY id ASC')->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** True when the shop may be used: active account and a live subscription. */
function shop_is_operational(array $shop): bool {
    if (($shop['status'] ?? 'active') !== 'active') return false;
    if ((int)($shop['subscription_active'] ?? 1) !== 1) return false;
    $expires = trim((string)($shop['subscription_expires_at'] ?? ''));
    if ($expires !== '' && strtotime($expires) !== false && strtotime($expires) < time()) return false;
    return true;
}

/** Per-shop setting overrides, loaded once per request. */
function shop_settings(int $shopId = 0): array {
    $cache = &$GLOBALS['_shop_settings_cache'];
    if(!is_array($cache))$cache=[];
    $shopId = $shopId > 0 ? $shopId : shop_current_id();
    if (isset($cache[$shopId])) return $cache[$shopId];
    global $pdo;
    $rows = [];
    if ($pdo instanceof PDO) {
        try {
            $q = $pdo->prepare('SELECT setting_key, setting_value FROM shop_settings WHERE shop_id=?');
            $q->execute([$shopId]);
            foreach ($q->fetchAll() as $row) $rows[(string)$row['setting_key']] = $row['setting_value'];
        } catch (Throwable $e) {
            $rows = []; // table not created yet (before migration 004)
        }
    }
    return $cache[$shopId] = $rows;
}

/**
 * Override value for a setting, or null when this shop has no own value.
 * Used by get_setting() so shop #1 keeps reading the global settings table.
 */
function shop_setting_lookup(string $key): ?string {
    if(!function_exists('invoice_brand_key')||!invoice_brand_key($key))return null;
    $branch=shop_current_id();if($branch<1)return null;
    $values=shop_settings($branch);if(array_key_exists($key,$values))return (string)$values[$key];
    global $pdo;$q=$pdo->prepare('SELECT business_id FROM shops WHERE id=?');$q->execute([$branch]);$business=(int)$q->fetchColumn();
    if($business>0){$values=invoice_scope_settings('business',$business);if(array_key_exists($key,$values))return (string)$values[$key];if($key==='shop_name'){$q=$pdo->prepare('SELECT name FROM businesses WHERE id=?');$q->execute([$business]);return (string)$q->fetchColumn();}if(in_array($key,invoice_identity_keys(),true))return '';}
    return null;
}

/** Save (or clear) a per-shop override. Empty value removes the override. */
function shop_setting_save(int $shopId, string $key, string $value): bool {
    global $pdo;
    if ($shopId <= 0 || !($pdo instanceof PDO)) return false;
    try {
        unset($GLOBALS['_shop_settings_cache']);
        if ($value === '') {
            $pdo->prepare('DELETE FROM shop_settings WHERE shop_id=? AND setting_key=?')->execute([$shopId, $key]);
            return true;
        }
        try {
            $pdo->prepare('INSERT INTO shop_settings (shop_id, setting_key, setting_value) VALUES (?,?,?)')->execute([$shopId, $key, $value]);
        } catch (Throwable $e) {
            $pdo->prepare('UPDATE shop_settings SET setting_value=? WHERE shop_id=? AND setting_key=?')->execute([$value, $shopId, $key]);
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** Choose the shop a central administrator works on (null = own shop). */
function shop_context_set(?int $shopId): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if ($shopId === null || $shopId <= 0) {
        unset($_SESSION['shop_context_id']);
        return;
    }
    $_SESSION['shop_context_id'] = $shopId;
}
