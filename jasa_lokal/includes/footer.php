<?php
// includes/footer.php
?>
<!-- Footer -->
<footer class="bg-dark text-light py-5 mt-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-handshake text-primary me-2"></i>
                    <?= APP_NAME ?>
                </h5>
                <p class="mb-3">Platform jasa lokal yang menghubungkan Anda dengan pekerja terbaik di sekitar. Mudah, cepat, dan terpercaya.</p>
                <div class="d-flex gap-3">
                    <a href="#" class="text-light fs-4"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="text-light fs-4"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-light fs-4"><i class="fab fa-whatsapp"></i></a>
                    <a href="#" class="text-light fs-4"><i class="fab fa-telegram"></i></a>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-6">
                <h6 class="fw-bold mb-3">Menu</h6>
                <ul class="list-unstyled">
                    <li><a href="<?= APP_URL ?>" class="text-light text-decoration-none">Beranda</a></li>
                    <li><a href="<?= APP_URL ?>/search.php" class="text-light text-decoration-none">Cari Pekerja</a></li>
                    <li><a href="<?= APP_URL ?>/pages/auth/register.php" class="text-light text-decoration-none">Daftar</a></li>
                    <li><a href="<?= APP_URL ?>/pages/auth/login.php" class="text-light text-decoration-none">Login</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold mb-3">Kategori Populer</h6>
                <ul class="list-unstyled">
                    <?php
                    $footer_categories = getKategoriJasa();
                    foreach (array_slice($footer_categories, 0, 5) as $kat):
                    ?>
                        <li>
                            <a href="<?= APP_URL ?>/search.php?kategori=<?= $kat['id'] ?>" 
                               class="text-light text-decoration-none">
                                <?= $kat['nama_kategori'] ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="col-lg-3">
                <h6 class="fw-bold mb-3">Kontak</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="fas fa-envelope me-2"></i>
                        info@jasalokal.com
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-phone me-2"></i>
                        +62 123 456 789
                    </li>
                    <li class="mb-2">
                        <i class="fab fa-whatsapp me-2"></i>
                        +62 812 3456 789
                    </li>
                    <li>
                        <i class="fas fa-map-marker-alt me-2"></i>
                        Indonesia
                    </li>
                </ul>
            </div>
        </div>
        
        <hr class="my-4">
        
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="mb-0">&copy; 2025 <?= APP_NAME ?>. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="#" class="text-light text-decoration-none me-3">Privacy Policy</a>
                <a href="#" class="text-light text-decoration-none me-3">Terms of Service</a>
                <a href="#" class="text-light text-decoration-none">Help</a>
            </div>
        </div>
    </div>
</footer>

<!-- Floating WhatsApp Button -->
<a href="https://wa.me/6281234567890" class="btn btn-success btn-floating" target="_blank" 
   title="Hubungi Admin" style="position: fixed; bottom: 20px; right: 20px; z-index: 1000; border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
    <i class="fab fa-whatsapp fa-lg"></i>
</a>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>

<!-- Custom Scripts -->
<?= isset($extra_js) ? $extra_js : '' ?>

<!-- Notification System -->
<?php if (isset($_SESSION['notifications']) && !empty($_SESSION['notifications'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php foreach ($_SESSION['notifications'] as $notif): ?>
            showNotification('<?= $notif['type'] ?>', '<?= $notif['title'] ?>', '<?= $notif['message'] ?>');
        <?php endforeach; ?>
    });
</script>
<?php 
unset($_SESSION['notifications']); 
endif; 
?>

</body>
</html>