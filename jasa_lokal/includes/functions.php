<?php
// includes/functions.php
// File ini berisi fungsi-fungsi tambahan yang dapat digunakan di seluruh aplikasi

// Fungsi untuk mengecek apakah string adalah nomor telepon valid
function isValidPhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/\D/', '', $phone);
    
    // Check if it's Indonesian phone number format
    if (strlen($phone) >= 10 && strlen($phone) <= 15) {
        if (substr($phone, 0, 1) === '0' || substr($phone, 0, 2) === '62') {
            return true;
        }
    }
    
    return false;
}

// Fungsi untuk format nomor telepon Indonesia
function formatPhoneNumber($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    
    if (substr($phone, 0, 1) === '0') {
        return '62' . substr($phone, 1);
    } elseif (substr($phone, 0, 2) !== '62') {
        return '62' . $phone;
    }
    
    return $phone;
}

// Fungsi untuk generate kode pesanan unik
function generateOrderCode() {
    $prefix = 'JL';
    $date = date('ymd');
    $random = sprintf("%04d", mt_rand(1, 9999));
    
    return $prefix . $date . $random;
}

// Fungsi untuk menghitung jarak antara 2 koordinat (Haversine formula)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

// Fungsi untuk resize dan compress image
function resizeImage($source, $destination, $maxWidth = 800, $maxHeight = 600, $quality = 80) {
    $imageInfo = getimagesize($source);
    if (!$imageInfo) {
        return false;
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $type = $imageInfo[2];
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    
    if ($ratio < 1) {
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }
    
    // Create image resource
    switch ($type) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Create new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG and GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize image
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Save image
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($newImage, $destination, $quality);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($newImage, $destination);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($newImage, $destination);
            break;
    }
    
    // Clean up
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return $result;
}

// Fungsi untuk send email (menggunakan PHP mail atau SMTP)
function sendEmail($to, $subject, $message, $from = null) {
    $from = $from ?: 'noreply@jasalokal.com';
    
    $headers = [
        'From: ' . $from,
        'Reply-To: ' . $from,
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return mail($to, $subject, $message, implode("\r\n", $headers));
}

// Fungsi untuk validasi input yang lebih advanced
function validateInput($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = isset($data[$field]) ? $data[$field] : '';
        $ruleArray = explode('|', $rule);
        
        foreach ($ruleArray as $r) {
            if ($r === 'required' && empty($value)) {
                $errors[$field] = ucfirst($field) . ' harus diisi';
                break;
            }
            
            if (strpos($r, 'min:') === 0) {
                $min = intval(substr($r, 4));
                if (strlen($value) < $min) {
                    $errors[$field] = ucfirst($field) . ' minimal ' . $min . ' karakter';
                    break;
                }
            }
            
            if (strpos($r, 'max:') === 0) {
                $max = intval(substr($r, 4));
                if (strlen($value) > $max) {
                    $errors[$field] = ucfirst($field) . ' maksimal ' . $max . ' karakter';
                    break;
                }
            }
            
            if ($r === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = 'Format email tidak valid';
                break;
            }
            
            if ($r === 'numeric' && !is_numeric($value)) {
                $errors[$field] = ucfirst($field) . ' harus berupa angka';
                break;
            }
            
            if ($r === 'phone' && !isValidPhoneNumber($value)) {
                $errors[$field] = 'Format nomor telepon tidak valid';
                break;
            }
        }
    }
    
    return $errors;
}

// Fungsi untuk log activity user
function logActivity($user_id, $activity, $description = null) {
    global $db;
    
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, activity, description, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([
        $user_id,
        $activity,
        $description,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
}

// Fungsi untuk generate breadcrumb
function generateBreadcrumb($items) {
    $breadcrumb = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    $total = count($items);
    foreach ($items as $index => $item) {
        if ($index === $total - 1) {
            $breadcrumb .= '<li class="breadcrumb-item active" aria-current="page">' . $item['title'] . '</li>';
        } else {
            $breadcrumb .= '<li class="breadcrumb-item"><a href="' . $item['url'] . '">' . $item['title'] . '</a></li>';
        }
    }
    
    $breadcrumb .= '</ol></nav>';
    return $breadcrumb;
}

// Fungsi untuk truncate text dengan preserve words
function truncateText($text, $limit = 100, $append = '...') {
    if (strlen($text) <= $limit) {
        return $text;
    }
    
    $truncated = substr($text, 0, $limit);
    $lastSpace = strrpos($truncated, ' ');
    
    if ($lastSpace !== false) {
        $truncated = substr($truncated, 0, $lastSpace);
    }
    
    return $truncated . $append;
}

// Fungsi untuk check if user is online (last activity dalam 5 menit)
function isUserOnline($user_id) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM activity_logs 
        WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$user_id]);
    
    return $stmt->fetchColumn() > 0;
}

// Fungsi untuk generate meta tags
function generateMetaTags($title, $description, $image = null, $url = null) {
    $url = $url ?: 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $image = $image ?: (defined('APP_URL') ? APP_URL . '/assets/img/logo.png' : '/assets/img/logo.png');
    
    $meta = '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $meta .= '<meta property="og:description" content="' . htmlspecialchars($description) . '">' . "\n";
    $meta .= '<meta property="og:image" content="' . htmlspecialchars($image) . '">' . "\n";
    $meta .= '<meta property="og:url" content="' . htmlspecialchars($url) . '">' . "\n";
    $meta .= '<meta property="og:type" content="website">' . "\n";
    $meta .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $meta .= '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $meta .= '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">' . "\n";
    $meta .= '<meta name="twitter:image" content="' . htmlspecialchars($image) . '">' . "\n";
    
    return $meta;
}

// Fungsi untuk sanitize input
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Fungsi untuk format currency
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Fungsi untuk format tanggal Indonesia
function formatTanggalIndonesia($date) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    $tanggal = date('j', $timestamp);
    $bulanNama = $bulan[date('n', $timestamp)];
    $tahun = date('Y', $timestamp);
    
    return $tanggal . ' ' . $bulanNama . ' ' . $tahun;
}

// Fungsi untuk time ago format
function timeAgo($datetime) {
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Baru saja';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' menit lalu';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' jam lalu';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' hari lalu';
    } else {
        return formatTanggalIndonesia($datetime);
    }
}

// Fungsi untuk generate slug
function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

// Fungsi untuk check file extension
function isValidImageExtension($filename) {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $allowedExtensions);
}

// Fungsi untuk generate random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}

// Fungsi untuk pagination
function generatePagination($currentPage, $totalPages, $baseUrl) {
    $pagination = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage - 1) . '">Previous</a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=1">1</a></li>';
        if ($start > 2) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $pagination .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $pagination .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage + 1) . '">Next</a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $pagination .= '</ul></nav>';
    
    return $pagination;
}

// Fungsi untuk check if user has permission
function hasPermission($user_role, $required_permission) {
    $permissions = [
        'admin' => ['read', 'write', 'update', 'delete', 'manage_users'],
        'pekerja' => ['read', 'write', 'update'],
        'klien' => ['read', 'write']
    ];
    
    return isset($permissions[$user_role]) && in_array($required_permission, $permissions[$user_role]);
}

// Fungsi untuk escape SQL injection
function escapeSql($input) {
    global $db;
    if ($db instanceof PDO) {
        return $db->quote($input);
    }
    return "'" . addslashes($input) . "'";
}

// Fungsi untuk log error
function logError($error, $file = null, $line = null) {
    $logMessage = date('Y-m-d H:i:s') . ' - ERROR: ' . $error;
    if ($file) $logMessage .= ' in ' . $file;
    if ($line) $logMessage .= ' on line ' . $line;
    $logMessage .= PHP_EOL;
    
    error_log($logMessage, 3, __DIR__ . '/../logs/error.log');
}

// Fungsi untuk send notification
function sendNotification($user_id, $title, $message, $type = 'info') {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([$user_id, $title, $message, $type]);
    } catch (Exception $e) {
        logError('Failed to send notification: ' . $e->getMessage());
        return false;
    }
}

// Fungsi untuk get user notifications
function getUserNotifications($user_id, $limit = 10, $unread_only = false) {
    global $db;
    
    try {
        $where = "WHERE user_id = ?";
        $params = [$user_id];
        
        if ($unread_only) {
            $where .= " AND is_read = 0";
        }
        
        $stmt = $db->prepare("
            SELECT * FROM notifications 
            $where 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        
        $params[] = $limit;
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        logError('Failed to get notifications: ' . $e->getMessage());
        return [];
    }
}

// Fungsi untuk mark notification as read
function markNotificationAsRead($notification_id, $user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        
        return $stmt->execute([$notification_id, $user_id]);
    } catch (Exception $e) {
        logError('Failed to mark notification as read: ' . $e->getMessage());
        return false;
    }
}

// Fungsi untuk check rate limiting
function checkRateLimit($user_id, $action, $limit = 10, $timeframe = 3600) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM rate_limits 
            WHERE user_id = ? AND action = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        
        $stmt->execute([$user_id, $action, $timeframe]);
        $count = $stmt->fetchColumn();
        
        if ($count >= $limit) {
            return false;
        }
        
        // Log this action
        $stmt = $db->prepare("INSERT INTO rate_limits (user_id, action, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $action]);
        
        return true;
    } catch (Exception $e) {
        logError('Rate limit check failed: ' . $e->getMessage());
        return true; // Allow on error to prevent blocking legitimate users
    }
}

?>