<?php
session_start();
include("config.php");

echo "<h2>Marketplace Diagnostic Test</h2>";

// Test 1: Database Connection
echo "<h3>1. Database Connection</h3>";
if ($con) {
    echo "✅ Database connected successfully<br>";
    echo "Database name: " . $dbname . "<br>";
} else {
    echo "❌ Database connection failed: " . mysqli_connect_error() . "<br>";
    exit();
}

// Test 2: Check if user is logged in
echo "<h3>2. User Session</h3>";
if (isset($_SESSION['user_id'])) {
    echo "✅ User is logged in (ID: " . $_SESSION['user_id'] . ")<br>";
} else {
    echo "❌ User is not logged in<br>";
}

// Test 3: Check if tables exist
echo "<h3>3. Required Tables</h3>";
$required_tables = [
    'market_categories',
    'market_subcategories', 
    'market_products',
    'market_product_images',
    'market_reviews',
    'market_store_followers'
];

foreach ($required_tables as $table) {
    $result = $con->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Table '$table' exists<br>";
        
        // Count rows
        $count_result = $con->query("SELECT COUNT(*) as cnt FROM $table");
        if ($count_result) {
            $count = $count_result->fetch_assoc()['cnt'];
            echo "&nbsp;&nbsp;&nbsp;→ Contains $count rows<br>";
        }
    } else {
        echo "❌ Table '$table' does NOT exist<br>";
    }
}

// Test 4: Check market_products columns
echo "<h3>4. market_products Table Structure</h3>";
$result = $con->query("SHOW COLUMNS FROM market_products");
if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Cannot show table structure<br>";
}

// Test 5: Fetch categories
echo "<h3>5. Categories Data</h3>";
$cats_query = $con->query("SELECT * FROM market_categories ORDER BY id ASC");
if ($cats_query) {
    echo "✅ Categories query successful<br>";
    echo "Found " . $cats_query->num_rows . " categories:<br>";
    while ($cat = $cats_query->fetch_assoc()) {
        echo "&nbsp;&nbsp;- " . $cat['name'] . " (ID: " . $cat['id'] . ", Icon: " . $cat['icon'] . ")<br>";
    }
} else {
    echo "❌ Categories query failed: " . $con->error . "<br>";
}

// Test 6: Fetch products
echo "<h3>6. Products Data</h3>";
$products_query = $con->query("SELECT p.*, pi.image_path as thumb 
                               FROM market_products p 
                               LEFT JOIN market_product_images pi ON p.id = pi.product_id AND pi.is_main = 1
                               WHERE p.status = 'active' 
                               LIMIT 5");
if ($products_query) {
    echo "✅ Products query successful<br>";
    echo "Found " . $products_query->num_rows . " active products (showing first 5):<br>";
    if ($products_query->num_rows > 0) {
        while ($prod = $products_query->fetch_assoc()) {
            echo "&nbsp;&nbsp;- " . $prod['title'] . " ($" . $prod['price'] . ")<br>";
        }
    } else {
        echo "&nbsp;&nbsp;⚠️ No products found<br>";
    }
} else {
    echo "❌ Products query failed: " . $con->error . "<br>";
}

// Test 7: Test AJAX endpoint
echo "<h3>7. AJAX Endpoint Test</h3>";
echo "<a href='market_action.php?action=fetch_products' target='_blank'>Test fetch_products endpoint</a><br>";

echo "<hr>";
echo "<h3>Recommendations:</h3>";
echo "<ul>";
echo "<li>If tables don't exist, run <a href='setup_marketplace.php'>setup_marketplace.php</a></li>";
echo "<li>If not logged in, <a href='login.php'>login first</a></li>";
echo "<li>Check the AJAX endpoint link above to see if it returns valid JSON</li>";
echo "</ul>";
?>
