<?php
// pages/pekerja/dashboard.php
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

// Statistik dashboard
$stats = [];

// Total pekerjaan
$stmt = $db->prepare("SELECT COUNT(*) as total FROM pesanan WHERE pekerja_id = ?");
$stmt->execute([$user['id']]);
$stats['total_pekerjaan'] = $stmt->fetchColumn();

// Pekerjaan pending
$stmt = $db->prepare("SELECT COUNT(*) as total FROM pesanan WHERE pekerja_id = ? AND status = 'pending'");
$stmt->execute([$user['id']]);
$stats['pending'] = $stmt->fetchColumn();

// Rating rata-rata
$stmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE pekerja_id = ?");
$stmt->execute([$user['id']]);
$rating_data = $stmt->fetch();
$stats['rating'] = $rating_data['avg_rating'] ?: 0;
$stats['total_reviews'] = $rating_data['total_reviews'];

// Penghasilan bulan ini (jika ada sistem pembayaran)
$stmt = $db->prepare("
    SELECT COALESCE(SUM(harga_final), 0) as total 
    FROM pesanan 
    WHERE pekerja_id = ? AND status = 'selesai' 
    AND MONTH(updated_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(updated_at) = YEAR(CURRENT_DATE())
");
$stmt->execute([$user['id']]);
$stats['penghasilan_bulan'] = $stmt->fetchColumn();

// Pesanan terbaru
$stmt = $db->prepare("
    SELECT p.*, u.nama as pencari_nama 
    FROM pesanan p 
    JOIN users u ON p.pencari_id = u.id 
    WHERE p.pekerja_id = ? 
    ORDER BY p.created_at DESC 
    LIMIT 5
");
$stmt->execute([$user['id']]);
$pesanan_terbaru = $stmt->fetchAll();

// Cek apakah profil sudah lengkap
$profil_lengkap = $profil_pekerja && $user['foto_profil'] && $profil_pekerja['deskripsi_skill'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pekerja - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Tambahkan CSS ini ke dalam tag <style> yang sudah ada */

/* Buat sidebar fixed dan scrollable */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 1000;
    width: 16.666667%; /* Sama dengan col-lg-2 */
}

/* Untuk tablet (col-md-3) */
@media (min-width: 768px) and (max-width: 991.98px) {
    .sidebar {
        width: 25%; /* Sama dengan col-md-3 */
    }
}

/* Untuk mobile, sidebar tidak fixed */
@media (max-width: 767.98px) {
    .sidebar {
        position: relative;
        width: 100%;
        min-height: auto;
    }
}

/* Beri padding kiri pada main content agar tidak tertutup sidebar */
.main-content {
    margin-left: 16.666667%; /* Sama dengan lebar col-lg-2 */
}

@media (min-width: 768px) and (max-width: 991.98px) {
    .main-content {
        margin-left: 25%; /* Sama dengan lebar col-md-3 */
    }
}

@media (max-width: 767.98px) {
    .main-content {
        margin-left: 0;
    }
}

/* Custom scrollbar untuk sidebar (opsional, untuk tampilan lebih baik) */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}
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
        
        .stat-card {
            border-radius: 15px;
            border: none;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .main-content {
            padding: 20px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .profile-card {
            border-radius: 15px;
            overflow: hidden;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .alert-profile {
            border-radius: 10px;
            border-left: 4px solid #ffc107;
        }
        
        .pesanan-card {
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .pesanan-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
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
                    <a class="nav-link active" href="dashboard.php">
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
                        <?php if ($stats['pending'] > 0): ?>
                            <span class="badge bg-warning ms-1"><?= $stats['pending'] ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link" href="reviews.php">
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
                        <h2 class="fw-bold mb-1">Selamat datang, <?= $user['nama'] ?>!</h2>
                        <p class="text-muted mb-0">Kelola profil dan pesanan Anda</p>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <small class="text-muted">Online terakhir:</small><br>
                            <strong>Sekarang</strong>
                        </div>
                        <img src="<?= $user['foto_profil'] ? '../../uploads/profil/' . $user['foto_profil'] : '../../assets/img/default-avatar.png' ?>" 
                             alt="<?= $user['nama'] ?>" class="profile-avatar">
                    </div>
                </div>
                
                <!-- Alert jika profil belum lengkap -->
                <?php if (!$profil_lengkap): ?>
                    <div class="alert alert-profile alert-warning mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Lengkapi Profil Anda!</h6>
                                <p class="mb-0">Profil yang lengkap akan meningkatkan peluang mendapat pesanan hingga 80%</p>
                            </div>
                            <a href="profil.php" class="btn btn-warning">Lengkapi Sekarang</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-primary text-white me-3">
                                    <i class="fas fa-briefcase"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0"><?= $stats['total_pekerjaan'] ?></h5>
                                    <small class="text-muted">Total Pekerjaan</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-warning text-white me-3">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0"><?= $stats['pending'] ?></h5>
                                    <small class="text-muted">Pesanan Pending</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-success text-white me-3">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0"><?= number_format($stats['rating'], 1) ?></h5>
                                    <small class="text-muted">Rating (<?= $stats['total_reviews'] ?> ulasan)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-info text-white me-3">
                                    <i class="fas fa-money-bill"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0"><?= formatRupiah($stats['penghasilan_bulan']) ?></h5>
                                    <small class="text-muted">Penghasilan Bulan Ini</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <!-- Profil Quick Info -->
                    <div class="col-lg-4">
                        <div class="card profile-card">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-user me-2"></i>
                                    Profil Saya
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <img src="<?= $user['foto_profil'] ? '../../uploads/profil/' . $user['foto_profil'] : '../../assets/img/default-avatar.png' ?>" 
                                         alt="<?= $user['nama'] ?>" class="profile-avatar mb-2">
                                    <h6 class="mb-1"><?= $user['nama'] ?></h6>
                                    <?php if ($profil_pekerja): ?>
                                        <span class="badge bg-primary"><?= $profil_pekerja['nama_kategori'] ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="small">
                                    <div class="mb-2">
                                        <i class="fas fa-map-marker-alt text-muted me-2"></i>
                                        <?= $user['kota'] ?>
                                    </div>
                                    <div class="mb-2">
                                        <i class="fas fa-phone text-muted me-2"></i>
                                        <?= $user['no_telepon'] ?>
                                    </div>
                                    <div class="mb-2">
                                        <i class="fas fa-envelope text-muted me-2"></i>
                                        <?= $user['email'] ?>
                                    </div>
                                    <?php if ($profil_pekerja): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-briefcase text-muted me-2"></i>
                                            <?= $profil_pekerja['pengalaman_tahun'] ?> tahun pengalaman
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-grid gap-2 mt-3">
                                    <a href="profil.php" class="btn btn-outline-primary btn-sm">Edit Profil</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pesanan Terbaru -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-clipboard-list me-2"></i>
                                    Pesanan Terbaru
                                </h6>
                                <a href="pesanan.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pesanan_terbaru)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                        <h6>Belum Ada Pesanan</h6>
                                        <p class="text-muted mb-0">Lengkapi profil Anda agar mudah ditemukan pelanggan</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($pesanan_terbaru as $pesanan): ?>
                                            <div class="list-group-item pesanan-card border-0 mb-2">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?= $pesanan['pencari_nama'] ?></h6>
                                                        <p class="mb-1 text-muted small">
                                                            <?= substr($pesanan['deskripsi_pekerjaan'], 0, 100) ?>...
                                                        </p>
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?= $pesanan['tanggal_kerja'] ? date('d/m/Y', strtotime($pesanan['tanggal_kerja'])) : 'Tanggal fleksibel' ?>
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge status-badge 
                                                            <?php 
                                                            echo match($pesanan['status']) {
                                                                'pending' => 'bg-warning',
                                                                'diterima' => 'bg-info',
                                                                'dalam_progress' => 'bg-primary',
                                                                'selesai' => 'bg-success',
                                                                'dibatalkan' => 'bg-danger',
                                                                default => 'bg-secondary'
                                                            };
                                                            ?>">
                                                            <?= ucfirst($pesanan['status']) ?>
                                                        </span>
                                                        <?php if ($pesanan['estimasi_harga']): ?>
                                                            <div class="small text-muted mt-1">
                                                                <?= formatRupiah($pesanan['estimasi_harga']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="small text-muted">
                                                            <?= timeAgo($pesanan['created_at']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row g-4 mt-2">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-bolt me-2"></i>
                                    Aksi Cepat
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-lg-3 col-md-6">
                                        <div class="d-grid">
                                            <a href="profil.php" class="btn btn-outline-primary">
                                                <i class="fas fa-user me-2"></i>
                                                Edit Profil
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <div class="d-grid">
                                            <a href="portfolio.php" class="btn btn-outline-success">
                                                <i class="fas fa-images me-2"></i>
                                                Upload Portfolio
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <div class="d-grid">
                                            <a href="pesanan.php" class="btn btn-outline-warning">
                                                <i class="fas fa-clipboard-list me-2"></i>
                                                Cek Pesanan
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <div class="d-grid">
                                            <a href="../../detail-pekerja.php?id=<?= $user['id'] ?>" target="_blank" class="btn btn-outline-info">
                                                <i class="fas fa-external-link-alt me-2"></i>
                                                Lihat Profil Publik
                                            </a>
                                        </div>
                                    </div>
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
        // Auto refresh dashboard setiap 5 menit
        setInterval(() => {
            // Refresh hanya bagian statistik untuk menghindari reload penuh
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    // Update stats jika diperlukan
                    console.log('Dashboard refreshed');
                })
                .catch(error => console.log('Refresh error:', error));
        }, 300000); // 5 menit

        // Notification sound untuk pesanan baru (implementasi lanjutan)
        function checkNewOrders() {
            // Implementasi check pesanan baru via AJAX
        }
    </script>
</body>
</html>
