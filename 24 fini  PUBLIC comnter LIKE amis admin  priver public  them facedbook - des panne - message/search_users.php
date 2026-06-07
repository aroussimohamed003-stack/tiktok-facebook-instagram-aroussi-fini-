<?php
session_start();
include("config.php");
include("includes/auto_delete.php");

$isLoggedIn = isset($_SESSION['user_id']);
$my_id = $isLoggedIn ? $_SESSION['user_id'] : 0;

$query = isset($_GET['query']) ? mysqli_real_escape_string($con, $_GET['query']) : '';

$pageTitle = "Search Results: " . htmlspecialchars($query);
include("includes/layout_start.php");
?>

<div class="max-w-3xl mx-auto py-8">
    <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 overflow-hidden">
        <div class="bg-gradient-to-r from-slate-900 via-blue-900 to-indigo-900 p-8 pb-12">
            <div class="flex items-center gap-4 mb-2">
                <div class="w-12 h-12 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center text-white">
                    <i class="fas fa-search"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-black text-white font-brand">Discovery</h2>
                    <p class="text-white/60 text-[10px] font-bold uppercase tracking-widest">Searching the high-fidelity network</p>
                </div>
            </div>
            <h3 class="text-white font-medium text-lg mt-4">Showing results for "<span class="text-blue-400 font-bold"><?= htmlspecialchars($query) ?></span>"</h3>
        </div>

        <div class="p-8 -mt-6 bg-white rounded-t-[2.5rem]">
            <?php
            if (!empty($query)) {
                $sql = "SELECT id, username, profile_picture FROM users WHERE (username LIKE '%$query%') AND id != $my_id LIMIT 50";
                $result = mysqli_query($con, $sql);

                if (mysqli_num_rows($result) > 0) {
                    while ($user = mysqli_fetch_assoc($result)) {
                        $uid = $user['id'];
                        $pic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'uploads/profile.jpg';
                        
                        // Check friendship status
                        $status = 'none';
                        if ($isLoggedIn) {
                            $check = mysqli_query($con, "SELECT id, status, sender_id FROM friends WHERE (sender_id = $my_id AND receiver_id = $uid) OR (sender_id = $uid AND receiver_id = $my_id)");
                            if ($row = mysqli_fetch_assoc($check)) {
                                if ($row['status'] == 'accepted') {
                                    $status = 'friend';
                                } elseif ($row['sender_id'] == $my_id) {
                                    $status = 'sent';
                                } else {
                                    $status = 'received';
                                }
                            }
                        }
                        ?>
                        <div class="flex items-center justify-between p-4 mb-3 bg-slate-50 hover:bg-slate-100/80 rounded-3xl border border-slate-100 transition-all group">
                            <div class="flex items-center gap-4">
                                <div class="relative">
                                    <img src="<?= $pic ?>" class="w-16 h-16 rounded-2xl object-cover shadow-sm ring-2 ring-white" onerror="this.src='uploads/profile.jpg'">
                                    <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-emerald-500 border-2 border-white rounded-full"></div>
                                </div>
                                <div>
                                    <h4 class="font-black text-slate-800 tracking-tight group-hover:text-blue-600 transition-colors"><?= htmlspecialchars($user['username']) ?></h4>
                                    <a href="profile.php?user_id=<?= $uid ?>" class="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-blue-500 transition-colors">View Hub</a>
                                </div>
                            </div>

                            <div id="status-btn-<?= $uid ?>">
                                <?php if ($isLoggedIn): ?>
                                    <?php if ($status == 'none'): ?>
                                        <button onclick="friendAction(<?= $uid ?>, 'add')" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-bold text-xs hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/10 flex items-center gap-2">
                                            <i class="fas fa-plus"></i> Connect
                                        </button>
                                    <?php elseif ($status == 'sent'): ?>
                                        <button class="px-6 py-2.5 bg-slate-200 text-slate-500 rounded-xl font-bold text-xs cursor-not-allowed flex items-center gap-2">
                                            <i class="fas fa-clock"></i> Pending
                                        </button>
                                    <?php elseif ($status == 'received'): ?>
                                        <button onclick="friendAction(<?= $uid ?>, 'accept')" class="px-6 py-2.5 bg-emerald-600 text-white rounded-xl font-bold text-xs hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-500/10 flex items-center gap-2">
                                            <i class="fas fa-check"></i> Accept
                                        </button>
                                    <?php elseif ($status == 'friend'): ?>
                                        <div class="flex gap-2">
                                            <a href="message.php?user_id=<?= $uid ?>" class="w-10 h-10 flex items-center justify-center bg-white border border-slate-100 text-blue-600 rounded-xl shadow-sm hover:scale-105 transition-transform"><i class="fas fa-comment"></i></a>
                                            <button class="px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl font-bold text-xs flex items-center gap-2 cursor-default"><i class="fas fa-check-circle text-blue-500"></i> Connected</button>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    ?>
                    <div class="text-center py-16">
                        <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-200">
                            <i class="fas fa-user-astronaut text-4xl"></i>
                        </div>
                        <h4 class="text-xl font-black text-slate-800 mb-2">Ghost Hub</h4>
                        <p class="text-slate-500 text-sm max-w-xs mx-auto">No humanoids matches the signal "<?= htmlspecialchars($query) ?>". Try adjusting your frequency.</p>
                    </div>
                    <?php
                }
            } else {
                ?>
                <div class="text-center py-16">
                    <div class="w-24 h-24 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-6 text-blue-200">
                        <i class="fas fa-radar text-4xl animate-pulse"></i>
                    </div>
                    <h4 class="text-xl font-black text-slate-800 mb-2">Scanning...</h4>
                    <p class="text-slate-500 text-sm max-w-xs mx-auto">Enter a handle or name to scan the high-fidelity social sphere.</p>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
</div>

<script>
function friendAction(userId, action) {
    const btnContainer = document.getElementById('status-btn-' + userId);
    const originalHtml = btnContainer.innerHTML;
    btnContainer.innerHTML = '<div class="w-8 h-8 rounded-full border-4 border-blue-50 border-t-blue-600 animate-spin mx-auto"></div>';
    
    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('action', action);
    
    fetch('friend_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            btnContainer.innerHTML = originalHtml;
            alert(data.error || 'Connection error');
        }
    })
    .catch(err => {
        btnContainer.innerHTML = originalHtml;
        alert('Server unreachable');
    });
}
</script>

<?php
include("includes/layout_end.php");
?>
