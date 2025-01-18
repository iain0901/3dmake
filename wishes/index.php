<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // 分頁設置
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 12;
    $offset = ($page - 1) * $per_page;
    
    // 搜尋條件
    $search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
    $status = isset($_GET['status']) ? sanitize_input($_GET['status']) : 'all';
    $sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'newest';
    
    // 構建查詢條件
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(w.title LIKE ? OR w.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($status !== 'all') {
        $where_conditions[] = "w.status = ?";
        $params[] = $status;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // 排序方式
    $order_by = match($sort) {
        'popular' => 'comment_count DESC, w.created_at DESC',
        'oldest' => 'w.created_at ASC',
        default => 'w.created_at DESC'
    };
    
    // 獲取總數
    $count_sql = "
        SELECT COUNT(*) 
        FROM wishes w 
        WHERE $where_clause
    ";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_items = $stmt->fetchColumn();
    
    $total_pages = ceil($total_items / $per_page);
    $page = min($page, $total_pages);
    
    // 獲取願望列表
    $sql = "
        SELECT w.*, u.username, u.nickname,
               (SELECT COUNT(*) FROM comments WHERE wish_id = w.wish_id) as comment_count
        FROM wishes w
        JOIN users u ON w.user_id = u.user_id
        WHERE $where_clause
        ORDER BY $order_by
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $wishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 獲取熱門標籤
    $stmt = $conn->query("
        SELECT tag, COUNT(*) as count
        FROM wish_tags
        GROUP BY tag
        ORDER BY count DESC
        LIMIT 10
    ");
    $popular_tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    set_flash_message('danger', '載入資料時發生錯誤');
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>願望列表 - 3D列印許願平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .tag-cloud .badge {
            margin: 0.2rem;
            font-size: 0.9rem;
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
            <!-- 側邊欄 -->
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">搜尋願望</h5>
                        <form method="GET" action="">
                            <div class="mb-3">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="搜尋願望...">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">狀態</label>
                                <select class="form-select" name="status">
                                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>全部</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>等待中</option>
                                    <option value="accepted" <?php echo $status === 'accepted' ? 'selected' : ''; ?>>已接受</option>
                                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>已完成</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">排序</label>
                                <select class="form-select" name="sort">
                                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>最新</option>
                                    <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>最熱門</option>
                                    <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>最舊</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">搜尋</button>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($popular_tags)): ?>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">熱門標籤</h5>
                            <div class="tag-cloud">
                                <?php foreach ($popular_tags as $tag): ?>
                                    <a href="?search=<?php echo urlencode($tag['tag']); ?>" 
                                       class="badge bg-secondary text-decoration-none">
                                        <?php echo htmlspecialchars($tag['tag']); ?>
                                        <span class="badge bg-light text-dark"><?php echo $tag['count']; ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 主要內容 -->
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>願望列表</h2>
                    <?php if (is_logged_in()): ?>
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>許下願望
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($wishes)): ?>
                    <div class="alert alert-info">
                        目前還沒有符合條件的願望。
                        <?php if (is_logged_in()): ?>
                            <a href="create.php" class="alert-link">成為第一個許願的人！</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($wishes as $wish): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100">
                                    <?php if ($wish['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($wish['image_url']); ?>" 
                                             class="card-img-top" alt="願望圖片">
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($wish['title']); ?></h5>
                                        <p class="card-text">
                                            <?php echo nl2br(htmlspecialchars(substr($wish['description'], 0, 100))); ?>...
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                由 <?php echo htmlspecialchars($wish['nickname'] ?? $wish['username']); ?> 發布
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-comments"></i> <?php echo $wish['comment_count']; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <a href="view.php?id=<?php echo $wish['wish_id']; ?>" 
                                           class="btn btn-primary btn-sm">查看詳情</a>
                                        <?php if ($wish['status'] !== 'pending'): ?>
                                            <span class="badge bg-<?php echo match($wish['status']) {
                                                'accepted' => 'info',
                                                'completed' => 'success',
                                                default => 'secondary'
                                            }; ?> float-end">
                                                <?php echo match($wish['status']) {
                                                    'accepted' => '製作中',
                                                    'completed' => '已完成',
                                                    default => '未知'
                                                }; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>">
                                            上一頁
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>">
                                            下一頁
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../templates/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 