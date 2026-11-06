<?php
/**
 * Office 2 - shops register (Phase 4 / Stage B).
 *
 * One owner per shop, employees within the limit configured by Office 2,
 * subscription state, and per-shop invoice branding stored in shop_settings
 * (get_setting() prefers those values, so every print/UI keeps working).
 */

require_once __DIR__ . '/shop.php';

/** Shops with owner, counters and computed state. */
function office2_shop_rows(): array {
    global $pdo;
    try {
        $shops = $pdo->query('SELECT * FROM shops ORDER BY id ASC')->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    foreach ($shops as &$shop) {
        $id = (int)$shop['id'];
        $ownerId = (int)($shop['owner_user_id'] ?? 0);
        $shop['owner'] = null;
        if ($ownerId > 0) {
            try {
                $q = $pdo->prepare('SELECT id,username,fullname,status FROM users WHERE id=?');
                $q->execute([$ownerId]);
                $shop['owner'] = $q->fetch() ?: null;
            } catch (Throwable $e) {
                $shop['owner'] = null;
            }
        }
        $counters = [
            'accounts'  => ['SELECT COUNT(*) FROM users WHERE shop_id=?', [$id]],
            'employees' => ["SELECT COUNT(DISTINCT u.id) FROM branch_memberships m JOIN users u ON u.id=m.user_id WHERE m.shop_id=? AND u.role='staff'", [$id]],
            'stock'     => ['SELECT COUNT(*) FROM stock WHERE shop_id=?', [$id]],
            'sales'     => ['SELECT COUNT(*) FROM sales WHERE shop_id=?', [$id]],
        ];
        foreach ($counters as $key => [$sql, $args]) {
            try {
                $q = $pdo->prepare($sql);
                $q->execute($args);
                $shop[$key] = (int)$q->fetchColumn();
            } catch (Throwable $e) {
                $shop[$key] = 0;
            }
        }
        $shop['operational']       = shop_is_operational($shop);
        $shop['employees_allowed'] = (int)($shop['can_add_employees'] ?? 0) === 1;
        $shop['employee_cap']      = min(1000, max(0, (int)($shop['employee_limit'] ?? 0)));
        $shop['branding_count']    = count(shop_settings($id));
    }
    unset($shop);
    return $shops;
}

/** Validated shop row. */
function office2_shop_target($id): array {
    global $pdo;
    if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id < 1) {
        throw new InvalidArgumentException(__t('Choose a shop.','یک فروشگاه انتخاب کنید.','دوکان وټاکئ.'));
    }
    $q = $pdo->prepare('SELECT * FROM shops WHERE id=?');
    $q->execute([(int)$id]);
    $shop = $q->fetch();
    if (!$shop) throw new InvalidArgumentException(__t('Shop not found.','فروشگاه یافت نشد.','دوکان ونه موندل شو.'));
    return $shop;
}

/** The shop of the signed-in account, or 0 for a central Office 2 account. */
function office2_actor_shop(): int {
    global $pdo;
    try {
        $q = $pdo->prepare('SELECT shop_id FROM users WHERE id=?');
        $q->execute([(int)($_SESSION['user_id'] ?? 0)]);
        return (int)$q->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function office2_shop_username_free(string $username, int $ignoreId = 0): void {
    global $pdo;
    $q = $pdo->prepare('SELECT id FROM users WHERE LOWER(username)=LOWER(?) AND id<>?');
    $q->execute([$username, $ignoreId]);
    if ($q->fetchColumn()) throw new InvalidArgumentException(__t('Username already exists.','نام کاربری قبلاً ثبت شده است.','کارن نوم مخکې ثبت شوی.'));
}

/** Creates the account of a shop (owner or employee) inside the current transaction. */
function office2_shop_account(array $p, int $shopId, string $role): int {
    global $pdo;
    $username = office2_text($p, 'username', 50, true);
    $fullname = office2_text($p, 'owner_fullname', 100, true);
    if (!preg_match('/^[\pL\pN_.@-]{3,50}$/uD', $username)) {
        throw new InvalidArgumentException(__t('Use 3-50 letters, digits or . _ - @ for the username.','نام کاربری ۳ تا ۵۰ حرف/رقم یا . _ - @ باشد.','کارن نوم ۳ تر ۵۰ توري/عدد یا . _ - @ وي.'));
    }
    office2_shop_username_free($username);
    $password = $p['password'] ?? '';
    security_validate_new_password($password, $p['confirm_password'] ?? '');
    $pdo->prepare('INSERT INTO users (username,password,fullname,role,status,shop_id) VALUES (?,?,?,?,?,?)')
        ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullname, $role, 'active', $shopId]);
    $id = (int)$pdo->lastInsertId();
    access_ensure_profile($id);
    $pdo->prepare('UPDATE user_access SET must_change_password=1 WHERE user_id=?')->execute([$id]);
    return $id;
}

/** Re-encodes an uploaded logo to a clean PNG inside uploads/branding. */
function office2_shop_logo_upload(int $shopId, array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (($file['error'] ?? -1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || ($file['size'] ?? 0) > ui_upload_limit(3 * 1024 * 1024)) {
        throw new InvalidArgumentException(__t('Choose a PNG, JPEG or WebP image up to 3 MB.','تصویر PNG، JPEG یا WebP تا ۳ مگابایت انتخاب کنید.','تر ۳ مېګابایټه PNG، JPEG یا WebP انځور وټاکئ.'));
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!$info || !in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) || $info[0] > 4096 || $info[1] > 4096 || $info[0] * $info[1] > 12000000) {
        throw new InvalidArgumentException(__t('Unsupported or oversized image.','تصویر نامعتبر یا بیش از حد بزرگ است.','انځور ناسم یا ډېر لوی دی.'));
    }
    if (!function_exists('imagecreatefromstring')) {
        throw new RuntimeException(__t('The server needs the GD image extension to upload logos safely.','برای آپلود امن لوگو، افزونهٔ GD روی سرور لازم است.','د لوګو خوندي پورته کولو لپاره GD اړین دی.'));
    }
    $image = @imagecreatefromstring(file_get_contents($file['tmp_name']));
    if (!$image) throw new InvalidArgumentException(__t('Image could not be read.','تصویر قابل خواندن نیست.','انځور نه لوستل کېږي.'));
    $dir = BASE_DIR . '/uploads/branding';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException(__t('Logo storage is not writable.','محل ذخیرهٔ لوگو قابل نوشتن نیست.','د لوګو ځای ته لیکل نه کېږي.'));
    }
        ui_protect_logo_directory($dir);
    $filename = 'shop-' . $shopId . '-' . bin2hex(random_bytes(12)) . '.png';
    $target = $dir . '/' . $filename;
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $ok = imagepng($image, $target, 6);
    imagedestroy($image);
    if (!$ok) throw new RuntimeException(__t('Logo could not be saved.','لوگو ذخیره نشد.','لوګو خوندي نه شو.'));
    chmod($target, 0644);
    return 'branding/' . $filename;
}

/** Saves per-shop invoice branding; empty values fall back to the global studio. */
function office2_shop_branding_save(array $shop, array $p, array $files): int {
    $shopId = (int)$shop['id'];
    $enums = [
        'invoice_logo_position'      => ['right', 'center', 'left', 'none'],
        'invoice_watermark_opacity'  => ['0.04', '0.07', '0.12', '0.18'],
        'invoice_watermark_size'     => ['small', 'medium', 'large'],
        'invoice_border_style'       => ['modern', 'classic', 'minimal'],
        'invoice_format'             => ['a5', 'thermal', 'doc'],
        'auto_print_invoice'         => ['0', '1'],
    ];
    $saved = 0;
    foreach (office2_invoice_fields() as $key) {
        if (!isset($p[$key])) continue;
        $limit = in_array($key, ['invoice_footer_note', 'invoice_thank_you', 'shop_address_central', 'shop_address_secondary'], true) ? 2000 : 255;
        $value = office2_text($p, $key, $limit, $key === 'shop_name');
        if (isset($enums[$key]) && !in_array($value, $enums[$key], true)) throw new InvalidArgumentException('Invalid invoice option.');
        if ($key === 'invoice_accent_color' && $value !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/D', $value)) throw new InvalidArgumentException('Invalid invoice color.');
        if (!shop_setting_save($shopId, $key, $value)) throw new RuntimeException('Shop setting could not be saved.');
        $saved++;
    }
    foreach (office2_invoice_toggles() as $key) {
        if (!shop_setting_save($shopId, $key, (string)office2_bool($p, $key))) throw new RuntimeException('Shop setting could not be saved.');
        $saved++;
    }
    if (!empty($p['remove_logo'])) {
        shop_setting_save($shopId, 'shop_logo', '');
        $saved++;
    }
    if (isset($files['shop_logo'])) {
        $path = office2_shop_logo_upload($shopId, $files['shop_logo']);
        if ($path !== '') {
            if (!shop_setting_save($shopId, 'shop_logo', $path)) throw new RuntimeException('Logo reference could not be saved.');
            $saved++;
        }
    }
    return $saved;
}

/** All shop actions of Office 2. Runs inside the caller's transaction. */
function office2_shop_action(string $action, array $p, array $files, array $actor): array {
    global $pdo;
    if (!shop_schema_ready()) {
        throw new RuntimeException(__t('The multi-shop migration (004) has not run yet. Reload the dashboard once and try again.','مایگریشن چند فروشگاهی (۰۰۴) هنوز اجرا نشده است؛ یک بار داشبورد را باز کنید و دوباره تلاش کنید.','د څو دوکان مایګریشن (۰۰۴) لا نه دی اجرا شوی؛ یو ځل ډشبورډ پرانیزئ او بیا هڅه وکړئ.'));
    }

    switch ($action) {
        case 'create_shop':
            $name = office2_text($p, 'shop_name', 150, true);
            $days = office2_text($p, 'days', 4);
            if ($days === '') $days = '30';
            if (!ctype_digit($days) || (int)$days < 1 || (int)$days > 3650) {
                throw new InvalidArgumentException(__t('The subscription period must be 1-3650 days.','مدت اشتراک باید بین ۱ و ۳۶۵۰ روز باشد.','د ګډون موده له ۱ تر ۳۶۵۰ ورځو وي.'));
            }
            $expires = (new DateTimeImmutable('today'))->modify('+' . (int)$days . ' days')->format('Y-m-d') . ' 23:59:59';
            $pdo->prepare('INSERT INTO shops (name,owner_user_id,slug,status,subscription_active,subscription_expires_at,employee_limit,can_add_employees,created_by) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$name, null, '', 'active', 1, $expires, 0, 0, (int)$actor['id']]);
            $shopId = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE shops SET slug=? WHERE id=?')->execute(['shop-' . $shopId, $shopId]);
            $ownerId = office2_shop_account($p, $shopId, 'admin');
            $pdo->prepare('INSERT INTO businesses (name,owner_user_id) VALUES (?,?)')->execute([$name,$ownerId]);$businessId=(int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE shops SET owner_user_id=?,business_id=? WHERE id=?')->execute([$ownerId,$businessId,$shopId]);
            $pdo->prepare('INSERT INTO branch_memberships (user_id,shop_id) VALUES (?,?)')->execute([$ownerId,$shopId]);
            office2_audit('create_shop', $ownerId, ['shop_id' => $shopId, 'shop_name' => $name, 'expires' => $expires, 'starts_empty' => true]);
            return ['user_id' => $ownerId, 'shop_id' => $shopId];

        case 'create_shop_employee':
            $shop = office2_shop_target($p['shop_id'] ?? '');
            $shopId = (int)$shop['id'];
            if (!(int)$shop['can_add_employees']) {
                throw new InvalidArgumentException(__t('Office 2 has not allowed employees for this shop.','دفتر دو اجازهٔ کارمند برای این فروشگاه نداده است.','دفتر دو دې دوکان ته د کارکوونکو اجازه نه ده ورکړې.'));
            }
            $cap = min(1000, max(0, (int)$shop['employee_limit']));
            if (domain_branch_employee_count($shopId) >= $cap) {
                throw new InvalidArgumentException(__t('The employee limit of this shop is full.','سقف کارمندان این فروشگاه پر شده است.','د دې دوکان د کارکوونکو حد ډک شوی.'));
            }
            $employeeId = office2_shop_account($p, $shopId, 'staff');
            $pdo->prepare('INSERT INTO branch_memberships (user_id,shop_id) VALUES (?,?)')->execute([$employeeId,$shopId]);
            office2_audit('create_shop_employee', $employeeId, ['shop_id' => $shopId, 'cap' => $cap]);
            return ['user_id' => $employeeId, 'shop_id' => $shopId];

        case 'shop_state':
            $shop = office2_shop_target($p['shop_id'] ?? '');
            $status = office2_text($p, 'status', 20, true);
            if (!in_array($status, ['active', 'locked'], true)) throw new InvalidArgumentException(__t('Invalid shop status.','وضعیت فروشگاه نامعتبر است.','د دوکان حالت ناسم دی.'));
            $pdo->prepare('UPDATE shops SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$status, (int)$shop['id']]);
            office2_audit('shop_state', 0, ['shop_id' => (int)$shop['id'], 'status' => $status]);
            return ['user_id' => 0, 'shop_id' => (int)$shop['id']];

        case 'shop_subscription':
            $shop = office2_shop_target($p['shop_id'] ?? '');
            $active = office2_bool($p, 'subscription_active');
            $date = office2_text($p, 'expires_at', 10);
            $expires = null;
            if ($date !== '') {
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                    throw new InvalidArgumentException(__t('Invalid expiration date.','تاریخ انقضا نامعتبر است.','د پای نېټه ناسمه ده.'));
                }
                $expires = $date . ' 23:59:59';
            }
            $pdo->prepare('UPDATE shops SET subscription_active=?,subscription_expires_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$active, $expires, (int)$shop['id']]);
            office2_audit('shop_subscription', 0, ['shop_id' => (int)$shop['id'], 'active' => $active, 'expires' => $expires]);
            return ['user_id' => 0, 'shop_id' => (int)$shop['id']];

        case 'shop_renew':
            $shop = office2_shop_target($p['shop_id'] ?? '');
            $days = office2_text($p, 'days', 4, true);
            if (!ctype_digit($days) || (int)$days < 1 || (int)$days > 3650) {
                throw new InvalidArgumentException(__t('The renewal period must be 1-3650 days.','مدت تمدید باید بین ۱ و ۳۶۵۰ روز باشد.','د تمدید موده له ۱ تر ۳۶۵۰ ورځو وي.'));
            }
            $prior = (string)($shop['subscription_expires_at'] ?? '');
            $start = new DateTimeImmutable($prior !== '' && strcmp($prior, date('Y-m-d H:i:s')) > 0 ? $prior : 'today');
            $expires = $start->modify('+' . (int)$days . ' days')->format('Y-m-d') . ' 23:59:59';
            $pdo->prepare('UPDATE shops SET subscription_active=1,subscription_expires_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$expires, (int)$shop['id']]);
            office2_audit('shop_renew', 0, ['shop_id' => (int)$shop['id'], 'days' => (int)$days, 'expires' => $expires]);
            return ['user_id' => 0, 'shop_id' => (int)$shop['id']];

        case 'shop_employees':
            $shop = office2_shop_target($p['shop_id'] ?? '');
            $allowed = office2_bool($p, 'can_add_employees');
            $limit = office2_text($p, 'employee_limit', 4, true);
            if (!ctype_digit($limit) || (int)$limit > 1000) {
                throw new InvalidArgumentException(__t('The employee limit is between 0 and 1000.','حد کارمندان بین ۰ و ۱۰۰۰ است.','د کارکوونکو حد له ۰ تر ۱۰۰۰ دی.'));
            }
            $pdo->prepare('UPDATE shops SET can_add_employees=?,employee_limit=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$allowed, (int)$limit, (int)$shop['id']]);
            office2_audit('shop_employees', 0, ['shop_id' => (int)$shop['id'], 'allowed' => $allowed, 'limit' => (int)$limit]);
            return ['user_id' => 0, 'shop_id' => (int)$shop['id']];

        case 'save_shop_features':
            $shop = office2_shop_target($p['shop_id'] ?? '');
            $features = $p['features'] ?? [];
            if (!is_array($features) || count($features) > 200) throw new InvalidArgumentException(__t('Invalid feature selection.','انتخاب قابلیت نامعتبر است.','د امکاناتو ټاکنه ناسمه ده.'));
            $catalog = get_lockable_features();
            $saved = 0;
            foreach ($features as $key => $state) {
                $key = (string)$key;
                if (!isset($catalog[$key])) continue;
                if (!in_array($state, ['inherit', '1', '0'], true)) continue;
                if ($state === 'inherit') {
                    $pdo->prepare('DELETE FROM shop_feature_access WHERE shop_id=? AND feature_key=?')->execute([(int)$shop['id'], $key]);
                } else {
                    try {
                        $pdo->prepare('INSERT INTO shop_feature_access (shop_id,feature_key,allowed) VALUES (?,?,?)')->execute([(int)$shop['id'], $key, (int)$state]);
                    } catch (Throwable $e) {
                        $pdo->prepare('UPDATE shop_feature_access SET allowed=?,updated_at=CURRENT_TIMESTAMP WHERE shop_id=? AND feature_key=?')->execute([(int)$state, (int)$shop['id'], $key]);
                    }
                }
                domain_legacy_policy('branch',(int)$shop['id'],$key,$state);
                $saved++;
            }
            office2_audit('save_shop_features', 0, ['shop_id' => (int)$shop['id'], 'features' => $saved]);
            return ['user_id' => 0, 'shop_id' => (int)$shop['id']];

        case 'save_shop_branding':
            $shop = office2_shop_target($p['shop_id'] ?? '');
            $saved = office2_shop_branding_save($shop, $p, $files);
            office2_audit('save_shop_branding', 0, ['shop_id' => (int)$shop['id'], 'fields' => $saved]);
            return ['user_id' => 0, 'shop_id' => (int)$shop['id']];

        case 'open_shop':
            if (office2_actor_shop() > 0) {
                throw new InvalidArgumentException(__t('Only a central Office 2 account can open another shop.','فقط حساب مرکزی دفتر دو می‌تواند فروشگاه دیگر را باز کند.','یوازې مرکزي دفتر دو حساب بل دوکان پرانیستلی شي.'));
            }
            $shop = office2_shop_target($p['shop_id'] ?? '');
            shop_context_set((int)$shop['id']);
            office2_audit('open_shop', 0, ['shop_id' => (int)$shop['id']]);
            return ['user_id' => (int)$actor['id'], 'shop_id' => (int)$shop['id'], 'redirect' => BASE_URL . 'index.php'];

        case 'leave_shop':
            shop_context_set(null);
            office2_audit('leave_shop', 0, ['default_shop' => 1]);
            return ['user_id' => (int)$actor['id'], 'shop_id' => 0, 'redirect' => office2_url() . '?tab=shops'];
    }

    throw new InvalidArgumentException(__t('Unknown shop action.','عملیات فروشگاه نامعتبر است.','د دوکان عمل ناسم دی.'));
}
