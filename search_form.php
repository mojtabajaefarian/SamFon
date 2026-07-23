<?php
/**
 * assets/view/search_form.php
 * نسخه نهایی، کامل و هماهنگ با معماری هیبریدی (Hybrid URL)
 */

// استخراج مقادیر از آرایه searchFilters برای استفاده راحت‌تر و تمیز در قالب
$currentPre  = isset($searchFilters['pre_number']) ? (array)$searchFilters['pre_number'] : [];
$status      = isset($searchFilters['status']) ? (array)$searchFilters['status'] : [];
$operator    = isset($searchFilters['operator']) ? (array)$searchFilters['operator'] : [];
$specialSale = $searchFilters['special_sale'] ?? false;
$hasPrice    = $searchFilters['has_price'] ?? false;

// ✅ استخراج صحیح محدوده قیمت از آرایه price_range
$priceRange  = $searchFilters['price_range'] ?? ['min' => 0, 'max' => 0, 'has_range' => false];
$minPrice    = $priceRange['min'] ?? 0;
$maxPrice    = $priceRange['max'] ?? 0;
?>

<!-- فرم جستجوی پیشرفته -->
<section class="advanced-search-section">
    <div style="max-width: 100%;" class="container w-100 px-1">
        <div class="search-panel shadow card">
            <div class="search-header d-flex py-4 card-header" data-bs-toggle="collapse" href="#searchBody">
                <h5 class="title text-center mb-0">🔍 جستجوی پیشرفته سیمکارت</h5>
                <i class="bi bi-chevron-down"></i>
            </div>
            <div class="collapse mt-2 card-body pb-0" id="searchBody">
                <div class="card-body pb-0">
                    <form id="advancedSearch" method="POST" class="needs-validation mb-0" novalidate>
                        <!-- ردیف اول: فیلترهای اصلی -->
                        <div class="row g-3 mb-4 align-items-center" dir="rtl">
                            <!-- تعداد در صفحه -->
                            <div class="col-md-4 col-lg-3">
                                <div class="input-group border rounded-pill">
                                    <span class="input-group-text bg-transparent border-0">تعداد در صفحه</span>
                                    <select class="form-select border-0 bg-transparent" name="perPage">
                                        <?php 
                                        $currentPerPage = $searchFilters['perPage'] ?? 20;
                                        foreach (PER_PAGES as $option): 
                                        ?>
                                            <option value="<?= $option ?>" <?= $currentPerPage == $option ? 'selected' : '' ?>>
                                                <?= $option ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- سوئیچ های سریع -->
                            <div class="col-md-8 col-lg-9">
                                <div class="d-flex flex-wrap justify-content-center">
                                    <div class="form-check form-switch col-6 text-center">
                                        <input class="form-check-input float-none" type="checkbox" id="specialSale" name="special_sale" <?= $specialSale ? 'checked' : '' ?>>
                                        <label class="form-check-label text-danger" for="specialSale">
                                            <i class="bi bi-lightning"></i> فروش ویژه
                                        </label>
                                    </div>
                                    <div class="form-check form-switch col-6 text-center">
                                        <input class="form-check-input float-none" type="checkbox" id="hasPrice" name="has_price" <?= $hasPrice ? 'checked' : '' ?>>
                                        <label class="form-check-label text-success" for="hasPrice">
                                            <i class="bi bi-coin"></i> فقط با قیمت
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ردیف دوم: پیش شماره و شماره سیمکارت -->
                        <div class="row g-3 mb-4" dir="ltr">
                            <!-- پیش شماره ها -->
                            <div class="col-md-6 col-lg-4">
                                <div class="dropdown mx-1">
                                    <button class="btn btn-outline-primary w-100 dropdown-toggle py-1" type="button" dir="rtl" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="d-block">پیش شماره ها</span>
                                        <span class="selected-count d-inline p-2">(<?= !empty($currentPre) ? count($currentPre)." مورد" : 'همه' ?>)</span>
                                    </button>
                                    <ul class="dropdown-menu w-100">
                                        <?php foreach (OPERATORS as $op => $prefixesList): ?>
                                            <?php foreach ($prefixesList as $prefix): ?>
                                                <li class="dropdown-item align-middle" onclick="handleDropdownItemClick(this, event)">
                                                    <input class="form-check-input me-2 pre-number w-10 h-75 pre_number" type="checkbox" value="<?= $prefix ?>" name="pre_number[]" <?= in_array($prefix, $currentPre) ? 'checked' : '' ?>> 
                                                    <span class="d-block pt-2"><?= $prefix ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>

                            <!-- شماره سیمکارت -->
                            <div class="col-md-6 col-lg-8">
                                <div class="digit-group d-flex justify-content-between">
                                    <?php for ($i = 5; $i <= 11; $i++): ?>
                                    <input type="tel" name="digit<?= $i ?>" class="digit-input form-control text-center mx-1" pattern="\d*" maxlength="1" value="<?= htmlspecialchars($searchFilters['digit'.$i] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>

                        <!-- آکاردئون فیلترهای پیشرفته -->
                        <div class="accordion" id="filterAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                                        ⚙️ فیلترهای پیشرفته
                                    </button>
                                </h2>
                                <div id="advancedFilters" class="accordion-collapse collapse">
                                    <div class="accordion-body">
                                        <div class="row g-3">
                                            <!-- محدوده قیمت -->
                                            <div class="col-md-6 col-lg-4">
                                                <div class="input-group">
                                                    <input type="text" class="form-control text-center price-input" placeholder="حداقل قیمت" name="min_price" value="<?= htmlspecialchars($minPrice > 0 ? number_format((int)$minPrice) : '') ?>">
                                                    <input type="text" class="form-control text-center price-input" placeholder="حداکثر قیمت" name="max_price" value="<?= htmlspecialchars($maxPrice > 0 ? number_format((int)$maxPrice) : '') ?>">
                                                </div>
                                            </div>

     
     
     
     
     
     
     
     
     
     
     
     
     <!-- اپراتورها -->
<div class="col-md-6 col-lg-4">
    <div class="dropdown">
        <button class="btn btn-outline-info w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            اپراتورها <span class="selected-count">(<?= !empty($operator) ? count($operator)." مورد" : 'همه' ?>)</span>
        </button>
        <ul class="dropdown-menu w-100">
            <?php 
            // نگاشت کلیدهای تمیز به نام‌های نمایشی فارسی
            $operatorDisplayMap = [
                'mci' => 'همراه اول',
                'irancell' => 'ایرانسل',
                'rightel' => 'رایتل',
                'samantel' => 'سامانتل',
                'shatel' => 'شاتل موبایل',
                'aria' => 'آریا تل',
                'aptel' => 'آپتل'
            ];
            foreach ($operatorDisplayMap as $opKey => $opName): 
            ?>
                <li class="dropdown-item align-middle" onclick="handleDropdownItemClick(this, event)">
                    <!-- ✅ مقدار value اکنون کلید تمیز است (مثلاً mci) -->
                    <input class="form-check-input me-2 pre-number w-10 h-75 operator" 
                           type="checkbox" 
                           value="<?= $opKey ?>" 
                           name="operator[]" 
                           <?= in_array($opKey, $operator) ? 'checked' : '' ?>> 
                    <span class="d-block pt-2"><?= $opName ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<!-- وضعیت -->
<div class="col-md-6 col-lg-4">
    <div class="dropdown">
        <button class="btn btn-outline-warning w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            وضعیت <span class="selected-count">(<?= !empty($status) ? count($status)." مورد" : 'همه' ?>)</span>
        </button>
        <ul class="dropdown-menu w-100">
            <?php foreach (STATUS_LIST as $dbKey => $statusLabel): ?>
                <li class="dropdown-item align-middle" onclick="handleDropdownItemClick(this, event)">
                    <!-- ✅ مقدار value اکنون ID عددی دیتابیس است (مثلاً 1 برای کارکرده) -->
                    <input class="form-check-input me-2 pre-number w-10 h-75 status" 
                           type="checkbox" 
                           value="<?= $dbKey ?>" 
                           name="status[]" 
                           <?= in_array($dbKey, $status) ? 'checked' : '' ?>> 
                    <span class="d-block pt-2"><?= htmlspecialchars($statusLabel) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>







                                            
                                            
                                            
                                            
                                            
                                            
                                            
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

<!-- این فیلدها فقط در صورتی ارسال می‌شوند که کاربر واقعاً آن‌ها را تغییر داده باشد -->
<input type="hidden" id="sort" name="sort" value="<?= htmlspecialchars($searchFilters['sort'] === 'sf_price' ? 'price' : ($searchFilters['sort'] ?? '')) ?>">
<input type="hidden" id="order" name="order" value="<?= htmlspecialchars($searchFilters['order'] === 'desc' ? 'desc' : '') ?>">


                        <!-- دکمه های عملیاتی -->
                        <div class="action-buttons py-4 text-center">
                            <button type="submit" class="btn btn-primary btn-lg my-2">
                                <i class="bi bi-search me-2"></i>اعمال فیلتر
                            </button>
                            <a href="/" class="btn btn-outline-danger btn-lg my-2">
                                <i class="bi bi-trash me-2"></i>حذف فیلتر
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- جاوااسکریپت برای مدیریت دراپداون های چندانتخابی -->
<script>
function handleDropdownItemClick(item, event) {
    const checkbox = item.querySelector('input[type="checkbox"]');
    const isCheckboxClick = event.target === checkbox;
    
    if (isCheckboxClick) {
        updateCount(item.closest('.dropdown'));
    } else {
        const dropdown = item.closest('.dropdown');
        if (checkbox.checked) {
            dropdown.querySelectorAll('input').forEach(cb => cb.checked = false);
        } else {
            dropdown.querySelectorAll('input').forEach(cb => cb.checked = false);
            checkbox.checked = true;            
        }
        updateCount(dropdown);
        setTimeout(() => {
            const dropdownInstance = bootstrap.Dropdown.getInstance(dropdown.querySelector('.dropdown-toggle'));
            if (dropdownInstance) dropdownInstance.hide();
        }, 200);
    }
}

function updateCount(dropdown) {
    const selected = dropdown.querySelectorAll('input:checked').length;
    const countSpan = dropdown.querySelector('.selected-count');
    countSpan.textContent = selected > 0 ? `(${selected} مورد)` : '(همه)';
}

(function() {
    'use strict';

    function formatNumberWithComma(value) {
        let num = value.replace(/[^0-9]/g, '');
        if (num === '') return '';
        return Number(num).toLocaleString('en-US');
    }

    document.querySelectorAll('.price-input').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let cursorPos = this.selectionStart;
            let raw = this.value.replace(/[^0-9]/g, '');
            let formatted = formatNumberWithComma(raw);
            this.value = formatted;
            let newPos = cursorPos + (formatted.length - raw.length);
            if (newPos >= 0) this.setSelectionRange(newPos, newPos);
        });

        input.addEventListener('focus', function() {
            let raw = this.value.replace(/[^0-9]/g, '');
            if (raw !== '') this.value = formatNumberWithComma(raw);
        });

        input.addEventListener('blur', function() {
            let raw = this.value.replace(/[^0-9]/g, '');
            if (raw !== '') this.value = formatNumberWithComma(raw);
        });
    });

    document.getElementById('advancedSearch').addEventListener('submit', function() {
        document.querySelectorAll('.price-input').forEach(function(input) {
            input.value = input.value.replace(/[^0-9]/g, '');
        });
    });
})();
</script>

<!-- استایل های اضافه شده -->
<style>
.input-group-text {
    background: #f8f9fa;
    border-color: #dee2e6;
}
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type="number"] {
    -moz-appearance: textfield;
}
.border-rounded-pill {
    border-radius: 50rem!important;
}
.form-check-input.me-2 {
    float: left;
    width: 10%;
}
.form-check-input.me-2:hover {
    cursor: pointer;
}
.dropdown-item {
    height: 15vh;
    max-height: 50px;
    min-height: 40px;
}
.dropdown-item:hover {
    background: #1416711A !important;
    cursor: pointer !important;
}
.dropdown-menu {
    z-index: 1040;
}
</style>
