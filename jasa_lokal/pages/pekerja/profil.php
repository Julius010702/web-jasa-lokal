<?php
// pages/pekerja/profil.php
require_once '../../config/config.php';

if (!isLoggedIn() || $_SESSION['user_type'] !== 'pekerja') {
    redirect('../../pages/auth/login.php');
}

$user = getCurrentUser();
$error = '';
$success = '';

// Ambil data profil pekerja
$stmt = $db->prepare("SELECT * FROM pekerja WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profil_pekerja = $stmt->fetch();

// Ambil kategori jasa
$kategori_list = getKategoriJasa();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = sanitizeInput($_POST['nama']);
    $email = sanitizeInput($_POST['email']);
    $no_telepon = sanitizeInput($_POST['no_telepon']);
    $whatsapp = sanitizeInput($_POST['whatsapp']);
    $alamat = sanitizeInput($_POST['alamat']);
    $kecamatan = sanitizeInput($_POST['kecamatan']);
    $kota = sanitizeInput($_POST['kota']);
    
    // Data pekerja
    $kategori_id = (int)$_POST['kategori_id'];
    $pengalaman_tahun = (int)$_POST['pengalaman_tahun'];
    $harga_mulai = $_POST['harga_mulai'] ? (float)$_POST['harga_mulai'] : null;
    $harga_hingga = $_POST['harga_hingga'] ? (float)$_POST['harga_hingga'] : null;
    $deskripsi_skill = sanitizeInput($_POST['deskripsi_skill']);
    $jam_kerja_mulai = $_POST['jam_kerja_mulai'];
    $jam_kerja_selesai = $_POST['jam_kerja_selesai'];
    $hari_kerja = isset($_POST['hari_kerja']) ? implode(',', $_POST['hari_kerja']) : '';
    $radius_kerja = (int)$_POST['radius_kerja'];
    
    // Handle foto upload dengan validasi lebih detail
    $foto_profil = $user['foto_profil'];
    $upload_error = '';
    
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Cek error upload
        if ($_FILES['foto_profil']['error'] !== UPLOAD_ERR_OK) {
            switch ($_FILES['foto_profil']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $upload_error = 'File terlalu besar. Maksimal 2MB.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $upload_error = 'Upload file tidak lengkap. Silakan coba lagi.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $upload_error = 'Folder temporary tidak ditemukan.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $upload_error = 'Gagal menulis file ke disk.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $upload_error = 'Upload dibatalkan oleh ekstensi PHP.';
                    break;
                default:
                    $upload_error = 'Terjadi kesalahan saat upload file.';
            }
        } else {
            // Validasi file
            $file_info = getimagesize($_FILES['foto_profil']['tmp_name']);
            if ($file_info === false) {
                $upload_error = 'File yang diupload bukan gambar yang valid.';
            } else {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($file_info['mime'], $allowed_types)) {
                    $upload_error = 'Format file tidak didukung. Gunakan JPG, PNG, atau GIF.';
                } elseif ($_FILES['foto_profil']['size'] > 2 * 1024 * 1024) { // 2MB
                    $upload_error = 'Ukuran file terlalu besar. Maksimal 2MB.';
                } else {
                    // Pastikan folder uploads/profil ada
                    $upload_dir = '../../uploads/profil/';
                    if (!is_dir($upload_dir)) {
                        if (!mkdir($upload_dir, 0755, true)) {
                            $upload_error = 'Gagal membuat folder upload.';
                        }
                    }
                    
                    if (empty($upload_error)) {
                        // Generate nama file unik
                        $file_extension = pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION);
                        $new_filename = 'profil_' . $user['id'] . '_' . time() . '.' . strtolower($file_extension);
                        $upload_path = $upload_dir . $new_filename;
                        
                        // Upload file
                        if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $upload_path)) {
                            // Hapus foto lama jika ada dan bukan default
                            if ($foto_profil && $foto_profil !== 'default-avatar.png' && file_exists($upload_dir . $foto_profil)) {
                                unlink($upload_dir . $foto_profil);
                            }
                            $foto_profil = $new_filename;
                        } else {
                            $upload_error = 'Gagal memindahkan file yang diupload.';
                        }
                    }
                }
            }
        }
        
        if ($upload_error) {
            $error = $upload_error;
        }
    }
    
    if (empty($nama) || empty($email) || empty($no_telepon) || empty($kategori_id)) {
        if (empty($error)) {
            $error = 'Field yang wajib diisi tidak boleh kosong!';
        }
    } elseif (empty($error)) { // Hanya simpan jika tidak ada error upload
        try {
            $db->beginTransaction();
            
            // Update data user
            $stmt = $db->prepare("
                UPDATE users 
                SET nama = ?, email = ?, no_telepon = ?, whatsapp = ?, 
                    alamat = ?, kecamatan = ?, kota = ?, foto_profil = ?
                WHERE id = ?
            ");
            $stmt->execute([$nama, $email, $no_telepon, $whatsapp, $alamat, $kecamatan, $kota, $foto_profil, $user['id']]);
            
            // Update atau insert data pekerja
            if ($profil_pekerja) {
                $stmt = $db->prepare("
                    UPDATE pekerja 
                    SET kategori_id = ?, pengalaman_tahun = ?, harga_mulai = ?, harga_hingga = ?,
                        deskripsi_skill = ?, jam_kerja_mulai = ?, jam_kerja_selesai = ?, 
                        hari_kerja = ?, radius_kerja = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $kategori_id, $pengalaman_tahun, $harga_mulai, $harga_hingga,
                    $deskripsi_skill, $jam_kerja_mulai, $jam_kerja_selesai,
                    $hari_kerja, $radius_kerja, $user['id']
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO pekerja 
                    (user_id, kategori_id, pengalaman_tahun, harga_mulai, harga_hingga,
                     deskripsi_skill, jam_kerja_mulai, jam_kerja_selesai, hari_kerja, radius_kerja)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user['id'], $kategori_id, $pengalaman_tahun, $harga_mulai, $harga_hingga,
                    $deskripsi_skill, $jam_kerja_mulai, $jam_kerja_selesai, $hari_kerja, $radius_kerja
                ]);
            }
            
            $db->commit();
            $success = 'Profil berhasil diperbarui!';
            
            // Refresh data
            $user = getCurrentUser();
            $stmt = $db->prepare("SELECT * FROM pekerja WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $profil_pekerja = $stmt->fetch();
            
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage();
        }
    }
}

$hari_options = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];
$hari_kerja_array = $profil_pekerja && $profil_pekerja['hari_kerja'] ? explode(',', $profil_pekerja['hari_kerja']) : [];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Dashboard Pekerja</title>
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
        
        .main-content {
            padding: 20px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .profile-form {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .foto-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e9ecef;
        }
        
        .form-section {
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .btn-upload {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .btn-upload input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .hari-checkbox {
            display: inline-block;
            margin: 5px;
        }
        
        .price-input {
            position: relative;
        }
        
        .price-input::before {
            content: 'Rp';
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-weight: 500;
        }
        
        .price-input input {
            padding-left: 35px;
        }
        
        .upload-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .file-selected {
            color: #198754;
            font-weight: 500;
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
                    <a class="nav-link active" href="profil.php">
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
                        <h2 class="fw-bold mb-1">Profil Saya</h2>
                        <p class="text-muted mb-0">Kelola informasi profil dan keahlian Anda</p>
                    </div>
                    <a href="../../detail-pekerja.php?id=<?= $user['id'] ?>" target="_blank" class="btn btn-outline-primary">
                        <i class="fas fa-external-link-alt me-2"></i>
                        Preview Profil
                    </a>
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
                
                <!-- Profile Form -->
                <div class="profile-form p-4">
                    <form method="POST" enctype="multipart/form-data" id="profileForm">
                        <!-- Foto Profil -->
                        <div class="form-section">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-camera text-primary me-2"></i>
                                Foto Profil
                            </h5>
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <img id="fotoPreview" 
                                         src="<?= $user['foto_profil'] ? '../../uploads/profil/' . $user['foto_profil'] : '../../assets/img/default-avatar.png' ?>" 
                                         alt="Foto Profil" class="foto-preview">
                                </div>
                                <div class="col">
                                    <label class="btn btn-outline-primary btn-upload">
                                        <i class="fas fa-upload me-2"></i>
                                        Pilih Foto
                                        <input type="file" name="foto_profil" accept="image/jpeg,image/png,image/gif" onchange="previewFoto(this)" id="fotoInput">
                                    </label>
                                    <div class="upload-info">
                                        <div class="small text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Format: JPG, PNG, GIF. Maksimal 2MB. Foto akan otomatis di-crop menjadi persegi.
                                        </div>
                                        <div id="fileInfo" class="small file-selected mt-1" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Data Pribadi -->
                        <div class="form-section">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-user text-primary me-2"></i>
                                Data Pribadi
                            </h5>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nama Lengkap *</label>
                                    <input type="text" class="form-control" name="nama" required 
                                           value="<?= htmlspecialchars($user['nama']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required 
                                           value="<?= htmlspecialchars($user['email']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">No. Telepon *</label>
                                    <input type="text" class="form-control" name="no_telepon" required 
                                           value="<?= htmlspecialchars($user['no_telepon']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">WhatsApp</label>
                                    <input type="text" class="form-control" name="whatsapp" 
                                           value="<?= htmlspecialchars($user['whatsapp']) ?>"
                                           placeholder="Kosongkan jika sama dengan no. telepon">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Alamat</label>
                                    <textarea class="form-control" name="alamat" rows="2"><?= htmlspecialchars($user['alamat']) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Kecamatan</label>
                                    <input type="text" class="form-control" name="kecamatan" 
                                           value="<?= htmlspecialchars($user['kecamatan']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Kota *</label>
                                    <input type="text" class="form-control" name="kota" required 
                                           value="<?= htmlspecialchars($user['kota']) ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informasi Pekerjaan -->
                        <div class="form-section">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-briefcase text-primary me-2"></i>
                                Informasi Pekerjaan
                            </h5>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Kategori Jasa *</label>
                                    <select class="form-select" name="kategori_id" required>
                                        <option value="">Pilih Kategori</option>
                                        <?php foreach ($kategori_list as $kat): ?>
                                            <option value="<?= $kat['id'] ?>" 
                                                    <?= ($profil_pekerja && $profil_pekerja['kategori_id'] == $kat['id']) ? 'selected' : '' ?>>
                                                <?= $kat['nama_kategori'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Pengalaman (Tahun) *</label>
                                    <input type="number" class="form-control" name="pengalaman_tahun" min="0" max="50" required 
                                           value="<?= $profil_pekerja ? $profil_pekerja['pengalaman_tahun'] : '' ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Deskripsi Keahlian *</label>
                                    <textarea class="form-control" name="deskripsi_skill" rows="4" required 
                                              placeholder="Jelaskan keahlian, pengalaman, dan layanan yang Anda tawarkan..."><?= $profil_pekerja ? htmlspecialchars($profil_pekerja['deskripsi_skill']) : '' ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Harga & Tarif -->
                        <div class="form-section">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-money-bill text-primary me-2"></i>
                                Harga & Tarif
                            </h5>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Harga Mulai Dari</label>
                                    <div class="price-input">
                                        <input type="number" class="form-control" name="harga_mulai" min="0" 
                                               value="<?= $profil_pekerja ? $profil_pekerja['harga_mulai'] : '' ?>"
                                               placeholder="50000">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Harga Hingga (Opsional)</label>
                                    <div class="price-input">
                                        <input type="number" class="form-control" name="harga_hingga" min="0" 
                                               value="<?= $profil_pekerja ? $profil_pekerja['harga_hingga'] : '' ?>"
                                               placeholder="500000">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Harga ini hanya sebagai estimasi awal. Harga final akan dinegosiasikan dengan klien.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Jam Kerja & Ketersediaan -->
                        <div class="form-section">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-clock text-primary me-2"></i>
                                Jam Kerja & Ketersediaan
                            </h5>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Jam Mulai</label>
                                    <input type="time" class="form-control" name="jam_kerja_mulai" 
                                           value="<?= $profil_pekerja ? $profil_pekerja['jam_kerja_mulai'] : '08:00' ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Jam Selesai</label>
                                    <input type="time" class="form-control" name="jam_kerja_selesai" 
                                           value="<?= $profil_pekerja ? $profil_pekerja['jam_kerja_selesai'] : '17:00' ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Hari Kerja</label>
                                    <div>
                                        <?php foreach ($hari_options as $hari): ?>
                                            <div class="form-check form-check-inline hari-checkbox">
                                                <input class="form-check-input" type="checkbox" name="hari_kerja[]" 
                                                       value="<?= $hari ?>" id="hari_<?= $hari ?>"
                                                       <?= in_array($hari, $hari_kerja_array) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="hari_<?= $hari ?>">
                                                    <?= ucfirst($hari) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Radius Kerja (KM)</label>
                                    <input type="number" class="form-control" name="radius_kerja" min="1" max="100" 
                                           value="<?= $profil_pekerja ? $profil_pekerja['radius_kerja'] : 5 ?>">
                                    <small class="text-muted">Jarak maksimal yang bersedia Anda tempuh</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="d-flex justify-content-between">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>
                                Kembali
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i class="fas fa-save me-2"></i>
                                Simpan Profil
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview foto sebelum upload
        function previewFoto(input) {
            const fileInfo = document.getElementById('fileInfo');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validasi ukuran file (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran file terlalu besar. Maksimal 2MB.');
                    input.value = '';
                    fileInfo.style.display = 'none';
                    return;
                }
                
                // Validasi tipe file
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format file tidak didukung. Gunakan JPG, PNG, atau GIF.');
                    input.value = '';
                    fileInfo.style.display = 'none';
                    return;
                }
                
                // Tampilkan info file
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                fileInfo.innerHTML = `<i class="fas fa-check-circle me-1"></i>File dipilih: ${file.name} (${fileSize}MB)`;
                fileInfo.style.display = 'block';
                
                // Preview gambar
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('fotoPreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            } else {
                fileInfo.style.display = 'none';
            }
        }

        // Auto-fill WhatsApp dengan nomor telepon jika kosong
        document.querySelector('input[name="no_telepon"]').addEventListener('blur', function() {
            const whatsappInput = document.querySelector('input[name="whatsapp"]');
            if (!whatsappInput.value.trim()) {
                whatsappInput.value = this.value;
            }
        });

        // Validasi harga
        document.querySelector('input[name="harga_hingga"]').addEventListener('input', function() {
            const hargaMulai = parseFloat(document.querySelector('input[name="harga_mulai"]').value) || 0;
            const hargaHingga = parseFloat(this.value) || 0;
            
            if (hargaHingga > 0 && hargaHingga < hargaMulai) {
                this.setCustomValidity('Harga hingga tidak boleh lebih kecil dari harga mulai');
            } else {
                this.setCustomValidity('');
            }
        });

        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const deskripsi = document.querySelector('textarea[name="deskripsi_skill"]').value.trim();
            
            // Validasi deskripsi
            if (deskripsi.length < 50) {
                e.preventDefault();
                alert('Deskripsi keahlian minimal 50 karakter untuk memberikan informasi yang cukup kepada calon klien.');
                return false;
            }
            
            // Disable submit button untuk mencegah double submit
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menyimpan...';
            
            // Re-enable button setelah 5 detik jika masih di halaman yang sama
            setTimeout(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Simpan Profil';
            }, 5000);
        });

        // Debug: Log form data sebelum submit
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const formData = new FormData(this);
            console.log('Form data being submitted:');
            for (let [key, value] of formData.entries()) {
                console.log(key, value);
            }
        });
    </script>
</body>
</html>