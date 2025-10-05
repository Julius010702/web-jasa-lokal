
<?php
// ==========================================
// FILE 4: api/mark-as-read.php
// ==========================================
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user = getCurrentUser();
$pengirim_id = isset($_POST['pengirim_id']) ? (int)$_POST['pengirim_id'] : 0;

if (empty($pengirim_id)) {
    echo json_encode(['success' => false, 'message' => 'Pengirim ID tidak valid']);
    exit;
}

try {
    $stmt = $db->prepare("UPDATE pesan SET is_read = 1 WHERE pengirim_id = ? AND penerima_id = ? AND is_read = 0");
    $stmt->execute([$pengirim_id, $user['id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Pesan berhasil ditandai sudah dibaca',
        'data' => ['marked_count' => $stmt->rowCount()]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>