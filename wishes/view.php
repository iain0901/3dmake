<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$wish_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$wish_id) {
    set_flash_message('danger', '無效的願望ID');
    redirect('/wishes/');
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // 獲取願望詳情
    $stmt = $conn->prepare("
        SELECT w.*, u.username, u.nickname as user_nickname,
               c.username as creator_username, c.nickname as creator_nickname
        FROM wishes w
        JOIN users u ON w.user_id = u.user_id
        LEFT JOIN users c ON w.creator_id = c.user_id
        WHERE w.wish_id = ?
    ");
    $stmt->execute([$wish_id]);
    $wish = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$wish) {
        set_flash_message('danger', '找不到此願望');
        redirect('/wishes/');
    }
    
    // 獲取願望標籤
    $stmt = $conn->prepare("
        SELECT tag FROM wish_tags WHERE wish_id = ?
    ");
    $stmt->execute([$wish_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 獲取評論
    $stmt = $conn->prepare("
        SELECT c.*, u.username, u.nickname, u.avatar_url
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.wish_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$wish_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 處理評論提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'comment':
                    if (!isset($_POST['content']) || trim($_POST['content']) === '') {
                        set_flash_message('danger', '請輸入評論內容');
                        break;
                    }
                    
                    $content = sanitize_input($_POST['content']);
                    
                    $stmt = $conn->prepare("
                        INSERT INTO comments (wish_id, user_id, content, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$wish_id, $_SESSION['user_id'], $content]);
                    
                    // 如果不是自己的願望，發送通知
                    if ($wish['user_id'] != $_SESSION['user_id']) {
                        $stmt = $conn->prepare("
                            INSERT INTO notifications (user_id, type, content, related_id, created_at)
                            VALUES (?, 'comment', ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $wish['user_id'],
                            $_SESSION['username'] . ' 在你的願望中發表了評論',
                            $wish_id
                        ]);
                    }
                    
                    redirect("/wishes/view.php?id=$wish_id#comments");
                    break;
                    
                case 'accept':
                    if ($_SESSION['role'] === 'creator' && $wish['status'] === 'pending') {
                        $stmt = $conn->prepare("
                            UPDATE wishes 
                            SET status = 'accepted', creator_id = ?, accepted_at = NOW()
                            WHERE wish_id = ?
                        ");
                        $stmt->execute([$_SESSION['user_id'], $wish_id]);
                        
                        // 發送通知
                        $stmt = $conn->prepare("
                            INSERT INTO notifications (user_id, type, content, related_id, created_at)
                            VALUES (?, 'wish_accepted', ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $wish['user_id'],
                            $_SESSION['username'] . ' 接受了你的願望',
                            $wish_id
                        ]);
                        
                        set_flash_message('success', '已接受此願望');
                        redirect("/wishes/view.php?id=$wish_id");
                    }
                    break;
                    
                case 'complete':
                    if ($wish['creator_id'] == $_SESSION['user_id'] && $wish['status'] === 'accepted') {
                        $stmt = $conn->prepare("
                            UPDATE wishes 
                            SET status = 'completed', completed_at = NOW()
                            WHERE wish_id = ?
                        ");
                        $stmt->execute([$wish_id]);
                        
                        // 發送通知
                        $stmt = $conn->prepare("
                            INSERT INTO notifications (user_id, type, content, related_id, created_at)
                            VALUES (?, 'wish_completed', ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $wish['user_id'],
                            $_SESSION['username'] . ' 完成了你的願望',
                            $wish_id
                        ]);
                        
                        set_flash_message('success', '已標記願望為完成');
                        redirect("/wishes/view.php?id=$wish_id");
                    }
                    break;
            }
        }
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    set_flash_message('danger', '載入資料時發生錯誤');
    redirect('/wishes/');
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($wish['title']); ?> - 3D列印許願平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .wish-image {
            max-height: 400px;
            object-fit: contain;
        }
        .comment-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
        }
        .rating {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>
    
    <div class="container py-4">
        <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <?php if ($wish['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($wish['image_url']); ?>" 
                             class="card-img-top wish-image" alt="願望圖片">
                    <?php endif; ?>
                    <div class="card-body">
                        <h1 class="card-title h2"><?php echo htmlspecialchars($wish['title']); ?></h1>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                由 <?php echo htmlspecialchars($wish['user_nickname'] ?? $wish['username']); ?> 發布
                                <span class="text-muted">
                                    <?php echo date('Y-m-d H:i', strtotime($wish['created_at'])); ?>
                                </span>
                            </div>
                            <span class="badge bg-<?php echo match($wish['status']) {
                                'pending' => 'warning',
                                'accepted' => 'info',
                                'completed' => 'success',
                                default => 'secondary'
                            }; ?>">
                                <?php echo match($wish['status']) {
                                    'pending' => '等待中',
                                    'accepted' => '製作中',
                                    'completed' => '已完成',
                                    default => '未知'
                                }; ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($tags)): ?>
                            <div class="mb-3">
                                <?php foreach ($tags as $tag): ?>
                                    <a href="/wishes/?search=<?php echo urlencode($tag); ?>" 
                                       class="badge bg-secondary text-decoration-none">
                                        <?php echo htmlspecialchars($tag); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <p class="card-text">
                            <?php echo nl2br(htmlspecialchars($wish['description'])); ?>
                        </p>
                        
                        <?php if ($wish['status'] === 'completed' && $wish['completed_image_url']): ?>
                            <div class="mt-4">
                                <h5>完成作品</h5>
                                <img src="<?php echo htmlspecialchars($wish['completed_image_url']); ?>" 
                                     class="img-fluid" alt="完成作品">
                                <?php if ($wish['rating']): ?>
                                    <div class="rating mt-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $wish['rating'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (is_logged_in()): ?>
                            <div class="mt-4">
                                <?php if ($_SESSION['role'] === 'creator' && $wish['status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-check me-2"></i>接受願望
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($wish['creator_id'] == $_SESSION['user_id'] && $wish['status'] === 'accepted'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="complete">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check-double me-2"></i>標記為完成
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 評論區 -->
                <div class="card" id="comments">
                    <div class="card-body">
                        <h5 class="card-title mb-4">評論 (<?php echo count($comments); ?>)</h5>
                        
                        <?php if (is_logged_in()): ?>
                            <form method="POST" class="mb-4">
                                <input type="hidden" name="action" value="comment">
                                <div class="mb-3">
                                    <textarea class="form-control" name="content" rows="3" 
                                              placeholder="寫下你的評論..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>發表評論
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info">
                                請<a href="/login.php" class="alert-link">登入</a>後發表評論
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($comments)): ?>
                            <p class="text-muted">目前還沒有評論</p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="d-flex mb-4">
                                    <img src="<?php echo $comment['avatar_url'] ?? '/assets/images/default-avatar.png'; ?>" 
                                         class="comment-avatar me-3" alt="用戶頭像">
                                    <div>
                                        <div class="mb-1">
                                            <strong>
                                                <?php echo htmlspecialchars($comment['nickname'] ?? $comment['username']); ?>
                                            </strong>
                                            <small class="text-muted ms-2">
                                                <?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-0">
                                            <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <?php if ($wish['status'] !== 'pending'): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">製作資訊</h5>
                            <p class="mb-0">
                                製作者：
                                <a href="/user/profile.php?id=<?php echo $wish['creator_id']; ?>" 
                                   class="text-decoration-none">
                                    <?php echo htmlspecialchars($wish['creator_nickname'] ?? $wish['creator_username']); ?>
                                </a>
                            </p>
                            <?php if ($wish['accepted_at']): ?>
                                <p class="mb-0">
                                    接受時間：<?php echo date('Y-m-d H:i', strtotime($wish['accepted_at'])); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($wish['completed_at']): ?>
                                <p class="mb-0">
                                    完成時間：<?php echo date('Y-m-d H:i', strtotime($wish['completed_at'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">相關願望</h5>
                        <?php
                        // 獲取相關願望（相同標籤）
                        if (!empty($tags)) {
                            $placeholders = str_repeat('?,', count($tags) - 1) . '?';
                            $stmt = $conn->prepare("
                                SELECT DISTINCT w.wish_id, w.title
                                FROM wishes w
                                JOIN wish_tags wt ON w.wish_id = wt.wish_id
                                WHERE wt.tag IN ($placeholders)
                                AND w.wish_id != ?
                                LIMIT 5
                            ");
                            $params = array_merge($tags, [$wish_id]);
                            $stmt->execute($params);
                            $related_wishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (!empty($related_wishes)): ?>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($related_wishes as $related): ?>
                                        <li class="mb-2">
                                            <a href="/wishes/view.php?id=<?php echo $related['wish_id']; ?>" 
                                               class="text-decoration-none">
                                                <?php echo htmlspecialchars($related['title']); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">沒有相關願望</p>
                            <?php endif;
                        } else { ?>
                            <p class="text-muted mb-0">沒有相關願望</p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../templates/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 