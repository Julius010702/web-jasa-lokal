<?php
// pages/pencari/pesanan.php
require_once '../../config/config.php';

if (!isLoggedIn() || $_SESSION['user_type'] !== 'pencari') {
    redirect('../../pages/auth/login.php');
}

$user = getCurrentUser();

// Filter dan pagination
$status_filter = $_GET['status'] ?? '';
$search_filter = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query dengan filter
$where_conditions = ["p.pencari_id = ?"];
$params = [$user['id']];

if ($status_filter) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if ($search_filter) {
    $where_conditions[] = "(u.nama LIKE ? OR p.kode_pesanan LIKE ? OR k.nama_kategori LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where_conditions);

// Total pesanan untuk pagination
$count_stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM pesanan p
    JOIN users u ON p.pekerja_id = u.id
    JOIN pekerja pk ON u.id = pk.user_id
    JOIN kategori_jasa k ON pk.kategori_id = k.id
    WHERE $where_clause
");
$count_stmt->execute($params);
$total_pesanan = $count_stmt->fetchColumn();
$total_pages = ceil($total_pesanan / $limit);

// Ambil data pesanan - FIXED: Removed pk.tarif_per_jam
$stmt = $db->prepare("
    SELECT p.*, u.nama as pekerja_nama, u.no_telepon, u.whatsapp, u.foto_profil,
           k.nama_kategori, pk.id as pekerja_id
    FROM pesanan p
    JOIN users u ON p.pekerja_id = u.id
    JOIN pekerja pk ON u.id = pk.user_id
    JOIN kategori_jasa k ON pk.kategori_id = k.id
    WHERE $where_clause
    ORDER BY p.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$pesanan_list = $stmt->fetchAll();

// Handle aksi
if ($_POST['action'] ?? '') {
    $pesanan_id = intval($_POST['pesanan_id']);
    
    // Verifikasi kepemilikan pesanan
    $verify_stmt = $db->prepare("SELECT id FROM pesanan WHERE id = ? AND pencari_id = ?");
    $verify_stmt->execute([$pesanan_id, $user['id']]);
    
    if ($verify_stmt->fetchColumn()) {
        switch ($_POST['action']) {
            case 'batalkan':
                if (($_POST['current_status'] ?? '') === 'pending') {
                    $update_stmt = $db->prepare("UPDATE pesanan SET status = 'dibatalkan' WHERE id = ?");
                    $update_stmt->execute([$pesanan_id]);
                    $success_message = "Pesanan berhasil dibatalkan.";
                }
                break;
                
            case 'konfirmasi_selesai':
                if (($_POST['current_status'] ?? '') === 'dalam_progress') {
                    $update_stmt = $db->prepare("UPDATE pesanan SET status = 'selesai' WHERE id = ?");
                    $update_stmt->execute([$pesanan_id]);
                    $success_message = "Pesanan dikonfirmasi selesai.";
                }
                break;
        }
        
        if (isset($success_message)) {
            $_SESSION['flash_message'] = $success_message;
            redirect('pesanan.php?' . http_build_query($_GET));
        }
    }
}

// Flash message
$flash_message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - <?= APP_NAME ?></title>
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
        .pesanan-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .pesanan-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 20px;
        }
        .pekerja-info {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            border-radius: 8px;
            padding: 15px;
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
                        <a class="nav-link active" href="pesanan.php">
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
                            <h2 class="fw-bold mb-0">Pesanan Saya</h2>
                            <p class="text-muted">Kelola semua pesanan Anda</p>
                        </div>
                        <div>
                            <a href="../../search.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>
                                Buat Pesanan Baru
                            </a>
                        </div>
                    </div>

                    <!-- Flash Message -->
                    <?php if ($flash_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= $flash_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Filter dan Search -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">Semua Status</option>
                                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="diterima" <?= $status_filter === 'diterima' ? 'selected' : '' ?>>Diterima</option>
                                        <option value="dalam_progress" <?= $status_filter === 'dalam_progress' ? 'selected' : '' ?>>Dalam Progress</option>
                                        <option value="selesai" <?= $status_filter === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                        <option value="dibatalkan" <?= $status_filter === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Cari</label>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Cari berdasarkan kode, pekerja, atau kategori..." 
                                           value="<?= htmlspecialchars($search_filter) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-2"></i>
                                            Filter
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Pesanan List -->
                    <?php if (empty($pesanan_list)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">Tidak ada pesanan ditemukan</h4>
                            <p class="text-muted">Buat pesanan pertama Anda sekarang!</p>
                            <a href="../../search.php" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>
                                Cari Pekerja
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($pesanan_list as $pesanan): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="card pesanan-card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0"><?= $pesanan['kode_pesanan'] ?></h6>
                                                <small class="text-muted"><?= date('d M Y, H:i', strtotime($pesanan['created_at'])) ?></small>
                                            </div>
                                            <div>
                                                <?php
                                                $status_colors = [
                                                    'pending' => 'warning',
                                                    'diterima' => 'info',
                                                    'dalam_progress' => 'primary',
                                                    'selesai' => 'success',
                                                    'dibatalkan' => 'danger'
                                                ];
                                                ?>
                                                <span class="badge bg-<?= $status_colors[$pesanan['status']] ?> status-badge">
                                                    <?= ucfirst(str_replace('_', ' ', $pesanan['status'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="card-body">
                                            <!-- Info Pekerja -->
                                            <div class="pekerja-info mb-3">
                                                <div class="d-flex align-items-center mb-2">
                                                    <img src="<?= $pesanan['foto_profil'] ? '../../uploads/profil/' . $pesanan['foto_profil'] : '../../assets/img/default-avatar.png' ?>" 
                                                         alt="<?= $pesanan['pekerja_nama'] ?>" 
                                                         class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                                    <div>
                                                        <h6 class="mb-0"><?= $pesanan['pekerja_nama'] ?></h6>
                                                        <small class="text-muted"><?= $pesanan['nama_kategori'] ?></small>
                                                    </div>
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <small class="text-muted">No. Telepon:</small><br>
                                                        <small><?= $pesanan['no_telepon'] ?></small>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted">Kategori:</small><br>
                                                        <small class="fw-bold"><?= $pesanan['nama_kategori'] ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Detail Pesanan -->
                                            <div class="mb-3">
                                                <h6 class="mb-2">Detail Pekerjaan:</h6>
                                                <p class="text-muted small mb-2"><?= nl2br(htmlspecialchars($pesanan['deskripsi_pekerjaan'])) ?></p>
                                                
                                                <div class="row g-2 small">
                                                    <div class="col-6">
                                                        <i class="fas fa-calendar text-primary me-1"></i>
                                                        <?= date('d/m/Y', strtotime($pesanan['tanggal_mulai'])) ?>
                                                    </div>
                                                    <div class="col-6">
                                                        <i class="fas fa-clock text-primary me-1"></i>
                                                        <?= date('H:i', strtotime($pesanan['jam_mulai'])) ?>
                                                    </div>
                                                    <div class="col-6">
                                                        <i class="fas fa-hourglass-half text-primary me-1"></i>
                                                        <?= $pesanan['estimasi_jam'] ?> jam
                                                    </div>
                                                    <div class="col-6">
                                                        <i class="fas fa-money-bill text-success me-1"></i>
                                                        Rp <?= number_format($pesanan['total_biaya'], 0, ',', '.') ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Alamat -->
                                            <div class="mb-3">
                                                <small class="text-muted">Alamat:</small><br>
                                                <small><?= htmlspecialchars($pesanan['alamat_lengkap']) ?></small>
                                            </div>
                                            
                                            <!-- Action Buttons -->
                                            <div class="d-flex gap-2 flex-wrap">
                                                <a href="pesanan-detail.php?id=<?= $pesanan['id'] ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>
                                                    Detail
                                                </a>
                                                
                                                <?php if ($pesanan['whatsapp']): ?>
                                                    <a href="https://wa.me/<?= $pesanan['whatsapp'] ?>" 
                                                       class="btn btn-success btn-sm" target="_blank">
                                                        <i class="fab fa-whatsapp me-1"></i>
                                                        WhatsApp
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($pesanan['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin membatalkan pesanan ini?')">
                                                        <input type="hidden" name="pesanan_id" value="<?= $pesanan['id'] ?>">
                                                        <input type="hidden" name="current_status" value="<?= $pesanan['status'] ?>">
                                                        <input type="hidden" name="action" value="batalkan">
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-times me-1"></i>
                                                            Batalkan
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($pesanan['status'] === 'dalam_progress'): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Konfirmasi bahwa pekerjaan sudah selesai?')">
                                                        <input type="hidden" name="pesanan_id" value="<?= $pesanan['id'] ?>">
                                                        <input type="hidden" name="current_status" value="<?= $pesanan['status'] ?>">
                                                        <input type="hidden" name="action" value="konfirmasi_selesai">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check me-1"></i>
                                                            Selesai
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($pesanan['status'] === 'selesai'): ?>
                                                    <a href="review.php?pesanan_id=<?= $pesanan['id'] ?>" 
                                                       class="btn btn-warning btn-sm">
                                                        <i class="fas fa-star me-1"></i>
                                                        Review
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Pagination">
                                <ul class="pagination justify-content-center">
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
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>