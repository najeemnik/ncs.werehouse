/**
 * Nik Smart Count - Mobile Shop Management System
 * Vanilla JavaScript Utilities & Form Enhancements
 */

document.addEventListener('DOMContentLoaded', function() {
    initNumberNormalization();
    initConfirmDialogs();
    initSalesCalculator();
    initTableSearch();
    initMobileNav();
});

/**
 * Normalizes Persian (۰-۹) and Arabic (٠-٩) digits to ASCII English digits (0-9).
 */
const digitMap = {
    '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
    '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
    '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
    '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
    '٫': '.', '٬': ''
};

function normalizeDigits(str) {
    if (str === null || str === undefined) return '';
    let result = str.toString();
    for (const [k, v] of Object.entries(digitMap)) {
        if (result.includes(k)) {
            result = result.split(k).join(v);
        }
    }
    return result;
}

function isSensitiveInput(el){return el.type==='password'||el.type==='file'||el.hasAttribute('data-sensitive')||el.autocomplete==='username'||el.autocomplete==='current-password'||el.autocomplete==='new-password';}

function insertTextAtCursor(el, text) {
    if (el.selectionStart !== undefined && el.selectionEnd !== undefined) {
        const start = el.selectionStart;
        const end = el.selectionEnd;
        const val = el.value || '';
        el.value = val.substring(0, start) + text + val.substring(end);
        el.selectionStart = el.selectionEnd = start + text.length;
    } else {
        el.value = (el.value || '') + text;
    }
}

// Global keydown capture: Intercept Persian/Arabic keyboard digits so browsers never block them
document.addEventListener('keydown', function(e) {
    if (!e.target || !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) return;
    const input = e.target;
    if(isSensitiveInput(input))return;

    // Check if the pressed key is a Persian or Arabic digit
    if (digitMap.hasOwnProperty(e.key)) {
        const mappedChar = digitMap[e.key];

        // If it's a number field, convert to text[inputmode=decimal] to avoid browser character rejection
        if (input.type === 'number') {
            try {
                input.type = 'text';
                input.setAttribute('inputmode', 'decimal');
                input.setAttribute('data-converted-number', '1');
            } catch (err) {}
        }

        e.preventDefault();
        insertTextAtCursor(input, mappedChar);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
}, true);

/**
 * Attaches real-time digit normalization on all numeric inputs and forms.
 */
function initNumberNormalization() {
    // 1. Convert all existing number inputs so Persian keyboard keys never get rejected
    document.querySelectorAll('input[type="number"]').forEach(function(input) {
        try {
            input.type = 'text';
            input.setAttribute('inputmode', 'decimal');
            input.setAttribute('data-converted-number', '1');
        } catch (err) {}
    });

    // 2. Listen on input & paste events
    const selector = 'input, textarea';
    document.querySelectorAll(selector).forEach(function(input) {
        if(isSensitiveInput(input))return;
        input.addEventListener('input', function() {
            const currentVal = this.value;
            const normalized = normalizeDigits(currentVal);
            if (currentVal !== normalized) {
                const start = this.selectionStart;
                this.value = normalized;
                if (start !== null && typeof this.setSelectionRange === 'function') {
                    try {
                        this.setSelectionRange(start, start);
                    } catch (err) {}
                }
            }
        });

        input.addEventListener('paste', function(e) {
            setTimeout(() => {
                this.value = normalizeDigits(this.value);
            }, 10);
        });
    });

    // 3. Normalize all fields before form submission
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            form.querySelectorAll('input, textarea').forEach(function(field) {
                if (field.value && !isSensitiveInput(field)) {
                    field.value = normalizeDigits(field.value);
                }
            });
        });
    });
}

/**
 * Intercepts links and forms with data-confirm attributes to show safe confirmation.
 */
function initConfirmDialogs() {
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            // Never block logout links
            if (this.classList.contains('logout-btn') || (this.href && this.href.includes('logout.php'))) {
                return true;
            }
            const message = this.getAttribute('data-confirm') || 'آیا از انجام این عملیات اطمینان دارید؟';
            try {
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            } catch (err) {
                // If window.confirm is restricted by iframe/browser sandbox, allow the user action
                return true;
            }
        });
    });
}

/**
 * Handles the dynamic product selection and profit preview on the Sales page.
 */
function initSalesCalculator() {
    const productSelect = document.getElementById('sale_product_select');
    if (!productSelect) return;

    const qtyInput       = document.getElementById('sale_quantity');
    const priceInput     = document.getElementById('sale_price');
    const totalDisplay   = document.getElementById('sale_total_display');
    const profitDisplay  = document.getElementById('sale_profit_display');
    const stockBadge     = document.getElementById('product_stock_badge');
    const specsDisplay   = document.getElementById('product_specs_display');
    const purchaseDisplay= document.getElementById('product_purchase_display');

    function updateCalculations() {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) {
            if (stockBadge) stockBadge.textContent = '-';
            if (specsDisplay) specsDisplay.textContent = '-';
            if (purchaseDisplay) purchaseDisplay.textContent = '-';
            if (totalDisplay) totalDisplay.textContent = '۰ افغانی';
            if (profitDisplay) profitDisplay.textContent = '۰ افغانی';
            return;
        }

        const availableStock = parseFloat(selectedOption.getAttribute('data-stock') || 0);
        const purchasePrice  = parseFloat(selectedOption.getAttribute('data-purchase') || 0);
        const expensePerItem = parseFloat(selectedOption.getAttribute('data-expense') || 0);
        const specs          = selectedOption.getAttribute('data-specs') || 'بدون مشخصات';

        if (stockBadge) {
            stockBadge.textContent = availableStock + ' عدد';
            if (availableStock <= 0) {
                stockBadge.className = 'badge badge-danger';
            } else if (availableStock <= 3) {
                stockBadge.className = 'badge badge-warning';
            } else {
                stockBadge.className = 'badge badge-success';
            }
        }

        if (specsDisplay) specsDisplay.textContent = specs;
        if (purchaseDisplay) purchaseDisplay.textContent = purchasePrice.toLocaleString() + ' افغانی';

        // Update default sale price if empty
        if (priceInput && (!priceInput.value || parseFloat(priceInput.value) === 0)) {
            const suggestedSale = purchasePrice + expensePerItem;
            if (suggestedSale > 0) {
                priceInput.value = suggestedSale;
            }
        }

        const enteredQty   = parseFloat(normalizeDigits(qtyInput ? qtyInput.value : 1)) || 0;
        const enteredPrice = parseFloat(normalizeDigits(priceInput ? priceInput.value : 0)) || 0;

        // Validation against available stock
        if (qtyInput) {
            if (enteredQty > availableStock) {
                qtyInput.style.borderColor = '#ef4444';
            } else {
                qtyInput.style.borderColor = '';
            }
        }

        const totalAmount = enteredQty * enteredPrice;
        const unitCost    = purchasePrice + expensePerItem;
        const totalProfit = (enteredPrice - unitCost) * enteredQty;

        if (totalDisplay) {
            totalDisplay.textContent = totalAmount.toLocaleString() + ' افغانی';
        }

        if (profitDisplay) {
            profitDisplay.textContent = totalProfit.toLocaleString() + ' افغانی';
            if (totalProfit < 0) {
                profitDisplay.style.color = '#ef4444';
            } else {
                profitDisplay.style.color = '#15803d';
            }
        }
    }

    productSelect.addEventListener('change', updateCalculations);
    if (qtyInput) {
        qtyInput.addEventListener('input', updateCalculations);
    }
    if (priceInput) {
        priceInput.addEventListener('input', updateCalculations);
    }

    // Trigger initial calculation
    updateCalculations();
}

/**
 * Simple client-side table filter for quick live search.
 */
function initTableSearch() {
    const searchInputs = document.querySelectorAll('[data-table-search]');
    searchInputs.forEach(function(input) {
        const tableId = input.getAttribute('data-table-search');
        const table = document.getElementById(tableId);
        if (!table) return;

        input.addEventListener('keyup', function() {
            const query = normalizeDigits(this.value.toLowerCase().trim());
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(function(row) {
                const text = normalizeDigits(row.textContent.toLowerCase());
                if (text.indexOf(query) !== -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
}

/**
 * Mobile Sidebar Drawer Controller
 */
function initMobileNav() {
    const toggleBtn = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.app-sidebar');
    const overlay = document.getElementById('mobileNavOverlay');
    const closeBtn = document.getElementById('sidebarCloseBtn');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('sidebar-open');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('sidebar-open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar && sidebar.classList.contains('sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            closeSidebar();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            closeSidebar();
        });
    }

    // Close mobile drawer when pressing Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });
}
