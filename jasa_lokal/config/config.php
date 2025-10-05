<?php
session_start();

// Konfigurasi aplikasi
define('APP_NAME', 'JasaLokal');
define('APP_URL', 'http://localhost/jasa-lokal');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (matikan di production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inisialisasi database
require_once 'database.php';
$database = new Database();
$db = $database->getConnection();

// Fungsi helper
function redirect($url) {
    header("Location: " . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    global $db;
    
    if (!isLoggedIn()) {
        return null;
    }
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

function formatRupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'baru saja';
    if ($time < 3600) return floor($time/60) . ' menit lalu';
    if ($time < 86400) return floor($time/3600) . ' jam lalu';
    if ($time < 2592000) return floor($time/86400) . ' hari lalu';
    if ($time < 31536000) return floor($time/2592000) . ' bulan lalu';
    
    return floor($time/31536000) . ' tahun lalu';
}

// Fungsi upload file
function uploadFile($file, $directory = 'profil') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    $upload_dir = UPLOAD_PATH . $directory . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return false;
}

// Fungsi pagination
function paginate($total, $page = 1, $per_page = 10) {
    $total_pages = ceil($total / $per_page);
    $offset = ($page - 1) * $per_page;
    
    return [
        'total' => $total,
        'per_page' => $per_page,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'has_prev' => $page > 1,
        'has_next' => $page < $total_pages
    ];
}

// Fungsi untuk mengirim notifikasi (placeholder)
function sendNotification($user_id, $title, $message, $type = 'info') {
    // Implementasi nanti: email, push notification, dll
    // Untuk sekarang bisa disimpan ke database atau session
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    
    $_SESSION['notifications'][] = [
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'time' => date('Y-m-d H:i:s')
    ];
}

// Fungsi untuk mengambil kategori jasa
function getKategoriJasa() {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM kategori_jasa WHERE status = 'aktif' ORDER BY nama_kategori");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Fungsi untuk pencarian pekerja - FIXED VERSION
function searchPekerja($kategori = null, $kota = null, $keyword = null, $page = 1, $per_page = 12) {
    global $db;
    
    $conditions = [];
    $params = [];
    
    // FIXED: Menggunakan approach yang lebih sederhana dengan subquery untuk menghindari konflik
    $sql = "SELECT users.id as user_id, users.nama, users.email, users.whatsapp, users.kota, 
                   users.foto_profil, users.status, users.user_type, users.created_at,
                   pekerja.id as pekerja_id, pekerja.kategori_id, 
                   pekerja.deskripsi_skill, pekerja.pengalaman_tahun, 
                   pekerja.harga_mulai, pekerja.harga_hingga, pekerja.total_pekerjaan,
                   kategori_jasa.nama_kategori, kategori_jasa.icon,
                   COALESCE(AVG(reviews.rating), 0) as avg_rating,
                   COUNT(reviews.id) as total_reviews
            FROM users
            JOIN pekerja ON users.id = pekerja.user_id
            JOIN kategori_jasa ON pekerja.kategori_id = kategori_jasa.id
            LEFT JOIN reviews ON pekerja.id = reviews.pekerja_id
            WHERE users.status = 'aktif' AND users.user_type = 'pekerja'";
    
    if ($kategori) {
        $conditions[] = "pekerja.kategori_id = ?";
        $params[] = $kategori;
    }
    
    if ($kota) {
        $conditions[] = "users.kota LIKE ?";
        $params[] = "%{$kota}%";
    }
    
    if ($keyword) {
        $conditions[] = "(users.nama LIKE ? OR pekerja.deskripsi_skill LIKE ?)";
        $params[] = "%{$keyword}%";
        $params[] = "%{$keyword}%";
    }
    
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    $sql .= " GROUP BY users.id, pekerja.id ORDER BY avg_rating DESC, pekerja.total_pekerjaan DESC";
    
    // Hitung total untuk pagination
    $count_sql = "SELECT COUNT(DISTINCT users.id) as total 
                  FROM users 
                  JOIN pekerja ON users.id = pekerja.user_id 
                  WHERE users.status = 'aktif' AND users.user_type = 'pekerja'";
    
    if (!empty($conditions)) {
        $count_sql .= " AND " . implode(" AND ", $conditions);
    }
    
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];
    
    // Tambahkan LIMIT untuk pagination
    $offset = ($page - 1) * $per_page;
    $sql .= " LIMIT {$offset}, {$per_page}";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    return [
        'data' => $results,
        'pagination' => paginate($total, $page, $per_page)
    ];
}
?>