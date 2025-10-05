<?php
// ==========================================
// FILE 5: api/search.php
// ==========================================
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = getCurrentUser();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Query pencarian kosong']);
    exit;
}

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query minimal 2 karakter']);
    exit;
}

try {
    // Search users by name
    $stmt = $db->prepare("
        SELECT 
            id, 
            nama, 
            foto_profil, 
            user_type,
            email,
            whatsapp,
            kota
        FROM users 
        WHERE (nama LIKE ? OR email LIKE ?)
        AND id != ? 
        AND status = 'aktif'
        ORDER BY nama ASC
        LIMIT 20
    ");
    $searchTerm = "%{$query}%";
    $stmt->execute([$searchTerm, $searchTerm, $user['id']]);
    $users = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $users,
        'count' => count($users)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>