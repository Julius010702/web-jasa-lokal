<?php
// pages/chat.php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$user = getCurrentUser();
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Ambil daftar kontak (user yang pernah chat)
$stmt = $db->prepare("
    SELECT DISTINCT 
        u.id, u.nama, u.foto_profil, u.user_type,
        (SELECT pesan FROM pesan 
         WHERE (pengirim_id = u.id AND penerima_id = ?) 
         OR (pengirim_id = ? AND penerima_id = u.id)
         ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM pesan 
         WHERE (pengirim_id = u.id AND penerima_id = ?) 
         OR (pengirim_id = ? AND penerima_id = u.id)
         ORDER BY created_at DESC LIMIT 1) as last_message_time,
        (SELECT COUNT(*) FROM pesan 
         WHERE pengirim_id = u.id AND penerima_id = ? AND is_read = 0) as unread_count
    FROM users u
    WHERE u.id IN (
        SELECT DISTINCT pengirim_id FROM pesan WHERE penerima_id = ?
        UNION
        SELECT DISTINCT penerima_id FROM pesan WHERE pengirim_id = ?
    )
    AND u.id != ?
    AND u.status = 'aktif'
    ORDER BY last_message_time DESC
");
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$contacts = $stmt->fetchAll();

// Ambil detail selected user jika ada
$selected_user = null;
if ($selected_user_id) {
    $stmt = $db->prepare("SELECT id, nama, foto_profil, user_type FROM users WHERE id = ? AND status = 'aktif'");
    $stmt->execute([$selected_user_id]);
    $selected_user = $stmt->fetch();
    
    // Mark messages as read
    if ($selected_user) {
        $stmt = $db->prepare("UPDATE pesan SET is_read = 1 WHERE pengirim_id = ? AND penerima_id = ? AND is_read = 0");
        $stmt->execute([$selected_user_id, $user['id']]);
    }
}

// Ambil riwayat chat dengan selected user
$messages = [];
if ($selected_user) {
    $stmt = $db->prepare("
        SELECT p.*, u.nama as pengirim_nama, u.foto_profil as pengirim_foto
        FROM pesan p
        JOIN users u ON p.pengirim_id = u.id
        WHERE (p.pengirim_id = ? AND p.penerima_id = ?)
        OR (p.pengirim_id = ? AND p.penerima_id = ?)
        ORDER BY p.created_at ASC
    ");
    $stmt->execute([$user['id'], $selected_user_id, $selected_user_id, $user['id']]);
    $messages = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat & Pesan - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: #f8f9fa;
            overflow-x: hidden;
        }
        
        .chat-container {
            height: calc(100vh - 60px);
            margin-top: 60px;
        }
        
        .contacts-sidebar {
            background: white;
            border-right: 1px solid #e0e0e0;
            height: 100%;
            overflow-y: auto;
        }
        
        .contact-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .contact-item:hover {
            background: #f8f9fa;
        }
        
        .contact-item.active {
            background: #e3f2fd;
            border-left: 3px solid #2196F3;
        }
        
        .contact-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .contact-info {
            flex: 1;
            margin-left: 12px;
            overflow: hidden;
        }
        
        .contact-name {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 3px;
        }
        
        .last-message {
            font-size: 0.85rem;
            color: #757575;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .message-time {
            font-size: 0.75rem;
            color: #9e9e9e;
        }
        
        .unread-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #f44336;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .chat-area {
            background: white;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f5f5f5;
            background-image: 
                linear-gradient(rgba(255,255,255,0.9), rgba(255,255,255,0.9)),
                url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><circle cx="10" cy="10" r="1" fill="%23e0e0e0"/></svg>');
            background-size: 100% 100%, 20px 20px;
        }
        
        .message-bubble {
            max-width: 70%;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }
        
        .message-bubble.sent {
            align-self: flex-end;
            align-items: flex-end;
        }
        
        .message-bubble.received {
            align-self: flex-start;
            align-items: flex-start;
        }
        
        .message-content {
            padding: 10px 15px;
            border-radius: 18px;
            word-wrap: break-word;
            position: relative;
        }
        
        .message-bubble.sent .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .message-bubble.received .message-content {
            background: white;
            color: #333;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .message-meta {
            font-size: 0.7rem;
            color: #9e9e9e;
            margin-top: 3px;
            padding: 0 5px;
        }
        
        .chat-input-area {
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            background: white;
        }
        
        .chat-input {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .chat-input textarea {
            flex: 1;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            padding: 10px 20px;
            resize: none;
            font-size: 0.95rem;
        }
        
        .chat-input textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-send {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-send:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-send:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: scale(1);
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #9e9e9e;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .typing-indicator {
            display: none;
            padding: 10px 15px;
            background: white;
            border-radius: 18px;
            width: fit-content;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .typing-indicator span {
            height: 8px;
            width: 8px;
            background: #9e9e9e;
            border-radius: 50%;
            display: inline-block;
            margin: 0 2px;
            animation: typing 1.4s infinite;
        }
        
        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
                opacity: 0.5;
            }
            30% {
                transform: translateY(-10px);
                opacity: 1;
            }
        }
        
        .search-box {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            font-size: 0.9rem;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .online-indicator {
            width: 10px;
            height: 10px;
            background: #4caf50;
            border-radius: 50%;
            position: absolute;
            bottom: 2px;
            right: 2px;
            border: 2px solid white;
        }
        
        .user-type-badge {
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        .badge-pekerja {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-pencari {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        /* Scrollbar custom */
        .contacts-sidebar::-webkit-scrollbar,
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        
        .contacts-sidebar::-webkit-scrollbar-track,
        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .contacts-sidebar::-webkit-scrollbar-thumb,
        .chat-messages::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .contacts-sidebar::-webkit-scrollbar-thumb:hover,
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .contacts-sidebar {
                display: none;
            }
            
            .contacts-sidebar.show-mobile {
                display: block;
                position: fixed;
                left: 0;
                top: 60px;
                width: 100%;
                z-index: 1000;
            }
            
            .message-bubble {
                max-width: 85%;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-tools me-2"></i><?= APP_NAME ?>
            </a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3"><?= $user['nama'] ?></span>
                <a href="<?= $user['user_type'] === 'pekerja' ? 'pekerja/dashboard.php' : 'pencari/dashboard.php' ?>" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-home me-1"></i>Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid chat-container">
        <div class="row h-100">
            <!-- Contacts Sidebar -->
            <div class="col-md-4 col-lg-3 p-0 contacts-sidebar">
                <div class="search-box">
                    <input type="text" id="searchContact" placeholder="Cari kontak..." class="form-control">
                </div>
                
                <div id="contactsList">
                    <?php if (empty($contacts)): ?>
                        <div class="text-center p-4 text-muted">
                            <i class="fas fa-comments fa-3x mb-3 opacity-50"></i>
                            <p>Belum ada percakapan</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($contacts as $contact): ?>
                            <div class="contact-item <?= $contact['id'] == $selected_user_id ? 'active' : '' ?>" 
                                 onclick="location.href='chat.php?user_id=<?= $contact['id'] ?>'">
                                <div class="d-flex align-items-start">
                                    <div style="position: relative;">
                                        <img src="<?= $contact['foto_profil'] ? '../uploads/profil/' . $contact['foto_profil'] : '../assets/img/default-avatar.png' ?>" 
                                             alt="<?= htmlspecialchars($contact['nama']) ?>" 
                                             class="contact-avatar">
                                    </div>
                                    <div class="contact-info">
                                        <div class="contact-name">
                                            <?= htmlspecialchars($contact['nama']) ?>
                                            <span class="user-type-badge badge-<?= $contact['user_type'] ?>">
                                                <?= ucfirst($contact['user_type']) ?>
                                            </span>
                                        </div>
                                        <div class="last-message">
                                            <?= $contact['last_message'] ? htmlspecialchars(substr($contact['last_message'], 0, 50)) . (strlen($contact['last_message']) > 50 ? '...' : '') : 'Belum ada pesan' ?>
                                        </div>
                                        <div class="message-time">
                                            <?= $contact['last_message_time'] ? timeAgo($contact['last_message_time']) : '' ?>
                                        </div>
                                    </div>
                                    <?php if ($contact['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?= $contact['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="col-md-8 col-lg-9 p-0">
                <?php if ($selected_user): ?>
                    <div class="chat-area">
                        <!-- Chat Header -->
                        <div class="chat-header">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <img src="<?= $selected_user['foto_profil'] ? '../uploads/profil/' . $selected_user['foto_profil'] : '../assets/img/default-avatar.png' ?>" 
                                         alt="<?= htmlspecialchars($selected_user['nama']) ?>" 
                                         class="contact-avatar me-3">
                                    <div>
                                        <h6 class="mb-0"><?= htmlspecialchars($selected_user['nama']) ?></h6>
                                        <small class="text-muted">
                                            <span class="user-type-badge badge-<?= $selected_user['user_type'] ?>">
                                                <?= ucfirst($selected_user['user_type']) ?>
                                            </span>
                                        </small>
                                    </div>
                                </div>
                                <div>
                                    <a href="tel:" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-phone"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Chat Messages -->
                        <div class="chat-messages" id="chatMessages">
                            <?php if (empty($messages)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-comment-dots"></i>
                                    <p>Mulai percakapan dengan <?= htmlspecialchars($selected_user['nama']) ?></p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): ?>
                                    <div class="message-bubble <?= $msg['pengirim_id'] == $user['id'] ? 'sent' : 'received' ?>">
                                        <div class="message-content">
                                            <?= nl2br(htmlspecialchars($msg['pesan'])) ?>
                                        </div>
                                        <div class="message-meta">
                                            <?= date('H:i', strtotime($msg['created_at'])) ?>
                                            <?php if ($msg['pengirim_id'] == $user['id']): ?>
                                                <i class="fas fa-check-double <?= $msg['is_read'] ? 'text-primary' : '' ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <div class="typing-indicator" id="typingIndicator">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>

                        <!-- Chat Input -->
                        <div class="chat-input-area">
                            <form id="chatForm" class="chat-input">
                                <textarea 
                                    id="messageInput" 
                                    placeholder="Ketik pesan..." 
                                    rows="1"
                                    maxlength="1000"></textarea>
                                <button type="submit" class="btn-send" id="sendBtn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="chat-area">
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <h5>Pilih kontak untuk memulai percakapan</h5>
                            <p class="text-muted">Pesan Anda akan muncul di sini</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const selectedUserId = <?= $selected_user_id ?>;
        const currentUserId = <?= $user['id'] ?>;
        
        // Auto-resize textarea
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });
        }
        
        // Handle form submit
        const chatForm = document.getElementById('chatForm');
        if (chatForm) {
            chatForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const pesan = messageInput.value.trim();
                if (!pesan) return;
                
                const sendBtn = document.getElementById('sendBtn');
                sendBtn.disabled = true;
                
                try {
                    const formData = new FormData();
                    formData.append('penerima_id', selectedUserId);
                    formData.append('pesan', pesan);
                    
                    const response = await fetch('../api/send-message.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Append message to chat
                        appendMessage(result.data, true);
                        messageInput.value = '';
                        messageInput.style.height = 'auto';
                        scrollToBottom();
                    } else {
                        alert('Gagal mengirim pesan: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat mengirim pesan');
                } finally {
                    sendBtn.disabled = false;
                    messageInput.focus();
                }
            });
        }
        
        // Append message to chat
        function appendMessage(data, isSent) {
            const chatMessages = document.getElementById('chatMessages');
            const emptyState = chatMessages.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message-bubble ${isSent ? 'sent' : 'received'}`;
            messageDiv.innerHTML = `
                <div class="message-content">
                    ${data.pesan.replace(/\n/g, '<br>')}
                </div>
                <div class="message-meta">
                    ${new Date(data.created_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'})}
                    ${isSent ? '<i class="fas fa-check"></i>' : ''}
                </div>
            `;
            
            chatMessages.appendChild(messageDiv);
        }
        
        // Scroll to bottom
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
        
        // Initial scroll
        scrollToBottom();
        
        // Search contacts
        const searchContact = document.getElementById('searchContact');
        if (searchContact) {
            searchContact.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const contacts = document.querySelectorAll('.contact-item');
                
                contacts.forEach(contact => {
                    const name = contact.querySelector('.contact-name').textContent.toLowerCase();
                    if (name.includes(searchTerm)) {
                        contact.style.display = 'block';
                    } else {
                        contact.style.display = 'none';
                    }
                });
            });
        }
        
        // Auto-refresh messages (polling every 5 seconds)
        if (selectedUserId) {
            setInterval(checkNewMessages, 5000);
        }
        
        async function checkNewMessages() {
            try {
                const response = await fetch(`../api/get-messages.php?user_id=${selectedUserId}&last_id=${getLastMessageId()}`);
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    result.data.forEach(msg => {
                        appendMessage({
                            pesan: msg.pesan,
                            created_at: msg.created_at
                        }, msg.pengirim_id === currentUserId);
                    });
                    scrollToBottom();
                }
            } catch (error) {
                console.error('Error checking new messages:', error);
            }
        }
        
        function getLastMessageId() {
            const messages = document.querySelectorAll('.message-bubble');
            return messages.length > 0 ? messages.length : 0;
        }
        
        // Enter to send, Shift+Enter for new line
        if (messageInput) {
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    chatForm.dispatchEvent(new Event('submit'));
                }
            });
        }
    </script>
</body>
</html>