
<?php
// pages/auth/login.php
require_once '../../config/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi!';
    } else {
        $stmt = $db->prepare("SELECT id, nama, email, password, user_type, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'nonaktif') {
                $error = 'Akun Anda telah dinonaktifkan. Hubungi admin.';
            } else {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nama'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Remember me (optional)
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    // Simpan token ke database dan set cookie (implementasi lanjutan)
                }
                
                // Log activity
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, activity, ip_address, user_agent) VALUES (?, 'login', ?, ?)");
                $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                
                // Redirect berdasarkan user type
                if ($user['user_type'] === 'pekerja') {
                    redirect('../../pages/pekerja/dashboard.php');
                } else {
                    redirect('../../index.php');
                }
            }
        } else {
            $error = 'Email atau password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
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
        .social-login {
            border: 1px solid #e9ecef;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .social-login:hover {
            background-color: #f8f9fa;
            border-color: #007bff;
        }
    </style>
</head>
<body>
    <div class="auth-container d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="card auth-card">
                        <div class="card-header text-center py-4">
                            <h3 class="mb-0">
                                <i class="fas fa-sign-in-alt text-primary me-2"></i>
                                Login
                            </h3>
                            <p class="text-muted mt-2">Masuk ke akun Anda</p>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?= $error ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" name="email" required 
                                               value="<?= isset($_POST['email']) ? $_POST['email'] : '' ?>" 
                                               placeholder="contoh@email.com">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" name="password" required 
                                               placeholder="Masukkan password">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-6">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                            <label class="form-check-label" for="remember">Ingat Saya</label>
                                        </div>
                                    </div>
                                    <div class="col-6 text-end">
                                        <a href="forgot-password.php" class="text-decoration-none small">Lupa Password?</a>
                                    </div>
                                </div>

                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i>
                                        Login
                                    </button>
                                </div>
                            </form>

                            <hr>

                            <div class="text-center">
                                <p class="mb-0">Belum punya akun? 
                                    <a href="register.php" class="text-decoration-none">Daftar di sini</a>
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
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.querySelector('input[name="password"]');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>