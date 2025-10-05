<?php
// index.php
require_once 'config/config.php';

// Ambil kategori untuk homepage
$kategori_populer = getKategoriJasa();

// Ambil pekerja terbaru/terbaik untuk showcase
$stmt = $db->prepare("
    SELECT u.*, p.*, k.nama_kategori, k.icon,
           AVG(r.rating) as avg_rating,
           COUNT(r.id) as total_reviews
    FROM users u
    JOIN pekerja p ON u.id = p.user_id
    JOIN kategori_jasa k ON p.kategori_id = k.id
    LEFT JOIN reviews r ON p.id = r.pekerja_id
    WHERE u.status = 'aktif' AND u.user_type = 'pekerja'
    GROUP BY u.id, p.id
    ORDER BY avg_rating DESC, p.total_pekerjaan DESC
    LIMIT 8
");
$stmt->execute();
$pekerja_terbaik = $stmt->fetchAll();

// Statistik untuk homepage
$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'pekerja' AND status = 'aktif'");
$total_pekerja = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM pesanan WHERE status = 'selesai'");
$total_pekerjaan = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Platform Jasa Lokal Terpercaya</title>
    <meta name="description" content="Temukan pekerja terbaik di sekitar Anda. Tukang, montir, guru les, dan berbagai jasa lainnya.">
    
    <!-- Bootstrap & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 60vh;
            color: white;
        }

        .hero-search {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .category-card {
            border: none;
            transition: all 0.3s ease;
            height: 100%;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .worker-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
        }

        .worker-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .worker-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }

        .rating {
            color: #ffc107;
        }

        .stats-section {
            background: #f8f9fa;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }

        .btn-floating {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            border-radius: 50%;
            width: 60px;
            height: 60px;
        }

        footer {
            background: #343a40;
            color: white;
        }

        @media (max-width: 768px) {
            .hero-section {
                min-height: 50vh;
            }
            
            .hero-search {
                margin: 0 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-handshake text-primary me-2"></i>
                <?= APP_NAME ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="search.php">Cari Pekerja</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Kategori
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach (array_slice($kategori_populer, 0, 8) as $kat): ?>
                                <li>
                                    <a class="dropdown-item" href="search.php?kategori=<?= $kat['id'] ?>">
                                        <i class="<?= $kat['icon'] ?> me-2"></i>
                                        <?= $kat['nama_kategori'] ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="search.php">Lihat Semua</a></li>
                        </ul>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i>
                                <?= $_SESSION['user_name'] ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($_SESSION['user_type'] === 'pekerja'): ?>
                                    <li><a class="dropdown-item" href="pages/pekerja/dashboard.php">Dashboard</a></li>
                                    <li><a class="dropdown-item" href="pages/pekerja/profil.php">Profil Saya</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="pages/pencari/dashboard.php">Dashboard</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="pages/auth/logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="pages/auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary btn-sm ms-2" href="pages/auth/register.php">Daftar</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section d-flex align-items-center">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="display-4 fw-bold mb-4">
                        Temukan Pekerja Terbaik di Sekitar Anda
                    </h1>
                    <p class="lead mb-5">
                        Platform jasa lokal yang menghubungkan Anda dengan ribuan pekerja terampil. 
                        Mulai dari tukang bangunan, montir, guru les, hingga berbagai jasa lainnya.
                    </p>
                    
                    <!-- Search Form -->
                    <div class="hero-search p-4">
                        <form action="search.php" method="GET">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select class="form-select form-select-lg" name="kategori">
                                        <option value="">Semua Kategori</option>
                                        <?php foreach ($kategori_populer as $kat): ?>
                                            <option value="<?= $kat['id'] ?>"><?= $kat['nama_kategori'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control form-control-lg" name="kota" 
                                           placeholder="Kota/Wilayah">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-search me-2"></i>Cari Sekarang
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number"><?= number_format($total_pekerja) ?>+</div>
                        <div class="text-muted">Pekerja Terdaftar</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number"><?= number_format($total_pekerjaan) ?>+</div>
                        <div class="text-muted">Pekerjaan Selesai</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number"><?= count($kategori_populer) ?>+</div>
                        <div class="text-muted">Kategori Jasa</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Kategori Populer -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="fw-bold">Kategori Jasa Populer</h2>
                    <p class="text-muted">Pilih kategori sesuai kebutuhan Anda</p>
                </div>
            </div>
            
            <div class="row g-4">
                <?php foreach (array_slice($kategori_populer, 0, 8) as $kategori): ?>
                    <div class="col-md-3 col-sm-6">
                        <a href="search.php?kategori=<?= $kategori['id'] ?>" class="text-decoration-none">
                            <div class="card category-card h-100 shadow-sm">
                                <div class="card-body text-center p-4">
                                    <div class="mb-3">
                                        <i class="<?= $kategori['icon'] ?> fa-3x text-primary"></i>
                                    </div>
                                    <h5 class="card-title"><?= $kategori['nama_kategori'] ?></h5>
                                    <p class="card-text text-muted small"><?= $kategori['deskripsi'] ?></p>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-5">
                <a href="search.php" class="btn btn-outline-primary btn-lg">
                    Lihat Semua Kategori
                    <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Pekerja Terbaik -->
    <?php if (!empty($pekerja_terbaik)): ?>
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="fw-bold">Pekerja Terbaik</h2>
                    <p class="text-muted">Pekerja dengan rating tertinggi dan pengalaman terpercaya</p>
                </div>
            </div>
            
            <div class="row g-4">
                <?php foreach (array_slice($pekerja_terbaik, 0, 4) as $pekerja): ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="card worker-card shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-3">
                                    <img src="<?= $pekerja['foto_profil'] ? 'uploads/profil/' . $pekerja['foto_profil'] : 'assets/img/default-avatar.png' ?>" 
                                         alt="<?= $pekerja['nama'] ?>" class="worker-avatar me-3">
                                    <div>
                                        <h6 class="mb-1"><?= $pekerja['nama'] ?></h6>
                                        <small class="text-muted"><?= $pekerja['kota'] ?></small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <span class="badge bg-primary mb-2">
                                        <i class="<?= $pekerja['icon'] ?> me-1"></i>
                                        <?= $pekerja['nama_kategori'] ?>
                                    </span>
                                    <p class="small text-muted mb-0">
                                        <?= substr($pekerja['deskripsi_skill'], 0, 80) ?>...
                                    </p>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="rating">
                                        <?php
                                        $rating = $pekerja['avg_rating'] ?: 0;
                                        for ($i = 1; $i <= 5; $i++):
                                        ?>
                                            <i class="fas fa-star <?= $i <= $rating ? '' : 'text-muted' ?>"></i>
                                        <?php endfor; ?>
                                        <small class="ms-1">(<?= $pekerja['total_reviews'] ?>)</small>
                                    </div>
                                    <small class="text-muted"><?= $pekerja['pengalaman_tahun'] ?> tahun</small>
                                </div>
                                
                                <?php if ($pekerja['harga_mulai']): ?>
                                    <div class="mb-3">
                                        <strong class="text-primary">
                                            <?= formatRupiah($pekerja['harga_mulai']) ?>+
                                        </strong>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2">
                                    <a href="detail-pekerja.php?id=<?= $pekerja['user_id'] ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        Lihat Profil
                                    </a>
                                    <a href="https://wa.me/<?= $pekerja['whatsapp'] ?>" 
                                       class="btn btn-success btn-sm" target="_blank">
                                        <i class="fab fa-whatsapp me-1"></i>
                                        WhatsApp
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-5">
                <a href="search.php" class="btn btn-primary btn-lg">
                    Lihat Semua Pekerja
                    <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Call to Action -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <div class="card h-100 border-0" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                        <div class="card-body d-flex align-items-center p-5">
                            <div>
                                <h3 class="fw-bold mb-3">Butuh Jasa?</h3>
                                <p class="mb-4">Temukan pekerja terbaik di sekitar Anda dengan mudah dan cepat.</p>
                                <a href="search.php" class="btn btn-light btn-lg">
                                    Cari Pekerja
                                    <i class="fas fa-search ms-2"></i>
                                </a>
                            </div>
                            <div class="ms-auto d-none d-md-block">
                                <i class="fas fa-search fa-5x opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card h-100 border-0" style="background: linear-gradient(135deg, #007bff 0%, #6610f2 100%); color: white;">
                        <div class="card-body d-flex align-items-center p-5">
                            <div>
                                <h3 class="fw-bold mb-3">Punya Keahlian?</h3>
                                <p class="mb-4">Daftarkan diri Anda dan mulai mendapatkan penghasilan dari keahlian Anda.</p>
                                <a href="pages/auth/register.php" class="btn btn-light btn-lg">
                                    Daftar Sebagai Pekerja
                                    <i class="fas fa-user-plus ms-2"></i>
                                </a>
                            </div>
                            <div class="ms-auto d-none d-md-block">
                                <i class="fas fa-tools fa-5x opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Cara Kerja -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="fw-bold">Cara Kerja</h2>
                    <p class="text-muted">Mudah, cepat, dan terpercaya</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4 text-center">
                    <div class="mb-4">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px;">
                            <i class="fas fa-search fa-2x"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold">1. Cari Pekerja</h5>
                    <p class="text-muted">Cari pekerja berdasarkan kategori, lokasi, atau kata kunci</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="mb-4">
                        <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px;">
                            <i class="fas fa-phone fa-2x"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold">2. Hubungi Langsung</h5>
                    <p class="text-muted">Hubungi pekerja via WhatsApp atau telepon untuk diskusi</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="mb-4">
                        <div class="bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px;">
                            <i class="fas fa-handshake fa-2x"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold">3. Sepakat & Kerja</h5>
                    <p class="text-muted">Sepakat harga dan jadwal, lalu biarkan ahlinya bekerja</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-handshake text-primary me-2"></i>
                        <?= APP_NAME ?>
                    </h5>
                    <p class="text-light">Platform jasa lokal yang menghubungkan Anda dengan pekerja terbaik di sekitar. Mudah, cepat, dan terpercaya.</p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-light"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="https://wa.link/6taxyx" class="text-light"><i class="fab fa-whatsapp fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h6 class="fw-bold mb-3">Menu</h6>
                    <ul class="list-unstyled">
                        <li><a href="search.php" class="text-light text-decoration-none">Cari Pekerja</a></li>
                        <li><a href="pages/auth/register.php" class="text-light text-decoration-none">Daftar</a></li>
                        <li><a href="pages/auth/login.php" class="text-light text-decoration-none">Login</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h6 class="fw-bold mb-3">Kategori Populer</h6>
                    <ul class="list-unstyled">
                        <?php foreach (array_slice($kategori_populer, 0, 5) as $kat): ?>
                            <li>
                                <a href="search.php?kategori=<?= $kat['id'] ?>" class="text-light text-decoration-none">
                                    <?= $kat['nama_kategori'] ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-lg-3">
                    <h6 class="fw-bold mb-3">Kontak</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i> info@jasalokal.com</li>
                        <li class="mb-2"><i class="fas fa-phone me-2"></i> +62 852 1618 2664</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i> Indonesia</li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-light">&copy; 2025 <?= APP_NAME ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-light text-decoration-none me-3">Privacy Policy</a>
                    <a href="#" class="text-light text-decoration-none">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Floating WhatsApp Button -->
    <a href="https://wa.me/6285216182664" class="btn btn-success btn-floating" target="_blank" title="Hubungi Admin">
        <i class="fab fa-whatsapp fa-lg"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Auto-hide navbar on scroll
        let lastScrollTop = 0;
        const navbar = document.querySelector('.navbar');
        
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop) {
                navbar.style.top = '-100px';
            } else {
                navbar.style.top = '0';
            }
            lastScrollTop = scrollTop;
        });
    </script>
</body>
</html>