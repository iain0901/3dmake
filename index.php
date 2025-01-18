<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // 初始化變數
    $model_requests = [];
    $print_requests = [];
    $active_creators = [];
    $active_printers = [];
    
    // 獲取最新的模型請求
    $stmt = $conn->prepare("
        SELECT p.*, u.username, u.nickname,
               (SELECT COUNT(*) FROM comments WHERE project_id = p.project_id) as comment_count
        FROM projects p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.type_id = 1 AND p.status = 'pending'
        ORDER BY p.created_at DESC
        LIMIT 6
    ");
    $stmt->execute();
    $model_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 獲取最新的代印請求
    $stmt = $conn->prepare("
        SELECT p.*, u.username, u.nickname
        FROM projects p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.type_id = 2 AND p.status = 'pending'
        ORDER BY p.created_at DESC
        LIMIT 6
    ");
    $stmt->execute();
    $print_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 獲取活躍模型創作者
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.nickname,
               COUNT(p.project_id) as completed_count,
               AVG(p.rating) as avg_rating
        FROM users u
        JOIN projects p ON u.user_id = p.creator_id
        WHERE p.type_id = 1 AND p.status = 'completed'
        GROUP BY u.user_id
        ORDER BY completed_count DESC
        LIMIT 3
    ");
    $stmt->execute();
    $active_creators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 獲取活躍代印者
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.nickname,
               COUNT(DISTINCT pr.printer_id) as printer_count,
               COUNT(DISTINCT p.project_id) as completed_count,
               AVG(p.rating) as avg_rating
        FROM users u
        JOIN printers pr ON u.user_id = pr.user_id
        LEFT JOIN projects p ON u.user_id = p.creator_id AND p.type_id = 2
        WHERE p.status = 'completed' OR pr.status = 'available'
        GROUP BY u.user_id
        ORDER BY completed_count DESC
        LIMIT 3
    ");
    $stmt->execute();
    $active_printers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    set_flash_message('danger', '載入資料時發生錯誤，請稍後再試');
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3D列印服務平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('assets/images/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            padding: 100px 0;
            margin-bottom: 2rem;
            color: white;
        }
        .feature-card {
            border: none;
            border-radius: 15px;
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <?php include 'templates/navbar.php'; ?>
    
    <div class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">歡迎來到3D列印服務平台</h1>
            <p class="lead mb-4">無論您需要3D模型設計或代印服務，我們都能幫您找到最適合的合作夥伴</p>
            <?php if (!is_logged_in()): ?>
                <div class="mb-4">
                    <a href="register.php" class="btn btn-primary btn-lg mx-2">立即註冊</a>
                    <a href="login.php" class="btn btn-outline-light btn-lg mx-2">登入</a>
                </div>
            <?php endif; ?>
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="fas fa-cube fa-3x mb-3 text-primary"></i>
                            <h3>尋找3D模型</h3>
                            <p class="text-muted">需要客製化的3D模型？讓專業設計師幫您實現想法</p>
                            <a href="projects/create.php?type=model" class="btn btn-primary">發布模型需求</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="fas fa-print fa-3x mb-3 text-primary"></i>
                            <h3>尋找代印服務</h3>
                            <p class="text-muted">已有3D模型？找專業代印商完成您的作品</p>
                            <a href="projects/create.php?type=print" class="btn btn-primary">發布代印需求</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- 最新模型需求 -->
        <section class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>最新模型需求</h2>
                <a href="projects/index.php?type=model" class="btn btn-outline-primary">查看全部</a>
            </div>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($model_requests as $request): ?>
                    <div class="col">
                        <div class="card h-100">
                            <?php if ($request['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($request['image_url']); ?>" 
                                     class="card-img-top" alt="參考圖"
                                     style="height: 200px; object-fit: cover;">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($request['title']); ?></h5>
                                <p class="card-text text-muted">
                                    <?php echo htmlspecialchars(mb_substr($request['description'], 0, 100)) . '...'; ?>
                                </p>
                                <?php if ($request['budget']): ?>
                                    <p class="card-text">
                                        <i class="fas fa-dollar-sign text-success"></i>
                                        預算: <?php echo number_format($request['budget']); ?> 元
                                    </p>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        由 <?php echo htmlspecialchars($request['nickname'] ?: $request['username']); ?> 發布
                                    </small>
                                    <a href="projects/view.php?id=<?php echo $request['project_id']; ?>" 
                                       class="btn btn-primary btn-sm">查看詳情</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        
        <!-- 最新代印需求 -->
        <section class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>最新代印需求</h2>
                <a href="projects/index.php?type=print" class="btn btn-outline-primary">查看全部</a>
            </div>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($print_requests as $request): ?>
                    <div class="col">
                        <div class="card h-100">
                            <?php if ($request['model_file']): ?>
                                <div class="card-img-top bg-light text-center py-4">
                                    <i class="fas fa-cube fa-4x text-primary"></i>
                                    <p class="mt-2 mb-0">已上傳模型檔案</p>
                                </div>
                            <?php elseif ($request['model_url']): ?>
                                <div class="card-img-top bg-light text-center py-4">
                                    <i class="fas fa-link fa-4x text-primary"></i>
                                    <p class="mt-2 mb-0">已提供模型連結</p>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($request['title']); ?></h5>
                                <p class="card-text text-muted">
                                    <?php echo htmlspecialchars(mb_substr($request['print_requirements'], 0, 100)) . '...'; ?>
                                </p>
                                <?php if ($request['budget']): ?>
                                    <p class="card-text">
                                        <i class="fas fa-dollar-sign text-success"></i>
                                        預算: <?php echo number_format($request['budget']); ?> 元
                                    </p>
                                <?php endif; ?>
                                <?php if ($request['deadline']): ?>
                                    <p class="card-text">
                                        <i class="fas fa-calendar text-info"></i>
                                        期限: <?php echo date('Y/m/d', strtotime($request['deadline'])); ?>
                                    </p>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        由 <?php echo htmlspecialchars($request['nickname'] ?: $request['username']); ?> 發布
                                    </small>
                                    <a href="projects/view.php?id=<?php echo $request['project_id']; ?>" 
                                       class="btn btn-primary btn-sm">查看詳情</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        
        <!-- 活躍創作者和代印者 -->
        <div class="row">
            <!-- 活躍模型創作者 -->
            <div class="col-md-6 mb-5">
                <h2 class="mb-4">活躍模型創作者</h2>
                <?php foreach ($active_creators as $creator): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <?php echo htmlspecialchars($creator['nickname'] ?: $creator['username']); ?>
                                </h5>
                                <span class="badge bg-success">
                                    已完成 <?php echo $creator['completed_count']; ?> 個模型
                                </span>
                            </div>
                            <?php if ($creator['avg_rating']): ?>
                                <div class="mt-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= round($creator['avg_rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                    <small class="text-muted ms-2">
                                        (<?php echo number_format($creator['avg_rating'], 1); ?>)
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- 活躍代印者 -->
            <div class="col-md-6 mb-5">
                <h2 class="mb-4">活躍代印者</h2>
                <?php foreach ($active_printers as $printer): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <?php echo htmlspecialchars($printer['nickname'] ?: $printer['username']); ?>
                                </h5>
                                <span class="badge bg-success">
                                    <?php echo $printer['printer_count']; ?> 台印表機
                                </span>
                            </div>
                            <p class="card-text mb-2">
                                已完成 <?php echo $printer['completed_count']; ?> 個代印專案
                            </p>
                            <?php if ($printer['avg_rating']): ?>
                                <div>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= round($printer['avg_rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                    <small class="text-muted ms-2">
                                        (<?php echo number_format($printer['avg_rating'], 1); ?>)
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 