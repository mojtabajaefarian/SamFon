<?php
// فعال کردن session در صورت غیرفعال بودن
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}



function format_phone(string $number, bool $isFirst): string {
    if ($isFirst) {
        // 4-4-3
        $parts = [
            substr($number, 0,  4),
            substr($number, 4,  4),
            substr($number, 8)      // بقیه (3 رقم)
        ];
    } else {
        // 4-3-2-2
        $parts = [
            substr($number, 0,  4),
            substr($number, 4,  3),
            substr($number, 7,  2),
            substr($number, 9,  2)
        ];
    }
    // دو تا فاصله بین هر بخش
    return implode('&nbsp;&nbsp;', $parts);
}

?>
<footer class="mt-5 py-4 bg-light text-center" id="footer">
    <div class="container">
        <div class="row g-4">
            <!-- بخش تماس با ما -->
            <div class="col-md-4 mb-3">
                <h5 class="mb-3">تماس با ما</h5>
                <ul class="list-unstyled">
                    <?php
                    $phones = [
                        '09126900948' => 'مدیریت فروش',
                        '09397077979' => 'پشتیبانی',
                        '09397077878' => 'اداری'
                    ];
                    $i=0;
                    foreach ($phones as $number => $title) : $i++?>
                        <li class="mb-2">
                            <a href="tel:<?= $number ?>" 
                               class="text-decoration-none link-dark"
                               data-bs-toggle="tooltip" 
                               title="<?= htmlentities($title) ?>">
                                <i class="bi bi-phone fs-5 me-2"></i>
                                <span dir="ltr">
                                     <?= format_phone($number, $i === 1) ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li>
                        <a href="mailto:info@SamFon.ir" 
                           class="text-decoration-none link-dark">
                            <i class="bi bi-envelope fs-5 me-2"></i>
                            info@SamFon.ir
                        </a>
                    </li>
                </ul>
            </div>

            <!-- لینک‌های مفید -->
            <div class="col-md-4 mb-3">
                <h5 class="mb-3">دسترسی سریع</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="/terms" 
                           class="text-decoration-none link-dark"
                           data-bs-toggle="modal" 
                           data-bs-target="#termsModal">
                            قوانین و مقررات
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="/faq" 
                           class="text-decoration-none link-dark"
                           data-bs-toggle="modal" 
                           data-bs-target="#faqModal">
                            سوالات متداول
                        </a>
                    </li>
                    <li>
                        <a href="/support" 
                           class="text-decoration-none link-dark">
                            <i class="bi bi-headset me-2"></i>پشتیبانی 24/7
                        </a>
                    </li>
                    <li>
                        <a href="/sitemap2.php"
                            class="text-decoration-none link-dark">
                            <i class="bi bi-diagram-3 me-1"></i> نقشه سایت
                        </a>
                    </li>
                </ul>
            </div>

            <!-- نمادهای اعتماد -->
            <div class="col-md-4">
                <h5 class="mb-3">مجوزها و نمادها</h5>
                <div class="d-flex justify-content-center gap-3">
                    <div id="certificate" 
                         <?php /*data-bs-toggle="modal" 
                         data-bs-target="#enamadModal" */?>
                         class="cursor-pointer">
                            <a referrerpolicy='origin'
                                target='_blank'
                                href='https://trustseal.enamad.ir/?id=501676&Code=rdiD6w8OwqI7cN0lXGEydOMlZS3tgZwt'>
                                <img src="/assets/images/enamad-logo.png" 
                                     alt="نماد اعتماد الکترونیک" 
                                     class="enamad-thumbnail"
                                     loading="lazy">
                            </a>
                    </div>
                    <div class="vr"></div>
                    <img src="/assets/images/samandehi-logo.png" 
                         alt="نماد ساماندهی" 
                         class="enamad-thumbnail"
                         loading="lazy">
                </div>
            </div>
        </div>

        <hr class="my-4">

        <!-- کپی رایت و اطلاعات قانونی -->
        <div class="row align-items-center">
            <div class="col-md-6 text-md-start mb-3 mb-md-0">
                <p class="mb-0">
                    © ۱۴۰۳ سام فون.<br class="d-md-none">
                    <span class="d-none d-md-inline"> - </span>
                    تمام حقوق محفوظ است.
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="d-flex justify-content-center justify-content-md-end gap-3">
                    <a href="#" class="text-decoration-none link-dark small">
                        حریم خصوصی
                    </a>
                    <span class="text-muted">|</span>
                    <a href="#" class="text-decoration-none link-dark small">
                        شرایط استفاده
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    
    <!-- دکمه نصب PWA که در ابتدا مخفی است -->
<button id="installButton" style="display: none; position: fixed; bottom: 20px; left: 20px; z-index: 1000; background-color: #4CAF50; color: white; border: none; border-radius: 5px; padding: 12px 20px; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
    📱 نصب برنامه (PWA)
</button>

</footer>

<!-- مودال نماد اعتماد -->
<div class="modal fade" id="enamadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 bg-transparent">
            <div class="modal-body text-center">
                <iframe src="/includes/enamad-info.php" 
                        class="w-100" 
                        style="height: 500px; border: none"
                        loading="lazy"></iframe>
            </div>
        </div>
    </div>
</div>

<style>
.enamad-thumbnail {
    transition: transform 0.3s ease, filter 0.3s ease;
    max-width: 150px;
    cursor: pointer;
}

.enamad-thumbnail:hover {
    transform: scale(1.05) rotate(2deg);
    filter: drop-shadow(0 5px 15px rgba(0,0,0,0.2));
}

.modal-backdrop {
    background-color: rgba(0, 0, 0, 0.7);
}

#paymentResultContent .alert {
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
</style>

<!-- اسکریپت‌های ضروری -->

<!-- ابتدا Bootstrap JS -->
<script src="/assets/js/bootstrap.bundle.min.js" defer></script>
<!-- سپس app.js -->
<script src="/assets/js/app.js" defer></script>
<!-- سایر اسکریپتها -->
<script src="/assets/js/jalaali.min.js" defer></script>

<?php require __DIR__ . '/../js/scripts.php'; ?>

<?php
// پاکسازی session بعد از نمایش نتیجه
if(isset($_SESSION['payment_result'])) {
    unset($_SESSION['payment_result']);
}
?>
<script type="text/javascript">
  ["keydown","touchmove","touchstart","mouseover"].forEach(function(v){window.addEventListener(v,function(){if(!window.isGoftinoAdded){window.isGoftinoAdded=1;var i="Cebmrl",d=document,g=d.createElement("script"),s="https://www.goftino.com/widget/"+i,l=localStorage.getItem("goftino_"+i);g.type="text/javascript",g.async=!0,g.src=l?s+"?o="+l:s;d.getElementsByTagName("head")[0].appendChild(g);}})});
</script>

<script src="/assets/js/install.js"></script>
<script>
    // اتصال تابع نصب به دکمه، پس از بارگذاری کامل صفحه
    document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('installButton');
        if (btn) {
            btn.addEventListener('click', installPWA);
        }
    });
</script>

<!-- دکمه شناور با آیکون دوربین -->
<button id="screenshotFloatingBtn" aria-label="گرفتن اسکرین‌شات از کل صفحه">
  📸
</button>

<!-- کتابخانه html2canvas -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<style>
  /* استایل دکمه شناور */
  #screenshotFloatingBtn {
    position: fixed;
    bottom: 20px;
    left: 20px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6e8efb, #a777e3);
    border: none;
    color: #fff;
    font-size: 30px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
    cursor: pointer;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    outline: none;
    user-select: none;
  }

  /* افکت هاور (وقتی ماوس روی دکمه می‌رود) */
  #screenshotFloatingBtn:hover {
    transform: scale(1.08);
    box-shadow: 0 8px 25px rgba(110, 142, 251, 0.6);
  }

  /* افکت کلیک */
  #screenshotFloatingBtn:active {
    transform: scale(0.92);
  }

  /* ریسپانسیو برای گوشی‌های کوچک */
  @media (max-width: 480px) {
    #screenshotFloatingBtn {
      width: 52px;
      height: 52px;
      font-size: 26px;
      bottom: 15px;
      left: 15px;
    }
  }
</style>

<script>
  document.getElementById('screenshotFloatingBtn').addEventListener('click', function() {
    // تغییر موقت آیکون برای نشان دادن در حال پردازش
    this.textContent = '⏳';
    this.style.transform = 'scale(0.9)';
    this.disabled = true;

    html2canvas(document.body, {
      scale: 2,               // کیفیت بالا
      useCORS: true,          // برای بارگیری تصاویر خارجی
      scrollY: 0,
      windowHeight: document.documentElement.scrollHeight,
      logging: false,
      allowTaint: false,
      backgroundColor: '#ffffff'
    }).then(function(canvas) {
      // ساخت لینک دانلود
      var link = document.createElement('a');
      link.download = 'screenshot-page.png';
      link.href = canvas.toDataURL('image/png');
      link.click();

      // بازگردانی دکمه به حالت اولیه
      var btn = document.getElementById('screenshotFloatingBtn');
      btn.textContent = '📸';
      btn.style.transform = 'scale(1)';
      btn.disabled = false;
    }).catch(function(error) {
      console.error('خطا در گرفتن اسکرین‌شات:', error);
      var btn = document.getElementById('screenshotFloatingBtn');
      btn.textContent = '❌';
      setTimeout(function() {
        btn.textContent = '📸';
        btn.style.transform = 'scale(1)';
        btn.disabled = false;
      }, 2000);
    });
  });
</script>

</body>
</html>
