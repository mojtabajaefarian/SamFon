<?php
define('DIR', __DIR__);
require DIR . '/includes/config.php';

// پارامترهای جستجو و فیلتر
$search = sanitize($_GET['search'] ?? '');
$pre_number = sanitize($_GET['pre_number'] ?? '');
$sim_type = sanitize($_GET['sim_type'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$min_price = sanitize($_GET['min_price'] ?? '');
$max_price = sanitize($_GET['max_price'] ?? '');
$special_sale = isset($_GET['special_sale']) ? 1 : 0;
$sort = sanitize($_GET['sort'] ?? 'price');
$order = sanitize($_GET['order'] ?? 'asc');

// اعتبارسنجی مرتب‌سازی // ستون‌های مجاز برای مرتب‌سازی
$allowed_sorts = ['sim_number', 'pre_number', 'sim_type', 'status', 'price'];

// جهت‌های مجاز برای مرتب‌سازی
$allowed_orders = ['asc', 'desc'];

$sort = in_array($sort, $allowed_sorts) ? $sort : 'id';
$order = in_array(strtolower($order), ['asc', 'desc']) ? $order : 'desc';

// ساخت کوئری
$where = [];
$params = [];
$substring_conditions = "";

if (!empty($search)) {
       $search_string = [];
       foreach ($search as $index => $value) {
              $search_string[$index + 5] = $value; // اضافه کردن ۴ واحد به اندیس
       }

       // حذف مقادیر خالی
       $search_string = array_filter($search_string, function ($value) {
              return $value !== '';
       });

       // حذف کاراکترهای غیرمجاز
       $search_string = array_map(function ($value) {
              return preg_replace('/[^0-9]/', '', $value);
       }, $search_string);


       // ساخت شرط‌های SUBSTRING
       foreach ($search_string as $position => $value) {
              $param_name = ":substring_$position";
              $substring_conditions .= " AND SUBSTRING(sim_number, $position, 1) = $param_name";
              $params[$param_name] = $value;
       }

       if (!empty($search_string)) {
              $where[] = "sim_number LIKE :search";
              $params[':search'] = '%' . implode('', $search_string) . '%';
       }
}

// سایر شرط‌ها (pre_number, sim_type, status, min_price, max_price, special_sale)
if (!empty($pre_number)) {
       $where[] = "pre_number = :pre_number";
       $params[':pre_number'] = $pre_number;
}

if (!empty($sim_type) && in_array($sim_type, ['دائمی', 'اعتباری'])) {
       $where[] = "sim_type = :sim_type";
       $params[':sim_type'] = $sim_type;
}

if (!empty($status) && in_array($status, ['صفر پک', 'صفر به نام', 'در حد صفر', 'کارکرده'])) {
       $where[] = "status = :status";
       $params[':status'] = $status;
}

if (!empty($min_price)) {
       $where[] = "price >= :min_price";
       $params[':min_price'] = $min_price;
}

if (!empty($max_price)) {
       $where[] = "price <= :max_price";
       $params[':max_price'] = $max_price;
}

if ($special_sale) {
       $where[] = "special_sale = 1";
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// پارامترهای صفحه‌بندی
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page_options = [40, 100, 250, 500];
$per_page = isset($_GET['per_page']) && in_array($_GET['per_page'], $per_page_options) ? $_GET['per_page'] : 40;
$offset = ($page - 1) * $per_page;

// کوئری اصلی برای شمارش کل رکوردها
$count_query = "SELECT COUNT(*) FROM sim_cards $where_clause $substring_conditions";

try {
       $stmt = $conn->prepare($count_query);
       $stmt->execute($params); // اتصال پارامترها
       $total = $stmt->fetchColumn();
} catch (PDOException $e) {
       die("خطای پایگاه داده: " . $e->getMessage());
}

$total_pages = ceil($total / $per_page);

// کوئری اصلی با صفحه‌بندی
$query = "SELECT * FROM sim_cards $where_clause $substring_conditions ORDER BY $sort $order LIMIT :limit OFFSET :offset";

try {
       $stmt = $conn->prepare($query);

       // اتصال پارامترها
       foreach ($params as $key => $value) {
              $stmt->bindValue($key, $value);
       }

       // اتصال پارامترهای صفحه‌بندی
       $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
       $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

       $stmt->execute();
       $results = $stmt->fetchAll();
} catch (PDOException $e) {
       die("خطای پایگاه داده: " . $e->getMessage());
}
// دریافت پیش‌شماره‌های منحصر به فرد
$pre_numbers = $conn->query("SELECT DISTINCT pre_number FROM sim_cards ORDER BY pre_number")->fetchAll(PDO::FETCH_COLUMN);

// محاسبه محدوده صفحات برای نمایش
$max_visible_pages = 5; // حداکثر صفحات قابل نمایش در نوار صفحه‌بندی
$start_page = max(1, $page - floor($max_visible_pages / 2));
$end_page = min($total_pages, $start_page + $max_visible_pages - 1);

// تنظیم مجدد start_page اگر محدوده از انتها تجاوز کند
if ($end_page - $start_page < $max_visible_pages - 1) {
       $start_page = max(1, $end_page - $max_visible_pages + 1);
}

?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">

<head>
       <meta charset="UTF-8">
       <meta name="viewport" content="width=device-width, initial-scale=1.0">
       <title>مدیریت سیم‌کارت‌ها</title>
       <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" integrity="sha384-dpuaG1suU0eT09tx5plTaGMLBsfDLzUCCUXOY2j/LSvXYuG6Bqs43ALlhIqAJVRb" crossorigin="anonymous">
       <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
       <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
       <style>
              body {
                     font-family: 'Vazirmatn', sans-serif;
              }

              .table-hover tbody tr:hover {
                     background-color: rgba(0, 0, 0, .03);
              }

              .badge {
                     font-weight: 500;
              }

              .pagination {
                     gap: 4px;
              }

              .page-link {
                     min-width: 40px;
                     text-align: center;
              }

              @media (max-width: 576px) {
                     .page-item:not(.active):not(:first-child):not(:last-child) {
                            display: none;
                     }

                     .page-item.disabled:not(.d-md-block) {
                            display: inline-block !important;
                     }

                     .per-page-select {
                            width: 80px !important;
                     }
              }
       </style>
</head>

<body>
       <div class="container mt-4">
              <div class="card shadow-lg">
                     <div class="card-header d-flex justify-content-between align-items-center py-3">
                            <h4 class="mb-0">لیست سیم‌کارت‌های سام فون</h4>
                            <!--<a href="upload.php" class="btn btn-success">
                                   <i class="bi bi-upload me-2"></i>آپلود جدید
                            </a>-->
                            <a href="tel:0912 0440 0912">تماس: 0912 0440 0912</a>
                     </div>

                     <div class="card-body">
                            <form method="get" class="mb-4 border-bottom pb-4">
                                   <div class="row g-3">
                                          <!-- جستجو با 7 باکس -->
                                          <div class="col-md-3">
                                                 <div class="d-flex gap-1 search-boxes" style="direction: ltr;">
                                                        <?php
                                                        // اگر $search_digits تعریف نشده است، یک آرایه خالی ایجاد کنید
                                                        $search_digits = $search_digits ?? array_fill(0, 7, '');

                                                        for ($i = 0; $i < 7; $i++):
                                                        ?>
                                                               <input type="text" name="search[]" class="form-control text-center digit-input"
                                                                      maxlength="1" pattern="[0-9]"
                                                                      value="<?= htmlspecialchars($search_digits[$i] ?? '') ?>">
                                                        <?php endfor; ?>
                                                 </div>
                                          </div>

                                          <!-- پیش شماره Dropdown -->
                                          <div class="col-md-2">
                                                 <select name="pre_number" class="form-select">
                                                        <option value="">همه پیش شماره‌ها</option>
                                                        <?php foreach ($pre_numbers as $pn): ?>
                                                               <option value="<?= $pn ?>" <?= $pn == $pre_number ? 'selected' : '' ?>>
                                                                      <?= $pn ?>
                                                               </option>
                                                        <?php endforeach; ?>
                                                 </select>
                                          </div>
                                          <div class="col-md-2">
                                                 <select name="sim_type" class="form-select">
                                                        <option value="">نوع سیم‌کارت</option>
                                                        <option value="دائمی" <?= $sim_type === 'دائمی' ? 'selected' : '' ?>>دائمی</option>
                                                        <option value="اعتباری" <?= $sim_type === 'اعتباری' ? 'selected' : '' ?>>اعتباری</option>
                                                 </select>
                                          </div>
                                          <div class="col-md-2">
                                                 <select name="status" class="form-select">
                                                        <option value="">وضعیت</option>
                                                        <option value="صفر پک" <?= $status === 'صفر پک' ? 'selected' : '' ?>>صفر پک</option>
                                                        <option value="صفر به نام" <?= $status === 'صفر به نام' ? 'selected' : '' ?>>صفر به نام</option>
                                                        <option value="در حد صفر" <?= $status === 'در حد صفر' ? 'selected' : '' ?>>در حد صفر</option>
                                                        <option value="کارکرده" <?= $status === 'کارکرده' ? 'selected' : '' ?>>کارکرده</option>
                                                 </select>
                                          </div>
                                          <div class="col-md-2">
                                                 <div class="form-check">
                                                        <input type="checkbox" name="special_sale" id="specialSale" class="form-check-input" <?= $special_sale ? 'checked' : '' ?>>
                                                        <label for="specialSale" class="form-check-label">فروش ویژه</label>
                                                 </div>
                                          </div>
                                          <div class="col-md-3">
                                                 <div class="input-group">
                                                        <input type="number" name="min_price" class="form-control" placeholder="حداقل قیمت" value="<?= htmlspecialchars($min_price) ?>">
                                                        <input type="number" name="max_price" class="form-control" placeholder="حداکثر قیمت" value="<?= htmlspecialchars($max_price) ?>">
                                                 </div>
                                          </div>
                                          <div class="col-md-2">
                                                 <button type="submit" class="btn btn-primary w-100">اعمال فیلتر</button>
                                          </div>
                                   </div>
                            </form>

                            <!-- قسمت صفحه‌بندی -->
                            <?php include("includes/pagination.php"); ?>

                            <div class="table-responsive">
                                   <table class="table table-hover align-middle">
                                          <thead class="table-light">
                                                 <tr>
                                                        <th>خوانش</th>

                                                        <th>
                                                               <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'sim_number', 'order' => $sort === 'sim_number' && $order === 'asc' ? 'desc' : 'asc'])) ?>">
                                                                      شماره سیم‌کارت
                                                                      <?= $sort === 'sim_number' ? ($order === 'asc' ? '▲' : '▼') : '' ?>
                                                               </a>
                                                        </th>
                                                        <th>
                                                               <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'pre_number', 'order' => $sort === 'pre_number' && $order === 'asc' ? 'desc' : 'asc'])) ?>">
                                                                      پیش‌شماره
                                                                      <?= $sort === 'pre_number' ? ($order === 'asc' ? '▲' : '▼') : '' ?>
                                                               </a>
                                                        </th>
                                                        <th>
                                                               <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'sim_type', 'order' => $sort === 'sim_type' && $order === 'asc' ? 'desc' : 'asc'])) ?>">
                                                                      نوع
                                                                      <?= $sort === 'sim_type' ? ($order === 'asc' ? '▲' : '▼') : '' ?>
                                                               </a>
                                                        </th>
                                                        <th>
                                                               <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'status', 'order' => $sort === 'status' && $order === 'asc' ? 'desc' : 'asc'])) ?>">
                                                                      وضعیت
                                                                      <?= $sort === 'status' ? ($order === 'asc' ? '▲' : '▼') : '' ?>
                                                               </a>
                                                        </th>
                                                        <th>
                                                               <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'price', 'order' => $sort === 'price' && $order === 'asc' ? 'desc' : 'asc'])) ?>">
                                                                      قیمت
                                                                      <?= $sort === 'price' ? ($order === 'asc' ? '▲' : '▼') : '' ?>
                                                               </a>
                                                        </th>
                                                        <th>
                                                               <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'special_sale', 'order' => $sort === 'special_sale' && $order === 'asc' ? 'desc' : 'asc'])) ?>">
                                                                      فروش ویژه
                                                                      <?= $sort === 'special_sale' ? ($order === 'asc' ? '▲' : '▼') : '' ?>
                                                               </a>
                                                        </th>
                                                        <th>توضیحات</th>
                                                        <th>سفارش</th>
                                                 </tr>
                                          </thead>

                                          <tbody>
                                                 <?php foreach ($results as $row): ?>
                                                        <tr>
                                                               <td style="direction: ltr; text-align: center;">
                                                                      <?php
                                                                      if ($row['readable_numbers']) {
                                                                             // تبدیل رشته‌ی قابل‌خواندن به آرایه
                                                                             $numbers = explode(',', $row['readable_numbers']);
                                                                             // نمایش هر مقدار در یک خط جدید
                                                                             echo implode('<br>', $numbers);
                                                                      } else {
                                                                             // اگر readable_numbers وجود نداشت، شماره سیم‌کارت اصلی را نمایش بده
                                                                             echo $row['sim_number'];
                                                                      }
                                                                      ?>
                                                               </td>
                                                               <td><?= $row['sim_number'] ?></td>

                                                               <td><?= $row['pre_number'] ?></td>
                                                               <td><?= $row['sim_type'] ?></td>
                                                               <td>
                                                                      <?php
                                                                      $badge_color = [
                                                                             'صفر پک' => 'primary',
                                                                             'صفر به نام' => 'info',
                                                                             'در حد صفر' => 'success',
                                                                             'کارکرده' => 'secondary'
                                                                      ][$row['status']];
                                                                      ?>
                                                                      <span class="badge bg-<?= $badge_color ?>"><?= $row['status'] ?></span>
                                                               </td>
                                                               <td><?= $row['price'] === 0 ? 'استعلام بگیرید' : number_format($row['price']) ?></td>
                                                               <td><?= $row['special_sale'] ? 'بله' : 'خیر' ?></td>
                                                               <td><?= $row['description'] ?></td>
                                                               <td>
                                                                      <?php if ($row['price'] === 0): ?>
                                                                             <button class="btn btn-sm btn-outline-warning" onclick="alert('برای استعلام قیمت و خرید این خط با واحد فروش تماس حاصل فرمایید. شماره واحد فروش 0912 0440 0912')">
                                                                                    <i class="bi bi-cart3"></i> سفارش
                                                                             </button>
                                                                      <?php else: ?>
                                                                             <a href="https://zarinp.al/samphone?amount=<?= $row['price'] * 10000 ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                                                    <i class="bi bi-cart3"></i> سفارش
                                                                             </a>
                                                                      <?php endif; ?>
                                                               </td>
                                                        </tr>
                                                 <?php endforeach; ?>
                                          </tbody>
                                   </table>
                            </div>
                            <!-- قسمت صفحه‌بندی -->
                            <?php include("includes/pagination.php"); ?>
                     </div>
              </div>
       </div>
       <script>
              // مدیریت ورودی کاراکترها
              document.querySelectorAll('.digit-input').forEach((input, index, inputs) => {
                     input.addEventListener('input', function(e) {
                            // محدودیت به یک کاراکتر مجاز
                            this.value = this.value.replace(/[^0-9*]/g, '').substring(0, 1);

                            // انتقال به باکس بعدی
                            if (this.value.length === 1 && index < inputs.length - 1) {
                                   inputs[index + 1].focus();
                            }
                     });

                     // مدیریت کلیدهای جهت‌دار
                     input.addEventListener('keydown', function(e) {
                            if (e.key === 'Backspace' && this.value === '' && index > 0) {
                                   inputs[index - 1].focus();
                            }
                     });
              });

              // مدیریت تغییر تعداد در هر صفحه
              document.querySelector('.per-page-select').addEventListener('change', function() {
                     const url = new URL(window.location);
                     url.searchParams.set('per_page', this.value);
                     window.location = url.toString();
              });
       </script>
       <script>
              // مدیریت تغییر تعداد در هر صفحه
              document.querySelector('.per-page-select').addEventListener('change', function() {
                     const url = new URL(window.location);
                     url.searchParams.set('per_page', this.value);
                     url.searchParams.delete('page'); // بازگشت به صفحه اول
                     window.location = url.toString();
              });

              // مدیریت کلیدهای صفحه‌بندی برای دستگاه‌های لمسی
              let touchStartX = 0;
              document.addEventListener('touchstart', e => {
                     touchStartX = e.changedTouches[0].screenX;
              });

              document.addEventListener('touchend', e => {
                     const touchEndX = e.changedTouches[0].screenX;
                     const diffX = touchStartX - touchEndX;

                     if (Math.abs(diffX) > 50) {
                            if (diffX > 0 && <?= $page < $total_pages ? 'true' : 'false' ?>) {
                                   window.location.href = `?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>`;
                            } else if (diffX < 0 && <?= $page > 1 ? 'true' : 'false' ?>) {
                                   window.location.href = `?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>`;
                            }
                     }
              });
       </script>
       <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>