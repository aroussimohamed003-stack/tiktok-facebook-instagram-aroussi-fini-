<?php
session_start();
include "config.php";
$conn = $con;

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle Like/Comment Actions via AJAX/POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action'])) {
    if ($_POST['ajax_action'] == 'like') {
        $post_id = intval($_POST['post_id']);
        $check = $conn->query("SELECT id FROM post_likes WHERE post_id = $post_id AND user_id = $user_id");
        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO post_likes (post_id, user_id) VALUES ($post_id, $user_id)");
            $action = 'liked';
            $p_query = $conn->query("SELECT user_id FROM posts WHERE id = $post_id");
            if ($p_row = $p_query->fetch_assoc()) {
                $recipient = $p_row['user_id'];
                $conn->query("INSERT INTO notifications (recipient_id, sender_id, type, post_id, created_at) VALUES ($recipient, $user_id, 'like', $post_id, NOW())");
            }
        } else {
            $conn->query("DELETE FROM post_likes WHERE post_id = $post_id AND user_id = $user_id");
            $action = 'unliked';
        }
        $cnt = $conn->query("SELECT COUNT(*) as c FROM post_likes WHERE post_id = $post_id")->fetch_assoc()['c'];
        header('Content-Type: application/json');
        echo json_encode(['action' => $action, 'count' => $cnt]);
        exit();
    }

    if ($_POST['ajax_action'] == 'add_comment') {
        $post_id = intval($_POST['post_id']);
        $comment = mysqli_real_escape_string($conn, $_POST['comment']);
        if (!empty($comment)) {
            $conn->query("INSERT INTO comments (post_id, user_id, comment) VALUES ($post_id, $user_id, '$comment')");
            // Notify post owner
            $p_query = $conn->query("SELECT user_id FROM posts WHERE id = $post_id");
            if ($p_row = $p_query->fetch_assoc()) {
                $recipient = $p_row['user_id'];
                if ($recipient != $user_id) {
                    $conn->query("INSERT INTO notifications (recipient_id, sender_id, type, post_id, created_at) VALUES ($recipient, $user_id, 'comment', $post_id, NOW())");
                }
            }
        }
        $cnt = $conn->query("SELECT COUNT(*) as c FROM comments WHERE post_id = $post_id")->fetch_assoc()['c'];
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'count' => $cnt]);
        exit();
    }

    if ($_POST['ajax_action'] == 'delete_comment') {
        $comment_id = intval($_POST['comment_id']);
        $post_id = intval($_POST['post_id']);
        // Check ownership
        $check = $conn->query("SELECT id FROM comments WHERE id = $comment_id AND (user_id = $user_id OR post_id IN (SELECT id FROM posts WHERE user_id = $user_id))");
        if ($check->num_rows > 0) {
            $conn->query("DELETE FROM comments WHERE id = $comment_id");
        }
        $cnt = $conn->query("SELECT COUNT(*) as c FROM comments WHERE post_id = $post_id")->fetch_assoc()['c'];
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'count' => $cnt]);
        exit();
    }
}

// Handle Fetching Comments via GET
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'get_comments') {
    $post_id = intval($_GET['post_id']);
    $res = $conn->query("SELECT comments.*, users.username, users.profile_picture FROM comments JOIN users ON comments.user_id = users.id WHERE post_id = $post_id ORDER BY created_at DESC");
    $comments = [];
    while ($r = $res->fetch_assoc()) {
        $r['is_mine'] = ($r['user_id'] == $user_id);
        $comments[] = $r;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'comments' => $comments]);
    exit();
}

// Handle Post Creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['content'])) {
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "uploads/posts/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $target_file = $target_dir . uniqid() . '.' . pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) $image_path = $target_file;
    }
    $privacy = $_POST['privacy'] ?? 'public';
    $conn->query("INSERT INTO posts (user_id, content, image_path, privacy) VALUES ($user_id, '$content', '$image_path', '$privacy')");
    header("Location: mo.php");
    exit();
}

// Handle Post Deletion
if (isset($_GET['delete_post'])) {
    $pid = intval($_GET['delete_post']);
    $conn->query("DELETE FROM posts WHERE id = $pid AND user_id = $user_id");
    header("Location: mo.php");
    exit();
}

include("includes/layout_start.php");
?>

<!-- Search Bar -->
<div class="mb-6">
    <div class="relative group">
        <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-slate-400 group-focus-within:text-blue-500 transition-colors">
            <i class="fas fa-search text-xs"></i>
        </span>
        <form action="search_users.php" method="GET">
            <input name="query" class="w-full bg-white border border-slate-200/60 rounded-2xl py-3 pl-11 pr-4 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none shadow-sm font-semibold placeholder:text-slate-400" placeholder="Search friends or posts..." type="text"/>
        </form>
    </div>
</div>

<!-- Create Post Card -->
<section class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-6 mb-8 transition-all hover:shadow-md">
    <form action="" method="post" enctype="multipart/form-data">
        <div class="flex gap-4">
            <div class="flex-shrink-0">
                <img src="<?php echo $_SESSION['profile_picture'] ?? 'uploads/profile.jpg'; ?>" class="w-12 h-12 rounded-2xl object-cover ring-4 ring-slate-50 shadow-sm" onerror="this.src='uploads/profile.jpg'">
            </div>
            <div class="flex-1">
                <textarea name="content" rows="3" class="w-full border-none focus:ring-0 text-slate-700 placeholder:text-slate-400 text-lg resize-none font-bold" placeholder="What's on your mind, <?php echo $_SESSION['username']; ?>?" required></textarea>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <label for="post-image" class="flex items-center gap-2 px-4 py-2.5 rounded-2xl hover:bg-slate-50 text-slate-600 cursor-pointer transition-all group active:scale-95">
                    <i class="fas fa-image text-emerald-500 group-hover:scale-110 transition-transform"></i>
                    <span class="text-xs font-bold uppercase tracking-wider">Photo</span>
                    <input type="file" id="post-image" name="image" class="hidden" accept="image/*">
                </label>
                <div class="relative">
                    <select name="privacy" class="appearance-none bg-slate-50 border-none rounded-xl py-2 pl-4 pr-10 text-[10px] font-bold text-slate-500 uppercase tracking-widest focus:ring-0 cursor-pointer hover:bg-slate-100 transition-colors">
                        <option value="public">🌍 Public</option>
                        <option value="private">🔒 Private</option>
                    </select>
                    <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-[8px] text-slate-400 pointer-events-none"></i>
                </div>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-2xl font-bold text-xs uppercase tracking-widest shadow-lg shadow-blue-200 hover:bg-blue-700 active:scale-95 transition-all">Post</button>
        </div>
    </form>
</section>

<!-- Posts Feed -->
<div class="space-y-8 pb-10">
    <?php
    $sql = "SELECT posts.*, users.username, users.profile_picture,
            (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) as likes_count,
            (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id AND post_likes.user_id = $user_id) as liked_by_me,
            (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) as comments_count
            FROM posts
            JOIN users ON posts.user_id = users.id
            WHERE posts.privacy = 'public' OR posts.user_id = $user_id
            ORDER BY posts.created_at DESC";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0):
        while ($row = $result->fetch_assoc()):
            $pp = !empty($row['profile_picture']) ? $row['profile_picture'] : 'uploads/profile.jpg';
    ?>
        <article class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden post-card transition-all hover:shadow-lg hover:shadow-slate-100" id="post-<?php echo $row['id']; ?>">
            <!-- Header -->
            <div class="p-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="profile.php?user_id=<?php echo $row['user_id']; ?>" class="relative group">
                        <img src="<?php echo $pp; ?>" class="w-12 h-12 rounded-2xl object-cover ring-4 ring-slate-50 shadow-sm transition-transform group-hover:scale-105" onerror="this.src='uploads/profile.jpg'">
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 border-2 border-white rounded-full"></div>
                    </a>
                    <div>
                        <h4 class="font-bold text-slate-900 text-[15px] hover:text-blue-600 transition-colors">
                            <a href="profile.php?user_id=<?php echo $row['user_id']; ?>"><?php echo htmlspecialchars($row['username']); ?></a>
                        </h4>
                        <p class="text-[10px] font-bold text-slate-400 mt-0.5 uppercase tracking-widest">
                            <i class="far fa-clock mr-1 text-blue-500/50"></i> <?php echo date('M d, h:i A', strtotime($row['created_at'])); ?>
                        </p>
                    </div>
                </div>
                <?php if ($row['user_id'] == $user_id): ?>
                    <button class="p-2.5 rounded-2xl h-10 w-10 flex items-center justify-center hover:bg-red-50 text-slate-300 hover:text-red-500 transition-all active:scale-90" onclick="if(confirm('Delete post permanentely?')) window.location='mo.php?delete_post=<?php echo $row['id']; ?>'">
                        <i class="fas fa-trash-alt text-sm"></i>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Content -->
            <div class="px-7 pb-5">
                <p class="text-slate-700 leading-relaxed text-[16px] whitespace-pre-wrap font-semibold"><?php echo htmlspecialchars($row['content']); ?></p>
            </div>
            
            <?php if (!empty($row['image_path'])): ?>
                <div class="px-4 pb-4">
                    <div class="rounded-[2rem] overflow-hidden bg-slate-50 border border-slate-100/30 cursor-pointer" onclick="openModal('<?php echo $row['image_path']; ?>')">
                        <img src="<?php echo $row['image_path']; ?>" class="w-full h-auto object-cover max-h-[600px] transition-transform duration-700 hover:scale-[1.02]" alt="Post image">
                    </div>
                </div>
            <?php endif; ?>

            <!-- Interactions -->
            <div class="px-6 py-5 border-t border-slate-50/50 bg-slate-50/20 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 group transition-all">
                        <button onclick="toggleLike(this, <?php echo $row['id']; ?>)" class="w-10 h-10 rounded-2xl flex items-center justify-center transition-all <?php echo $row['liked_by_me'] ? 'bg-red-500 text-white shadow-lg shadow-red-200' : 'bg-white text-slate-400 shadow-sm ring-1 ring-slate-100 group-hover:bg-red-50 group-hover:text-red-500 group-hover:ring-red-100'; ?>">
                            <i class="<?php echo $row['liked_by_me'] ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                        <span class="text-xs font-black <?php echo $row['liked_by_me'] ? 'text-red-500' : 'text-slate-500'; ?> transition-colors cursor-pointer hover:underline like-count" onclick="openPostLikes(<?php echo $row['id']; ?>)"><?php echo $row['likes_count']; ?></span>
                    </div>
                    <div class="flex items-center gap-2 group transition-all">
                        <button onclick="openPostComments(<?php echo $row['id']; ?>)" class="w-10 h-10 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-blue-50 group-hover:text-blue-500 group-hover:ring-blue-100 transition-all">
                            <i class="far fa-comment"></i>
                        </button>
                        <span class="text-xs font-black text-slate-500 cursor-pointer hover:underline comments-count-<?php echo $row['id']; ?>" onclick="openPostComments(<?php echo $row['id']; ?>)"><?php echo $row['comments_count']; ?></span>
                    </div>
                </div>
                <button onclick="sharePost('Post by <?php echo addslashes($row['username']); ?>', '<?php echo addslashes(mb_substr($row['content'], 0, 50)); ?>...', window.location.origin + window.location.pathname + '#post-<?php echo $row['id']; ?>')" class="w-10 h-10 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 flex items-center justify-center text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 hover:ring-indigo-100 transition-all active:scale-95">
                    <i class="fas fa-share-nodes"></i>
                </button>
            </div>
        </article>
    <?php endwhile; ?>
    <?php else: ?>
        <div class="bg-white rounded-[3rem] p-16 text-center border border-slate-100 border-dashed border-2">
            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-newspaper text-slate-200 text-3xl"></i>
            </div>
            <h3 class="text-slate-900 font-bold text-xl mb-2">The feed is quiet</h3>
            <p class="text-slate-400 text-sm font-medium">Be the first to share your thoughts with the community!</p>
        </div>
    <?php endif; ?>
</div>

<!-- Post Likes Modal -->
<div id="likes-modal" class="fixed inset-0 z-[100] hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] w-full max-w-md overflow-hidden shadow-2xl">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-900">Likers</h3>
            <button onclick="closeLikesModal()" class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 hover:bg-red-50 hover:text-red-500 transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="likes-list" class="max-h-[60vh] overflow-y-auto p-2">
            <!-- Likers will be loaded here -->
        </div>
    </div>
</div>

<!-- Post Comments Modal -->
<div id="comments-modal" class="fixed inset-0 z-[100] hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] w-full max-w-md overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-900">Comments</h3>
            <button onclick="closeCommentsModal()" class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 hover:bg-red-50 hover:text-red-500 transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="comments-list" class="flex-1 overflow-y-auto p-4 space-y-4">
            <!-- Comments will be loaded here -->
        </div>
        <div class="p-4 border-t border-slate-100 bg-slate-50">
            <div class="flex gap-2">
                <input type="text" id="new-comment-input" class="flex-1 bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all" placeholder="Write a comment...">
                <button onclick="sendComment()" class="bg-blue-600 text-white w-10 h-10 rounded-xl flex items-center justify-center shadow-lg shadow-blue-200 hover:bg-blue-700 active:scale-95 transition-all">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox Modal -->
<div id="image-modal" class="fixed inset-0 z-[100] hidden bg-black/95 backdrop-blur-xl flex items-center justify-center p-4">
    <button onclick="closeModal()" class="absolute top-6 right-6 w-12 h-12 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20 transition-all">
        <i class="fas fa-times text-xl"></i>
    </button>
    <img id="modal-img" src="" class="max-w-full max-h-full rounded-2xl shadow-2xl object-contain">
</div>

<script>
function openModal(src) {
    const modal = document.getElementById('image-modal');
    const img = document.getElementById('modal-img');
    img.src = src;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('image-modal');
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

function sharePost(title, text, url) {
    if (navigator.share) {
        navigator.share({
            title: title,
            text: text,
            url: url || window.location.href
        }).catch(err => console.log('Share failed:', err));
    } else {
        copyToClipboard(url || window.location.href);
    }
}

function copyToClipboard(text) {
    const dummy = document.createElement('input');
    document.body.appendChild(dummy);
    dummy.value = text;
    dummy.select();
    document.execCommand('copy');
    document.body.removeChild(dummy);
    alert('Link copied to clipboard!');
}

function toggleLike(btn, postId) {
    const formData = new FormData();
    formData.append('ajax_action', 'like');
    formData.append('post_id', postId);

    fetch('mo.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const iconDiv = btn.querySelector('div') || btn; // Handle different structures
        const icon = btn.querySelector('i');
        const count = btn.closest('.group').querySelector('.like-count');
        
        count.textContent = data.count;
        
        if(data.action == 'liked') {
            icon.classList.replace('far', 'fas');
            if (iconDiv.tagName === 'DIV') {
                iconDiv.className = 'w-10 h-10 rounded-2xl flex items-center justify-center transition-all bg-red-500 text-white shadow-lg shadow-red-200 scale-110';
            } else {
                btn.className = 'w-10 h-10 rounded-2xl flex items-center justify-center transition-all bg-red-500 text-white shadow-lg shadow-red-200 scale-110';
            }
            setTimeout(() => (iconDiv.tagName === 'DIV' ? iconDiv : btn).classList.remove('scale-110'), 200);
            count.className = 'text-xs font-black text-red-500 like-count';
        } else {
            icon.classList.replace('fas', 'far');
            if (iconDiv.tagName === 'DIV') {
                iconDiv.className = 'w-10 h-10 rounded-2xl bg-white text-slate-400 shadow-sm ring-1 ring-slate-100 flex items-center justify-center transition-all';
            } else {
                btn.className = 'w-10 h-10 rounded-2xl bg-white text-slate-400 shadow-sm ring-1 ring-slate-100 flex items-center justify-center transition-all';
            }
            count.className = 'text-xs font-black text-slate-500 like-count';
        }
    })
    .catch(err => console.error('Error liking post:', err));
}

// --- Post Likes Logic ---
function openPostLikes(postId) {
    const modal = document.getElementById('likes-modal');
    const list = document.getElementById('likes-list');
    list.innerHTML = '<div class="text-center p-4 text-slate-400">Loading...</div>';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    fetch(`get_likers.php?post_id=${postId}&theme=light`)
    .then(r => r.text())
    .then(html => {
        list.innerHTML = html;
        // Adjust styling of the injected components for the modern mo.php UI
        list.querySelectorAll('li').forEach(li => {
            li.style.border = 'none';
            li.style.padding = '8px 12px';
            li.className = 'hover:bg-slate-50 rounded-2xl transition-colors';
        });
    });
}

function closeLikesModal() {
    document.getElementById('likes-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

// --- Post Comments Logic ---
let currentPostId = null;
function openPostComments(postId) {
    currentPostId = postId;
    const modal = document.getElementById('comments-modal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    loadComments(postId);
}

function closeCommentsModal() {
    document.getElementById('comments-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

function loadComments(postId) {
    const list = document.getElementById('comments-list');
    list.innerHTML = '<div class="text-center p-4 text-slate-400">Loading comments...</div>';

    fetch(`mo.php?ajax_action=get_comments&post_id=${postId}`)
    .then(r => r.json())
    .then(data => {
        if (data.comments.length === 0) {
            list.innerHTML = '<div class="text-center p-8 text-slate-400 flex flex-col items-center gap-3"><i class="far fa-comment-dots text-4xl"></i><p class="font-bold">No comments yet</p></div>';
            return;
        }
        
        list.innerHTML = data.comments.map(c => `
            <div class="flex gap-3 group/comment">
                <img src="${c.profile_picture || 'uploads/profile.jpg'}" class="w-10 h-10 rounded-xl object-cover ring-2 ring-slate-50" onerror="this.src='uploads/profile.jpg'">
                <div class="flex-1">
                    <div class="bg-slate-50 rounded-2xl px-4 py-3 relative">
                        <div class="flex items-center justify-between mb-1">
                            <h5 class="font-bold text-black text-xs">${c.username}</h5>
                            ${c.is_mine ? `<button onclick="deleteComment(${c.id})" class="text-slate-300 hover:text-red-500 opacity-0 group-hover/comment:opacity-100 transition-all"><i class="fas fa-trash-alt text-[10px]"></i></button>` : ''}
                        </div>
                        <p class="text-black text-xs leading-relaxed font-semibold">${c.comment}</p>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400 mt-1 ml-2 block">${new Date(c.created_at).toLocaleDateString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'})}</span>
                </div>
            </div>
        `).join('');
    });
}

function sendComment() {
    const input = document.getElementById('new-comment-input');
    const text = input.value.trim();
    if (!text || !currentPostId) return;

    const formData = new FormData();
    formData.append('ajax_action', 'add_comment');
    formData.append('post_id', currentPostId);
    formData.append('comment', text);

    fetch('mo.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        input.value = '';
        loadComments(currentPostId);
        // Update count on page
        const countSpan = document.querySelector(`.comments-count-${currentPostId}`);
        if (countSpan) countSpan.textContent = data.count;
    });
}

function deleteComment(commentId) {
    if (!confirm('Delete this comment?')) return;
    
    const formData = new FormData();
    formData.append('ajax_action', 'delete_comment');
    formData.append('comment_id', commentId);
    formData.append('post_id', currentPostId);

    fetch('mo.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        loadComments(currentPostId);
        const countSpan = document.querySelector(`.comments-count-${currentPostId}`);
        if (countSpan) countSpan.textContent = data.count;
    });
}
</script>

<?php include("includes/layout_end.php"); ?>