<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    set_flash_message('warning', '請先登入');
    redirect('../login.php');
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $user_id = $_SESSION['user_id'];
    
    // 獲取用戶資料
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('找不到用戶資料');
    }
    
    // 處理表單提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validate_csrf_token();
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_profile':
                $nickname = sanitize_input($_POST['nickname']);
                $bio = sanitize_input($_POST['bio']);
                
                // 更新用戶資料
                $stmt = $conn->prepare("UPDATE users SET nickname = ?, bio = ? WHERE user_id = ?");
                $stmt->execute([$nickname, $bio, $user_id]);
                
                set_flash_message('success', '個人資料已更新');
                redirect('profile.php');
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // 驗證當前密碼
                if (!password_verify($current_password, $user['password'])) {
                    throw new Exception('當前密碼錯誤');
                }
                
                // 驗證新密碼
                if (strlen($new_password) < 8) {
                    throw new Exception('新密碼至少需要8個字元');
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception('新密碼與確認密碼不符');
                }
                
                // 更新密碼
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->execute([$password_hash, $user_id]);
                
                set_flash_message('success', '密碼已更新');
                redirect('profile.php');
                break;
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    set_flash_message('danger', $e->getMessage());
    redirect('profile.php');
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>個人檔案 - 3D列印許願平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../templates/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <?php if ($flash = get_flash_message()): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0">個人資料</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="mb-3">
                                <label class="form-label">電子郵件</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">用戶名稱</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label for="nickname" class="form-label">暱稱</label>
                                <input type="text" class="form-control" id="nickname" name="nickname" 
                                       value="<?php echo htmlspecialchars($user['nickname'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="bio" class="form-label">個人簡介</label>
                                <textarea class="form-control" id="bio" name="bio" rows="3"><?php 
                                    echo htmlspecialchars($user['bio'] ?? ''); 
                                ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">更新資料</button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">修改密碼</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">當前密碼</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">新密碼</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">密碼至少需要8個字元</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">確認新密碼</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">更新密碼</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../templates/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 