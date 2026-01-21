<?php
include("config.php");

// 1. Market Categories Table
$sql = "CREATE TABLE IF NOT EXISTS market_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(100) DEFAULT 'fa-box',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($con->query($sql) === TRUE) {
    echo "Table 'market_categories' created successfully.<br>";
} else {
    echo "Error creating table 'market_categories': " . $con->error . "<br>";
}

// 1.5 Market Subcategories Table
$sql = "CREATE TABLE IF NOT EXISTS market_subcategories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES market_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($con->query($sql) === TRUE) {
    echo "Table 'market_subcategories' created successfully.<br>";
} else {
    echo "Error creating table 'market_subcategories': " . $con->error . "<br>";
}

// Insert Extended Hierarchical Categories
$categories_data = [
    'Home' => [
        'icon' => 'fa-home',
        'subs' => ['New Arrivals', 'Best Sellers', 'Deals & Discounts', 'Featured Products', 'Seasonal Offers']
    ],
    'Vehicles' => [
        'icon' => 'fa-car',
        'subs' => ['Cars & Bikes', 'New Cars', 'Used Cars', 'Motorcycles', 'Bicycles', 'Electric Vehicles', 'Auto Parts & Accessories', 'Spare Parts', 'Tires & Batteries', 'Oils & Fluids', 'Interior Accessories', 'Exterior Accessories', 'Car Electronics', 'Services', 'Car Rental', 'Maintenance Tools']
    ],
    'Electronics' => [
        'icon' => 'fa-mobile-alt',
        'subs' => ['Mobile & Computing', 'Smartphones', 'Tablets', 'Laptops', 'Desktop Computers', 'Accessories', 'Chargers & Cables', 'Power Banks', 'Headphones & Earbuds', 'Phone Accessories', 'Home Electronics', 'TVs', 'Monitors', 'Cameras', 'Smart Home Devices', 'Gaming', 'Consoles', 'Controllers', 'Games']
    ],
    'Clothing' => [
        'icon' => 'fa-tshirt',
        'subs' => ['Men', 'Tops', 'Bottoms', 'Jackets', 'Shoes', 'Women', 'Dresses', 'Tops', 'Bottoms', 'Shoes', 'Bags', 'Kids', 'Boys', 'Girls', 'Accessories', 'Watches', 'Sunglasses', 'Belts', 'Special', 'Sportswear', 'Formal Wear', 'Seasonal Clothing']
    ],
    'Home & Garden' => [
        'icon' => 'fa-couch',
        'subs' => ['Furniture', 'Home Décor', 'Lighting', 'Storage & Organization', 'Kitchen', 'Kitchen Tools', 'Appliances', 'Cleaning', 'Cleaning Supplies', 'Laundry', 'Garden', 'Garden Tools', 'Plants & Flowers', 'Irrigation Systems']
    ],
    'Entertainment' => [
        'icon' => 'fa-gamepad',
        'subs' => ['Games & Toys', 'Video Games', 'Board Games', 'Kids Toys', 'Media', 'Books', 'Movies & TV', 'Sports & Hobbies', 'Sports Equipment', 'Art & Music', 'Photography', 'Digital', 'Digital Subscriptions', 'Digital Products']
    ],
    'Gifts' => [
        'icon' => 'fa-gift',
        'subs' => ['Gift Ideas', 'Gift Cards', 'Bundles']
    ],
    'Other' => [
        'icon' => 'fa-box-open',
        'subs' => ['Miscellaneous']
    ]
];

foreach ($categories_data as $cat_name => $data) {
    // 1. Insert/Get Category
    $cat_icon = $data['icon'];
    $cat_id = 0;
    
    // Check if exists
    $stmt = $con->prepare("SELECT id FROM market_categories WHERE name = ?");
    $stmt->bind_param("s", $cat_name);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $cat_id = $res->fetch_assoc()['id'];
        // Update icon just in case
        $con->query("UPDATE market_categories SET icon = '$cat_icon' WHERE id = $cat_id");
    } else {
        $stmt_ins = $con->prepare("INSERT INTO market_categories (name, icon) VALUES (?, ?)");
        $stmt_ins->bind_param("ss", $cat_name, $cat_icon);
        $stmt_ins->execute();
        $cat_id = $stmt_ins->insert_id;
    }

    // 2. Insert Subcategories
    foreach ($data['subs'] as $sub_name) {
        $stmt_sub = $con->prepare("SELECT id FROM market_subcategories WHERE category_id = ? AND name = ?");
        $stmt_sub->bind_param("is", $cat_id, $sub_name);
        $stmt_sub->execute();
        
        if ($stmt_sub->get_result()->num_rows == 0) {
            $stmt_ins_sub = $con->prepare("INSERT INTO market_subcategories (category_id, name) VALUES (?, ?)");
            $stmt_ins_sub->bind_param("is", $cat_id, $sub_name);
            $stmt_ins_sub->execute();
        }
    }
}


// 2. Market Products Table
$sql = "CREATE TABLE IF NOT EXISTS market_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    location VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'sold', 'deleted') DEFAULT 'active',
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES market_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($con->query($sql) === TRUE) {
    echo "Table 'market_products' created successfully.<br>";
} else {
    echo "Error creating table 'market_products': " . $con->error . "<br>";
}

// 3. Market Product Images
$sql = "CREATE TABLE IF NOT EXISTS market_product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_main BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (product_id) REFERENCES market_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($con->query($sql) === TRUE) {
    echo "Table 'market_product_images' created successfully.<br>";
} else {
    echo "Error creating table 'market_product_images': " . $con->error . "<br>";
}

// 4. Market Reviews (Seller Ratings)
$sql = "CREATE TABLE IF NOT EXISTS market_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($con->query($sql) === TRUE) {
    echo "Table 'market_reviews' created successfully.<br>";
} else {
    echo "Error creating table 'market_reviews': " . $con->error . "<br>";
}

// 5. Store Followers
$sql = "CREATE TABLE IF NOT EXISTS market_store_followers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    follower_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_follow (store_id, follower_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($con->query($sql) === TRUE) {
    echo "Table 'market_store_followers' created successfully.<br>";
} else {
    echo "Error creating table 'market_store_followers': " . $con->error . "<br>";
}

// Update Notifications ENUM
$con->query("ALTER TABLE notifications MODIFY COLUMN type ENUM('like', 'comment', 'message', 'follow') NOT NULL");

// Update Messages Table for Separated Marketplace Chat
// We add 'type' to distinguish normal vs market chats
// We add 'product_id' to link a message to a specific product context
// Update Messages Table for Separated Marketplace Chat
// ... previous code ...
$check_msg = $con->query("SHOW COLUMNS FROM messages LIKE 'type'");
if ($check_msg->num_rows == 0) {
    $con->query("ALTER TABLE messages ADD COLUMN type ENUM('normal', 'market') DEFAULT 'normal'");
    $con->query("ALTER TABLE messages ADD COLUMN product_id INT DEFAULT NULL");
    $con->query("ALTER TABLE messages ADD CONSTRAINT fk_msg_product FOREIGN KEY (product_id) REFERENCES market_products(id) ON DELETE SET NULL");
}

// 6. Add subcategory_id to market_products if missing
$check_sub = $con->query("SHOW COLUMNS FROM market_products LIKE 'subcategory_id'");
if ($check_sub->num_rows == 0) {
    $con->query("ALTER TABLE market_products ADD COLUMN subcategory_id INT DEFAULT 0 AFTER category_id");
}

echo "Marketplace setup complete.";
?>
