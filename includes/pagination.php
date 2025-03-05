                            <!-- قسمت صفحه‌بندی -->
                            <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-3">
                                   <div class="d-flex align-items-center gap-2">
                                          <select class="form-select per-page-select" style="width: 100px">
                                                 <?php foreach ($per_page_options as $option): ?>
                                                        <option value="<?= $option ?>" <?= $per_page == $option ? 'selected' : '' ?>>
                                                               <?= $option ?>
                                                        </option>
                                                 <?php endforeach; ?>
                                          </select>
                                          <span class="d-none d-md-block">آیتم در هر صفحه</span>
                                   </div>

                                   <nav aria-label="Page navigation">
                                          <ul class="pagination flex-wrap justify-content-center">
                                                 <li class="page-item <?= $page == 1 ? 'disabled' : '' ?>">
                                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                                               <span aria-hidden="true">&laquo;</span>
                                                        </a>
                                                 </li>

                                                 <?php if ($start_page > 1): ?>
                                                        <li class="page-item disabled d-none d-md-block">
                                                               <span class="page-link">...</span>
                                                        </li>
                                                 <?php endif; ?>

                                                 <?php for ($p = $start_page; $p <= $end_page; $p++): ?>
                                                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                                               <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>">
                                                                      <?= $p ?>
                                                               </a>
                                                        </li>
                                                 <?php endfor; ?>

                                                 <?php if ($end_page < $total_pages): ?>
                                                        <li class="page-item disabled d-none d-md-block">
                                                               <span class="page-link">...</span>
                                                        </li>
                                                 <?php endif; ?>

                                                 <li class="page-item <?= $page == $total_pages ? 'disabled' : '' ?>">
                                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                                               <span aria-hidden="true">&raquo;</span>
                                                        </a>
                                                 </li>
                                          </ul>
                                   </nav>

                                   <div class="d-none d-lg-block text-muted">
                                          نمایش <?= ($page - 1) * $per_page + 1 ?> تا <?= min($page * $per_page, $total) ?> از <?= $total ?> نتیجه
                                   </div>
                            </div>