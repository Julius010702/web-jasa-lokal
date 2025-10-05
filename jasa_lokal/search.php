<?php
// search.php
require_once 'config/config.php';

// Parameter pencarian
$kategori = isset($_GET['kategori']) ? (int)$_GET['kategori'] : null;
$kota = isset($_GET['kota']) ? sanitizeInput($_GET['kota']) : null;
$keyword = isset($_GET['keyword']) ? sanitizeInput($_GET['keyword']) : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;

// Ambil hasil pencarian
$search_results = searchPekerja($kategori, $kota, $keyword, $page, $per_page);
$pekerja_list = $search_results['data'];
$pagination = $search_results['pagination'];

// Ambil kategori untuk filter
$kategori_list = getKategoriJasa();

// Ambil kota untuk suggestions (optional)
$stmt = $db->query("SELECT DISTINCT kota FROM users WHERE kota IS NOT NULL AND kota != '' ORDER BY kota");
$kota_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'Cari Pekerja';
if ($kategori) {
    $stmt = $db->prepare("SELECT nama_kategori FROM kategori_jasa WHERE id = ?");
    $stmt->execute([$kategori]);
    $kategori_name = $stmt->fetchColumn();
    if ($kategori_name) {
        $page_title = 'Cari ' . $kategori_name;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .search-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
        }
        
        .search-form {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .filter-sidebar {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 80px;
        }
        
        .worker-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .worker-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .worker-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .rating {
            color: #ffc107;
        }
        
        .pagination .page-link {
            border-radius: 50%;
            margin: 0 2px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .filter-sidebar {
                margin-bottom: 30px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar (sama seperti index.php) -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-handshake text-primary me-2"></i>
                <?= APP_NAME ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="search.php">Cari Pekerja</a>
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

    <!-- Search Header -->
    <section class="search-header">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="text-center mb-4"><?= $page_title ?></h1>
                    
                    <div class="search-form">
                        <form method="GET">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select class="form-select" name="kategori">
                                        <option value="">Semua Kategori</option>
                                        <?php foreach ($kategori_list as $kat): ?>
                                            <option value="<?= $kat['id'] ?>" <?= $kategori == $kat['id'] ? 'selected' : '' ?>>
                                                <?= $kat['nama_kategori'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="kota" 
                                           value="<?= htmlspecialchars($kota ?: '') ?>" 
                                           placeholder="Kota/Wilayah" list="kotaList">
                                    <datalist id="kotaList">
                                        <?php foreach ($kota_list as $k): ?>
                                            <option value="<?= htmlspecialchars($k) ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="keyword" 
                                           value="<?= htmlspecialchars($keyword ?: '') ?>" 
                                           placeholder="Kata kunci...">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Search Results -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <!-- Sidebar Filter -->
                <div class="col-lg-3">
                    <div class="filter-sidebar">
                        <h5 class="fw-bold mb-3">Filter</h5>
                        
                        <!-- Quick Categories -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-2">Kategori</h6>
                            <?php foreach (array_slice($kategori_list, 0, 8) as $kat): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="filter_kategori" 
                                           value="<?= $kat['id'] ?>" id="cat<?= $kat['id'] ?>"
                                           <?= $kategori == $kat['id'] ? 'checked' : '' ?>
                                           onchange="filterChange()">
                                    <label class="form-check-label" for="cat<?= $kat['id'] ?>">
                                        <i class="<?= $kat['icon'] ?> me-2"></i>
                                        <?= $kat['nama_kategori'] ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="d-grid gap-2">
                            <a href="search.php" class="btn btn-outline-secondary btn-sm">Reset Filter</a>
                        </div>
                    </div>
                </div>
                
                <!-- Results -->
                <div class="col-lg-9">
                    <!-- Results Info -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h5 class="mb-0">
                                Ditemukan <?= $pagination['total'] ?> pekerja
                                <?php if ($kategori || $kota || $keyword): ?>
                                    <div class="mt-2">
                                        <?php if ($kategori): ?>
                                            <span class="badge bg-primary me-1">Kategori: <?= $kategori_name ?></span>
                                        <?php endif; ?>
                                        <?php if ($kota): ?>
                                            <span class="badge bg-success me-1">Kota: <?= $kota ?></span>
                                        <?php endif; ?>
                                        <?php if ($keyword): ?>
                                            <span class="badge bg-warning me-1">Keyword: <?= $keyword ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div>
                            <select class="form-select form-select-sm" onchange="sortChange(this.value)">
                                <option value="rating">Rating Tertinggi</option>
                                <option value="experience">Pengalaman Terbanyak</option>
                                <option value="price_low">Harga Terendah</option>
                                <option value="price_high">Harga Tertinggi</option>
                                <option value="newest">Terbaru</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Worker Cards -->
                    <?php if (empty($pekerja_list)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h4>Tidak ada pekerja ditemukan</h4>
                            <p class="text-muted">Coba ubah kata kunci atau filter pencarian Anda</p>
                            <a href="search.php" class="btn btn-primary">Reset Pencarian</a>
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($pekerja_list as $pekerja): ?>
                                <div class="col-lg-4 col-md-6">
                                    <div class="card worker-card shadow-sm">
                                        <div class="card-body p-4">
                                            <!-- Worker Info -->
                                            <div class="d-flex align-items-center mb-3">
                                                <img src="<?= $pekerja['foto_profil'] ? 'uploads/profil/' . $pekerja['foto_profil'] : 'assets/img/default-avatar.png' ?>" 
                                                     alt="<?= $pekerja['nama'] ?>" class="worker-avatar me-3">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1 fw-bold"><?= $pekerja['nama'] ?></h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?= $pekerja['kota'] ?>
                                                    </small>
                                                </div>
                                                <?php if (isset($pekerja['is_verified']) && $pekerja['is_verified']): ?>
                                                    <i class="fas fa-check-circle text-success" title="Terverifikasi"></i>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Category -->
                                            <div class="mb-3">
                                                <span class="badge bg-primary">
                                                    <i class="<?= $pekerja['icon'] ?> me-1"></i>
                                                    <?= $pekerja['nama_kategori'] ?>
                                                </span>
                                            </div>
                                            
                                            <!-- Description -->
                                            <p class="text-muted small mb-3">
                                                <?= substr($pekerja['deskripsi_skill'], 0, 100) ?>...
                                            </p>
                                            
                                            <!-- Rating & Experience -->
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
                                                <small class="text-muted">
                                                    <i class="fas fa-briefcase me-1"></i>
                                                    <?= $pekerja['pengalaman_tahun'] ?> tahun
                                                </small>
                                            </div>
                                            
                                            <!-- Price -->
                                            <?php if ($pekerja['harga_mulai']): ?>
                                                <div class="mb-3">
                                                    <strong class="text-primary h6">
                                                        <?= formatRupiah($pekerja['harga_mulai']) ?>
                                                        <?php if ($pekerja['harga_hingga'] && $pekerja['harga_hingga'] != $pekerja['harga_mulai']): ?>
                                                            - <?= formatRupiah($pekerja['harga_hingga']) ?>
                                                        <?php else: ?>
                                                            +
                                                        <?php endif; ?>
                                                    </strong>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Action Buttons -->
                                            <div class="d-grid gap-2">
                                                <a href="detail-pekerja.php?id=<?= $pekerja['user_id'] ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>
                                                    Lihat Profil
                                                </a>
                                                <a href="https://wa.me/<?= $pekerja['whatsapp'] ?>" 
                                                   class="btn btn-success btn-sm" target="_blank">
                                                    <i class="fab fa-whatsapp me-1"></i>
                                                    Hubungi WhatsApp
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($pagination['total_pages'] > 1): ?>
                            <nav class="mt-5">
                                <ul class="pagination justify-content-center">
                                    <?php if ($pagination['has_prev']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1])) ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $pagination['current_page'] - 2);
                                    $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <li class="page-item <?= $i == $pagination['current_page'] ? 'active' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($pagination['has_next']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])) ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter change handler
        function filterChange() {
            const form = document.createElement('form');
            form.method = 'GET';
            
            // Preserve existing parameters
            const params = new URLSearchParams(window.location.search);
            
            // Update category filter
            const selectedCategory = document.querySelector('input[name="filter_kategori"]:checked');
            if (selectedCategory) {
                params.set('kategori', selectedCategory.value);
            } else {
                params.delete('kategori');
            }
            
            // Reset page to 1 when filtering
            params.set('page', '1');
            
            // Redirect with new parameters
            window.location.href = '?' + params.toString();
        }

        // Sort change handler
        function sortChange(sortBy) {
            const params = new URLSearchParams(window.location.search);
            params.set('sort', sortBy);
            params.set('page', '1');
            window.location.href = '?' + params.toString();
        }

        // Auto-submit search on Enter
        document.addEventListener('keypress', function(e) {
            if (e.target.matches('input[name="kota"], input[name="keyword"]') && e.key === 'Enter') {
                e.target.closest('form').submit();
            }
        });
    </script>
</body>
</html>