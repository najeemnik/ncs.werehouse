<?php
/**
 * Nik Smart Count - Business & Inventory Management System
 * Category Management Module
 * 100% Multilingual Isolated: English, Dari, Pashto
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

auth_require();
$currentUser = auth_user();
$currentLang = get_current_lang();
$currentDir  = get_current_dir();

$error = '';
$success = '';

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error, please resubmit form.', 'خطای امنیتی نشست، لطفاً مجدداً ارسال نمایید.', 'د ناستې امنیتي تېروتنه.');
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $error = __t('Category name cannot be empty.', 'نام دسته بندی نمی تواند خالی باشد.', 'د کټګورۍ نوم نشي خالي کېدای.');
        } else {
            $chk = $pdo->prepare("SELECT id FROM categories WHERE name = :name AND " . shop_sql());
            $chk->execute([':name' => $name]);
            if ($chk->fetch()) {
                $error = __t('A category with this name already exists.', 'دسته بندی با این نام قبلاً ایجاد شده است.', 'په دې نوم بله کټګوري شته.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description, is_sample, shop_id) VALUES (:name, :desc, 0, " . shop_id() . ")");
                $stmt->execute([':name' => $name, ':desc' => $description]);
                log_activity($currentUser['id'], 'افزودن دسته', "ایجاد دسته بندی جدید «{$name}»");
                set_flash(sprintf(__t('Category «%s» created successfully.', 'دسته بندی «%s» با موفقیت افزوده شد.', 'کټګوري «%s» په بریالیتوب اضافه شوه.'), $name), 'success');
                redirect(BASE_URL . 'modules/categories.php');
            }
        }
    }
}

// Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!');
    } else {
        $catId = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name) || $catId <= 0) {
            $error = __t('Invalid category details provided.', 'اطلاعات ارسالی نامعتبر است.', 'ناسم معلومات.');
        } else {
            $chk = $pdo->prepare("SELECT id FROM categories WHERE name = :name AND id != :id AND " . shop_sql());
            $chk->execute([':name' => $name, ':id' => $catId]);
            if ($chk->fetch()) {
                $error = __t('Another category with this name already exists.', 'دسته بندی دیگری با این نام وجود دارد.', 'په دې نوم بله کټګوري موجوده ده.');
            } else {
                $stmt = $pdo->prepare("UPDATE categories SET name = :name, description = :desc WHERE id = :id AND " . shop_sql());
                $stmt->execute([':name' => $name, ':desc' => $description, ':id' => $catId]);
                log_activity($currentUser['id'], 'ویرایش دسته', "ویرایش دسته بندی «{$name}» با شناسه {$catId}");
                set_flash(sprintf(__t('Category «%s» updated successfully.', 'دسته بندی «%s» با موفقیت ویرایش گردید.', 'کټګوري «%s» په بریالیتوب سم شوه.'), $name), 'success');
                redirect(BASE_URL . 'modules/categories.php');
            }
        }
    }
}

// Handle Delete Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = __t('Security session error!', 'خطای امنیتی نشست!', 'د ناستې امنیتي تېروتنه!');
    } else {
        $catId = (int)($_POST['id'] ?? 0);
        
        $pCountStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM stock WHERE category_id = :id AND " . shop_sql());
        $pCountStmt->execute([':id' => $catId]);
        $productCount = (int)($pCountStmt->fetch()['total'] ?? 0);

        if ($productCount > 0) {
            set_flash(sprintf(__t('This category contains %s inventory items. To delete it, reassign or delete its items first.', 'این دسته بندی دارای %s قلم جنس در گدام است. برای حذف آن، ابتدا اجناس مربوطه را به دسته دیگری انتقال دهید یا حذف نمایید.', 'دا کټګوري %s توکي لري. لومړۍ یې توکي لرې کړئ.'), ($currentLang === 'en' ? $productCount : to_persian_num($productCount))), 'warning');
        } else {
            $delStmt = $pdo->prepare("DELETE FROM categories WHERE id = :id AND " . shop_sql());
            $delStmt->execute([':id' => $catId]);
            log_activity($currentUser['id'], 'حذف دسته', "حذف دسته بندی با شناسه {$catId}");
            set_flash(__t('Category deleted successfully.', 'دسته بندی با موفقیت حذف گردید.', 'کټګوري په بریالیتوب ړنګه شوه.'), 'success');
        }
        redirect(BASE_URL . 'modules/categories.php');
    }
}

// Check if editing specific category
$editCat = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id AND " . shop_sql());
    $stmt->execute([':id' => $editId]);
    $editCat = $stmt->fetch();
}

// Fetch all categories with product counts
$categories = $pdo->query("SELECT c.*, COUNT(s.id) AS product_count 
                           FROM categories c 
                           LEFT JOIN stock s ON c.id = s.category_id 
                           WHERE " . shop_sql('c') . " 
                           GROUP BY c.id, c.name, c.description, c.is_sample, c.created_at, c.shop_id 
                           ORDER BY c.name ASC")->fetchAll();

$pageTitle = 'مدیریت دسته بندی های دکان';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <!-- Add or Edit Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php if ($editCat): ?>
                    <i class="fa-solid fa-pen-to-square ml-1"></i> <?= __t('Edit Category', 'ویرایش دسته بندی', 'کټګوري سمول') ?>
                <?php else: ?>
                    <i class="fa-solid fa-folder-plus ml-1"></i> <?= __t('Create New Category', 'ایجاد دسته بندی جدید', 'نوې کټګوري جوړول') ?>
                <?php endif; ?>
            </h3>
            <?php if ($editCat): ?>
                <a href="<?= BASE_URL ?>modules/categories.php" class="btn btn-sm btn-secondary"><?= __t('Cancel', 'انصراف', 'لغوه') ?></a>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editCat ? 'edit' : 'add' ?>">
            <?php if ($editCat): ?>
                <input type="hidden" name="id" value="<?= $editCat['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="name">
                    <?= __t('Category Name: *', 'نام دسته بندی: *', 'د کټګورۍ نوم: *') ?>
                </label>
                <input type="text" id="name" name="name" class="form-control" required 
                       value="<?= sanitize($editCat ? $editCat['name'] : '') ?>" 
                       placeholder="<?= __t('e.g. Groceries, Clothing, Tools...', 'مثلاً: مواد غذایی، پوشاک، ابزار...', 'لکه: خوراکي توکي، جامې، وسایل...') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="description"><?= __t('Description (Optional):', 'توضیحات (اختیاری):', 'تفصیل (اختیاري):') ?></label>
                <textarea id="description" name="description" class="form-control" rows="3" 
                          placeholder="<?= __t('Short description for this inventory section', 'توضیح کوتاه درباره این بخش', 'د دې کټګورۍ لنډ تفصیل') ?>"><?= sanitize($editCat ? $editCat['description'] : '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                <i class="fa-solid fa-floppy-disk ml-1"></i> <?= $editCat ? __t('Save Changes', 'ذخیره تغییرات', 'بدلونونه خوندي کړئ') : __t('Add Category', 'افزودن دسته بندی', 'کټګوري اضافه کړئ') ?>
            </button>
        </form>

        <div style="margin-top: 20px; font-size: 11.5px; color: var(--text-muted); line-height: 1.5; background: var(--surface-muted); padding: 10px; border-radius: 6px; border: 1px solid var(--border-color);">
            <i class="fa-solid fa-circle-info ml-1" style="color: var(--info);"></i>
            <?= __t('You have complete control over product categories and can customize, rename, or delete them to fit your store catalog.', 'شما کنترل کامل روی دسته بندی ها دارید و می توانید آنها را مطابق سلیقه و نیاز دکان خود تغییر نام داده، اضافه کرده یا حذف نمایید.', 'تاسو کولای شئ کټګورۍ د خپلې خوښې سره سم سمې، اضافه یا لرې کړئ.') ?>
        </div>
    </div>

    <!-- Categories List Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-tags ml-1"></i> <?= __t('Defined Store Categories List', 'لست دسته بندی های تعریف شده', 'د تعریف شویو کټګوریو لست') ?>
            </h3>
            <span class="badge badge-secondary"><?= $currentLang === 'en' ? count($categories) . ' categories' : to_persian_num(count($categories)) . ' دسته' ?></span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th><?= __t('Category Name', 'نام دسته بندی', 'د کټګورۍ نوم') ?></th>
                        <th><?= __t('Description', 'توضیحات', 'تفصیل') ?></th>
                        <th style="width: 110px; text-align: center;"><?= __t('Inventory Items', 'تعداد اجناس', 'د توکو شمېر') ?></th>
                        <th style="width: 90px; text-align: center;"><?= __t('Actions', 'عملیات', 'کړنې') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">
                                <?= __t('No categories registered yet.', 'هنوز هیچ دسته بندی ثبت نشده است.', 'تر اوسه هېڅ کټګوري نه ده ثبت شوې.') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $cNum = 1;
                        foreach ($categories as $cat): 
                        ?>
                            <tr>
                                <td><?= $currentLang === 'en' ? $cNum++ : to_persian_num($cNum++) ?></td>
                                <td>
                                    <strong><?= sanitize($cat['name']) ?></strong>
                                    <?php if (!empty($cat['is_sample'])): ?>
                                        <span class="badge badge-secondary" style="font-size: 10px; margin-right: 4px;"><?= __t('Sample', 'نمونه', 'بېلګه') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted);"><?= sanitize($cat['description'] ?: '-') ?></td>
                                <td style="text-align: center;">
                                    <a href="<?= BASE_URL ?>modules/stock.php?category_id=<?= $cat['id'] ?>" class="badge badge-primary" style="text-decoration: none;">
                                        <?= display_item_count($cat['product_count']) ?>
                                    </a>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <a href="<?= BASE_URL ?>modules/categories.php?edit=<?= $cat['id'] ?>" class="btn btn-sm btn-secondary" title="<?= __t('Edit', 'ویرایش', 'سمول') ?>">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <form method="POST" action="" style="display: inline-block;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(<?= sanitize(json_for_html(sprintf(__t('Are you sure you want to delete category «%s»?', 'آیا از حذف دسته بندی «%s» اطمینان دارید؟', 'ایا باوري یاست چې دا کټګوري ړنګه کړئ؟'), $cat['name']))) ?>);" title="<?= __t('Delete', 'حذف', 'ړنګول') ?>">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
