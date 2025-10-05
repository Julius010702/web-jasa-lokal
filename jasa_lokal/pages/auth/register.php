<?php
// pages/auth/register.php
require_once '../../config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = sanitizeInput($_POST['nama']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $no_telepon = sanitizeInput($_POST['no_telepon']);
    $whatsapp = sanitizeInput($_POST['whatsapp']);
    $alamat = sanitizeInput($_POST['alamat']);
    $kecamatan = sanitizeInput($_POST['kecamatan']);
    $kota = sanitizeInput($_POST['kota']);
    $user_type = sanitizeInput($_POST['user_type']);
    
    // Validasi
    if (empty($nama) || empty($email) || empty($password) || empty($no_telepon) || empty($user_type)) {
        $error = 'Semua field wajib diisi!';
    } elseif ($password !== $confirm_password) {
        $error = 'Password tidak sama!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        // Cek apakah email sudah terdaftar
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar!';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $db->prepare("INSERT INTO users (nama, email, password, no_telepon, whatsapp, alamat, kecamatan, kota, user_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nama, $email, $hashed_password, $no_telepon, $whatsapp, $alamat, $kecamatan, $kota, $user_type]);
                
                $success = 'Registrasi berhasil! Silakan login.';
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan saat registrasi!';
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
    <title>Daftar - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .auth-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .auth-card {
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border-radius: 15px;
        }
        .user-type-card {
            border: 2px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s;
        }
        .user-type-card:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }
        .user-type-card.active {
            border-color: #007bff;
            background-color: #f8f9ff;
        }
    </style>
</head>
<body>
    <div class="auth-container d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card auth-card">
                        <div class="card-header text-center py-4">
                            <h3 class="mb-0">
                                <i class="fas fa-user-plus text-primary me-2"></i>
                                Daftar Akun
                            </h3>
                            <p class="text-muted mt-2">Bergabunglah dengan komunitas jasa lokal</p>
                        </div>
                        <div class="card-body p-4">
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

                            <form method="POST" id="registerForm">
                                <!-- Pilih Tipe User -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Daftar Sebagai</label>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="user-type-card p-3 text-center rounded" data-type="pekerja">
                                                <i class="fas fa-tools fa-2x text-primary mb-2"></i>
                                                <h6 class="mb-1">Pekerja</h6>
                                                <small class="text-muted">Saya menyediakan jasa</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="user-type-card p-3 text-center rounded" data-type="pencari">
                                                <i class="fas fa-search fa-2x text-success mb-2"></i>
                                                <h6 class="mb-1">Pencari Jasa</h6>
                                                <small class="text-muted">Saya butuh jasa</small>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="user_type" id="user_type" value="">
                                </div>

                                <!-- Data Pribadi -->
                                <div class="mb-3">
                                    <label class="form-label">Nama Lengkap</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" name="nama" required 
                                               value="<?= isset($_POST['nama']) ? $_POST['nama'] : '' ?>" 
                                               placeholder="Masukkan nama lengkap">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" name="email" required 
                                               value="<?= isset($_POST['email']) ? $_POST['email'] : '' ?>" 
                                               placeholder="contoh@email.com">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" name="password" required 
                                                   placeholder="Minimal 6 karakter">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Konfirmasi Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" name="confirm_password" required 
                                                   placeholder="Ulangi password">
                                        </div>
                                    </div>
                                </div>

                                <!-- Kontak -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">No. Telepon</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                            <input type="text" class="form-control" name="no_telepon" required 
                                                   value="<?= isset($_POST['no_telepon']) ? $_POST['no_telepon'] : '' ?>" 
                                                   placeholder="08123456789">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">WhatsApp</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-whatsapp"></i></span>
                                            <input type="text" class="form-control" name="whatsapp" 
                                                   value="<?= isset($_POST['whatsapp']) ? $_POST['whatsapp'] : '' ?>" 
                                                   placeholder="08123456789">
                                        </div>
                                    </div>
                                </div>

                                <!-- Alamat -->
                                <div class="mb-3">
                                    <label class="form-label">Alamat</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                        <textarea class="form-control" name="alamat" rows="2" 
                                                  placeholder="Alamat lengkap"><?= isset($_POST['alamat']) ? $_POST['alamat'] : '' ?></textarea>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Kecamatan</label>
                                        <input type="text" class="form-control" name="kecamatan" 
                                               value="<?= isset($_POST['kecamatan']) ? $_POST['kecamatan'] : '' ?>" 
                                               placeholder="Kecamatan">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Kota</label>
                                        <input type="text" class="form-control" name="kota" 
                                               value="<?= isset($_POST['kota']) ? $_POST['kota'] : '' ?>" 
                                               placeholder="Kota">
                                    </div>
                                </div>

                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-user-plus me-2"></i>
                                        Daftar Sekarang
                                    </button>
                                </div>
                            </form>

                            <div class="text-center">
                                <p class="mb-0">Sudah punya akun? 
                                    <a href="login.php" class="text-decoration-none">Login di sini</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle user type selection
        document.querySelectorAll('.user-type-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remove active class from all cards
                document.querySelectorAll('.user-type-card').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked card
                this.classList.add('active');
                
                // Set hidden input value
                document.getElementById('user_type').value = this.dataset.type;
            });
        });

        // Validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const userType = document.getElementById('user_type').value;
            if (!userType) {
                e.preventDefault();
                alert('Silakan pilih tipe user terlebih dahulu!');
                return false;
            }
        });
    </script>
</body>
</html>