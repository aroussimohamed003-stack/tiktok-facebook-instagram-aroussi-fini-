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
                    <button onclick="toggleLike(this, <?php echo $row['id']; ?>)" class="flex items-center gap-2 group transition-all">
                        <div class="w-10 h-10 rounded-2xl flex items-center justify-center transition-all <?php echo $row['liked_by_me'] ? 'bg-red-500 text-white shadow-lg shadow-red-200' : 'bg-white text-slate-400 shadow-sm ring-1 ring-slate-100 group-hover:bg-red-50 group-hover:text-red-500 group-hover:ring-red-100'; ?>">
                            <i class="<?php echo $row['liked_by_me'] ? 'fas' : 'far'; ?> fa-heart"></i>
                        </div>
                        <span class="text-xs font-black <?php echo $row['liked_by_me'] ? 'text-red-500' : 'text-slate-500'; ?> like-count"><?php echo $row['likes_count']; ?></span>
                    </button>
                    <button class="flex items-center gap-2 group transition-all" onclick="window.location.hash = 'post-<?php echo $row['id']; ?>'">
                        <div class="w-10 h-10 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-blue-50 group-hover:text-blue-500 group-hover:ring-blue-100 transition-all">
                            <i class="far fa-comment"></i>
                        </div>
                        <span class="text-xs font-black text-slate-500"><?php echo $row['comments_count']; ?></span>
                    </button>
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
        const iconDiv = btn.querySelector('div');
        const icon = btn.querySelector('i');
        const count = btn.querySelector('.like-count');
        
        count.textContent = data.count;
        
        if(data.action == 'liked') {
            icon.classList.replace('far', 'fas');
            iconDiv.className = 'w-10 h-10 rounded-2xl flex items-center justify-center transition-all bg-red-500 text-white shadow-lg shadow-red-200 scale-110';
            setTimeout(() => iconDiv.classList.remove('scale-110'), 200);
            count.className = 'text-xs font-black text-red-500 like-count';
        } else {
            icon.classList.replace('fas', 'far');
            iconDiv.className = 'w-10 h-10 rounded-2xl bg-white text-slate-400 shadow-sm ring-1 ring-slate-100 flex items-center justify-center transition-all';
            count.className = 'text-xs font-black text-slate-500 like-count';
        }
    })
    .catch(err => console.error('Error liking post:', err));
}
</script>

<?php include("includes/layout_end.php"); ?>