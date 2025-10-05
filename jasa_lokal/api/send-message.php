
<?php
// ==========================================
// FILE 1: api/send-message.php
// ==========================================
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Silakan login terlebih dahulu']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user = getCurrentUser();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
    exit;
}

$penerima_id = isset($_POST['penerima_id']) ? (int)$_POST['penerima_id'] : 0;
$pesan = isset($_POST['pesan']) ? trim($_POST['pesan']) : '';

// Validasi
if (empty($penerima_id) || $penerima_id == 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Penerima tidak valid',
        'debug' => [
            'penerima_id' => $penerima_id,
            'post_data' => $_POST
        ]
    ]);
    exit;
}

if (empty($pesan)) {
    echo json_encode(['success' => false, 'message' => 'Pesan tidak boleh kosong']);
    exit;
}

if (strlen($pesan) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Pesan terlalu panjang (max 1000 karakter)']);
    exit;
}

// Cek apakah penerima exists dan aktif
$stmt = $db->prepare("SELECT id, nama FROM users WHERE id = ? AND status = 'aktif'");
$stmt->execute([$penerima_id]);
$penerima = $stmt->fetch();

if (!$penerima) {
    echo json_encode(['success' => false, 'message' => 'Penerima tidak ditemukan di database']);
    exit;
}

try {
    // Insert pesan
    $stmt = $db->prepare("
        INSERT INTO pesan (pengirim_id, penerima_id, pesan, is_read, created_at) 
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$user['id'], $penerima_id, $pesan]);
    
    $message_id = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pesan berhasil dikirim',
        'data' => [
            'id' => $message_id,
            'pengirim_id' => $user['id'],
            'penerima_id' => $penerima_id,
            'pesan' => $pesan,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengirim pesan: ' . $e->getMessage()
    ]);
}
?>