<?php
/**
 * Nik Smart Count - Mobile Shop Management System
 * Universal Screen Lock & Custom PIN Component
 */

$isScreenLocked = (!empty($_SESSION['screen_locked']) || (isset($_GET['locked']) && $_GET['locked'] === '1'));
$lockPinEnabled = security_screen_enabled();
$lockShopName   = get_setting('shop_name', DEFAULT_SHOP_NAME);
$lockShopLogo   = get_setting('shop_logo', '');
?>

<!-- 1. FULLSCREEN SCREEN LOCK OVERLAY -->
<div id="nikxorScreenLockOverlay" class="nikxor-lock-overlay" style="display: <?= $isScreenLocked ? 'flex' : 'none' ?>;">
    <div class="nikxor-lock-card">
        <div class="nikxor-lock-header">
            <div class="nikxor-lock-badge">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h2 class="nikxor-lock-title"><?= sanitize(function_exists('get_shop_title') ? get_shop_title() : $lockShopName) ?></h2>
            <p class="nikxor-lock-desc">
                <?= $lockPinEnabled ? __('screen_locked_desc_pin') : __('screen_locked_desc_nopin') ?>
            </p>
        </div>

        <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>modules/login.php?locked=1" style="margin-bottom:12px"><?= __t('Unlock with account password','باز کردن با رمز حساب','د حساب په رمز خلاص کړئ') ?></a>
        <div id="nikxorLockError" class="nikxor-lock-error" style="display: none;"></div>

        <?php if ($lockPinEnabled): ?>
            <!-- PIN Code Display -->
            <div class="nikxor-pin-display-box" id="nikxorPinDisplayBox" onclick="document.getElementById('nikxorHiddenPinInput').focus();">
                <div class="pin-dot" id="pindot_1"></div>
                <div class="pin-dot" id="pindot_2"></div>
                <div class="pin-dot" id="pindot_3"></div>
                <div class="pin-dot" id="pindot_4"></div>
            </div>

            <!-- Hidden input for mobile keyboard / accessibility -->
            <input type="password" id="nikxorHiddenPinInput" inputmode="numeric" maxlength="12" style="position: absolute; opacity: 0; pointer-events: none;" autocomplete="off">

            <!-- Numeric Keypad -->
            <div class="nikxor-keypad">
                <button type="button" class="kp-btn" onclick="pressLockKey('1')"><?= to_persian_num('1') ?></button>
                <button type="button" class="kp-btn" onclick="pressLockKey('2')"><?= to_persian_num('2') ?></button>
                <button type="button" class="kp-btn" onclick="pressLockKey('3')"><?= to_persian_num('3') ?></button>
                <button type="button" class="kp-btn" onclick="pressLockKey('4')"><?= to_persian_num('4') ?></button>
                <button type="button" class="kp-btn" onclick="pressLockKey('5')"><?= to_persian_num('5') ?></button>
                <button type="button" class="kp-btn" onclick="pressLockKey('6')"><?= to_persian_num('6') ?></button>
                <button type="button" class="kp-btn" onclick="pressLockKey('7')"><?= to_persian_num('7') ?></button>
                <button type="button" class="kp-btn" onclick="pressLockKey('8')"><?= to_persian_num('8') ?></button>
                <button type="button" class="kp-btn" onclick="pressLockKey('9')"><?= to_persian_num('9') ?></button>
                <button type="button" class="kp-btn kp-action" onclick="clearLockPin()" title="<?= __('delete') ?>">
                    <i class="fa-solid fa-delete-left"></i>
                </button>
                <button type="button" class="kp-btn" onclick="pressLockKey('0')"><?= to_persian_num('0') ?></button>
                <button type="button" class="kp-btn kp-submit" onclick="submitUnlockPin()" id="btnSubmitLockPin" title="<?= __('confirm') ?>">
                    <i class="fa-solid fa-check"></i>
                </button>
            </div>
        <?php else: ?>
            <!-- Direct Unlock Button When PIN is disabled -->
            <div style="margin-top: 20px; margin-bottom: 20px;">
                <button type="button" onclick="submitUnlockPin(true)" class="btn btn-primary" style="width: 100%; padding: 14px 20px; font-size: 16px; font-weight: 800; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fa-solid fa-lock-open"></i>
                    <span><?= __('instant_unlock_btn') ?></span>
                </button>
            </div>
        <?php endif; ?>

        <div class="nikxor-lock-footer-links">
            <button type="button" onclick="openScreenLockSettingsModal();" class="link-btn-settings">
                <i class="fa-solid fa-gear ml-1"></i> <?= __('lock_settings_link') ?>
            </button>
        </div>
    </div>
</div>

<!-- 2. SCREEN LOCK SETTINGS MODAL (SHOPKEEPER CUSTOM PIN) -->
<div id="screenLockSettingsModal" class="feature-modal-backdrop" style="display: none; z-index: 1000001;" onclick="if(event.target === this) closeScreenLockSettingsModal();">
    <div class="feature-modal-box" style="max-width: 480px; width: 100%; text-align: <?= get_current_dir() === 'rtl' ? 'right' : 'left' ?>; direction: <?= get_current_dir() ?>; padding: 24px; border-radius: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-shield-keyhole text-primary"></i> <?= __('lock_settings_title') ?>
            </h3>
            <button type="button" onclick="closeScreenLockSettingsModal();" style="background: none; border: none; font-size: 20px; color: #64748b; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="<?= BASE_URL ?>includes/screen_lock_action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_screen_lock_settings">
            <input type="hidden" name="return_to" value="<?= sanitize($_SERVER['REQUEST_URI']??BASE_URL.'index.php') ?>">
            <div class="form-group"><label class="form-label" for="screenSettingsPassword"><?= __t('Current account password','رمز فعلی حساب','د حساب اوسنی رمز') ?></label><input type="password" data-sensitive class="form-control" id="screenSettingsPassword" name="current_password" autocomplete="current-password" required></div>

            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 13px; color: #166534; line-height: 1.6;">
                <i class="fa-solid fa-circle-check text-green-600 ml-1"></i>
                <?= __('lock_settings_help_text') ?>
            </div>

            <div class="form-group" style="margin-bottom: 16px; display: flex; align-items: center; gap: 10px; background: #f8fafc; padding: 12px 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <input type="checkbox" id="chk_screen_lock_enabled" name="screen_lock_enabled" value="1" <?= $lockPinEnabled ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer;">
                <label for="chk_screen_lock_enabled" style="margin-bottom: 0; font-weight: 700; font-size: 13.5px; color: #1e293b; cursor: pointer;">
                    <?= __('lock_settings_enable_label') ?>
                </label>
            </div>

            <div id="pinSettingsInputs" style="display: <?= $lockPinEnabled ? 'block' : 'none' ?>;">
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label" style="font-weight: 700; font-size: 13px;"><?= __('lock_settings_new_pin') ?></label>
                    <input type="password" name="new_pin" id="inp_new_lock_pin" class="form-control" placeholder="<?= __('lock_settings_new_pin_placeholder') ?>" style="direction: ltr; text-align: center; font-size: 18px; letter-spacing: 4px;">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight: 700; font-size: 13px;"><?= __('lock_settings_repeat_pin') ?></label>
                    <input type="password" name="confirm_pin" id="inp_confirm_lock_pin" class="form-control" placeholder="<?= __('lock_settings_repeat_pin_placeholder') ?>" style="direction: ltr; text-align: center; font-size: 18px; letter-spacing: 4px;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px;">
                <button type="button" onclick="closeScreenLockSettingsModal();" class="btn btn-secondary">
                    <?= __('cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check ml-1"></i> <?= __('save_settings') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Nik Smart Count screen-lock overlay styling */
.nikxor-lock-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    background: radial-gradient(circle at center, #1e293b, #090d16);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
}

body.screen-is-locked .app-wrapper {
    filter: blur(14px) grayscale(40%);
    pointer-events: none !important;
    user-select: none !important;
}

.nikxor-lock-card {
    background: rgba(15, 23, 42, 0.95);
    border: 1px solid rgba(251, 191, 36, 0.4);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8), 0 0 30px rgba(245, 158, 11, 0.15);
    border-radius: 20px;
    padding: 32px 28px;
    max-width: 400px;
    width: 100%;
    text-align: center;
    color: #f8fafc;
    animation: lockCardPop 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes lockCardPop {
    0% { transform: scale(0.92); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}

.nikxor-lock-badge {
    width: 68px;
    height: 68px;
    margin: 0 auto 16px;
    background: rgba(251, 191, 36, 0.15);
    border: 2px solid #fbbf24;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: #fbbf24;
    box-shadow: 0 0 20px rgba(251, 191, 36, 0.3);
}

.nikxor-lock-title {
    font-size: 19px;
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 6px 0;
}

.nikxor-lock-desc {
    font-size: 13px;
    color: #94a3b8;
    line-height: 1.6;
    margin: 0 0 16px 0;
}

.nikxor-lock-error {
    background: rgba(239, 68, 68, 0.2);
    border: 1px solid #ef4444;
    color: #fca5a5;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 12.5px;
    margin-bottom: 16px;
    animation: lockShake 0.4s ease-in-out;
}

@keyframes lockShake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-8px); }
    40%, 80% { transform: translateX(8px); }
}

.nikxor-pin-display-box {
    display: flex;
    justify-content: center;
    gap: 14px;
    margin-bottom: 20px;
    cursor: pointer;
}

.pin-dot {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2px solid #64748b;
    background: transparent;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.pin-dot.filled {
    border-color: #fbbf24;
    background: #fbbf24;
    box-shadow: 0 0 12px rgba(251, 191, 36, 0.7);
    transform: scale(1.15);
}

.nikxor-keypad {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 18px;
}

.kp-btn {
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #f8fafc;
    font-size: 20px;
    font-weight: 700;
    padding: 14px 0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.kp-btn:hover {
    background: rgba(51, 65, 85, 0.9);
    border-color: rgba(251, 191, 36, 0.4);
    transform: translateY(-2px);
}

.kp-btn:active {
    transform: scale(0.95);
    background: #fbbf24;
    color: #0f172a;
}

.kp-btn.kp-action {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border-color: rgba(239, 68, 68, 0.3);
}

.kp-btn.kp-action:hover {
    background: rgba(239, 68, 68, 0.3);
}

.kp-btn.kp-submit {
    background: #fbbf24;
    color: #0f172a;
    border-color: #f59e0b;
}

.kp-btn.kp-submit:hover {
    background: #f59e0b;
}

.nikxor-lock-footer-links {
    margin-top: 14px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 14px;
}

.link-btn-settings {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 12px;
    cursor: pointer;
    text-decoration: underline;
    transition: color 0.2s;
}

.link-btn-settings:hover {
    color: #fbbf24;
}
</style>

<script>
var currentEnteredPin = "";
var isPinLockEnabled = <?= $lockPinEnabled ? 'true' : 'false' ?>;

function updatePinDisplay() {
    for (var i = 1; i <= 4; i++) {
        var dot = document.getElementById('pindot_' + i);
        if (dot) {
            if (currentEnteredPin.length >= i) {
                dot.classList.add('filled');
            } else {
                dot.classList.remove('filled');
            }
        }
    }
}

function pressLockKey(num) {
    if (currentEnteredPin.length < 10) {
        currentEnteredPin += num;
        updatePinDisplay();
        hideLockError();
        // Auto-submit if 4 digits entered and PIN is 4 digits
        if (currentEnteredPin.length === 4) {
            setTimeout(function() {
                submitUnlockPin();
            }, 100);
        }
    }
}

function clearLockPin() {
    currentEnteredPin = currentEnteredPin.slice(0, -1);
    updatePinDisplay();
    hideLockError();
}

var MSG_INVALID_PIN = <?= json_encode(__('msg_invalid_pin')) ?>;
var MSG_ENTER_PIN = <?= json_encode(__('msg_enter_pin')) ?>;
var MSG_SERVER_ERROR = <?= json_encode(__('msg_server_error')) ?>;

function showLockError(msg) {
    var errBox = document.getElementById('nikxorLockError');
    if (errBox) {
        errBox.textContent = msg || MSG_INVALID_PIN;
        errBox.style.display = 'block';
    }
    var card = document.querySelector('.nikxor-lock-card');
    if (card) {
        card.style.animation = 'none';
        card.offsetHeight; // trigger reflow
        card.style.animation = 'lockShake 0.4s ease-in-out';
    }
}

function hideLockError() {
    var errBox = document.getElementById('nikxorLockError');
    if (errBox) {
        errBox.style.display = 'none';
    }
}

// Function to Lock Screen
function lockShopScreen() {
    var overlay = document.getElementById('nikxorScreenLockOverlay');
    if (overlay) {
        overlay.style.display = 'flex';
        document.body.classList.add('screen-is-locked');
        currentEnteredPin = "";
        updatePinDisplay();
        hideLockError();

        // Notify server session in background
        fetch('<?= BASE_URL ?>includes/screen_lock_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest','Accept':'application/json' },
            body: new URLSearchParams({action:'lock_screen',csrf_token:document.querySelector('meta[name="csrf-token"]')?.content||''})
        }).then(r=>r.json()).then(data=>{if(data.auth_required)location.href=data.login_url;else if(!data.success)showLockError(data.message||MSG_SERVER_ERROR);}).catch(function(){showLockError(MSG_SERVER_ERROR);});
    }
}

// Function to Submit Unlock
function submitUnlockPin(bypassPin) {
    if (!bypassPin && isPinLockEnabled && !currentEnteredPin) {
        showLockError(MSG_ENTER_PIN);
        return;
    }

    var submitBtn = document.getElementById('btnSubmitLockPin');
    if (submitBtn) submitBtn.disabled = true;

    var params = new URLSearchParams();
    params.append('action', 'verify_unlock');
    params.append('csrf_token',document.querySelector('meta[name="csrf-token"]')?.content||'');
    params.append('pin', currentEnteredPin);

    fetch('<?= BASE_URL ?>includes/screen_lock_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest','Accept':'application/json' },
        body: params.toString()
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (submitBtn) submitBtn.disabled = false;
        if(data.auth_required){location.href=data.login_url;return;}
        if (data.success) {
            performUnlock();
        } else {
            showLockError(data.message || MSG_INVALID_PIN);
            currentEnteredPin = "";
            updatePinDisplay();
        }
    })
    .catch(function(err) {
        if (submitBtn) submitBtn.disabled = false;
        showLockError(MSG_SERVER_ERROR);
    });
}

function performUnlock() {
    var overlay = document.getElementById('nikxorScreenLockOverlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
    document.body.classList.remove('screen-is-locked');
    currentEnteredPin = "";
    updatePinDisplay();
    hideLockError();

    // Clean up query param if present
    if (window.location.search.includes('locked=1')) {
        var cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
    }
}

function openScreenLockSettingsModal() {
    var modal = document.getElementById('screenLockSettingsModal');
    if (modal) modal.style.display = 'flex';
}

function closeScreenLockSettingsModal() {
    var modal = document.getElementById('screenLockSettingsModal');
    if (modal) modal.style.display = 'none';
}

// Physical keyboard listener for PIN typing
document.addEventListener('keydown', function(e) {
    var overlay = document.getElementById('nikxorScreenLockOverlay');
    if (overlay && overlay.style.display !== 'none') {
        if (e.key >= '0' && e.key <= '9') {
            pressLockKey(e.key);
        } else if (e.key === 'Backspace') {
            clearLockPin();
        } else if (e.key === 'Enter') {
            submitUnlockPin();
        }
    }
});

// Check local storage on page load
document.addEventListener('DOMContentLoaded', function() {
    var isLockedInStorage = localStorage.getItem('nikxor_screen_locked');
    var isLockedSession = <?= $isScreenLocked ? 'true' : 'false' ?>;
    if (isLockedInStorage === '1' || isLockedSession) {
        lockShopScreen();
    }

    var chkLock = document.getElementById('chk_screen_lock_enabled');
    if (chkLock) {
        chkLock.addEventListener('change', function() {
            var inputsBox = document.getElementById('pinSettingsInputs');
            if (inputsBox) {
                inputsBox.style.display = this.checked ? 'block' : 'none';
            }
        });
    }
});
</script>
