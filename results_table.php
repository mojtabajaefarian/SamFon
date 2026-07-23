<?php
/**
 * جدول نمایش نتایج – نسخه نهایی، کامل و بدون هیچ حذفیاتی
 * تأیید شده توسط تیم تخصصی فنی سام فون
 */

// ========== توابع کمکی ==========
function cleanSpaces($str) {
    return str_replace(['&nbsp;', '&nbsp', "\xC2\xA0", "\xE2\x80\xAF"], ' ', $str);
}

function convertDigits($str, $toPersian = false) {
    $p = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $e = ['0','1','2','3','4','5','6','7','8','9'];
    return $toPersian ? str_replace($e, $p, $str) : str_replace($p, $e, $str);
}

// ✅ تابع حیاتی: تبدیل قطعی تمام اعداد فارسی/عربی به انگلیسی و استانداردسازی فاصله
function toEnglishDigitsAndClean($str, $spacer = ' ') {
    if (empty($str)) return '';
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $str = str_replace($persian, $english, $str);
    $str = str_replace(['&nbsp;', '&nbsp', "\xC2\xA0", "\xE2\x80\xAF"], ' ', $str);
    return preg_replace('/\s+/', $spacer, trim($str));
}

// ✅ تابع حیاتی: تولید ۵ حالت خوانش استاندارد در صورت خالی بودن ستون readable_numbers دیتابیس
function generateFallbackReadable($sim) {
    $s = preg_replace('/[^0-9]/', '', $sim);
    if (strlen($s) !== 11) return htmlspecialchars($sim);
    
    $formats = [
        substr($s, 0, 4) . ' ' . substr($s, 4, 3) . ' ' . substr($s, 7, 2) . ' ' . substr($s, 9, 2), // 4-3-2-2
        substr($s, 0, 4) . ' ' . substr($s, 4, 3) . ' ' . substr($s, 7, 4),                         // 4-3-4
        substr($s, 0, 4) . '-' . substr($s, 4, 3) . '-' . substr($s, 7, 2) . '-' . substr($s, 9, 2), // 4-3-2-2 با خط تیره
        substr($s, 0, 4) . ' ' . substr($s, 4, 2) . ' ' . substr($s, 6, 2) . ' ' . substr($s, 8, 3), // 4-2-2-3
        $s // بدون فاصله
    ];
    return implode("\n", $formats);
}

// ========== نگاشت وضعیت ==========
$statusMap = [1 => 'کارکرده', 2 => 'درحدصفر', 3 => 'صفر به نام', 4 => 'صفرپک'];
$statusColors = ['کارکرده' => 'secondary', 'درحدصفر' => 'info', 'صفر به نام' => 'success', 'صفرپک' => 'primary'];

if (empty($results) || empty($results['results'])) {
    echo '<div class="alert alert-warning shadow-sm">نتیجه‌ای یافت نشد</div>';
    return;
}

// ========== تنظیمات کوکی ==========
$spaceCount = isset($_COOKIE['spaceCount']) ? (int)$_COOKIE['spaceCount'] : 2;
$spaceCount = max(1, min(5, $spaceCount));
$digitMode = isset($_COOKIE['digitMode']) ? $_COOKIE['digitMode'] : 'en';
$drawerOpen = isset($_COOKIE['drawerOpen']) ? $_COOKIE['drawerOpen'] : 'closed';
$spacer = str_repeat(' ', $spaceCount);
?>

<!-- ===== برگه تنظیمات ===== -->
<div id="settingsDrawer" class="settings-drawer <?= $drawerOpen === 'open' ? 'open' : '' ?>">
    <div class="drawer-content">
        <div class="drawer-header">
            <span class="drawer-title">تنظیمات</span>
            <span class="drawer-close" id="drawerClose">✕</span>
        </div>
        <div class="drawer-body">
            <div class="mb-3">
                <label class="fw-bold d-block">فاصله بین اعداد</label>
                <div class="btn-group w-100">
                    <button class="btn btn-outline-secondary" id="spaceDec">−</button>
                    <span class="btn btn-outline-secondary disabled" id="spaceDisplay"><?= $spaceCount ?></span>
                    <button class="btn btn-outline-secondary" id="spaceInc">+</button>
                </div>
            </div>
            <div class="mb-3">
                <label class="fw-bold d-block">نوع اعداد</label>
                <div class="btn-group w-100">
                    <button class="btn btn-outline-primary <?= $digitMode==='en'?'active':'' ?>" id="digitEn">انگلیسی</button>
                    <button class="btn btn-outline-primary <?= $digitMode==='fa'?'active':'' ?>" id="digitFa">فارسی</button>
                </div>
            </div>
        </div>
    </div>
    <div class="drawer-tab" id="drawerToggle">⚙️</div>
</div>

<!-- ===== جدول نتایج ===== -->
<div class="table-container position-relative">
    <div class="shadow-sm">
        <table class="table table-hover table-striped" id="resultsTable">
            <thead class="table-dark sticky-top">
                <tr>
                    <th class="text-center sortable" data-sort="sim_number">شماره سیمکارت <i class="bi bi-arrow-down-up"></i></th>
                    <th class="text-center sortable" data-sort="sf_price">قیمت <i class="bi bi-arrow-down-up"></i></th>
                    <th class="text-center sortable d-none d-md-table-cell" data-sort="operator">اپراتور <i class="bi bi-arrow-down-up"></i></th>
                    <th class="text-center sortable d-none d-md-table-cell" data-sort="status">وضعیت <i class="bi bi-arrow-down-up"></i></th>
                    <th class="text-center sortable d-none d-md-table-cell" data-sort="last_update">آخرین بروزرسانی <i class="bi bi-arrow-down-up"></i></th>
                    <th class="text-center d-none d-md-table-cell">خوانش</th>
                    <th class="text-center">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['results'] as $row):
                    $rawSim = $row['sim_number'] ?? '';
                    $cleanSim = preg_replace('/[^0-9]/', '', $rawSim);
                    
                    // ===== شماره اصلی و خوانش =====
                    $mainDisplay = $rawSim;
                    $readableLines = [];
                    
                    if (!empty($row['readable_numbers'])) {
                        $cleaned = str_replace(["\r\n", "\r"], "\n", $row['readable_numbers']);
                        $lines = array_filter(array_map('trim', explode("\n", $cleaned)));
                        if (!empty($lines)) {
                            $mainDisplay = array_shift($lines);
                            $readableLines = $lines;
                        }
                    } else {
                        // ✅ اگر خالی بود، ۵ حالت استاندارد تولید می‌شود
                        $fallback = generateFallbackReadable($rawSim);
                        $lines = array_filter(array_map('trim', explode("\n", $fallback)));
                        $mainDisplay = array_shift($lines);
                        $readableLines = $lines;
                    }
                    
                    // ✅ اعمال قطعی تبدیل به اعداد انگلیسی و فاصله یکنواخت روی خروجی نهایی
                    $mainDisplay = toEnglishDigitsAndClean($mainDisplay, $spacer);
                    $readableHtml = '';
                    if (!empty($readableLines)) {
                        $readableHtml = implode('<br>', array_map(function($line) use ($spacer) {
                            return htmlspecialchars(toEnglishDigitsAndClean($line, $spacer));
                        }, $readableLines));
                    }
                    
                    // ===== قیمت =====
                    $priceRaw = $row['sf_price'] ?? $row['price'] ?? 0;
                    if (is_string($priceRaw)) $priceRaw = (float) str_replace(',', '', $priceRaw);
                    $price = ($priceRaw > 0) ? number_format($priceRaw) : 'استعلام';
                    
                    // ===== اپراتور و وضعیت =====
                    $operator = detectOperator($cleanSim, OPERATORS) ?? 'ناشناس';
                    $statusNum = (int)($row['status'] ?? 0);
                    $statusLabel = $statusMap[$statusNum] ?? 'نامشخص';
                    $statusBadge = '<span class="badge bg-' . ($statusColors[$statusLabel] ?? 'secondary') . '">' . htmlspecialchars($statusLabel) . '</span>';
                    
                    // ===== تاریخ =====
                    $lastUpdate = $row['last_update'] ?? '---';
                    if (function_exists('convertToShamsi')) {
                        $lastUpdate = convertToShamsi($lastUpdate);
                    }
                    
                    $isSpecial = !empty($row['special_sale']) && $row['special_sale'] != 0;
                    $trClass = $isSpecial ? 'class="special"' : '';
                    $simLink = "/sim/" . $cleanSim;
                ?>
<!--                <tr <?= $trClass ?> data-toggle="expand" data-sim="<?= htmlspecialchars($cleanSim) ?>"> -->
<tr class="<?= !empty($sim['special_sale']) ? 'special-row' : '' ?>">
                    <td class="text-center" dir="ltr">
                        <a href="<?= $simLink ?>" class="text-decoration-none fw-bold text-primary"><?= htmlspecialchars($mainDisplay) ?></a>
                    </td>
                    
<!--                    <td class="text-center"><?= $price ?></td> -->
<!-- ستون قیمت: با تولتیپ حروفی -->
<td data-label="قیمت" class="text-center">
    <div class="price-cell d-inline-block">
        <span class="fw-bold text-success"><?= $price ?></span>
        <small class="text-muted">تومان</small>
        <div class="price-word-tooltip"><?= numberToWordsFa($price) ?></div>
    </div>
</td>

<!-- بج VIP برای فروش ویژه -->
<?php if (!empty($sim['special_sale'])): ?>
    <span class="vip-badge"><i class="bi bi-star-fill"></i> VIP</span>
<?php endif; ?>

<!--                    <td class="text-center d-none d-md-table-cell"><?= htmlspecialchars($operator) ?></td> -->
<!-- ستون اپراتور: لوگو به جای نام -->
<td data-label="اپراتور" class="text-center">
    <?= getOperatorLogo($operator, 32) ?>
</td>

                    <td class="text-center d-none d-md-table-cell"><?= $statusBadge ?></td>
                    <td class="text-center d-none d-md-table-cell" dir="ltr"><?= htmlspecialchars($lastUpdate) ?></td>
                    <td class="text-center d-none d-md-table-cell" dir="ltr"><?= $readableHtml ?></td>
                    <td class="text-center">
                        <a href="<?= $simLink ?>" class="btn btn-sm btn-primary" title="مشاهده و خرید">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <tr class="expandable-row d-md-none">
                    <td colspan="7">
                        <div class="expandable-content">
                            <div class="row">
                                <div class="col-6"><strong>اپراتور:</strong> <?= htmlspecialchars($operator) ?></div>
                                <div class="col-6"><strong>وضعیت:</strong> <?= $statusBadge ?></div>
                                <div class="col-6"><strong>آخرین بروزرسانی:</strong> <span dir="ltr"><?= htmlspecialchars($lastUpdate) ?></span></div>
                                <div class="col-12"><strong>خوانش:</strong> <span dir="ltr"><?= $readableHtml ?></span></div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========== استایل‌ها (کامل و بدون هیچ حذفیاتی) ========== -->
<style>
/* هایلایت سطرهای ویژه */
tr.special {
    background-color: #fff9e6 !important;
    border-right: 4px solid #ffc107;
}
tr.special:hover {
    background-color: #fff3cd !important;
}

/* حفظ فاصله‌ها در سلول‌های دارای شماره و خوانش */
#resultsTable td[dir="ltr"],
.expandable-content [dir="ltr"] {
    white-space: pre-wrap;
    word-break: break-word;
}

/* ===== برگه تنظیمات ===== */
.settings-drawer {
    position: fixed;
    top: 100px;
    right: 0;
    z-index: 1050;
    display: flex;
    align-items: flex-start;
    transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateX(calc(100% - 50px));
}
.settings-drawer.open { transform: translateX(0); }
.drawer-tab {
    width: 50px;
    height: 50px;
    min-width: 50px;
    background: #2b0353;
    color: white;
    border-radius: 12px 0 0 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: -2px 2px 12px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
    flex-shrink: 0;
}
.drawer-tab:hover { background: #4a1a7a; transform: scale(1.05); }
.drawer-tab-icon { font-size: 1.6rem; line-height: 1; }
.drawer-content {
    width: 260px;
    background: #fff;
    border-radius: 0 0 0 12px;
    box-shadow: -4px 4px 24px rgba(0,0,0,0.15);
    overflow: hidden;
    direction: rtl;
    flex-shrink: 0;
}
.drawer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
}
.drawer-title { font-weight: bold; font-size: 1rem; }
.drawer-close { cursor: pointer; font-size: 1.2rem; color: #888; padding: 0 4px; }
.drawer-close:hover { color: #333; }
.drawer-body { padding: 16px; }

/* ===== جدول ===== */
.table-container { padding: 1rem 0; }
.table th { white-space: nowrap; user-select: none; }
.table th i { font-size: 0.8rem; margin-right: 0.3rem; transition: transform 0.2s; }
.table th.sortable { cursor: pointer; }
.table th.sortable:hover { background-color: #0033cc !important; color: white; }
.table th.sortable i { display: inline-block; }
.expandable-row { display: none; }
.expandable-row.show { display: table-row; }
.expandable-content { padding: 1rem; background: #f8f9fa; border-radius: 0.25rem; margin: 0.5rem 0; }
.expandable-content .row { margin: 0; }
.expandable-content .col-6, .expandable-content .col-12 { padding: 0.3rem 0.5rem; }
tr[data-toggle="expand"] { cursor: pointer; vertical-align: middle;}

/* ===== موبایل ===== */
@media (max-width: 767.98px) {
    .settings-drawer { top: 80px; transform: translateX(calc(100% - 44px)); }
    .drawer-tab { width: 44px; height: 44px; min-width: 44px; }
    .drawer-tab-icon { font-size: 1.3rem; }
    .drawer-content { width: 220px; }
    .table td:not(.text-center) { text-align: center !important; }
    .table th { font-size: 0.8rem; padding: 0.3rem 0.15rem; }
    .expandable-content .col-6, .expandable-content .col-12 { text-align: center !important; direction: ltr; }
}
</style>

<!-- ========== جاوااسکریپت (کامل و بدون هیچ حذفیاتی) ========== -->
<script>
(function() {
    'use strict';
    
    // ===== برگه تنظیمات =====
    var drawer = document.getElementById('settingsDrawer');
    var tab = document.getElementById('drawerToggle');
    var closeBtn = document.getElementById('drawerClose');
    
    function toggleDrawer(forceState) {
        if (forceState !== undefined) {
            forceState ? drawer.classList.add('open') : drawer.classList.remove('open');
        } else {
            drawer.classList.toggle('open');
        }
        document.cookie = 'drawerOpen=' + (drawer.classList.contains('open') ? 'open' : 'closed') + '; path=/; max-age=31536000';
    }
    
    tab.addEventListener('click', function(e) { e.stopPropagation(); toggleDrawer(); });
    closeBtn.addEventListener('click', function(e) { e.stopPropagation(); toggleDrawer(false); });
    document.addEventListener('click', function(e) {
        if (drawer.classList.contains('open') && !drawer.contains(e.target)) toggleDrawer(false);
    });
    
    // ===== فاصله =====
    var spaceDisplay = document.getElementById('spaceDisplay');
    document.getElementById('spaceInc').addEventListener('click', function() {
        var current = parseInt(spaceDisplay.textContent);
        if (current < 5) {
            document.cookie = 'spaceCount=' + (current+1) + '; path=/; max-age=31536000';
            location.reload();
        }
    });
    document.getElementById('spaceDec').addEventListener('click', function() {
        var current = parseInt(spaceDisplay.textContent);
        if (current > 1) {
            document.cookie = 'spaceCount=' + (current-1) + '; path=/; max-age=31536000';
            location.reload();
        }
    });
    
    // ===== اعداد =====
    document.getElementById('digitEn').addEventListener('click', function() {
        document.cookie = 'digitMode=en; path=/; max-age=31536000';
        location.reload();
    });
    document.getElementById('digitFa').addEventListener('click', function() {
        document.cookie = 'digitMode=fa; path=/; max-age=31536000';
        location.reload();
    });
    
    // ===== مرتب‌سازی =====
    function initSorting() {
        var headers = document.querySelectorAll('#resultsTable thead th.sortable');
        if (!headers.length) return;
        var urlParams = new URLSearchParams(window.location.search);
        var currentSort = urlParams.get('sort') || 'sf_price';
        var currentOrder = urlParams.get('order') || 'asc';
        headers.forEach(function(header) {
            var sortKey = header.getAttribute('data-sort');
            var icon = header.querySelector('i');
            if (sortKey === currentSort) {
                icon.className = (currentOrder === 'asc') ? 'bi bi-arrow-up' : 'bi bi-arrow-down';
            } else {
                icon.className = 'bi bi-arrow-down-up';
            }
        });
        headers.forEach(function(header) {
            header.addEventListener('click', function(e) {
                var sortKey = this.getAttribute('data-sort');
                if (!sortKey) return;
                var newOrder = 'asc';
                if (sortKey === currentSort) {
                    newOrder = (currentOrder === 'asc') ? 'desc' : 'asc';
                }
                var params = new URLSearchParams(window.location.search);
                params.set('sort', sortKey);
                params.set('order', newOrder);
                params.set('page', 1);
                window.location.href = window.location.pathname + '?' + params.toString();
            });
        });
    }
    
    // ===== Expandable =====
    function initExpandableRows() {
        document.querySelectorAll('#resultsTable tbody tr[data-toggle="expand"]').forEach(function(row) {
            row.addEventListener('click', function(e) {
                if (e.target.closest('a')) return; // ✅ جلوگیری از تداخل با لینک جدید شماره سیمکارت
                var expandableRow = this.nextElementSibling;
                if (expandableRow && expandableRow.classList.contains('expandable-row')) {
                    expandableRow.classList.toggle('show');
                }
            });
        });
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initSorting();
            initExpandableRows();
        });
    } else {
        initSorting();
        initExpandableRows();
    }
})();
</script>

<!-- =============================================================
     اصلاحات results_table: لوگو + VIP + قیمت به حروف + موبایل
     ============================================================= -->
<style>
/* لوگوی اپراتور */
.operator-logo {
    cursor: help;
    transition: transform 0.2s;
}
.operator-logo:hover {
    transform: scale(1.15);
}

/* بج VIP */
.vip-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: linear-gradient(135deg, #f5af19 0%, #f12711 100%);
    color: #fff;
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 10px;
    letter-spacing: 1px;
    animation: vipPulse 2s infinite;
}
@keyframes vipPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(245, 175, 25, 0.4); }
    50% { box-shadow: 0 0 0 6px rgba(245, 175, 25, 0); }
}

/* سطر فروش ویژه */
tr.special-row {
    background: linear-gradient(90deg, #fff9e6 0%, #fff 100%) !important;
    border-right: 3px solid #f5af19 !important;
}

/* تولتیپ قیمت به حروف */
.price-word-tooltip {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: #fff;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    white-space: nowrap;
    z-index: 1000;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.2s;
    direction: rtl;
}
.price-cell {
    position: relative;
    cursor: help;
}
.price-cell:hover .price-word-tooltip {
    opacity: 1;
}

/* موبایل: جدول کارتی */
@media (max-width: 767.98px) {
    #resultsTable thead { display: none !important; }
    #resultsTable, #resultsTable tbody, #resultsTable tr, #resultsTable td {
        display: block !important;
        width: 100% !important;
    }
    #resultsTable tr {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        margin-bottom: 12px;
        padding: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    #resultsTable td {
        border: none !important;
        padding: 6px 0 !important;
        display: flex !important;
        justify-content: space-between;
        align-items: center;
    }
    #resultsTable td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #6c757d;
        font-size: 0.8rem;
        flex-shrink: 0;
        margin-left: 8px;
    }
    #resultsTable td[dir="ltr"] {
        direction: ltr !important;
        text-align: left !important;
        white-space: nowrap;
        overflow-x: auto;
    }
}
</style>

<script>
// اضافه کردن data-label + تولتیپ قیمت به حروف
document.addEventListener('DOMContentLoaded', function() {
    const labels = ['شماره', 'قیمت', 'اپراتور', 'وضعیت', 'بروزرسانی', 'خوانش', 'عملیات'];
    
    document.querySelectorAll('#resultsTable tbody tr:not(.expandable-row)').forEach(function(row) {
        row.querySelectorAll('td').forEach(function(cell, i) {
            if (labels[i]) cell.setAttribute('data-label', labels[i]);
        });
    });
});
</script>
