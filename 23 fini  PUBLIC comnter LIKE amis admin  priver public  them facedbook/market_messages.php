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
        
        // Mark MARKET messages as read
        $con->query("UPDATE messages SET is_read = 1 WHERE sender_id = $other_id AND receiver_id = $my_id AND type = 'market'");

        // Get receiver's profile picture
        $rec_res = $con->query("SELECT profile_picture, username FROM users WHERE id = $other_id");
        $rec_data = $rec_res->fetch_assoc();
        $receiver_pic = !empty($rec_data['profile_picture']) ? $rec_data['profile_picture'] : 'uploads/profile.jpg';
        $receiver_name = $rec_data['username'];

        // Find the ID of the last MARKET message I sent that they have read
        $last_read_res = $con->query("SELECT MAX(id) as last_id FROM messages WHERE sender_id = $my_id AND receiver_id = $other_id AND is_read = 1 AND type = 'market'");
        $last_read_id = $last_read_res->fetch_assoc()['last_id'] ?? 0;

        $sql = "SELECT m.*, 
                       CASE WHEN m.sender_id = $my_id THEN 'sent' ELSE 'received' END as type,
                       p.title as product_title, p.id as product_id_ref
                FROM messages m 
                LEFT JOIN market_products p ON m.product_id = p.id
                WHERE ((sender_id = $my_id AND receiver_id = $other_id) 
                   OR (sender_id = $other_id AND receiver_id = $my_id))
                   AND m.type = 'market'
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
            'receiver_name' => $receiver_name,
            'last_read_id' => $last_read_id
        ]);
        exit();
    }

    // 2. Send Message
    if ($_GET['action'] == 'send_message' && isset($_POST['receiver_id'])) {
        $receiver_id = intval($_POST['receiver_id']);
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
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : NULL;
            $msg_type = 'market';
            $stmt = $con->prepare("INSERT INTO messages (sender_id, receiver_id, message, type, product_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iissi", $my_id, $receiver_id, $message, $msg_type, $product_id);
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
}

// Get list of users I have MARKET chats with.
$users = [];
$sql_users = "SELECT DISTINCT u.id, u.username, u.profile_picture 
              FROM users u 
              JOIN messages m ON (m.sender_id = u.id AND m.receiver_id = $my_id) OR (m.receiver_id = u.id AND m.sender_id = $my_id)
              WHERE m.type = 'market' AND u.id != $my_id
              ORDER BY m.created_at DESC";
$res_users = $con->query($sql_users);
while($u = $res_users->fetch_assoc()){
    $uid = $u['id'];
    $unread = $con->query("SELECT COUNT(*) as cnt FROM messages WHERE sender_id = $uid AND receiver_id = $my_id AND is_read = 0 AND type='market'")->fetch_assoc()['cnt'];
    $u['unread'] = $unread;
    $u['pic'] = !empty($u['profile_picture']) ? $u['profile_picture'] : 'uploads/profile.jpg';
    $users[] = $u;
}

$pageTitle = "Market Messages - Discovery Hub";
include("includes/layout_start.php");
?>

<div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 overflow-hidden h-[calc(100vh-140px)] flex flex-col md:flex-row relative">
    
    <!-- Sidebar -->
    <div id="messengerSidebar" class="w-full md:w-80 border-r border-slate-100 flex flex-col bg-slate-50/50 backdrop-blur-md transition-transform duration-300 z-20">
        <div class="p-6">
            <h2 class="text-2xl font-black text-slate-800 font-brand mb-4">Market Chats</h2>
            <div class="relative group">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" id="marketSearchInput" placeholder="Filter market chats..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-xs font-semibold focus:outline-none focus:border-blue-500 shadow-sm transition-all">
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-4 pb-6 space-y-1 custom-scrollbar" id="sidebarUserList">
            <?php foreach ($users as $u): ?>
                <button onclick="openChat(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['username'])) ?>', '<?= addslashes($u['pic']) ?>')" class="user-item w-full flex items-center gap-4 p-3 rounded-[1.25rem] hover:bg-white hover:shadow-sm border border-transparent hover:border-slate-100 transition-all text-left group">
                    <div class="relative">
                        <img src="<?= $u['pic'] ?>" class="w-12 h-12 rounded-2xl object-cover shadow-sm ring-2 ring-white" onerror="this.src='uploads/profile.jpg'">
                        <?php if($u['unread'] > 0): ?>
                            <div class="absolute -top-1 -right-1 bg-amber-500 text-white text-[10px] font-black px-1.5 py-0.5 rounded-lg border-2 border-white shadow-sm ring-1 ring-amber-500/20">
                                <?= $u['unread'] ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($u['username']) ?></h4>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Market Contact</p>
                    </div>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="flex-1 flex flex-col h-full bg-white relative">
        <!-- Placeholder -->
        <div id="noChat" class="absolute inset-0 z-10 bg-white flex flex-col items-center justify-center text-center p-10">
            <div class="w-24 h-24 bg-amber-50 rounded-[2rem] flex items-center justify-center text-amber-200 text-4xl mb-6 animate-bounce">
                <i class="fas fa-store"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800 mb-2">Market Negotiations</h3>
            <p class="text-slate-400 text-sm max-w-xs font-medium">Select a buyer or seller to discuss product details and finalize deals.</p>
        </div>

        <!-- Active Chat -->
        <div id="activeChat" class="hidden flex-col h-full opacity-0 translate-y-4 transition-all duration-300">
            <!-- Header -->
            <div class="p-6 border-bottom border-slate-100 flex items-center justify-between bg-white/80 backdrop-blur-md sticky top-0 z-10 shadow-sm border-b border-slate-50">
                <div class="flex items-center gap-4">
                    <button onclick="closeChat()" class="md:hidden w-10 h-10 flex items-center justify-center bg-slate-100 rounded-xl text-slate-500"><i class="fas fa-arrow-left"></i></button>
                    <div class="relative">
                        <img id="headerAvatar" src="uploads/profile.jpg" class="w-12 h-12 rounded-2xl object-cover shadow-sm ring-2 ring-white">
                    </div>
                    <div>
                        <h4 id="headerUsername" class="font-black text-slate-800 tracking-tight">John Doe</h4>
                        <p class="text-[9px] font-black uppercase tracking-widest text-amber-600">Active Market Session</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <button onclick="startVideoCall()" class="w-10 h-10 rounded-xl flex items-center justify-center text-blue-600 bg-blue-50 hover:bg-blue-600 hover:text-white transition-all"><i class="fas fa-video"></i></button>
                    <button onclick="deleteConversation()" class="w-10 h-10 rounded-xl flex items-center justify-center text-rose-600 bg-rose-50 hover:bg-rose-600 hover:text-white transition-all"><i class="fas fa-trash-alt"></i></button>
                </div>
            </div>

            <!-- Messages Stream -->
            <div class="flex-1 overflow-y-auto p-8 flex flex-col gap-6 custom-scrollbar scroll-smooth" id="messagesContainer">
                <!-- Injected via AJAX -->
            </div>

            <!-- Input Bar -->
            <div class="p-6 bg-white border-t border-slate-50">
                <div class="bg-slate-50 p-2 rounded-[2rem] flex items-center gap-2 shadow-inner group">
                    <div class="flex gap-1 pl-2">
                        <button onclick="triggerFile('image')" class="w-10 h-10 rounded-full flex items-center justify-center text-slate-400 hover:text-blue-500 hover:bg-blue-50 transition-all"><i class="fas fa-image"></i></button>
                        <button onclick="triggerFile('video')" class="w-10 h-10 rounded-full flex items-center justify-center text-slate-400 hover:text-indigo-500 hover:bg-indigo-50 transition-all"><i class="fas fa-clapperboard"></i></button>
                    </div>
                    
                    <input type="text" id="msgInput" placeholder="Message agent regarding item..." class="flex-1 bg-transparent border-none focus:ring-0 text-sm font-medium text-slate-700 px-4">
                    
                    <button id="voiceBtn" onclick="toggleRecording()" class="w-10 h-10 rounded-full flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-all"><i class="fas fa-microphone"></i></button>
                    
                    <div id="recordingStatus" class="hidden px-4 items-center gap-2">
                        <span class="w-2 h-2 bg-rose-500 rounded-full animate-ping"></span>
                        <span id="recTimer" class="text-[10px] font-black text-rose-500">00:00</span>
                    </div>

                    <button onclick="sendMessage()" class="w-12 h-12 rounded-full bg-amber-600 text-white flex items-center justify-center shadow-lg shadow-amber-500/30 hover:bg-amber-700 hover:scale-110 active:scale-95 transition-all">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                
                <div id="uploadProgress" class="hidden mt-4 h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-amber-600 animate-[progress_2s_infinite]"></div>
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
.user-item.active { @apply bg-amber-600 text-white shadow-xl shadow-amber-600/20 border-amber-500; }
.user-item.active h4 { @apply text-white; }
.user-item.active p { @apply text-amber-100; }
.user-item.active .ring-white { @apply ring-amber-500; }

.message-bubble { @apply px-5 py-3.5 rounded-[2rem] max-w-[85%] text-sm font-medium shadow-sm relative overflow-hidden; }
.received .message-bubble { @apply bg-slate-100 text-slate-800 rounded-tl-none border border-slate-200; }
.sent .message-bubble { @apply bg-amber-600 text-white rounded-tr-none shadow-lg shadow-amber-600/10; }
.sent .message-bubble .message-time { @apply text-amber-100; }
.received .message-bubble .message-time { @apply text-slate-400; }
.message-time { @apply text-[9px] font-black uppercase tracking-widest mt-2 block opacity-60; }

.message-media { @apply rounded-2xl w-full max-w-sm h-auto cursor-pointer hover:opacity-90 transition-all shadow-md mt-2; }
.product-ref { @apply bg-white/10 p-3 rounded-xl border border-white/10 mb-2 flex items-center gap-3; }

@keyframes progress { 0% { @apply -translate-x-full; } 100% { @apply translate-x-full; } }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    let currentUserId = null;
    let pollingInterval = null;
    let myId = <?= $my_id ?>;
    let pendingProductId = <?= isset($_GET['product_id']) ? intval($_GET['product_id']) : 'null' ?>;

    function openChat(uid, name, pic) {
        currentUserId = uid;
        $('#headerUsername').text(name);
        $('#headerAvatar').attr('src', pic);
        $('#noChat').addClass('hidden');
        $('#activeChat').removeClass('hidden').addClass('flex opacity-100 translate-y-0');
        
        $('.user-item').removeClass('active');
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
        $.get(`market_messages.php?action=fetch_chat&user_id=${currentUserId}`, function(res) {
            if(res.success) {
                let html = '';
                res.messages.forEach(msg => {
                    let display = msg.message;
                    
                    if(display.startsWith('[[IMAGE]]')) {
                        let path = display.replace('[[IMAGE]]', '');
                        display = `<img src="${path}" class="message-media" onclick="viewMedia('${path}', 'image')">`;
                    } else if(display.startsWith('[[VIDEO]]')) {
                        let path = display.replace('[[VIDEO]]', '');
                        display = `<video src="${path}" class="message-media" onclick="viewMedia('${path}', 'video')" muted></video>`;
                    } else if(display.startsWith('[[AUDIO]]')) {
                        let path = display.replace('[[AUDIO]]', '');
                        display = `<div class="bg-white/10 p-3 rounded-2xl"><audio src="${path}" controls class="h-8 max-w-[150px]"></audio></div>`;
                    } else if(display.includes('[[VIDEO_CALL]]')) {
                        let parts = display.split('|');
                        display = `<div class="p-4 bg-white/10 rounded-2xl flex items-center gap-4"><i class="fas fa-video text-2xl"></i><div><p class="text-xs font-black uppercase tracking-widest mb-1">Market Call</p><a href="meet.php?room=${parts[1]}" target="_blank" class="px-4 py-1.5 bg-white text-amber-600 rounded-lg font-bold text-[10px] uppercase tracking-widest">Join Call</a></div></div>`;
                    }

                    html += `
                    <div class="flex ${msg.type === 'sent' ? 'justify-end sent' : 'justify-start received'} group">
                        <div class="message-bubble">
                            ${(msg.product_id_ref && msg.type=='sent') ? `<div class="product-ref"><i class="fas fa-tag"></i> <span class="text-[9px] font-black uppercase tracking-widest">Ref: ${msg.product_title}</span></div>` : '' }
                            ${display}
                            <span class="message-time">${msg.time} <button onclick="deleteMsg(${msg.id})" class="ml-2 opacity-0 group-hover:opacity-100 transition-opacity"><i class="fas fa-trash-alt text-[8px]"></i></button></span>
                        </div>
                    </div>`;
                    
                    if(msg.type === 'sent' && msg.id == res.last_read_id) {
                        html += `<div class="flex justify-end -mt-4 mb-4"><img src="${res.receiver_pic}" class="w-3 h-3 rounded-full opacity-60"></div>`;
                    }
                });
                
                if($('#messagesContainer').html() !== html) {
                    $('#messagesContainer').html(html);
                    $('#messagesContainer').scrollTop($('#messagesContainer')[0].scrollHeight);

                    // Apply duration fix to all audio elements
                    $('#messagesContainer audio').each(function() {
                        let audio = this;
                        let fixAttempts = 0;
                        const maxAttempts = 50;
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

    function sendMessage() {
        let txt = $('#msgInput').val().trim();
        if(!txt || !currentUserId) return;
        let data = { receiver_id: currentUserId, message: txt };
        if(pendingProductId) { data.product_id = pendingProductId; pendingProductId = null; }
        
        $.post('market_messages.php?action=send_message', data, function(res) {
            if(res.success) { $('#msgInput').val(''); fetchMessages(); }
        });
    }

    function deleteMsg(id) { if(confirm('Delete this market record?')) $.post('market_messages.php?action=delete_message', { message_id: id }, fetchMessages); }
    function deleteConversation() { if(confirm('Wipe this negotiation history?')) $.post('market_messages.php?action=delete_conversation', { other_id: currentUserId }, () => { $('#messagesContainer').empty(); closeChat(); }); }

    function triggerFile(t) { $('#fileInput').data('type', t).val('').click(); }
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
                    startMarketUpload(file);
                }
            };
            video.src = URL.createObjectURL(file);
        } else {
            startMarketUpload(file);
        }
    }

    function startMarketUpload(file) {
        $('#uploadProgress').removeClass('hidden');
        let fd = new FormData();
        fd.append('receiver_id', currentUserId);
        fd.append('file', file);
        if(pendingProductId) { fd.append('product_id', pendingProductId); pendingProductId = null; }
        
        $.ajax({
            url: 'market_messages.php?action=send_message',
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

    let rec;
    let chunks = [];
    async function toggleRecording() {
        if (!rec || rec.state === 'inactive') {
            let s = await navigator.mediaDevices.getUserMedia({ audio: true });
            rec = new MediaRecorder(s);
            chunks = [];
            rec.ondataavailable = e => chunks.push(e.data);
            rec.onstop = () => {
                let b = new Blob(chunks, { type: 'audio/webm' });
                let fd = new FormData();
                fd.append('receiver_id', currentUserId);
                fd.append('file', b, 'v.webm');
                if(pendingProductId) { fd.append('product_id', pendingProductId); pendingProductId = null; }
                $.ajax({ url: 'market_messages.php?action=send_message', type: 'POST', data: fd, processData: false, contentType: false, success: fetchMessages });
                s.getTracks().forEach(t => t.stop());
            };
            rec.start();
            $('#voiceBtn').addClass('text-rose-500 animate-pulse').html('<i class="fas fa-stop"></i>');
            $('#recordingStatus').removeClass('hidden').addClass('flex');
        } else {
            rec.stop();
            $('#voiceBtn').removeClass('text-rose-500 animate-pulse').html('<i class="fas fa-microphone"></i>');
            $('#recordingStatus').addClass('hidden').removeClass('flex');
        }
    }

    function viewMedia(p, t) {
        $('#mediaModal').removeClass('hidden').addClass('flex');
        let html = t === 'image' ? `<img src="${p}" class="max-w-full max-h-[80vh]">` : `<video src="${p}" controls autoplay class="max-w-full max-h-[80vh]"></video>`;
        $('#modalContentContainer').html(html);
    }
    function closeMediaModal() { $('#mediaModal').addClass('hidden').removeClass('flex'); }

    function startVideoCall() { if(!currentUserId) return; let r = 'market_' + Math.min(myId, currentUserId) + '_' + Math.max(myId, currentUserId); sendMessage(`[[VIDEO_CALL]]|${r}|Established Connection`); window.open(`meet.php?room=${r}`, '_blank'); }

    $('#msgInput').on('keypress', e => { if(e.key==='Enter') sendMessage(); });

    // Auto open if user_id passed
    const urlParams = new URLSearchParams(window.location.search);
    const autoId = urlParams.get('user_id');
    if(autoId) {
        setTimeout(() => {
            let item = $(`.user-item[onclick*="openChat(${autoId},"]`);
            if(item.length) item.click();
        }, 500);
    }
</script>

<?php include("includes/layout_end.php"); ?>