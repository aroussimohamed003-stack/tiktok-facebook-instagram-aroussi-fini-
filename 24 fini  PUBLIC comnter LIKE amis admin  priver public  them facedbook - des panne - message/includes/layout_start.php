<?php
// layout_start.php
include_once("includes/header.php");
include_once("includes/navbar.php");
?>

<main class="max-w-[1440px] mx-auto grid grid-cols-1 md:grid-cols-12 gap-6 p-4 md:p-6">
    <!-- BEGIN: LeftSidebar -->
    <aside class="hidden md:block md:col-span-3 space-y-6">
        <!-- Activity Card -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold text-slate-900">Activity</h2>
                <a href="notification.php" class="text-xs font-semibold text-slate-400 hover:text-blue-500">See all</a>
            </div>
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center border border-dashed border-slate-200">
                        <i class="fas fa-bolt text-slate-400"></i>
                    </div>
                    <div class="text-sm">
                        <p class="font-semibold text-slate-800">New Updates</p>
                        <p class="text-slate-500 text-xs text-nowrap">Stay tuned for new features</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Explore Card -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold text-slate-900">Explore</h2>
                <a href="public.php" class="text-xs font-semibold text-slate-400 hover:text-blue-500">See all</a>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div class="h-20 bg-slate-100 rounded-xl overflow-hidden group cursor-pointer relative">
                    <img src="https://images.unsplash.com/photo-1611162617474-5b21e879e113?w=200" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                    <div class="absolute inset-0 bg-black/10"></div>
                </div>
                <div class="h-20 bg-slate-100 rounded-xl overflow-hidden group cursor-pointer relative">
                    <img src="https://images.unsplash.com/photo-1611605698335-8b1569810432?w=200" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                    <div class="absolute inset-0 bg-black/10"></div>
                </div>
            </div>
        </section>

        <!-- Navigation Menu -->
        <nav class="bg-white rounded-2xl shadow-sm border border-slate-100 p-2">
            <a href="indexmo.php" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'indexmo.php' ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700'; ?>">
                <i class="fas fa-home w-5"></i> Home
            </a>
            <a href="public.php" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'public.php' ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700'; ?>">
                <i class="fas fa-compass w-5"></i> Explore
            </a>
            <a href="message.php" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'message.php' ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700'; ?>">
                <i class="fas fa-comment w-5"></i> Messages
            </a>
            <a href="marketplace.php" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition-all <?php echo basename($_SERVER['PHP_SELF']) == 'marketplace.php' ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700'; ?>">
                <i class="fas fa-store w-5"></i> Market
            </a>
        </nav>
    </aside>
    <!-- END: LeftSidebar -->

    <!-- BEGIN: CenterColumn -->
    <div class="col-span-1 md:col-span-9 lg:col-span-6 space-y-6">
