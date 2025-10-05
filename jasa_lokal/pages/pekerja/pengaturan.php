<?php
// pages/pekerja/pengaturan.php
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

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            $nama = trim($_POST['nama']);
            $email = trim($_POST['email']);
            $no_telepon = trim($_POST['no_telepon']);
            $kota = trim($_POST['kota']);
            
            // Validasi
            $errors = [];
            if (empty($nama)) $errors[] = "Nama tidak boleh kosong";
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email tidak valid";
            if (empty($no_telepon)) $errors[] = "Nomor telepon tidak boleh kosong";
            if (empty($kota)) $errors[] = "Kota tidak boleh kosong";
            
            // Cek email duplikat
            if (empty($errors)) {
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user['id']]);
                if ($stmt->fetchColumn()) {
                    $errors[] = "Email sudah digunakan";
                }
            }
            
            if (empty($errors)) {
                $stmt = $db->prepare("UPDATE users SET nama = ?, email = ?, no_telepon = ?, kota = ? WHERE id = ?");
                $stmt->execute([$nama, $email, $no_telepon, $kota, $user['id']]);
                
                $_SESSION['flash_message'] = "Profil berhasil diupdate";
                $_SESSION['flash_type'] = "success";
                redirect('pengaturan.php');
            } else {
                $error_message = implode('<br>', $errors);
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            $errors = [];
            
            // Validasi password lama
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = "Password lama tidak benar";
            }
            
            // Validasi password baru
            if (strlen($new_password) < 6) {
                $errors[] = "Password baru minimal 6 karakter";
            }
            
            if ($new_password !== $confirm_password) {
                $errors[] = "Konfirmasi password tidak cocok";
            }
            
            if (empty($errors)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user['id']]);
                
                $_SESSION['flash_message'] = "Password berhasil diubah";
                $_SESSION['flash_type'] = "success";
                redirect('pengaturan.php');
            } else {
                $password_error = implode('<br>', $errors);
            }
            break;
            
        case 'update_notification':
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
            $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
            
            try {
                // Update ke kolom di tabel users
                $stmt = $db->prepare("
                    UPDATE users 
                    SET email_notifications = ?, sms_notifications = ?, push_notifications = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$email_notifications, $sms_notifications, $push_notifications, $user['id']]);
                
                $_SESSION['flash_message'] = "Pengaturan notifikasi berhasil diupdate";
                $_SESSION['flash_type'] = "success";
            } catch (PDOException $e) {
                // Jika kolom belum ada, skip atau buat alternatif
                $_SESSION['flash_message'] = "Fitur notifikasi akan segera tersedia";
                $_SESSION['flash_type'] = "info";
            }
            redirect('pengaturan.php');
            break;
            
        case 'update_privacy':
            $show_phone = isset($_POST['show_phone']) ? 1 : 0;
            $show_email = isset($_POST['show_email']) ? 1 : 0;
            $allow_contact = isset($_POST['allow_contact']) ? 1 : 0;
            
            try {
                if ($profil_pekerja) {
                    $stmt = $db->prepare("
                        UPDATE pekerja 
                        SET show_phone = ?, show_email = ?, allow_contact = ? 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$show_phone, $show_email, $allow_contact, $user['id']]);
                } else {
                    // Jika belum ada profil pekerja, buat record minimal
                    $stmt = $db->prepare("
                        INSERT INTO pekerja (user_id, show_phone, show_email, allow_contact) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$user['id'], $show_phone, $show_email, $allow_contact]);
                }
                
                $_SESSION['flash_message'] = "Pengaturan privasi berhasil diupdate";
                $_SESSION['flash_type'] = "success";
            } catch (PDOException $e) {
                $_SESSION['flash_message'] = "Fitur pengaturan privasi akan segera tersedia";
                $_SESSION['flash_type'] = "info";
            }
            redirect('pengaturan.php');
            break;
            
        case 'deactivate_account':
            $confirm_deactivate = $_POST['confirm_deactivate'] ?? '';
            
            if ($confirm_deactivate === 'NONAKTIFKAN') {
                try {
                    // Set status tidak aktif
                    $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Logout
                    session_destroy();
                    redirect('../../pages/auth/login.php?message=account_deactivated');
                } catch (PDOException $e) {
                    // Jika kolom is_active belum ada, gunakan cara alternatif
                    $stmt = $db->prepare("UPDATE users SET password = CONCAT('DEACTIVATED_', password) WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    session_destroy();
                    redirect('../../pages/auth/login.php?message=account_deactivated');
                }
            } else {
                $deactivate_error = "Ketik 'NONAKTIFKAN' untuk konfirmasi";
            }
            break;
    }
}

// Ambil pengaturan notifikasi dari tabel users
$notif_settings = [
    'email_notifications' => $user['email_notifications'] ?? 1,
    'sms_notifications' => $user['sms_notifications'] ?? 0,
    'push_notifications' => $user['push_notifications'] ?? 1
];

// Refresh data user setelah update
$user = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
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
        
        .settings-card {
            border-radius: 15px;
            border: none;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .settings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1rem 1.5rem;
        }
        
        .settings-section {
            padding: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-custom {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 10px;
            background: #fff5f5;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #28a745;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .alert-custom {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
        }
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
                    <a class="nav-link" href="pesanan.php">
                        <i class="fas fa-clipboard-list me-2"></i>
                        Pesanan
                    </a>
                    <a class="nav-link" href="reviews.php">
                        <i class="fas fa-star me-2"></i>
                        Ulasan
                    </a>
                    <a class="nav-link active" href="pengaturan.php">
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
                <!-- Flash Message -->
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?= $_SESSION['flash_type'] ?> alert-custom alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= $_SESSION['flash_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php 
                    unset($_SESSION['flash_message']);
                    unset($_SESSION['flash_type']);
                    ?>
                <?php endif; ?>
                
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold mb-1">Pengaturan Akun</h2>
                        <p class="text-muted mb-0">Kelola profil, privasi, dan preferensi akun Anda</p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Pengaturan Profil -->
                        <div class="card settings-card">
                            <div class="settings-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user me-2"></i>
                                    Informasi Profil
                                </h5>
                            </div>
                            <div class="settings-section">
                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger alert-custom">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <?= $error_message ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="nama" class="form-label">Nama Lengkap</label>
                                            <input type="text" class="form-control" id="nama" name="nama" 
                                                   value="<?= htmlspecialchars($user['nama']) ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="no_telepon" class="form-label">Nomor Telepon</label>
                                            <input type="tel" class="form-control" id="no_telepon" name="no_telepon" 
                                                   value="<?= htmlspecialchars($user['no_telepon']) ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="kota" class="form-label">Kota</label>
                                            <input type="text" class="form-control" id="kota" name="kota" 
                                                   value="<?= htmlspecialchars($user['kota']) ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="submit" class="btn btn-primary btn-custom">
                                            <i class="fas fa-save me-2"></i>
                                            Simpan Perubahan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Ubah Password -->
                        <div class="card settings-card">
                            <div class="settings-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-lock me-2"></i>
                                    Keamanan & Password
                                </h5>
                            </div>
                            <div class="settings-section">
                                <?php if (isset($password_error)): ?>
                                    <div class="alert alert-danger alert-custom">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <?= $password_error ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="current_password" class="form-label">Password Lama</label>
                                            <input type="password" class="form-control" id="current_password" 
                                                   name="current_password" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="new_password" class="form-label">Password Baru</label>
                                            <input type="password" class="form-control" id="new_password" 
                                                   name="new_password" minlength="6" required>
                                            <small class="text-muted">Minimal 6 karakter</small>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                                            <input type="password" class="form-control" id="confirm_password" 
                                                   name="confirm_password" minlength="6" required>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="submit" class="btn btn-warning btn-custom">
                                            <i class="fas fa-key me-2"></i>
                                            Ubah Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Pengaturan Notifikasi -->
                        <div class="card settings-card">
                            <div class="settings-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-bell me-2"></i>
                                    Notifikasi
                                </h5>
                            </div>
                            <div class="settings-section">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_notification">
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h6 class="mb-1">Notifikasi Email</h6>
                                            <small class="text-muted">Terima notifikasi pesanan melalui email</small>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="email_notifications" 
                                                   <?= $notif_settings['email_notifications'] ? 'checked' : '' ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h6 class="mb-1">Notifikasi SMS</h6>
                                            <small class="text-muted">Terima notifikasi pesanan melalui SMS</small>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="sms_notifications" 
                                                   <?= $notif_settings['sms_notifications'] ? 'checked' : '' ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div>
                                            <h6 class="mb-1">Notifikasi Push</h6>
                                            <small class="text-muted">Terima notifikasi langsung di browser</small>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="push_notifications" 
                                                   <?= $notif_settings['push_notifications'] ? 'checked' : '' ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-info btn-custom">
                                            <i class="fas fa-save me-2"></i>
                                            Simpan Pengaturan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Pengaturan Privasi -->
                        <div class="card settings-card">
                            <div class="settings-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    Privasi
                                </h5>
                            </div>
                            <div class="settings-section">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_privacy">
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h6 class="mb-1">Tampilkan Nomor Telepon</h6>
                                            <small class="text-muted">Nomor telepon akan terlihat di profil publik</small>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="show_phone" 
                                                   <?= ($profil_pekerja['show_phone'] ?? 1) ? 'checked' : '' ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h6 class="mb-1">Tampilkan Email</h6>
                                            <small class="text-muted">Email akan terlihat di profil publik</small>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="show_email" 
                                                   <?= ($profil_pekerja['show_email'] ?? 0) ? 'checked' : '' ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div>
                                            <h6 class="mb-1">Izinkan Kontak Langsung</h6>
                                            <small class="text-muted">Pelanggan dapat menghubungi tanpa melalui sistem</small>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="allow_contact" 
                                                   <?= ($profil_pekerja['allow_contact'] ?? 1) ? 'checked' : '' ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-success btn-custom">
                                            <i class="fas fa-save me-2"></i>
                                            Simpan Pengaturan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Danger Zone -->
                        <div class="card settings-card">
                            <div class="settings-header bg-danger">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Zona Bahaya
                                </h5>
                            </div>
                            <div class="settings-section danger-zone">
                                <h6 class="text-danger mb-3">Nonaktifkan Akun</h6>
                                <p class="text-muted mb-3">
                                    Nonaktifkan akun akan menyembunyikan profil Anda dari pencarian dan 
                                    mencegah pelanggan baru menghubungi Anda. Anda masih bisa login dan 
                                    mengaktifkan kembali kapan saja.
                                </p>
                                
                                <?php if (isset($deactivate_error)): ?>
                                    <div class="alert alert-danger alert-custom">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <?= $deactivate_error ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" onsubmit="return confirm('Yakin ingin menonaktifkan akun?')">
                                    <input type="hidden" name="action" value="deactivate_account">
                                    
                                    <div class="mb-3">
                                        <label for="confirm_deactivate" class="form-label">
                                            Ketik <strong>"NONAKTIFKAN"</strong> untuk konfirmasi:
                                        </label>
                                        <input type="text" class="form-control" id="confirm_deactivate" 
                                               name="confirm_deactivate" placeholder="NONAKTIFKAN" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-danger btn-custom">
                                        <i class="fas fa-user-times me-2"></i>
                                        Nonaktifkan Akun
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar Info -->
                    <div class="col-lg-4">
                        <div class="card settings-card">
                            <div class="settings-section">
                                <h6 class="mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Tips Keamanan
                                </h6>
                                
                                <div class="mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <span class="small">Gunakan password yang kuat</span>
                                    </div>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <span class="small">Jangan bagikan informasi login</span>
                                    </div>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <span class="small">Logout dari perangkat publik</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <span class="small">Periksa aktivitas akun secara berkala</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card settings-card">
                            <div class="settings-section">
                                <h6 class="mb-3">
                                    <i class="fas fa-headset me-2"></i>
                                    Bantuan & Dukungan
                                </h6>
                                
                                <div class="d-grid gap-2">
                                    <a href="../../pages/bantuan.php" class="btn btn-outline-primary btn-custom">
                                        <i class="fas fa-question-circle me-2"></i>
                                        Pusat Bantuan
                                    </a>
                                    
                                    <a href="mailto:support@jasalokal.com" class="btn btn-outline-success btn-custom">
                                        <i class="fas fa-envelope me-2"></i>
                                        Kontak Support
                                    </a>
                                    
                                    <a href="../../pages/kebijakan.php" class="btn btn-outline-info btn-custom">
                                        <i class="fas fa-file-alt me-2"></i>
                                        Kebijakan Privasi
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card settings-card">
                            <div class="settings-section">
                                <h6 class="mb-3">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Status Akun
                                </h6>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted">Status Akun:</span>
                                        <span class="badge bg-success">Aktif</span>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted">Tipe Akun:</span>
                                        <span class="badge bg-primary">Pekerja</span>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted">Tanggal Bergabung:</span>
                                        <span class="small"><?= date('d M Y', strtotime($user['created_at'])) ?></span>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted">Terakhir Login:</span>
                                        <span class="small text-success">Sekarang</span>
                                    </div>
                                </div>
                                
                                <?php if ($profil_pekerja): ?>
                                    <hr>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small text-muted">Kategori Jasa:</span>
                                            <span class="small"><?= $profil_pekerja['nama_kategori'] ?></span>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small text-muted">Pengalaman:</span>
                                            <span class="small"><?= $profil_pekerja['pengalaman_tahun'] ?> tahun</span>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small text-muted">Status Profil:</span>
                                            <?php if ($profil_pekerja && $user['foto_profil'] && $profil_pekerja['deskripsi_skill']): ?>
                                                <span class="badge bg-success">Lengkap</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Belum Lengkap</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Password tidak cocok');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Auto-save notification settings
        const notificationToggles = document.querySelectorAll('input[name$="_notifications"]');
        notificationToggles.forEach(toggle => {
            toggle.addEventListener('change', function() {
                // Optional: Auto-save dengan AJAX
                console.log(`${this.name}: ${this.checked}`);
            });
        });
        
        // Confirm deactivation
        document.getElementById('confirm_deactivate').addEventListener('input', function() {
            const submitBtn = this.closest('form').querySelector('button[type="submit"]');
            if (this.value === 'NONAKTIFKAN') {
                submitBtn.classList.remove('btn-danger');
                submitBtn.classList.add('btn-outline-danger');
            } else {
                submitBtn.classList.remove('btn-outline-danger');
                submitBtn.classList.add('btn-danger');
            }
        });
        
        // Request notification permission
        function requestNotificationPermission() {
            if ('Notification' in window) {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        console.log('Notification permission granted');
                    }
                });
            }
        }
        
        // Check if push notifications are enabled
        document.querySelector('input[name="push_notifications"]').addEventListener('change', function() {
            if (this.checked) {
                requestNotificationPermission();
            }
        });
        
        // Show success toast for better UX
        function showToast(message, type = 'success') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-check-circle me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            // Add to page and show
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Remove after hidden
            toast.addEventListener('hidden.bs.toast', () => {
                document.body.removeChild(toast);
            });
        }
        
        // Form validation feedback
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!this.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    showToast('Mohon periksa kembali data yang diisi', 'danger');
                }
                this.classList.add('was-validated');
            });
        });
        
        // Auto-hide alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            }, 5000);
        });
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Toggle password visibility
        function addPasswordToggle() {
            document.querySelectorAll('input[type="password"]').forEach(input => {
                const wrapper = document.createElement('div');
                wrapper.className = 'position-relative';
                
                input.parentNode.insertBefore(wrapper, input);
                wrapper.appendChild(input);
                
                const toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = 'btn btn-outline-secondary position-absolute end-0 top-0 h-100';
                toggleBtn.style.borderLeft = 'none';
                toggleBtn.style.borderTopLeftRadius = '0';
                toggleBtn.style.borderBottomLeftRadius = '0';
                toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
                
                toggleBtn.addEventListener('click', function() {
                    if (input.type === 'password') {
                        input.type = 'text';
                        this.innerHTML = '<i class="fas fa-eye-slash"></i>';
                    } else {
                        input.type = 'password';
                        this.innerHTML = '<i class="fas fa-eye"></i>';
                    }
                });
                
                wrapper.appendChild(toggleBtn);
            });
        }
        
        // Initialize password toggle on page load
        document.addEventListener('DOMContentLoaded', addPasswordToggle);
    </script>
</body>
</html>