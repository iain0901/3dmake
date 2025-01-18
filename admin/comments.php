<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once 'includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// 處理留言操作
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $comment_id = (int)$_POST['comment_id'];
    
    if ($_POST['action'] == 'delete') {
        $stmt = $conn->prepare("DELETE FROM comments WHERE comment_id = ?");
        $stmt->execute([$comment_id]);
    }
}

// 分頁和搜索
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = '';
$params = [];

if ($search) {
    $where = "WHERE c.content LIKE ? OR w.title LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// 獲取留言列表
$query = "
    SELECT c.*, u.email as user_email, w.title as wish_title, w.wish_id
    FROM comments c
    JOIN users u ON c.user_id = u.user_id
    JOIN wishes w ON c.wish_id = w.wish_id
    $where
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 獲取總數
$total_stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM comments c
    JOIN wishes w ON c.wish_id = w.wish_id
    $where
");
$total_stmt->execute($search ? ["%$search%", "%$search%"] : []);
$total_comments = $total_stmt->fetchColumn();
$total_pages = ceil($total_comments / $per_page);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>留言管理</h1>
    
    <form class="d-flex">
        <input type="search" name="search" class="form-control me-2" 
               placeholder="搜索留言..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn btn-outline-primary">搜索</button>
    </form>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>許願標題</th>
                        <th>留言內容</th>
                        <th>發布者</th>
                        <th>發布時間</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comments as $comment): ?>
                        <tr>
                            <td><?php echo $comment['comment_id']; ?></td>
                            <td>
                                <a href="/wishes/view.php?id=<?php echo $comment['wish_id']; ?>" target="_blank">
                                    <?php echo htmlspecialchars($comment['wish_title']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars(substr($comment['content'], 0, 100)) . '...'; ?></td>
                            <td><?php echo htmlspecialchars($comment['user_email']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?></td>
                            <td>
                                <form method="POST" class="d-inline" 
                                      onsubmit="return confirm('確定要刪除此留言嗎？');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">刪除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 分頁 -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 