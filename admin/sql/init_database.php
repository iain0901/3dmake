<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // 開始事務
    $conn->beginTransaction();
    
    // 創建專案類型表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS project_types (
            type_id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // 插入預設專案類型
    $conn->exec("
        INSERT INTO project_types (name, description) 
        SELECT * FROM (
            SELECT 'model_request' as name, '尋找3D模型' as description
            UNION ALL
            SELECT 'print_request', '尋找代印服務'
        ) AS tmp
        WHERE NOT EXISTS (
            SELECT 1 FROM project_types
        );
    ");
    
    // 創建專案表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS projects (
            project_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            type_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            image_url VARCHAR(255),
            model_url VARCHAR(255),
            model_file VARCHAR(255),
            print_requirements TEXT,
            budget DECIMAL(10,2),
            deadline DATE,
            reward_amount DECIMAL(10,2) DEFAULT 0,
            reward_status ENUM('pending', 'locked', 'paid', 'cancelled'),
            reward_paid_at TIMESTAMP NULL,
            status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id),
            FOREIGN KEY (type_id) REFERENCES project_types(type_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // 創建用途類型表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS usage_types (
            type_id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // 插入預設用途類型
    $conn->exec("
        INSERT INTO usage_types (name, description) 
        SELECT * FROM (
            SELECT 'education' as name, '教育用途' as description
            UNION ALL SELECT 'research', '研究用途'
            UNION ALL SELECT 'public_welfare', '公益用途'
            UNION ALL SELECT 'personal', '個人自用'
            UNION ALL SELECT 'commercial', '商業用途'
            UNION ALL SELECT 'other', '其他用途'
        ) AS tmp
        WHERE NOT EXISTS (
            SELECT 1 FROM usage_types
        );
    ");
    
    // 創建專案用途關聯表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS project_usages (
            project_id INT NOT NULL,
            usage_type_id INT NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (project_id, usage_type_id),
            FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
            FOREIGN KEY (usage_type_id) REFERENCES usage_types(type_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // 創建評論表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS comments (
            comment_id INT PRIMARY KEY AUTO_INCREMENT,
            project_id INT NOT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // 創建3D列印機資料表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS printers (
            printer_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            specifications TEXT,
            status ENUM('available', 'busy', 'maintenance') DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // 創建報價表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS quotes (
            quote_id INT PRIMARY KEY AUTO_INCREMENT,
            project_id INT NOT NULL,
            user_id INT NOT NULL,
            printer_id INT NULL,
            price DECIMAL(10,2) NOT NULL,
            description TEXT,
            estimated_time INT,
            status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
            reward_claimed BOOLEAN DEFAULT FALSE,
            reward_claimed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(project_id),
            FOREIGN KEY (user_id) REFERENCES users(user_id),
            FOREIGN KEY (printer_id) REFERENCES printers(printer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // 創建印幣交易記錄表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS coin_transactions (
            transaction_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            type ENUM('deposit', 'withdraw', 'reward', 'earn', 'refund') NOT NULL,
            status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            reference_id INT NULL,
            reference_type VARCHAR(50) NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // 創建用戶印幣餘額表
    $conn->exec("
        CREATE TABLE IF NOT EXISTS user_coins (
            user_id INT PRIMARY KEY,
            balance DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_earned DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_spent DECIMAL(10,2) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // 確保上傳目錄存在
    $upload_dirs = [
        '../../uploads/images',
        '../../uploads/models'
    ];
    
    foreach ($upload_dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    
    // 提交事務
    $conn->commit();
    
    echo "資料庫初始化成功！\n";
    
} catch (Exception $e) {
    // 發生錯誤時回滾事務
    if ($conn) {
        $conn->rollBack();
    }
    echo "錯誤：" . $e->getMessage() . "\n";
}
?> 