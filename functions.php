<?php
/**
 * فایل توابع کمکی – نسخه نهایی و مهاجرت‌یافته به معماری هیبریدی
 * 
 * تغییرات کلیدی این نسخه:
 * ۱. تقویت processSeoParameters برای نگاشت دقیق URLهای فارسی قدیمی به $_GET (جهت ریدایرکت ۳۰۱)
 * ۲. به‌روزرسانی getPriceRange برای پشتیبانی همزمان از فرمت قدیمی (sf_price) و جدید (price)
 * ۳. حفظ کامل توابع کمکی، امنیتی و فرمت‌دهی سیمکارت بدون هیچ حذفیاتی
 */

// ====================== شروع session امن ======================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ====================== توابع مدیریت جلسه و Maintenance ======================
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 3600,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    if (isset($_GET['bypass']) && defined('MAINTENANCE_KEY') && $_GET['bypass'] === MAINTENANCE_KEY) {
        setcookie('maintenance_bypass', MAINTENANCE_KEY, time() + 3600, '/');
    }
}

function validateMaintenanceBypass() {
    if (!defined('MAINTENANCE_KEY')) return false;
    $secret_key = MAINTENANCE_KEY;
    $is_valid = (($_GET['bypass'] ?? '') === $secret_key) ||
                (($_COOKIE['maintenance_bypass'] ?? '') === $secret_key);
    if ($is_valid) {
        setcookie('maintenance_bypass', MAINTENANCE_KEY, [
            'expires' => time() + 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
    return $is_valid;
}

// ====================== توابع پایه ======================
if (!function_exists('set_message')) {
    function set_message($type, $message) {
        if (!isset($_SESSION['messages'][$type])) {
            $_SESSION['messages'][$type] = [];
        }
        $_SESSION['messages'][$type][] = $message;
    }
}

if (!function_exists('redirect')) {
    function redirect($url, $statusCode = 303) {
        header('Location: ' . $url, true, $statusCode);
        exit();
    }
}

if (!function_exists('json_response')) {
    function json_response($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}

if (!function_exists('log_error')) {
    function log_error($message, $context = []) {
        $logEntry = date('Y-m-d H:i:s') . " - " . $message . " " . json_encode($context) . PHP_EOL;
        file_put_contents(__DIR__ . '/../error.log', $logEntry, FILE_APPEND);
    }
}

// ====================== توابع پردازش URL و پارامترها ======================
function parseUrlSegments($url) {
    $cleanedUrl = filter_var(rtrim($url, '/'), FILTER_SANITIZE_URL);
    return explode('/', $cleanedUrl);
}

/**
 * ✅ نسخه تقویت‌شده: نگاشت دقیق URLهای فارسی قدیمی به $_GET برای فعال‌سازی ریدایرکت ۳۰۱ در index.php
 */
function processSeoParameters(array $segments) {
    // نگاشت صریح و ضدخطا برای اطمینان از عملکرد ریدایرکت هوشمند
    $persianToGetMap = [
        'پیش-شماره' => 'pre_number',
        'وضعیت-خط' => 'status',
        'شماره_سیمکارت' => 'sim_number_digits', // پردازش ویژه
        'قیمت' => 'price_range',
        'مرتبسازی-براساس' => 'sort_order', // پردازش ویژه
        'تعداددرصفحه' => 'perPage',
        'فروش-ویژه' => 'special_sale',
        'فقط-با-قیمت' => 'has_price',
        'اپراتور' => 'operator'
    ];

    // پشتیبانی عقب‌سازگار از PARAM_MAP در صورت وجود در config.php
    $map = defined('PARAM_MAP') ? PARAM_MAP : [];
    $reverseMap = array_flip($map);

    for ($i = 0; $i < count($segments); $i++) {
        $key = urldecode(trim($segments[$i]));
        if (empty($key)) continue;

        // ۱. بررسی نگاشت‌های صریح فارسی (اولویت بالا برای مهاجرت)
        if (isset($persianToGetMap[$key])) {
            $targetKey = $persianToGetMap[$key];
            
            // پردازش ویژه برای ارقام سیمکارت
            if ($targetKey === 'sim_number_digits' && isset($segments[$i+1])) {
                $digits = str_split(urldecode($segments[$i+1]));
                foreach ($digits as $index => $digit) {
                    if ($digit !== '_') {
                        $_GET['digit' . (5 + $index)] = $digit;
                    }
                }
                $i++;
                continue;
            }
            
            // پردازش ویژه برای مرتب‌سازی (مثال: قیمت__صعودی)
            if ($targetKey === 'sort_order' && isset($segments[$i+1])) {
                $sortVal = urldecode($segments[$i+1]);
                if (strpos($sortVal, '__') !== false) {
                    list($s, $o) = explode('__', $sortVal, 2);
                    $_GET['sort'] = $s;
                    $_GET['order'] = $o;
                } else {
                    $_GET['sort'] = $sortVal;
                }
                $i++;
                continue;
            }

            // پردازش عادی مقدار
            if (isset($segments[$i+1])) {
                $val = urldecode($segments[$i+1]);
                // اگر وضعیت یا اپراتور بود و شامل خط تیره بود، به آرایه تبدیل کن
                if (in_array($targetKey, ['pre_number', 'operator', 'status']) && str_contains($val, '-')) {
                    $_GET[$targetKey] = explode('-', $val);
                } else {
                    $_GET[$targetKey] = $val;
                }
                $i++;
            }
            continue;
        }

        // ۲. پشتیبانی عقب‌سازگار از PARAM_MAP (برای لینک‌های خیلی قدیمی)
        if (isset($reverseMap[$key]) && isset($segments[$i+1])) {
            $internalKey = $reverseMap[$key];
            $val = urldecode($segments[$i+1]);
            
            if (in_array($internalKey, ['pre_number', 'operator', 'status']) && str_contains($val, '-')) {
                $_GET[$internalKey] = explode('-', $val);
            } else {
                $_GET[$internalKey] = $val;
            }
            $i++;
        }
    }
}

function generateSeoUrl($params) {
    // این تابع برای سازگاری با بخش‌های قدیمی سیستم نگه داشته شده است
    $reverseMap = defined('PARAM_MAP') ? array_flip(PARAM_MAP) : [];
    $parts = [];
    foreach ($params as $key => $value) {
        if (isset($reverseMap[$key])) {
            $persianKey = $reverseMap[$key];
            $parts[] = urlencode($persianKey) . '/' . urlencode($value);
        } elseif (preg_match('/^digit(\d+)$/', $key, $matches)) {
            $parts[] = 'رقم' . $matches[1] . '/' . urlencode($value);
        }
    }
    return '/جستجو/' . implode('/', $parts);
}

/**
 * ✅ منبع واحد حقیقت (Single Source of Truth) برای ساخت URL جستجو
 * این تابع تمام پارامترها را تمیز کرده، مقادیر پیش‌فرض را حذف می‌کند و URL نهایی را می‌سازد.
 */
/**
 * ✅ منبع واحد حقیقت (Single Source of Truth) برای ساخت URL جستجو
 * این تابع باید در سطح جهانی (Global) تعریف شده باشد.
 */
/**
 * ✅ منبع واحد حقیقت (Single Source of Truth) برای ساخت URL جستجو
 * نسخه اصلاح‌شده: افزودن پشتیبانی کامل از پارامتر page برای صفحه‌بندی
 */
/**
 * ✅ منبع واحد حقیقت (Single Source of Truth) - نسخه نهایی و ضدگلوله
 * رفع باگ پارامترهای خالی (مثل order=) با اعتبارسنجی سخت‌گیرانه
 */
if (!function_exists('generateCleanSearchUrl')) {
function generateCleanSearchUrl(array $params): string {
    // ✅ بررسی اینکه آیا می‌توان URL سئو شده سلسله‌مراتبی ساخت
    $hasPre = !empty($params['pre_number']);
    $preVal = is_array($params['pre_number']) ? ($params['pre_number'][0] ?? '') : $params['pre_number'];
    $preVal = ltrim((string)$preVal, '0');
    
    // فقط اگر یک پیش‌شماره داریم و فیلترهای ساده هستند، URL سلسله‌مراتبی بساز
    if ($hasPre && count((array)$params['pre_number']) === 1) {
        $basePath = '/simcards/' . $preVal;
        $queryParams = $params;
        unset($queryParams['pre_number']);
        
        // /simcards/0912/code/1
        if (isset($queryParams['digit5']) && $queryParams['digit5'] !== '' && count($queryParams) <= 3) {
            $code = $queryParams['digit5'];
            unset($queryParams['digit5']);
            $extra = http_build_query($queryParams);
            $extra = str_replace('%2C', ',', $extra);
            return $basePath . '/code/' . $code . ($extra ? '?' . $extra : '');
        }
        
        // /simcards/0912/special
        if (isset($queryParams['special']) && $queryParams['special'] == 1) {
            unset($queryParams['special']);
            $extra = http_build_query($queryParams);
            $extra = str_replace('%2C', ',', $extra);
            return $basePath . '/special' . ($extra ? '?' . $extra : '');
        }
        
        // /simcards/0912 (ساده)
        $extra = http_build_query($queryParams);
        $extra = str_replace('%2C', ',', $extra);
        return $basePath . ($extra ? '?' . $extra : '');
    }
    
    // /simcards/special
    if (isset($params['special']) && $params['special'] == 1 && !$hasPre) {
        $queryParams = $params;
        unset($queryParams['special']);
        $extra = http_build_query($queryParams);
        $extra = str_replace('%2C', ',', $extra);
        return '/simcards/special' . ($extra ? '?' . $extra : '');
    }
    
    // /simcards/operator/mci
    if (isset($params['operator']) && !$hasPre) {
        $opVal = is_array($params['operator']) ? ($params['operator'][0] ?? '') : $params['operator'];
        $queryParams = $params;
        unset($queryParams['operator']);
        $extra = http_build_query($queryParams);
        $extra = str_replace('%2C', ',', $extra);
        return '/simcards/operator/' . $opVal . ($extra ? '?' . $extra : '');
    }
    
    // /simcards/status/1
    if (isset($params['status']) && !$hasPre) {
        $stVal = is_array($params['status']) ? ($params['status'][0] ?? '') : $params['status'];
        $queryParams = $params;
        unset($queryParams['status']);
        $extra = http_build_query($queryParams);
        $extra = str_replace('%2C', ',', $extra);
        return '/simcards/status/' . $stVal . ($extra ? '?' . $extra : '');
    }
    
    // fallback: query string معمولی
    // ... (بقیه کد قبلی بدون تغییر)
    
    
    $clean = [];
        
        $defaults = [
            'perPage' => 20,
            'sort'    => 'price',
            'order'   => 'asc'
        ];

        $opMap = ['همراه اول' => 'mci', 'ایرانسل' => 'irancell', 'رایتل' => 'rightel', 'سامانتل' => 'samantel', 'شاتل موبایل' => 'shatel', 'آریا تل' => 'aria', 'آپتل' => 'aptel'];
        $statusMap = defined('STATUS_LIST') ? array_flip(STATUS_LIST) : [];

        // ۱. پردازش آرایه‌ها و تبدیل به رشته کامایی
        foreach (['pre_number', 'operator', 'status'] as $key) {
            if (!empty($params[$key])) {
                $val = is_array($params[$key]) ? $params[$key] : [$params[$key]];
                $val = array_filter($val, fn($v) => $v !== '');
                
                if (!empty($val)) {
                    if ($key === 'operator') {
                        $val = array_map(fn($v) => $opMap[$v] ?? $v, $val);
                    } elseif ($key === 'status') {
                        $val = array_map(fn($v) => $statusMap[$v] ?? $v, $val);
                    }
                    $clean[$key] = implode(',', $val);
                }
            }
        }

        // ۲. پردازش ارقام (پشتیبانی کامل از عدد '0')
        for ($i = 5; $i <= 11; $i++) {
            $digitKey = "digit$i";
            if (isset($params[$digitKey]) && (string)$params[$digitKey] !== '' && ctype_digit((string)$params[$digitKey])) {
                $clean[$digitKey] = (string)$params[$digitKey];
            }
        }

        // ۳. پردازش قیمت (فقط مقادیر بزرگتر از صفر)
        $min = isset($params['min_price']) ? (int)str_replace(',', '', $params['min_price']) : 0;
        $max = isset($params['max_price']) ? (int)str_replace(',', '', $params['max_price']) : 0;
        if ($min > 0) $clean['min_price'] = $min;
        if ($max > 0) $clean['max_price'] = $max;

        // ۴. پردازش هوشمند و سخت‌گیرانه مقادیر پیش‌فرض (جلوگیری از مقادیر خالی)
        
        // مرتب‌سازی (Sort)
        $sort = isset($params['sort']) ? trim($params['sort']) : $defaults['sort'];
        $sort = ($sort === 'sf_price') ? 'price' : $sort;
        if (!empty($sort) && $sort !== $defaults['sort']) {
            $clean['sort'] = $sort;
        }

        // جهت مرتب‌سازی (Order) - ✅ اصلاح شده برای جلوگیری از order=
        $order = isset($params['order']) ? strtolower(trim($params['order'])) : $defaults['order'];
        if (!empty($order) && in_array($order, ['asc', 'desc'], true) && $order !== $defaults['order']) {
            $clean['order'] = $order;
        }

        // تعداد در صفحه (PerPage)
        $perPage = isset($params['perPage']) ? (int)$params['perPage'] : $defaults['perPage'];
        if ($perPage > 0 && $perPage !== $defaults['perPage']) {
            $clean['perPage'] = $perPage;
        }

        // ۵. پردازش شماره صفحه (فقط صفحات بزرگتر از ۱)
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        if ($page > 1) {
            $clean['page'] = $page;
        }

        // ۶. فیلترهای بولی
        if (!empty($params['has_price'])) $clean['has_price'] = 1;
        if (!empty($params['special_sale']) || !empty($params['special'])) $clean['special'] = 1;

        // ۷. ساخت نهایی و تمیزسازی
        $queryString = http_build_query($clean);
        $queryString = str_replace('%2C', ',', $queryString); // تبدیل %2C به کامای خوانا
        
        return $queryString ? '/search?' . $queryString : '/search';
    }
}

function getCurrentUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return "{$protocol}://{$host}{$uri}";
}

// ====================== توابع اعتبارسنجی و فیلتر ======================
function getSanitizedInput($inputName, $type = 'string', $inputMethod = 'GET') {
    $source = $inputMethod === 'POST' ? $_POST : $_GET;
    if (!isset($source[$inputName])) return null;
    $value = $source[$inputName];

    $filterMap = [
        'string' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'int'    => FILTER_SANITIZE_NUMBER_INT,
        'float'  => FILTER_SANITIZE_NUMBER_FLOAT,
        'email'  => FILTER_SANITIZE_EMAIL,
        'url'    => FILTER_SANITIZE_URL,
        'price'  => FILTER_SANITIZE_NUMBER_FLOAT,
    ];
    $filterType = $filterMap[strtolower($type)] ?? FILTER_DEFAULT;

    if (is_array($value)) {
        return array_map(function($item) use ($filterType) {
            $sanitized = filter_var($item, $filterType, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
            return is_string($sanitized) ? trim(htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8')) : $sanitized;
        }, $value);
    }

    $sanitizedValue = filter_var($value, $filterType, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
    if (is_string($sanitizedValue)) {
        return trim(htmlspecialchars($sanitizedValue, ENT_QUOTES, 'UTF-8'));
    }
    return $sanitizedValue;
}

function getDigitFilters($start, $end) {
    $digits = [];
    for ($i = $start; $i <= $end; $i++) {
        $digits[$i] = getSanitizedInput('digit' . $i, 'int') ?? '';
    }
    return $digits;
}

/**
 * ✅ نسخه به‌روزشده: پشتیبانی همزمان از پارامتر قدیمی (sf_price) و جدید (price)
 */
/**
 * ✅ نسخه اصلاح‌شده: خواندن مستقیم min_price و max_price از URL برای نمایش در فرم
 */
function getPriceRange(): array {
    // ۱. اولویت اول: خواندن مستقیم از پارامترهای فرم (min_price و max_price)
    $min = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
    $max = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
    
    // ۲. اولویت دوم: خواندن از فرمت قدیمی یا هیبریدی (price یا sf_price)
    if ($min == 0 && $max == 0) {
        $priceParam = $_GET['price'] ?? (getSanitizedInput('sf_price', 'array') ?? []);
        if (!empty($priceParam)) {
            if (is_array($priceParam)) {
                $min = isset($priceParam[0]) ? (float)$priceParam[0] : 0;
                $max = isset($priceParam[1]) ? (float)$priceParam[1] : 0;
            } elseif (is_string($priceParam) && str_contains($priceParam, '-')) {
                $parts = explode('-', $priceParam);
                $min = isset($parts[0]) ? (float)$parts[0] : 0;
                $max = (isset($parts[1]) && is_numeric($parts[1])) ? (float)$parts[1] : 0;
            }
        }
    }
    
    return [
        'min' => (float)$min, 
        'max' => (float)$max, 
        'has_range' => ($min > 0 || $max > 0)
    ];
}

function getValidatedSort($input, $allowed = []) {
    static $default_fields = ['price' => 'sf_price', 'date' => 'created_at', 'number' => 'sim_number'];
    if (strpos($input, '__') !== false) {
        $parts = explode('__', $input, 2);
        $input = $parts[0];
    }
    $allowed = array_merge($default_fields, $allowed);
    $input = strtolower(trim($input));
    
    // نگاشت کلمات فارسی به انگلیسی برای اطمینان بیشتر
    $persian_map = ['قیمت' => 'sf_price', 'تاریخ' => 'date', 'شماره' => 'number'];
    $input = $persian_map[$input] ?? $input;
    
    return array_key_exists($input, $allowed) ? $allowed[$input] : $allowed['price'];
}

function getValidatedOrder($input) {
    $order_map = ['asc' => 'asc', 'desc' => 'desc', 'صعودی' => 'asc', 'نزولی' => 'desc'];
    $input = mb_strtolower(trim($input));
    return $order_map[$input] ?? 'asc';
}

function getCurrentPage() {
    $page = $_GET['page'] ?? 1;
    return filter_var($page, FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
}

function getPerPage($allowedOptions = [10, 20, 40, 80, 160]) {
    $mapping = ['1' => 20, '2' => 40, '3' => 80, '4' => 160];
    $input = $_GET['perPage'] ?? '';
    if (array_key_exists($input, $mapping)) return $mapping[$input];
    return filter_var($input, FILTER_VALIDATE_INT, [
        'options' => [
            'default' => 20, 
            'min_range' => min($allowedOptions), 
            'max_range' => max($allowedOptions)
        ]
    ]);
}

function handleDatabaseError(PDOException $e) {
    error_log('Database Error: ' . $e->getMessage());
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development') {
        die("<div class='alert alert-danger'><h4>خطای پایگاه داده:</h4><pre>{$e->getMessage()}</pre><small>File: {$e->getFile()} (Line: {$e->getLine()})</small></div>");
    }
    die("<div class='alert alert-danger'>خطایی در سیستم رخ داده است. لطفاً بعداً دوباره امتحان کنید.</div>");
}

// ====================== توابع مربوط به شماره سیم‌کارت ======================

/**
 * تولید آرایه‌ای از فرمت‌های مختلف برای نمایش شماره (بدون HTML)
 */
function getSimFormats($number) {
    $cleaned = preg_replace('/\D/', '', $number);  // ← \D (هر کاراکتر غیر عددی)
    if (strlen($cleaned) < 11) return [$number];
    $digits = str_split($cleaned);
    $formats = [];
    $patterns = [
        function($d) { return implode('', array_slice($d, 0, 4)) . ' ' . implode('', array_slice($d, 4, 3)) . ' ' . implode('', array_slice($d, 7, 4)); },
        function($d) { return implode('', array_slice($d, 0, 4)) . ' ' . implode('', array_slice($d, 4, 2)) . ' ' . implode('', array_slice($d, 6, 2)) . ' ' . implode('', array_slice($d, 8, 3)); },
        function($d) { return implode('', array_slice($d, 0, 4)) . ' ' . implode('', array_slice($d, 4, 2)) . ' ' . implode('', array_slice($d, 6, 3)) . ' ' . implode('', array_slice($d, 9, 2)); },
        function($d) { return implode('', array_slice($d, 0, 4)) . ' ' . implode('', array_slice($d, 4, 1)) . ' ' . implode('', array_slice($d, 5, 3)) . ' ' . implode('', array_slice($d, 8, 3)); },
        function($d) { return implode('', array_slice($d, 0, 4)) . ' ' . implode('', array_slice($d, 4, 3)) . ' ' . implode('', array_slice($d, 7, 1)) . ' ' . implode('', array_slice($d, 8, 3)); },
    ];
    foreach ($patterns as $pattern) {
        $formats[] = $pattern($digits);
    }
    return array_unique($formats);
}

/**
 * تشخیص اپراتور بر اساس شماره سیم‌کارت
 */
function detectOperator($number, $lookupTable) {
    $cleaned = preg_replace('/\D/u', '', $number);  // ← \D (هر کاراکتر غیر عددی)
    if (strlen($cleaned) !== 11 || $cleaned[0] !== '0') return null;
    $prefixes = [substr($cleaned, 1, 4), substr($cleaned, 1, 3)];
    foreach ($prefixes as $prefix) {
        foreach ($lookupTable as $operator => $prefixesList) {
            if (in_array($prefix, $prefixesList)) {
                return $operator;
            }
        }
    }
    return null;
}

/**
 * تولید جدول جستجوی سریع اپراتورها (مسطح)
 */
function getOperatorLookup($operators) {
    static $lookup = [];
    if (empty($lookup)) {
        foreach ($operators as $operator => $prefixes) {
            foreach ($prefixes as $prefix) {
                $lookup[$prefix] = $operator;
            }
        }
    }
    return $lookup;
}

// ====================== توابع قیمت و فرمت ======================
function calculateDynamicSfPrice($price, $simNumber) {
    // توجه: مسیر config.php را بر اساس ساختار واقعی پروژه خود بررسی کنید
    $configPath = __DIR__ . '/../admin/config.php';
    if (!file_exists($configPath)) return (int)$price;
    
    $config = include $configPath;
    $rule = null;
    
    if (isset($config['price_rules'])) {
        foreach ($config['price_rules'] as $key => $r) {
            if (isset($r['pattern']) && preg_match($r['pattern'], $simNumber)) {
                $rule = $r;
                break;
            }
        }
    }
    
    if (!$rule) $rule = $config['price_rules']['default'] ?? null;
    if (!$rule) return (int)$price;

    $minPercent = $rule['min_percent'] ?? 0;
    $maxPercent = $rule['max_percent'] ?? 0;
    $percent = $minPercent;
    
    if (isset($rule['min_price'], $rule['max_price']) && $price > $rule['min_price'] && ($rule['max_price'] - $rule['min_price']) > 0) {
        $range = $rule['max_price'] - $rule['min_price'];
        $percent = $minPercent + (($price - $rule['min_price']) / $range) * ($maxPercent - $minPercent);
    }
    $percent = max($minPercent, min($maxPercent, $percent));

    $overHead = 0;
    if (isset($rule['min_price'], $rule['max_price']) && $price >= 1 && $price <= 10000000) {
        $minOverHead = 200000;
        $maxOverHead = 2000000;
        if ($price == 1) $overHead = $minOverHead;
        elseif ($price == 10000000) $overHead = $maxOverHead;
        else $overHead = $minOverHead + (($price - 1) / (10000000 - 1)) * ($maxOverHead - $minOverHead);
    }

    $sellPrice = customRound($price * (1 + $percent/100) + $overHead);
    if ($sellPrice - $price < 500000) {
        $sellPrice = $price + 500000;
        $sellPrice = customRound($sellPrice);
    }
    return (int)$sellPrice;
}

function customRound($number) {
    $number = (int)$number;
    $str = (string)$number;
    $length = strlen($str);
    $keep = min(3, $length);
    $base = pow(10, $length - $keep);
    return (int)(((int)($number / $base) + 1) * $base);
}

function opertorParamFormatter($number) {
    $numberStr = (string)$number;
    $remaining = str_split(substr($numberStr, 3)) + array_fill(0, 7, '_');
    return [
        'pre_number' => substr($numberStr, 0, 3),
        'digit5' => $remaining[0] ?? '_',
        'digit6' => $remaining[1] ?? '_',
        'digit7' => $remaining[2] ?? '_',
        'digit8' => $remaining[3] ?? '_',
        'digit9' => $remaining[4] ?? '_',
        'digit10' => $remaining[5] ?? '_',
        'digit11' => $remaining[6] ?? '_'
    ];
}

function generateSortLink($title, $field) {
    $currentSort = $_GET['sort'] ?? 'price';
    $currentOrder = $_GET['order'] ?? 'asc';
    $newOrder = ($currentSort === $field && $currentOrder === 'asc') ? 'desc' : 'asc';
    $params = array_merge($_GET, ['sort' => $field, 'order' => $newOrder, 'page' => 1]);
    
    // استفاده از http_build_query برای سازگاری با معماری هیبریدی جدید
    $queryString = http_build_query($params);
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    return "<a href=\"$currentPath?$queryString\" class=\"sort-link\">$title</a>";
}

function removeAllFilters() {
    return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
}

// ====================== توابع تاریخ ======================

/**
 * تبدیل تاریخ میلادی به شمسی با منطقه زمانی تهران
 */
function convertToShamsi($gregorianDate) {
    try {
        $datetime = new DateTime($gregorianDate, new DateTimeZone('UTC'));
        $datetime->setTimezone(new DateTimeZone('Asia/Tehran'));
        
        $year = (int)$datetime->format('Y');
        $month = (int)$datetime->format('m');
        $day = (int)$datetime->format('d');
        $time = $datetime->format('H:i:s');

        $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

        $gy = $year - 1600;
        $gm = $month - 1;
        $gd = $day - 1;

        $g_day_no = 365 * $gy + (int)(($gy + 3) / 4) - (int)(($gy + 99) / 100) + (int)(($gy + 399) / 400);
        for ($i = 0; $i < $gm; ++$i) {
            $g_day_no += $g_days_in_month[$i];
        }
        if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) {
            $g_day_no++;
        }
        $g_day_no += $gd;

        $j_day_no = $g_day_no - 79;
        $j_np = (int)($j_day_no / 12053);
        $j_day_no %= 12053;
        $jy = 979 + 33 * $j_np + 4 * (int)($j_day_no / 1461);
        $j_day_no %= 1461;
        if ($j_day_no >= 366) {
            $jy += (int)(($j_day_no - 1) / 365);
            $j_day_no = ($j_day_no - 1) % 365;
        }
        for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i) {
            $j_day_no -= $j_days_in_month[$i];
        }
        $jm = $i + 1;
        $jd = $j_day_no + 1;

        return sprintf("%04d/%02d/%02d %s", $jy, $jm, $jd, $time);
    } catch (Exception $e) {
        return $gregorianDate;
    }
}

// ====================== توابع کمکی دیگر ======================

/**
 * تابع قدیمی formatSimNumber (برای سازگاری عقب نگه داشته شده است)
 * توصیه تیم: در ویوهای جدید از getSimFormats استفاده کنید.
 */
function formatSimNumber($number) {
    $formats = getSimFormats($number);
    $html = '<div class="sim-formats">' . implode('<br>', array_map('htmlspecialchars', $formats)) . '</div>';
    return $html;
}

function persianToEnglish($string) {
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($persian, $english, $string);
}

function generateReadableFormats($normalizedNumber, $originalFormat = null) {
    $formats = [];
    if (!empty($originalFormat)) {
        $formats[] = trim($originalFormat);
    }

    $digits = str_split($normalizedNumber);
    if (count($digits) < 11) return $formats;

    $patterns = [
        function($d) { return implode('', array_slice($d, 0, 4)) . ' ' . implode('', array_slice($d, 4, 3)) . ' ' . implode('', array_slice($d, 7, 4)); },
        function($d) { return implode('', array_slice($d, 0, 4)) . ' ' . implode('', array_slice($d, 4, 2)) . ' ' . implode('', array_slice($d, 6, 2)) . ' ' . implode('', array_slice($d, 8, 3)); },
        function($d) { return implode('', array_slice($d, 0, 4)) . ' ' . implode('', array_slice($d, 4, 2)) . ' ' . implode('', array_slice($d, 6, 3)) . ' ' . implode('', array_slice($d, 9, 2)); },
        function($d) { return implode('', array_slice($d, 0, 4)) . ' ' . implode('', array_slice($d, 4, 1)) . ' ' . implode('', array_slice($d, 5, 3)) . ' ' . implode('', array_slice($d, 8, 3)); },
        function($d) { return implode('', array_slice($d, 0, 4)) . ' ' . implode('', array_slice($d, 4, 3)) . ' ' . implode('', array_slice($d, 7, 1)) . ' ' . implode('', array_slice($d, 8, 3)); },
    ];

    foreach ($patterns as $pattern) {
        $formatted = $pattern($digits);
        if (!in_array($formatted, $formats)) {
            $formats[] = $formatted;
        }
    }

    return array_slice(array_unique(array_filter($formats)), 0, 5);
}

// =====================================================================
// ██ تبدیل عدد به حروف فارسی (برای tooltip قیمت)
// =====================================================================
// =====================================================================
// ██ تبدیل عدد به حروف فارسی (برای tooltip قیمت)
// ✅ اصلاح‌شده: threeDigitToWords از حالت nested خارج شد
// =====================================================================

/**
 * تبدیل یک عدد ۳ رقمی به حروف فارسی
 * ✅ تابع مستقل (نه nested) - جلوگیری از Cannot redeclare
 */
if (!function_exists('threeDigitToWordsFa')) {
    function threeDigitToWordsFa(int $num): string {
        if ($num === 0) return '';
        
        $yekan  = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
        $dahgan = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
        $sadgan = ['', 'یکصد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
        $dahyek = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
        
        $parts = [];
        $sad = intdiv($num, 100);
        $rem = $num % 100;
        $dah = intdiv($rem, 10);
        $yek = $rem % 10;
        
        if ($sad > 0) $parts[] = $sadgan[$sad];
        
        if ($rem >= 10 && $rem <= 19) {
            $parts[] = $dahyek[$rem - 10];
        } else {
            if ($dah > 0) $parts[] = $dahgan[$dah];
            if ($yek > 0) $parts[] = $yekan[$yek];
        }
        
        return implode(' و ', $parts);
    }
}

/**
 * تبدیل عدد کامل به حروف فارسی + تومان
 */
if (!function_exists('numberToWordsFa')) {
    function numberToWordsFa($number): string {
        $number = (int)$number;
        if ($number === 0) return 'صفر تومان';
        if ($number < 0) return 'منفی ' . numberToWordsFa(abs($number));
        
        $result = [];
        
        // میلیارد
        $billion = intdiv($number, 1000000000);
        if ($billion > 0) {
            $result[] = threeDigitToWordsFa($billion) . ' میلیارد';
        }
        
        // میلیون
        $million = intdiv($number % 1000000000, 1000000);
        if ($million > 0) {
            $result[] = threeDigitToWordsFa($million) . ' میلیون';
        }
        
        // هزار
        $thousand = intdiv($number % 1000000, 1000);
        if ($thousand > 0) {
            $result[] = threeDigitToWordsFa($thousand) . ' هزار';
        }
        
        // باقی‌مانده
        $rest = $number % 1000;
        if ($rest > 0) {
            $result[] = threeDigitToWordsFa($rest);
        }
        
        return implode(' و ', $result) . ' تومان';
    }
}

// =====================================================================
// ██ لوگوی اپراتور (SVG دایره‌ای با حرف اول)
// =====================================================================
function getOperatorLogo(string $operatorName, int $size = 32): string {
    $logos = [
        'همراه اول'    => ['color' => '#00A3E0', 'letter' => 'M', 'title' => 'همراه اول'],
        'ایرانسل'      => ['color' => '#FFCC00', 'letter' => 'I', 'title' => 'ایرانسل'],
        'رایتل'        => ['color' => '#8DC63F', 'letter' => 'R', 'title' => 'رایتل'],
        'سامانتل'      => ['color' => '#E31E24', 'letter' => 'S', 'title' => 'سامانتل'],
        'شاتل موبایل'  => ['color' => '#FF6600', 'letter' => 'Sh', 'title' => 'شاتل موبایل'],
        'آرین تل'      => ['color' => '#662D91', 'letter' => 'A', 'title' => 'آرین تل'],
        'آپتل'         => ['color' => '#00539B', 'letter' => 'Ap', 'title' => 'آپتل'],
    ];
    
    $logo = $logos[$operatorName] ?? ['color' => '#6c757d', 'letter' => '?', 'title' => $operatorName];
    
    return '<span class="operator-logo" title="' . htmlspecialchars($logo['title']) . '" '
         . 'style="display:inline-flex;align-items:center;justify-content:center;'
         . 'width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;'
         . 'background:' . $logo['color'] . ';color:#fff;font-weight:700;'
         . 'font-size:' . max(10, $size/3) . 'px;flex-shrink:0;" '
         . 'aria-label="' . htmlspecialchars($logo['title']) . '">'
         . htmlspecialchars($logo['letter']) . '</span>';
}

// =====================================================================
// ██ موتور تولید Title و Description سئو
// =====================================================================
function generateSeoMeta(array $params): array {
    $title = '';
    $desc = '';
    $keywords = 'سیم کارت, خرید سیم کارت, سام فون';
    
    $pre = $params['pre_number'] ?? '';
    if (is_array($pre)) $pre = $pre[0] ?? '';
    $pre = ltrim((string)$pre, '0');
    
    $op = $params['operator'] ?? '';
    if (is_array($op)) $op = $op[0] ?? '';
    $opNames = ['mci'=>'همراه اول','irancell'=>'ایرانسل','rightel'=>'رایتل','samantel'=>'سامانتل','shatel'=>'شاتل موبایل','aria'=>'آرین تل','aptel'=>'آپتل'];
    $opName = $opNames[$op] ?? $op;
    
    $status = $params['status'] ?? '';
    if (is_array($status)) $status = $status[0] ?? '';
    $statusNames = ['1'=>'کارکرده','2'=>'درحدصفر','3'=>'صفر به نام','4'=>'صفرپک'];
    $statusName = $statusNames[$status] ?? $status;
    
    $code = $params['digit5'] ?? '';
    $special = !empty($params['special']);
    $minP = isset($params['min_price']) ? (int)$params['min_price'] : 0;
    $maxP = isset($params['max_price']) ? (int)$params['max_price'] : 0;
    
    // ساخت عنوان
    $parts = [];
    if ($special) $parts[] = 'فروش ویژه';
    if ($pre) $parts[] = "سیمکارت {$pre}";
    if ($opName) $parts[] = $opName;
    if ($code !== '') $parts[] = "کد {$code}";
    if ($statusName) $parts[] = $statusName;
    if ($minP > 0 || $maxP > 0) {
        $minM = $minP > 0 ? number_format($minP / 1000000) . ' میلیون' : '';
        $maxM = $maxP > 0 ? number_format($maxP / 1000000) . ' میلیون' : '';
        if ($minM && $maxM) $parts[] = "قیمت {$minM} تا {$maxM}";
        elseif ($minM) $parts[] = "قیمت از {$minM}";
        elseif ($maxM) $parts[] = "قیمت تا {$maxM}";
    }
    
    if (empty($parts)) {
        $title = 'خرید سیمکارت | لیست قیمت و موجودی | سام فون';
        $desc = 'خرید آنلاین سیمکارت با بهترین قیمت. لیست کامل موجودی سیمکارت‌های رند و معمولی در سام فون.';
    } else {
        $title = implode(' | ', $parts) . ' | سام فون';
        $desc = 'خرید آنلاین ' . implode('، ', $parts) . ' با بهترین قیمت. لیست کامل موجودی و ارسال سریع در سام فون.';
    }
    
    // محدودسازی طول
    if (mb_strlen($title) > 70) $title = mb_substr($title, 0, 67) . '... | سام فون';
    if (mb_strlen($desc) > 160) $desc = mb_substr($desc, 0, 157) . '...';
    
    return ['title' => $title, 'desc' => $desc, 'keywords' => $keywords];
}



?>
