<?php
// ==========================================
// FILE 2: api/get-messages.php
// ==========================================
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = getCurrentUser();
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if (empty($user_id)) {
    echo json_encode(['success' => false, 'message' => 'User ID tidak valid']);
    exit;
}

try {
    // Get messages
    $stmt = $db->prepare("
        SELECT p.*, u.nama as pengirim_nama, u.foto_profil as pengirim_foto
        FROM pesan p
        JOIN users u ON p.pengirim_id = u.id
        WHERE ((p.pengirim_id = ? AND p.penerima_id = ?)
        OR (p.pengirim_id = ? AND p.penerima_id = ?))
        AND p.id > ?
        ORDER BY p.created_at ASC
    ");
    $stmt->execute([$user['id'], $user_id, $user_id, $user['id'], $last_id]);
    $messages = $stmt->fetchAll();
    
    $formatted = [];
    foreach ($messages as $msg) {
        $formatted[] = [
            'id' => $msg['id'],
            'pengirim_id' => $msg['pengirim_id'],
            'penerima_id' => $msg['penerima_id'],
            'pesan' => $msg['pesan'],
            'created_at' => $msg['created_at']
        ];
    }
    
    // Mark as read
    if (!empty($messages)) {
        $stmt = $db->prepare("UPDATE pesan SET is_read = 1 WHERE pengirim_id = ? AND penerima_id = ? AND is_read = 0");
        $stmt->execute([$user_id, $user['id']]);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formatted
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>