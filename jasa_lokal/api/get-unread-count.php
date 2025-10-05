
<?php
// ==========================================
// FILE 3: api/get-unread-count.php
// ==========================================
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = getCurrentUser();

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total_unread FROM pesan WHERE penerima_id = ? AND is_read = 0");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch();
    
    $stmt = $db->prepare("
        SELECT p.pengirim_id, u.nama, u.foto_profil, COUNT(*) as unread_count
        FROM pesan p
        JOIN users u ON p.pengirim_id = u.id
        WHERE p.penerima_id = ? AND p.is_read = 0
        GROUP BY p.pengirim_id
    ");
    $stmt->execute([$user['id']]);
    $unread_by_user = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_unread' => (int)$result['total_unread'],
            'unread_by_user' => $unread_by_user
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>