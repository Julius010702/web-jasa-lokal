<?php
// ==========================================
// FILE 6: api/pekerja.php
// ==========================================
require_once '../config/config.php';

header('Content-Type: application/json');

// Get query parameters
$kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';
$lokasi = isset($_GET['lokasi']) ? trim($_GET['lokasi']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

try {
    // Build query
    $sql = "
        SELECT 
            u.id,
            u.nama,
            u.foto_profil,
            u.email,
            p.keahlian,
            p.deskripsi,
            p.pengalaman,
            p.tarif_per_jam,
            p.lokasi,
            p.kategori_pekerjaan,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(r.id) as total_review
        FROM users u
        JOIN pekerja p ON u.id = p.user_id
        LEFT JOIN review r ON p.id = r.pekerja_id
        WHERE u.status = 'aktif' AND u.user_type = 'pekerja'
    ";
    
    $params = [];
    
    // Filter by kategori
    if (!empty($kategori)) {
        $sql .= " AND p.kategori_pekerjaan = ?";
        $params[] = $kategori;
    }
    
    // Filter by lokasi
    if (!empty($lokasi)) {
        $sql .= " AND p.lokasi LIKE ?";
        $params[] = "%{$lokasi}%";
    }
    
    // Search by name or keahlian
    if (!empty($search)) {
        $sql .= " AND (u.nama LIKE ? OR p.keahlian LIKE ? OR p.deskripsi LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " GROUP BY u.id, p.id";
    $sql .= " ORDER BY avg_rating DESC, total_review DESC";
    $sql .= " LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $pekerja = $stmt->fetchAll();
    
    // Get total count for pagination
    $countSql = "
        SELECT COUNT(DISTINCT u.id) as total
        FROM users u
        JOIN pekerja p ON u.id = p.user_id
        WHERE u.status = 'aktif' AND u.user_type = 'pekerja'
    ";
    
    $countParams = [];
    if (!empty($kategori)) {
        $countSql .= " AND p.kategori_pekerjaan = ?";
        $countParams[] = $kategori;
    }
    if (!empty($lokasi)) {
        $countSql .= " AND p.lokasi LIKE ?";
        $countParams[] = "%{$lokasi}%";
    }
    if (!empty($search)) {
        $countSql .= " AND (u.nama LIKE ? OR p.keahlian LIKE ? OR p.deskripsi LIKE ?)";
        $searchTerm = "%{$search}%";
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
    }
    
    $stmt = $db->prepare($countSql);
    $stmt->execute($countParams);
    $totalResult = $stmt->fetch();
    $total = $totalResult['total'];
    
    echo json_encode([
        'success' => true,
        'data' => $pekerja,
        'pagination' => [
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
