<?php
include("config.php");

// 1. Marketplace Items Table
$sql1 = "CREATE TABLE IF NOT EXISTS market_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    category VARCHAR(50) NOT NULL,
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($con->query($sql1)) echo "Table 'market_items' created.<br>";
else echo "Error creating 'market_items': " . $con->error . "<br>";

// 2. Marketplace Images Table
$sql2 = "CREATE TABLE IF NOT EXISTS market_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_main TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES market_items(id) ON DELETE CASCADE
)";
if ($con->query($sql2)) echo "Table 'market_images' created.<br>";
else echo "Error creating 'market_images': " . $con->error . "<br>";

// 3. Marketplace Ratings/Reviews Table
$sql3 = "CREATE TABLE IF NOT EXISTS market_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    rater_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($con->query($sql3)) echo "Table 'market_ratings' created.<br>";
else echo "Error creating 'market_ratings': " . $con->error . "<br>";

// 4. Marketplace Store Follows (Favorites)
$sql4 = "CREATE TABLE IF NOT EXISTS market_follows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,
    store_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_follow (follower_id, store_id)
)";
if ($con->query($sql4)) echo "Table 'market_follows' created.<br>";
else echo "Error creating 'market_follows': " . $con->error . "<br>";

echo "Marketplace Setup Complete.";
?>
