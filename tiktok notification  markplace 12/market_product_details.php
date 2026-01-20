<?php
session_start();
include("config.php");

if (!isset($_GET['id'])) {
    header("Location: marketplace.php");
    exit();
}

$p_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

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
$check_follow = $con->query("SELECT id FROM market_store_followers WHERE store_id = {$product['seller_id']} AND follower_id = $user_id");
if ($check_follow->num_rows > 0) $is_following = true;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['title']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="market.css">
    <style>
        .details-container { padding: 20px; max-width: 800px; margin: 0 auto; }
        .img-gallery { display: flex; overflow-x: auto; gap: 10px; margin-bottom: 20px; height: 300px; }
        .img-gallery img { height: 100%; border-radius: 10px; flex-shrink: 0; }
        .seller-card { background: #333; padding: 15px; border-radius: 10px; display: flex; align-items: center; justify-content: space-between; margin-top: 20px; }
        .seller-info { display: flex; align-items: center; gap: 10px; }
        .seller-pic { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; }
        .action-btns { margin-top: 20px; display: flex; gap: 10px; }
    </style>
</head>
<body class="market-page">

    <div class="market-header">
        <a href="marketplace.php" style="color:white;"><i class="fas fa-arrow-left fa-lg"></i></a>
        <span>Detail</span>
        <i class="fas fa-share-alt fa-lg" style="color:white;"></i>
    </div>

    <div class="details-container">
        <!-- Images -->
        <div class="img-gallery">
            <?php foreach($images as $img): ?>
                <img src="<?= $img['image_path'] ?>" onclick="window.open(this.src)">
            <?php endforeach; ?>
            <?php if(empty($images)): ?>
                <img src="images/no-product.png">
            <?php endif; ?>
        </div>

        <h1><?= htmlspecialchars($product['title']) ?></h1>
        <h2 style="color: var(--market-primary);">$<?= htmlspecialchars($product['price']) ?></h2>
        
        <div style="color:#888; margin: 10px 0;">
            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($product['location']) ?> • 
            <i class="fas fa-eye"></i> <?= $product['views'] ?> views • 
            <span><?= $product['cat_name'] ?></span>
        </div>

        <p style="line-height: 1.6; color: #ddd;">
            <?= nl2br(htmlspecialchars($product['description'])) ?>
        </p>

        <!-- Seller Section -->
        <div class="seller-card">
            <div class="seller-info">
                <img src="<?= !empty($product['profile_picture']) ? $product['profile_picture'] : 'uploads/profile.jpg' ?>" class="seller-pic">
                <div>
                    <div style="font-weight:bold;"><?= htmlspecialchars($product['username']) ?></div>
                    <div style="font-size:0.8em; color:#ccc;">
                        <i class="fas fa-star" style="color:gold;"></i> <?= $avg_rating ?> (<?= $count_rating ?> reviews)
                    </div>
                </div>
            </div>
            <?php if ($product['seller_id'] != $user_id): ?>
                <div style="display:flex; gap:5px;">
                    <button class="btn btn-sm" onclick="openRateModal()" style="background:#444; border:1px solid #555; padding: 5px 10px; border-radius: 20px; color: white; cursor:pointer;" title="Rate User">
                        <i class="fas fa-star"></i>
                    </button>
                    <button class="btn btn-sm" id="followBtn" onclick="toggleFollow()" 
                        style="background: <?= $is_following ? '#333' : 'var(--market-primary)' ?>; border:1px solid #555; padding: 5px 15px; border-radius: 20px; color: white; cursor:pointer;">
                        <?= $is_following ? 'Following' : 'Follow' ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <div class="action-btns">
            <?php if ($product['seller_id'] == $user_id): ?>
                <button class="btn-primary" style="background:#444;" onclick="deleteProduct()">Delete Item</button>
            <?php else: ?>
                <a href="market_messages.php?user_id=<?= $product['seller_id'] ?>&product_id=<?= $p_id ?>" class="btn-primary" style="text-align:center; text-decoration:none;">
                    <i class="fas fa-comment-dots"></i> Message Seller
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rating Modal -->
    <div class="modal-overlay" id="rateModal">
        <div class="modal-content" style="max-width: 300px; text-align: center;">
            <h3 style="margin-top:0;">Rate Seller</h3>
            <div style="font-size: 30px; margin: 15px 0;">
                <i class="fas fa-star rate-star" data-val="1" onclick="setRating(1)" style="color:#444; cursor:pointer;"></i>
                <i class="fas fa-star rate-star" data-val="2" onclick="setRating(2)" style="color:#444; cursor:pointer;"></i>
                <i class="fas fa-star rate-star" data-val="3" onclick="setRating(3)" style="color:#444; cursor:pointer;"></i>
                <i class="fas fa-star rate-star" data-val="4" onclick="setRating(4)" style="color:#444; cursor:pointer;"></i>
                <i class="fas fa-star rate-star" data-val="5" onclick="setRating(5)" style="color:#444; cursor:pointer;"></i>
            </div>
            <textarea id="rateComment" class="form-control" placeholder="Write a review..." rows="2"></textarea>
            <div style="margin-top:15px; display:flex; gap:10px;">
                <button class="btn-primary" onclick="submitRating()">Submit</button>
                <button class="btn-primary" style="background:#444" onclick="closeRateModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let currentRating = 0;

        function deleteProduct() {
            if(confirm("Are you sure?")) {
                $.post('market_action.php', { action: 'delete_product', product_id: <?= $p_id ?> }, function(res){
                    res = JSON.parse(res);
                    if(res.success) {
                        window.location.href = 'marketplace.php';
                    } else {
                        alert(res.message);
                    }
                });
            }
        }

        function toggleFollow() {
             $.post('market_action.php', {
                 action: 'toggle_follow',
                 store_id: <?= $product['seller_id'] ?>
             }, function(res) {
                 res = JSON.parse(res);
                 if(res.success) {
                     let btn = $('#followBtn');
                     if(res.data.status === 'followed') {
                         btn.text('Following').css('background', '#333');
                     } else {
                         btn.text('Follow').css('background', 'var(--market-primary)');
                     }
                 } else {
                     alert(res.message);
                 }
             });
        }

        function openRateModal() {
            $('#rateModal').css('display', 'flex');
        }
        function closeRateModal() {
            $('#rateModal').hide();
        }
        function setRating(val) {
            currentRating = val;
            $('.rate-star').each(function(idx) {
                if (idx < val) $(this).css('color', 'gold');
                else $(this).css('color', '#444');
            });
        }
        function submitRating() {
            if (currentRating === 0) return alert("Please select a star rating");
            let comm = $('#rateComment').val();
            $.post('market_action.php', { 
                action: 'submit_review', 
                seller_id: <?= $seller_id ?>, 
                rating: currentRating, 
                comment: comm 
            }, function(res) {
                res = JSON.parse(res);
                alert(res.message);
                if(res.success) location.reload();
            });
        }
    </script>
</body>
</html>
