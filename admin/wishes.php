<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once 'includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// 處理許願操作
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $wish_id = (int)$_POST['wish_id'];
    
    switch ($_POST['action']) {
        case 'delete':
            $conn->beginTransaction();
            try {
                // 獲取圖片路徑
                $stmt = $conn->prepare("SELECT image_url FROM wishes WHERE wish_id = ?");
                $stmt->execute([$wish_id]);
                $wish = $stmt->fetch();
                
                // 刪除相關數據
                $conn->prepare("DELETE FROM comments WHERE wish_id = ?")->execute([$wish_id]);
                $conn->prepare("DELETE FROM wishes WHERE wish_id = ?")->execute([$wish_id]);
                
                // 刪除圖片文件
                if ($wish && $wish['image_url'] && file_exists('../' . $wish['image_url'])) {
                    unlink('../' . $wish['image_url']);
                }
                
                $conn->commit();
                log_action('delete_wish', "Deleted wish ID: $wish_id");
            } catch (Exception $e) {
                $conn->rollBack();
                log_action('error', "Failed to delete wish ID: $wish_id. Error: " . $e->getMessage());
            }
            break;
            
        case 'change_status':
            $new_status = $_POST['new_status'];
            if (in_array($new_status, ['pending', 'processing', 'completed'])) {
                $stmt = $conn->prepare("UPDATE wishes SET status = ? WHERE wish_id = ?");
                $stmt->execute([$new_status, $wish_id]);
                log_action('change_wish_status', "Changed wish ID: $wish_id status to: $new_status");
            }
            break;
    }
}

// 分頁和搜索
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(w.title LIKE ? OR w.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where[] = "w.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// 獲取許願列表
$query = "
    SELECT w.*, u.email as user_email,
    (SELECT COUNT(*) FROM comments WHERE wish_id = w.wish_id) as comment_count
    FROM wishes w
    JOIN users u ON w.user_id = u.user_id
    $where_clause
    ORDER BY w.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$wishes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 獲取總數
$count_where = str_replace("w.", "", $where_clause);
$total_stmt = $conn->prepare("SELECT COUNT(*) FROM wishes w $where_clause");
$total_stmt->execute(array_slice($params, 0, -2));
$total_wishes = $total_stmt->fetchColumn();
$total_pages = ceil($total_wishes / $per_page);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>許願管理</h1>
    
    <form class="d-flex">
        <select name="status" class="form-select me-2" style="width: auto;">
            <option value="">所有狀態</option>
            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>等待中</option>
            <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>製作中</option>
            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>已完成</option>
        </select>
        <input type="search" name="search" class="form-control me-2" 
               placeholder="搜索許願..." value="<?php echo htmlspecialchars($search); ?>">
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
                        <th>標題</th>
                        <th>發布者</th>
                        <th>狀態</th>
                        <th>留言數</th>
                        <th>發布時間</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wishes as $wish): ?>
                        <tr>
                            <td><?php echo $wish['wish_id']; ?></td>
                            <td>
                                <a href="/wishes/view.php?id=<?php echo $wish['wish_id']; ?>" target="_blank">
                                    <?php echo htmlspecialchars($wish['title']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($wish['user_email']); ?></td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="change_status">
                                    <input type="hidden" name="wish_id" value="<?php echo $wish['wish_id']; ?>">
                                    <select name="new_status" class="form-select form-select-sm" 
                                            onchange="this.form.submit()"
                                            style="width: auto;">
                                        <option value="pending" <?php echo $wish['status'] == 'pending' ? 'selected' : ''; ?>>
                                            等待中
                                        </option>
                                        <option value="processing" <?php echo $wish['status'] == 'processing' ? 'selected' : ''; ?>>
                                            製作中
                                        </option>
                                        <option value="completed" <?php echo $wish['status'] == 'completed' ? 'selected' : ''; ?>>
                                            已完成
                                        </option>
                                    </select>
                                </form>
                            </td>
                            <td><?php echo $wish['comment_count']; ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($wish['created_at'])); ?></td>
                            <td>
                                <form method="POST" class="d-inline" 
                                      onsubmit="return confirm('確定要刪除此許願嗎？這將同時刪除相關的所有留言！');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="wish_id" value="<?php echo $wish['wish_id']; ?>">
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
                            <a class="page-link" 
                               href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
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