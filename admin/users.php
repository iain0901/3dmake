<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once 'includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// 處理用戶操作
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $user_id = (int)$_POST['user_id'];
    
    switch ($_POST['action']) {
        case 'delete':
            // 刪除用戶相關數據
            $conn->beginTransaction();
            try {
                $conn->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$user_id]);
                $conn->prepare("DELETE FROM comments WHERE user_id = ?")->execute([$user_id]);
                $conn->prepare("DELETE FROM wishes WHERE user_id = ?")->execute([$user_id]);
                $conn->prepare("DELETE FROM users WHERE user_id = ?")->execute([$user_id]);
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollBack();
            }
            break;
            
        case 'change_role':
            $new_role = $_POST['new_role'];
            if (in_array($new_role, ['user', 'creator', 'admin'])) {
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                $stmt->execute([$new_role, $user_id]);
            }
            break;
    }
}

// 分頁設置
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 搜索功能
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = '';
$params = [];

if ($search) {
    $where = "WHERE email LIKE ?";
    $params[] = "%$search%";
}

// 獲取用戶列表
$query = "SELECT * FROM users $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 獲取總數
$total_stmt = $conn->prepare("SELECT COUNT(*) FROM users $where");
$total_stmt->execute($search ? ["%$search%"] : []);
$total_users = $total_stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>用戶管理</h1>
    
    <form class="d-flex">
        <input type="search" name="search" class="form-control me-2" 
               placeholder="搜索用戶..." value="<?php echo htmlspecialchars($search); ?>">
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
                        <th>電子郵件</th>
                        <th>角色</th>
                        <th>註冊時間</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <select name="new_role" class="form-select form-select-sm" 
                                            onchange="this.form.submit()"
                                            style="width: auto;">
                                        <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>
                                            一般用戶
                                        </option>
                                        <option value="creator" <?php echo $user['role'] == 'creator' ? 'selected' : ''; ?>>
                                            模型製作者
                                        </option>
                                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>
                                            管理員
                                        </option>
                                    </select>
                                </form>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                            <td>
                                <form method="POST" class="d-inline" 
                                      onsubmit="return confirm('確定要刪除此用戶嗎？這將同時刪除該用戶的所有數據！');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
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