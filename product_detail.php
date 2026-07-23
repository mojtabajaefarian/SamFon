<?php
/**
 * ============================================================
 * صفحه جزئیات محصول (سیمکارت)
 * نسخه نهایی v4 - اصلاح‌شده توسط تیم فنی
 * ============================================================
 * 
 * باگ‌های اصلاح‌شده:
 * ۱. ناهماهنگی نام متغیر $analysis / $detectedPatterns
 * ۲. ساختار آرایه patterns (icon, type, value)
 * ۳. فیلتر کد خط در سیمکارت‌های مشابه
 * ۴. Structured Data ایمن
 * ۵. جلوگیری از htmlspecialchars(null)
 */

if (!isset($simNumber) || !isset($product) || empty($product)) {
    header("Location: /");
    exit;
}

// بارگذاری وابستگی‌ها
require_once DIR . '/includes/rond_patterns.php';
require_once DIR . '/includes/dictionaries.php';

// =====================================================================
// ۱. توابع کمکی
// =====================================================================

/**
 * تبدیل اعداد فارسی/عربی به انگلیسی و استانداردسازی فاصله
 */
function toEnglishDigitsAndClean($str, $spacer = ' ') {
    if (empty($str)) return '';
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $english = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
    $str = str_replace($persian, $english, $str);
    $str = str_replace(['&nbsp;', '&nbsp', "\xC2\xA0", "\xE2\x80\xAF"], ' ', $str);
    return preg_replace('/\s+/', $spacer, trim($str));
}

/**
 * نگاشت آیکون و برچسب نمایشی برای هر نوع رندی
 */
function getRondDisplayInfo(string $rondId): array {
    $map = [
        'seven_same'           => ['icon' => '🔥', 'label' => 'هفت رقم یکی'],
        'six_same_start'       => ['icon' => '🔥', 'label' => 'شش رقم یکی از اول'],
        'six_same_end'         => ['icon' => '🔥', 'label' => 'شش رقم یکی از آخر'],
        'five_same_start'      => ['icon' => '🔥', 'label' => 'پنج رقم یکی از اول'],
        'five_same_middle'     => ['icon' => '🔥', 'label' => 'پنج رقم یکی از وسط'],
        'five_same_end'        => ['icon' => '🔥', 'label' => 'پنج رقم یکی از آخر'],
        'four_same_start'      => ['icon' => '⭐', 'label' => 'چهار رقم یکی از اول'],
        'four_same_middle'     => ['icon' => '⭐', 'label' => 'چهار رقم یکی از وسط'],
        'four_same_end'        => ['icon' => '⭐', 'label' => 'چهار رقم یکی از آخر'],
        'three_same_start'     => ['icon' => '⭐', 'label' => 'سه رقم یکی از اول'],
        'three_same_middle'    => ['icon' => '⭐', 'label' => 'سه رقم یکی از وسط'],
        'three_same_end'       => ['icon' => '⭐', 'label' => 'سه رقم یکی از آخر'],
        'pair_start'           => ['icon' => '✨', 'label' => 'دودویی از اول'],
        'pair_end'             => ['icon' => '✨', 'label' => 'دودویی از آخر'],
        'pair_middle'          => ['icon' => '✨', 'label' => 'دودویی از وسط'],
        'pair_pair_start'      => ['icon' => '✨', 'label' => 'جفت جفت از اول'],
        'pair_pair_end'        => ['icon' => '✨', 'label' => 'جفت جفت از آخر'],
        'pair_first_last'      => ['icon' => '✨', 'label' => 'جفت اول و آخر'],
        'pair_pair_separate'   => ['icon' => '✨', 'label' => 'جفت جفت مجزا'],
        'three_pair_start'     => ['icon' => '✨', 'label' => 'سه جفت از اول'],
        'three_pair_end'       => ['icon' => '✨', 'label' => 'سه جفت از آخر'],
        'three_pair_separate'  => ['icon' => '✨', 'label' => 'سه جفت مجزا'],
        'ladder_start'         => ['icon' => '📈', 'label' => 'پله‌ای از اول'],
        'ladder_plus_start'    => ['icon' => '📈', 'label' => 'پله پلاس از اول'],
        'ladder_end'           => ['icon' => '📈', 'label' => 'پله‌ای از آخر'],
        'ladder_plus_end'      => ['icon' => '📈', 'label' => 'پله پلاس از آخر'],
        'three_ladder'         => ['icon' => '📈', 'label' => 'سه پله'],
        'seq3_start'           => ['icon' => '🔢', 'label' => 'ترتیبی از اول'],
        'seq4_start'           => ['icon' => '🔢', 'label' => '۴ رقم ترتیبی از اول'],
        'seq5_start'           => ['icon' => '🔢', 'label' => '۵ رقم ترتیبی از اول'],
        'seq3_end'             => ['icon' => '🔢', 'label' => 'ترتیبی از آخر'],
        'seq4_end'             => ['icon' => '🔢', 'label' => '۴ رقم ترتیبی از آخر'],
        'seq5_end'             => ['icon' => '🔢', 'label' => '۵ رقم ترتیبی از آخر'],
        'mirror'               => ['icon' => '🪞', 'label' => 'آینه‌ای'],
        'mirror_full'          => ['icon' => '🪞', 'label' => 'آینه‌ای کامل'],
        'balanced'             => ['icon' => '⚖️', 'label' => 'ترازویی (پرانتزی)'],
        'ten_ten_start'        => ['icon' => '🎯', 'label' => 'ده دهی از اول'],
        'ten_ten_end'          => ['icon' => '🎯', 'label' => 'ده دهی از آخر'],
        'ten_ten_middle'       => ['icon' => '🎯', 'label' => 'ده دهی از وسط'],
        'three_ten_start'      => ['icon' => '🎯', 'label' => 'سه دهی از اول'],
        'three_ten_end'        => ['icon' => '🎯', 'label' => 'سه دهی از آخر'],
        'ten_thousand_start'   => ['icon' => '💎', 'label' => 'ده هزاری از اول'],
        'ten_thousand_end'     => ['icon' => '💎', 'label' => 'ده هزاری از آخر'],
        'ten_thousand_middle'  => ['icon' => '💎', 'label' => 'ده هزاری از وسط'],
        'thousand_start'       => ['icon' => '💎', 'label' => 'هزاری از اول'],
        'thousand_middle'      => ['icon' => '💎', 'label' => 'هزاری از وسط'],
        'thousand_end'         => ['icon' => '💎', 'label' => 'هزاری از آخر'],
        'hundred_start'        => ['icon' => '💰', 'label' => 'صدی از اول'],
        'hundred_middle'       => ['icon' => '💰', 'label' => 'صدی از وسط'],
        'hundred_end'          => ['icon' => '💰', 'label' => 'صدی از آخر'],
        'hundred_hundred_start'=> ['icon' => '💰', 'label' => 'صد صدی از اول'],
        'hundred_hundred_end'  => ['icon' => '💰', 'label' => 'صد صدی از آخر'],
        'million'              => ['icon' => '🏆', 'label' => 'میلیونی'],
        'hundred_thousand'     => ['icon' => '🏆', 'label' => 'صدهزاری'],
        'birthday_start'       => ['icon' => '🎂', 'label' => 'تاریخ تولدی از اول'],
        'birthday_end'         => ['icon' => '🎂', 'label' => 'تاریخ تولدی از آخر'],
        'two_digit_only'       => ['icon' => '🎲', 'label' => 'متشکل از دو رقم'],
        'low_code'             => ['icon' => '👑', 'label' => 'کد پایین'],
        'prefix_repeat'        => ['icon' => '🔄', 'label' => 'تکرار پیش شماره'],
        'alphabetic'           => ['icon' => '🔤', 'label' => 'حروفی'],
        'verbal'               => ['icon' => '🗣️', 'label' => 'گفتاری'],
        'normal'               => ['icon' => '📱', 'label' => 'معمولی'],
    ];
    
    return $map[$rondId] ?? ['icon' => '📱', 'label' => $rondId];
}

/**
 * تحلیل رندی شماره بر اساس جدول جامع rond_patterns.php
 */
function analyzeSimNumberNew($simNumber, $price, $operator) {
    $clean = preg_replace('/[^0-9]/', '', $simNumber);
    if (strlen($clean) !== 11) return null;
    
    $code = substr($clean, 4, 1);
    $detected = detectRondType($simNumber);
    
    // محاسبه مجموع امتیاز و ساخت آرایه نمایشی
    $totalScore = 0;
    $patterns = [];
    foreach ($detected as $d) {
        $totalScore += $d['avg_price_impact'];
        $displayInfo = getRondDisplayInfo($d['id']);
        $patterns[] = [
            'id'    => $d['id'],
            'icon'  => $displayInfo['icon'],
            'type'  => $displayInfo['label'],
            'value' => $d['avg_price_impact'] . '% افزایش قیمت',
            'impact'=> $d['avg_price_impact'],
        ];
    }
    
    // تعیین سطح رندی
    $rondLevel = 'معمولی';
    $rondColor = 'secondary';
    if ($totalScore >= 150)      { $rondLevel = 'فوق‌رند (VIP)'; $rondColor = 'danger'; }
    elseif ($totalScore >= 100)  { $rondLevel = 'رند برتر';      $rondColor = 'warning'; }
    elseif ($totalScore >= 50)   { $rondLevel = 'رند';           $rondColor = 'success'; }
    elseif ($totalScore >= 25)   { $rondLevel = 'نیمه‌رند';      $rondColor = 'info'; }
    
    // کیفیت کد
    $codeQuality = 'معمولی';
    if (in_array($code, ['1', '2', '3']))      $codeQuality = 'برتر';
    elseif (in_array($code, ['4', '5', '6']))   $codeQuality = 'خوب';
    
    // پیشنهاد کاربرد
    if ($totalScore >= 100) {
        $useCases = ['برندینگ شرکتی', 'کسب‌وکارهای لوکس', 'شخصیت‌های VIP', 'تبلیغات تلویزیونی'];
    } elseif ($totalScore >= 50) {
        $useCases = ['مدیران و وکلا', 'مشاوران املاک', 'پزشکان', 'فروشگاه‌های آنلاین'];
    } else {
        $useCases = ['استفاده شخصی', 'دانشجویان', 'فریلنسرها', 'کسب‌وکارهای کوچک'];
    }
    
    return [
        'patterns'    => $patterns,
        'score'       => $totalScore,
        'rondLevel'   => $rondLevel,
        'rondColor'   => $rondColor,
        'codeQuality' => $codeQuality,
        'code'        => $code,
        'useCases'    => $useCases,
        'operator'    => $operator,
        'price'       => $price,
    ];
}

// =====================================================================
// ۲. استخراج داده‌های اصلی
// =====================================================================
$displayPre4    = $product['pre_number'] ?? substr($simNumber, 0, 4);
$displayPre3    = ltrim($displayPre4, '0');
$displayOperator = $operatorName ?? 'نامشخص';
$displayStatus  = $statusText ?? 'نامشخص';
$displayPrice   = (int)($product['sf_price'] ?? $product['price'] ?? 0);
$displayCode    = substr($simNumber, 4, 1);
$displayNext3   = substr($simNumber, 4, 3);
$breadcrumbPre  = $displayPre3;

// ✅ اجرای موتور تحلیل (نام متغیر اصلاح‌شده: $analysis)
$analysis = analyzeSimNumberNew($simNumber, $displayPrice, $displayOperator);

// عنوان H1
$h1Title = $simNumber;
if (!empty($product['readable_numbers'])) {
    $lines = array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", $product['readable_numbers']))));
    if (!empty($lines)) {
        $h1Title = toEnglishDigitsAndClean($lines[0], ' ');
    } else {
        $h1Title = toEnglishDigitsAndClean(
            substr($simNumber, 0, 4) . ' ' . substr($simNumber, 4, 3) . ' ' . 
            substr($simNumber, 7, 2) . ' ' . substr($simNumber, 9, 2), ' '
        );
    }
} else {
    $h1Title = toEnglishDigitsAndClean(
        substr($simNumber, 0, 4) . ' ' . substr($simNumber, 4, 3) . ' ' . 
        substr($simNumber, 7, 2) . ' ' . substr($simNumber, 9, 2), ' '
    );
}

require 'header.php'; 
?>

<!-- =====================================================================
     استایل‌های مدرن
     ===================================================================== -->
<style>
/* ===== بردکرامب مدرن ===== */
.modern-breadcrumb {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-radius: 12px;
    padding: 12px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    border: 1px solid #e9ecef;
    margin-bottom: 24px;
}
.modern-breadcrumb .breadcrumb {
    margin: 0;
    background: transparent;
    padding: 0;
}
.modern-breadcrumb .breadcrumb-item + .breadcrumb-item::before {
    content: "›";
    color: #6c757d;
    font-weight: bold;
    font-size: 1.3rem;
    padding: 0 8px;
}
.modern-breadcrumb .breadcrumb-item a {
    color: #495057;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.modern-breadcrumb .breadcrumb-item a:hover {
    color: #0d6efd;
}
.modern-breadcrumb .breadcrumb-item.active {
    color: #0d6efd;
    font-weight: 600;
}

/* ===== کارت تحلیل ===== */
.analysis-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #e9ecef;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
}
.analysis-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 2px dashed #e9ecef;
}
.analysis-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}
.pattern-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 20px;
    margin: 4px;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s;
}
.pattern-badge:hover {
    background: #e7f1ff;
    border-color: #0d6efd;
    transform: translateY(-2px);
}
.use-case-tag {
    display: inline-block;
    padding: 5px 12px;
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
    margin: 3px;
}
.rond-meter {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 8px;
}
.rond-meter-fill {
    height: 100%;
    background: linear-gradient(90deg, #11998e 0%, #38ef7d 100%);
    border-radius: 4px;
    transition: width 1s ease;
}

/* ===== اسکرول افقی پیشنهادات ===== */
.suggestion-scroll {
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto;
    gap: 15px;
    padding-bottom: 12px;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
}
.suggestion-scroll::-webkit-scrollbar {
    height: 6px;
}
.suggestion-scroll::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}
.suggestion-scroll::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}
.suggestion-card {
    min-width: 170px;
    max-width: 170px;
    flex-shrink: 0;
    border-radius: 12px;
    transition: all 0.3s;
}
.suggestion-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(13, 110, 253, 0.15);
}
.category-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    color: #0d6efd;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e7f1ff;
}
.category-title .badge {
    font-size: 0.7rem;
    padding: 4px 8px;
}
</style>

<!-- =====================================================================
     ۳. بردکرامب مدرن
     ===================================================================== -->
<div class="container">
    <nav class="modern-breadcrumb" aria-label="مسیر راهنما">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="/"><i class="bi bi-house-door-fill"></i> خانه</a>
            </li>
            <li class="breadcrumb-item">
                <a href="/search/<?= htmlspecialchars($breadcrumbPre) ?>">
                    <i class="bi bi-phone-fill"></i> سیمکارت‌های <?= htmlspecialchars($breadcrumbPre) ?>
                </a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">
                <span dir="ltr"><?= htmlspecialchars($h1Title) ?></span>
            </li>
        </ol>
    </nav>
</div>

<div class="container my-4">
    <div class="row g-4">
        <!-- =============================================================
             ستون اصلی اطلاعات
             ============================================================= -->
        <div class="col-lg-7">
            <h1 class="display-5 fw-bold text-primary mb-3" dir="ltr" style="text-align: right;">
                <?= htmlspecialchars($h1Title) ?>
            </h1>
            
            <!-- مشخصات کامل -->
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h5 class="text-muted mb-3"><i class="bi bi-info-circle-fill me-2"></i>مشخصات کامل سیمکارت</h5>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">اپراتور</small>
                                <strong><?= htmlspecialchars($displayOperator) ?></strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">پیش‌شماره</small>
                                <strong dir="ltr"><?= htmlspecialchars($displayPre4) ?></strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">کد خط</small>
                                <strong><?= htmlspecialchars($displayCode) ?></strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">وضعیت خط</small>
                                <strong><?= htmlspecialchars($displayStatus) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- =============================================================
                 ۴. موتور تحلیل هوشمند شماره
                 ✅ باگ اصلاح‌شده: $analysis به جای $detectedPatterns
                 ✅ باگ اصلاح‌شده: $pattern['icon'], $pattern['type'], $pattern['value']
                 ============================================================= -->
            <?php if ($analysis && !empty($analysis['patterns'])): ?>
            <div class="analysis-card mb-4">
                <div class="analysis-header">
                    <div class="analysis-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold">تحلیل تخصصی شماره</h5>
                        <small class="text-muted">بررسی الگوهای رندی و ارزش بازار</small>
                    </div>
                    <span class="badge bg-<?= htmlspecialchars($analysis['rondColor'] ?? 'secondary') ?> ms-auto px-3 py-2" style="font-size: 0.9rem;">
                        <?= htmlspecialchars($analysis['rondLevel'] ?? 'معمولی') ?>
                    </span>
                </div>

                <!-- نمایش الگوهای شناسایی شده -->
                <div class="mb-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-stars text-warning me-2"></i>الگوهای رندی شناسایی شده:</h6>
                    <div>
                        <?php foreach ($analysis['patterns'] as $pattern): ?>
                            <span class="pattern-badge">
                                <span><?= htmlspecialchars($pattern['icon'] ?? '📱') ?></span>
                                <span><?= htmlspecialchars($pattern['type'] ?? 'نامشخص') ?></span>
                                <small class="text-muted">(<?= htmlspecialchars($pattern['value'] ?? '') ?>)</small>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- متر رندی -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="fw-bold">میزان رندی</small>
                        <small class="text-muted"><?= min(100, (int)($analysis['score'] ?? 0)) ?>%</small>
                    </div>
                    <div class="rond-meter">
                        <div class="rond-meter-fill" style="width: <?= min(100, (int)($analysis['score'] ?? 0)) ?>%;"></div>
                    </div>
                </div>

                <!-- توضیحات تحلیلی (سئو شده) -->
                <div class="p-3 bg-light rounded mb-3">
                    <p class="mb-2" style="line-height: 1.8;">
                        سیمکارت <strong><?= htmlspecialchars($displayOperator) ?></strong> با پیش‌شماره 
                        <strong dir="ltr"><?= htmlspecialchars($displayPre4) ?></strong> و کد 
                        <strong><?= htmlspecialchars($analysis['codeQuality'] ?? 'معمولی') ?> <?= htmlspecialchars($analysis['code'] ?? '') ?></strong>،
                        <?php if (($analysis['score'] ?? 0) >= 100): ?>
                            یک خط <strong class="text-danger">VIP و سرمایه‌ای</strong> در بازار سیمکارت ایران محسوب می‌شود.
                            این شماره به دلیل حفظ شدن آسان در ذهن و ایجاد تمایز برای برند شخصی یا شرکتی، ارزش افزوده بالایی دارد.
                        <?php elseif (($analysis['score'] ?? 0) >= 50): ?>
                            یک خط <strong class="text-success">رند و حرفه‌ای</strong> است که برای کسب‌وکارهای در حال رشد
                            و افرادی که به دنبال تمایز هستند، انتخابی هوشمندانه محسوب می‌شود.
                        <?php else: ?>
                            با قیمت مناسب، گزینه‌ای <strong>اقتصادی و کاربردی</strong> برای استفاده روزمره است.
                        <?php endif; ?>
                    </p>
                </div>

                <!-- پیشنهاد کاربرد -->
                <div>
                    <h6 class="fw-bold mb-2"><i class="bi bi-briefcase-fill text-success me-2"></i>مناسب برای:</h6>
                    <div>
                        <?php foreach (($analysis['useCases'] ?? []) as $case): ?>
                            <span class="use-case-tag"><?= htmlspecialchars($case) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- =============================================================
             ستون قیمت و خرید
             ============================================================= -->
        <div class="col-lg-5">
            <div class="card shadow-lg border-0 sticky-top" style="top: 20px; border-radius: 16px;">
                <div class="card-body p-4">
                    <h4 class="text-center mb-4 fw-bold">
                        <i class="bi bi-tag-fill text-success me-2"></i>
                        قیمت و ثبت سفارش
                    </h4>
                    <div class="text-center mb-4 p-4 bg-light rounded-3">
                        <span class="text-muted text-decoration-line-through d-block small">قیمت مصرف‌کننده</span>
                        <h2 class="text-success fw-bold mb-1" dir="ltr">
                            <?= number_format($displayPrice) ?>
                        </h2>
                        <small class="text-muted">تومان</small>
                        <div class="mt-3">
                            <span class="badge bg-info px-3 py-2">
                                <i class="bi bi-bank me-1"></i> قابل پرداخت با وام و اقساط
                            </span>
                        </div>
                    </div>
                    <a href="/?page=cart&add=<?= htmlspecialchars($simNumber) ?>" 
                       class="btn btn-primary btn-lg w-100 mb-2 shadow-sm">
                        <i class="bi bi-cart-plus-fill me-2"></i> افزودن به سبد خرید
                    </a>
                    <a href="tel:09126900948" 
                       class="btn btn-outline-success btn-lg w-100 shadow-sm">
                        <i class="bi bi-telephone-fill me-2"></i> مشاوره و خرید تلفنی
                    </a>
                    
                    <div class="mt-4 pt-3 border-top">
                        <small class="text-muted d-flex align-items-center gap-2">
                            <i class="bi bi-shield-check text-success"></i>
                            انتقال سند رسمی در مراکز مخابراتی
                        </small>
                        <small class="text-muted d-flex align-items-center gap-2 mt-2">
                            <i class="bi bi-patch-check-fill text-primary"></i>
                            ضمانت اصالت و قانونی بودن سیمکارت
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =============================================================
         ۵. سیمکارت‌های مشابه
         ✅ باگ اصلاح‌شده: فیلتر کد خط اضافه شد
         ✅ کوئری بهینه: یک کوئری اصلی + یک کوئری هم‌قیمت
         ============================================================= -->
    <hr class="my-5">
    <h3 class="mb-4 fw-bold">
        <i class="bi bi-collection-fill text-primary me-2"></i>
        سایر سیمکارت‌های پیشنهادی مشابه
    </h3>
    
    <?php
    try {
        // ✅ کوئری اصلی: فیلتر بر اساس پیش‌شماره + کد خط (رفع باگ بی‌ربطی)
        $sql = "SELECT sim_number, price, status 
                FROM sim_cards 
                WHERE pre_number = :pre3 
                AND SUBSTRING(sim_number, 5, 1) = :code
                AND sim_number != :simNumber 
                AND price > 0
                ORDER BY price ASC 
                LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':pre3'      => $displayPre3,
            ':code'      => $displayCode,
            ':simNumber' => $simNumber
        ]);
        $allCandidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // امتیازدهی و دسته‌بندی
        $categories = [
            'premium'    => ['title' => '🏆 پیشنهادات ویژه (سه رقم مشابه + هم‌قیمت)', 'items' => []],
            'code_match' => ['title' => '⭐ هم‌کد در محدوده قیمت', 'items' => []],
            'rond'       => ['title' => '✨ شماره‌های رند هم‌کد', 'items' => []],
        ];
        
        $next3    = substr($simNumber, 4, 3);
        $minPrice = $displayPrice * 0.7;
        $maxPrice = $displayPrice * 1.3;
        
        foreach ($allCandidates as $candidate) {
            $candNum   = $candidate['sim_number'];
            $candPrice = (int)$candidate['price'];
            $candNext3 = substr($candNum, 4, 3);
            $candLast4 = substr($candNum, 7, 4);
            
            $score    = 0;
            $category = null;
            
            // دسته ۱: سه رقم مشابه + محدوده قیمت (بالاترین اولویت)
            if ($candNext3 === $next3 && $candPrice >= $minPrice && $candPrice <= $maxPrice) {
                $score    = 100;
                $category = 'premium';
            }
            // دسته ۲: هم‌کد + محدوده قیمت
            elseif ($candPrice >= $minPrice && $candPrice <= $maxPrice) {
                $score    = 80;
                $category = 'code_match';
            }
            
            // دسته ۳: رند (بررسی جداگانه)
            $isRond = preg_match('/(\d)\1{2}/', $candLast4) || 
                      preg_match('/1234|2345|3456|4567|5678|6789|(\d{2})\1/', $candLast4);
            if ($isRond && (empty($category) || $category === 'code_match')) {
                $category = 'rond';
                $score    = max($score, 70);
            }
            
            if ($category && count($categories[$category]['items']) < 12) {
                $candidate['score']     = $score;
                $candidate['formatted'] = toEnglishDigitsAndClean(
                    substr($candNum, 0, 4) . ' ' . substr($candNum, 4, 3) . ' ' . 
                    substr($candNum, 7, 2) . ' ' . substr($candNum, 9, 2), ' '
                );
                $categories[$category]['items'][] = $candidate;
            }
        }
        
        // ✅ کوئری جداگانه برای هم‌قیمت‌ها (بدون فیلتر کد - از کل پیش‌شماره)
        $sqlPrice = "SELECT sim_number, price, status 
                     FROM sim_cards 
                     WHERE pre_number = :pre3 
                     AND sim_number != :simNumber 
                     AND price BETWEEN :min AND :max
                     AND price > 0
                     ORDER BY price ASC 
                     LIMIT 12";
        $stmtPrice = $conn->prepare($sqlPrice);
        $stmtPrice->execute([
            ':pre3'      => $displayPre3,
            ':simNumber' => $simNumber,
            ':min'       => (int)$minPrice,
            ':max'       => (int)$maxPrice
        ]);
        $priceMatches = $stmtPrice->fetchAll(PDO::FETCH_ASSOC);
        
        // اضافه کردن هم‌قیمت‌ها به دسته price_range
        $categories['price_range'] = ['title' => '💰 هم‌قیمت‌ها (کل پیش‌شماره)', 'items' => []];
        foreach ($priceMatches as $pm) {
            if (count($categories['price_range']['items']) >= 12) break;
            $pm['formatted'] = toEnglishDigitsAndClean(
                substr($pm['sim_number'], 0, 4) . ' ' . substr($pm['sim_number'], 4, 3) . ' ' . 
                substr($pm['sim_number'], 7, 2) . ' ' . substr($pm['sim_number'], 9, 2), ' '
            );
            $categories['price_range']['items'][] = $pm;
        }
        
        // نمایش دسته‌ها
        $hasAnySuggestion = false;
        foreach ($categories as $cat): 
            if (empty($cat['items'])) continue;
            $hasAnySuggestion = true;
        ?>
            <div class="mb-4">
                <div class="category-title">
                    <span><?= htmlspecialchars($cat['title']) ?></span>
                    <span class="badge bg-primary"><?= count($cat['items']) ?> مورد</span>
                </div>
                <div class="suggestion-scroll">
                    <?php foreach ($cat['items'] as $sim): ?>
                        <div class="card suggestion-card border-primary">
                            <div class="card-body text-center p-3">
                                <h6 class="fw-bold text-dark mb-2" dir="ltr" style="font-size: 1rem;">
                                    <?= htmlspecialchars($sim['formatted']) ?>
                                </h6>
                                <p class="text-success mb-2 fw-bold">
                                    <?= number_format((int)$sim['price']) ?> <small class="text-muted">تومان</small>
                                </p>
                                <a href="/sim/<?= htmlspecialchars($sim['sim_number']) ?>" 
                                   class="btn btn-sm btn-primary w-100">
                                    <i class="bi bi-eye-fill me-1"></i> مشاهده
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php 
        endforeach;
        
        if (!$hasAnySuggestion): 
        ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle-fill me-2"></i>
                در حال حاضر سیمکارت مشابهی با این مشخصات موجود نیست. 
                <a href="/search/<?= htmlspecialchars($breadcrumbPre) ?>" class="alert-link">
                    مشاهده همه سیمکارت‌های <?= htmlspecialchars($breadcrumbPre) ?>
                </a>
            </div>
        <?php 
        endif;
        
    } catch (Exception $e) {
        echo '<div class="alert alert-warning text-center">';
        echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
        echo 'در بارگذاری پیشنهادات با مشکل مواجه شدیم. لطفاً صفحه را رفرش کنید.';
        echo '</div>';
    }
    ?>

</div>

<!-- =====================================================================
     ۶. Structured Data
     ✅ باگ اصلاح‌شده: استفاده از $analysis به جای $detectedPatterns
     ===================================================================== -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "سیمکارت <?= htmlspecialchars($simNumber) ?> <?= htmlspecialchars($displayOperator) ?>",
  "image": "https://samfon.ir/assets/images/simcard-default.jpg",
  "description": "<?= htmlspecialchars($pageDescription ?? 'خرید سیمکارت ' . $simNumber . ' ' . $displayOperator . ' با بهترین قیمت') ?>",
  "sku": "<?= htmlspecialchars($simNumber) ?>",
  "brand": {
    "@type": "Brand",
    "name": "<?= htmlspecialchars($displayOperator) ?>"
  },
  "offers": {
    "@type": "Offer",
    "url": "https://samfon.ir/sim/<?= htmlspecialchars($simNumber) ?>",
    "priceCurrency": "IRR",
    "price": "<?= $displayPrice ?>",
    "availability": "https://schema.org/InStock",
    "seller": {
      "@type": "Organization",
      "name": "سام فون"
    }
  }<?php if ($analysis && ($analysis['score'] ?? 0) >= 50): ?>,
  "additionalProperty": {
    "@type": "PropertyValue",
    "name": "سطح رندی",
    "value": "<?= htmlspecialchars($analysis['rondLevel'] ?? 'معمولی') ?>"
  }<?php endif; ?>
}
</script>

<!-- =============================================================
     اسکرول خودکار دکمه‌های پیشنهادی (فقط یک‌بار، ۴ ثانیه)
     ============================================================= -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // پیدا کردن تمام کانتینرهای اسکرول افقی
    const scrollContainers = document.querySelectorAll('.suggestion-scroll');
    
    scrollContainers.forEach(function(container) {
        let hasInteracted = false;
        let animationFrame;
        let startTime = null;
        const duration = 3000;   // ۳ ثانیه انیمیشن
        const maxScroll = 120;   // حداکثر پیکسل اسکرول
        
        // متوقف کردن در صورت تعامل کاربر
        function stopAnimation() {
            hasInteracted = true;
            if (animationFrame) cancelAnimationFrame(animationFrame);
        }
        
        container.addEventListener('touchstart', stopAnimation, { passive: true });
        container.addEventListener('mousedown', stopAnimation);
        container.addEventListener('wheel', stopAnimation, { passive: true });
        
        // انیمیشن اسکرول رفت و برگشت
        function animateScroll(timestamp) {
            if (hasInteracted) return;
            if (!startTime) startTime = timestamp;
            
            const elapsed = timestamp - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // حرکت سینوسی: رفت به چپ، برگشت به راست
            const scrollPos = Math.sin(progress * Math.PI * 2) * maxScroll;
            container.scrollLeft = Math.max(0, scrollPos);
            
            if (progress < 1) {
                animationFrame = requestAnimationFrame(animateScroll);
            } else {
                container.scrollLeft = 0; // بازگشت به ابتدا
            }
        }
        
        // شروع با تأخیر ۱.۵ ثانیه بعد از لود
        setTimeout(function() {
            if (!hasInteracted && container.scrollWidth > container.clientWidth) {
                animationFrame = requestAnimationFrame(animateScroll);
            }
        }, 1500);
    });
});
</script>

<?php require 'footer.php'; ?>
