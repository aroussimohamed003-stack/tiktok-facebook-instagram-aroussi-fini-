<?php
session_start();
// Prevent any output before JSON
ob_start();

include("config.php"); // Ensure DB connection ($con)
ini_set('display_errors', 0); // Suppress HTML errors in AJAX response

// Helper to clean output and send JSON
function jsonResponse($success, $message, $data = []) {
    ob_clean(); // Clear any previous output (e.g. from includes)
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, "Please login first");
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// AUTO-FIX: Ensure subcategory_id and currency columns exist
$check_sub = $con->query("SHOW COLUMNS FROM market_products LIKE 'subcategory_id'");
if ($check_sub && $check_sub->num_rows == 0) {
    $con->query("ALTER TABLE market_products ADD COLUMN subcategory_id INT DEFAULT 0 AFTER category_id");
}
$check_curr = $con->query("SHOW COLUMNS FROM market_products LIKE 'currency'");
if ($check_curr && $check_curr->num_rows == 0) {
    $con->query("ALTER TABLE market_products ADD COLUMN currency VARCHAR(10) DEFAULT 'USD' AFTER price");
}

if ($action == 'add_product') {
    $title = $_POST['title'] ?? '';
    $price = $_POST['price'] ?? 0;
    $currency = $_POST['currency'] ?? 'USD';
    $desc = $_POST['description'] ?? '';
    $cat_id = $_POST['category_id'] ?? 1;
    $loc = $_POST['location'] ?? '';

    if (empty($title) || empty($price)) {
        jsonResponse(false, "Title and Price are required");
    }

    $subcat_id = $_POST['subcategory_id'] ?? 0;

    // Insert Product
    $stmt = $con->prepare("INSERT INTO market_products (user_id, category_id, subcategory_id, title, description, price, currency, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        jsonResponse(false, "Database preparation error: " . $con->error);
    }

    $stmt->bind_param("iiisssss", $user_id, $cat_id, $subcat_id, $title, $desc, $price, $currency, $loc);
    
    if ($stmt->execute()) {
        $product_id = $stmt->insert_id;

        // Handle Images
        $uploadDir = 'uploads/market/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                $fileName = basename($_FILES['images']['name'][$key]);
                $targetPath = $uploadDir . time() . "_" . $fileName;
                
                // Allow specific extensions
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    if (move_uploaded_file($tmp_name, $targetPath)) {
                        $is_main = ($key == 0) ? 1 : 0;
                        $imgStmt = $con->prepare("INSERT INTO market_product_images (product_id, image_path, is_main) VALUES (?, ?, ?)");
                        $imgStmt->bind_param("isi", $product_id, $targetPath, $is_main);
                        $imgStmt->execute();
                    }
                }
            }
        }

        jsonResponse(true, "Product listed successfully!");
    } else {
        jsonResponse(false, "Database error: " . $con->error);
    }
}

if ($action == 'fetch_products') {
    $cat_id = $_GET['cat'] ?? 0;
    $search = $_GET['q'] ?? '';
    // $min_price and $max_price are unused in query but good to keep for logic reference
    $min_price = floatval($_GET['min_p'] ?? 0);
    $max_price = floatval($_GET['max_p'] ?? 0);
    $subcat_id = intval($_GET['subcat'] ?? 0);
    
    $sql = "SELECT p.*, pi.image_path as thumb, u.username, u.profile_picture 
            FROM market_products p 
            LEFT JOIN users u ON p.user_id = u.id 
            LEFT JOIN market_product_images pi ON p.id = pi.product_id AND pi.is_main = 1
            WHERE p.status = 'active'";
            
    if ($cat_id > 0) {
        $sql .= " AND p.category_id = " . intval($cat_id);
    }
    if ($subcat_id > 0) {
        $sql .= " AND p.subcategory_id = " . intval($subcat_id);
    }
    if ($min_price > 0) {
        $sql .= " AND p.price >= $min_price";
    }
    if ($max_price > 0) {
        $sql .= " AND p.price <= $max_price";
    }
    if (!empty($search)) {
        $searchEsc = $con->real_escape_string($search);
        $sql .= " AND (p.title LIKE '%$searchEsc%' OR p.location LIKE '%$searchEsc%')";
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT 50";
    
    $result = $con->query($sql);
    $products = [];
    while($row = $result->fetch_assoc()) {
        // Fix image path if missing
        if (empty($row['thumb'])) {
            $row['thumb'] = 'images/no-product.png'; // Fallback
        }
        $products[] = $row;
    }
    
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'products' => $products
    ]);
    exit();
}

if ($action == 'delete_product') {
    $p_id = $_POST['product_id'];
    // Verify owner
    $check = $con->query("SELECT user_id FROM market_products WHERE id = $p_id");
    if ($row = $check->fetch_assoc()) {
        if ($row['user_id'] == $user_id) {
            $con->query("UPDATE market_products SET status = 'deleted' WHERE id = $p_id");
            jsonResponse(true, "Product deleted");
        }
    }
    jsonResponse(false, "Not authorized");
}

if ($action == 'submit_review') {
    $seller_id = intval($_POST['seller_id']);
    $rating = intval($_POST['rating']);
    $comment = $_POST['comment'] ?? '';

    if ($seller_id == $user_id) {
        jsonResponse(false, "You cannot rate yourself");
    }

    // Check if already rated
    $check = $con->query("SELECT id FROM market_reviews WHERE seller_id = $seller_id AND reviewer_id = $user_id");
    if ($check->num_rows > 0) {
        // Update
        $stmt = $con->prepare("UPDATE market_reviews SET rating = ?, comment = ? WHERE seller_id = ? AND reviewer_id = ?");
        $stmt->bind_param("isii", $rating, $comment, $seller_id, $user_id);
    } else {
        // Insert
        $stmt = $con->prepare("INSERT INTO market_reviews (seller_id, reviewer_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $seller_id, $user_id, $rating, $comment);
    }

    if ($stmt->execute()) {
        jsonResponse(true, "Review submitted");
    } else {
        jsonResponse(false, "Database error");
    }
}

if ($action == 'toggle_follow') {
    $store_id = intval($_POST['store_id']);
    
    if ($store_id == $user_id) jsonResponse(false, "Cannot follow self");

    $check = $con->query("SELECT id FROM market_store_followers WHERE store_id = $store_id AND follower_id = $user_id");
    
    if ($check->num_rows > 0) {
        // Unfollow
        $con->query("DELETE FROM market_store_followers WHERE store_id = $store_id AND follower_id = $user_id");
        jsonResponse(true, "Unfollowed", ['status' => 'unfollowed']);
    } else {
        // Follow
        $con->query("INSERT INTO market_store_followers (store_id, follower_id) VALUES ($store_id, $user_id)");
        // Notify
        $con->query("INSERT INTO notifications (recipient_id, sender_id, type) VALUES ($store_id, $user_id, 'follow')");
        jsonResponse(true, "Followed", ['status' => 'followed']);
    }
}
?>
