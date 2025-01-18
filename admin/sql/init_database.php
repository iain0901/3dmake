<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$db = new Database();
$conn = $db->getConnection();

try {
    // 開始事務
    $conn->beginTransaction();

    // 創建用戶表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS users (
            user_id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('user', 'creator', 'admin') DEFAULT 'user',
            email_verified BOOLEAN DEFAULT FALSE,
            avatar_url VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login_at TIMESTAMP NULL,
            failed_login_attempts INT DEFAULT 0,
            account_locked_until TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 創建許願表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS wishes (
            wish_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            image_url VARCHAR(255),
            status ENUM('pending', 'accepted', 'completed', 'rejected') DEFAULT 'pending',
            view_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_updated_at TIMESTAMP NULL,
            completed_image_url VARCHAR(255),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 創建評論表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS comments (
            comment_id INT PRIMARY KEY AUTO_INCREMENT,
            wish_id INT NOT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (wish_id) REFERENCES wishes(wish_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 創建通知表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            notification_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            type ENUM('wish_accepted', 'wish_completed', 'new_comment', 'system') NOT NULL,
            content TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 創建檔案記錄表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS files (
            file_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 創建 API 金鑰表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            key_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            api_key VARCHAR(64) NOT NULL UNIQUE,
            description TEXT,
            active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 創建 API 請求記錄表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS api_requests (
            request_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            endpoint VARCHAR(255),
            method VARCHAR(10),
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 創建 CSRF 令牌表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS csrf_tokens (
            token_id INT PRIMARY KEY AUTO_INCREMENT,
            token VARCHAR(64) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            used BOOLEAN DEFAULT FALSE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 創建索引以提升性能
    $conn->exec("
        CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
        CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
        CREATE INDEX IF NOT EXISTS idx_wishes_status ON wishes(status);
        CREATE INDEX IF NOT EXISTS idx_wishes_created_at ON wishes(created_at);
        CREATE INDEX IF NOT EXISTS idx_comments_created_at ON comments(created_at);
        CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications(user_id, is_read);
        CREATE INDEX IF NOT EXISTS idx_api_keys_key ON api_keys(api_key);
        CREATE INDEX IF NOT EXISTS idx_csrf_tokens_token ON csrf_tokens(token);
    ");

    // 創建預設管理員帳戶
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->exec("
        INSERT IGNORE INTO users (username, email, password, role, email_verified)
        VALUES ('admin', 'admin@3dstumake.com', '$admin_password', 'admin', TRUE)
    ");

    // 提交事務
    $conn->commit();
    echo "資料庫初始化成功！\n";
    echo "預設管理員帳戶：\n";
    echo "用戶名：admin\n";
    echo "密碼：admin123\n";
    echo "請立即登入並修改密碼！";

} catch (Exception $e) {
    // 回滾事務
    $conn->rollBack();
    echo "資料庫初始化錯誤: " . $e->getMessage();
} 