<?php
// pages/pekerja/portfolio.php
require_once '../../config/config.php';

if (!isLoggedIn() || $_SESSION['user_type'] !== 'pekerja') {
    redirect('../../pages/auth/login.php');
}

$user = getCurrentUser();
$error = '';
$success = '';

// Ambil data pekerja
$stmt = $db->prepare("SELECT id FROM pekerja WHERE user_id = ?");
$stmt->execute([$user['id']]);
$pekerja_data = $stmt->fetch();

if (!$pekerja_data) {
    redirect('profil.php');
}

$pekerja_id = $pekerja_data['id'];

// Handle upload portfolio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $deskripsi = sanitizeInput($_POST['deskripsi']);
    
    if (isset($_FILES['foto_portfolio']) && $_FILES['foto_portfolio']['error'] === UPLOAD_ERR_OK) {
        $uploaded_foto = uploadFile($_FILES['foto_portfolio'], 'portfolio');
        if ($uploaded_foto) {
            try {
                $stmt = $db->prepare("INSERT INTO portfolio (pekerja_id, foto, deskripsi) VALUES (?, ?, ?)");
                $stmt->execute([$pekerja_id, $uploaded_foto, $deskripsi]);
                $success = 'Foto portfolio berhasil ditambahkan!';
            } catch (Exception $e) {
                $error = 'Gagal menyimpan foto portfolio!';
                // Hapus file yang sudah diupload
                if (file_exists('../../uploads/portfolio/' . $uploaded_foto)) {
                    unlink('../../uploads/portfolio/' . $uploaded_foto);
                }
            }
        } else {
            $error = 'Gagal upload foto! Pastikan file berformat JPG/PNG dan ukuran maksimal 2MB.';
        }
    } else {
        $error = 'Silakan pilih foto untuk diupload!';
    }
}

// Handle hapus portfolio
if (isset($_GET['delete'])) {
    $portfolio_id = (int)$_GET['delete'];
    
    // Ambil data portfolio untuk hapus file
    $stmt = $db->prepare("SELECT foto FROM portfolio WHERE id = ? AND pekerja_id = ?");
    $stmt->execute([$portfolio_id, $pekerja_id]);
    $portfolio_item = $stmt->fetch();
    
    if ($portfolio_item) {
        try {
            // Hapus dari database
            $stmt = $db->prepare("DELETE FROM portfolio WHERE id = ? AND pekerja_id = ?");
            $stmt->execute([$portfolio_id, $pekerja_id]);
            
            // Hapus file
            if (file_exists('../../uploads/portfolio/' . $portfolio_item['foto'])) {
                unlink('../../uploads/portfolio/' . $portfolio_item['foto']);
            }
            
            $success = 'Foto portfolio berhasil dihapus!';
        } catch (Exception $e) {
            $error = 'Gagal menghapus foto portfolio!';
        }
    }
}

// Handle update urutan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order') {
    $portfolio_ids = $_POST['portfolio_ids'];
    
    try {
        foreach ($portfolio_ids as $index => $id) {
            $stmt = $db->prepare("UPDATE portfolio SET urutan = ? WHERE id = ? AND pekerja_id = ?");
            $stmt->execute([$index + 1, (int)$id, $pekerja_id]);
        }
        $success = 'Urutan portfolio berhasil diperbarui!';
    } catch (Exception $e) {
        $error = 'Gagal memperbarui urutan portfolio!';
    }
}

// Ambil portfolio
$stmt = $db->prepare("SELECT * FROM portfolio WHERE pekerja_id = ? ORDER BY urutan, created_at DESC");
$stmt->execute([$pekerja_id]);
$portfolio_items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio - Dashboard Pekerja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.css" rel="stylesheet">
    
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
        
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            background: white;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: #007bff;
            background: #f8f9ff;
        }
        
        .upload-area.dragover {
            border-color: #007bff;
            background: #e3f2fd;
        }
        
        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .portfolio-item {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: move;
        }
        
        .portfolio-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .portfolio-item.sortable-ghost {
            opacity: 0.4;
        }
        
        .portfolio-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .portfolio-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s;
        }
        
        .portfolio-item:hover .portfolio-overlay {
            opacity: 1;
        }
        
        .portfolio-actions {
            display: flex;
            gap: 10px;
        }
        
        .drag-handle {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.5);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: move;
        }
        
        .empty-portfolio {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .preview-modal .modal-dialog {
            max-width: 90vw;
        }
        
        .preview-img {
            width: 100%;
            height: auto;
            max-height: 70vh;
            object-fit: contain;
        }
        
        @media (max-width: 768px) {
            .portfolio-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
            }
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
                    <a class="nav-link active" href="portfolio.php">
                        <i class="fas fa-images me-2"></i>
                        Portfolio
                    </a>
                    <a class="nav-link" href="pesanan.php">
                        <i class="fas fa-clipboard-list me-2"></i>
                        Pesanan
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
                        <h2 class="fw-bold mb-1">Portfolio</h2>
                        <p class="text-muted mb-0">Tampilkan hasil kerja terbaik Anda (<?= count($portfolio_items) ?>/20 foto)</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-plus me-2"></i>
                        Tambah Foto
                    </button>
                </div>
                
                <!-- Alerts -->
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= $success ?>
                    </div>
                <?php endif; ?>
                
                <!-- Tips -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-2">
                            <i class="fas fa-lightbulb text-warning me-2"></i>
                            Tips Portfolio yang Menarik
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled small mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i>Upload foto hasil kerja terbaik</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Gunakan foto berkualitas tinggi</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Berikan deskripsi yang jelas</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled small mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i>Tunjukkan before & after</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Atur urutan foto dengan drag & drop</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Update portfolio secara rutin</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Portfolio Grid -->
                <?php if (empty($portfolio_items)): ?>
                    <div class="empty-portfolio">
                        <i class="fas fa-images fa-4x mb-3"></i>
                        <h4>Portfolio Masih Kosong</h4>
                        <p>Tambahkan foto-foto hasil kerja terbaik Anda untuk menarik lebih banyak pelanggan</p>
                        <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#uploadModal">
                            <i class="fas fa-plus me-2"></i>
                            Tambah Foto Pertama
                        </button>
                    </div>
                <?php else: ?>
                    <div id="portfolioGrid" class="portfolio-grid">
                        <?php foreach ($portfolio_items as $item): ?>
                            <div class="portfolio-item" data-id="<?= $item['id'] ?>">
                                <div class="position-relative">
                                    <img src="../../uploads/portfolio/<?= $item['foto'] ?>" 
                                         alt="Portfolio" class="portfolio-img"
                                         onclick="previewImage('../../uploads/portfolio/<?= $item['foto'] ?>', '<?= htmlspecialchars($item['deskripsi']) ?>')">
                                    
                                    <div class="drag-handle">
                                        <i class="fas fa-grip-vertical"></i>
                                    </div>
                                    
                                    <div class="portfolio-overlay">
                                        <div class="portfolio-actions">
                                            <button class="btn btn-light btn-sm" 
                                                    onclick="previewImage('../../uploads/portfolio/<?= $item['foto'] ?>', '<?= htmlspecialchars($item['deskripsi']) ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-warning btn-sm" 
                                                    onclick="editPortfolio(<?= $item['id'] ?>, '<?= htmlspecialchars($item['deskripsi']) ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="deletePortfolio(<?= $item['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($item['deskripsi']): ?>
                                    <div class="p-3">
                                        <p class="mb-0 small text-muted">
                                            <?= htmlspecialchars(substr($item['deskripsi'], 0, 80)) ?>
                                            <?= strlen($item['deskripsi']) > 80 ? '...' : '' ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Order Update Form -->
                    <form id="orderForm" method="POST" style="display: none;">
                        <input type="hidden" name="action" value="update_order">
                        <div id="orderInputs"></div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Foto Portfolio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload">
                        
                        <div class="mb-3">
                            <label class="form-label">Pilih Foto</label>
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-muted"></i>
                                <h6>Drag & Drop atau Klik untuk Upload</h6>
                                <p class="text-muted mb-0">Format: JPG, PNG. Maksimal 2MB</p>
                                <input type="file" name="foto_portfolio" id="fotoInput" 
                                       accept="image/*" required style="display: none;">
                            </div>
                        </div>
                        
                        <!-- Preview -->
                        <div id="imagePreview" style="display: none;" class="mb-3 text-center">
                            <img id="previewImg" class="img-fluid rounded" style="max-height: 200px;">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Deskripsi (Opsional)</label>
                            <textarea class="form-control" name="deskripsi" rows="3" 
                                      placeholder="Jelaskan tentang pekerjaan ini..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i>
                            Upload Foto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Deskripsi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="portfolio_id" id="editPortfolioId">
                        
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" id="editDeskripsi" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Preview Portfolio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPreviewImg" class="preview-img">
                    <div id="modalPreviewDesc" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        // Initialize sortable
        <?php if (!empty($portfolio_items)): ?>
        const portfolioGrid = document.getElementById('portfolioGrid');
        const sortable = Sortable.create(portfolioGrid, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                updateOrder();
            }
        });

        function updateOrder() {
            const items = portfolioGrid.querySelectorAll('.portfolio-item');
            const orderInputs = document.getElementById('orderInputs');
            orderInputs.innerHTML = '';
            
            items.forEach((item, index) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'portfolio_ids[]';
                input.value = item.dataset.id;
                orderInputs.appendChild(input);
            });
            
            // Submit form
            document.getElementById('orderForm').submit();
        }
        <?php endif; ?>

        // Upload area drag & drop
        const uploadArea = document.getElementById('uploadArea');
        const fotoInput = document.getElementById('fotoInput');

        uploadArea.addEventListener('click', () => fotoInput.click());

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fotoInput.files = files;
                previewUploadImage();
            }
        });

        fotoInput.addEventListener('change', previewUploadImage);

        function previewUploadImage() {
            const file = fotoInput.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        }

        // Preview image in modal
        function previewImage(src, desc) {
            document.getElementById('modalPreviewImg').src = src;
            document.getElementById('modalPreviewDesc').innerHTML = desc ? `<p class="text-muted">${desc}</p>` : '';
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }

        // Edit portfolio
        function editPortfolio(id, desc) {
            document.getElementById('editPortfolioId').value = id;
            document.getElementById('editDeskripsi').value = desc;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        // Delete portfolio
        function deletePortfolio(id) {
            if (confirm('Apakah Anda yakin ingin menghapus foto ini?')) {
                window.location.href = `portfolio.php?delete=${id}`;
            }
        }

        // Handle edit form submission
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            fetch('portfolio.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                location.reload();
            })
            .catch(error => {
                alert('Terjadi kesalahan saat menyimpan perubahan');
            });
        });

        // Auto close modals on success
        <?php if ($success): ?>
        setTimeout(() => {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            });
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>

<?php
// Handle edit portfolio (AJAX request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $portfolio_id = (int)$_POST['portfolio_id'];
    $deskripsi = sanitizeInput($_POST['deskripsi']);
    
    try {
        $stmt = $db->prepare("UPDATE portfolio SET deskripsi = ? WHERE id = ? AND pekerja_id = ?");
        $stmt->execute([$deskripsi, $portfolio_id, $pekerja_id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Gagal memperbarui deskripsi']);
    }
    exit;
}
?>

<?php
// includes/functions.php - Tambahan fungsi untuk portfolio
function getPortfolioStats($pekerja_id, $db) {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM portfolio WHERE pekerja_id = ?");
    $stmt->execute([$pekerja_id]);
    return $stmt->fetchColumn();
}

function optimizeImage($source, $destination, $quality = 80) {
    $info = getimagesize($source);
    
    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
    } elseif ($info['mime'] == 'image/gif') {
        $image = imagecreatefromgif($source);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
    } else {
        return false;
    }
    
    // Resize jika terlalu besar
    $width = imagesx($image);
    $height = imagesy($image);
    
    if ($width > 1200 || $height > 1200) {
        $ratio = min(1200 / $width, 1200 / $height);
        $new_width = $width * $ratio;
        $new_height = $height * $ratio;
        
        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        // Handle transparency for PNG
        if ($info['mime'] == 'image/png') {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
        }
        
        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        $image = $new_image;
    }
    
    // Save optimized image
    if ($info['mime'] == 'image/jpeg') {
        return imagejpeg($image, $destination, $quality);
    } elseif ($info['mime'] == 'image/png') {
        return imagepng($image, $destination, 9);
    } elseif ($info['mime'] == 'image/gif') {
        return imagegif($image, $destination);
    }
    
    imagedestroy($image);
    return false;
}
?>