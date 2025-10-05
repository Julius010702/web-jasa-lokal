<?php
// pages/pencari/favorit.php
require_once '../../config/config.php';

if (!isLoggedIn() || $_SESSION['user_type'] !== 'pencari') {
    redirect('../../pages/auth/login.php');
}

$user = getCurrentUser();

// Check if favorit table exists, if not create it
try {
    $check_table = $db->query("SHOW TABLES LIKE 'favorit'")->fetch();
    if (!$check_table) {
        // Create favorit table if it doesn't exist
        $create_table_sql = "
            CREATE TABLE favorit (
                id INT PRIMARY KEY AUTO_INCREMENT,
                pencari_id INT NOT NULL,
                pekerja_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (pencari_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (pekerja_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_favorit (pencari_id, pekerja_id)
            )
        ";
        $db->exec($create_table_sql);
    }
} catch (Exception $e) {
    // If table creation fails, show error message
    $table_error = "Tabel favorit belum tersedia. Silakan hubungi administrator.";
}

// Handle add/remove favorit (only if table exists)
if (!isset($table_error) && ($_POST['action'] ?? '')) {
    $pekerja_id = intval($_POST['pekerja_id']);
    
    if ($_POST['action'] === 'add_favorit') {
        // Check if already exists
        $check_stmt = $db->prepare("SELECT id FROM favorit WHERE pencari_id = ? AND pekerja_id = ?");
        $check_stmt->execute([$user['id'], $pekerja_id]);
        
        if (!$check_stmt->fetchColumn()) {
            $insert_stmt = $db->prepare("INSERT INTO favorit (pencari_id, pekerja_id, created_at) VALUES (?, ?, NOW())");
            $insert_stmt->execute([$user['id'], $pekerja_id]);
            $success_message = "Pekerja berhasil ditambahkan ke favorit.";
        }
    } elseif ($_POST['action'] === 'remove_favorit') {
        $delete_stmt = $db->prepare("DELETE FROM favorit WHERE pencari_id = ? AND pekerja_id = ?");
        $delete_stmt->execute([$user['id'], $pekerja_id]);
        $success_message = "Pekerja berhasil dihapus dari favorit.";
    }
    
    if (isset($success_message)) {
        $_SESSION['flash_message'] = $success_message;
        redirect('favorit.php');
    }
}

// Initialize empty arrays
$pekerja_favorit = [];
$kota_list = [];

// Only fetch data if table exists
if (!isset($table_error)) {
    try {
        // Filter
        $kategori_filter = $_GET['kategori'] ?? '';
        $kota_filter = $_GET['kota'] ?? '';
        $search_filter = $_GET['search'] ?? '';

        // Build query dengan filter
        $where_conditions = ["f.pencari_id = ?"];
        $params = [$user['id']];

        if ($kategori_filter) {
            $where_conditions[] = "k.id = ?";
            $params[] = $kategori_filter;
        }

        if ($kota_filter) {
            $where_conditions[] = "u.kota LIKE ?";
            $params[] = "%$kota_filter%";
        }

        if ($search_filter) {
            // Only include deskripsi in search if column exists
            $search_conditions = "(u.nama LIKE ? OR k.nama_kategori LIKE ?";
            $search_term = "%$search_filter%";
            $params[] = $search_term;
            $params[] = $search_term;
            
            if (in_array('deskripsi', $pekerja_columns ?? [])) {
                $search_conditions .= " OR pk.deskripsi LIKE ?";
                $params[] = $search_term;
            }
            $search_conditions .= ")";
            $where_conditions[] = $search_conditions;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Check which columns exist in pekerja table
        $pekerja_columns = $db->query("SHOW COLUMNS FROM pekerja")->fetchAll(PDO::FETCH_COLUMN);
        
        $tarif_column = in_array('tarif_per_jam', $pekerja_columns) ? 'pk.tarif_per_jam' : '0 as tarif_per_jam';
        $deskripsi_column = in_array('deskripsi', $pekerja_columns) ? 'pk.deskripsi' : 'NULL as deskripsi';
        $pengalaman_column = in_array('pengalaman_tahun', $pekerja_columns) ? 'pk.pengalaman_tahun' : '0 as pengalaman_tahun';
        $portofolio_column = in_array('portofolio', $pekerja_columns) ? 'pk.portofolio' : 'NULL as portofolio';

        // Ambil data pekerja favorit
        $stmt = $db->prepare("
            SELECT DISTINCT u.id, u.nama, u.email, u.no_telepon, u.whatsapp, u.kota, u.foto_profil,
                   pk.id as pekerja_id, $tarif_column, $deskripsi_column, $pengalaman_column, $portofolio_column,
                   k.nama_kategori,
                   f.created_at as tanggal_favorit,
                   (SELECT AVG(rating) FROM reviews WHERE pekerja_id = pk.id) as avg_rating,
                   (SELECT COUNT(*) FROM reviews WHERE pekerja_id = pk.id) as total_reviews,
                   (SELECT COUNT(*) FROM pesanan WHERE pekerja_id = u.id AND pencari_id = ?) as total_pesanan_bersama
            FROM favorit f
            JOIN users u ON f.pekerja_id = u.id
            JOIN pekerja pk ON u.id = pk.user_id
            JOIN kategori_jasa k ON pk.kategori_id = k.id
            WHERE $where_clause
            ORDER BY f.created_at DESC
        ");

        $all_params = array_merge([$user['id']], $params);
        $stmt->execute($all_params);
        $pekerja_favorit = $stmt->fetchAll();

        // Get available cities for filter
        $kota_stmt = $db->prepare("
            SELECT DISTINCT u.kota
            FROM favorit f
            JOIN users u ON f.pekerja_id = u.id
            WHERE f.pencari_id = ? AND u.kota IS NOT NULL AND u.kota != ''
            ORDER BY u.kota
        ");
        $kota_stmt->execute([$user['id']]);
        $kota_list = $kota_stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (Exception $e) {
        $query_error = "Terjadi kesalahan saat mengambil data: " . $e->getMessage();
    }
}

// Get available categories for filter
try {
    $kategori_list = getKategoriJasa();
} catch (Exception $e) {
    $kategori_list = [];
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
    <title>Pekerja Favorit - <?= APP_NAME ?></title>
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
        .pekerja-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            overflow: hidden;
        }
        .pekerja-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .pekerja-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .rating-stars {
            color: #ffc107;
        }
        .favorit-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #dc3545;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .stats-row {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin: 15px 0;
        }
        .btn-favorite {
            position: relative;
            overflow: hidden;
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
                        <a class="nav-link active" href="favorit.php">
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
                            <h2 class="fw-bold mb-0">Pekerja Favorit</h2>
                            <p class="text-muted">Daftar pekerja yang Anda sukai</p>
                        </div>
                        <div>
                            <span class="badge bg-primary fs-6"><?= count($pekerja_favorit) ?> Favorit</span>
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

                    <!-- Error Messages -->
                    <?php if (isset($table_error)): ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= $table_error ?>
                            <hr>
                            <p class="mb-0">Untuk membuat tabel favorit secara manual, jalankan SQL berikut:</p>
                            <code class="d-block mt-2 p-2 bg-light">
                                CREATE TABLE favorit (
                                    id INT PRIMARY KEY AUTO_INCREMENT,
                                    pencari_id INT NOT NULL,
                                    pekerja_id INT NOT NULL,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (pencari_id) REFERENCES users(id) ON DELETE CASCADE,
                                    FOREIGN KEY (pekerja_id) REFERENCES users(id) ON DELETE CASCADE,
                                    UNIQUE KEY unique_favorit (pencari_id, pekerja_id)
                                );
                            </code>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($query_error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-times-circle me-2"></i>
                            <?= $query_error ?>
                        </div>
                    <?php endif; ?>

                    <!-- Filter (only show if no table error) -->
                    <?php if (!isset($table_error)): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Kategori</label>
                                    <select name="kategori" class="form-select">
                                        <option value="">Semua Kategori</option>
                                        <?php foreach ($kategori_list as $kat): ?>
                                            <option value="<?= $kat['id'] ?>" <?= $kategori_filter == $kat['id'] ? 'selected' : '' ?>>
                                                <?= $kat['nama_kategori'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Kota</label>
                                    <select name="kota" class="form-select">
                                        <option value="">Semua Kota</option>
                                        <?php foreach ($kota_list as $kota): ?>
                                            <option value="<?= $kota ?>" <?= $kota_filter === $kota ? 'selected' : '' ?>>
                                                <?= $kota ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Cari</label>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Cari nama pekerja atau kategori..." 
                                           value="<?= htmlspecialchars($search_filter) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-1"></i>
                                            Filter
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Pekerja Favorit -->
                    <?php if (isset($table_error) || empty($pekerja_favorit)): ?>
                        <div class="empty-state">
                            <i class="fas fa-heart fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted"><?= isset($table_error) ? 'Fitur Favorit Belum Tersedia' : 'Belum Ada Pekerja Favorit' ?></h4>
                            <p class="text-muted mb-4">
                                <?= isset($table_error) ? 
                                    'Silakan hubungi administrator untuk mengaktifkan fitur favorit.' : 
                                    'Temukan pekerja yang sesuai dan tambahkan ke favorit untuk memudahkan akses di kemudian hari.' ?>
                            </p>
                            <a href="../../search.php" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>
                                Cari Pekerja
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($pekerja_favorit as $pekerja): ?>
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="card pekerja-card h-100 position-relative">
                                        <div class="favorit-badge">
                                            <i class="fas fa-heart"></i>
                                        </div>
                                        
                                        <div class="card-body text-center">
                                            <img src="<?= $pekerja['foto_profil'] ? '../../uploads/profil/' . $pekerja['foto_profil'] : '../../assets/img/default-avatar.png' ?>" 
                                                 alt="<?= $pekerja['nama'] ?>" class="pekerja-avatar mb-3">
                                            
                                            <h5 class="card-title mb-1"><?= $pekerja['nama'] ?></h5>
                                            <p class="text-primary mb-2"><?= $pekerja['nama_kategori'] ?></p>
                                            
                                            <!-- Rating -->
                                            <div class="rating-stars mb-2">
                                                <?php
                                                $rating = $pekerja['avg_rating'] ?: 0;
                                                for ($i = 1; $i <= 5; $i++):
                                                ?>
                                                    <i class="fas fa-star <?= $i <= $rating ? '' : 'text-muted' ?>"></i>
                                                <?php endfor; ?>
                                                <span class="text-muted ms-2">(<?= $pekerja['total_reviews'] ?> review)</span>
                                            </div>
                                            
                                            <!-- Stats -->
                                            <div class="stats-row">
                                                <div class="row text-center">
                                                    <div class="col-4">
                                                        <div class="fw-bold text-primary"><?= $pekerja['pengalaman_tahun'] ?></div>
                                                        <small class="text-muted">Tahun</small>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="fw-bold text-success"><?= $pekerja['total_pesanan_bersama'] ?></div>
                                                        <small class="text-muted">Pesanan</small>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="fw-bold text-warning">
                                                            <?= $pekerja['tarif_per_jam'] ? number_format($pekerja['tarif_per_jam'], 0, ',', '.') : 'N/A' ?>
                                                        </div>
                                                        <small class="text-muted">Per Jam</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Location -->
                                            <p class="text-muted mb-3">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?= $pekerja['kota'] ?>
                                            </p>
                                            
                                            <!-- Description -->
                                            <?php if ($pekerja['deskripsi']): ?>
                                                <p class="text-muted small mb-3">
                                                    <?= substr(strip_tags($pekerja['deskripsi']), 0, 80) ?>
                                                    <?= strlen($pekerja['deskripsi']) > 80 ? '...' : '' ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <!-- Added to favorite date -->
                                            <small class="text-muted">
                                                <i class="fas fa-heart me-1"></i>
                                                Ditambahkan <?= date('d M Y', strtotime($pekerja['tanggal_favorit'])) ?>
                                            </small>
                                        </div>
                                        
                                        <div class="card-footer bg-transparent">
                                            <div class="d-grid gap-2">
                                                <div class="btn-group">
                                                    <a href="../../detail-pekerja.php?id=<?= $pekerja['id'] ?>" 
                                                       class="btn btn-primary">
                                                        <i class="fas fa-eye me-2"></i>
                                                        Lihat Profil
                                                    </a>
                                                    <?php if ($pekerja['whatsapp']): ?>
                                                        <a href="https://wa.me/<?= $pekerja['whatsapp'] ?>" 
                                                           class="btn btn-success" target="_blank">
                                                            <i class="fab fa-whatsapp"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <form method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Hapus dari favorit?')">
                                                    <input type="hidden" name="pekerja_id" value="<?= $pekerja['id'] ?>">
                                                    <input type="hidden" name="action" value="remove_favorit">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                                        <i class="fas fa-heart-broken me-2"></i>
                                                        Hapus dari Favorit
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Additional Actions -->
                        <div class="text-center mt-4">
                            <a href="../../search.php" class="btn btn-outline-primary me-2">
                                <i class="fas fa-search me-2"></i>
                                Cari Pekerja Lainnya
                            </a>
                            <a href="pesanan.php" class="btn btn-outline-secondary">
                                <i class="fas fa-clipboard-list me-2"></i>
                                Lihat Pesanan Saya
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth animation for card hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.pekerja-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>