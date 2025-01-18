<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';

$db = new Database();
$conn = $db->getConnection();

try {
    // 開始事務
    $conn->beginTransaction();

    // 創建 API 相關表格
    $conn->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            key_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            api_key VARCHAR(64) NOT NULL UNIQUE,
            description TEXT,
            active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS api_requests (
            request_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            endpoint VARCHAR(255),
            method VARCHAR(10),
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )
    ");

    // 添加用戶表的新欄位
    $conn->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE,
        ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255),
        ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS failed_login_attempts INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS account_locked_until TIMESTAMP NULL
    ");

    // 添加許願表的新欄位
    $conn->exec("
        ALTER TABLE wishes
        ADD COLUMN IF NOT EXISTS view_count INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS last_updated_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS completed_image_url VARCHAR(255)
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
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )
    ");

    // 提交事務
    $conn->commit();
    echo "Database updated successfully!";
} catch (Exception $e) {
    // 回滾事務
    $conn->rollBack();
    echo "Error updating database: " . $e->getMessage();
} 