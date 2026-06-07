  <!-- Footer -->
  <footer class="mt-12 py-8 bg-white border-t border-slate-100">
    <div class="max-w-[1440px] mx-auto px-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div>
          <h5 class="font-bold text-slate-900 mb-4 text-right">روابط سريعة</h5>
          <ul class="space-y-2 text-sm text-slate-500 text-right">
            <li><a href="indexmo.php" class="hover:text-blue-500 transition-colors">الرئيسية</a></li>
            <li><a href="uplod-profile.php" class="hover:text-blue-500 transition-colors">رفع فيديو</a></li>
            <li><a href="public.php" class="hover:text-blue-500 transition-colors">فيديوهات عامة</a></li>
          </ul>
        </div>
        <div>
          <h5 class="font-bold text-slate-900 mb-4 text-right">تواصل معنا</h5>
          <ul class="space-y-2 text-sm text-slate-500 text-right">
            <li class="flex items-center justify-end gap-2"><i class="fas fa-envelope text-blue-500"></i> satm5035@gmail.com</li>
            <li class="flex items-center justify-end gap-2"><i class="fas fa-phone text-blue-500"></i> +216 55912216</li>
            <li class="flex items-center justify-end gap-4 mt-4">
              <a href="#" class="text-slate-400 hover:text-blue-600 transition-colors"><i class="fab fa-facebook text-lg"></i></a>
              <a href="#" class="text-slate-400 hover:text-sky-400 transition-colors"><i class="fab fa-twitter text-lg"></i></a>
              <a href="#" class="text-slate-400 hover:text-pink-500 transition-colors"><i class="fab fa-instagram text-lg"></i></a>
            </li>
          </ul>
        </div>
        <div>
          <h5 class="font-bold text-slate-900 mb-4 text-right">عن الموقع</h5>
          <p class="text-sm text-slate-500 leading-relaxed text-right">منصة لمشاركة الفيديوهات والقصص مع الأصدقاء والعائلة. استمتع بتجربة اجتماعية فريدة.</p>
        </div>
      </div>
      <div class="mt-8 pt-8 border-t border-slate-50 flex flex-col md:flex-row justify-between items-center gap-4">
        <p class="text-xs text-slate-400">© <?php echo date('Y'); ?> Mohamed Aroussi. جميع الحقوق محفوظة.</p>
        <div class="flex gap-6 text-xs text-slate-400">
          <a href="#" class="hover:text-slate-600">Privacy Policy</a>
          <a href="#" class="hover:text-slate-600">Terms of Service</a>
        </div>
      </div>
    </div>
  </footer>

  <!-- Bootstrap JS Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <!-- Theme JS -->
  <script src="js/theme.js"></script>

  <?php if (isset($additionalJs)): ?>
    <?php foreach ($additionalJs as $js): ?>
      <script src="<?php echo $js; ?>"></script>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (isset($inlineJs)): ?>
    <script>
      <?php echo $inlineJs; ?>
    </script>
  <?php endif; ?>

  <!-- Service Worker Registration -->
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function() {
        navigator.serviceWorker.register('service-worker.js')
          .then(function(registration) {
            console.log('ServiceWorker registration successful with scope: ', registration.scope);
          }, function(err) {
            console.log('ServiceWorker registration failed: ', err);
          });
      });
    }
  </script>
</body>
</html>
