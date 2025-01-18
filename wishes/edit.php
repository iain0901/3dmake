<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    redirect('/login.php');
}

$wish_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$wish_id) {
    redirect('index.php');
}

$db = new Database();
$conn = $db->getConnection();

// 獲取許願單資訊
$stmt = $conn->prepare("SELECT * FROM wishes WHERE wish_id = ? AND user_id = ?");
$stmt->execute([$wish_id, $_SESSION['user_id']]);
$wish = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wish) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } else {
        $title = sanitize_input($_POST['title']);
        $description = sanitize_input($_POST['description']);
        $tags = sanitize_input($_POST['tags']);
        
        if (empty($title)) {
            $error = '請填寫許願標題';
        } else {
            // 處理圖片上傳
            $image_url = $wish['image_url'];
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['image']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $upload_dir = '../uploads/wishes/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $new_filename = uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_filename)) {
                        // 刪除舊圖片
                        if ($wish['image_url'] && file_exists('../' . $wish['image_url'])) {
                            unlink('../' . $wish['image_url']);
                        }
                        $image_url = 'uploads/wishes/' . $new_filename;
                    }
                }
            }
            
            $stmt = $conn->prepare("
                UPDATE wishes 
                SET title = ?, description = ?, tags = ?, image_url = ?
                WHERE wish_id = ? AND user_id = ?
            ");
            
            if ($stmt->execute([$title, $description, $tags, $image_url, $wish_id, $_SESSION['user_id']])) {
                $success = '許願單已更新';
                $wish['title'] = $title;
                $wish['description'] = $description;
                $wish['tags'] = $tags;
                $wish['image_url'] = $image_url;
            } else {
                $error = '更新許願單失敗，請稍後再試';
            }
        }
    }
}

$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>編輯許願 - 3D列印許願平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>編輯許願</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mb-3">
                <label for="title" class="form-label">許願標題</label>
                <input type="text" class="form-control" id="title" name="title" 
                       value="<?php echo htmlspecialchars($wish['title']); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">許願描述</label>
                <textarea class="form-control" id="description" name="description" 
                          rows="5"><?php echo htmlspecialchars($wish['description']); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label for="tags" class="form-label">類別標籤</label>
                <input type="text" class="form-control" id="tags" name="tags" 
                       value="<?php echo htmlspecialchars($wish['tags']); ?>"
                       placeholder="使用逗號分隔多個標籤，例如：玩具,工具,裝飾">
            </div>
            
            <div class="mb-3">
                <label for="image" class="form-label">參考圖片</label>
                <?php if ($wish['image_url']): ?>
                    <div class="mb-2">
                        <img src="/<?php echo htmlspecialchars($wish['image_url']); ?>" 
                             alt="當前圖片" style="max-width: 200px;">
                    </div>
                <?php endif; ?>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
            </div>
            
            <div class="mb-3">
                <button type="submit" class="btn btn-primary">更新許願</button>
                <a href="index.php" class="btn btn-secondary">返回</a>
            </div>
        </form>
    </div>
</body>
</html> 