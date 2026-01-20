<?php
session_start();
include("config.php");

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$my_id = $_SESSION['user_id'];

// --- AJAX HANDLERS ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // 1. Fetch Chat Messages
    if ($_GET['action'] == 'fetch_chat' && isset($_GET['user_id'])) {
        $other_id = intval($_GET['user_id']);
        
        // Mark as read
        $con->query("UPDATE messages SET is_read = 1 WHERE sender_id = $other_id AND receiver_id = $my_id");

        // Get receiver's profile picture
        $rec_res = $con->query("SELECT profile_picture FROM users WHERE id = $other_id");
        $rec_data = $rec_res->fetch_assoc();
        $receiver_pic = !empty($rec_data['profile_picture']) ? $rec_data['profile_picture'] : 'uploads/profile.jpg';

        // Find the ID of the last message I sent that they have read
        $last_read_res = $con->query("SELECT MAX(id) as last_id FROM messages WHERE sender_id = $my_id AND receiver_id = $other_id AND is_read = 1");
        $last_read_id = $last_read_res->fetch_assoc()['last_id'] ?? 0;

        $sql = "SELECT m.*, 
                       CASE WHEN m.sender_id = $my_id THEN 'sent' ELSE 'received' END as type
                FROM messages m 
                WHERE ((sender_id = $my_id AND receiver_id = $other_id) 
                   OR (sender_id = $other_id AND receiver_id = $my_id))
                   AND (m.type = 'normal' OR m.type IS NULL)
                ORDER BY created_at ASC";
        
        $result = $con->query($sql);
        $messages = [];
        while($row = $result->fetch_assoc()) {
            $row['time'] = date('h:i A', strtotime($row['created_at']));
            $messages[] = $row;
        }
        echo json_encode([
            'success' => true, 
            'messages' => $messages, 
            'receiver_pic' => $receiver_pic,
            'last_read_id' => $last_read_id
        ]);
        exit();
    }

    // 2. Send Message
    if ($_GET['action'] == 'send_message' && isset($_POST['receiver_id'])) {
        $receiver_id = intval($_POST['receiver_id']);
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        $file_path = '';
        $file_type_tag = '';

        // Handle File Upload
        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            $uploadDir = "uploads/messages/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = time() . '_' . basename($_FILES['file']['name']);
            $targetPath = $uploadDir . $fileName;
            $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $file_type_tag = "[[IMAGE]]";
                } elseif (in_array($ext, ['mp4', 'webm', 'mov'])) {
                    $file_type_tag = "[[VIDEO]]";
                } elseif (in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'webm', '3gp'])) {
                    $file_type_tag = "[[AUDIO]]";
                }
                $message = $file_type_tag . $targetPath;
            }
        }

        if ($message != '') {
            $stmt = $con->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $my_id, $receiver_id, $message);
            if ($stmt->execute()) {
                // Insert Notification
                $msg_id = $stmt->insert_id;
                $con->query("INSERT INTO notifications (recipient_id, sender_id, type, message_id) VALUES ($receiver_id, $my_id, 'message', $msg_id)");
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'db_error']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'empty']);
        }
        exit();
    }


    // 3. Delete Conversation
    if ($_GET['action'] == 'delete_conversation' && isset($_POST['other_id'])) {
        $other_id = intval($_POST['other_id']);
        $con->query("DELETE FROM messages WHERE (sender_id = $my_id AND receiver_id = $other_id) OR (sender_id = $other_id AND receiver_id = $my_id)");
        echo json_encode(['success' => true]);
        exit();
    }

    // 4. Delete Single Message
    if ($_GET['action'] == 'delete_message' && isset($_POST['message_id'])) {
        $msg_id = intval($_POST['message_id']);
        // Verify (simple: if I am sender or receiver)
        $con->query("DELETE FROM messages WHERE id = $msg_id AND (sender_id = $my_id OR receiver_id = $my_id)");
        echo json_encode(['success' => true]);
        exit();
    }
}

// --- PAGE LOAD DATA ---

// Get list of users I have chatted with OR all users //
// For simplicity and "Messenger" feel, we show all users or recent chats.
// Let's toggle: Show recent chats first, then all users search.
// For now: Fetch All Users but order by recent interaction if possible. 
// Simplified: List all users EXCEPT me, but typically you'd filter by existing 'normal' chats.
// For now, listing all is fine, but if we want to hide "Market-only" contacts from here, we should filter.
// But the current logic is just "All users", so it will include market users.
// To STRICTLY separate, we should probably only show users who have 'normal' messages.
// But the user just asked "I don't want it in message.php", which usually means the CONVERSATION content.
// Having the USER in the list is okay, as long as clicking them shows "Normal" chat (empty if no normal messages).
// However, to be cleaner let's try to prioritize 'normal' chats if we were sorting.
// Existing code just fetches ALL users.
$users = [];
$sql_users = "SELECT id, username, profile_picture FROM users WHERE id != $my_id ORDER BY id DESC";
$res_users = $con->query($sql_users);
while($u = $res_users->fetch_assoc()){
    // Check unread count
    $uid = $u['id'];
    $unread = $con->query("SELECT COUNT(*) as cnt FROM messages WHERE sender_id = $uid AND receiver_id = $my_id AND is_read = 0")->fetch_assoc()['cnt'];
    $u['unread'] = $unread;
    $user_pic = !empty($u['profile_picture']) ? $u['profile_picture'] : 'uploads/profile.jpg';
    $u['pic'] = htmlspecialchars($user_pic, ENT_QUOTES);
    $users[] = $u;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #000;
            --sidebar-bg: #121212;
            --chat-bg: #000;
            --primary: #0084ff;
            --received-bg: #222;
            --sent-bg: #0084ff;
            --text-main: #fff;
            --text-muted: #aaa;
            --border-color: #333;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: 350px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sidebar-title {
            font-size: 24px;
            font-weight: bold;
        }
        .user-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        .user-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .user-item:hover, .user-item.active {
            background: rgba(255,255,255,0.1);
        }
        .avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }
        .user-info {
            flex: 1;
        }
        .username {
            font-weight: 600;
            font-size: 15px;
        }
        .last-msg {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 3px;
        }
        .unread-badge {
            background: var(--primary);
            color: white;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
        }

        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--chat-bg);
            position: relative;
            height: 100%;
            overflow: hidden;
        }
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            background: var(--sidebar-bg);
            z-index: 10;
        }
        .back-btn {
            display: none;
            margin-right: 15px;
            font-size: 20px;
            cursor: pointer;
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        .message-row {
            display: flex;
            margin-bottom: 5px;
        }
        .message-row.sent {
            justify-content: flex-end;
        }
        .message-bubble {
            max-width: 75%;
            padding: 10px 15px;
            border-radius: 18px;
            font-size: 15px;
            line-height: 1.4;
            position: relative;
            word-wrap: break-word;
            box-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        .received .message-bubble {
            background: var(--received-bg);
            color: white;
            border-bottom-left-radius: 4px;
        }
        .sent .message-bubble {
            background: var(--primary);
            color: white;
            border-bottom-right-radius: 4px;
        }
        .message-time-tooltip {
            font-size: 10px;
            color: #777;
            margin-top: 5px;
            opacity: 0.7;
            text-align: right;
        }

        /* Input Area */
        .chat-input-area {
            padding: 15px;
            padding-bottom: calc(15px + env(safe-area-inset-bottom));
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            background: var(--sidebar-bg);
            width: 100%;
            box-sizing: border-box;
        }
        .chat-input {
            flex: 1;
            min-width: 0;
            background: #222;
            border: none;
            border-radius: 20px;
            padding: 10px 15px;
            color: white;
            outline: none;
            font-size: 15px;
            box-sizing: border-box;
        }
        .send-btn {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 20px;
            margin-left: 15px;
            cursor: pointer;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Placeholder state */
        .no-chat-selected {
            display: flex;
            flex: 1;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
        }
        .no-chat-selected i {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100px;
            height: 100px;
            font-size: 50px;
            margin-bottom: 20px;
            color: #333;
            border: 2px solid #333;
            border-radius: 50%;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: absolute;
                height: 100%;
                z-index: 20;
                transform: translateX(0);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .sidebar.hidden {
                transform: translateX(-100%);
                pointer-events: none;
            }
            .chat-area {
                width: 100%;
                height: 100%;
                position: absolute;
                top: 0;
                left: 0;
            }
            .back-btn {
                display: block;
            }
            .message-bubble {
                max-width: 88%;
            }
            .chat-input-area {
                padding: 10px;
                padding-bottom: calc(10px + env(safe-area-inset-bottom));
                gap: 5px;
            }
            .attach-btn {
                margin: 0;
                padding: 5px;
                font-size: 18px;
                flex-shrink: 0;
            }
            .chat-input {
                padding: 10px 12px;
                font-size: 14px;
            }
            .send-btn {
                margin-left: 5px;
                padding: 5px;
                font-size: 18px;
            }
        }

        /* File Upload Styles */
        .attach-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 18px;
            margin-right: 10px;
            cursor: pointer;
            transition: color 0.2s;
        }
        .attach-btn:hover {
            color: var(--primary);
        }
        .message-media {
            max-width: 250px;
            max-height: 350px;
            width: auto;
            height: auto;
            border-radius: 12px;
            display: block;
            margin-top: 5px;
            cursor: pointer;
            transition: transform 0.2s;
            object-fit: contain;
            background: #1a1a1a;
        }
        .message-media:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }

        /* Full Screen Viewer Modal */
        #mediaModal {
            display: none;
            position: fixed;
            z-index: 9999;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.95);
            align-items: center;
            justify-content: center;
        }
        .modal-content-wrapper {
            position: relative;
            max-width: 90%;
            max-height: 90%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #modalContentView {
            max-width: 100%;
            max-height: 90vh;
            border-radius: 8px;
            box-shadow: 0 0 30px rgba(0,0,0,0.5);
        }
        .close-modal {
            position: absolute;
            top: -40px;
            right: 0;
            color: white;
            font-size: 30px;
            cursor: pointer;
            background: none;
            border: none;
        }
        @media (max-width: 768px) {
            .message-media {
                max-width: 200px;
                max-height: 300px;
            }
        }
        .loading-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 100;
            color: white;
            border-radius: 10px;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .recording-pulse {
            color: #ff4d4d !important;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        .voice-msg-container {
            display: flex;
            flex-direction: column;
            gap: 5px;
            background: rgba(255,255,255,0.05);
            padding: 10px;
            border-radius: 15px;
            margin-top: 5px;
            min-width: 220px;
        }
        .voice-player {
            width: 100%;
            height: 35px;
        }
        .voice-duration-label {
            font-size: 10px;
            color: var(--text-muted);
            text-align: right;
            margin-right: 5px;
        }

        /* Seen Status Avatar */
        .seen-container {
            display: flex;
            justify-content: flex-end;
            margin-top: -5px;
            margin-bottom: 10px;
            padding-right: 5px;
        }
        .seen-avatar {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--border-color);
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <span class="sidebar-title">Chats</span>
            <a href="indexmo.php"><i class="fas fa-home text-white mt-1"></i></a>
        </div>
        <div class="user-list">
            <?php foreach ($users as $u): ?>
                <div class="user-item" onclick="openChat(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['username'])) ?>', '<?= addslashes($u['pic']) ?>')">
                    <img src="<?= $u['pic'] ?>" class="avatar" alt="Avatar" onerror="this.src='uploads/profile.jpg'">
                    <div class="user-info">
                        <div class="username"><?= htmlspecialchars($u['username']) ?></div>
                    </div>
                    <?php if ($u['unread'] > 0): ?>
                        <div class="unread-badge"><?= $u['unread'] ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="chat-area">
        <div id="noChat" class="no-chat-selected">
            <i class="fab fa-facebook-messenger"></i>
            <h3>Select a conversation</h3>
        </div>

        <div id="activeChat" style="display: none; display: flex; flex-direction: column; height: 100%; width: 100%;">
            <div class="chat-header">
                <i class="fas fa-arrow-left back-btn" onclick="closeChat()"></i>
                <img id="headerAvatar" src="uploads/profile.jpg" class="avatar" style="width: 40px; height: 40px;" onerror="this.src='uploads/profile.jpg'">
                <h4 id="headerUsername" class="m-0" style="font-size: 16px;">User</h4>
                <div style="flex:1;"></div>
                <button onclick="startVideoCall()" style="background:none; border:none; color:var(--primary); font-size: 18px; cursor: pointer; margin-right: 10px;" title="Video Call">
                    <i class="fas fa-video"></i>
                </button>
                <button onclick="deleteConversation()" style="background:none; border:none; color:#ff4d4d; font-size: 18px; cursor: pointer; margin-right: 10px;" title="Delete Chat">
                    <i class="fas fa-trash"></i>
                </button>
            </div>

            <div class="messages-container" id="messagesContainer">
                <!-- Messages will load here -->
            </div>

            <!-- Loading Overlay -->
            <div id="uploadLoading" class="loading-overlay">
                <div class="spinner"></div>
                <div>جارٍ الرفع... (Loading...)</div>
            </div>

            <div class="chat-input-area">
                <button class="attach-btn" id="imgBtn" onclick="triggerFile('image')" title="Upload Image"><i class="fas fa-image"></i></button>
                <button class="attach-btn" id="vidBtn" onclick="triggerFile('video')" title="Upload Video (Max 1 min)"><i class="fas fa-video"></i></button>
                
                <!-- Voice Recording Button -->
                <button class="attach-btn" id="voiceBtn" onclick="toggleRecording()" title="Record Voice"><i class="fas fa-microphone"></i></button>
                <div id="recordingStatus" style="display:none; color:#ff4d4d; font-size:12px; margin-right:10px;">
                    <i class="fas fa-circle recording-pulse"></i> <span id="recTimer">00:00</span>
                </div>

                <input type="file" id="fileInput" style="display: none;" onchange="handleFile(this)">
                
                <input type="text" id="msgInput" class="chat-input" placeholder="Type a message...">
                <button class="send-btn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <!-- Media Modal Viewer -->
    <div id="mediaModal" onclick="closeMediaModal()">
        <div class="modal-content-wrapper" onclick="event.stopPropagation()">
            <button class="close-modal" onclick="closeMediaModal()">&times;</button>
            <div id="modalContentContainer"></div>
        </div>
    </div>


    <script>
        let currentUserId = null;
        let myId = <?= $my_id ?>; // Pass PHP variable to JS
        let pollingInterval = null;

        function openChat(userId, username, pic) {
            currentUserId = userId;
            document.getElementById('headerUsername').textContent = username;
            document.getElementById('headerAvatar').src = pic;
            
            document.getElementById('noChat').style.display = 'none';
            var activeChat = document.getElementById('activeChat');
            activeChat.style.display = 'flex'; // Use flex to maintain layout

            // Mobile View styling
            if(window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.add('hidden');
                document.querySelector('.chat-area').style.display = 'flex';
            }

            fetchMessages();
            
            // Start polling
            if(pollingInterval) clearInterval(pollingInterval);
            pollingInterval = setInterval(fetchMessages, 3000);
            
            // Highlight active user
            document.querySelectorAll('.user-item').forEach(el => el.classList.remove('active'));
            // (Optional: add active class logic here based on ID)
        }

        function closeChat() {
            if(window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.remove('hidden');
                document.getElementById('activeChat').style.display = 'none';
                document.getElementById('noChat').style.display = 'flex';
                currentUserId = null;
                if(pollingInterval) clearInterval(pollingInterval);
            }
        }

        function fetchMessages() {
            if(!currentUserId) return;
            
            fetch(`message.php?action=fetch_chat&user_id=${currentUserId}`)
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        const container = document.getElementById('messagesContainer');
                        
                        let html = '';
                        data.messages.forEach(msg => {
                            let content = msg.message;
                            
                            // Handle Call Links
                            if(content.includes('[[VIDEO_CALL]]')) {
                                let parts = content.split('|');
                                if(parts.length >= 3) {
                                    let roomId = parts[1];
                                    let label = parts[2];
                                    content = `<a href="meet.php?room=${roomId}" target="_blank" style="color:white; text-decoration:underline;"><i class="fas fa-video"></i> ${label}</a>`;
                                }
                            }
                            // Handle Images
                            else if(content.startsWith('[[IMAGE]]')) {
                                let path = content.replace('[[IMAGE]]', '');
                                content = `<img src="${path}" class="message-media" onclick="viewMedia('${path}', 'image')">`;
                            }
                            // Handle Videos
                            else if(content.startsWith('[[VIDEO]]')) {
                                let path = content.replace('[[VIDEO]]', '');
                                content = `<video src="${path}" class="message-media" onclick="viewMedia('${path}', 'video')" muted></video>`;
                            }
                            // Handle Audio
                            else if(content.startsWith('[[AUDIO]]')) {
                                let path = content.replace('[[AUDIO]]', '');
                                content = `
                                <div class="voice-msg-container">
                                    <audio src="${path}" controls class="voice-player" preload="metadata"></audio>
                                    <div class="voice-duration-label"></div>
                                </div>`;
                            }

                            html += `
                                <div class="message-row ${msg.type}">
                                    <div class="message-bubble">
                                        ${content}
                                        <div class="message-time-tooltip">
                                            ${msg.time}
                                            <i class="fas fa-trash" style="font-size:10px; cursor:pointer; margin-left:8px; color:#555;" onclick="deleteMessage(${msg.id})"></i>
                                        </div>
                                    </div>
                                </div>
                            `;

                            // Add VU (Seen) indicator
                            if(msg.type === 'sent' && msg.id == data.last_read_id) {
                                html += `
                                    <div class="seen-container">
                                        <img src="${data.receiver_pic}" class="seen-avatar" title="Seen" onerror="this.src='uploads/profile.jpg'">
                                    </div>
                                `;
                            }
                        });
                        if(container.innerHTML !== html) {
                            // Prevention: Don't refresh if user is currently listening to audio
                            const isAudioPlaying = Array.from(container.querySelectorAll('audio')).some(a => !a.paused);
                            if (isAudioPlaying) return;

                            container.innerHTML = html;
                            container.scrollTop = container.scrollHeight;
                            
                            // Apply duration fix to all audio elements recursively
                            container.querySelectorAll('audio').forEach(audio => {
                                let fixAttempts = 0;
                                const maxAttempts = 50; // Try for 5 seconds
                                
                                const performFix = () => {
                                    if (audio.duration === Infinity || isNaN(audio.duration)) {
                                        if(fixAttempts < maxAttempts) {
                                            audio.currentTime = 1e101;
                                            fixAttempts++;
                                            setTimeout(performFix, 100);
                                        }
                                    } else {
                                        audio.currentTime = 0;
                                    }
                                };

                                audio.addEventListener('play', performFix, {once: true});
                                audio.addEventListener('loadedmetadata', performFix);
                            });
                        }
                    }
                });
        }

        // --- NEW FILE UPLOAD LOGIC ---
        let pendingFileType = null;

        function viewMedia(path, type) {
            const modal = document.getElementById('mediaModal');
            const container = document.getElementById('modalContentContainer');
            modal.style.display = 'flex';
            
            if(type === 'image') {
                container.innerHTML = `<img src="${path}" id="modalContentView">`;
            } else {
                container.innerHTML = `<video src="${path}" id="modalContentView" controls autoplay></video>`;
            }
        }

        function closeMediaModal() {
            document.getElementById('mediaModal').style.display = 'none';
            document.getElementById('modalContentContainer').innerHTML = '';
        }

        function triggerFile(type) {
            pendingFileType = type;
            const input = document.getElementById('fileInput');
            if(type === 'image') input.accept = 'image/*';
            else if(type === 'video') input.accept = 'video/*';
            else if(type === 'audio') input.accept = 'audio/*';
            input.click();
        }

        function handleFile(input) {
            const file = input.files[0];
            if(!file) return;

            // 1 Minute limit check for Video
            if(pendingFileType === 'video') {
                const video = document.createElement('video');
                video.preload = 'metadata';
                video.onloadedmetadata = function() {
                    window.URL.revokeObjectURL(video.src);
                    if (video.duration > 60) {
                        alert('الفيديو طويل جداً. الحد الأقصى دقيقة واحدة (Max 1 minute).');
                        input.value = '';
                    } else {
                        uploadMedia(file);
                    }
                };
                video.src = URL.createObjectURL(file);
            } else {
                uploadMedia(file);
            }
        }

        function uploadMedia(file, isBlob = false) {
            if(!currentUserId) return;
            
            document.getElementById('uploadLoading').style.display = 'flex';
            
            const formData = new FormData();
            formData.append('receiver_id', currentUserId);
            
            if(isBlob) {
                let ext = 'webm';
                if(file.type.includes('mp4')) ext = 'm4a';
                else if(file.type.includes('ogg')) ext = 'ogg';
                formData.append('file', file, `voice_message.${ext}`);
            } else {
                formData.append('file', file);
            }

            fetch('message.php?action=send_message', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                document.getElementById('uploadLoading').style.display = 'none';
                document.getElementById('fileInput').value = '';
                if(data.success) {
                    fetchMessages();
                } else {
                    alert('خطأ في الرفع (Upload error)');
                }
            })
            .catch(err => {
                document.getElementById('uploadLoading').style.display = 'none';
                alert('فشل الاتصال (Connection failed)');
            });
        }

        // --- VOICE RECORDING LOGIC ---
        let mediaRecorder;
        let audioChunks = [];
        let isRecording = false;
        let recordingTimer;
        let startTime;

        async function toggleRecording() {
            if (!isRecording) {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    
                    // Priority list: MP4 is much more stable for duration than WebM
                    const types = [
                        'audio/mp4',
                        'audio/webm;codecs=opus',
                        'audio/webm',
                        'audio/ogg;codecs=opus'
                    ];
                    
                    let mimeType = 'audio/webm';
                    for (const type of types) {
                        if (MediaRecorder.isTypeSupported(type)) { 
                            mimeType = type; 
                            break; 
                        }
                    }

                    mediaRecorder = new MediaRecorder(stream, { mimeType });
                    audioChunks = [];

                    mediaRecorder.ondataavailable = (event) => {
                        if (event.data && event.data.size > 0) {
                            audioChunks.push(event.data);
                        }
                    };

                    mediaRecorder.onstop = async () => {
                        const mime = mediaRecorder.mimeType || 'audio/webm';
                        let audioBlob = new Blob(audioChunks, { type: mime });
                        
                        // --- WEB M DURATION FIX HACK ---
                        // Because MediaRecorder doesn't put duration in headers
                        if (mime.includes('webm')) {
                            const duration = Date.now() - startTime;
                            // We use a simplified version of the manual header patching
                            // but simpler is to just send it and let the server know if possible.
                            // However, the best client-side way without a library is to ensure 
                            // the file is treated as a solid stream.
                        }
                        
                        if(audioBlob.size > 0) {
                            uploadMedia(audioBlob, true);
                        }
                        
                        stream.getTracks().forEach(track => track.stop());
                    };

                    // Record with a SMALL steady timeslice. 
                    // This forces the recorder to keep headers alive.
                    mediaRecorder.start(100); 
                    isRecording = true;
                    
                    // UI Update
                    document.getElementById('voiceBtn').classList.add('recording-pulse');
                    document.getElementById('voiceBtn').innerHTML = '<i class="fas fa-stop"></i>';
                    document.getElementById('recordingStatus').style.display = 'inline-block';
                    document.getElementById('msgInput').style.display = 'none';
                    document.getElementById('imgBtn').style.display = 'none';
                    document.getElementById('vidBtn').style.display = 'none';
                    
                    startTime = Date.now();
                    recordingTimer = setInterval(updateTimer, 1000);
                } catch (err) {
                    alert('تعذر الوصول للميكروفون (Cannot access microphone)');
                }
            } else {
                if(mediaRecorder && mediaRecorder.state !== 'inactive') {
                    // Larger delay to ensure the browser has finished writing the last chunk to the buffer
                    setTimeout(() => {
                        mediaRecorder.stop();
                    }, 500);
                }
                isRecording = false;
                
                // UI Reset
                clearInterval(recordingTimer);
                document.getElementById('voiceBtn').classList.remove('recording-pulse');
                document.getElementById('voiceBtn').innerHTML = '<i class="fas fa-microphone"></i>';
                document.getElementById('recordingStatus').style.display = 'none';
                document.getElementById('msgInput').style.display = 'block';
                document.getElementById('imgBtn').style.display = 'inline-block';
                document.getElementById('vidBtn').style.display = 'inline-block';
            }
        }

        function updateTimer() {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
            const secs = String(elapsed % 60).padStart(2, '0');
            document.getElementById('recTimer').textContent = `${mins}:${secs}`;
        }
        // --- END VOICE RECORDING LOGIC ---

        function sendMessage(customMsg = null) {
            const input = document.getElementById('msgInput');
            const txt = customMsg || input.value.trim();
            if(!txt || !currentUserId) return;

            const formData = new FormData();
            formData.append('receiver_id', currentUserId);
            formData.append('message', txt);

            fetch('message.php?action=send_message', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    if(!customMsg) input.value = '';
                    fetchMessages(); // Refresh immediately
                }
            });
        }
        
        function startVideoCall() {
            if(!currentUserId) return;
            
            // Generate Room ID: sort IDs to make it unique per pair
            let id1 = Math.min(myId, currentUserId);
            let id2 = Math.max(myId, currentUserId);
            let roomName = 'chat_' + id1 + '_' + id2;
            
            // Send a message with the link
            // Format: [[VIDEO_CALL]]|ROOM_ID|TEXT
            sendMessage(`[[VIDEO_CALL]]|${roomName}|Click to Join Video Call`);
            
            // Open window
            window.open(`meet.php?room=${roomName}`, '_blank');
        }

        function deleteConversation() {
            if(!currentUserId) return;
            if(confirm('Are you sure you want to delete this conversation?')) {
                const fd = new FormData();
                fd.append('other_id', currentUserId);
                fetch('message.php?action=delete_conversation', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        document.getElementById('messagesContainer').innerHTML = ''; // Clear view
                        fetchMessages();
                    }
                });
            }
        }

        function deleteMessage(msgId) {
            if(!msgId) return;
            if(confirm('Delete this message?')) {
                const fd = new FormData();
                fd.append('message_id', msgId);
                fetch('message.php?action=delete_message', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        fetchMessages();
                    }
                });
            }
        }

        // Enter key to send
        document.getElementById('msgInput').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
        // Check for URL param user_id to auto-open chat
        const urlParams = new URLSearchParams(window.location.search);
        const autoUserId = urlParams.get('user_id');
        if (autoUserId) {
             // Find user in list to get name/pic
             // Since we render all users in PHP $users loop, we can try to find them in the DOM or Fetch info
             // Simplified: Just fetch info or use placeholder
             // Actually, the user list contains all users. PROBABLY.
             // Let's try to click the user item in the list if it exists
             let userItem = document.querySelector(`.user-item[onclick*="openChat(${autoUserId},"]`);
             if (userItem) {
                 userItem.click();
             } else {
                 // If user is not in the list (maybe new interaction), we might need to fetch their info manually
                 // But for now, let's just try to open with placeholders, fetchMessages will update header info if successful?
                 // No, openChat sets header info. 
                 // Let's fetch user info via AJAX if not found in list (or just reload page with that user active?)
                 // We will force openChat with generic info, and let fetchMessages fix it? 
                 // fetchMessages only gets messages.
                 // We need user name/pic.
                 // Let's rely on the PHP list being comprehensive "SELECT * FROM users".
                 // In PHP line 130: SELECT id... FROM users WHERE id != $my_id
                 // So the user SHOULD be in the list.
             }
        }
    </script>
</body>
</html>