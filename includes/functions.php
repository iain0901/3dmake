<?php

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function is_creator() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'creator';
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function validate_csrf_token() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_message('danger', '無效的請求');
        redirect('/');
    }
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function format_date($date) {
    return date('Y-m-d H:i', strtotime($date));
}

function get_user_notifications_count() {
    if (!is_logged_in()) return 0;
    
    $db = Database::getInstance();
    $stmt = $db->getConnection()->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    return $result['count'];
}

// 錯誤處理
function handle_error($errno, $errstr, $errfile, $errline) {
    $error_message = "Error [$errno] $errstr in $errfile on line $errline";
    error_log($error_message);
    
    if (ini_get('display_errors')) {
        echo "<div style='color:red;'>An error occurred. Please try again later.</div>";
    }
    return true;
}

set_error_handler('handle_error');

// 異常處理
function handle_exception($exception) {
    $error_message = "Exception: " . $exception->getMessage() . 
                    " in " . $exception->getFile() . 
                    " on line " . $exception->getLine();
    error_log($error_message);
    
    if (ini_get('display_errors')) {
        echo "<div style='color:red;'>An error occurred. Please try again later.</div>";
    }
}

set_exception_handler('handle_exception');

function set_remember_me_cookie($user_id, $token) {
    $cookie_options = [
        'expires' => time() + COOKIE_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    
    setcookie('remember_token', $token, $cookie_options);
    setcookie('user_id', $user_id, $cookie_options);
}

function clear_remember_me_cookie() {
    $cookie_options = [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    
    setcookie('remember_token', '', $cookie_options);
    setcookie('user_id', '', $cookie_options);
} 