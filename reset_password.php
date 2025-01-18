<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

if (!isset($_GET['token'])) {
    redirect('/login.php');
}

$token = $_GET['token'];
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT user_id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->execute([$token]);

if (!$user = $stmt->fetch()) {
    redirect('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } else {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (strlen($password) < 6) {
            $error = '密碼長度至少需要6個字符';
        } elseif ($password !== $confirm_password) {
            $error = '兩次輸入的密碼不相符';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?");
            if ($stmt->execute([$hashed_password, $token])) {
                $success = '密碼已成功重置，請使用新密碼登入';
            } else {
                $error = '重置密碼失敗，請稍後再試';
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
    <title>重置密碼 - 3D列印許願平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">重置密碼</h3>
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
                                <label for="password" class="form-label">新密碼</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">確認新密碼</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">重置密碼</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 