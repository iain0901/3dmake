<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } elseif (!validate_email($_POST['email'])) {
        $error = '請輸入有效的電子郵件地址';
    } else {
        $email = sanitize_input($_POST['email']);
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($user = $stmt->fetch()) {
            $token = generate_reset_token();
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
            $stmt->execute([$token, $expires, $email]);
            
            if (send_reset_email($email, $token)) {
                $success = '重置密碼連結已發送到您的郵箱';
            } else {
                $error = '發送郵件失敗，請稍後再試';
            }
        } else {
            $error = '找不到此電子郵件地址';
        }
    }
}

$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>忘記密碼 - 3D列印許願平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">忘記密碼</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div class="mb-3">
                                <label for="email" class="form-label">電子郵件</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <img src="captcha.php" alt="驗證碼">
                                <input type="text" class="form-control mt-2" name="captcha" placeholder="請輸入驗證碼" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">發送重置連結</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 