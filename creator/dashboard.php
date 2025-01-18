<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!is_logged_in() || $_SESSION['role'] !== 'creator') {
    redirect('/login.php');
}

$db = new Database();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];

// 獲取待處理的許願
$stmt = $conn->prepare("
    SELECT w.*, u.email as user_email,
    (SELECT COUNT(*) FROM comments WHERE wish_id = w.wish_id) as comment_count
    FROM wishes w
    JOIN users u ON w.user_id = u.user_id
    WHERE w.status = 'pending'
    ORDER BY w.created_at DESC
");
$stmt->execute();
$pending_wishes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 獲取我接受的許願
$stmt = $conn->prepare("
    SELECT w.*, u.email as user_email
    FROM wishes w
    JOIN users u ON w.user_id = u.user_id
    WHERE w.creator_id = ? AND w.status IN ('processing', 'completed')
    ORDER BY w.status ASC, w.created_at DESC
");
$stmt->execute([$user_id]);
$my_wishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>製作者面板 - 3D列印許願平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../templates/navbar.php'; ?>
    
    <div class="container py-4">
        <h1 class="mb-4">製作者面板</h1>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">待處理的許願</h5>
                        <div class="list-group">
                            <?php foreach ($pending_wishes as $wish): ?>
                                <div class="list-group-item">
                                    <h6 class="mb-1">
                                        <a href="/wishes/view.php?id=<?php echo $wish['wish_id']; ?>">
                                            <?php echo htmlspecialchars($wish['title']); ?>
                                        </a>
                                    </h6>
                                    <p class="mb-1"><?php echo htmlspecialchars(substr($wish['description'], 0, 100)) . '...'; ?></p>
                                    <small class="text-muted">
                                        由 <?php echo htmlspecialchars($wish['user_email']); ?> 發布於 
                                        <?php echo date('Y-m-d H:i', strtotime($wish['created_at'])); ?>
                                    </small>
                                    <div class="mt-2">
                                        <form method="POST" action="/wishes/accept.php" class="d-inline">
                                            <input type="hidden" name="wish_id" value="<?php echo $wish['wish_id']; ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">接受製作</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">我接受的許願</h5>
                        <div class="list-group">
                            <?php foreach ($my_wishes as $wish): ?>
                                <div class="list-group-item">
                                    <h6 class="mb-1">
                                        <a href="/wishes/view.php?id=<?php echo $wish['wish_id']; ?>">
                                            <?php echo htmlspecialchars($wish['title']); ?>
                                        </a>
                                    </h6>
                                    <p class="mb-1"><?php echo htmlspecialchars(substr($wish['description'], 0, 100)) . '...'; ?></p>
                                    <small class="text-muted">
                                        狀態：<?php echo $wish['status'] == 'processing' ? '製作中' : '已完成'; ?>
                                    </small>
                                    <?php if ($wish['status'] == 'processing'): ?>
                                        <div class="mt-2">
                                            <form method="POST" action="/wishes/complete.php" class="d-inline">
                                                <input type="hidden" name="wish_id" value="<?php echo $wish['wish_id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm">標記為完成</button>
                                            </form>
                                            <button type="button" class="btn btn-info btn-sm" 
                                                    onclick="updateProgress(<?php echo $wish['wish_id']; ?>)">
                                                更新進度
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 進度更新模態框 -->
    <div class="modal fade" id="progressModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">更新進度</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="progressForm" method="POST" action="/wishes/update_progress.php">
                        <input type="hidden" name="wish_id" id="progressWishId">
                        <div class="mb-3">
                            <label for="progress" class="form-label">進度說明</label>
                            <textarea class="form-control" id="progress" name="progress" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">更新</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function updateProgress(wishId) {
        document.getElementById('progressWishId').value = wishId;
        new bootstrap.Modal(document.getElementById('progressModal')).show();
    }
    </script>
</body>
</html> 