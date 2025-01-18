<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="/">3D列印許願平台</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="/notifications/">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge" style="display: none;">
                                0
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/user/profile.php">個人中心</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/logout.php">登出</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/login.php">登入</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/register.php">註冊</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if (is_logged_in()): ?>
<script>
    // 定期檢查新通知
    function checkNotifications() {
        fetch('/api/check_notifications.php')
            .then(response => response.json())
            .then(data => {
                const badge = document.querySelector('.notification-badge');
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count;
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            });
    }

    // 每30秒檢查一次
    setInterval(checkNotifications, 30000);
    checkNotifications(); // 初始檢查
</script>
<?php endif; ?> 