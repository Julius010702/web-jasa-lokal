<?php
// pages/pekerja/reviews.php
require_once '../../config/config.php';

// Cek login dan tipe user
if (!isLoggedIn() || $_SESSION['user_type'] !== 'pekerja') {
    redirect('../../pages/auth/login.php');
}

$user = getCurrentUser();

// Ambil data profil pekerja
$stmt = $db->prepare("
    SELECT p.*, k.nama_kategori 
    FROM pekerja p 
    LEFT JOIN kategori_jasa k ON p.kategori_id = k.id 
    WHERE p.user_id = ?
");
$stmt->execute([$user['id']]);
$profil_pekerja = $stmt->fetch();

// Pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter dan sorting
$sort = $_GET['sort'] ?? 'terbaru';
$rating_filter = $_GET['rating'] ?? '';

// Build query conditions
$where_conditions = ['r.pekerja_id = ?'];
$params = [$user['id']];

if ($rating_filter) {
    $where_conditions[] = 'r.rating = ?';
    $params[] = $rating_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Sorting
$order_by = match($sort) {
    'terbaru' => 'r.created_at DESC',
    'terlama' => 'r.created_at ASC',
    'rating_tertinggi' => 'r.rating DESC',
    'rating_terendah' => 'r.rating ASC',
    default => 'r.created_at DESC'
};

// Ambil ulasan dengan pagination
$stmt = $db->prepare("
    SELECT r.*, u.nama as pencari_nama, u.foto_profil as pencari_foto,
           p.deskripsi_pekerjaan, p.estimasi_harga
    FROM reviews r 
    JOIN users u ON r.pencari_id = u.id 
    JOIN pesanan p ON r.pesanan_id = p.id
    WHERE {$where_clause}
    ORDER BY {$order_by}
    LIMIT {$limit} OFFSET {$offset}
");
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Total reviews untuk pagination
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM reviews r 
    WHERE {$where_clause}
");
$stmt->execute($params);
$total_reviews = $stmt->fetchColumn();
$total_pages = ceil($total_reviews / $limit);

// Statistik ulasan
$stmt = $db->prepare("
    SELECT 
        AVG(rating) as avg_rating,
        COUNT(*) as total_reviews,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
    FROM reviews 
    WHERE pekerja_id = ?
");
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ulasan & Rating - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 0;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .main-content {
            padding: 20px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .stat-card {
            border-radius: 15px;
            border: none;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .review-card {
            border-radius: 15px;
            border: none;
            transition: all 0.3s;
            margin-bottom: 1rem;
        }
        
        .review-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .rating-stars {
            color: #ffc107;
        }
        
        .rating-breakdown {
            font-size: 0.9rem;
        }
        
        .rating-bar {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            overflow: hidden;
        }
        
        .rating-fill {
            height: 100%;
            background: #ffc107;
            transition: width 0.3s;
        }
        
        .filter-btn {
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.9rem;
        }
        
        .review-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 0;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 sidebar p-0">
                <div class="p-3">
                    <h5 class="mb-0">
                        <i class="fas fa-tools me-2"></i>
                        Dashboard Pekerja
                    </h5>
                </div>
                
                <nav class="nav flex-column px-3">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-home me-2"></i>
                        Dashboard
                    </a>
                    <a class="nav-link" href="profil.php">
                        <i class="fas fa-user me-2"></i>
                        Profil Saya
                    </a>
                    <a class="nav-link" href="portofolio.php">
                        <i class="fas fa-images me-2"></i>
                        Portofolio
                    </a>
                    <a class="nav-link" href="pesanan.php">
                        <i class="fas fa-clipboard-list me-2"></i>
                        Pesanan
                    </a>
                    <a class="nav-link active" href="reviews.php">
                        <i class="fas fa-star me-2"></i>
                        Ulasan
                    </a>
                    <a class="nav-link" href="pengaturan.php">
                        <i class="fas fa-cog me-2"></i>
                        Pengaturan
                    </a>
                    
                    <hr class="my-3">
                    
                    <a class="nav-link" href="../../detail-pekerja.php?id=<?= $user['id'] ?>" target="_blank">
                        <i class="fas fa-external-link-alt me-2"></i>
                        Lihat Profil Publik
                    </a>
                    <a class="nav-link" href="../../index.php">
                        <i class="fas fa-home me-2"></i>
                        Beranda
                    </a>
                    <a class="nav-link" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>
                        Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10 col-md-9 main-content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold mb-1">Ulasan & Rating</h2>
                        <p class="text-muted mb-0">Lihat feedback dari pelanggan Anda</p>
                    </div>
                </div>
                
                <!-- Rating Overview -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-4">
                        <div class="card stat-card text-center">
                            <div class="card-body">
                                <h1 class="display-4 fw-bold text-warning mb-0">
                                    <?= $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : '0.0' ?>
                                </h1>
                                <div class="rating-stars mb-2">
                                    <?php 
                                    $rating = $stats['avg_rating'] ?: 0;
                                    for ($i = 1; $i <= 5; $i++): 
                                    ?>
                                        <i class="fas fa-star <?= $i <= $rating ? '' : 'text-muted' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-muted mb-0">
                                    Berdasarkan <?= $stats['total_reviews'] ?> ulasan
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-8">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h6 class="mb-3">Breakdown Rating</h6>
                                <?php 
                                $total = $stats['total_reviews'] ?: 1; // Prevent division by zero
                                for ($i = 5; $i >= 1; $i--): 
                                    $count = $stats["rating_{$i}"] ?: 0;
                                    $percentage = ($count / $total) * 100;
                                ?>
                                    <div class="d-flex align-items-center mb-2 rating-breakdown">
                                        <div class="me-2" style="width: 60px;">
                                            <?= $i ?> <i class="fas fa-star text-warning"></i>
                                        </div>
                                        <div class="rating-bar flex-grow-1 me-2">
                                            <div class="rating-fill" style="width: <?= $percentage ?>%"></div>
                                        </div>
                                        <div style="width: 50px;">
                                            <?= $count ?> (<?= number_format($percentage, 1) ?>%)
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter dan Sort -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">FILTER RATING</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="?sort=<?= $sort ?>" class="btn filter-btn <?= !$rating_filter ? 'btn-primary' : 'btn-outline-secondary' ?>">
                                        Semua
                                    </a>
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <a href="?sort=<?= $sort ?>&rating=<?= $i ?>" 
                                           class="btn filter-btn <?= $rating_filter == $i ? 'btn-warning' : 'btn-outline-secondary' ?>">
                                            <?= $i ?> <i class="fas fa-star"></i>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">URUTKAN</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="?sort=terbaru<?= $rating_filter ? '&rating=' . $rating_filter : '' ?>" 
                                       class="btn filter-btn <?= $sort == 'terbaru' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                                        Terbaru
                                    </a>
                                    <a href="?sort=rating_tertinggi<?= $rating_filter ? '&rating=' . $rating_filter : '' ?>" 
                                       class="btn filter-btn <?= $sort == 'rating_tertinggi' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                                        Rating Tertinggi
                                    </a>
                                    <a href="?sort=rating_terendah<?= $rating_filter ? '&rating=' . $rating_filter : '' ?>" 
                                       class="btn filter-btn <?= $sort == 'rating_terendah' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                                        Rating Terendah
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Daftar Ulasan -->
                <?php if (empty($reviews)): ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="empty-state">
                                <i class="fas fa-star"></i>
                                <h5>Belum Ada Ulasan</h5>
                                <p class="text-muted">Selesaikan pesanan dengan baik untuk mendapat ulasan pertama Anda</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="card review-card">
                            <div class="card-body">
                                <div class="d-flex mb-3">
                                    <img src="<?= $review['pencari_foto'] ? '../../uploads/profil/' . $review['pencari_foto'] : '../../assets/img/default-avatar.png' ?>" 
                                         alt="<?= $review['pencari_nama'] ?>" class="review-avatar me-3">
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= $review['pencari_nama'] ?></h6>
                                                <div class="rating-stars mb-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?= $i <= $review['rating'] ? '' : 'text-muted' ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ms-1 text-muted small"><?= $review['rating'] ?>/5</span>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="fas fa-briefcase me-1"></i>
                                                    <?= $review['judul_pekerjaan'] ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <?= timeAgo($review['created_at']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($review['komentar']): ?>
                                    <div class="review-comment">
                                        <p class="mb-0">"<?= nl2br(htmlspecialchars($review['komentar'])) ?>"</p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($review['estimasi_harga']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-money-bill me-1"></i>
                                            Nilai Proyek: <?= formatRupiah($review['estimasi_harga']) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&sort=<?= $sort ?><?= $rating_filter ? '&rating=' . $rating_filter : '' ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php 
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                
                                for ($i = $start; $i <= $end; $i++): 
                                ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&sort=<?= $sort ?><?= $rating_filter ? '&rating=' . $rating_filter : '' ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&sort=<?= $sort ?><?= $rating_filter ? '&rating=' . $rating_filter : '' ?>">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>