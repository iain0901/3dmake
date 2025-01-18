<?php
$current_page = basename($_SERVER['PHP_SELF']);

// 獲取未讀通知數量
$unread_notifications = 0;
if (is_logged_in()) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([get_user_id()]);
        $result = $stmt->fetch();
        $unread_notifications = $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
            <?php echo SITE_NAME; ?>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>">
                        <i class="bi bi-house"></i> 首頁
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'projects.php' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>/projects">
                        <i class="bi bi-list-task"></i> 專案列表
                    </a>
                </li>
                
                <?php if (is_logged_in()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-plus-lg"></i> 發布專案
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/projects/create.php?type=model">
                                    <i class="bi bi-box"></i> 發布模型需求
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/projects/create.php?type=print">
                                    <i class="bi bi-printer"></i> 發布代印需求
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="<?php echo SITE_URL; ?>/notifications">
                            <i class="bi bi-bell"></i> 通知
                            <?php if ($unread_notifications > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $unread_notifications; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/coins">
                            <i class="bi bi-coin"></i> 印幣
                            <span class="badge bg-warning text-dark">
                                <?php echo format_coin_amount(get_user_coin_balance(get_user_id())); ?>
                            </span>
                        </a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> 
                            <?php echo htmlspecialchars($_SESSION['username'] ?? '會員'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/user/profile.php">
                                    <i class="bi bi-person"></i> 個人資料
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/user/projects.php">
                                    <i class="bi bi-folder"></i> 我的專案
                                </a>
                            </li>
                            <?php if (is_creator()): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/creator/dashboard.php">
                                        <i class="bi bi-speedometer"></i> 創作者中心
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (is_admin()): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin">
                                        <i class="bi bi-gear"></i> 管理後台
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>/logout.php">
                                    <i class="bi bi-box-arrow-right"></i> 登出
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/login.php">
                            <i class="bi bi-box-arrow-in-right"></i> 登入
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/register.php">
                            <i class="bi bi-person-plus"></i> 註冊
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav> 