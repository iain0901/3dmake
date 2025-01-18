<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    redirect('/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'user'; // 預設角色

    try {
        // 驗證輸入
        $errors = [];
        
        if (empty($username)) {
            $errors[] = '請輸入使用者名稱';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = '使用者名稱必須在3到50個字元之間';
        }
        
        if (empty($email)) {
            $errors[] = '請輸入電子郵件';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '請輸入有效的電子郵件地址';
        }
        
        if (empty($password)) {
            $errors[] = '請輸入密碼';
        } elseif (strlen($password) < 6) {
            $errors[] = '密碼必須至少6個字元';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = '兩次輸入的密碼不一致';
        }

        if (empty($errors)) {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // 檢查使用者名稱是否已存在
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = '此使用者名稱已被使用';
            }
            
            // 檢查電子郵件是否已存在
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = '此電子郵件已被註冊';
            }
            
            if (empty($errors)) {
                // 創建新用戶
                $stmt = $conn->prepare("
                    INSERT INTO users (username, email, password, role, nickname, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$username, $email, $password_hash, $role, $username]);
                
                set_flash_message('success', '註冊成功！請登入');
                redirect('/login.php');
            }
        }
        
        if (!empty($errors)) {
            set_flash_message('danger', implode('<br>', $errors));
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        set_flash_message('danger', '註冊時發生錯誤，請稍後再試');
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊 - 3D列印許願平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .register-container {
            max-width: 400px;
            margin: 60px auto;
        }
    </style>
</head>
<body>
    <?php include 'templates/navbar.php'; ?>
    
    <div class="container">
        <div class="register-container">
            <h2 class="text-center mb-4">註冊新帳號</h2>
            
            <?php if ($flash = get_flash_message()): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">使用者名稱</label>
                            <input type="text" class="form-control" id="username" name="username" required 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">電子郵件</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">密碼</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">確認密碼</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">註冊</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-3">
                已有帳號？<a href="login.php" class="text-decoration-none">立即登入</a>
            </div>
        </div>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
</html> 