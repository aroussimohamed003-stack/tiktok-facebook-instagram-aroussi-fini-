  <!-- Footer -->
  <footer class="mt-5 py-4 text-center">
    <div class="container">
      <div class="row">
        <div class="col-md-4 mb-3 mb-md-0">
          <h5>روابط سريعة</h5>
          <ul class="list-unstyled">
            <li><a href="indexmo.php">الرئيسية</a></li>

            <li><a href="uplod-profile.php">رفع فيديو</a></li>
          </ul>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
          <h5>تواصل معنا</h5>
          <ul class="list-unstyled">
            <li><i class="fas fa-envelope me-2"></i> satm5035@gmail.com</li>
            <li><i class="fas fa-phone me-2"></i> +216 55912216</li>
            <li>
              <a href="#" class="me-2"><i class="fab fa-facebook"></i></a>
              <a href="#" class="me-2"><i class="fab fa-twitter"></i></a>
              <a href="#" class="me-2"><i class="fab fa-instagram"></i></a>
            </li>
          </ul>
        </div>
        <div class="col-md-4">
          <h5>عن الموقع</h5>
          <p>منصة لمشاركة الفيديوهات والقصص مع الأصدقاء والعائلة.</p>
        </div>
      </div>
      <hr>
      <p class="mb-0">© <?php echo date('Y'); ?> Mohamed Aroussi. جميع الحقوق محفوظة.</p>
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
