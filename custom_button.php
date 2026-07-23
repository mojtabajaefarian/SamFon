<?php
/**
 * دکمه‌های میانبر - نسخه نهایی هماهنگ با URLهای سئو شده
 */
?>

<!-- دکمه فروش ویژه -->
<?php
$paramsSpecial = [
    'pre_number' => '912',
    'special'    => '1',
//    'sort'       => $searchFilters['sort'] ?? null,
//    'order'      => $searchFilters['order'] ?? null,
//    'perPage'    => $searchFilters['perPage'] ?? null
];
$paramsSpecial = array_filter($paramsSpecial, fn($v) => !is_null($v) && $v !== '');
$urlSpecial = buildSeoUrlFromParams($paramsSpecial);
?>
<a href="<?= htmlspecialchars($urlSpecial) ?>" 
   class="btn btn-danger w-100 py-3 shadow-lg hover-scale mb-3">
    <i class="bi bi-lightning-charge-fill me-2"></i>
    فروش ویژه
    <small class="d-block mt-1 text-white-50">تخفیف‌های استثنایی</small>
</a>

<!-- دکمه‌های اسکرول‌شونده -->
<div class="position-relative">
    <!-- ✅ نشانگر اسکرول -->
    <div class="scroll-hint-left" style="position:absolute;left:0;top:50%;transform:translateY(-50%);z-index:2;pointer-events:none;">
        <span style="background:linear-gradient(90deg,#fff,transparent);padding:8px 4px;display:flex;align-items:center;">
            <i class="bi bi-chevron-left text-primary" style="font-size:1.2rem;"></i>
        </span>
    </div>
    <div class="scroll-hint-right" style="position:absolute;right:0;top:50%;transform:translateY(-50%);z-index:2;pointer-events:none;">
        <span style="background:linear-gradient(270deg,#fff,transparent);padding:8px 4px;display:flex;align-items:center;">
            <i class="bi bi-chevron-right text-primary" style="font-size:1.2rem;"></i>
        </span>
    </div>
    
    <div class="btn-scroll-container position-relative overflow-hidden">
        <div class="btn-scroll-wrapper py-3" data-bs-scroll="horizontal" 
             style="overflow-x:auto;white-space:nowrap;scrollbar-width:thin;-webkit-overflow-scrolling:touch;">
            <div class="d-flex gap-2 px-2">
                
                <!-- لیست کامل ۹۱۲ -->
                <a href="/simcards/912" 
                   class="btn btn-primary rounded-pill px-4 py-2 shadow hover-lift flex-shrink-0">
                    <i class="bi bi-list-ul me-2"></i>
                    لیست کامل 912
                </a>
                
                <!-- کدها -->
                <?php 
                $codes = ['یک','دو','سه','چهار','پنج','شش','هفت','هشت','نه','صفر'];
                for ($i = 1; $i <= 10; $i++): 
                    $digit = ($i === 10) ? 0 : $i;
                ?>
                <a href="/simcards/912/code/<?= $digit ?>" 
                   class="btn btn-outline-primary rounded-pill px-4 py-2 hover-lift flex-shrink-0">
                    <span class="badge bg-primary me-2"><?= $digit ?></span>
                    کد <?= $codes[$i-1] ?>
                </a>
                <?php endfor; ?>
                
                <!-- رند -->
                <a href="/simcards/912/rond" 
                   class="btn btn-outline-warning rounded-pill px-4 py-2 hover-lift flex-shrink-0">
                    <i class="bi bi-gem me-2"></i>
                    رند
                </a>
                
                <!-- فروش ویژه ۹۱۲ -->
                <a href="/simcards/912/special" 
                   class="btn btn-outline-danger rounded-pill px-4 py-2 hover-lift flex-shrink-0">
                    <i class="bi bi-lightning me-2"></i>
                    فروش ویژه
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ✅ اسکرول خودکار + نشانگر -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.querySelector('.btn-scroll-wrapper');
    if (!wrapper) return;
    
    let hasInteracted = false;
    let animFrame;
    let startTime = null;
    const duration = 3000;
    const maxScroll = 120;
    
    function stop() {
        hasInteracted = true;
        if (animFrame) cancelAnimationFrame(animFrame);
    }
    wrapper.addEventListener('touchstart', stop, {passive:true});
    wrapper.addEventListener('mousedown', stop);
    wrapper.addEventListener('wheel', stop, {passive:true});
    
    function animate(ts) {
        if (hasInteracted) return;
        if (!startTime) startTime = ts;
        const p = Math.min((ts - startTime) / duration, 1);
        wrapper.scrollLeft = Math.max(0, Math.sin(p * Math.PI * 2) * maxScroll);
        if (p < 1) animFrame = requestAnimationFrame(animate);
        else wrapper.scrollLeft = 0;
    }
    
    setTimeout(() => {
        if (!hasInteracted && wrapper.scrollWidth > wrapper.clientWidth) {
            animFrame = requestAnimationFrame(animate);
        }
    }, 1500);
    
    // مخفی کردن نشانگر در صورت اسکرول
    wrapper.addEventListener('scroll', function() {
        const hintL = document.querySelector('.scroll-hint-left');
        const hintR = document.querySelector('.scroll-hint-right');
        if (hintL) hintL.style.opacity = wrapper.scrollLeft > 10 ? '0' : '1';
        if (hintR) hintR.style.opacity = wrapper.scrollLeft < wrapper.scrollWidth - wrapper.clientWidth - 10 ? '1' : '0';
    });
});
</script>
