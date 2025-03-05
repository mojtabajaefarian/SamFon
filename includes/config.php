<?php
session_start();

// فعال‌سازی گزارش خطا در محیط توسعه
if ($_SERVER['SERVER_NAME'] === 'localhost') {
       ini_set('display_errors', 1);
       error_reporting(E_ALL);
} else {
       ini_set('display_errors', 0);
       error_reporting(0);
       ini_set('log_errors', 1);
       ini_set('error_log', DIR . '/../uploads/php_errors.log');
}

// تنظیمات دیتابیس
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'samfon');

// اتصال به MySQL
try {
       $conn = new PDO(
              "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
              DB_USER,
              DB_PASS,
              [
                     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
              ]
       );
} catch (PDOException $e) {
       die("خطای اتصال به دیتابیس: " . $e->getMessage());
}

// توابع کمکی
/*function sanitize($data)
{
       return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}*/
function sanitize($input)
{
       if (is_array($input)) {
              return array_map('sanitize', $input); // بازگشت به تابع برای هر عنصر آرایه
       }
       return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

function format_sim_number($sim_number)
{
       $formats = [
              '0912 xxx xx xx',
              '0912 xx xxx xx',
              '0912 xx xx xxx',
       ];

       $formatted_numbers = [];
       // حذف '0912' از ابتدای شماره ورودی برای پردازش بخشهای بعدی
       $sim_number = substr($sim_number, 4);

       foreach ($formats as $format) {
              $parts = explode(' ', $format);
              $index = 0;
              $formatted_number = '';
              $sim_temp = $sim_number; // استفاده از کپی شماره برای هر فرمت

              foreach ($parts as $part) {
                     if ($part === '0912') {
                            $formatted_number .= '0912 ';
                     } else {
                            $len = strlen($part); // طول بخش فعلی فرمت
                            $formatted_number .= substr($sim_temp, $index, $len) . ' ';
                            $index += $len;
                     }
              }
              $formatted_numbers[] = trim($formatted_number);
       }
       return $formatted_numbers;
}
