<?php
$unread_notifications = 0;
if (is_logged_in()) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications = $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="index.php">3D列印許願平台</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="wishes/index.php">願望列表</a>
                </li>
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="wishes/create.php">許願</a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="/wishes/completed.php">完成作品</a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="notifications/index.php">
                            通知
                            <?php if ($unread_notifications > 0): ?>
                                <span class="badge bg-danger"><?php echo $unread_notifications; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="user/profile.php">個人檔案</a></li>
                            <?php if (is_admin()): ?>
                                <li><a class="dropdown-item" href="admin/index.php">管理後台</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">登出</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">登入</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">註冊</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav> 