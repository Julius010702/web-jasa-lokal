?php
// includes/header.php
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME ?></title>
    <meta name="description" content="<?= isset($meta_description) ? $meta_description : 'Platform jasa lokal terpercaya untuk menghubungkan Anda dengan pekerja terbaik' ?>">
    
    <!-- Bootstrap & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= APP_URL ?>/assets/img/favicon.ico">
    
    <?= isset($extra_css) ? $extra_css : '' ?>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= APP_URL ?>">
            <i class="fas fa-handshake text-primary me-2"></i>
            <?= APP_NAME ?>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>">Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'search.php' ? 'active' : '' ?>" 
                       href="<?= APP_URL ?>/search.php">Cari Pekerja</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        Kategori
                    </a>
                    <ul class="dropdown-menu">
                        <?php
                        $kategori_nav = getKategoriJasa();
                        foreach (array_slice($kategori_nav, 0, 8) as $kat):
                        ?>
                            <li>
                                <a class="dropdown-item" href="<?= APP_URL ?>/search.php?kategori=<?= $kat['id'] ?>">
                                    <i class="<?= $kat['icon'] ?> me-2"></i>
                                    <?= $kat['nama_kategori'] ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/search.php">Lihat Semua</a></li>
                    </ul>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <img src="<?= getCurrentUser()['foto_profil'] ? APP_URL . '/uploads/profil/' . getCurrentUser()['foto_profil'] : APP_URL . '/assets/img/default-avatar.png' ?>" 
                                 alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                            <?= $_SESSION['user_name'] ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($_SESSION['user_type'] === 'pekerja'): ?>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/pekerja/dashboard.php">
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                </a></li>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/pekerja/profil.php">
                                    <i class="fas fa-user me-2"></i>Profil Saya
                                </a></li>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/pekerja/portfolio.php">
                                    <i class="fas fa-images me-2"></i>Portfolio
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/detail-pekerja.php?id=<?= $_SESSION['user_id'] ?>" target="_blank">
                                    <i class="fas fa-external-link-alt me-2"></i>Lihat Profil Publik
                                </a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/pencari/dashboard.php">
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                </a></li>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/pencari/profil.php">
                                    <i class="fas fa-user me-2"></i>Profil Saya
                                </a></li>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/pencari/pesanan.php">
                                    <i class="fas fa-clipboard-list me-2"></i>Pesanan Saya
                                </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/pages/auth/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm ms-2" href="<?= APP_URL ?>/pages/auth/register.php">Daftar</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>