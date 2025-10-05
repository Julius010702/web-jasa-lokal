<?php
// pages/pencari/profil.php  
require_once '../../config/config.php';

if (!isLoggedIn() || $_SESSION['user_type'] !== 'pencari') {
    redirect('../../pages/auth/login.php');
}

$user = getCurrentUser();
$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = sanitizeInput($_POST['nama']);
    $email = sanitizeInput($_POST['email']);
    $no_telepon = sanitizeInput($_POST['no_telepon']);
    $whatsapp = sanitizeInput($_POST['whatsapp']);
    $alamat = sanitizeInput($_POST['alamat']);
    $kecamatan = sanitizeInput($_POST['kecamatan']);
    $kota = sanitizeInput($_POST['kota']);
    
    // Handle foto profil upload
    $foto_profil = $user['foto_profil'];
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        // Validasi file
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        $file_type = $_FILES['foto_profil']['type'];
        $file_size = $_FILES['foto_profil']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $error = 'Format file tidak didukung. Gunakan JPG, PNG, atau GIF!';
        } elseif ($file_size > $max_size) {
            $error = 'Ukuran file terlalu besar. Maksimal 2MB!';
        } else {
            // Buat direktori jika belum ada
            $upload_dir = '../../uploads/profil/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate nama file unik
            $file_extension = pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION);
            $new_filename = 'profil_' . $user['id'] . '_' . time() . '.' . strtolower($file_extension);
            $upload_path = $upload_dir . $new_filename;
            
            // Upload file
            if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $upload_path)) {
                // Delete old file jika ada
                if ($foto_profil && file_exists('../../uploads/profil/' . $foto_profil)) {
                    unlink('../../uploads/profil/' . $foto_profil);
                }
                $foto_profil = $new_filename;
            } else {
                $error = 'Gagal mengupload foto profil!';
            }
        }
    }
    
    // Cek email duplicate (kecuali email sendiri) - hanya jika tidak ada error upload
    if (!$error) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user['id']]);
        
        if (empty($nama) || empty($email)) {
            $error = 'Nama dan email harus diisi!';
        } elseif ($stmt->fetch()) {
            $error = 'Email sudah digunakan oleh user lain!';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE users SET 
                    nama = ?, email = ?, no_telepon = ?, whatsapp = ?, 
                    alamat = ?, kecamatan = ?, kota = ?, foto_profil = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nama, $email, $no_telepon, $whatsapp, $alamat, $kecamatan, $kota, $foto_profil, $user['id']]);
                
                $success = 'Profil berhasil diperbarui!';
                
                // Refresh user data
                $user = getCurrentUser();
                
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - <?= APP_NAME ?></title>
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
        .profile-image-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e9ecef;
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
                        <a class="nav-link active" href="profil.php">
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="fw-bold mb-0">Profil Saya</h2>
                            <p class="text-muted">Kelola informasi pribadi Anda</p>
                        </div>
                    </div>

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

                    <!-- Form Profil -->
                    <div class="card">
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    <!-- Left Column -->
                                    <div class="col-lg-8">
                                        <!-- Informasi Dasar -->
                                        <div class="form-section">
                                            <h5 class="fw-bold mb-3">
                                                <i class="fas fa-user text-primary me-2"></i>
                                                Informasi Dasar
                                            </h5>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Nama Lengkap</label>
                                                    <input type="text" class="form-control" name="nama" required 
                                                           value="<?= htmlspecialchars($user['nama']) ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" class="form-control" name="email" required 
                                                           value="<?= htmlspecialchars($user['email']) ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">No. Telepon</label>
                                                    <input type="text" class="form-control" name="no_telepon" required 
                                                           value="<?= htmlspecialchars($user['no_telepon']) ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">WhatsApp</label>
                                                    <input type="text" class="form-control" name="whatsapp" 
                                                           value="<?= htmlspecialchars($user['whatsapp']) ?>"
                                                           placeholder="08123456789">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Lokasi -->
                                        <div class="form-section">
                                            <h5 class="fw-bold mb-3">
                                                <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                                Lokasi
                                            </h5>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Alamat</label>
                                                <textarea class="form-control" name="alamat" rows="2" 
                                                          placeholder="Alamat lengkap"><?= htmlspecialchars($user['alamat']) ?></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Kecamatan</label>
                                                    <input type="text" class="form-control" name="kecamatan" 
                                                           value="<?= htmlspecialchars($user['kecamatan']) ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Kota</label>
                                                    <input type="text" class="form-control" name="kota" 
                                                           value="<?= htmlspecialchars($user['kota']) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Right Column -->
                                    <div class="col-lg-4">
                                        <!-- Foto Profil -->
                                        <div class="form-section">
                                            <h5 class="fw-bold mb-3">
                                                <i class="fas fa-camera text-primary me-2"></i>
                                                Foto Profil
                                            </h5>
                                            
                                            <div class="text-center mb-3">
                                                <img src="<?= $user['foto_profil'] ? '../../uploads/profil/' . $user['foto_profil'] : '../../assets/img/default-avatar.png' ?>" 
                                                     alt="Profile Preview" id="profilePreview" class="profile-image-preview">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <input type="file" class="form-control" name="foto_profil" 
                                                       accept="image/jpeg,image/jpg,image/png,image/gif" id="profileInput">
                                                <div class="form-text">Max 2MB. Format: JPG, PNG, GIF</div>
                                            </div>
                                        </div>

                                        <!-- Tips -->
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="fw-bold text-primary">
                                                    <i class="fas fa-lightbulb me-2"></i>
                                                    Tips Profil Menarik
                                                </h6>
                                                <ul class="small mb-0">
                                                    <li>Gunakan foto profil yang jelas dan profesional</li>
                                                    <li>Lengkapi informasi kontak untuk memudahkan pekerja menghubungi Anda</li>
                                                    <li>Pastikan alamat akurat untuk memudahkan pencarian pekerja</li>
                                                    <li>Gunakan nomor WhatsApp aktif</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-3 mt-4">
                                    <a href="dashboard.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>
                                        Batal
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        Simpan Profil
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview foto profil dengan validasi
        document.getElementById('profileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validasi ukuran file (2MB = 2097152 bytes)
                if (file.size > 2097152) {
                    alert('Ukuran file terlalu besar! Maksimal 2MB.');
                    this.value = '';
                    return;
                }
                
                // Validasi tipe file
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format file tidak didukung! Gunakan JPG, PNG, atau GIF.');
                    this.value = '';
                    return;
                }
                
                // Preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });

        // Auto-format phone numbers
        document.querySelectorAll('input[name="no_telepon"], input[name="whatsapp"]').forEach(input => {
            input.addEventListener('input', function() {
                // Remove non-numeric characters
                let value = this.value.replace(/\D/g, '');
                
                // Add leading zero if starts with 8
                if (value.startsWith('8')) {
                    value = '0' + value;
                }
                
                this.value = value;
            });
        });
        
        // Debug: Log form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('profileInput');
            if (fileInput.files.length > 0) {
                console.log('File yang akan diupload:', fileInput.files[0]);
                console.log('Nama file:', fileInput.files[0].name);
                console.log('Ukuran file:', fileInput.files[0].size);
                console.log('Tipe file:', fileInput.files[0].type);
            }
        });
    </script>
</body>
</html>