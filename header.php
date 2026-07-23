<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-KRDRCG4B');</script>
    <!-- End Google Tag Manager -->

    <?php
        // تنظیم URL فعلی
        $canonical_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        // حذف پارامترهای اضافی (در صورت نیاز)
        $canonical_url = strtok($canonical_url, '?');
    ?>
    <link rel="canonical" href="<?php echo $canonical_url; ?>" />
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle ?? 'خرید فروش معاوضه سیمکارت خط 0912 تسهیلات وام') ?> | سام فون</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Preload منابع حیاتی -->
    <!--<link rel="preload" href="/assets/fonts/Vazirmatn/Vazirmatn[wght].woff2" as="font" type="font/woff2" crossorigin="anonymous">-->
    <link rel="preload" href="/assets/css/main.css" as="style">
    <link rel="stylesheet" href="/assets/fonts/Vazirmatn/Vazirmatn-Variable-font-face.css" type="text/css" />

    <!-- استایل‌ها -->
    <link rel="stylesheet" href="/assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
    
    <link rel="stylesheet" href="/assets/css/bootstrap-icons/font/bootstrap-icons.min.css">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "سام فون",
        "url": "https://samfon.ir/",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "https://samfon.ir/search?q={search_term}",
            "query-input": "required name=search_term"
        }
    }
    </script>
</head>
<body>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-KRDRCG4B"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<!--<header class="main-header shadow-sm sticky-top">-->
<header class="main-header shadow-sm">
    <!-- بخش بالایی هدر -->
    <div class="px-3 py-2 border-bottom" style="">
        <div class="container">
<!--            <div class="d-flex flex-wrap align-items-center justify-content-between">-->
            <div class="d-flex flex-wrap align-items-center justify-content-center">
                <!-- لوگو با متن سفید -->
                <a href="/" class="d-flex align-items-center text-white text-decoration-none hover-scale">
                    <img src="/assets/images/logo.png" 
                         alt="لوگو" 
                         width="45" 
                         height="45"
                         class="me-2"
                         loading="lazy">
                    <span class="fs-4 fw-bold d-none d-md-block">سام فون</span>
                </a>

                <!-- ناوبری اصلی -->
                <nav class="nav col-12 col-lg-auto my-2 justify-content-center my-md-0">
                    <ul class="nav-list">
                        <li class="nav-item">
                            <a href="/" class="nav-link text-white">
                                <i class="bi bi-house-door fs-5 me-1"></i>
                                صفحه اصلی
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link text-white">
                                <i class="bi bi-phone fs-5 me-1"></i>
                                محصولات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link text-white">
                                <i class="bi bi-info-circle fs-5 me-1"></i>
                                درباره ما
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#footer" class="nav-link text-white">
                                <i class="bi bi-headset fs-5 me-1"></i>
                                تماس با ما
                            </a>
                        </li>
                    </ul>
                </nav>

                <!-- اقدامات کاربر -->
                <div class="header-actions">
                    <a href="https://samfon.ir/?page=cart" class="btn btn-outline-light me-2">
                        <i class="bi bi-cart3"></i>
                        سبد خرید
                        <span id="cart-count" class="badge bg-danger">3</span>
                    </a>
                    <div class="dropdown d-inline">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="https://samfon.ir/?page=auth"><i class="bi bi-box-arrow-in-right me-2"></i>ورود</a></li>
                            <li><a class="dropdown-item" href="https://samfon.ir/?page=auth"><i class="bi bi-person-plus me-2"></i>ثبت نام</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- بخش اطلاعات تماس و جستجو -->
    <div class="px-3 py-2 border-bottom" style="background: rgba(255, 255, 255, 0.98);">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <!-- اطلاعات تماس -->
                <div class="contact-info">
                    <a href="tel:09126900948" class="text-decoration-none text-dark">
                        <i class="bi bi-telephone-outbound-fill text-primary me-2"></i>
                        <span class="fw-medium" dir="ltr">0912 - 6900 - 948</span>
                    </a>
                </div>

                <!-- جستجو -->
                <form class="search-form flex-grow-1 mx-3">
                    <div class="input-group">
                        <input type="search" 
                               class="form-control" 
                               placeholder="جستجوی محصولات..."
                               aria-label="Search">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</header>

<style>
/* استایل‌های سفارشی */

.main-header {
    background: linear-gradient(135deg, #9d94c8, #2b0353);
    backdrop-filter: blur(5px);
}
.main-header {
    /*
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(5px);
    */
}

.nav-list {
    display: flex;
    gap: 1.5rem;
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-link {
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-2px);
}

.hover-scale {
    transition: transform 0.3s ease;
}
.hover-scale:hover {
    transform: scale(1.05);
}

.search-form {
    max-width: 500px;
}

@media (max-width: 992px) {
    .nav-list {
        gap: 0.75rem;
    }
    
    .nav-link {
        padding: 0.5rem;
        font-size: 0.9rem;
    }
    
    .header-actions .btn {
        padding: 0.375rem 0.75rem;
    }
}

@media (max-width: 768px) {
    .contact-info {
        display: none;
    }
    
    .search-form {
        width: 100%;
        margin: 0.5rem 0;
    }
}
</style>
