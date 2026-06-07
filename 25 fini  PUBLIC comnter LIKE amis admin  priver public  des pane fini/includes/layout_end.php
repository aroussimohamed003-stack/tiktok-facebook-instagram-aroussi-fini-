<?php
// layout_end.php
?>
    </div>
    <!-- END: CenterColumn -->

    <!-- BEGIN: RightSidebar -->
    <aside class="hidden lg:block lg:col-span-3 space-y-6">
        <!-- Suggested For You -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold text-slate-900">Suggested For You</h2>
                <button class="text-xs font-semibold text-slate-400 hover:text-blue-500">See all</button>
            </div>
            <div class="space-y-4">
                <?php
                // Fetch some random users
                $s_res = mysqli_query($con, "SELECT id, username, profile_picture FROM users WHERE id != ".($_SESSION['user_id'] ?? 0)." ORDER BY RAND() LIMIT 4");
                while($s_row = mysqli_fetch_assoc($s_res)):
                    $s_pic = !empty($s_row['profile_picture']) ? $s_row['profile_picture'] : 'uploads/profile.jpg';
                ?>
                <div class="flex items-center gap-3">
                    <img alt="User" class="w-9 h-9 rounded-full border border-slate-100 object-cover" src="<?php echo $s_pic; ?>">
                    <div class="flex-1">
                        <h5 class="text-xs font-bold text-slate-900"><?php echo htmlspecialchars($s_row['username']); ?></h5>
                        <p class="text-[10px] text-slate-400">Recently active</p>
                    </div>
                    <a href="profile.php?user_id=<?php echo $s_row['id']; ?>" class="text-blue-500 hover:bg-blue-50 p-1 rounded-full transition-colors">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 6v6m0 0v6m0-6h6m-6 0H6" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                    </a>
                </div>
                <?php endwhile; ?>
            </div>
        </section>

        <!-- Stats/Promo Card -->
        <section class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl shadow-lg p-6 text-white overflow-hidden relative">
            <div class="relative z-10">
                <h3 class="font-bold text-lg mb-2">Go Premium</h3>
                <p class="text-xs text-blue-100 mb-4 leading-relaxed">Boost your posts and get verified badge today!</p>
                <a href="sponsor.php" class="inline-block bg-white text-blue-600 text-xs font-bold px-4 py-2 rounded-xl hover:bg-blue-50 transition-colors">Learn More</a>
            </div>
            <div class="absolute -bottom-8 -right-8 w-24 h-24 bg-white/10 rounded-full blur-2xl"></div>
        </section>

        <!-- Footer Links -->
        <div class="px-5 text-[10px] text-slate-400 space-x-2">
            <a href="about.php" class="hover:underline">About</a>
            <span>•</span>
            <a href="#" class="hover:underline">Privacy</a>
            <span>•</span>
            <a href="#" class="hover:underline">Terms</a>
            <p class="mt-4">© <?php echo date('Y'); ?> Piqosocial</p>
        </div>
    </aside>
    <!-- END: RightSidebar -->
</main>

<?php include_once("includes/footer.php"); ?>
