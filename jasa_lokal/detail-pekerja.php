

<?php
// detail-pekerja.php
require_once 'config/config.php';

$pekerja_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$pekerja_id) {
    redirect('search.php');
}

// Ambil detail pekerja
$stmt = $db->prepare("
    SELECT u.*, p.*, k.nama_kategori, k.icon,
           AVG(r.rating) as avg_rating,
           COUNT(r.id) as total_reviews
    FROM users u
    JOIN pekerja p ON u.id = p.user_id
    JOIN kategori_jasa k ON p.kategori_id = k.id
    LEFT JOIN reviews r ON p.id = r.pekerja_id
    WHERE u.id = ? AND u.status = 'aktif' AND u.user_type = 'pekerja'
    GROUP BY u.id, p.id
");
$stmt->execute([$pekerja_id]);
$pekerja = $stmt->fetch();

if (!$pekerja) {
    redirect('search.php');
}

// Ambil portfolio
$stmt = $db->prepare("SELECT * FROM portfolio WHERE pekerja_id = ? ORDER BY urutan, created_at DESC");
$stmt->execute([$pekerja['id']]);
$portfolio = $stmt->fetchAll();

// Ambil reviews
$stmt = $db->prepare("
    SELECT r.*, u.nama as reviewer_nama
    FROM reviews r
    JOIN users u ON r.pencari_id = u.id
    WHERE r.pekerja_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
");
$stmt->execute([$pekerja['id']]);
$reviews = $stmt->fetchAll();

// Ambil pekerja serupa
$stmt = $db->prepare("
    SELECT u.*, p.*, k.nama_kategori,
           AVG(r.rating) as avg_rating
    FROM users u
    JOIN pekerja p ON u.id = p.user_id
    JOIN kategori_jasa k ON p.kategori_id = k.id
    LEFT JOIN reviews r ON p.id = r.pekerja_id
    WHERE p.kategori_id = ? AND u.id != ? AND u.status = 'aktif'
    GROUP BY u.id, p.id
    ORDER BY avg_rating DESC
    LIMIT 4
");
$stmt->execute([$pekerja['kategori_id'], $pekerja_id]);
$pekerja_serupa = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pekerja['nama'] ?> - <?= $pekerja['nama_kategori'] ?> - <?= APP_NAME ?></title>
    <meta name="description" content="<?= $pekerja['nama'] ?> - <?= $pekerja['nama_kategori'] ?> berpengalaman <?= $pekerja['pengalaman_tahun'] ?> tahun di <?= $pekerja['kota'] ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/lightbox2@2.11.3/dist/css/lightbox.min.css" rel="stylesheet">
    
    <style>
        .worker-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
        }
        
        .worker-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .rating {
            color: #ffc107;
        }
        
        .contact-card {
            position: sticky;
            top: 100px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .portfolio-item {
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .portfolio-item:hover {
            transform: scale(1.05);
        }
        
        .portfolio-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        
        .review-card {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
        
        .similar-worker {
            transition: all 0.3s;
        }
        
        .similar-worker:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .badge-verified {
            background: linear-gradient(45deg, #28a745, #20c997);
        }
        
        .working-hours {
            background: #e3f2fd;
            border-radius: 10px;
            padding: 15px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-handshake text-primary me-2"></i>
                <?= APP_NAME ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="search.php">
                    <i class="fas fa-arrow-left me-1"></i>
                    Kembali ke Pencarian
                </a>
            </div>
        </div>
    </nav>

    <!-- Worker Header -->
    <section class="worker-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-3 text-center text-lg-start mb-4 mb-lg-0">
                    <img src="<?= $pekerja['foto_profil'] ? 'uploads/profil/' . $pekerja['foto_profil'] : 'assets/img/default-avatar.png' ?>" 
                         alt="<?= $pekerja['nama'] ?>" class="worker-avatar">
                </div>
                <div class="col-lg-6">
                    <h1 class="fw-bold mb-2">
                        <?= $pekerja['nama'] ?>
                        <?php if ($pekerja['is_verified']): ?>
                            <span class="badge badge-verified ms-2">
                                <i class="fas fa-check-circle"></i> Terverifikasi
                            </span>
                        <?php endif; ?>
                    </h1>
                    
                    <div class="mb-3">
                        <span class="badge bg-light text-dark fs-6 me-2">
                            <i class="<?= $pekerja['icon'] ?> me-1"></i>
                            <?= $pekerja['nama_kategori'] ?>
                        </span>
                        <span class="badge bg-light text-dark fs-6">
                            <i class="fas fa-briefcase me-1"></i>
                            <?= $pekerja['pengalaman_tahun'] ?> tahun pengalaman
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <?= $pekerja['alamat'] ? $pekerja['alamat'] . ', ' : '' ?>
                        <?= $pekerja['kecamatan'] ? $pekerja['kecamatan'] . ', ' : '' ?>
                        <?= $pekerja['kota'] ?>
                    </div>
                    
                    <div class="d-flex align-items-center">
                        <div class="rating me-3">
                            <?php
                            $rating = $pekerja['avg_rating'] ?: 0;
                            for ($i = 1; $i <= 5; $i++):
                            ?>
                                <i class="fas fa-star <?= $i <= $rating ? '' : 'text-muted' ?>"></i>
                            <?php endfor; ?>
                            <span class="ms-2 fw-bold"><?= number_format($rating, 1) ?></span>
                            <small class="ms-1">(<?= $pekerja['total_reviews'] ?> ulasan)</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 text-center">
                    <div class="mb-3">
                        <?php if ($pekerja['harga_mulai']): ?>
                            <div class="h4 mb-0">
                                <?= formatRupiah($pekerja['harga_mulai']) ?>
                                <?php if ($pekerja['harga_hingga'] && $pekerja['harga_hingga'] != $pekerja['harga_mulai']): ?>
                                    - <?= formatRupiah($pekerja['harga_hingga']) ?>
                                <?php else: ?>
                                    +
                                <?php endif; ?>
                            </div>
                            <small class="text-light">Harga mulai dari</small>
                        <?php else: ?>
                            <div class="h5">Harga Nego</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <!-- Left Content -->
                <div class="col-lg-8">
                    <!-- Deskripsi -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-user-circle text-primary me-2"></i>
                                Tentang Saya
                            </h5>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($pekerja['deskripsi_skill'])) ?></p>
                        </div>
                    </div>
                    
                    <!-- Jam Kerja -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-clock text-primary me-2"></i>
                                Jam Kerja
                            </h5>
                            <div class="working-hours">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Jam Operasional:</strong><br>
                                        <?= $pekerja['jam_kerja_mulai'] ? date('H:i', strtotime($pekerja['jam_kerja_mulai'])) : '08:00' ?> - 
                                        <?= $pekerja['jam_kerja_selesai'] ? date('H:i', strtotime($pekerja['jam_kerja_selesai'])) : '17:00' ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Hari Kerja:</strong><br>
                                        <?= $pekerja['hari_kerja'] ?: 'Senin - Jumat' ?>
                                    </div>
                                </div>
                                <?php if ($pekerja['radius_kerja']): ?>
                                    <div class="mt-2">
                                        <strong>Jangkauan Area:</strong> <?= $pekerja['radius_kerja'] ?> km dari lokasi
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Portfolio -->
                    <?php if (!empty($portfolio)): ?>
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="fw-bold mb-3">
                                    <i class="fas fa-images text-primary me-2"></i>
                                    Portfolio & Galeri
                                </h5>
                                <div class="row g-3">
                                    <?php foreach ($portfolio as $item): ?>
                                        <div class="col-md-4">
                                            <div class="portfolio-item">
                                                <a href="uploads/portfolio/<?= $item['foto'] ?>" data-lightbox="portfolio" 
                                                   data-title="<?= htmlspecialchars($item['deskripsi']) ?>">
                                                    <img src="uploads/portfolio/<?= $item['foto'] ?>" 
                                                         alt="<?= htmlspecialchars($item['deskripsi']) ?>" 
                                                         class="img-fluid">
                                                </a>
                                            </div>
                                            <?php if ($item['deskripsi']): ?>
                                                <p class="small text-muted mt-2 mb-0"><?= htmlspecialchars($item['deskripsi']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Reviews -->
                    <?php if (!empty($reviews)): ?>
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="fw-bold mb-3">
                                    <i class="fas fa-star text-primary me-2"></i>
                                    Ulasan & Rating
                                </h5>
                                
                                <?php foreach ($reviews as $review): ?>
                                    <div class="review-card p-3 mb-3 rounded">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong><?= htmlspecialchars($review['reviewer_nama']) ?></strong>
                                                <div class="rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?= $i <= $review['rating'] ? '' : 'text-muted' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <small class="text-muted"><?= timeAgo($review['created_at']) ?></small>
                                        </div>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($review['komentar'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Right Sidebar -->
                <div class="col-lg-4">
                    <!-- Contact Card -->
                    <div class="card contact-card mb-4">
                        <div class="card-body text-center p-4">
                            <h5 class="fw-bold mb-3">Hubungi Sekarang</h5>
                            
                            <div class="d-grid gap-3">
                                <a href="https://wa.me/<?= $pekerja['whatsapp'] ?>" 
                                   class="btn btn-success btn-lg" target="_blank">
                                    <i class="fab fa-whatsapp me-2"></i>
                                    WhatsApp
                                </a>
                                
                                <a href="tel:<?= $pekerja['no_telepon'] ?>" 
                                   class="btn btn-primary btn-lg">
                                    <i class="fas fa-phone me-2"></i>
                                    Telepon
                                </a>
                                
                                <?php if (isLoggedIn() && $_SESSION['user_type'] === 'pencari'): ?>
                                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#pesanModal">
                                        <i class="fas fa-envelope me-2"></i>
                                        Kirim Pesan
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="row text-start">
                                <div class="col-12 mb-2">
                                    <small class="text-muted">Status:</small><br>
                                    <span class="badge bg-success">Aktif</span>
                                </div>
                                <div class="col-12 mb-2">
                                    <small class="text-muted">Total Pekerjaan:</small><br>
                                    <strong><?= $pekerja['total_pekerjaan'] ?></strong>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">Bergabung:</small><br>
                                    <strong><?= date('M Y', strtotime($pekerja['created_at'])) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Share Card -->
                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <h6 class="fw-bold mb-3">Bagikan Profil</h6>
                            <div class="d-flex justify-content-center gap-2">
                                <a href="https://wa.me/?text=Lihat profil pekerja ini: <?= urlencode(APP_URL . '/detail-pekerja.php?id=' . $pekerja_id) ?>" 
                                   class="btn btn-success btn-sm" target="_blank">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(APP_URL . '/detail-pekerja.php?id=' . $pekerja_id) ?>" 
                                   class="btn btn-primary btn-sm" target="_blank">
                                    <i class="fab fa-facebook"></i>
                                </a>
                                <button class="btn btn-secondary btn-sm" onclick="copyLink()">
                                    <i class="fas fa-link"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pekerja Serupa -->
            <?php if (!empty($pekerja_serupa)): ?>
                <div class="row mt-5">
                    <div class="col-12">
                        <h4 class="fw-bold mb-4"><?= $pekerja['nama_kategori'] ?> Lainnya</h4>
                        <div class="row g-4">
                            <?php foreach ($pekerja_serupa as $similar): ?>
                                <div class="col-lg-3 col-md-6">
                                    <div class="card similar-worker h-100">
                                        <div class="card-body text-center p-3">
                                            <img src="<?= $similar['foto_profil'] ? 'uploads/profil/' . $similar['foto_profil'] : 'assets/img/default-avatar.png' ?>" 
                                                 alt="<?= $similar['nama'] ?>" 
                                                 class="rounded-circle mb-3" 
                                                 style="width: 60px; height: 60px; object-fit: cover;">
                                            
                                            <h6 class="fw-bold mb-1"><?= $similar['nama'] ?></h6>
                                            <small class="text-muted d-block mb-2"><?= $similar['kota'] ?></small>
                                            
                                            <div class="rating mb-3">
                                                <?php
                                                $sim_rating = $similar['avg_rating'] ?: 0;
                                                for ($i = 1; $i <= 5; $i++):
                                                ?>
                                                    <i class="fas fa-star <?= $i <= $sim_rating ? '' : 'text-muted' ?> small"></i>
                                                <?php endfor; ?>
                                            </div>
                                            
                                            <a href="detail-pekerja.php?id=<?= $similar['user_id'] ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                Lihat Profil
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Modal Pesan (jika user login sebagai pencari) -->
    <?php if (isLoggedIn() && $_SESSION['user_type'] === 'pencari'): ?>
        <div class="modal fade" id="pesanModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Kirim Pesan ke <?= $pekerja['nama'] ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="api/send-message.php">
                        <div class="modal-body">
                            <input type="hidden" name="pekerja_id" value="<?= $pekerja_id ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Deskripsi Pekerjaan</label>
                                <textarea class="form-control" name="deskripsi_pekerjaan" rows="4" required 
                                          placeholder="Jelaskan pekerjaan yang Anda butuhkan..."></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tanggal</label>
                                    <input type="date" class="form-control" name="tanggal_kerja" 
                                           min="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Jam</label>
                                    <input type="time" class="form-control" name="jam_kerja">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Alamat Pekerjaan</label>
                                <textarea class="form-control" name="alamat_pekerjaan" rows="2" required 
                                          placeholder="Alamat lengkap lokasi pekerjaan"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Estimasi Budget</label>
                                <input type="number" class="form-control" name="estimasi_harga" 
                                       placeholder="Masukkan estimasi budget">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Kirim Pesan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lightbox2@2.11.3/dist/js/lightbox.min.js"></script>
    <script>
        // Copy link function
        function copyLink() {
            navigator.clipboard.writeText(window.location.href).then(function() {
                alert('Link berhasil disalin!');
            });
        }

        // Lightbox configuration
        lightbox.option({
            'resizeDuration': 200,
            'wrapAround': true
        });

        // WhatsApp click tracking
        document.querySelector('a[href*="wa.me"]').addEventListener('click', function() {
            // Track WhatsApp click (analytics)
            console.log('WhatsApp contact clicked for worker ID: <?= $pekerja_id ?>');
        });
    </script>
</body>
</html>