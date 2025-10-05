<?php
// pages/auth/forgot-password.php
require_once '../../config/config.php';

$success = '';
$error = '';
$email_sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    
    if (empty($email)) {
        $error = 'Email harus diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
    } else {
        // Cek apakah email terdaftar
        $stmt = $db->prepare("SELECT id, nama, email FROM users WHERE email = ? AND status = 'aktif'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Hapus token lama yang belum digunakan untuk user ini
            $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ? AND used = 0");
            $stmt->execute([$user['id']]);
            
            // Generate token reset password
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Simpan token ke database
            $stmt = $db->prepare("INSERT INTO password_resets (user_id, email, token, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['id'], $email, $token, $expires]);
            
            // Generate reset link
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . "/auth/reset-password.php?token=" . $token;
            
            // ============================================
            // KIRIM EMAIL (Sesuaikan dengan sistem email Anda)
            // ============================================
            
            // Opsi 1: Menggunakan mail() PHP (perlu konfigurasi SMTP server)
            /*
            $to = $email;
            $subject = "Reset Password - " . APP_NAME;
            $message = "
            <html>
            <body>
                <h2>Reset Password</h2>
                <p>Halo {$user['nama']},</p>
                <p>Anda menerima email ini karena ada permintaan reset password untuk akun Anda.</p>
                <p>Klik link di bawah ini untuk mereset password:</p>
                <p><a href='{$reset_link}' style='background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>Reset Password</a></p>
                <p>Atau copy link berikut ke browser Anda:</p>
                <p>{$reset_link}</p>
                <p>Link ini berlaku selama 1 jam.</p>
                <p>Jika Anda tidak meminta reset password, abaikan email ini.</p>
                <br>
                <p>Salam,<br>" . APP_NAME . "</p>
            </body>
            </html>
            ";
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
            
            $email_sent = mail($to, $subject, $message, $headers);
            */
            
            // Opsi 2: Menggunakan PHPMailer (Recommended untuk produksi)
            /*
            require '../../vendor/autoload.php';
            use PHPMailer\PHPMailer\PHPMailer;
            
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'your-email@gmail.com';
                $mail->Password = 'your-app-password';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                $mail->setFrom('noreply@yourapp.com', APP_NAME);
                $mail->addAddress($email, $user['nama']);
                
                $mail->isHTML(true);
                $mail->Subject = 'Reset Password - ' . APP_NAME;
                $mail->Body = "HTML email content here";
                
                $mail->send();
                $email_sent = true;
            } catch (Exception $e) {
                error_log("Email Error: {$mail->ErrorInfo}");
                $email_sent = false;
            }
            */
            
            // Opsi 3: MODE DEVELOPMENT - Log ke file/console
            $email_sent = true; // Set true untuk development
            error_log("========================================");
            error_log("RESET PASSWORD REQUEST");
            error_log("Email: " . $email);
            error_log("Name: " . $user['nama']);
            error_log("Reset Link: " . $reset_link);
            error_log("Expires: " . $expires);
            error_log("========================================");
            
            // ============================================
            
            if ($email_sent) {
                $success = true;
                
                // Log activity
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, activity, ip_address, user_agent) VALUES (?, 'request_password_reset', ?, ?)");
                $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            } else {
                $error = 'Gagal mengirim email. Silakan coba lagi nanti.';
            }
        } else {
            // Untuk keamanan, tetap tampilkan pesan sukses meskipun email tidak terdaftar
            // Ini mencegah attacker mengetahui email mana yang terdaftar
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .auth-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px 0;
        }
        .auth-card {
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border-radius: 15px;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .info-box {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .success-animation {
            animation: scaleIn 0.5s ease-out;
        }
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        .icon-lg {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .btn-lg {
            padding: 12px 24px;
            font-size: 1.1rem;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .steps-guide {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .step-item {
            display: flex;
            align-items: start;
            margin-bottom: 15px;
        }
        .step-item:last-child {
            margin-bottom: 0;
        }
        .step-number {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 12px;
            flex-shrink: 0;
        }
        .countdown {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="auth-container d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-7 col-lg-6">
                    <div class="card auth-card border-0">
                        <div class="card-header text-center py-4 bg-white border-0">
                            <h3 class="mb-0 fw-bold">
                                <i class="fas fa-key text-primary me-2"></i>
                                Lupa Password
                            </h3>
                            <p class="text-muted mt-2 mb-0">Reset password akun Anda dengan mudah</p>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($success): ?>
                                <div class="text-center success-animation">
                                    <i class="fas fa-envelope-circle-check icon-lg text-success"></i>
                                    <h4 class="mb-3 fw-bold text-success">Email Terkirim!</h4>
                                    <div class="alert alert-success">
                                        <p class="mb-2">
                                            <i class="fas fa-check-circle me-2"></i>
                                            Link reset password telah dikirim ke email Anda.
                                        </p>
                                        <p class="mb-0 small">
                                            <i class="fas fa-clock me-2"></i>
                                            Link berlaku selama <strong>1 jam</strong>
                                        </p>
                                    </div>

                                    <div class="steps-guide">
                                        <h6 class="fw-bold mb-3">
                                            <i class="fas fa-list-check me-2"></i>
                                            Langkah Selanjutnya:
                                        </h6>
                                        <div class="step-item">
                                            <div class="step-number">1</div>
                                            <div class="text-start">
                                                <strong>Cek Email Anda</strong>
                                                <p class="text-muted small mb-0">Buka inbox atau folder spam</p>
                                            </div>
                                        </div>
                                        <div class="step-item">
                                            <div class="step-number">2</div>
                                            <div class="text-start">
                                                <strong>Klik Link Reset</strong>
                                                <p class="text-muted small mb-0">Ikuti link yang dikirimkan</p>
                                            </div>
                                        </div>
                                        <div class="step-item">
                                            <div class="step-number">3</div>
                                            <div class="text-start">
                                                <strong>Buat Password Baru</strong>
                                                <p class="text-muted small mb-0">Masukkan password yang kuat</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="countdown mt-4">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Tidak menerima email? 
                                        <a href="forgot-password.php" class="text-decoration-none">Kirim ulang</a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php if ($error): ?>
                                    <div class="alert alert-danger alert-dismissible fade show">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <?= $error ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <div class="info-box">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-shield-halved text-primary me-3" style="font-size: 1.5rem;"></i>
                                        <div>
                                            <h6 class="mb-2 fw-bold">Lupa Password?</h6>
                                            <p class="mb-0 small text-muted">
                                                Masukkan alamat email yang terdaftar, dan kami akan mengirimkan 
                                                link untuk mereset password Anda.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <form method="POST" id="forgotForm">
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">
                                            Alamat Email <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-envelope text-primary"></i>
                                            </span>
                                            <input type="email" 
                                                   class="form-control" 
                                                   name="email" 
                                                   id="email"
                                                   required 
                                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                                                   placeholder="contoh@email.com"
                                                   autocomplete="email">
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Pastikan email yang dimasukkan sudah terdaftar
                                        </small>
                                    </div>

                                    <div class="d-grid mb-3">
                                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                            <i class="fas fa-paper-plane me-2"></i>
                                            Kirim Link Reset
                                        </button>
                                    </div>

                                    <div class="alert alert-warning border-0 shadow-sm">
                                        <i class="fas fa-lightbulb me-2"></i>
                                        <small>
                                            <strong>Tips:</strong> Periksa folder spam jika email tidak masuk dalam beberapa menit.
                                        </small>
                                    </div>
                                </form>
                            <?php endif; ?>

                            <hr class="my-4">

                            <div class="text-center">
                                <p class="mb-2">
                                    <a href="login.php" class="text-decoration-none">
                                        <i class="fas fa-arrow-left me-1"></i>
                                        Kembali ke Login
                                    </a>
                                </p>
                                <p class="mb-0 text-muted small">
                                    Belum punya akun? 
                                    <a href="register.php" class="text-decoration-none">Daftar di sini</a>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Info Card -->
                    <div class="card mt-3 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-center text-muted small">
                                <i class="fas fa-lock me-2"></i>
                                <span>Link reset password aman dan terenkripsi</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation and submit handling
        const form = document.getElementById('forgotForm');
        const submitBtn = document.getElementById('submitBtn');
        const emailInput = document.getElementById('email');

        if (form) {
            form.addEventListener('submit', function(e) {
                const email = emailInput.value.trim();
                
                // Basic email validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    alert('Format email tidak valid!');
                    emailInput.focus();
                    return false;
                }
                
                // Disable submit button and show loading
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Mengirim...';
            });
        }

        // Email input formatting
        if (emailInput) {
            emailInput.addEventListener('blur', function() {
                this.value = this.value.trim().toLowerCase();
            });

            emailInput.addEventListener('input', function() {
                this.value = this.value.toLowerCase();
            });
        }

        // Auto dismiss alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    </script>
</body>
</html>