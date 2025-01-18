<footer class="bg-light py-4 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <h5>關於我們</h5>
                <p class="text-muted">
                    3D列印許願平台是一個連接創意與技術的橋樑，
                    讓每個想法都能被實現。
                </p>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <h5>快速連結</h5>
                <ul class="list-unstyled">
                    <li><a href="/wishes/" class="text-decoration-none text-muted">願望列表</a></li>
                    <li><a href="/wishes/completed.php" class="text-decoration-none text-muted">完成作品</a></li>
                    <?php if (!is_logged_in()): ?>
                        <li><a href="/register.php" class="text-decoration-none text-muted">註冊帳號</a></li>
                        <li><a href="/login.php" class="text-decoration-none text-muted">登入</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-md-4">
                <h5>聯絡我們</h5>
                <ul class="list-unstyled text-muted">
                    <li><i class="fas fa-envelope me-2"></i>support@3dstumake.com</li>
                    <li><i class="fas fa-phone me-2"></i>(02) 1234-5678</li>
                    <li><i class="fas fa-map-marker-alt me-2"></i>台北市信義區信義路五段7號</li>
                </ul>
            </div>
        </div>
        <hr>
        <div class="text-center text-muted">
            <small>&copy; <?php echo date('Y'); ?> 3D列印許願平台. All rights reserved.</small>
        </div>
    </div>
</footer> 