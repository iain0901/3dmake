<?php
// 資料庫設定
define('DB_HOST', 'localhost');
define('DB_NAME', '3dstumake');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// 網站設定
define('SITE_NAME', '3D列印服務平台');
define('SITE_URL', 'http://localhost/3dstu');
define('UPLOAD_PATH', __DIR__ . '/../uploads');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// 印幣設定
define('COIN_EXCHANGE_RATE', 100); // 1元 = 100印幣
define('MIN_DEPOSIT', 100); // 最低儲值金額
define('MAX_DEPOSIT', 10000); // 最高儲值金額

// 安全設定
define('SESSION_LIFETIME', 7200); // 2小時
define('REMEMBER_ME_LIFETIME', 30 * 24 * 3600); // 30天
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 15 * 60); // 15分鐘

// 初始化session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 設定時區
date_default_timezone_set('Asia/Taipei');

// 錯誤處理
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// 確保日誌目錄存在
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

// 確保上傳目錄存在
$upload_dirs = [
    UPLOAD_PATH . '/images',
    UPLOAD_PATH . '/models'
];

foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
} 