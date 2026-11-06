<?php
/**
 * Office 2 - Shops & branding tab.
 * Expects: $shops (office2_shop_rows()), $activeShop (shop_current_id()), $error.
 */
$shops       = $shops ?? [];
$activeShop  = (int)($activeShop ?? 1);
$contextShop = (int)($_SESSION['shop_context_id'] ?? 0);
$shopFieldLabel = static function (string $key): array {
    $map = [
        'shop_name'                     => ['Business name', 'نام کسب‌وکار'],
        'invoice_title'                 => ['Invoice title', 'عنوان بل'],
        'shop_phone_central'            => ['Main phone', 'تلفن اصلی'],
        'shop_phone_secondary'          => ['Secondary phone', 'تلفن دوم'],
        'shop_phone_whatsapp'           => ['WhatsApp', 'واتساپ'],
        'shop_address_central'          => ['Main address', 'آدرس اصلی'],
        'shop_address_secondary'        => ['Secondary address', 'آدرس دوم'],
        'invoice_footer_note'           => ['Invoice terms', 'شرایط بل'],
        'invoice_thank_you'             => ['Thank-you message', 'پیام تشکر'],
        'invoice_signature_shop_text'   => ['Shop signature caption', 'عنوان امضای فروشگاه'],
        'invoice_signature_cust_text'   => ['Customer signature caption', 'عنوان امضای مشتری'],
    ];
    return $map[$key] ?? [$key, $key];
};
?>
<section class="card">
    <div class="card-header">
        <div>
            <h2><?= o2_label('Shops','فروشگاه‌ها','دوکانونه') ?></h2>
            <p class="office2-muted"><?= o2_label('Every shop starts empty, with one owner account and its own data. Employees are allowed only when this page grants them, within the configured branch limit.','هر فروشگاه از صفر شروع می‌شود؛ یک حساب مالک و دادهٔ جداگانه دارد. کارمند فقط وقتی این صفحه اجازه بدهد، مطابق حد تعیین‌شدهٔ شعبه.','هر دوکان له صفر پیل کېږي؛ یو مالک حساب او جلا معلومات لري. کارکوونکي یوازې کله چې دا پاڼه اجازه ورکړي، تر ټاکلي حد پورې.') ?></p>
        </div>
        <?php if ($contextShop > 0): ?>
            <form method="post" class="office2-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="leave_shop">
                <button class="btn btn-secondary" type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i> <?= o2_label('Return to my own shop','بازگشت به فروشگاه خودم','زما دوکان ته ستنیدل') ?></button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($contextShop > 0): ?>
        <div class="alert alert-warning"><?= o2_label('You are currently working inside shop','اکنون داخل این فروشگاه کار می‌کنید:','اوس په دې دوکان کې کار کوئ:') ?> <strong>#<?= $contextShop ?></strong></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table office2-table">
            <thead><tr>
                <th>#</th>
                <th><?= o2_label('Shop','فروشگاه','دوکان') ?></th>
                <th><?= o2_label('Owner','مالک','مالک') ?></th>
                <th><?= o2_label('State','وضعیت','حالت') ?></th>
                <th><?= o2_label('Subscription','اشتراک','ګډون') ?></th>
                <th><?= o2_label('Employees','کارمندان','کارکوونکي') ?></th>
                <th><?= o2_label('Data','داده','معلومات') ?></th>
                <th><?= o2_label('Actions','عملیات','عملونه') ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($shops as $shop): $sid = (int)$shop['id']; ?>
                <tr>
                    <td><?= $sid ?></td>
                    <td><strong><?= sanitize($shop['name']) ?></strong><?php if ($sid === $activeShop): ?><br><small class="office2-muted"><?= o2_label('current','جاری','اوسنی') ?></small><?php endif; ?></td>
                    <td><?php if ($shop['owner']): ?><?= sanitize($shop['owner']['fullname']) ?><br><small class="office2-muted"><?= sanitize($shop['owner']['username']) ?></small><?php else: ?>—<?php endif; ?></td>
                    <td><span class="badge <?= $shop['operational'] ? 'badge-success' : 'badge-danger' ?>"><?= $shop['operational'] ? o2_label('Active','فعال','فعال') : o2_label('Locked / expired','قفل یا منقضی','قفل یا پای') ?></span></td>
                    <td><?= (int)$shop['subscription_active'] === 1 ? o2_label('On','روشن','روښان') : o2_label('Off','خاموش','مړ') ?><br><small class="office2-muted"><?= $shop['subscription_expires_at'] ? sanitize(substr((string)$shop['subscription_expires_at'], 0, 10)) : o2_label('no expiry','بدون انقضا','بې پای') ?></small></td>
                    <td><?= (int)$shop['employees'] ?> / <?= (int)$shop['employee_cap'] ?><?= $shop['employees_allowed'] ? '' : ' ⛔' ?></td>
                    <td><small><?= (int)$shop['stock'] ?> <?= o2_label('items','جنس','توکي') ?> · <?= (int)$shop['sales'] ?> <?= o2_label('invoices','بل','بلونه') ?></small></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <form method="post" class="office2-form"><?= csrf_field() ?><input type="hidden" name="action" value="open_shop"><input type="hidden" name="shop_id" value="<?= $sid ?>"><button class="btn btn-sm btn-primary" type="submit"><?= o2_label('Open','باز کردن','پرانیستل') ?></button></form>
                            <form method="post" class="office2-form"><?= csrf_field() ?><input type="hidden" name="action" value="shop_state"><input type="hidden" name="shop_id" value="<?= $sid ?>"><input type="hidden" name="status" value="<?= (string)$shop['status'] === 'active' ? 'locked' : 'active' ?>"><button class="btn btn-sm btn-secondary" type="submit"><?= (string)$shop['status'] === 'active' ? o2_label('Lock','قفل','قفل') : o2_label('Activate','فعال‌سازی','فعالول') ?></button></form>
                            <form method="post" class="office2-form"><?= csrf_field() ?><input type="hidden" name="action" value="shop_renew"><input type="hidden" name="shop_id" value="<?= $sid ?>"><input type="hidden" name="days" value="30"><button class="btn btn-sm btn-secondary" type="submit">+30 <?= o2_label('days','روز','ورځې') ?></button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$shops): ?><tr><td colspan="8"><?= o2_label('No shop is registered yet.','هنوز فروشگاهی ثبت نشده است.','تر اوسه دوکان نه دی ثبت شوی.') ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2><?= o2_label('Create a shop','ساخت فروشگاه','دوکان جوړول') ?></h2>
    <p class="office2-muted"><?= o2_label('The owner account starts empty: no products, sales, debts or capital. The owner must change the temporary password at first sign-in.','حساب مالک از صفر شروع می‌شود: بدون جنس، فروش، قرض یا سرمایه. مالک باید در اولین ورود رمز موقت را تغییر دهد.','د مالک حساب له صفر پیل کېږي: بې توکو، پلور، پور یا پانګې. مالک باید په لومړي ننوت کې لنډمهالی رمز بدل کړي.') ?></p>
    <form method="post" class="office2-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="create_shop">
        <div class="office2-grid">
            <?php o2_input('shop_name', o2_label('Shop name','نام فروشگاه','د دوکان نوم'), '', 'text', true); ?>
            <?php o2_input('owner_fullname', o2_label('Owner full name','نام کامل مالک','د مالک بشپړ نوم'), '', 'text', true); ?>
            <?php o2_input('username', o2_label('Owner username','نام کاربری مالک','د مالک کارن نوم'), '', 'text', true); ?>
            <?php o2_input('days', o2_label('Subscription days (default 30)','روزهای اشتراک (پیش‌فرض ۳۰)','د ګډون ورځې (۳۰)'), '30', 'number'); ?>
            <?php o2_input('password', o2_label('Temporary password','رمز موقت','لنډمهالی رمز'), '', 'password', true); ?>
            <?php o2_input('confirm_password', o2_label('Repeat temporary password','تکرار رمز موقت','لنډمهالی رمز بیا'), '', 'password', true); ?>
        </div>
        <?php o2_reauth(); ?>
        <?php o2_submit(o2_label('Create shop','ساخت فروشگاه','دوکان جوړ کړئ')); ?>
    </form>
</section>

<?php foreach ($shops as $shop): $sid = (int)$shop['id']; $overrides = shop_settings($sid); $effectiveBrand=invoice_effective_brand($sid); ?>
<details class="card">
    <summary><strong>#<?= $sid ?> — <?= sanitize($shop['name']) ?></strong> <small class="office2-muted"><?= o2_label('subscription, employees and invoice branding','اشتراک، کارمندان و هویت بل','ګډون، کارکوونکي او د بل برانډ') ?></small></summary>

    <div class="office2-grid">
        <section>
            <h3><?= o2_label('Subscription','اشتراک','ګډون') ?></h3>
            <form method="post" class="office2-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="shop_subscription"><input type="hidden" name="shop_id" value="<?= $sid ?>">
                <?php o2_check('subscription_active', o2_label('Subscription is active','اشتراک فعال است','ګډون فعال دی'), (int)$shop['subscription_active'] === 1); ?>
                <?php o2_input('expires_at', o2_label('Expires on','تاریخ انقضا','د پای نېټه'), $shop['subscription_expires_at'] ? substr((string)$shop['subscription_expires_at'], 0, 10) : '', 'date'); ?>
                <?php o2_submit(); ?>
            </form>
            <form method="post" class="office2-form" style="margin-top:8px">
                <?= csrf_field() ?><input type="hidden" name="action" value="shop_renew"><input type="hidden" name="shop_id" value="<?= $sid ?>">
                <?php o2_input('days', o2_label('Renew for days','تمدید به روز','د ورځو تمدید'), '30', 'number', true); ?>
                <?php o2_submit(o2_label('Renew','تمدید','تمدید')); ?>
            </form>
        </section>

        <section>
            <h3><?= o2_label('Employees','کارمندان','کارکوونکي') ?></h3>
            <form method="post" class="office2-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="shop_employees"><input type="hidden" name="shop_id" value="<?= $sid ?>">
                <?php o2_check('can_add_employees', o2_label('Allow employees for this shop','اجازهٔ کارمند برای این فروشگاه','دې دوکان ته د کارکوونکو اجازه'), $shop['employees_allowed']); ?>
                <?php o2_input('employee_limit', o2_label('Employee limit (0–1000)','حد کارمندان (۰ تا ۱۰۰۰)','د کارکوونکو حد (۰–۱۰۰۰)'), (string)$shop['employee_cap'], 'number', true); ?>
                <?php o2_submit(); ?>
            </form>
            <?php if ($shop['employees_allowed']): ?>
                <form method="post" class="office2-form" style="margin-top:8px">
                    <?= csrf_field() ?><input type="hidden" name="action" value="create_shop_employee"><input type="hidden" name="shop_id" value="<?= $sid ?>">
                    <?php o2_input('owner_fullname', o2_label('Employee full name','نام کامل کارمند','د کارکوونکي بشپړ نوم'), '', 'text', true); ?>
                    <?php o2_input('username', o2_label('Employee username','نام کاربری کارمند','د کارکوونکي کارن نوم'), '', 'text', true); ?>
                    <?php o2_input('password', o2_label('Temporary password','رمز موقت','لنډمهالی رمز'), '', 'password', true); ?>
                    <?php o2_input('confirm_password', o2_label('Repeat temporary password','تکرار رمز موقت','لنډمهالی رمز بیا'), '', 'password', true); ?>
                    <?php o2_reauth(); ?>
                    <?php o2_submit(o2_label('Create employee','ساخت کارمند','کارکوونکی جوړ کړئ')); ?>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <h3><?= o2_label('Feature permissions of this shop','مجوز قابلیت‌های این فروشگاه','د دې دوکان د امکاناتو اجازه') ?></h3>
    <p class="office2-muted"><?= o2_label('Inherit uses the global licence and each user own permission. Allowed is an explicit permission for this shop, but it never overrides the global licence switch or a user personal restriction. Denied blocks the feature for this shop only.','«تلقّی از عمومی» از لایسنس عمومی و مجوز شخصی هر کاربر استفاده می‌کند؛ «مجاز» اجازهٔ صریح برای همین فروشگاه است اما هرگز قفل عمومی لایسنس یا محدودیت شخصی کاربر را باطل نمی‌کند؛ «مسدود» قابلیت را فقط برای همین فروشگاه می‌بندد.','«عمومي» د عمومي لایسنس او د هر کارن د خپلې اجازې کار اخلي؛ «اجازه» د دې دوکان لپاره صریحه اجازه ده خو عمومي لایسنس یا د کارن شخصي بندیز نه لرې کوي؛ «بند» امکان یوازې دې دوکان ته بندوي.') ?></p>
    <form method="post" class="office2-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_shop_features"><input type="hidden" name="shop_id" value="<?= $sid ?>">
        <div class="office2-policy-groups"><?php $shopPermissionGroups=[];foreach(get_lockable_features() as $fkey=>$fmeta)$shopPermissionGroups[explode('.',domain_legacy_feature($fkey))[0]][$fkey]=$fmeta;foreach($shopPermissionGroups as $module=>$features): ?>
        <details class="office2-policy-group" <?= $module==='dashboard'?'open':'' ?>><summary><span><i class="fa-solid fa-folder-open"></i> <?= sanitize(domain_label($module)) ?></span><span class="office2-badge"><?= count($features) ?></span></summary>
        <div class="table-responsive"><table class="table office2-table"><thead><tr><th><?= o2_label('Feature','قابلیت','امکان') ?></th><th><?= o2_label('State for this shop','وضعیت این فروشگاه','د دې دوکان حالت') ?></th></tr></thead><tbody>
        <?php foreach($features as $fkey=>$fmeta):$state=shop_feature_state($fkey,$sid); ?><tr><td><?= sanitize((string)($fmeta['title_fa']??$fkey)) ?><br><small class="office2-muted"><?= sanitize($fkey) ?></small></td><td><select class="form-control" name="features[<?= sanitize($fkey) ?>]"><option value="inherit" <?= $state===null?'selected':'' ?>><?= o2_label('Inherit global','تلقّی از عمومی','عمومي') ?></option><option value="1" <?= $state===1?'selected':'' ?>><?= o2_label('Allowed','مجاز','اجازه') ?></option><option value="0" <?= $state===0?'selected':'' ?>><?= o2_label('Denied','مسدود','بند') ?></option></select></td></tr><?php endforeach; ?>
        </tbody></table></div></details><?php endforeach; ?></div>
        <?php o2_submit(o2_label('Save feature permissions','ذخیرهٔ مجوز قابلیت‌ها','د امکاناتو اجازه خوندي کړئ')); ?>
    </form>

    <h3><?= o2_label('Invoice branding of this shop','هویت بل این فروشگاه','د دې دوکان د بل برانډ') ?></h3>
    <p class="office2-muted"><?= o2_label('Empty fields inherit the business design. Saved values apply to this branch only.','خانهٔ خالی از دیزاین تجارت استفاده می‌کند؛ مقدار ذخیره‌شده فقط برای همین شعبه است.','تشه خانه عمومي سټوډیو کاروي؛ ساتل شوی ارزښت یوازې دې دوکان ته دی.') ?></p>
    <form method="post" enctype="multipart/form-data" class="office2-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_shop_branding"><input type="hidden" name="shop_id" value="<?= $sid ?>">
        <?php
        $brandValue = static function (string $key, string $default = '') use ($overrides,$effectiveBrand) {
            return array_key_exists($key, $overrides) ? (string)$overrides[$key] : ($effectiveBrand[$key]??$default);
        };
        ?>
        <div class="office2-grid">
            <?php
            foreach (['shop_name', 'invoice_title', 'shop_phone_central', 'shop_phone_secondary', 'shop_phone_whatsapp'] as $key) {
                o2_input($key, o2_label(...$shopFieldLabel($key)), $brandValue($key));
            }
            foreach (['shop_address_central', 'shop_address_secondary', 'invoice_footer_note', 'invoice_thank_you', 'invoice_signature_shop_text', 'invoice_signature_cust_text'] as $key) {
                o2_textarea($key, o2_label(...$shopFieldLabel($key)), $brandValue($key));
            }
            o2_select('invoice_logo_position', o2_label('Logo position','موقعیت لوگو'), ['right' => 'right', 'center' => 'center', 'left' => 'left', 'none' => 'none'], $brandValue('invoice_logo_position', 'right'));
            o2_select('invoice_format', o2_label('Print format','فرمت چاپ'), ['a5' => 'A5', 'thermal' => '80mm', 'doc' => 'Word'], $brandValue('invoice_format', 'a5'));
            o2_select('invoice_border_style', o2_label('Border style','استایل کادر'), ['modern' => 'modern', 'classic' => 'classic', 'minimal' => 'minimal'], $brandValue('invoice_border_style', 'modern'));
            o2_select('invoice_watermark_opacity', o2_label('Watermark opacity','شفافیت واترمارک'), ['0.04' => '4%', '0.07' => '7%', '0.12' => '12%', '0.18' => '18%'], $brandValue('invoice_watermark_opacity', '0.07'));
            o2_select('invoice_watermark_size', o2_label('Watermark size','اندازهٔ واترمارک'), ['small' => 'small', 'medium' => 'medium', 'large' => 'large'], $brandValue('invoice_watermark_size', 'medium'));
            o2_select('auto_print_invoice', o2_label('Automatic printing','چاپ خودکار'), ['0' => o2_label('Off','خاموش'), '1' => o2_label('On','روشن')], $brandValue('auto_print_invoice', '0'));
            o2_input('invoice_accent_color', o2_label('Invoice accent color','رنگ اصلی بل'), $brandValue('invoice_accent_color', '#0f172a'), 'color');
            ?>
        </div>
        <div class="office2-permission-grid">
            <?php
            $toggleLabels = [
                'invoice_show_phone_secondary'   => ['Show secondary phone', 'نمایش تلفن دوم'],
                'invoice_show_whatsapp'          => ['Show WhatsApp', 'نمایش واتساپ'],
                'invoice_show_address_secondary' => ['Show secondary address', 'نمایش آدرس دوم'],
                'invoice_show_terms'             => ['Show terms', 'نمایش شرایط'],
                'invoice_show_signatures'        => ['Show signatures', 'نمایش امضاها'],
                'invoice_show_barcode'           => ['Show QR code', 'نمایش کد QR'],
                'invoice_watermark_enabled'      => ['Enable watermark', 'فعال‌سازی واترمارک'],
            ];
            foreach ($toggleLabels as $key => $label) o2_check($key, o2_label(...$label), $brandValue($key, '1') === '1');
            ?>
        </div>
        <div class="office2-logo">
            <?php
            $shopLogo = (string)($overrides['shop_logo'] ?? '');
            if ($shopLogo !== '' && is_file(BASE_DIR . '/uploads/' . $shopLogo)) {
                echo '<img src="' . sanitize(BASE_URL . 'uploads/' . implode('/', array_map('rawurlencode', explode('/', $shopLogo)))) . '" alt="">';
            }
            ?>
            <label class="form-label" for="shopLogo<?= $sid ?>"><?= o2_label('Logo: PNG, JPEG or WebP, up to 3 MB','لوگو: PNG، JPEG یا WebP تا ۳ مگابایت') ?></label>
            <input type="file" class="form-control" name="shop_logo" id="shopLogo<?= $sid ?>" accept="image/png,image/jpeg,image/webp">
            <?php o2_check('remove_logo', o2_label('Remove this shop logo','حذف لوگوی این فروشگاه'), false); ?>
        </div>
        <div class="office2-actions">
            <a class="btn btn-secondary" href="<?= sanitize(BASE_URL . 'print/invoice.php?demo=1') ?>" target="_blank" rel="noopener"><?= o2_label('Preview invoice','پیش‌نمایش بل') ?></a>
            <button class="btn btn-primary" type="submit"><?= o2_label('Save shop branding','ذخیرهٔ هویت این فروشگاه','د دې دوکان برانډ خوندي کړئ') ?></button>
        </div>
    </form>
</details>
<?php endforeach; ?>
