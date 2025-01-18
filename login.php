<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    redirect('/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);

    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            
            if (isset($_POST['remember_me'])) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + COOKIE_LIFETIME);
                
                $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires) VALUES (?, ?, ?)");
                $stmt->execute([$user['user_id'], $token, $expires]);
                
                set_remember_me_cookie($user['user_id'], $token);
            }
            
            set_flash_message('success', '登入成功');
            redirect('/');
        } else {
            error_log("Login failed for user {$email}: Password verification failed");
            set_flash_message('danger', '電子郵件或密碼錯誤');
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        set_flash_message('danger', '登入時發生錯誤，請稍後再試');
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入 - 3D列印許願平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .login-container {
            max-width: 400px;
            margin: 60px auto;
        }
    </style>
</head>
<body>
    <?php include 'templates/navbar.php'; ?>
    
    <div class="container">
        <div class="login-container">
            <h2 class="text-center mb-4">登入</h2>
            
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
                            <label for="email" class="form-label">電子郵件</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">密碼</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me">記住我</label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">登入</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="forgot_password.php" class="text-decoration-none">忘記密碼？</a>
                <span class="mx-2">|</span>
                <a href="register.php" class="text-decoration-none">註冊新帳號</a>
            </div>
        </div>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 