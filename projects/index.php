<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // 獲取篩選參數
    $page = max(1, intval($_GET['page'] ?? 1));
    $type = $_GET['type'] ?? 'all';
    $status = $_GET['status'] ?? 'all';
    $usage = $_GET['usage'] ?? 'all';
    $sort = $_GET['sort'] ?? 'newest';
    $search = trim($_GET['search'] ?? '');
    $per_page = 12;
    
    // 構建查詢條件
    $where_conditions = ['1=1'];
    $params = [];
    
    if ($type !== 'all') {
        $where_conditions[] = 'pt.name = ?';
        $params[] = $type;
    }
    
    if ($status !== 'all') {
        $where_conditions[] = 'p.status = ?';
        $params[] = $status;
    }
    
    if ($usage !== 'all') {
        $where_conditions[] = 'pu.usage_type_id = ?';
        $params[] = $usage;
    }
    
    if (!empty($search)) {
        $where_conditions[] = '(p.title LIKE ? OR p.description LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // 計算總數
    $count_sql = "
        SELECT COUNT(DISTINCT p.project_id) as total
        FROM projects p
        JOIN project_types pt ON p.type_id = pt.type_id
        LEFT JOIN project_usages pu ON p.project_id = pu.project_id
        WHERE " . implode(' AND ', $where_conditions);
    
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $per_page);
    $page = min($page, $total_pages);
    
    // 排序條件
    $order_by = match($sort) {
        'reward_high' => 'p.reward_amount DESC',
        'reward_low' => 'p.reward_amount ASC',
        'budget_high' => 'p.budget DESC',
        'budget_low' => 'p.budget ASC',
        'deadline' => 'p.deadline ASC',
        'oldest' => 'p.created_at ASC',
        default => 'p.created_at DESC'
    };
    
    // 獲取專案列表
    $sql = "
        SELECT DISTINCT 
            p.*, pt.name as type_name, u.username,
            COUNT(DISTINCT c.comment_id) as comment_count,
            COUNT(DISTINCT q.quote_id) as quote_count,
            GROUP_CONCAT(DISTINCT ut.description) as usages
        FROM projects p
        JOIN project_types pt ON p.type_id = pt.type_id
        JOIN users u ON p.user_id = u.user_id
        LEFT JOIN project_usages pu ON p.project_id = pu.project_id
        LEFT JOIN usage_types ut ON pu.usage_type_id = ut.type_id
        LEFT JOIN comments c ON p.project_id = c.project_id
        LEFT JOIN quotes q ON p.project_id = q.project_id
        WHERE " . implode(' AND ', $where_conditions) . "
        GROUP BY p.project_id
        ORDER BY $order_by
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $per_page;
    $params[] = ($page - 1) * $per_page;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 獲取用途類型列表（用於篩選）
    $stmt = $conn->prepare("SELECT * FROM usage_types ORDER BY type_id");
    $stmt->execute();
    $usage_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    set_flash_message('danger', '載入專案列表時發生錯誤，請稍後再試');
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>專案列表 - 3D列印服務平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .project-card {
            height: 100%;
            transition: transform 0.2s;
        }
        .project-card:hover {
            transform: translateY(-5px);
        }
        .project-image {
            height: 200px;
            object-fit: cover;
        }
        .badge-outline {
            background-color: transparent;
            border: 1px solid currentColor;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>
    
    <div class="container py-4">
        <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="h2">專案列表</h1>
            </div>
            <div class="col-md-4 text-end">
                <div class="btn-group">
                    <a href="create.php?type=model" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> 發布模型需求
                    </a>
                    <a href="create.php?type=print" class="btn btn-outline-primary">
                        <i class="bi bi-plus-lg"></i> 發布代印需求
                    </a>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">搜索與篩選</h5>
                        <form method="GET" class="mb-4">
                            <div class="mb-3">
                                <label for="search" class="form-label">關鍵字搜索</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="搜索專案標題或描述...">
                            </div>
                            
                            <div class="mb-3">
                                <label for="type" class="form-label">專案類型</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>全部類型</option>
                                    <option value="model_request" <?php echo $type === 'model_request' ? 'selected' : ''; ?>>
                                        模型需求
                                    </option>
                                    <option value="print_request" <?php echo $type === 'print_request' ? 'selected' : ''; ?>>
                                        代印需求
                                    </option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">專案狀態</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>全部狀態</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>等待中</option>
                                    <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>進行中</option>
                                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>已完成</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="usage" class="form-label">用途</label>
                                <select class="form-select" id="usage" name="usage">
                                    <option value="all" <?php echo $usage === 'all' ? 'selected' : ''; ?>>全部用途</option>
                                    <?php foreach ($usage_types as $ut): ?>
                                        <option value="<?php echo $ut['type_id']; ?>" 
                                                <?php echo $usage == $ut['type_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ut['description']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="sort" class="form-label">排序方式</label>
                                <select class="form-select" id="sort" name="sort">
                                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>最新發布</option>
                                    <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>最早發布</option>
                                    <option value="reward_high" <?php echo $sort === 'reward_high' ? 'selected' : ''; ?>>懸賞金額高到低</option>
                                    <option value="reward_low" <?php echo $sort === 'reward_low' ? 'selected' : ''; ?>>懸賞金額低到高</option>
                                    <option value="budget_high" <?php echo $sort === 'budget_high' ? 'selected' : ''; ?>>預算高到低</option>
                                    <option value="budget_low" <?php echo $sort === 'budget_low' ? 'selected' : ''; ?>>預算低到高</option>
                                    <option value="deadline" <?php echo $sort === 'deadline' ? 'selected' : ''; ?>>即將截止</option>
                                </select>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">套用篩選</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-9">
                <?php if (empty($projects)): ?>
                    <div class="alert alert-info">
                        沒有找到符合條件的專案。
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                        <?php foreach ($projects as $project): ?>
                            <div class="col">
                                <div class="card project-card h-100">
                                    <?php if ($project['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($project['image_url']); ?>" 
                                             class="card-img-top project-image" alt="專案圖片">
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <a href="view.php?id=<?php echo $project['project_id']; ?>" 
                                               class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($project['title']); ?>
                                            </a>
                                        </h5>
                                        
                                        <div class="mb-2">
                                            <span class="badge bg-<?php echo $project['type_name'] === 'model_request' ? 'primary' : 'success'; ?>">
                                                <?php echo $project['type_name'] === 'model_request' ? '模型需求' : '代印需求'; ?>
                                            </span>
                                            
                                            <span class="badge bg-<?php 
                                                echo match($project['status']) {
                                                    'pending' => 'warning',
                                                    'in_progress' => 'info',
                                                    'completed' => 'success',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php 
                                                    echo match($project['status']) {
                                                        'pending' => '等待中',
                                                        'in_progress' => '進行中',
                                                        'completed' => '已完成',
                                                        default => '未知狀態'
                                                    };
                                                ?>
                                            </span>
                                        </div>
                                        
                                        <p class="card-text text-muted small">
                                            <?php echo mb_substr(htmlspecialchars($project['description']), 0, 100); ?>...
                                        </p>
                                        
                                        <?php if ($project['usages']): ?>
                                            <div class="mb-2">
                                                <?php foreach (explode(',', $project['usages']) as $usage): ?>
                                                    <span class="badge badge-outline text-primary">
                                                        <?php echo htmlspecialchars($usage); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <div class="small text-muted">
                                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($project['username']); ?>
                                            </div>
                                            <div class="small text-muted">
                                                <?php if ($project['reward_amount'] > 0): ?>
                                                    <span class="text-warning">
                                                        <i class="bi bi-coin"></i> <?php echo number_format($project['reward_amount']); ?> 印幣
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <div class="small text-muted">
                                                <i class="bi bi-chat"></i> <?php echo $project['comment_count']; ?>
                                                <i class="bi bi-clipboard-check ms-2"></i> <?php echo $project['quote_count']; ?>
                                            </div>
                                            <div class="small text-muted">
                                                <?php if ($project['deadline']): ?>
                                                    <i class="bi bi-calendar"></i> 
                                                    <?php echo date('Y/m/d', strtotime($project['deadline'])); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
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
                                        <a class="page-link" href="?<?php 
                                            $_GET['page'] = $page - 1;
                                            echo http_build_query($_GET);
                                        ?>">上一頁</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php 
                                            $_GET['page'] = $i;
                                            echo http_build_query($_GET);
                                        ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php 
                                            $_GET['page'] = $page + 1;
                                            echo http_build_query($_GET);
                                        ?>">下一頁</a>
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