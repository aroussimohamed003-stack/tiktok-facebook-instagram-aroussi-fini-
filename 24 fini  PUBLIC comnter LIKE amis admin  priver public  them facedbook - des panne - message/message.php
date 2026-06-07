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

    // Create blocked_users table if not exists (fail-safe)
    $con->query("CREATE TABLE IF NOT EXISTS blocked_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blocker_id INT NOT NULL,
        blocked_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_block (blocker_id, blocked_id),
        FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    if ($_GET['action'] == 'block_user' && isset($_POST['user_id'])) {
        $blocked_id = intval($_POST['user_id']);
        $stmt = $con->prepare("INSERT IGNORE INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $my_id, $blocked_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit();
    }

    if ($_GET['action'] == 'unblock_user' && isset($_POST['user_id'])) {
        $blocked_id = intval($_POST['user_id']);
        $con->query("DELETE FROM blocked_users WHERE blocker_id = $my_id AND blocked_id = $blocked_id");
        echo json_encode(['success' => true]);
        exit();
    }
    
    if ($_GET['action'] == 'fetch_chat' && isset($_GET['user_id'])) {
        $other_id = intval($_GET['user_id']);
        $con->query("UPDATE messages SET is_read = 1 WHERE sender_id = $other_id AND receiver_id = $my_id");

        $rec_res = $con->query("SELECT profile_picture, username FROM users WHERE id = $other_id");
        $rec_data = $rec_res->fetch_assoc();
        $receiver_pic = !empty($rec_data['profile_picture']) ? $rec_data['profile_picture'] : 'uploads/profile.jpg';
        $receiver_name = $rec_data['username'];

        $last_read_res = $con->query("SELECT MAX(id) as last_id FROM messages WHERE sender_id = $my_id AND receiver_id = $other_id AND is_read = 1");
        $last_read_id = $last_read_res->fetch_assoc()['last_id'] ?? 0;

        $sql = "SELECT m.*, CASE WHEN m.sender_id = $my_id THEN 'sent' ELSE 'received' END as type
                FROM messages m 
                WHERE ((sender_id = $my_id AND receiver_id = $other_id) OR (sender_id = $other_id AND receiver_id = $my_id))
                   AND (m.type = 'normal' OR m.type IS NULL)
                ORDER BY created_at ASC";
        
        $result = $con->query($sql);
        $messages = [];
        while($row = $result->fetch_assoc()) {
            $row['time'] = date('h:i A', strtotime($row['created_at']));
            $messages[] = $row;
        }

        $blocked_res = $con->query("SELECT id FROM blocked_users WHERE blocker_id = $my_id AND blocked_id = $other_id");
        $is_blocked = $blocked_res->num_rows > 0;

        $am_i_blocked_res = $con->query("SELECT id FROM blocked_users WHERE blocker_id = $other_id AND blocked_id = $my_id");
        $am_i_blocked = $am_i_blocked_res->num_rows > 0;

        echo json_encode([
            'success' => true, 
            'messages' => $messages, 
            'receiver_pic' => $receiver_pic,
            'receiver_name' => $receiver_name,
            'last_read_id' => $last_read_id,
            'is_blocked' => $is_blocked,
            'am_i_blocked' => $am_i_blocked
        ]);
        exit();
    }

    if ($_GET['action'] == 'send_message' && isset($_POST['receiver_id'])) {
        $receiver_id = intval($_POST['receiver_id']);
        $block_check = $con->query("SELECT id FROM blocked_users WHERE (blocker_id = $my_id AND blocked_id = $receiver_id) OR (blocker_id = $receiver_id AND blocked_id = $my_id)");
        if ($block_check->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'blocked']);
            exit();
        }

        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            $uploadDir = "uploads/messages/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = time() . '_' . basename($_FILES['file']['name']);
            $targetPath = $uploadDir . $fileName;
            $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
                $tag = "[[IMAGE]]";
                if (in_array($ext, ['mp4', 'webm', 'mov'])) $tag = "[[VIDEO]]";
                elseif (in_array($ext, ['mp3', 'wav', 'ogg', 'm4a'])) $tag = "[[AUDIO]]";
                $message = $tag . $targetPath;
            }
        }

        if ($message != '') {
            $stmt = $con->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $my_id, $receiver_id, $message);
            if ($stmt->execute()) {
                $msg_id = $stmt->insert_id;
                $con->query("INSERT INTO notifications (recipient_id, sender_id, type, message_id) VALUES ($receiver_id, $my_id, 'message', $msg_id)");
                echo json_encode(['success' => true]);
            } else echo json_encode(['success' => false, 'error' => 'db_error']);
        } else echo json_encode(['success' => false, 'error' => 'empty']);
        exit();
    }

    if ($_GET['action'] == 'delete_conversation' && isset($_POST['other_id'])) {
        $other_id = intval($_POST['other_id']);
        $con->query("DELETE FROM messages WHERE (sender_id = $my_id AND receiver_id = $other_id) OR (sender_id = $other_id AND receiver_id = $my_id)");
        echo json_encode(['success' => true]);
        exit();
    }

    if ($_GET['action'] == 'delete_message' && isset($_POST['message_id'])) {
        $msg_id = intval($_POST['message_id']);
        $con->query("DELETE FROM messages WHERE id = $msg_id AND (sender_id = $my_id OR receiver_id = $my_id)");
        echo json_encode(['success' => true]);
        exit();
    }

    if ($_GET['action'] == 'search_users' && isset($_GET['query'])) {
        $query = $con->real_escape_string($_GET['query']);
        $filter = $_GET['filter'] ?? 'all';
        $sql = "SELECT id, username, profile_picture FROM users WHERE id != $my_id AND username LIKE '%$query%'";
        if ($filter == 'friends') {
            $sql .= " AND (id IN (SELECT sender_id FROM friends WHERE receiver_id = $my_id AND status = 'accepted') 
                        OR id IN (SELECT receiver_id FROM friends WHERE sender_id = $my_id AND status = 'accepted'))";
        }
        $sql .= " LIMIT 20";
        $res = $con->query($sql);
        $results = [];
        while($row = $res->fetch_assoc()) {
            $row['pic'] = !empty($row['profile_picture']) ? $row['profile_picture'] : 'uploads/profile.jpg';
            $results[] = $row;
        }
        echo json_encode(['success' => true, 'users' => $results]);
        exit();
    }
}

// Recent chats
$users = [];
$sql_users = "SELECT DISTINCT u.id, u.username, u.profile_picture 
              FROM users u 
              JOIN messages m ON (m.sender_id = u.id AND m.receiver_id = $my_id) 
                              OR (m.receiver_id = u.id AND m.sender_id = $my_id)
              WHERE u.id != $my_id AND (m.type = 'normal' OR m.type IS NULL)
              ORDER BY m.created_at DESC";
$res_users = $con->query($sql_users);
while($u = $res_users->fetch_assoc()){
    $uid = $u['id'];
    $unread = $con->query("SELECT COUNT(*) as cnt FROM messages WHERE sender_id = $uid AND receiver_id = $my_id AND is_read = 0 AND (type='normal' OR type IS NULL)")->fetch_assoc()['cnt'];
    $u['unread'] = $unread;
    $u['pic'] = !empty($u['profile_picture']) ? $u['profile_picture'] : 'uploads/profile.jpg';
    $users[] = $u;
}

$pageTitle = "Messages - Discovery Hub";
include("includes/layout_start.php");
?>

<div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 overflow-hidden h-[calc(100vh-130px)] md:h-[calc(100vh-140px)] flex flex-col md:flex-row relative">
    
    <!-- Sidebar -->
    <div id="messengerSidebar" class="w-full md:w-80 border-r border-slate-100 flex flex-col bg-slate-50/50 backdrop-blur-md transition-transform duration-300 z-20 overflow-y-auto">
        <div class="p-6">
            <h2 class="text-2xl font-black text-slate-800 font-brand mb-4">Messages</h2>
            <div class="relative group">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" id="userSearchInput" onkeyup="handleSearch()" placeholder="Search people..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs font-semibold focus:outline-none focus:border-blue-500 shadow-sm transition-all">
            </div>
        </div>

        <div class="flex px-6 gap-2 mb-4">
            <button onclick="setFilter('recent')" class="filter-tab active flex-1 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">Recent</button>
            <button onclick="setFilter('friends')" class="filter-tab flex-1 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">Friends</button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 pb-6 space-y-1 custom-scrollbar" id="sidebarUserList">
            <?php foreach ($users as $u): ?>
                <button onclick="openChat(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['username'])) ?>', '<?= addslashes($u['pic']) ?>')" class="user-item w-full flex items-center gap-4 p-3 rounded-[1.25rem] hover:bg-white hover:shadow-sm border border-transparent hover:border-slate-100 transition-all text-left">
                    <div class="relative">
                        <img src="<?= $u['pic'] ?>" class="w-12 h-12 rounded-2xl object-cover shadow-sm ring-2 ring-white" onerror="this.src='uploads/profile.jpg'">
                        <?php if($u['unread'] > 0): ?>
                            <div class="absolute -top-1 -right-1 bg-blue-600 text-white text-[10px] font-black px-1.5 py-0.5 rounded-lg border-2 border-white shadow-sm ring-1 ring-blue-500/20">
                                <?= $u['unread'] ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($u['username']) ?></h4>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Discovery Agent</p>
                    </div>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="flex-1 flex flex-col h-full bg-white relative">
        <!-- Placeholder -->
        <div id="noChat" class="absolute inset-0 z-10 bg-white flex flex-col items-center justify-center text-center p-10">
            <div class="w-24 h-24 bg-blue-50 rounded-[2rem] flex items-center justify-center text-blue-200 text-4xl mb-6 animate-bounce">
                <i class="fas fa-comment-dots"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800 mb-2">Discovery Comms</h3>
            <p class="text-slate-400 text-sm max-w-xs font-medium">Select an operator from the sidebar to establish a high-fidelity connection.</p>
        </div>

        <!-- Active Chat -->
        <div id="activeChat" class="hidden flex-col h-full opacity-0 translate-y-4 transition-all duration-300">
            <!-- Header -->
            <div class="p-6 border-bottom border-slate-100 flex items-center justify-between bg-white/80 backdrop-blur-md sticky top-0 z-10 shadow-sm border-b border-slate-50">
                <div class="flex items-center gap-4">
                    <button onclick="closeChat()" class="md:hidden w-10 h-10 flex items-center justify-center bg-slate-100 rounded-xl text-slate-500"><i class="fas fa-arrow-left"></i></button>
                    <div class="relative">
                        <img id="headerAvatar" src="uploads/profile.jpg" class="w-12 h-12 rounded-2xl object-cover shadow-sm ring-2 ring-white">
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-emerald-500 border-2 border-white rounded-full"></div>
                    </div>
                    <div>
                        <h4 id="headerUsername" class="font-black text-slate-800 tracking-tight">John Doe</h4>
                        <p class="text-[9px] font-black uppercase tracking-widest text-emerald-500 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span> Online Now
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <button onclick="startVideoCall()" class="w-10 h-10 rounded-xl flex items-center justify-center text-blue-600 bg-blue-50 hover:bg-blue-600 hover:text-white transition-all"><i class="fas fa-video"></i></button>
                    <button id="blockBtn" onclick="toggleBlock()" class="w-10 h-10 rounded-xl flex items-center justify-center text-amber-600 bg-amber-50 hover:bg-amber-600 hover:text-white transition-all"><i class="fas fa-ban"></i></button>
                    <button onclick="deleteConversation()" class="w-10 h-10 rounded-xl flex items-center justify-center text-rose-600 bg-rose-50 hover:bg-rose-600 hover:text-white transition-all"><i class="fas fa-trash-alt"></i></button>
                </div>
            </div>

            <!-- Messages Stream -->
            <div class="flex-1 overflow-y-auto p-8 flex flex-col gap-6 custom-scrollbar scroll-smooth" id="messagesContainer">
                <!-- Injected via AJAX -->
            </div>

            <!-- Input Bar -->
            <div class="p-6 bg-white border-t border-slate-50">
                <div class="bg-slate-50 p-2 rounded-[2rem] flex items-center gap-2 shadow-inner group-focus-within:bg-white group-focus-within:ring-2 ring-blue-500/10 transition-all">
                    <div class="flex gap-1 pl-2">
                        <button onclick="triggerFile('image')" class="w-10 h-10 rounded-full flex items-center justify-center text-slate-400 hover:text-blue-500 hover:bg-blue-50 transition-all rotate-12"><i class="fas fa-image"></i></button>
                        <button onclick="triggerFile('video')" class="w-10 h-10 rounded-full flex items-center justify-center text-slate-400 hover:text-indigo-500 hover:bg-indigo-50 transition-all -rotate-12"><i class="fas fa-clapperboard"></i></button>
                    </div>
                    
                    <input type="text" id="msgInput" placeholder="Message discovery agent..." class="flex-1 bg-transparent border-none focus:ring-0 text-sm font-medium text-slate-700 px-4">
                    
                    <button id="voiceBtn" onclick="toggleRecording()" class="w-10 h-10 rounded-full flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-all"><i class="fas fa-microphone"></i></button>
                    
                    <div id="recordingStatus" class="hidden px-4 items-center gap-2">
                        <span class="w-2 h-2 bg-rose-500 rounded-full animate-ping"></span>
                        <span id="recTimer" class="text-[10px] font-black text-rose-500">00:00</span>
                    </div>

                    <button onclick="sendMessage()" class="w-12 h-12 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-lg shadow-blue-500/30 hover:bg-blue-700 hover:scale-110 active:scale-95 transition-all">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                
                <div id="uploadProgress" class="hidden mt-4 h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-600 animate-[progress_2s_infinite]"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<input type="file" id="fileInput" class="hidden" onchange="handleFile(this)">

<!-- Media Viewer Overlay -->
<div id="mediaModal" onclick="closeMediaModal()" class="fixed inset-0 z-[100] hidden bg-slate-900/95 backdrop-blur-xl items-center justify-center p-10 cursor-zoom-out">
    <div class="max-w-4xl max-h-full" onclick="event.stopPropagation()">
        <div id="modalContentContainer" class="rounded-[2.5rem] overflow-hidden shadow-2xl relative"></div>
        <button onclick="closeMediaModal()" class="absolute top-8 right-8 w-12 h-12 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20 transition-all border border-white/10"><i class="fas fa-times"></i></button>
    </div>
</div>

<style>
.filter-tab { @apply text-slate-400 bg-transparent; }
.filter-tab.active { @apply text-blue-600 bg-blue-50 shadow-sm border border-blue-100; }
.user-item.active { @apply bg-blue-600 text-white shadow-xl shadow-blue-600/20 border-blue-500; }
.user-item.active h4 { @apply text-white; }
.user-item.active p { @apply text-blue-100; }
.user-item.active .ring-white { @apply ring-blue-500; }

.message-bubble { @apply px-5 py-3.5 rounded-[2rem] max-w-[85%] text-sm font-medium shadow-sm relative overflow-hidden; }
.received .message-bubble { @apply bg-slate-100 text-slate-800 rounded-tl-none border border-slate-200; }
.sent .message-bubble { @apply bg-blue-600 text-white rounded-tr-none shadow-lg shadow-blue-600/10; }
.sent .message-bubble .message-time { @apply text-blue-200; }
.received .message-bubble .message-time { @apply text-slate-400; }
.message-time { @apply text-[9px] font-black uppercase tracking-widest mt-2 block opacity-60; }

.message-media { @apply rounded-2xl w-full max-w-sm h-auto cursor-pointer hover:opacity-90 transition-all shadow-md mt-2; }
.voice-msg { @apply flex items-center gap-3 bg-white/10 p-3 rounded-2xl border border-white/5 mt-2; }

@keyframes progress { 0% { @apply -translate-x-full; } 100% { @apply translate-x-full; } }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    let currentUserId = null;
    let pollingInterval = null;
    let currentFilter = 'recent';
    let myId = <?= $my_id ?>;
    let isBlockedByMe = false;
    let amIBlocked = false;

    function setFilter(f) {
        currentFilter = f;
        $('.filter-tab').removeClass('active');
        $(`button[onclick="setFilter('${f}')"]`).addClass('active');
        handleSearch();
    }

    function handleSearch() {
        let q = $('#userSearchInput').val();
        $.get(`message.php?action=search_users&query=${encodeURIComponent(q)}&filter=${currentFilter}`, function(res) {
            if(res.success) {
                let html = '';
                res.users.forEach(u => {
                    html += `
                    <button onclick="openChat(${u.id}, '${u.username.replace(/'/g, "\\'")}', '${u.pic}')" class="user-item w-full flex items-center gap-4 p-3 rounded-[1.25rem] hover:bg-white hover:shadow-sm border border-transparent hover:border-slate-100 transition-all text-left">
                        <div class="relative">
                            <img src="${u.pic}" class="w-12 h-12 rounded-2xl object-cover shadow-sm ring-2 ring-white" onerror="this.src='uploads/profile.jpg'">
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-sm">${u.username}</h4>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Discovery Agent</p>
                        </div>
                    </button>`;
                });
                $('#sidebarUserList').html(html || '<p class="text-center py-10 text-slate-400 text-xs font-bold uppercase tracking-widest">No agents found</p>');
            }
        });
    }

    function openChat(uid, name, pic) {
        currentUserId = uid;
        $('#headerUsername').text(name);
        $('#headerAvatar').attr('src', pic);
        $('#noChat').addClass('hidden');
        $('#activeChat').removeClass('hidden').addClass('flex opacity-100 translate-y-0');
        
        $('.user-item').removeClass('active');
        // Mobile toggle
        if(window.innerWidth < 768) $('#messengerSidebar').addClass('-translate-x-full absolute');

        fetchMessages();
        if(pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(fetchMessages, 3000);
    }

    function closeChat() {
        $('#messengerSidebar').removeClass('-translate-x-full absolute');
        $('#activeChat').addClass('hidden').removeClass('flex');
        $('#noChat').removeClass('hidden');
        if(pollingInterval) clearInterval(pollingInterval);
        currentUserId = null;
    }

    function fetchMessages() {
        if(!currentUserId) return;
        $.get(`message.php?action=fetch_chat&user_id=${currentUserId}`, function(res) {
            if(res.success) {
                let html = '';
                res.messages.forEach(msg => {
                    let text = msg.message;
                    let display = text;
                    
                    if(text.startsWith('[[IMAGE]]')) {
                        let path = text.replace('[[IMAGE]]', '');
                        display = `<img src="${path}" class="message-media" onclick="viewMedia('${path}', 'image')">`;
                    } else if(text.startsWith('[[VIDEO]]')) {
                        let path = text.replace('[[VIDEO]]', '');
                        display = `<video src="${path}" class="message-media" onclick="viewMedia('${path}', 'video')" muted></video>`;
                    } else if(text.startsWith('[[AUDIO]]')) {
                        let path = text.replace('[[AUDIO]]', '');
                        display = `<div class="voice-msg"><audio src="${path}" controls class="h-8 max-w-[150px]"></audio></div>`;
                    } else if(text.includes('[[VIDEO_CALL]]')) {
                        let parts = text.split('|');
                        display = `<div class="p-4 bg-white/10 rounded-2xl flex items-center gap-4"><i class="fas fa-video text-2xl"></i><div><p class="text-xs font-black uppercase tracking-widest mb-1">Incoming Call</p><a href="meet.php?room=${parts[1]}" target="_blank" class="px-4 py-1.5 bg-white text-blue-600 rounded-lg font-bold text-[10px] uppercase tracking-widest">Join Discovery Call</a></div></div>`;
                    }

                    html += `
                    <div class="flex ${msg.type === 'sent' ? 'justify-end sent' : 'justify-start received'} group">
                        <div class="message-bubble">
                            ${display}
                            <span class="message-time">${msg.time} <button onclick="deleteMsg(${msg.id})" class="ml-2 opacity-0 group-hover:opacity-100 transition-opacity"><i class="fas fa-trash-alt text-[8px]"></i></button></span>
                        </div>
                    </div>`;
                    
                    if(msg.type === 'sent' && msg.id == res.last_read_id) {
                        html += `<div class="flex justify-end -mt-4 mb-4"><img src="${res.receiver_pic}" class="w-3 h-3 rounded-full opacity-60"></div>`;
                    }
                });
                
                // Block status
                isBlockedByMe = res.is_blocked;
                amIBlocked = res.am_i_blocked;
                if(isBlockedByMe) $('#msgInput').val('').attr('placeholder', 'You blocked this agent').prop('disabled', true);
                else if(amIBlocked) $('#msgInput').val('').attr('placeholder', 'Agent restricted your access').prop('disabled', true);
                else $('#msgInput').prop('disabled', false).attr('placeholder', 'Message discovery agent...');
                
                const container = $('#messagesContainer');
                
                // CRITICAL: If audio is playing, do NOT refresh the messages container
                // This prevents the "cutting off" caused by the 3-second polling refresh
                const isPlayingAudio = container.find('audio').filter((_, el) => !el.paused).length > 0;
                if (isPlayingAudio) return;

                if(container.html() !== html) {
                    const oldScrollHeight = container[0].scrollHeight;
                    const oldScrollTop = container.scrollTop();
                    const oldHeight = container.outerHeight();
                    const wasAtBottom = (oldScrollHeight - oldScrollTop) <= (oldHeight + 50);
                    
                    container.html(html);
                    
                    if(wasAtBottom || oldScrollHeight === 0) {
                        container.scrollTop(container[0].scrollHeight);
                    }

                    // Force duration calculation for WebM audio
                    container.find('audio').each(function() {
                        const audio = this;
                        audio.preload = "metadata";
                        
                        const handleDuration = () => {
                            if (audio.duration === Infinity) {
                                audio.currentTime = 1e101;
                                audio.onseeked = function() {
                                    this.currentTime = 0;
                                    this.onseeked = null;
                                };
                            }
                        };

                        if (audio.readyState >= 1) handleDuration();
                        else audio.onloadedmetadata = handleDuration;
                    });
                }
            }
        });
    }

    function sendMessage() {
        let txt = $('#msgInput').val().trim();
        if(!txt || !currentUserId) return;
        $.post('message.php?action=send_message', { receiver_id: currentUserId, message: txt }, function(res) {
            if(res.success) { $('#msgInput').val(''); fetchMessages(); }
        });
    }

    function deleteMsg(id) {
        if(confirm('Erase this data packet?')) {
            $.post('message.php?action=delete_message', { message_id: id }, fetchMessages);
        }
    }

    function toggleBlock() {
        if(!currentUserId) return;
        let action = isBlockedByMe ? 'unblock_user' : 'block_user';
        if(confirm(isBlockedByMe ? 'Establish reconnection?' : 'Restict this frequency?')) {
            $.post(`message.php?action=${action}`, { user_id: currentUserId }, fetchMessages);
        }
    }

    function deleteConversation() {
        if(!currentUserId || !confirm('Wipe all comms history with this agent?')) return;
        $.post('message.php?action=delete_conversation', { other_id: currentUserId }, () => {
             $('#messagesContainer').empty();
             closeChat();
        });
    }

    function triggerFile(t) {
        $('#fileInput').data('type', t).val('').click();
    }

    function handleFile(input) {
        let file = input.files[0];
        if(!file) return;
        let type = $(input).data('type');
        
        // 1 Minute limit check for Video
        if(type === 'video') {
            const video = document.createElement('video');
            video.preload = 'metadata';
            video.onloadedmetadata = function() {
                window.URL.revokeObjectURL(video.src);
                if (video.duration > 60) {
                    alert('الفيديو طويل جداً. الحد الأقصى دقيقة واحدة (Max 1 minute).');
                    input.value = '';
                } else {
                    startUpload(file);
                }
            };
            video.src = URL.createObjectURL(file);
        } else {
            startUpload(file);
        }
    }

    function startUpload(file) {
        $('#uploadProgress').removeClass('hidden');
        let fd = new FormData();
        fd.append('receiver_id', currentUserId);
        fd.append('file', file);
        
        $.ajax({
            url: 'message.php?action=send_message',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: (res) => {
                $('#uploadProgress').addClass('hidden');
                if(res.success) fetchMessages();
            }
        });
    }

    // Voice logic simplified for refactor speed but fully functional
    let rec;
    let chunks = [];
    let recInterval;
    let startTime;

    async function toggleRecording() {
        const voiceBtn = $('#voiceBtn');
        const status = $('#recordingStatus');
        const timer = $('#recTimer');

        if (!rec || rec.state === 'inactive') {
            try {
                let s = await navigator.mediaDevices.getUserMedia({ audio: true });
                const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') 
                                 ? 'audio/webm;codecs=opus' 
                                 : 'audio/webm';
                
                rec = new MediaRecorder(s, { mimeType });
                chunks = [];
                
                rec.ondataavailable = e => {
                    if (e.data && e.data.size > 0) chunks.push(e.data);
                };

                rec.onstop = async () => {
                    const blob = new Blob(chunks, { type: mimeType });
                    const fd = new FormData();
                    fd.append('receiver_id', currentUserId);
                    fd.append('file', blob, 'voice_message.webm');
                    
                    $.ajax({ 
                        url: 'message.php?action=send_message', 
                        type: 'POST', 
                        data: fd, 
                        processData: false, 
                        contentType: false, 
                        success: fetchMessages 
                    });
                    
                    s.getTracks().forEach(t => t.stop());
                    clearInterval(recInterval);
                    timer.text('00:00');
                };

                rec.start(100); // Collect data in 100ms chunks to avoid loss
                startTime = Date.now();
                voiceBtn.addClass('text-rose-500 animate-pulse').html('<i class="fas fa-stop"></i>');
                status.removeClass('hidden').addClass('flex');
                
                recInterval = setInterval(() => {
                    const sec = Math.floor((Date.now() - startTime) / 1000);
                    const min = Math.floor(sec / 60);
                    timer.text(`${min.toString().padStart(2, '0')}:${(sec % 60).toString().padStart(2, '0')}`);
                    if (sec >= 60) toggleRecording(); // Auto-stop at 1 minute
                }, 1000);

            } catch (err) {
                console.error("Recording error:", err);
                alert("تعذر الوصول إلى الميكروفون. يرجى التحقق من الأذونات.");
            }
        } else {
            rec.stop();
            voiceBtn.removeClass('text-rose-500 animate-pulse').html('<i class="fas fa-microphone"></i>');
            status.addClass('hidden').removeClass('flex');
        }
    }

    function viewMedia(p, t) {
        $('#mediaModal').removeClass('hidden').addClass('flex');
        let html = t === 'image' ? `<img src="${p}" class="max-w-full max-h-[80vh]">` : `<video src="${p}" controls autoplay class="max-w-full max-h-[80vh]"></video>`;
        $('#modalContentContainer').html(html);
    }
    function closeMediaModal() { $('#mediaModal').addClass('hidden').removeClass('flex'); }

    function startVideoCall() {
        if(!currentUserId) return;
        let r = 'discovery_' + Math.min(myId, currentUserId) + '_' + Math.max(myId, currentUserId);
        sendMessage(`[[VIDEO_CALL]]|${r}|Established Connection`);
        window.open(`meet.php?room=${r}`, '_blank');
    }

    $('#msgInput').on('keypress', e => { if(e.key==='Enter') sendMessage(); });
</script>

<?php
include("includes/layout_end.php");
?>