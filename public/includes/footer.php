        </div> <!-- End app-content -->
    </main>
</div> <!-- End app-wrapper -->
<form method="POST" action="<?= BASE_URL ?>logout.php" id="accountLogoutForm" hidden><?= csrf_field() ?></form>

<!-- Universal Feature Lock Modal (Ahmad Najeem Nik Contact) -->
<div id="featureLockModal" class="feature-modal-backdrop" onclick="if(event.target === this) closeLockModal();">
    <div class="feature-modal-box">
        <button type="button" class="feature-modal-close" onclick="closeLockModal();" aria-label="Close">&times;</button>
        
        <div class="feature-modal-icon">
            <i class="fa-solid fa-lock"></i>
        </div>

        <h3 id="lockModalTitleFa" class="feature-modal-title"><?= __('feature_locked_title') ?></h3>
        <p id="lockModalSubtitle" class="feature-modal-subtitle"><?= __('feature_locked_desc') ?></p>

        <div class="feature-modal-en-box">
            <h5 id="lockModalTitleEn"><?= __('feature_locked_title') ?></h5>
            <p><?= __('feature_locked_desc') ?></p>
        </div>

        <div class="feature-modal-actions">
            <a id="lockModalWhatsappBtn" href="https://wa.me/93783788278?text=Hello%20Ahmad%20Najeem%20Nik,%20I%20would%20like%20to%20unlock%20this%20feature" target="_blank" class="btn-whatsapp-direct">
                <i class="fa-brands fa-whatsapp" style="font-size: 18px;"></i>
                <span><?= __('whatsapp_btn') ?></span>
            </a>
            <a href="tel:+93783788278" class="btn btn-secondary" style="font-size: 13px;">
                <i class="fa-solid fa-phone"></i> <?= __('phone_call_btn') ?>
            </a>
        </div>
    </div>
</div>

<script>
function showLockModal(key, titleEn, titleFa) {
    var modal = document.getElementById('featureLockModal');
    if (!modal) return;
    
    var tFa = document.getElementById('lockModalTitleFa');
    var tEn = document.getElementById('lockModalTitleEn');
    var waBtn = document.getElementById('lockModalWhatsappBtn');

    var currentLang = <?= json_encode(get_current_lang()) ?>;
    var lockedSuffix = (currentLang === 'en') ? ' (Locked)' : ((currentLang === 'ps') ? ' (بند دی)' : ' (قفل است)');
    var titleDisplay = (currentLang === 'en') ? (titleEn || key) : (titleFa || titleEn || key);

    if (tFa) tFa.textContent = titleDisplay + lockedSuffix;
    if (tEn) tEn.textContent = (currentLang === 'en') ? 'Licensed Feature' : ('Feature: ' + (titleEn || key));
    
    if (waBtn) {
        var query = encodeURIComponent("Hello Ahmad Najeem Nik, I would like to request unlocking the feature: " + (titleEn || key));
        waBtn.href = "https://wa.me/93783788278?text=" + query;
    }
    
    modal.style.display = 'flex';
}

function closeLockModal() {
    var modal = document.getElementById('featureLockModal');
    if (modal) modal.style.display = 'none';
}
</script>

<!-- Universal Screen Lock & Shopkeeper PIN System -->
<?php require_once __DIR__ . '/screen_lock_modal.php'; ?>

<script src="<?= BASE_URL ?>assets/js/currency.js?v=2.0"></script>
<!-- Main Application JS -->
<script src="<?= BASE_URL ?>assets/js/main.js?v=2.0"></script>
<!-- Brand motion, PWA installation and static-only service worker -->
<script src="<?= BASE_URL ?>assets/js/branding.js?v=<?= APP_VERSION ?>" data-base="<?= BASE_URL ?>"></script>
<script src="<?= BASE_URL ?>assets/js/tables.js?v=<?= APP_VERSION ?>" defer></script>
</body>
</html>

