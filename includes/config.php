<?php
// 資料庫配置
define('DB_HOST', 'localhost');
define('DB_NAME', '3dstumake');
define('DB_USER', '3dstumake');
define('DB_PASS', 'BCNBhXC5Jy7hc4h4');

// 安全配置
define('CSRF_EXPIRATION', 7200); // 2小時
define('SESSION_LIFETIME', 7200);
define('COOKIE_LIFETIME', 2592000); // 30天
define('API_RATE_LIMIT', 1000); // 每小時請求限制

// 檔案上傳配置
define('MAX_UPLOAD_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_DIR', __DIR__ . '/../uploads');

// 設置時區
date_default_timezone_set('Asia/Taipei');

// 確保日誌目錄存在
$log_dir = __DIR__ . '/../logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}

// 錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', $log_dir . '/error.log');

// 會話安全配置
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

// Cookie 安全配置
ini_set('session.cookie_lifetime', COOKIE_LIFETIME);
session_set_cookie_params([
    'lifetime' => COOKIE_LIFETIME,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// 驗證會話ID
if (!isset($_SESSION['created'])) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > SESSION_LIFETIME) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
} 