<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    redirect('/login.php');
}

$db = new Database();
$conn = $db->getConnection();

// 獲取未讀通知數量
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0
");
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetchColumn();

// 分頁設置
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 獲取通知列表
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$_SESSION['user_id'], $per_page, $offset]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 獲取總數
$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_notifications = $stmt->fetchColumn();
$total_pages = ceil($total_notifications / $per_page);

// 標記所有通知為已讀
$stmt = $conn->prepare("
    UPDATE notifications 
    SET is_read = 1 
    WHERE user_id = ? AND is_read = 0
");
$stmt->execute([$_SESSION['user_id']]);
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>通知中心 - 3D列印許願平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>通知中心</h1>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger"><?php echo $unread_count; ?> 則未讀</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="alert alert-info">
                        暫無通知
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="list-group-item list-group-item-action <?php echo !$notification['is_read'] ? 'bg-light' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="badge bg-primary me-2">新</span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('Y-m-d H:i', strtotime($notification['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="mt-2">
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger delete-notification" 
                                            data-notification-id="<?php echo $notification['notification_id']; ?>">
                                        刪除
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 分頁 -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 刪除通知的確認
        document.querySelectorAll('.delete-notification').forEach(button => {
            button.addEventListener('click', function() {
                if (confirm('確定要刪除這則通知嗎？')) {
                    const notificationId = this.dataset.notificationId;
                    window.location.href = `delete.php?id=${notificationId}`;
                }
            });
        });
    </script>
</body>
</html> 