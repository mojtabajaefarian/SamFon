<?php
define('DIR', __DIR__);
require DIR . '/includes/config.php';
require DIR . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
       try {
              // اعتبارسنجی فایل
              $allowed = ['xlsx', 'xls', 'csv'];
              $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));

              if (!in_array($ext, $allowed)) {
                     throw new Exception('فقط فایل‌های اکسل (xlsx, xls, csv) مجاز هستند');
              }

              if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                     throw new Exception('خطا در آپلود فایل. کد خطا: ' . $_FILES['excel_file']['error']);
              }

              // ایجاد نام فایل تصادفی
              $filename = uniqid('upload_') . '.' . $ext;
              $targetPath = DIR . '/uploads/' . $filename;

              // انتقال فایل
              if (!move_uploaded_file($_FILES['excel_file']['tmp_name'], $targetPath)) {
                     throw new Exception('خطا در ذخیره فایل آپلود شده');
              }

              // پردازش اکسل
              $spreadsheet = IOFactory::load($targetPath);
              $sheet = $spreadsheet->getActiveSheet();
              $rows = $sheet->toArray();

              // شروع تراکنش
              $conn->beginTransaction();

              // حذف داده‌های قدیمی
              $conn->exec("DELETE FROM sim_cards");

              // درج داده‌های جدید
              $stmt = $conn->prepare("
            INSERT INTO sim_cards (row_number, sim_number, pre_number, sim_type, status, price, special_sale, description, readable_numbers)
            VALUES (:row_number, :sim_number, :pre_number, :sim_type, :status, :price, :special_sale, :description, :readable_numbers)
        ");

              $insertedRows = 0;
              $errorCount = 0;

              foreach ($rows as $index => $row) {
                     if ($index === 0) continue; // رد کردن هدر

                     try {
                            // پاکسازی و استانداردسازی شماره سیم‌کارت
                            $sim_number = preg_replace('/[^0-9]/', '', $row[0]);

                            // اعتبارسنجی شماره سیم‌کارت (11 رقمی و شروع با 0)
                            if (strlen($sim_number) !== 11 || substr($sim_number, 0, 1) !== '0') {
                                   throw new Exception("شماره سیم‌کارت نامعتبر در ردیف {$index} (باید 11 رقمی و با 0 شروع شود)");
                            }
                            // استخراج پیش‌شماره (4 رقم اول)
                            $pre_number = substr($sim_number, 1, 3);

                            $status_map = [
                                   'کارکرده' => 'کارکرده',
                                   'کار کرده' => 'کارکرده',
                                   'در حد صفر' => 'در حد صفر',
                                   'درحد صفر' => 'در حد صفر',
                                   'در حدصفر' => 'در حد صفر',
                                   'درحدصفر' => 'در حد صفر',
                                   'صفر پک' => 'صفر پک',
                                   'صفرپک' => 'صفر پک',
                                   'صفربه نام' => 'صفر به نام',
                                   'صفر بنام' => 'صفر به نام',
                                   'صفربنام' => 'صفر به نام',
                                   'صفر به نام' => 'صفر به نام',
                            ];

                            $status = trim($row[1]); // حذف فاصله‌های اضافی
                            $status = $status_map[$status] ?? 'کارکرده'; // انتخاب مقدار نهایی
                            var_dump("");

                            // تبدیل قیمت به عدد صحیح
                            $price = (int) preg_replace('/[^0-9]/', '', $row[2]);

                            // نوع سیم کارت
                            $sim_type = 'دائمی';

                            // فروش ویژه
                            $special_sale = 0;

                            // توضیحات
                            $description = '';

                            // شماره های خوانا
                            $readable_numbers = implode(',', format_sim_number($sim_number));

                            $data = [
                                   ':row_number' => $index,
                                   ':sim_number' => $sim_number,
                                   ':pre_number' => $pre_number,
                                   ':sim_type' => $sim_type,
                                   ':status' => $status,
                                   ':price' => $price,
                                   ':special_sale' => $special_sale,
                                   ':description' => $description,
                                   ':readable_numbers' => $readable_numbers,
                            ];

                            $stmt->execute($data);
                            $insertedRows++;
                     } catch (Exception $e) {
                            $errorCount++;
                            error_log("خطا در ردیف {$index}: " . $e->getMessage(), 3, DIR . '/uploads/error.log');
                     }
              }
              $conn->commit();
              // جایگزینی پیام موفقیت با این کد:
              $_SESSION['success'] = sprintf(
                     "پردازش با موفقیت انجام شد!<br>
                     رکوردهای موفق: %d<br>
                     رکوردهای ناموفق: %d<br>
                     <small>جزئیات خطاها در لاگ سیستم ثبت شده است</small><br>
                     <small>تاریخ و زمان: %s</small>",
                     $insertedRows,
                     $errorCount,
                     date('Y-m-d H:i:s') // این قسمت تاریخ و زمان را اضافه می‌کند
              );
              header('Location: upload.php');
              exit;
       } catch (Exception $e) {
              $conn->rollBack();
              $error = $e->getMessage();
              error_log('[ ' . date('Y-m-d H:i:s') . ' ] ' . $e->getMessage() . PHP_EOL, 3, DIR . '/uploads/error.log');
       }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">

<head>
       <meta charset="UTF-8">
       <meta name="viewport" content="width=device-width, initial-scale=1.0">
       <title>آپلود فایل اکسل</title>
       <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" integrity="sha384-dpuaG1suU0eT09tx5plTaGMLBsfDLzUCCUXOY2j/LSvXYuG6Bqs43ALlhIqAJVRb" crossorigin="anonymous">
       <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
       <style>
              body {
                     font-family: 'Vazirmatn', sans-serif;
                     background-color: #f8f9fa;
              }

              .upload-card {
                     max-width: 600px;
                     margin: 2rem auto;
                     box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, .15);
              }

              .drag-drop-area {
                     border: 2px dashed #dee2e6;
                     border-radius: 1rem;
                     padding: 2rem;
                     text-align: center;
              }

              .drag-drop-area.dragover {
                     border-color: #0d6efd;
                     background-color: rgba(13, 110, 253, .1);
              }
       </style>
</head>

<body>
       <div class="container py-5">
              <div class="card upload-card">
                     <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">آپلود فایل اکسل</h5>
                     </div>

                     <div class="card-body">
                            <?php if (isset($_SESSION['success'])): ?>
                                   <div class="alert alert-success alert-dismissible fade show">
                                          <?= $_SESSION['success'] ?>
                                          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                   </div>
                            <?php unset($_SESSION['success']);
                            endif; ?>

                            <?php if ($error): ?>
                                   <div class="alert alert-danger alert-dismissible fade show">
                                          <?= $error ?>
                                          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                   </div>
                            <?php endif; ?>

                            <form method="post" enctype="multipart/form-data" id="uploadForm">
                                   <div class="drag-drop-area mb-4" id="dropZone">
                                          <input type="file" name="excel_file" id="fileInput" class="d-none" accept=".xlsx,.xls,.csv">
                                          <label for="fileInput" class="btn btn-outline-primary mb-3">
                                                 <i class="bi bi-file-earmark-spreadsheet"></i> انتخاب فایل
                                          </label>
                                          <p class="text-muted mb-0">فایل را اینجا رها کنید یا کلیک کنید</p>
                                          <small class="text-muted">(فرمت‌های مجاز: xlsx, xls, csv)</small>
                                   </div>

                                   <div class="progress mb-3">
                                          <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                                   </div>

                                   <div class="d-grid gap-2">
                                          <button type="submit" class="btn btn-primary" id="submitBtn">
                                                 <i class="bi bi-upload"></i> آپلود و پردازش
                                          </button>
                                          <a href="index.php" class="btn btn-outline-secondary">
                                                 <i class="bi bi-list-ul"></i> مشاهده لیست
                                          </a>
                                   </div>
                            </form>
                     </div>
              </div>
       </div>

       <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
       <script>
              // Drag & Drop Handling
              const dropZone = document.getElementById('dropZone');
              const fileInput = document.getElementById('fileInput');
              const progress = document.querySelector('.progress');
              const progressBar = document.querySelector('.progress-bar');

              dropZone.addEventListener('dragover', (e) => {
                     e.preventDefault();
                     dropZone.classList.add('dragover');
              });

              dropZone.addEventListener('dragleave', () => {
                     dropZone.classList.remove('dragover');
              });

              dropZone.addEventListener('drop', (e) => {
                     e.preventDefault();
                     dropZone.classList.remove('dragover');
                     fileInput.files = e.dataTransfer.files;
                     updateDropZoneText();
              });

              // File Input Change Handler
              fileInput.addEventListener('change', () => {
                     updateDropZoneText();
              });

              function updateDropZoneText() {
                     if (fileInput.files.length) {
                            dropZone.querySelector('p').textContent = fileInput.files[0].name;
                     } else {
                            dropZone.querySelector('p').textContent = 'فایل را اینجا رها کنید یا کلیک کنید';
                     }
              }

              // Upload Progress
              document.getElementById('uploadForm').addEventListener('submit', function(e) {
                     e.preventDefault();
                     const formData = new FormData(this);
                     const xhr = new XMLHttpRequest();
                     xhr.upload.onprogress = function(event) {
                            if (event.lengthComputable) {
                                   const percentComplete = (event.loaded / event.total) * 100;
                                   progressBar.style.width = percentComplete + '%';
                                   progressBar.textContent = percentComplete.toFixed(0) + '%';
                            }
                     };

                     xhr.onload = function() {
                            if (xhr.status === 200) {
                                   window.location.href = 'upload.php'; // Refresh to show success message
                            } else {
                                   alert('خطا در آپلود فایل');
                            }
                     };

                     xhr.open('POST', 'upload.php');
                     progress.style.display = 'block';
                     xhr.send(formData);
              });
       </script>

</body>

</html>