<?php
// pages/pencari/dashboard.php
require_once '../../config/config.php';

if (!isLoggedIn() || $_SESSION['user_type'] !== 'pencari') {
    redirect('../../pages/auth/login.php');
}

$user = getCurrentUser();

// Statistik untuk pencari jasa
$stats = [];

// Total pesanan
$stmt = $db->prepare("SELECT COUNT(*) as total FROM pesanan WHERE pencari_id = ?");
$stmt->execute([$user['id']]);
$stats['total_pesanan'] = $stmt->fetchColumn();

// Pesanan aktif
$stmt = $db->prepare("SELECT COUNT(*) as total FROM pesanan WHERE pencari_id = ? AND status IN ('pending', 'diterima', 'dalam_progress')");
$stmt->execute([$user['id']]);
$stats['pesanan_aktif'] = $stmt->fetchColumn();

// Pesanan selesai
$stmt = $db->prepare("SELECT COUNT(*) as total FROM pesanan WHERE pencari_id = ? AND status = 'selesai'");
$stmt->execute([$user['id']]);
$stats['pesanan_selesai'] = $stmt->fetchColumn();

// Review yang diberikan
$stmt = $db->prepare("SELECT COUNT(*) as total FROM reviews WHERE pencari_id = ?");
$stmt->execute([$user['id']]);
$stats['total_reviews'] = $stmt->fetchColumn();

// Pesanan terbaru
$stmt = $db->prepare("
    SELECT p.*, u.nama as pekerja_nama, u.no_telepon, u.whatsapp,
           k.nama_kategori
    FROM pesanan p
    JOIN users u ON p.pekerja_id = u.id
    JOIN pekerja pk ON u.id = pk.user_id
    JOIN kategori_jasa k ON pk.kategori_id = k.id
    WHERE p.pencari_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$pesanan_terbaru = $stmt->fetchAll();

// Pekerja favorit (yang paling sering dihubungi) - FIXED QUERY
$stmt = $db->prepare("
    SELECT DISTINCT u.id, u.nama, u.foto_profil,
           pk.id as pekerja_id, k.nama_kategori,
           (SELECT COUNT(*) FROM pesanan WHERE pekerja_id = u.id AND pencari_id = ?) as total_pesanan,
           (SELECT AVG(rating) FROM reviews WHERE pekerja_id = pk.id) as avg_rating
    FROM users u
    JOIN pekerja pk ON u.id = pk.user_id
    JOIN kategori_jasa k ON pk.kategori_id = k.id
    JOIN pesanan p ON u.id = p.pekerja_id
    WHERE p.pencari_id = ?
    ORDER BY total_pesanan DESC, avg_rating DESC
    LIMIT 3
");
$stmt->execute([$user['id'], $user['id']]);
$pekerja_favorit = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pencari - <?= APP_NAME ?></title>
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
        .stat-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .quick-search {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            color: white;
            padding: 30px;
        }
        .worker-card {
            border-radius: 15px;
            transition: all 0.3s;
        }
        .worker-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .worker-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
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
                        <a class="nav-link active" href="dashboard.php">
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
                        <a class="nav-link" href="review.php">
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
                            <h2 class="fw-bold mb-0">Dashboard</h2>
                            <p class="text-muted">Selamat datang kembali, <?= $user['nama'] ?>!</p>
                        </div>
                        <div>
                            <a href="../../search.php" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>
                                Cari Pekerja
                            </a>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row g-4 mb-4">
                        <div class="col-lg-3 col-md-6">
                            <div class="card stat-card">
                                <div class="card-body d-flex align-items-center">
                                    <div class="stat-icon bg-primary text-white me-3">
                                        <i class="fas fa-clipboard-list"></i>
                                    </div>
                                    <div>
                                        <div class="h4 mb-0"><?= $stats['total_pesanan'] ?></div>
                                        <small class="text-muted">Total Pesanan</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6">
                            <div class="card stat-card">
                                <div class="card-body d-flex align-items-center">
                                    <div class="stat-icon bg-warning text-white me-3">
                                        <i class="fas fa-hourglass-half"></i>
                                    </div>
                                    <div>
                                        <div class="h4 mb-0"><?= $stats['pesanan_aktif'] ?></div>
                                        <small class="text-muted">Pesanan Aktif</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6">
                            <div class="card stat-card">
                                <div class="card-body d-flex align-items-center">
                                    <div class="stat-icon bg-success text-white me-3">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div>
                                        <div class="h4 mb-0"><?= $stats['pesanan_selesai'] ?></div>
                                        <small class="text-muted">Pesanan Selesai</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6">
                            <div class="card stat-card">
                                <div class="card-body d-flex align-items-center">
                                    <div class="stat-icon bg-info text-white me-3">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <div>
                                        <div class="h4 mb-0"><?= $stats['total_reviews'] ?></div>
                                        <small class="text-muted">Review Diberikan</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Quick Search -->
                        <div class="col-lg-8 mb-4">
                            <div class="quick-search">
                                <h5 class="fw-bold mb-3">
                                    <i class="fas fa-search me-2"></i>
                                    Cari Pekerja Cepat
                                </h5>
                                <form action="../../search.php" method="GET">
                                    <div class="row g-3">
                                        <div class="col-md-5">
                                            <select class="form-select" name="kategori">
                                                <option value="">Pilih Kategori</option>
                                                <?php
                                                $kategori_list = getKategoriJasa();
                                                foreach ($kategori_list as $kat):
                                                ?>
                                                    <option value="<?= $kat['id'] ?>"><?= $kat['nama_kategori'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="text" class="form-control" name="kota" 
                                                   placeholder="Kota/Wilayah" value="<?= $user['kota'] ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-light w-100">
                                                <i class="fas fa-search me-2"></i>
                                                Cari
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0">Aksi Cepat</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="../../search.php" class="btn btn-primary">
                                            <i class="fas fa-search me-2"></i>
                                            Cari Pekerja
                                        </a>
                                        <a href="pesanan.php" class="btn btn-outline-primary">
                                            <i class="fas fa-clipboard-list me-2"></i>
                                            Lihat Pesanan
                                        </a>
                                        <a href="favorit.php" class="btn btn-outline-success">
                                            <i class="fas fa-heart me-2"></i>
                                            Pekerja Favorit
                                        </a>
                                        <a href="profil.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-user me-2"></i>
                                            Edit Profil
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Pesanan Terbaru -->
                        <div class="col-lg-8 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Pesanan Terbaru</h5>
                                    <a href="pesanan.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($pesanan_terbaru)): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <p>Belum ada pesanan</p>
                                            <a href="../../search.php" class="btn btn-primary">
                                                Cari Pekerja Sekarang
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Kode</th>
                                                        <th>Pekerja</th>
                                                        <th>Kategori</th>
                                                        <th>Tanggal</th>
                                                        <th>Status</th>
                                                        <th>Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($pesanan_terbaru as $pesanan): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?= $pesanan['kode_pesanan'] ?></strong>
                                                            </td>
                                                            <td>
                                                                <?= $pesanan['pekerja_nama'] ?><br>
                                                                <small class="text-muted"><?= $pesanan['no_telepon'] ?></small>
                                                            </td>
                                                            <td><?= $pesanan['nama_kategori'] ?></td>
                                                            <td><?= date('d/m/Y', strtotime($pesanan['created_at'])) ?></td>
                                                            <td>
                                                                <?php
                                                                $status_colors = [
                                                                    'pending' => 'warning',
                                                                    'diterima' => 'info',
                                                                    'dalam_progress' => 'primary',
                                                                    'selesai' => 'success',
                                                                    'dibatalkan' => 'danger'
                                                                ];
                                                                ?>
                                                                <span class="badge bg-<?= $status_colors[$pesanan['status']] ?>">
                                                                    <?= ucfirst($pesanan['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group btn-group-sm">
                                                                    <a href="pesanan-detail.php?id=<?= $pesanan['id'] ?>" 
                                                                       class="btn btn-outline-primary">Detail</a>
                                                                    <?php if ($pesanan['whatsapp']): ?>
                                                                        <a href="https://wa.me/<?= $pesanan['whatsapp'] ?>" 
                                                                           class="btn btn-success" target="_blank">
                                                                            <i class="fab fa-whatsapp"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pekerja Favorit -->
                        <div class="col-lg-4 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Pekerja Favorit</h5>
                                    <a href="favorit.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($pekerja_favorit)): ?>
                                        <div class="text-center text-muted py-3">
                                            <i class="fas fa-heart fa-2x mb-2"></i>
                                            <p class="small">Belum ada pekerja favorit</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($pekerja_favorit as $pekerja): ?>
                                            <div class="worker-card card mb-3">
                                                <div class="card-body p-3">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <img src="<?= $pekerja['foto_profil'] ? '../../uploads/profil/' . $pekerja['foto_profil'] : '../../assets/img/default-avatar.png' ?>" 
                                                             alt="<?= $pekerja['nama'] ?>" class="worker-avatar me-3">
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1"><?= $pekerja['nama'] ?></h6>
                                                            <small class="text-muted"><?= $pekerja['nama_kategori'] ?></small>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <div class="text-warning small">
                                                            <?php
                                                            $rating = $pekerja['avg_rating'] ?: 0;
                                                            for ($i = 1; $i <= 5; $i++):
                                                            ?>
                                                                <i class="fas fa-star <?= $i <= $rating ? '' : 'text-muted' ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <small class="text-muted"><?= $pekerja['total_pesanan'] ?> pesanan</small>
                                                    </div>
                                                    
                                                    <div class="d-grid gap-1">
                                                        <a href="../../detail-pekerja.php?id=<?= $pekerja['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            Lihat Profil
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
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
        // Auto-fill kota dari profil user
        document.addEventListener('DOMContentLoaded', function() {
            const kotaInput = document.querySelector('input[name="kota"]');
            if (kotaInput && !kotaInput.value) {
                kotaInput.value = '<?= $user['kota'] ?: '' ?>';
            }
        });
    </script>
</body>
</html>