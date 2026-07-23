<?php
/**
 * ============================================================
 * روتر مرکزی سام فون
 * ============================================================
 * تمام URLها را parse کرده و $_GET را تنظیم می‌کند.
 * خروجی: 'exit' (صفحه handle شد) یا 'continue' (ادامه پردازش)
 */

function dispatchRoute(PDO $conn): string {
    $requestPath = trim(rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');
    $pathParts = array_values(array_filter(explode('/', $requestPath), fn($p) => $p !== ''));

    // =========================================================
    // ۱. POST → redirect به URL تمیز
    // =========================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cleanPost = [];
        $opMap = ['همراه اول'=>'mci','ایرانسل'=>'irancell','رایتل'=>'rightel','سامانتل'=>'samantel','شاتل موبایل'=>'shatel','آرین تل'=>'aria','آپتل'=>'aptel'];
        $statusReverse = defined('STATUS_LIST') ? array_flip(STATUS_LIST) : [];

        if (!empty($_POST['pre_number']) && is_array($_POST['pre_number'])) {
            $cleanPost['pre_number'] = implode(',', array_filter($_POST['pre_number']));
        }
        if (!empty($_POST['operator']) && is_array($_POST['operator'])) {
            $ops = array_map(fn($v) => $opMap[$v] ?? $v, $_POST['operator']);
            $cleanPost['operator'] = implode(',', array_filter($ops));
        }
        if (!empty($_POST['status']) && is_array($_POST['status'])) {
            $sts = array_map(fn($v) => $statusReverse[$v] ?? $v, $_POST['status']);
            $cleanPost['status'] = implode(',', array_filter($sts));
        }
        foreach (['digit5','digit6','digit7','digit8','digit9','digit10','digit11'] as $key) {
            if (isset($_POST[$key]) && trim((string)$_POST[$key]) !== '') {
                $cleanPost[$key] = trim((string)$_POST[$key]);
            }
        }
        $min = isset($_POST['min_price']) ? (int)str_replace(',', '', $_POST['min_price']) : 0;
        $max = isset($_POST['max_price']) ? (int)str_replace(',', '', $_POST['max_price']) : 0;
        if ($min > 0) $cleanPost['min_price'] = $min;
        if ($max > 0) $cleanPost['max_price'] = $max;
        if (isset($_POST['sort']) && $_POST['sort'] !== '' && $_POST['sort'] !== 'price' && $_POST['sort'] !== 'sf_price') {
            $cleanPost['sort'] = $_POST['sort'];
        }
        if (isset($_POST['order']) && $_POST['order'] !== '' && $_POST['order'] !== 'asc') {
            $cleanPost['order'] = $_POST['order'];
        }
        if (isset($_POST['perPage']) && (int)$_POST['perPage'] !== 20) {
            $cleanPost['perPage'] = (int)$_POST['perPage'];
        }
        if (!empty($_POST['has_price'])) $cleanPost['has_price'] = 1;
        if (!empty($_POST['special_sale'])) $cleanPost['special'] = 1;

        $queryString = http_build_query($cleanPost);
        $queryString = str_replace('%2C', ',', $queryString);
        
        // ساخت URL سئو شده
        $url = buildSeoUrlFromParams($cleanPost);
        
        header("Location: " . $url, true, 301);
        exit;
    }

    // =========================================================
    // ۲. /sim/0912... → صفحه محصول
    // =========================================================
    if (($pathParts[0] ?? '') === 'sim' && isset($pathParts[1])) {
        $simNumber = preg_replace('/[^0-9]/', '', $pathParts[1]);
        
        if (strlen($simNumber) === 11 && strpos($simNumber, '09') === 0) {
            try {
                $stmt = $conn->prepare("SELECT * FROM sim_cards WHERE sim_number = :simNumber LIMIT 1");
                $stmt->execute([':simNumber' => $simNumber]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    $pre4 = substr($simNumber, 0, 4);
                    $code = substr($simNumber, 4, 1);
                    
                    $operatorName = 'سایر اپراتورها';
                    if (preg_match('/^(0910|0911|0912|0913|0914|0915|0916|0917|0918|0919|0990|0991|0992|0993|0994|0995|0996)$/', $pre4)) {
                        $operatorName = 'همراه اول';
                    } elseif (preg_match('/^(0930|0933|0935|0936|0937|0938|0939|0901|0902|0903|0904|0905|0941)$/', $pre4)) {
                        $operatorName = 'ایرانسل';
                    } elseif (preg_match('/^(0921|0922|0923|0998|0999)$/', $pre4)) {
                        $operatorName = 'رایتل';
                    }

                    $statusMap = [1 => 'کارکرده', 2 => 'درحدصفر', 3 => 'صفر به نام', 4 => 'صفرپک'];
                    $statusText = $statusMap[$product['status']] ?? 'نامشخص';

                    $pageTitle = "خرید سیمکارت $simNumber $operatorName (کد $code) | قیمت و مشخصات";
                    $pageDescription = "خرید سیمکارت $simNumber ($operatorName) با پیش‌شماره $pre4 و کد $code. وضعیت خط: $statusText.";
                    
                    require DIR . '/assets/view/product_detail.php';
                    return 'exit';
                }
            } catch (PDOException $e) {
                // خطای دیتابیس
            }
        }
        
        // ۴۰۴
        header("HTTP/1.0 404 Not Found");
        $pageTitle = "صفحه یافت نشد | سام فون";
        require DIR . '/assets/view/header.php';
        echo "<div class='container my-5 text-center'>";
        echo "<h1 class='display-4 text-danger'>❌ سیمکارت مورد نظر یافت نشد</h1>";
        echo "<p class='lead text-muted'>این سیمکارت ممکن است به فروش رفته باشد یا آدرس اشتباه وارد شده باشد.</p>";
        echo "<a href='/' class='btn btn-primary btn-lg mt-3'><i class='bi bi-house'></i> بازگشت به صفحه اصلی</a>";
        echo "</div>";
        require DIR . '/assets/view/footer.php';
        return 'exit';
    }

    // =========================================================
    // ۳. /simcards/... → صفحات سئو شده
    // =========================================================
    if (($pathParts[0] ?? '') === 'simcards') {
        handleSimcardsRoute($pathParts);
        return 'continue';
    }

    // =========================================================
    // ۴. /search → جستجو با query string
    // =========================================================
    if (($pathParts[0] ?? '') === 'search') {
        // /search/912 → pre_number=912
        if (isset($pathParts[1]) && preg_match('/^\d{3,4}$/', $pathParts[1])) {
            $_GET['pre_number'] = $pathParts[1];
        }
        return 'continue';
    }

    // =========================================================
    // ۵. /جستجو/... → redirect به فرمت جدید (مهاجرت)
    // =========================================================
    if (strpos($requestPath, 'جستجو/') === 0) {
        handleLegacyRedirect();
        return 'exit';
    }

    return 'continue';
}

/**
 * پردازش مسیر /simcards/...
 */
function handleSimcardsRoute(array $pathParts): void {
    global $pageTitle, $seoDescription;
    
    // /simcards/special
    if (isset($pathParts[1]) && $pathParts[1] === 'special') {
        $_GET['special'] = 1;
        $pageTitle = 'فروش ویژه سیمکارت | تخفیف‌های استثنایی | سام فون';
        $seoDescription = 'بهترین تخفیف‌های سیمکارت در سام فون. سیمکارت‌های رند و معمولی با قیمت استثنایی.';
        return;
    }
    
    // /simcards/operator/mci
    if (isset($pathParts[1]) && $pathParts[1] === 'operator' && isset($pathParts[2])) {
        $_GET['operator'] = $pathParts[2];
        $opNames = ['mci'=>'همراه اول','irancell'=>'ایرانسل','rightel'=>'رایتل','samantel'=>'سامانتل','shatel'=>'شاتل موبایل','aria'=>'آرین تل','aptel'=>'آپتل'];
        $opName = $opNames[$pathParts[2]] ?? $pathParts[2];
        $pageTitle = "خرید سیمکارت {$opName} | لیست قیمت و موجودی | سام فون";
        $seoDescription = "خرید آنلاین سیمکارت {$opName} با بهترین قیمت. لیست کامل موجودی در سام فون.";
        return;
    }
    
    // /simcards/status/1
    if (isset($pathParts[1]) && $pathParts[1] === 'status' && isset($pathParts[2])) {
        $_GET['status'] = $pathParts[2];
        $statusNames = ['1'=>'کارکرده','2'=>'درحدصفر','3'=>'صفر به نام','4'=>'صفرپک'];
        $stName = $statusNames[$pathParts[2]] ?? $pathParts[2];
        $pageTitle = "سیمکارت {$stName} | قیمت مناسب و خرید آنلاین | سام فون";
        $seoDescription = "خرید سیمکارت {$stName} با قیمت مناسب. ارسال سریع و انتقال سند رسمی.";
        return;
    }
    
    // /simcards/0912/...
    if (isset($pathParts[1]) && preg_match('/^0?\d{3,4}$/', $pathParts[1])) {
        $preClean = ltrim($pathParts[1], '0');
        $_GET['pre_number'] = $preClean;
        
        $pageTitle = "خرید سیمکارت {$preClean} | قیمت و لیست کامل موجودی | سام فون";
        $seoDescription = "خرید آنلاین سیمکارت {$preClean} با بهترین قیمت. لیست کامل موجودی در سام فون.";
        
        if (isset($pathParts[2])) {
            switch ($pathParts[2]) {
                case 'code':
                    if (isset($pathParts[3]) && ctype_digit($pathParts[3])) {
                        $_GET['digit5'] = $pathParts[3];
                        $pageTitle = "سیمکارت {$preClean} کد {$pathParts[3]} | خرید آنلاین با بهترین قیمت | سام فون";
                        $seoDescription = "خرید سیمکارت {$preClean} کد {$pathParts[3]} با بهترین قیمت. لیست کامل موجودی.";
                    }
                    break;
                    
                case 'rond':
                    $_GET['rond_filter'] = 1;
                    $pageTitle = "سیمکارت {$preClean} رند | ارزان‌ترین قیمت‌های بازار | سام فون";
                    $seoDescription = "خرید سیمکارت {$preClean} رند با ارزان‌ترین قیمت. لیست کامل شماره‌های رند.";
                    break;
                    
                case 'special':
                    $_GET['special'] = 1;
                    $pageTitle = "فروش ویژه سیمکارت {$preClean} | تخفیف‌های استثنایی | سام فون";
                    $seoDescription = "فروش ویژه سیمکارت {$preClean} با تخفیف‌های استثنایی. فرصت محدود.";
                    break;
                    
                case 'price':
                    if (isset($pathParts[3])) {
                        $priceParts = explode('-', $pathParts[3]);
                        if (isset($priceParts[0]) && is_numeric($priceParts[0])) {
                            $_GET['min_price'] = (int)$priceParts[0] * 1000000;
                        }
                        if (isset($priceParts[1]) && is_numeric($priceParts[1])) {
                            $_GET['max_price'] = (int)$priceParts[1] * 1000000;
                        }
                        $priceLabel = isset($priceParts[1]) ? "{$priceParts[0]} تا {$priceParts[1]} میلیون" : "بالای {$priceParts[0]} میلیون";
                        $pageTitle = "سیمکارت {$preClean} قیمت {$priceLabel} | سام فون";
                        $seoDescription = "خرید سیمکارت {$preClean} در بازه قیمتی {$priceLabel}. لیست کامل موجودی.";
                    }
                    break;
            }
        }
    }

    // ✅ تولید خودکار title و description بر اساس پارامترها
    $meta = generateSeoMeta($_GET);
    if (!isset($pageTitle) || $pageTitle === '') {
        $pageTitle = $meta['title'];
    }
    if (!isset($seoDescription) || $seoDescription === '') {
        $seoDescription = $meta['desc'];
    }

}

/**
 * مهاجرت URLهای قدیمی فارسی
 */
function handleLegacyRedirect(): void {
    $currentUrl = getCurrentUrl();
    $segments = parseUrlSegments($currentUrl);
    processSeoParameters($segments);
    
    $cleanPost = [];
    if (isset($_GET['pre_number'])) $cleanPost['pre_number'] = $_GET['pre_number'];
    if (isset($_GET['operator'])) $cleanPost['operator'] = $_GET['operator'];
    if (isset($_GET['status'])) $cleanPost['status'] = $_GET['status'];
    if (isset($_GET['digit5']) && $_GET['digit5'] !== '') $cleanPost['digit5'] = $_GET['digit5'];
    if (isset($_GET['min_price'])) $cleanPost['min_price'] = $_GET['min_price'];
    if (isset($_GET['max_price'])) $cleanPost['max_price'] = $_GET['max_price'];
    if (isset($_GET['sort']) && $_GET['sort'] !== 'price') $cleanPost['sort'] = $_GET['sort'];
    if (isset($_GET['order']) && $_GET['order'] !== 'asc') $cleanPost['order'] = $_GET['order'];
    if (isset($_GET['perPage']) && (int)$_GET['perPage'] !== 20) $cleanPost['perPage'] = $_GET['perPage'];
    if (isset($_GET['special_sale'])) $cleanPost['special'] = 1;
    if (isset($_GET['has_price'])) $cleanPost['has_price'] = 1;
    
    $url = buildSeoUrlFromParams($cleanPost);
    header("Location: https://samfon.ir" . $url, true, 301);
    exit;
}

/**
 * ساخت URL سئو شده از پارامترها
 */
function buildSeoUrlFromParams(array $params): string {
    $pre = $params['pre_number'] ?? '';
    if (is_array($pre)) $pre = $pre[0] ?? '';
    $pre = ltrim((string)$pre, '0');
    
    // /simcards/0912/code/1
    if ($pre !== '' && isset($params['digit5']) && $params['digit5'] !== '') {
        $extra = $params;
        unset($extra['pre_number'], $extra['digit5']);
        $extra = array_filter($extra, fn($v) => $v !== '' && $v !== null);
        $qs = http_build_query($extra);
        $qs = str_replace('%2C', ',', $qs);
        return "/simcards/{$pre}/code/{$params['digit5']}" . ($qs ? "?{$qs}" : '');
    }
    
    // /simcards/0912/special
    if ($pre !== '' && !empty($params['special'])) {
        $extra = $params;
        unset($extra['pre_number'], $extra['special']);
        $extra = array_filter($extra, fn($v) => $v !== '' && $v !== null);
        $qs = http_build_query($extra);
        $qs = str_replace('%2C', ',', $qs);
        return "/simcards/{$pre}/special" . ($qs ? "?{$qs}" : '');
    }
    
    // /simcards/0912/price/50-100
    if ($pre !== '' && (isset($params['min_price']) || isset($params['max_price']))) {
        $minM = isset($params['min_price']) ? (int)($params['min_price'] / 1000000) : 0;
        $maxM = isset($params['max_price']) ? (int)($params['max_price'] / 1000000) : 0;
        $priceSlug = $minM . '-' . $maxM;
        $extra = $params;
        unset($extra['pre_number'], $extra['min_price'], $extra['max_price']);
        $extra = array_filter($extra, fn($v) => $v !== '' && $v !== null);
        $qs = http_build_query($extra);
        $qs = str_replace('%2C', ',', $qs);
        return "/simcards/{$pre}/price/{$priceSlug}" . ($qs ? "?{$qs}" : '');
    }
    
    // /simcards/0912
    if ($pre !== '') {
        $extra = $params;
        unset($extra['pre_number']);
        $extra = array_filter($extra, fn($v) => $v !== '' && $v !== null);
        $qs = http_build_query($extra);
        $qs = str_replace('%2C', ',', $qs);
        return "/simcards/{$pre}" . ($qs ? "?{$qs}" : '');
    }
    
    // /simcards/special
    if (!empty($params['special'])) {
        $extra = $params;
        unset($extra['special']);
        $extra = array_filter($extra, fn($v) => $v !== '' && $v !== null);
        $qs = http_build_query($extra);
        $qs = str_replace('%2C', ',', $qs);
        return "/simcards/special" . ($qs ? "?{$qs}" : '');
    }
    
    // /simcards/operator/mci
    if (isset($params['operator']) && $params['operator'] !== '') {
        $op = is_array($params['operator']) ? $params['operator'][0] : $params['operator'];
        $extra = $params;
        unset($extra['operator']);
        $extra = array_filter($extra, fn($v) => $v !== '' && $v !== null);
        $qs = http_build_query($extra);
        $qs = str_replace('%2C', ',', $qs);
        return "/simcards/operator/{$op}" . ($qs ? "?{$qs}" : '');
    }
    
    // /simcards/status/1
    if (isset($params['status']) && $params['status'] !== '') {
        $st = is_array($params['status']) ? $params['status'][0] : $params['status'];
        $extra = $params;
        unset($extra['status']);
        $extra = array_filter($extra, fn($v) => $v !== '' && $v !== null);
        $qs = http_build_query($extra);
        $qs = str_replace('%2C', ',', $qs);
        return "/simcards/status/{$st}" . ($qs ? "?{$qs}" : '');
    }
    
    // fallback: /search?...
    $clean = array_filter($params, fn($v) => $v !== '' && $v !== null);
    $qs = http_build_query($clean);
    $qs = str_replace('%2C', ',', $qs);
    return '/search' . ($qs ? "?{$qs}" : '');
}
