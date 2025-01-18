<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once 'includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// 獲取時間範圍
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// 獲取每日新增數據
$stmt = $conn->prepare("
    SELECT DATE(created_at) as date, 
           COUNT(*) as count,
           'users' as type
    FROM users
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    UNION ALL
    SELECT DATE(created_at) as date,
           COUNT(*) as count,
           'wishes' as type
    FROM wishes
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    UNION ALL
    SELECT DATE(created_at) as date,
           COUNT(*) as count,
           'comments' as type
    FROM comments
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date, type
");

$stmt->execute([
    $start_date, $end_date,
    $start_date, $end_date,
    $start_date, $end_date
]);

$daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 處理數據為圖表格式
$dates = [];
$users_data = [];
$wishes_data = [];
$comments_data = [];

foreach ($daily_data as $row) {
    if (!in_array($row['date'], $dates)) {
        $dates[] = $row['date'];
    }
    
    switch ($row['type']) {
        case 'users':
            $users_data[$row['date']] = $row['count'];
            break;
        case 'wishes':
            $wishes_data[$row['date']] = $row['count'];
            break;
        case 'comments':
            $comments_data[$row['date']] = $row['count'];
            break;
    }
}

// 獲取熱門標籤
$stmt = $conn->query("
    SELECT tags, COUNT(*) as count
    FROM wishes
    WHERE tags IS NOT NULL AND tags != ''
    GROUP BY tags
    ORDER BY count DESC
    LIMIT 10
");
$popular_tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 獲取活躍用戶
$stmt = $conn->query("
    SELECT u.email, COUNT(w.wish_id) as wish_count, COUNT(c.comment_id) as comment_count
    FROM users u
    LEFT JOIN wishes w ON u.user_id = w.user_id
    LEFT JOIN comments c ON u.user_id = c.user_id
    GROUP BY u.user_id
    ORDER BY (COUNT(w.wish_id) + COUNT(c.comment_id)) DESC
    LIMIT 10
");
$active_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>數據分析</h1>
    
    <form class="d-flex">
        <input type="date" name="start_date" class="form-control me-2" 
               value="<?php echo $start_date; ?>">
        <input type="date" name="end_date" class="form-control me-2" 
               value="<?php echo $end_date; ?>">
        <button type="submit" class="btn btn-outline-primary">查看</button>
    </form>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">每日新增數據</h5>
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">熱門標籤</h5>
                <div class="list-group">
                    <?php foreach ($popular_tags as $tag): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <?php echo htmlspecialchars($tag['tags']); ?>
                            <span class="badge bg-primary rounded-pill">
                                <?php echo $tag['count']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">活躍用戶</h5>
                <div class="list-group">
                    <?php foreach ($active_users as $user): ?>
                        <div class="list-group-item">
                            <h6 class="mb-1"><?php echo htmlspecialchars($user['email']); ?></h6>
                            <small class="text-muted">
                                許願：<?php echo $user['wish_count']; ?> |
                                留言：<?php echo $user['comment_count']; ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 繪製圖表
const ctx = document.getElementById('dailyChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($dates); ?>,
        datasets: [{
            label: '新增用戶',
            data: <?php echo json_encode(array_map(function($date) use ($users_data) {
                return $users_data[$date] ?? 0;
            }, $dates)); ?>,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }, {
            label: '新增許願',
            data: <?php echo json_encode(array_map(function($date) use ($wishes_data) {
                return $wishes_data[$date] ?? 0;
            }, $dates)); ?>,
            borderColor: 'rgb(255, 99, 132)',
            tension: 0.1
        }, {
            label: '新增留言',
            data: <?php echo json_encode(array_map(function($date) use ($comments_data) {
                return $comments_data[$date] ?? 0;
            }, $dates)); ?>,
            borderColor: 'rgb(54, 162, 235)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?> 