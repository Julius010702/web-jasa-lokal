<?php
// pages/auth/reset-password.php
require_once '../../config/config.php';

$error = '';
$success = '';
$valid_token = false;
$user_id = null;
$reset_data = null;

// Validasi token dari URL
if (isset($_GET['token'])) {
    $token = sanitizeInput($_GET['token']);
    
    // Cek token di database
    $stmt = $db->prepare("SELECT pr.*, u.nama, u.email 
                          FROM password_resets pr 
                          JOIN users u ON pr.user_id = u.id 
                          WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()");
    $stmt->execute([$token]);
    $reset_data = $stmt->fetch();
    
    if ($reset_data) {
        $valid_token = true;
        $user_id = $reset_data['user_id'];
    } else {
        $error = 'Token tidak valid atau sudah kadaluarsa. Silakan request reset password kembali.';
    }
} else {
    $error = 'Token tidak ditemukan.';
}

// Proses reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Semua field harus diisi!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak sama!';
    } else {
        // Hash password baru
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password user
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        
        // Tandai token sebagai sudah digunakan
        $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);
        
        // Log activity
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, activity, ip_address, user_agent) VALUES (?, 'password_reset', ?, ?)");
        $stmt->execute([$user_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?= APP_NAME ?></title>
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
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s;
            background-color: #e9ecef;
        }
        .strength-bar {
            height: 100%;
            border-radius: 3px;
            transition: all 0.3s;
        }
        .strength-weak { 
            background-color: #dc3545; 
            width: 33%; 
        }
        .strength-medium { 
            background-color: #ffc107; 
            width: 66%; 
        }
        .strength-strong { 
            background-color: #28a745; 
            width: 100%; 
        }
        .requirements-list {
            font-size: 0.875rem;
            margin-top: 10px;
        }
        .requirements-list li {
            color: #6c757d;
            margin-bottom: 5px;
        }
        .requirements-list li.met {
            color: #28a745;
        }
        .requirements-list li i {
            width: 20px;
        }
    </style>
</head>
<body>
    <div class="auth-container d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card auth-card">
                        <div class="card-header text-center py-4">
                            <h3 class="mb-0">
                                <i class="fas fa-lock-open text-primary me-2"></i>
                                Reset Password
                            </h3>
                            <p class="text-muted mt-2">Buat password baru Anda</p>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($success): ?>
                                <div class="alert alert-success text-center">
                                    <i class="fas fa-check-circle fa-3x mb-3 d-block text-success"></i>
                                    <h5 class="mb-3">Password Berhasil Direset!</h5>
                                    <p class="mb-4">Password Anda telah berhasil diubah. Silakan login dengan password baru Anda.</p>
                                    <a href="login.php" class="btn btn-primary btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i>
                                        Login Sekarang
                                    </a>
                                </div>
                            <?php elseif (!$valid_token): ?>
                                <div class="alert alert-danger text-center">
                                    <i class="fas fa-times-circle fa-3x mb-3 d-block text-danger"></i>
                                    <h5 class="mb-3">Token Tidak Valid</h5>
                                    <p class="mb-4"><?= $error ?></p>
                                    <a href="forgot-password.php" class="btn btn-primary btn-lg">
                                        <i class="fas fa-redo me-2"></i>
                                        Request Reset Lagi
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php if ($error): ?>
                                    <div class="alert alert-danger alert-dismissible fade show">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <?= $error ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <div class="alert alert-info mb-4">
                                    <i class="fas fa-user me-2"></i>
                                    Reset password untuk: <strong><?= htmlspecialchars($reset_data['email']) ?></strong>
                                </div>

                                <form method="POST" id="resetForm">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Password Baru <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" name="password" id="password" required 
                                                   placeholder="Minimal 6 karakter" autocomplete="new-password">
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword" tabindex="-1">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="password-strength mt-2">
                                            <div class="strength-bar" id="strengthBar"></div>
                                        </div>
                                        <small class="text-muted d-block mt-1" id="strengthText"></small>
                                        
                                        <ul class="requirements-list list-unstyled mt-3 mb-0">
                                            <li id="req-length">
                                                <i class="fas fa-circle"></i>
                                                <span>Minimal 6 karakter</span>
                                            </li>
                                            <li id="req-letter">
                                                <i class="fas fa-circle"></i>
                                                <span>Mengandung huruf</span>
                                            </li>
                                            <li id="req-number">
                                                <i class="fas fa-circle"></i>
                                                <span>Mengandung angka (disarankan)</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Konfirmasi Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required 
                                                   placeholder="Ulangi password baru" autocomplete="new-password">
                                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirm" tabindex="-1">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-success d-none" id="matchSuccess">
                                                <i class="fas fa-check-circle me-1"></i>Password cocok!
                                            </small>
                                            <small class="text-danger d-none" id="matchError">
                                                <i class="fas fa-times-circle me-1"></i>Password tidak sama!
                                            </small>
                                        </div>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                            <i class="fas fa-check me-2"></i>
                                            Reset Password
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>

                            <hr class="my-4">

                            <div class="text-center">
                                <p class="mb-0">
                                    <a href="login.php" class="text-decoration-none">
                                        <i class="fas fa-arrow-left me-1"></i>
                                        Kembali ke Login
                                    </a>
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
        function togglePasswordVisibility(buttonId, inputId) {
            const button = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            
            if (!button || !input) return;
            
            button.addEventListener('click', function() {
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }

        togglePasswordVisibility('togglePassword', 'password');
        togglePasswordVisibility('toggleConfirm', 'confirmPassword');

        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const reqLength = document.getElementById('req-length');
        const reqLetter = document.getElementById('req-letter');
        const reqNumber = document.getElementById('req-number');

        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Check requirements
                const hasLength = password.length >= 6;
                const hasLetter = /[a-zA-Z]/.test(password);
                const hasNumber = /\d/.test(password);
                const hasSpecial = /[^a-zA-Z\d]/.test(password);
                
                // Update requirement indicators
                updateRequirement(reqLength, hasLength);
                updateRequirement(reqLetter, hasLetter);
                updateRequirement(reqNumber, hasNumber);
                
                // Calculate strength
                if (hasLength) strength++;
                if (hasLetter) strength++;
                if (hasNumber) strength++;
                if (hasSpecial) strength++;
                if (password.length >= 10) strength++;
                
                // Update strength bar
                strengthBar.className = 'strength-bar';
                
                if (password.length === 0) {
                    strengthBar.style.width = '0%';
                    strengthText.textContent = '';
                } else if (strength <= 2) {
                    strengthBar.classList.add('strength-weak');
                    strengthText.textContent = 'Password lemah';
                    strengthText.className = 'text-danger d-block mt-1';
                } else if (strength <= 3) {
                    strengthBar.classList.add('strength-medium');
                    strengthText.textContent = 'Password sedang';
                    strengthText.className = 'text-warning d-block mt-1';
                } else {
                    strengthBar.classList.add('strength-strong');
                    strengthText.textContent = 'Password kuat';
                    strengthText.className = 'text-success d-block mt-1';
                }
                
                // Check confirm password match
                checkPasswordMatch();
            });
        }

        function updateRequirement(element, isMet) {
            if (!element) return;
            
            const icon = element.querySelector('i');
            if (isMet) {
                element.classList.add('met');
                icon.classList.remove('fa-circle');
                icon.classList.add('fa-check-circle');
            } else {
                element.classList.remove('met');
                icon.classList.remove('fa-check-circle');
                icon.classList.add('fa-circle');
            }
        }

        // Password match validation
        const confirmInput = document.getElementById('confirmPassword');
        const matchSuccess = document.getElementById('matchSuccess');
        const matchError = document.getElementById('matchError');
        const submitBtn = document.getElementById('submitBtn');

        function checkPasswordMatch() {
            if (!confirmInput || !passwordInput) return;
            
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            
            if (confirm.length === 0) {
                matchSuccess.classList.add('d-none');
                matchError.classList.add('d-none');
                return;
            }
            
            if (password === confirm) {
                matchSuccess.classList.remove('d-none');
                matchError.classList.add('d-none');
            } else {
                matchSuccess.classList.add('d-none');
                matchError.classList.remove('d-none');
            }
        }

        if (confirmInput) {
            confirmInput.addEventListener('input', checkPasswordMatch);
        }

        // Form validation
        const form = document.getElementById('resetForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirm = confirmInput.value;
                
                if (password !== confirm) {
                    e.preventDefault();
                    matchError.classList.remove('d-none');
                    matchSuccess.classList.add('d-none');
                    confirmInput.focus();
                    return false;
                }
                
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password minimal 6 karakter!');
                    passwordInput.focus();
                    return false;
                }
                
                // Disable submit button to prevent double submission
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
            });
        }
    </script>
</body>
</html>