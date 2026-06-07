<?php
session_start();
include("config.php");

if (!isset($_GET['id'])) {
    header("Location: marketplace.php");
    exit();
}

$p_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'] ?? 0;

// Increment View
$con->query("UPDATE market_products SET views = views + 1 WHERE id = $p_id");

// Fetch Product
$stmt = $con->prepare("
    SELECT p.*, u.username, u.profile_picture, u.id as seller_id, c.name as cat_name 
    FROM market_products p
    JOIN users u ON p.user_id = u.id
    JOIN market_categories c ON p.category_id = c.id
    WHERE p.id = ?");
$stmt->bind_param("i", $p_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    echo "Product not found";
    exit();
}

// Fetch Seller Rating
$seller_id = $product['seller_id'];
$rating_res = $con->query("SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM market_reviews WHERE seller_id = $seller_id");
$rating_data = $rating_res->fetch_assoc();
$avg_rating = round(($rating_data['avg_rating'] ?? 0), 1);
$count_rating = $rating_data['count'];

// Fetch Images
$imgs = $con->query("SELECT * FROM market_product_images WHERE product_id = $p_id");
$images = [];
while($r = $imgs->fetch_assoc()) $images[] = $r;

// Check if following
$is_following = false;
if($user_id > 0) {
    $check_follow = $con->query("SELECT id FROM market_store_followers WHERE store_id = {$product['seller_id']} AND follower_id = $user_id");
    if ($check_follow->num_rows > 0) $is_following = true;
}

$pageTitle = htmlspecialchars($product['title']) . " - Discovery Hub";
include("includes/layout_start.php");
?>

<div class="max-w-5xl mx-auto py-8">
    <div class="bg-white rounded-[3rem] shadow-2xl overflow-hidden border border-slate-100 mb-8">
        <div class="grid grid-cols-1 lg:grid-cols-2">
            <!-- Left: Visuals -->
            <div class="p-8 bg-slate-50">
                <div class="aspect-square rounded-[2.5rem] overflow-hidden bg-white shadow-inner mb-6 relative group">
                    <?php if(!empty($images)): ?>
                        <img id="mainImage" src="<?= $images[0]['image_path'] ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                    <?php else: ?>
                        <img id="mainImage" src="images/no-product.png" class="w-full h-full object-cover opacity-20 p-20 grayscale">
                    <?php endif; ?>
                    
                    <div class="absolute top-6 left-6 bg-slate-900/80 backdrop-blur-md px-4 py-2 rounded-2xl text-white font-black text-sm shadow-xl">
                        <span id="priceDisplay">
                            <?php 
                                $pCurr = $product['currency'] ?? 'USD';
                                $symbol = $pCurr === 'USD' ? '$' : ($pCurr === 'TND' ? 'د.ت' : '€');
                                echo $symbol . number_format($product['price'], 2);
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="flex gap-4 overflow-x-auto pb-2 scrollbar-hide no-scrollbar">
                    <?php foreach($images as $img): ?>
                        <button onclick="document.getElementById('mainImage').src = this.querySelector('img').src" class="w-20 h-20 rounded-2xl overflow-hidden border-2 border-transparent hover:border-blue-500 transition-all flex-shrink-0 bg-white shadow-sm active:scale-95">
                            <img src="<?= $img['image_path'] ?>" class="w-full h-full object-cover">
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right: Details -->
            <div class="p-10 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="px-4 py-1.5 bg-blue-50 text-blue-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-blue-100">
                            <?= $product['cat_name'] ?>
                        </span>
                        <div class="flex items-center gap-3 text-slate-400 text-xs font-bold">
                            <span><i class="fas fa-eye me-1"></i> <?= $product['views'] ?> views</span>
                            <button class="hover:text-blue-500 transition-colors"><i class="fas fa-share-alt"></i></button>
                        </div>
                    </div>
                    
                    <h1 class="text-3xl font-black text-slate-800 tracking-tight mb-4"><?= htmlspecialchars($product['title']) ?></h1>
                    
                    <div class="flex items-center gap-3 text-slate-500 text-sm font-medium mb-8 p-3 bg-slate-50 rounded-2xl inline-flex">
                        <i class="fas fa-map-marker-alt text-blue-500"></i>
                        <span><?= htmlspecialchars($product['location'] ?: 'Discovery Hub') ?></span>
                    </div>

                    <div class="space-y-4 mb-10">
                        <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Story & Details</h4>
                        <p class="text-slate-600 leading-relaxed font-medium">
                            <?= nl2br(htmlspecialchars($product['description'])) ?>
                        </p>
                    </div>
                </div>

                <div class="space-y-6">
                    <!-- Seller Card (Elite Edition) -->
                    <div class="p-6 bg-slate-50 rounded-[2rem] border border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="relative">
                                <img src="<?= !empty($product['profile_picture']) ? $product['profile_picture'] : 'uploads/profile.jpg' ?>" class="w-14 h-14 rounded-2xl object-cover shadow-sm ring-4 ring-white">
                                <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-emerald-500 border-2 border-white rounded-full"></div>
                            </div>
                            <div>
                                <h5 class="font-black text-slate-800"><?= htmlspecialchars($product['username']) ?></h5>
                                <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400">
                                    <div class="flex text-amber-400">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <span class="text-slate-800"><?= $avg_rating ?></span>
                                    <span>(<?= $count_rating ?> reviews)</span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($product['seller_id'] != $user_id): ?>
                            <div class="flex gap-2">
                                <button onclick="openRateModal()" class="w-10 h-10 flex items-center justify-center bg-white rounded-xl text-slate-400 hover:text-amber-500 hover:shadow-md transition-all border border-slate-100">
                                    <i class="fas fa-star"></i>
                                </button>
                                <button id="followBtn" onclick="toggleFollow()" class="px-5 py-2.5 <?= $is_following ? 'bg-slate-200 text-slate-600' : 'bg-slate-900 text-white shadow-lg shadow-slate-900/10' ?> rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                                    <?= $is_following ? 'Following' : 'Follow' ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex gap-4">
                        <?php if ($product['seller_id'] == $user_id): ?>
                            <button onclick="deleteProduct()" class="flex-1 py-4 bg-rose-50 text-rose-600 border border-rose-100 rounded-2xl font-black text-xs hover:bg-rose-600 hover:text-white transition-all">
                                <i class="fas fa-trash-alt me-2"></i> Delete Discovery
                            </button>
                        <?php else: ?>
                            <a href="market_messages.php?user_id=<?= $product['seller_id'] ?>&product_id=<?= $p_id ?>" class="flex-1 py-4 bg-blue-600 text-white rounded-[1.5rem] font-black text-sm text-center shadow-xl shadow-blue-500/20 hover:bg-blue-700 transition-all active:scale-[0.98]">
                                <i class="fas fa-comment-dots me-2"></i> Connect with Seller
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rating Modal (Modern Dark) -->
<div id="rateModal" class="fixed inset-0 z-[100] hidden bg-slate-900/60 backdrop-blur-sm items-center justify-center p-4">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-xs overflow-hidden text-center animate-in zoom-in-95 duration-200">
        <div class="p-8">
            <h3 class="text-xl font-black text-slate-800 mb-2">Rate Agent</h3>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-6">How was your interaction?</p>
            
            <div class="flex justify-center gap-2 mb-8">
                <?php for($i=1; $i<=5; $i++): ?>
                    <i class="fas fa-star rate-star text-3xl text-slate-100 cursor-pointer hover:scale-110 transition-transform" data-val="<?= $i ?>" onclick="setRating(<?= $i ?>)"></i>
                <?php endfor; ?>
            </div>
            
            <textarea id="rateComment" rows="3" placeholder="Elite feedback only..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 font-medium text-sm mb-6"></textarea>
            
            <div class="grid grid-cols-2 gap-3">
                <button onclick="submitRating()" class="py-3 bg-slate-900 text-white rounded-xl font-black text-xs">Submit</button>
                <button onclick="closeRateModal()" class="py-3 bg-slate-100 text-slate-400 rounded-xl font-black text-xs">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    let currentRating = 0;

    function deleteProduct() {
        if(confirm("Confirm deletion of this discovery? This cannot be undone.")) {
            $.post('market_action.php', { action: 'delete_product', product_id: <?= $p_id ?> }, function(res){
                try {
                    res = JSON.parse(res);
                    if(res.success) window.location.href = 'marketplace.php';
                    else alert(res.message);
                } catch(e) { alert("Server error"); }
            });
        }
    }

    function toggleFollow() {
         $.post('market_action.php', {
             action: 'toggle_follow',
             store_id: <?= $product['seller_id'] ?>
         }, function(res) {
             try {
                res = JSON.parse(res);
                if(res.success) {
                    let btn = $('#followBtn');
                    if(res.data.status === 'followed') {
                        btn.text('Following').removeClass('bg-slate-900 text-white shadow-lg shadow-slate-900/10').addClass('bg-slate-200 text-slate-600');
                    } else {
                        btn.text('Follow').addClass('bg-slate-900 text-white shadow-lg shadow-slate-900/10').removeClass('bg-slate-200 text-slate-600');
                    }
                } else { alert(res.message); }
             } catch(e) { }
         });
    }

    function openRateModal() { $('#rateModal').removeClass('hidden').addClass('flex'); }
    function closeRateModal() { $('#rateModal').addClass('hidden').removeClass('flex'); }
    
    function setRating(val) {
        currentRating = val;
        $('.rate-star').each(function(idx) {
            if (idx < val) $(this).removeClass('text-slate-100').addClass('text-amber-400');
            else $(this).addClass('text-slate-100').removeClass('text-amber-400');
        });
    }

    function submitRating() {
        if (currentRating === 0) return alert("Select a rating level");
        let comm = $('#rateComment').val();
        $.post('market_action.php', { 
            action: 'submit_review', 
            seller_id: <?= $seller_id ?>, 
            rating: currentRating, 
            comment: comm 
        }, function(res) {
            try {
                res = JSON.parse(res);
                if(res.success) location.reload();
                else alert(res.message);
            } catch(e) { }
        });
    }
</script>

<?php
include("includes/layout_end.php");
?>
