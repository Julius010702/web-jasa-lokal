<?php
// pages/pekerja/pesanan.php
require_once '../../config/config.php';

// Cek login dan tipe user
if (!isLoggedIn() || $_SESSION['user_type'] !== 'pekerja') {
    redirect('../../pages/auth/login.php');
}

$user = getCurrentUser();

// Handle update status pesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $pesanan_id = (int)$_POST['pesanan_id'];
    $new_status = $_POST['status'];
    $harga_final = isset($_POST['harga_final']) ? (float)$_POST['harga_final'] : null;
    
    // Validasi status yang diizinkan
    $allowed_statuses = ['diterima', 'dalam_progress', 'selesai', 'ditolak'];
    
    if (in_array($new_status, $allowed_statuses)) {
        try {
            $db->beginTransaction();
            
            // Update status pesanan
            if ($harga_final && $new_status === 'diterima') {
                $stmt = $db->prepare("UPDATE pesanan SET status = ?, harga_final = ?, updated_at = NOW() WHERE id = ? AND pekerja_id = ?");
                $stmt->execute([$new_status, $harga_final, $pesanan_id, $user['id']]);
            } else {
                $stmt = $db->prepare("UPDATE pesanan SET status = ?, updated_at = NOW() WHERE id = ? AND pekerja_id = ?");
                $stmt->execute([$new_status, $pesanan_id, $user['id']]);
            }
            
            // Log aktivitas
            $stmt = $db->prepare("INSERT INTO aktivitas_log (user_id, aktivitas, detail) VALUES (?, ?, ?)");
            $stmt->execute([
                $user['id'], 
                'update_status_pesanan', 
                "Status pesanan #$pesanan_id diubah menjadi $new_status"
            ]);
            
            $db->commit();
            $_SESSION['success'] = 'Status pesanan berhasil diupdate!';
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['error'] = 'Gagal mengupdate status pesanan: ' . $e->getMessage();
        }
        
        redirect('pesanan.php');
    }
}

// Filter dan sorting
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = (int)($_GET['page'] ?? 1);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query dengan filter
$where_conditions = ['p.pekerja_id = ?'];
$params = [$user['id']];

if ($status_filter && $status_filter !== 'semua') {
    $where_conditions[] = 'p.status = ?';
    $params[] = $status_filter;
}

// Query utama
$where_clause = implode(' AND ', $where_conditions);
$allowed_sorts = ['created_at', 'tanggal_kerja', 'estimasi_harga', 'status'];
$sort_field = in_array($sort_by, $allowed_sorts) ? $sort_by : 'created_at';
$order_by = ($order === 'ASC') ? 'ASC' : 'DESC';

// Ambil total records untuk pagination
$count_query = "SELECT COUNT(*) FROM pesanan p WHERE $where_clause";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Ambil data pesanan
$query = "
    SELECT p.*, u.nama as pencari_nama, u.foto_profil as pencari_foto, 
           u.no_telepon as pencari_telepon, u.latitude as pencari_lat, u.longitude as pencari_lng
    FROM pesanan p 
    JOIN users u ON p.pencari_id = u.id 
    WHERE $where_clause 
    ORDER BY p.$sort_field $order_by 
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$pesanan_list = $stmt->fetchAll();

// Statistik untuk cards
$stats = [];

// Total pesanan
$stmt = $db->prepare("SELECT COUNT(*) FROM pesanan WHERE pekerja_id = ?");
$stmt->execute([$user['id']]);
$stats['total'] = $stmt->fetchColumn();

// Per status
$statuses = ['pending', 'diterima', 'dalam_progress', 'selesai', 'ditolak', 'dibatalkan'];
foreach ($statuses as $status) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM pesanan WHERE pekerja_id = ? AND status = ?");
    $stmt->execute([$user['id'], $status]);
    $stats[$status] = $stmt->fetchColumn();
}

// Fungsi untuk menghitung jarak
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return null;
    
    $earthRadius = 6371; // km
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    $dLat = $lat2 - $lat1;
    $dLon = $lon2 - $lon1;
    
    $a = sin($dLat/2) * sin($dLat/2) + 
         cos($lat1) * cos($lat2) * 
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earthRadius * $c;
    
    return round($distance, 1);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        /* Sidebar Fixed */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1000;
            width: 16.666667%;
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
        
        .main-content {
            margin-left: 16.666667%;
            padding: 20px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        @media (min-width: 768px) and (max-width: 991.98px) {
            .sidebar {
                width: 25%;
            }
            .main-content {
                margin-left: 25%;
            }
        }
        
        @media (max-width: 767.98px) {
            .sidebar {
                position: relative;
                width: 100%;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
        
        .stat-card {
            border-radius: 10px;
            border: none;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .pesanan-card {
            border-radius: 12px;
            border: none;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        
        .pesanan-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .priority-high {
            border-left: 4px solid #dc3545;
        }
        
        .priority-medium {
            border-left: 4px solid #ffc107;
        }
        
        .priority-low {
            border-left: 4px solid #28a745;
        }
        
        .action-buttons .btn {
            margin: 2px;
            border-radius: 6px;
        }
        
        .avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .modal-content {
            border-radius: 15px;
        }
        
        .modal-header {
            border-radius: 15px 15px 0 0;
        }
        
        /* Maps Styling */
        .map-container {
            height: 250px;
            width: 100%;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 10px;
            border: 2px solid #e9ecef;
        }
        
        .map-container .leaflet-container {
            height: 100%;
            width: 100%;
        }
        
        .location-info {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 10px;
            padding: 12px;
            margin-top: 12px;
            border: 1px solid #dee2e6;
        }
        
        .location-info i {
            color: #0d6efd;
        }
        
        .distance-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        
        .map-toggle-btn {
            font-size: 0.85rem;
            padding: 5px 12px;
            transition: all 0.3s;
        }
        
        .map-toggle-btn:hover {
            transform: scale(1.05);
        }
        
        .leaflet-popup-content-wrapper {
            border-radius: 10px;
        }
        
        .no-coords-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 8px 12px;
            margin-top: 8px;
            font-size: 0.85rem;
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
                    <a class="nav-link active" href="pesanan.php">
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
                        <h2 class="fw-bold mb-1">Kelola Pesanan</h2>
                        <p class="text-muted mb-0">Total <?= $total_records ?> pesanan ditemukan</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh
                        </button>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <!-- Status Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="card stat-card <?= $status_filter === '' ? 'border-primary' : '' ?>" 
                             onclick="filterByStatus('')">
                            <div class="card-body text-center">
                                <div class="h4 text-primary mb-1"><?= $stats['total'] ?></div>
                                <div class="small text-muted">Semua</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="card stat-card <?= $status_filter === 'pending' ? 'border-warning' : '' ?>" 
                             onclick="filterByStatus('pending')">
                            <div class="card-body text-center">
                                <div class="h4 text-warning mb-1"><?= $stats['pending'] ?></div>
                                <div class="small text-muted">Pending</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="card stat-card <?= $status_filter === 'diterima' ? 'border-info' : '' ?>" 
                             onclick="filterByStatus('diterima')">
                            <div class="card-body text-center">
                                <div class="h4 text-info mb-1"><?= $stats['diterima'] ?></div>
                                <div class="small text-muted">Diterima</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="card stat-card <?= $status_filter === 'dalam_progress' ? 'border-primary' : '' ?>" 
                             onclick="filterByStatus('dalam_progress')">
                            <div class="card-body text-center">
                                <div class="h4 text-primary mb-1"><?= $stats['dalam_progress'] ?></div>
                                <div class="small text-muted">Progress</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="card stat-card <?= $status_filter === 'selesai' ? 'border-success' : '' ?>" 
                             onclick="filterByStatus('selesai')">
                            <div class="card-body text-center">
                                <div class="h4 text-success mb-1"><?= $stats['selesai'] ?></div>
                                <div class="small text-muted">Selesai</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="card stat-card <?= $status_filter === 'ditolak' ? 'border-danger' : '' ?>" 
                             onclick="filterByStatus('ditolak')">
                            <div class="card-body text-center">
                                <div class="h4 text-danger mb-1"><?= $stats['ditolak'] ?></div>
                                <div class="small text-muted">Ditolak</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter & Sort -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <select class="form-select form-select-sm" style="width: auto;" onchange="changeSort(this)">
                                        <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Tanggal Dibuat</option>
                                        <option value="tanggal_kerja" <?= $sort_by === 'tanggal_kerja' ? 'selected' : '' ?>>Tanggal Kerja</option>
                                        <option value="estimasi_harga" <?= $sort_by === 'estimasi_harga' ? 'selected' : '' ?>>Harga</option>
                                        <option value="status" <?= $sort_by === 'status' ? 'selected' : '' ?>>Status</option>
                                    </select>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="toggleOrder()">
                                        <i class="fas fa-sort-amount-<?= $order === 'DESC' ? 'down' : 'up' ?>"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="text-muted small">
                                    Menampilkan <?= min($offset + 1, $total_records) ?>-<?= min($offset + $per_page, $total_records) ?> dari <?= $total_records ?> pesanan
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Daftar Pesanan -->
                <div class="row">
                    <?php if (empty($pesanan_list)): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                                    <h5>Tidak Ada Pesanan</h5>
                                    <p class="text-muted">
                                        <?php if ($status_filter): ?>
                                            Tidak ada pesanan dengan status "<?= ucfirst($status_filter) ?>".
                                        <?php else: ?>
                                            Anda belum memiliki pesanan. Lengkapi profil untuk menarik lebih banyak pelanggan.
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($status_filter): ?>
                                        <button class="btn btn-primary" onclick="filterByStatus('')">Lihat Semua Pesanan</button>
                                    <?php else: ?>
                                        <a href="profil.php" class="btn btn-primary">Lengkapi Profil</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pesanan_list as $pesanan): 
                            // Hitung jarak
                            $distance = null;
                            if ($user['latitude'] && $user['longitude'] && $pesanan['pencari_lat'] && $pesanan['pencari_lng']) {
                                $distance = calculateDistance(
                                    $user['latitude'], 
                                    $user['longitude'], 
                                    $pesanan['pencari_lat'], 
                                    $pesanan['pencari_lng']
                                );
                            }
                        ?>
                            <div class="col-12">
                                <div class="card pesanan-card <?php 
                                    echo match($pesanan['status']) {
                                        'pending' => 'priority-high',
                                        'diterima', 'dalam_progress' => 'priority-medium',
                                        default => 'priority-low'
                                    };
                                ?>">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-lg-8">
                                                <!-- Header Pesanan -->
                                                <div class="d-flex align-items-start mb-3">
                                                    <img src="<?= $pesanan['pencari_foto'] ? '../../uploads/profil/' . $pesanan['pencari_foto'] : '../../assets/img/default-avatar.png' ?>" 
                                                         alt="<?= $pesanan['pencari_nama'] ?>" 
                                                         class="avatar-small me-3">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h6 class="mb-1"><?= htmlspecialchars($pesanan['pencari_nama']) ?></h6>
                                                                <div class="small text-muted mb-2">
                                                                    <i class="fas fa-calendar me-1"></i>
                                                                    <?= $pesanan['tanggal_kerja'] ? date('d M Y', strtotime($pesanan['tanggal_kerja'])) : 'Tanggal fleksibel' ?>
                                                                    <?php if ($pesanan['waktu_kerja']): ?>
                                                                        <span class="mx-2">•</span>
                                                                        <i class="fas fa-clock me-1"></i>
                                                                        <?= $pesanan['waktu_kerja'] ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <span class="badge status-badge 
                                                                <?php 
                                                                echo match($pesanan['status']) {
                                                                    'pending' => 'bg-warning text-dark',
                                                                    'diterima' => 'bg-info',
                                                                    'dalam_progress' => 'bg-primary',
                                                                    'selesai' => 'bg-success',
                                                                    'ditolak' => 'bg-danger',
                                                                    'dibatalkan' => 'bg-secondary',
                                                                    default => 'bg-secondary'
                                                                };
                                                                ?>">
                                                                <?= ucfirst(str_replace('_', ' ', $pesanan['status'])) ?>
                                                            </span>
                                                        </div>
                                                        
                                                        <!-- Deskripsi -->
                                                        <p class="mb-2">
                                                            <?= nl2br(htmlspecialchars($pesanan['deskripsi_pekerjaan'])) ?>
                                                        </p>
                                                        
                                                        <!-- Detail tambahan -->
                                                        <div class="small text-muted">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                                    <?= htmlspecialchars($pesanan['alamat_kerja']) ?>
                                                                </div>
                                                                <?php if ($pesanan['estimasi_harga']): ?>
                                                                    <div class="col-md-6">
                                                                        <i class="fas fa-money-bill me-1"></i>
                                                                        Budget: <?= formatRupiah($pesanan['estimasi_harga']) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if ($pesanan['catatan_tambahan']): ?>
                                                                <div class="mt-2">
                                                                    <i class="fas fa-sticky-note me-1"></i>
                                                                    <em><?= htmlspecialchars($pesanan['catatan_tambahan']) ?></em>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- Informasi Lokasi & Maps -->
                                                        <div class="location-info">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <div>
                                                                    <i class="fas fa-location-arrow me-1"></i>
                                                                    <strong>Lokasi Pekerjaan</strong>
                                                                </div>
                                                                <button class="btn btn-sm btn-outline-primary map-toggle-btn" 
                                                                        onclick="toggleMap(<?= $pesanan['id'] ?>)">
                                                                    <i class="fas fa-map me-1"></i>
                                                                    <span id="mapBtnText<?= $pesanan['id'] ?>">Lihat Peta</span>
                                                                </button>
                                                            </div>
                                                            
                                                            <?php if ($distance !== null): ?>
                                                                <span class="distance-badge">
                                                                    <i class="fas fa-route me-1"></i>
                                                                    Jarak: ~<?= $distance ?> km dari lokasi Anda
                                                                </span>
                                                            <?php else: ?>
                                                                <div class="no-coords-warning">
                                                                    <i class="fas fa-info-circle me-1"></i>
                                                                    Lengkapi koordinat lokasi di profil untuk menampilkan jarak
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <!-- Container untuk peta -->
                                                            <div id="mapContainer<?= $pesanan['id'] ?>" class="map-container" style="display: none;">
                                                                <div id="map<?= $pesanan['id'] ?>" style="height: 100%; width: 100%;"></div>
                                                            </div>
                                                            
                                                            <!-- Data koordinat untuk JavaScript -->
                                                            <div class="d-none map-data" 
                                                                 data-pesanan-id="<?= $pesanan['id'] ?>"
                                                                 data-lat="<?= $pesanan['pencari_lat'] ?? '' ?>"
                                                                 data-lng="<?= $pesanan['pencari_lng'] ?? '' ?>"
                                                                 data-alamat="<?= htmlspecialchars($pesanan['alamat_kerja']) ?>"
                                                                 data-pencari-nama="<?= htmlspecialchars($pesanan['pencari_nama']) ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-4">
                                                <div class="text-end">
                                                    <!-- Harga Final jika ada -->
                                                    <?php if ($pesanan['harga_final']): ?>
                                                        <div class="h5 text-success mb-2">
                                                            <?= formatRupiah($pesanan['harga_final']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Tombol Aksi -->
                                                    <div class="action-buttons">
                                                        <?php if ($pesanan['status'] === 'pending'): ?>
                                                            <button class="btn btn-success btn-sm" 
                                                                    onclick="acceptOrder(<?= $pesanan['id'] ?>, '<?= addslashes($pesanan['pencari_nama']) ?>')">
                                                                <i class="fas fa-check me-1"></i>Terima
                                                            </button>
                                                            <button class="btn btn-danger btn-sm" 
                                                                    onclick="updateStatus(<?= $pesanan['id'] ?>, 'ditolak')">
                                                                <i class="fas fa-times me-1"></i>Tolak
                                                            </button>
                                                        <?php elseif ($pesanan['status'] === 'diterima'): ?>
                                                            <button class="btn btn-primary btn-sm" 
                                                                    onclick="updateStatus(<?= $pesanan['id'] ?>, 'dalam_progress')">
                                                                <i class="fas fa-play me-1"></i>Mulai Kerja
                                                            </button>
                                                        <?php elseif ($pesanan['status'] === 'dalam_progress'): ?>
                                                            <button class="btn btn-success btn-sm" 
                                                                    onclick="updateStatus(<?= $pesanan['id'] ?>, 'selesai')">
                                                                <i class="fas fa-check-double me-1"></i>Selesaikan
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($pesanan['pencari_lat'] && $pesanan['pencari_lng']): ?>
                                                            <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $pesanan['pencari_lat'] ?>,<?= $pesanan['pencari_lng'] ?>" 
                                                               target="_blank" 
                                                               class="btn btn-outline-info btn-sm">
                                                                <i class="fas fa-map-marked-alt me-1"></i>Navigasi
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <a href="tel:<?= $pesanan['pencari_telepon'] ?>" 
                                                           class="btn btn-outline-success btn-sm">
                                                            <i class="fas fa-phone me-1"></i>Hubungi
                                                        </a>
                                                    </div>
                                                    
                                                    <!-- Waktu -->
                                                    <div class="small text-muted mt-2">
                                                        <div>Dibuat: <?= timeAgo($pesanan['created_at']) ?></div>
                                                        <?php if ($pesanan['updated_at'] !== $pesanan['created_at']): ?>
                                                            <div>Update: <?= timeAgo($pesanan['updated_at']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center mt-4">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Accept Order -->
    <div class="modal fade" id="acceptOrderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>
                        Terima Pesanan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <i class="fas fa-handshake fa-3x text-success mb-3"></i>
                            <h6>Konfirmasi Penerimaan Pesanan</h6>
                            <p class="text-muted" id="acceptOrderText"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Harga Final <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="harga_final" required 
                                       placeholder="Masukkan harga final">
                            </div>
                            <div class="form-text">
                                Harga ini akan menjadi harga final yang dibayar pelanggan
                            </div>
                        </div>
                        
                        <input type="hidden" name="pesanan_id" id="acceptOrderId">
                        <input type="hidden" name="status" value="diterima">
                        <input type="hidden" name="update_status" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Terima Pesanan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        // Inisialisasi maps yang sudah dibuka
        const openMaps = new Map();
        
        // Filter by status
        function filterByStatus(status) {
            const currentUrl = new URL(window.location);
            if (status) {
                currentUrl.searchParams.set('status', status);
            } else {
                currentUrl.searchParams.delete('status');
            }
            currentUrl.searchParams.delete('page');
            window.location = currentUrl;
        }
        
        // Change sorting
        function changeSort(select) {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('sort', select.value);
            currentUrl.searchParams.delete('page');
            window.location = currentUrl;
        }
        
        // Toggle order
        function toggleOrder() {
            const currentUrl = new URL(window.location);
            const currentOrder = currentUrl.searchParams.get('order') || 'DESC';
            const newOrder = currentOrder === 'DESC' ? 'ASC' : 'DESC';
            currentUrl.searchParams.set('order', newOrder);
            currentUrl.searchParams.delete('page');
            window.location = currentUrl;
        }
        
        // Accept order with price
        function acceptOrder(pesananId, pencariNama) {
            document.getElementById('acceptOrderId').value = pesananId;
            document.getElementById('acceptOrderText').textContent = 
                `Anda akan menerima pesanan dari ${pencariNama}. Pastikan harga yang Anda masukkan sudah sesuai dengan kesepakatan.`;
            
            const modal = new bootstrap.Modal(document.getElementById('acceptOrderModal'));
            modal.show();
        }
        
        // Update status pesanan
        function updateStatus(pesananId, newStatus) {
            let title, text, icon, confirmButtonText;
            
            switch(newStatus) {
                case 'ditolak':
                    title = 'Tolak Pesanan';
                    text = 'Apakah Anda yakin ingin menolak pesanan ini?';
                    icon = 'warning';
                    confirmButtonText = 'Ya, Tolak';
                    break;
                case 'dalam_progress':
                    title = 'Mulai Mengerjakan';
                    text = 'Pesanan akan dimulai dan status berubah menjadi "Dalam Progress"';
                    icon = 'info';
                    confirmButtonText = 'Ya, Mulai';
                    break;
                case 'selesai':
                    title = 'Selesaikan Pesanan';
                    text = 'Apakah pekerjaan sudah selesai 100%?';
                    icon = 'question';
                    confirmButtonText = 'Ya, Selesai';
                    break;
            }
            
            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: confirmButtonText,
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="pesanan_id" value="${pesananId}">
                        <input type="hidden" name="status" value="${newStatus}">
                        <input type="hidden" name="update_status" value="1">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        // Toggle tampilan peta
        function toggleMap(pesananId) {
            const container = document.getElementById(`mapContainer${pesananId}`);
            const btnText = document.getElementById(`mapBtnText${pesananId}`);
            
            if (container.style.display === 'none') {
                container.style.display = 'block';
                btnText.textContent = 'Sembunyikan Peta';
                
                if (!openMaps.has(pesananId)) {
                    loadMap(pesananId);
                    openMaps.set(pesananId, true);
                }
            } else {
                container.style.display = 'none';
                btnText.textContent = 'Lihat Peta';
            }
        }
        
        // Load peta dengan Leaflet
        function loadMap(pesananId) {
            const dataDiv = document.querySelector(`[data-pesanan-id="${pesananId}"]`);
            const lat = parseFloat(dataDiv.dataset.lat);
            const lng = parseFloat(dataDiv.dataset.lng);
            const alamat = dataDiv.dataset.alamat;
            const nama = dataDiv.dataset.pencariNama;
            
            // Jika tidak ada koordinat, gunakan koordinat default Kupang
            if (!lat || !lng) {
                const defaultLat = -10.1772;
                const defaultLng = 123.5973;
                initializeMap(pesananId, defaultLat, defaultLng, alamat, nama, false);
                return;
            }
            
            initializeMap(pesananId, lat, lng, alamat, nama, true);
        }
        
        // Inisialisasi peta
        function initializeMap(pesananId, lat, lng, alamat, nama, hasCoords) {
            const mapId = `map${pesananId}`;
            
            // Hapus map lama jika ada
            const existingMap = window[`mapInstance${pesananId}`];
            if (existingMap) {
                existingMap.remove();
            }
            
            // Buat map baru
            const map = L.map(mapId).setView([lat, lng], hasCoords ? 15 : 12);
            
            // Tambahkan tile layer (OpenStreetMap)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19
            }).addTo(map);
            
            // Icon custom untuk marker
            const customIcon = L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });
            
            // Tambahkan marker lokasi pesanan
            const marker = L.marker([lat, lng], { icon: customIcon }).addTo(map);
            
            // Popup dengan info
            const popupContent = `
                <div style="min-width: 200px;">
                    <strong style="font-size: 1.1em;">${nama}</strong><br>
                    <small class="text-muted">${alamat}</small><br>
                    ${hasCoords ? '' : '<div class="badge bg-warning text-dark mt-1">Koordinat perkiraan</div><br>'}
                    <a href="https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}" 
                       target="_blank" class="btn btn-sm btn-primary mt-2" style="width: 100%;">
                        <i class="fas fa-directions me-1"></i>Petunjuk Arah
                    </a>
                </div>
            `;
            marker.bindPopup(popupContent).openPopup();
            
            // Refresh map size
            setTimeout(() => {
                map.invalidateSize();
            }, 100);
            
            // Simpan instance map
            window[`mapInstance${pesananId}`] = map;
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'r' && !e.ctrlKey && !e.metaKey && !e.target.matches('input, textarea')) {
                e.preventDefault();
                window.location.reload();
            }
            
            const statusMap = {
                '1': '',
                '2': 'pending',
                '3': 'diterima', 
                '4': 'dalam_progress',
                '5': 'selesai',
                '6': 'ditolak'
            };
            
            if (statusMap.hasOwnProperty(e.key) && !e.target.matches('input, textarea')) {
                e.preventDefault();
                filterByStatus(statusMap[e.key]);
            }
        });
        
        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
        
        // Cleanup maps saat halaman di-unload
        window.addEventListener('beforeunload', function() {
            openMaps.forEach((value, pesananId) => {
                const mapInstance = window[`mapInstance${pesananId}`];
                if (mapInstance) {
                    mapInstance.remove();
                }
            });
        });
    </script>
</body>
</html>