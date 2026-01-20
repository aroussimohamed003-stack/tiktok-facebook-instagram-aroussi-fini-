<?php
session_start();
include("config.php");
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// Get Categories and Subcategories
$cats_query = $con->query("SELECT * FROM market_categories ORDER BY id ASC");
$subcats_query = $con->query("SELECT * FROM market_subcategories");

$categories = [];
$subcategories = [];

if ($cats_query) {
    while($c = $cats_query->fetch_assoc()) {
        $categories[] = $c;
    }
    // Sort categories by name alphabetically or ID
}

if ($subcats_query) {
    while($s = $subcats_query->fetch_assoc()) {
        $subcategories[$s['category_id']][] = $s;
    }
}

// Pass subcats to JS
$subcats_json = json_encode($subcategories);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css"> <!-- Base styles -->
    <link rel="stylesheet" href="market.css"> <!-- Market styles -->
</head>
<body class="market-page">

    <!-- Header -->
    <div class="market-header">
        <a href="indexmo.php" style="color:white; margin-right: 15px;"><i class="fas fa-arrow-left fa-lg"></i></a>
        <div class="market-search">
            <i class="fas fa-search" style="color:#888"></i>
            <input type="text" id="searchInput" placeholder="Search Marketplace...">
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <i class="fas fa-filter fa-lg" style="color:white; cursor:pointer;" onclick="toggleFilters()"></i>
            <a href="market_messages.php" style="color:white;"><i class="fas fa-comment-dots fa-lg"></i></a>
        </div>
    </div>

    <!-- Categories -->
    <!-- Categories -->
    <div class="categories-scroller" id="mainCatScroller">
        <div class="category-pill active" onclick="filterCat(0, this)">
            <i class="fas fa-border-all"></i> All
        </div>
        <?php foreach($categories as $c): ?>
        <div class="category-pill" onclick="filterCat(<?= $c['id'] ?>, this)">
            <i class="fas <?= $c['icon'] ?>"></i> <?= $c['name'] ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Subcategories Scroller (Hidden by default) -->
    <div class="categories-scroller" id="subCatScroller" style="display:none; background:rgba(255,255,255,0.05); padding:10px;">
        <!-- Injected by JS -->
    </div>

    <!-- Filter Drawer (Hidden by default) -->
    <div id="filterDrawer" style="display:none; padding:15px; background:var(--market-card-bg);">
        <label>Price Range</label>
        <div style="display:flex; gap:10px;">
            <input type="number" id="minPrice" placeholder="Min" class="form-control">
            <input type="number" id="maxPrice" placeholder="Max" class="form-control">
        </div>
        <button onclick="loadProducts()" class="btn-primary" style="margin-top:10px;">Apply</button>
    </div>

    <!-- Product Grid -->
    <div class="products-grid" id="productContainer">
        <!-- Products injected by JS -->
    </div>

    <!-- FAB Add Button -->
    <div class="fab-add" onclick="openAddModal()">
        <i class="fas fa-plus"></i>
    </div>

    <!-- Add Product Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; margin-bottom:15px;">
                <h3>Sell Item</h3>
                <i class="fas fa-times" onclick="closeAddModal()" style="cursor:pointer"></i>
            </div>
            <form id="addForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_product">
                
                <div class="form-group">
                    <label>Photos (Max 3)</label>
                    <input type="file" name="images[]" multiple accept="image/*" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" required placeholder="What are you selling?">
                </div>

                <div class="form-group">
                    <label>Price</label>
                    <input type="number" name="price" class="form-control" required placeholder="0.00">
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" id="catSelect" class="form-control" onchange="updateSubCats(this.value)">
                        <?php foreach($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="subCatGroup" style="display:none;">
                    <label>Subcategory</label>
                    <select name="subcategory_id" id="subCatSelect" class="form-control">
                        <!-- Populated by JS -->
                    </select>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" class="form-control" placeholder="City, Area">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>

                <button type="submit" class="btn-primary">List Item</button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let currentCat = 0;
        let currentSubCat = 0;
        let subcats = <?= $subcats_json ?>; // PHP to JS
        
        $(document).ready(function() {
            loadProducts();

            $('#searchInput').on('keyup', function() {
                loadProducts();
            });

            $('#addForm').on('submit', function(e) {
                e.preventDefault();
                let formData = new FormData(this);
                let btn = $(this).find('button[type="submit"]');
                btn.prop('disabled', true).text('Listing...');
                
                $.ajax({
                    url: 'market_action.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        btn.prop('disabled', false).text('List Item');
                        var data;
                        try {
                            data = (typeof res === 'object') ? res : JSON.parse(res);
                        } catch(e) {
                            console.error('JSON Parse Error:', e, res);
                            alert('Server Error: ' + res.substring(0, 100));
                            return;
                        }

                        if(data.success) {
                            alert(data.message);
                            closeAddModal();
                            loadProducts();
                            $('#addForm')[0].reset();
                        } else {
                            alert(data.message);
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).text('List Item');
                        alert('Network/Server Error');
                    }
                });
            });
        });

        function loadProducts() {
            let q = $('#searchInput').val();
            let min = $('#minPrice').val();
            let max = $('#maxPrice').val();

            $.get('market_action.php', { 
                action: 'fetch_products', 
                cat: currentCat, 
                subcat: currentSubCat, // NEW
                q: q, 
                min_p: min, 
                max_p: max 
            }, function(res) {
                var data;
                try {
                    data = (typeof res === 'object') ? res : JSON.parse(res);
                } catch(e) {
                    console.error('JSON Parse Error:', e, res);
                    return;
                }

                if(data.success) {
                    let html = '';
                    if(data.products.length > 0) {
                        res.products.forEach(p => {
                            html += `
                            <div class="product-card" onclick="window.location.href='market_product_details.php?id=${p.id}'">
                                <div class="product-img-wrapper">
                                    <img src="${p.thumb}" class="product-img" onerror="this.src='images/no-product.png'">
                                </div>
                                <div class="product-info">
                                    <div class="product-price">$${p.price}</div>
                                    <div class="product-title">${p.title}</div>
                                    <div class="product-meta">
                                        <span>${p.location || 'Unknown'}</span>
                                        <span>${p.views} views</span>
                                    </div>
                                </div>
                            </div>`;
                        });
                    } else {
                        html = '<div style="grid-column: 1/-1; text-align:center; padding:20px; color:#666;">No products found in this category.</div>';
                    }
                    $('#productContainer').html(html);
                }
            });
        }

        function filterCat(id, el) {
            currentCat = id;
            currentSubCat = 0; // Reset subcat when changing main cat
            
            // UI Updates
            $('#mainCatScroller .category-pill').removeClass('active');
            $(el).addClass('active');

            // Show/Hide Subcategories
            if(id != 0 && subcats[id] && subcats[id].length > 0) {
                let subHtml = `<div class="category-pill active" onclick="filterSubCat(0, this)">All</div>`;
                subcats[id].forEach(s => {
                    subHtml += `<div class="category-pill" onclick="filterSubCat(${s.id}, this)">${s.name}</div>`;
                });
                $('#subCatScroller').html(subHtml).slideDown();
            } else {
                $('#subCatScroller').slideUp();
            }
            
            loadProducts();
        }

        function filterSubCat(id, el) {
            currentSubCat = id;
            $('#subCatScroller .category-pill').removeClass('active');
            $(el).addClass('active');
            loadProducts();
        }

        function toggleFilters() {
            $('#filterDrawer').slideToggle();
        }

        function openAddModal() {
            $('#addModal').addClass('flex').show();
            // Using flex for centering
            $('#addModal').css('display', 'flex');
        }

        function closeAddModal() {
            $('#addModal').hide();
        }

        function updateSubCats(catId) {
            let el = $('#subCatSelect');
            el.empty();
            if(subcats[catId] && subcats[catId].length > 0) {
                subcats[catId].forEach(s => {
                    el.append(`<option value="${s.id}">${s.name}</option>`);
                });
                $('#subCatGroup').show();
            } else {
                el.append('<option value="0">None</option>');
                $('#subCatGroup').hide();
            }
        }
        
        // Init subcats for default cat
        $(document).ready(function() {
             // ... existing ready ...
             // Trigger update initially
             if($('#catSelect').val()) updateSubCats($('#catSelect').val());
        });
    </script>
</body>
</html>
