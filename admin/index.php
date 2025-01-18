<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once 'includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// 獲取統計數據
$stats = [
    'users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'wishes' => $conn->query("SELECT COUNT(*) FROM wishes")->fetchColumn(),
    'comments' => $conn->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
    'pending_wishes' => $conn->query("SELECT COUNT(*) FROM wishes WHERE status = 'pending'")->fetchColumn()
];

// 獲取最近的許願
$recent_wishes = $conn->query("
    SELECT w.*, u.email as user_email 
    FROM wishes w 
    JOIN users u ON w.user_id = u.user_id 
    ORDER BY w.created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// 獲取最近的留言
$recent_comments = $conn->query("
    SELECT c.*, u.email as user_email, w.title as wish_title
    FROM comments c 
    JOIN users u ON c.user_id = u.user_id 
    JOIN wishes w ON c.wish_id = w.wish_id
    ORDER BY c.created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h1 class="mb-4">儀表板</h1>

<!-- 統計卡片 -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">總用戶數</h5>
                <p class="card-text h2"><?php echo $stats['users']; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title">總許願數</h5>
                <p class="card-text h2"><?php echo $stats['wishes']; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title">總留言數</h5>
                <p class="card-text h2"><?php echo $stats['comments']; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title">待處理許願</h5>
                <p class="card-text h2"><?php echo $stats['pending_wishes']; ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- 最近許願 -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">最近許願</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($recent_wishes as $wish): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($wish['title']); ?></h6>
                                    <small class="text-muted">
                                        由 <?php echo htmlspecialchars($wish['user_email']); ?> 發布
                                    </small>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('Y-m-d H:i', strtotime($wish['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 最近留言 -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">最近留言</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($recent_comments as $comment): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($comment['wish_title']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($comment['user_email']); ?> 說：
                                        <?php echo htmlspecialchars(substr($comment['content'], 0, 50)) . '...'; ?>
                                    </small>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 