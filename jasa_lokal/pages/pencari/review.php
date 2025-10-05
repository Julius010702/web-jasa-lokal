<?php
// pages/pencari/review.php
require_once '../../config/config.php';

if (!isLoggedIn() || $_SESSION['user_type'] !== 'pencari') {
    redirect('../../pages/auth/login.php');
}

$user = getCurrentUser();

// Check which columns exist in tables
try {
    $pesanan_columns = $db->query("SHOW COLUMNS FROM pesanan")->fetchAll(PDO::FETCH_COLUMN);
    $pekerja_columns = $db->query("SHOW COLUMNS FROM pekerja")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $pesanan_columns = [];
    $pekerja_columns = [];
}

// Handle add/edit review
if ($_POST['action'] ?? '') {
    $pesanan_id = intval($_POST['pesanan_id']);
    $rating = intval($_POST['rating']);
    $komentar = trim($_POST['komentar']);
    
    // Verifikasi bahwa pesanan ini milik user dan statusnya selesai
    $verify_stmt = $db->prepare("
        SELECT p.id, p.pekerja_id, pk.id as pekerja_detail_id
        FROM pesanan p
        JOIN pekerja pk ON p.pekerja_id = pk.user_id
        WHERE p.id = ? AND p.pencari_id = ? AND p.status = 'selesai'
    ");
    $verify_stmt->execute([$pesanan_id, $user['id']]);
    $pesanan_data = $verify_stmt->fetch();
    
    if ($pesanan_data) {
        if ($_POST['action'] === 'add_review') {
            // Check if review already exists
            $check_stmt = $db->prepare("SELECT id FROM reviews WHERE pesanan_id = ?");
            $check_stmt->execute([$pesanan_id]);
            
            if (!$check_stmt->fetchColumn()) {
                $insert_stmt = $db->prepare("
                    INSERT INTO reviews (pencari_id, pekerja_id, pesanan_id, rating, komentar, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $insert_stmt->execute([
                    $user['id'], 
                    $pesanan_data['pekerja_detail_id'], 
                    $pesanan_id, 
                    $rating, 
                    $komentar
                ]);
                $success_message = "Review berhasil ditambahkan.";
            }
        } elseif ($_POST['action'] === 'edit_review') {
            $review_id = intval($_POST['review_id']);
            $update_stmt = $db->prepare("
                UPDATE reviews 
                SET rating = ?, komentar = ?, updated_at = NOW() 
                WHERE id = ? AND pencari_id = ?
            ");
            $update_stmt->execute([$rating, $komentar, $review_id, $user['id']]);
            $success_message = "Review berhasil diperbarui.";
        }
        
        if (isset($success_message)) {
            $_SESSION['flash_message'] = $success_message;
            redirect('review.php');
        }
    }
}

// Handle delete review
if ($_POST['delete_review'] ?? '') {
    $review_id = intval($_POST['review_id']);
    $delete_stmt = $db->prepare("DELETE FROM reviews WHERE id = ? AND pencari_id = ?");
    $delete_stmt->execute([$review_id, $user['id']]);
    $_SESSION['flash_message'] = "Review berhasil dihapus.";
    redirect('review.php');
}

// Get pesanan_id from URL if creating new review
$pesanan_id_param = intval($_GET['pesanan_id'] ?? 0);
$edit_review_id = intval($_GET['edit'] ?? 0);

// If editing, get review data
$edit_review = null;
if ($edit_review_id) {
    // Build dynamic SQL for edit query
    $tanggal_column = in_array('tanggal_mulai', $pesanan_columns) ? 'p.tanggal_mulai' : 'p.created_at as tanggal_mulai';
    
    $edit_stmt = $db->prepare("
        SELECT r.*, p.kode_pesanan, u.nama as pekerja_nama, k.nama_kategori, $tanggal_column
        FROM reviews r
        JOIN pesanan p ON r.pesanan_id = p.id
        JOIN users u ON p.pekerja_id = u.id
        JOIN pekerja pk ON u.id = pk.user_id
        JOIN kategori_jasa k ON pk.kategori_id = k.id
        WHERE r.id = ? AND r.pencari_id = ?
    ");
    $edit_stmt->execute([$edit_review_id, $user['id']]);
    $edit_review = $edit_stmt->fetch();
}

// If pesanan_id provided, get pesanan data for new review
$new_review_pesanan = null;
if ($pesanan_id_param && !$edit_review) {
    // Build dynamic SQL for new review query
    $tanggal_column = in_array('tanggal_mulai', $pesanan_columns) ? 'p.tanggal_mulai' : 'p.created_at as tanggal_mulai';
    
    $pesanan_stmt = $db->prepare("
        SELECT p.*, u.nama as pekerja_nama, k.nama_kategori, $tanggal_column
        FROM pesanan p
        JOIN users u ON p.pekerja_id = u.id
        JOIN pekerja pk ON u.id = pk.user_id
        JOIN kategori_jasa k ON pk.kategori_id = k.id
        WHERE p.id = ? AND p.pencari_id = ? AND p.status = 'selesai'
    ");
    $pesanan_stmt->execute([$pesanan_id_param, $user['id']]);
    $new_review_pesanan = $pesanan_stmt->fetch();
    
    // Check if review already exists
    if ($new_review_pesanan) {
        $existing_review_stmt = $db->prepare("SELECT id FROM reviews WHERE pesanan_id = ?");
        $existing_review_stmt->execute([$pesanan_id_param]);
        if ($existing_review_stmt->fetchColumn()) {
            $new_review_pesanan = null; // Review already exists
            $error_message = "Review untuk pesanan ini sudah ada.";
        }
    }
}

// Get all reviews by this user
$tanggal_column = in_array('tanggal_mulai', $pesanan_columns) ? 'p.tanggal_mulai' : 'p.created_at as tanggal_mulai';
$tarif_column = in_array('tarif_per_jam', $pekerja_columns) ? 'pk.tarif_per_jam' : '0 as tarif_per_jam';

$reviews_stmt = $db->prepare("
    SELECT r.*, p.kode_pesanan, $tanggal_column, u.nama as pekerja_nama, u.foto_profil,
           k.nama_kategori, $tarif_column
    FROM reviews r
    JOIN pesanan p ON r.pesanan_id = p.id
    JOIN users u ON p.pekerja_id = u.id
    JOIN pekerja pk ON u.id = pk.user_id
    JOIN kategori_jasa k ON pk.kategori_id = k.id
    WHERE r.pencari_id = ?
    ORDER BY r.created_at DESC
");
$reviews_stmt->execute([$user['id']]);
$my_reviews = $reviews_stmt->fetchAll();

// Get pesanan yang belum direview
$tanggal_column_unreviewed = in_array('tanggal_mulai', $pesanan_columns) ? 'p.tanggal_mulai' : 'p.created_at as tanggal_mulai';

$unreviewed_stmt = $db->prepare("
    SELECT p.id, p.kode_pesanan, $tanggal_column_unreviewed, u.nama as pekerja_nama, k.nama_kategori
    FROM pesanan p
    JOIN users u ON p.pekerja_id = u.id
    JOIN pekerja pk ON u.id = pk.user_id
    JOIN kategori_jasa k ON pk.kategori_id = k.id
    LEFT JOIN reviews r ON p.id = r.pesanan_id
    WHERE p.pencari_id = ? AND p.status = 'selesai' AND r.id IS NULL
    ORDER BY p.created_at DESC
    LIMIT 5
");
$unreviewed_stmt->execute([$user['id']]);
$unreviewed_pesanan = $unreviewed_stmt->fetchAll();

// Flash message
$flash_message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Saya - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #28a745 0%, #20c997 100%);
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
            color: white;
            background: rgba(255,255,255,0.2);
        }
        .review-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .review-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .rating-display .fa-star {
            color: #ffc107;
            font-size: 1.1rem;
        }
        .rating-display .fa-star.text-muted {
            color: #dee2e6 !important;
        }
        .rating-input {
            display: flex;
            gap: 5px;
            margin: 10px 0;
        }
        .rating-input .star {
            font-size: 1.5rem;
            color: #dee2e6;
            cursor: pointer;
            transition: color 0.2s;
        }
        .rating-input .star:hover,
        .rating-input .star.active {
            color: #ffc107;
        }
        .review-form {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
        }
        .pekerja-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar p-3">
                    <div class="text-center mb-4">
                        <img src="<?= $user['foto_profil'] ? '../../uploads/profil/' . $user['foto_profil'] : '../../assets/img/default-avatar.png' ?>" 
                             alt="Profile" class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                        <h6 class="mt-2 mb-0"><?= $user['nama'] ?></h6>
                        <small class="opacity-75">Pencari Jasa</small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                        <a class="nav-link" href="profil.php">
                            <i class="fas fa-user me-2"></i>
                            Profil Saya
                        </a>
                        <a class="nav-link" href="pesanan.php">
                            <i class="fas fa-clipboard-list me-2"></i>
                            Pesanan Saya
                        </a>
                        <a class="nav-link" href="favorit.php">
                            <i class="fas fa-heart me-2"></i>
                            Pekerja Favorit
                        </a>
                        <a class="nav-link active" href="review.php">
                            <i class="fas fa-star me-2"></i>
                            Review Saya
                        </a>
                        <hr class="my-3">
                        <a class="nav-link" href="../../search.php">
                            <i class="fas fa-search me-2"></i>
                            Cari Pekerja
                        </a>
                        <a class="nav-link" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Logout
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="fw-bold mb-0">Review Saya</h2>
                            <p class="text-muted">Kelola review untuk pekerja yang pernah Anda gunakan</p>
                        </div>
                        <div>
                            <span class="badge bg-warning fs-6"><?= count($my_reviews) ?> Review</span>
                        </div>
                    </div>

                    <!-- Flash Message -->
                    <?php if ($flash_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= $flash_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Error Message -->
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= $error_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Review Form (New or Edit) -->
                    <?php if ($new_review_pesanan || $edit_review): ?>
                        <div class="review-form mb-4">
                            <h4 class="mb-4">
                                <i class="fas fa-star me-2"></i>
                                <?= $edit_review ? 'Edit Review' : 'Berikan Review' ?>
                            </h4>
                            
                            <form method="POST" id="reviewForm">
                                <input type="hidden" name="action" value="<?= $edit_review ? 'edit_review' : 'add_review' ?>">
                                <input type="hidden" name="pesanan_id" value="<?= $edit_review ? $edit_review['pesanan_id'] : $new_review_pesanan['id'] ?>">
                                <?php if ($edit_review): ?>
                                    <input type="hidden" name="review_id" value="<?= $edit_review['id'] ?>">
                                <?php endif; ?>
                                
                                <!-- Pesanan Info -->
                                <div class="pekerja-info">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="text-dark">
                                                <?= $edit_review ? $edit_review['pekerja_nama'] : $new_review_pesanan['pekerja_nama'] ?>
                                            </h6>
                                            <p class="text-muted mb-1">
                                                <?= $edit_review ? $edit_review['nama_kategori'] : $new_review_pesanan['nama_kategori'] ?>
                                            </p>
                                            <small class="text-muted">
                                                Kode: <?= $edit_review ? $edit_review['kode_pesanan'] : $new_review_pesanan['kode_pesanan'] ?>
                                            </small>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <?php if ($edit_review): ?>
                                                <a href="review.php" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-arrow-left me-1"></i>
                                                    Kembali
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Rating -->
                                <div class="mb-3">
                                    <label class="form-label">Rating</label>
                                    <div class="rating-input" id="ratingInput">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star star" data-rating="<?= $i ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <input type="hidden" name="rating" id="ratingValue" value="<?= $edit_review['rating'] ?? 0 ?>" required>
                                </div>
                                
                                <!-- Komentar -->
                                <div class="mb-4">
                                    <label class="form-label">Komentar</label>
                                    <textarea name="komentar" class="form-control" rows="4" 
                                              placeholder="Ceritakan pengalaman Anda dengan pekerja ini..." required><?= $edit_review['komentar'] ?? '' ?></textarea>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-light">
                                        <i class="fas fa-save me-2"></i>
                                        <?= $edit_review ? 'Update Review' : 'Kirim Review' ?>
                                    </button>
                                    <a href="review.php" class="btn btn-outline-light">
                                        Batal
                                    </a>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- My Reviews -->
                        <div class="col-lg-8">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Review Saya</h5>
                            </div>
                            
                            <?php if (empty($my_reviews)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-star fa-4x text-muted mb-3"></i>
                                    <h4 class="text-muted">Belum Ada Review</h4>
                                    <p class="text-muted">Anda belum memberikan review untuk pekerja manapun.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($my_reviews as $review): ?>
                                    <div class="card review-card mb-3">
                                        <div class="card-body">
                                            <div class="d-flex align-items-start">
                                                <img src="<?= $review['foto_profil'] ? '../../uploads/profil/' . $review['foto_profil'] : '../../assets/img/default-avatar.png' ?>" 
                                                     alt="<?= $review['pekerja_nama'] ?>" 
                                                     class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div>
                                                            <h6 class="mb-0"><?= $review['pekerja_nama'] ?></h6>
                                                            <small class="text-muted"><?= $review['nama_kategori'] ?></small>
                                                        </div>
                                                        <div class="text-end">
                                                            <div class="rating-display">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <i class="fas fa-star <?= $i <= $review['rating'] ? '' : 'text-muted' ?>"></i>
                                                                <?php endfor; ?>
                                                            </div>
                                                            <small class="text-muted"><?= date('d M Y', strtotime($review['created_at'])) ?></small>
                                                        </div>
                                                    </div>
                                                    
                                                    <p class="mb-2"><?= nl2br(htmlspecialchars($review['komentar'])) ?></p>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            <i class="fas fa-receipt me-1"></i>
                                                            <?= $review['kode_pesanan'] ?> • 
                                                            <?= date('d M Y', strtotime($review['tanggal_mulai'])) ?>
                                                        </small>
                                                        
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="review.php?edit=<?= $review['id'] ?>" 
                                                               class="btn btn-outline-primary">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <form method="POST" class="d-inline" 
                                                                  onsubmit="return confirm('Yakin ingin menghapus review ini?')">
                                                                <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                                                <input type="hidden" name="delete_review" value="1">
                                                                <button type="submit" class="btn btn-outline-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Unreviewed Orders -->
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Pesanan Belum Direview</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($unreviewed_pesanan)): ?>
                                        <div class="text-center text-muted py-3">
                                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                                            <p class="small">Semua pesanan sudah direview</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($unreviewed_pesanan as $pesanan): ?>
                                            <div class="border-bottom pb-2 mb-2">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?= $pesanan['pekerja_nama'] ?></h6>
                                                        <small class="text-muted"><?= $pesanan['nama_kategori'] ?></small><br>
                                                        <small class="text-muted"><?= $pesanan['kode_pesanan'] ?></small>
                                                    </div>
                                                    <a href="review.php?pesanan_id=<?= $pesanan['id'] ?>" 
                                                       class="btn btn-sm btn-warning">
                                                        <i class="fas fa-star"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <div class="text-center mt-3">
                                            <a href="pesanan.php?status=selesai" class="btn btn-sm btn-outline-primary">
                                                Lihat Semua Pesanan Selesai
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Quick Stats -->
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Statistik Review</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($my_reviews)): ?>
                                        <?php 
                                        $total_reviews = count($my_reviews);
                                        $avg_rating = array_sum(array_column($my_reviews, 'rating')) / $total_reviews;
                                        $rating_distribution = array_count_values(array_column($my_reviews, 'rating'));
                                        ?>
                                        
                                        <div class="text-center mb-3">
                                            <div class="h4 mb-1"><?= number_format($avg_rating, 1) ?></div>
                                            <div class="rating-display mb-1">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= round($avg_rating) ? '' : 'text-muted' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="text-muted"><?= $total_reviews ?> review</small>
                                        </div>
                                        
                                        <div class="small">
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                                <div class="d-flex align-items-center mb-1">
                                                    <span class="me-2"><?= $i ?></span>
                                                    <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                        <div class="progress-bar bg-warning" 
                                                             style="width: <?= $total_reviews > 0 ? (($rating_distribution[$i] ?? 0) / $total_reviews * 100) : 0 ?>%">
                                                        </div>
                                                    </div>
                                                    <span class="text-muted"><?= $rating_distribution[$i] ?? 0 ?></span>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center text-muted">
                                            <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                            <p class="small">Belum ada data statistik</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ratingInput = document.getElementById('ratingInput');
            const ratingValue = document.getElementById('ratingValue');
            const stars = ratingInput.querySelectorAll('.star');
            
            // Set initial rating if editing
            const initialRating = parseInt(ratingValue.value);
            if (initialRating > 0) {
                updateStars(initialRating);
            }
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.dataset.rating);
                    ratingValue.value = rating;
                    updateStars(rating);
                });
                
                star.addEventListener('mouseenter', function() {
                    const rating = parseInt(this.dataset.rating);
                    highlightStars(rating);
                });
            });
            
            ratingInput.addEventListener('mouseleave', function() {
                const currentRating = parseInt(ratingValue.value);
                updateStars(currentRating);
            });
            
            function updateStars(rating) {
                stars.forEach((star, index) => {
                    if (index < rating) {
                        star.classList.add('active');
                    } else {
                        star.classList.remove('active');
                    }
                });
            }
            
            function highlightStars(rating) {
                stars.forEach((star, index) => {
                    if (index < rating) {
                        star.style.color = '#ffc107';
                    } else {
                        star.style.color = '#dee2e6';
                    }
                });
            }
        });
    </script>
</body>
</html>