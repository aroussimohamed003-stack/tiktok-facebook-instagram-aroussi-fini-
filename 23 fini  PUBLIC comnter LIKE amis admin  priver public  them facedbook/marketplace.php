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
}

if ($subcats_query) {
    while($s = $subcats_query->fetch_assoc()) {
        $subcategories[$s['category_id']][] = $s;
    }
}

// Pass subcats to JS
$subcats_json = json_encode($subcategories);

$pageTitle = "High-Fidelity Marketplace";
include("includes/layout_start.php");
?>

<div class="max-w-6xl mx-auto py-6 px-4">
    <!-- Market Header / Toolbar -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-4 mb-6 flex flex-col md:flex-row items-center gap-4">
        <div class="flex-1 w-full md:w-auto relative group">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
            <input type="text" id="searchInput" placeholder="Search the marketplace..." class="w-full pl-12 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 focus:bg-white transition-all text-sm font-medium">
        </div>
        
        <div class="flex items-center gap-3 w-full md:w-auto">
            <select id="currencyViewer" class="px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-xs font-bold text-slate-700 h-[46px]" onchange="loadProducts()">
                <option value="ORIGINAL">Original</option>
                <option value="USD">USD ($)</option>
                <option value="TND">TND (د.ت)</option>
                <option value="EUR">EUR (€)</option>
            </select>
            
            <button onclick="toggleFilters()" class="w-12 h-[46px] flex items-center justify-center bg-slate-50 border border-slate-200 rounded-2xl text-slate-500 hover:text-blue-600 hover:border-blue-500 transition-all">
                <i class="fas fa-sliders-h"></i>
            </button>
            
            <a href="market_messages.php" class="w-12 h-[46px] flex items-center justify-center bg-blue-600 text-white rounded-2xl shadow-lg shadow-blue-500/20 hover:bg-blue-700 transition-all">
                <i class="fas fa-comment-dots"></i>
            </a>
            
            <button onclick="openAddModal()" class="px-6 py-3 bg-slate-900 text-white rounded-2xl font-black text-xs hover:bg-slate-800 transition-all shadow-xl shadow-slate-900/10 flex items-center gap-2 whitespace-nowrap h-[46px]">
                <i class="fas fa-plus"></i> Sell Item
            </button>
        </div>
    </div>

    <!-- Filter Drawer (Tailwind Styled) -->
    <div id="filterDrawer" class="hidden mb-6 p-6 bg-white rounded-3xl border border-slate-100 shadow-sm animate-in fade-in slide-in-from-top-4 duration-300">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="space-y-2">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Price Floor</label>
                <input type="number" id="minPrice" placeholder="0.00" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 text-sm">
            </div>
            <div class="space-y-2">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Price Ceiling</label>
                <input type="number" id="maxPrice" placeholder="Unlimited" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 text-sm">
            </div>
            <div class="flex items-end">
                <button onclick="loadProducts()" class="w-full py-3 bg-blue-600 text-white rounded-xl font-black text-xs shadow-lg shadow-blue-500/20">Apply Filters</button>
            </div>
        </div>
    </div>

    <!-- Category Pills -->
    <div class="flex items-center gap-3 overflow-x-auto pb-4 mb-4 scrollbar-hide no-scrollbar" id="mainCatScroller">
        <button onclick="filterCat(0, this)" class="category-pill active whitespace-nowrap px-6 py-2.5 rounded-full bg-white border border-slate-100 text-slate-600 text-xs font-bold hover:bg-slate-50 transition-all flex items-center gap-2 [&.active]:bg-slate-900 [&.active]:text-white [&.active]:border-slate-900 shadow-sm">
            <i class="fas fa-border-all"></i> All Items
        </button>
        <?php foreach($categories as $c): ?>
        <button onclick="filterCat(<?= $c['id'] ?>, this)" class="category-pill whitespace-nowrap px-6 py-2.5 rounded-full bg-white border border-slate-100 text-slate-600 text-xs font-bold hover:bg-slate-50 transition-all flex items-center gap-2 [&.active]:bg-slate-900 [&.active]:text-white [&.active]:border-slate-900 shadow-sm">
            <i class="fas <?= $c['icon'] ?>"></i> <?= $c['name'] ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Subcategories (Hidden by default) -->
    <div id="subCatScroller" class="hidden flex items-center gap-2 overflow-x-auto pb-4 mb-6 scrollbar-hide no-scrollbar border-t border-slate-50 pt-4">
        <!-- Injected by JS -->
    </div>

    <!-- Product Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6" id="productContainer">
        <!-- Products injected by JS -->
    </div>
</div>

<!-- Sell Item Modal (Tailwind Modern) -->
<div id="addModal" class="fixed inset-0 z-[100] hidden bg-slate-900/60 backdrop-blur-sm items-center justify-center p-4">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-xl overflow-hidden animate-in zoom-in-95 duration-200">
        <div class="bg-gradient-to-r from-slate-900 to-blue-900 p-8 flex items-center justify-between">
            <div>
                <h3 class="text-2xl font-black text-white font-brand">List Item</h3>
                <p class="text-white/60 text-[10px] font-bold uppercase tracking-widest">Connect with elite buyers</p>
            </div>
            <button onclick="closeAddModal()" class="w-10 h-10 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20 transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="addForm" enctype="multipart/form-data" class="p-8 space-y-6 max-h-[70vh] overflow-y-auto custom-scrollbar">
            <input type="hidden" name="action" value="add_product">
            
            <div class="space-y-4">
                <div class="p-8 border-2 border-dashed border-slate-200 rounded-3xl text-center group hover:border-blue-500 hover:bg-blue-50/50 transition-all cursor-pointer relative">
                    <input type="file" name="images[]" multiple accept="image/*" required class="absolute inset-0 opacity-0 cursor-pointer">
                    <div class="w-12 h-12 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                        <i class="fas fa-images text-xl text-slate-400 group-hover:text-blue-500"></i>
                    </div>
                    <p class="text-xs font-black text-slate-700">Add Product Photos</p>
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">Maximum 3 High-Fidelity Images</p>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Display Title</label>
                    <input type="text" name="title" required placeholder="What are you offering?" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 font-medium">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Value</label>
                        <input type="number" name="price" required placeholder="0.00" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 font-bold">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Currency</label>
                        <select name="currency" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 font-bold appearance-none">
                            <option value="USD">USD ($)</option>
                            <option value="TND">TND (د.ت)</option>
                            <option value="EUR">EUR (€)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Main Category</label>
                        <select name="category_id" id="catSelect" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 font-bold appearance-none" onchange="updateSubCats(this.value)">
                            <?php foreach($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-2" id="subCatGroup">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Subcategory</label>
                        <select name="subcategory_id" id="subCatSelect" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 font-bold appearance-none">
                            <!-- Populated by JS -->
                        </select>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Location</label>
                    <input type="text" name="location" placeholder="City, Area" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 font-medium">
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Story & Details</label>
                    <textarea name="description" rows="3" placeholder="Tell the world why they need this..." class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 font-medium"></textarea>
                </div>
            </div>

            <button type="submit" class="w-full py-4 bg-slate-900 text-white rounded-2xl font-black text-sm hover:bg-slate-800 transition-all shadow-xl shadow-slate-900/20">List Hub Item</button>
        </form>
    </div>
</div>

<style>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.category-pill.active { @apply bg-slate-900 text-white border-slate-900 shadow-lg shadow-slate-900/10; }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    let currentCat = 0;
    let currentSubCat = 0;
    let subcats = <?= $subcats_json ?>;
    
    $(document).ready(function() {
        loadProducts();

        $('#searchInput').on('keyup', function() {
            loadProducts();
        });

        $('#addForm').on('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            let btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true).html('<i class="fas fa-circle-notch animate-spin"></i> Listing...');
            
            $.ajax({
                url: 'market_action.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    btn.prop('disabled', false).text('List Hub Item');
                    var data;
                    try {
                        data = (typeof res === 'object') ? res : JSON.parse(res);
                    } catch(e) {
                        alert('Server Connection Issue');
                        return;
                    }

                    if(data.success) {
                        closeAddModal();
                        loadProducts();
                        $('#addForm')[0].reset();
                        // Optional: Show premium success notification
                    } else {
                        alert(data.message);
                    }
                },
                error: function() {
                    btn.prop('disabled', false).text('List Hub Item');
                    alert('Network error');
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
            subcat: currentSubCat,
            q: q, 
            min_p: min, 
            max_p: max 
        }, function(res) {
            var data;
            try {
                data = (typeof res === 'object') ? res : JSON.parse(res);
            } catch(e) { return; }

            if(data.success) {
                let html = '';
                let viewCurr = $('#currencyViewer').val();
                const rates = {
                    'USD': { 'TND': 3.1, 'EUR': 0.92, 'USD': 1 },
                    'TND': { 'USD': 0.32, 'EUR': 0.30, 'TND': 1 },
                    'EUR': { 'USD': 1.09, 'TND': 3.35, 'EUR': 1 }
                };

                if(data.products.length > 0) {
                    data.products.forEach(p => {
                        let priceDisplay = '';
                        let pPrice = parseFloat(p.price);
                        let pCurr = p.currency || 'USD';

                        if (viewCurr === 'ORIGINAL' || viewCurr === pCurr) {
                            let symbol = pCurr === 'USD' ? '$' : (pCurr === 'TND' ? 'د.ت' : '€');
                            priceDisplay = symbol + pPrice.toLocaleString();
                        } else {
                            let converted = pPrice * (rates[pCurr][viewCurr]);
                            let symbol = viewCurr === 'USD' ? '$' : (viewCurr === 'TND' ? 'د.ت' : '€');
                            priceDisplay = symbol + converted.toLocaleString(undefined, {maximumFractionDigits: 0});
                        }

                        html += `
                        <div class="bg-white rounded-[2rem] border border-slate-100 overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group cursor-pointer" onclick="window.location.href='market_product_details.php?id=${p.id}'">
                            <div class="aspect-square relative overflow-hidden">
                                <img src="${p.thumb}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" onerror="this.src='images/no-product.png'">
                                <div class="absolute top-4 left-4 bg-white/90 backdrop-blur-md px-3 py-1.5 rounded-xl text-xs font-black text-slate-900 shadow-sm">
                                    ${priceDisplay}
                                </div>
                            </div>
                            <div class="p-5">
                                <h4 class="font-bold text-slate-800 text-sm line-clamp-1 mb-2 group-hover:text-blue-600 transition-colors">${p.title}</h4>
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1">
                                        <i class="fas fa-map-marker-alt text-[9px]"></i> ${p.location || 'Hub'}
                                    </span>
                                    <span class="text-[10px] font-bold text-blue-500 bg-blue-50 px-2 py-0.5 rounded-lg border border-blue-100">${p.views} views</span>
                                </div>
                            </div>
                        </div>`;
                    });
                } else {
                    html = '<div class="col-span-full text-center py-20 bg-slate-50 rounded-[3rem] border-2 border-dashed border-slate-200"><div class="text-4xl mb-4">🛸</div><h4 class="font-bold text-slate-800">No hub items detected</h4><p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-2">Try a different frequency or category</p></div>';
                }
                $('#productContainer').html(html);
            }
        });
    }

    function filterCat(id, el) {
        currentCat = id;
        currentSubCat = 0;
        $('.category-pill').removeClass('active').addClass('bg-white text-slate-600').removeClass('bg-slate-900 text-white shadow-lg shadow-slate-900/10');
        $(el).addClass('active bg-slate-900 text-white shadow-lg shadow-slate-900/10').removeClass('bg-white text-slate-600');

        if(id != 0 && subcats[id] && subcats[id].length > 0) {
            let subHtml = `<button onclick="filterSubCat(0, this)" class="sub-pill active whitespace-nowrap px-4 py-2 rounded-xl bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest shadow-sm">All</button>`;
            subcats[id].forEach(s => {
                subHtml += `<button onclick="filterSubCat(${s.id}, this)" class="sub-pill whitespace-nowrap px-4 py-2 rounded-xl bg-slate-100 text-slate-500 text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all">${s.name}</button>`;
            });
            $('#subCatScroller').html(subHtml).removeClass('hidden').addClass('flex');
        } else {
            $('#subCatScroller').addClass('hidden').removeClass('flex');
        }
        loadProducts();
    }

    function filterSubCat(id, el) {
        currentSubCat = id;
        $('.sub-pill').removeClass('bg-slate-900 text-white').addClass('bg-slate-100 text-slate-500');
        $(el).addClass('bg-slate-900 text-white').removeClass('bg-slate-100 text-slate-500');
        loadProducts();
    }

    function toggleFilters() {
        $('#filterDrawer').toggleClass('hidden');
    }

    function openAddModal() {
        $('#addModal').removeClass('hidden').addClass('flex');
    }

    function closeAddModal() {
        $('#addModal').addClass('hidden').removeClass('flex');
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
            el.append('<option value="0">General</option>');
            $('#subCatGroup').hide();
        }
    }
</script>

<?php
include("includes/layout_end.php");
?>
