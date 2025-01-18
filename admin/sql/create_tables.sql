-- 專案類型表
CREATE TABLE IF NOT EXISTS project_types (
    type_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入預設專案類型
INSERT INTO project_types (name, description) VALUES 
('model_request', '尋找3D模型'),
('print_request', '尋找代印服務');

-- 更新專案表（原願望表）
ALTER TABLE wishes RENAME TO projects;
ALTER TABLE projects
    ADD COLUMN type_id INT NOT NULL DEFAULT 1,
    ADD COLUMN model_url VARCHAR(255) NULL,
    ADD COLUMN model_file VARCHAR(255) NULL,
    ADD COLUMN print_requirements TEXT NULL,
    ADD COLUMN budget DECIMAL(10,2) NULL,
    ADD COLUMN deadline DATE NULL,
    ADD FOREIGN KEY (type_id) REFERENCES project_types(type_id);

-- 更新標籤表
ALTER TABLE wish_tags RENAME TO project_tags;
ALTER TABLE project_tags
    CHANGE COLUMN wish_id project_id INT NOT NULL,
    ADD FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE;

-- 更新評論表
ALTER TABLE comments
    CHANGE COLUMN wish_id project_id INT NOT NULL,
    ADD FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE;

-- 3D列印機資料表
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

-- 報價表
CREATE TABLE IF NOT EXISTS quotes (
    quote_id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    printer_id INT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    estimated_time INT, -- 預估完成時間（小時）
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (printer_id) REFERENCES printers(printer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 用途類型表
CREATE TABLE IF NOT EXISTS usage_types (
    type_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入預設用途類型
INSERT INTO usage_types (name, description) VALUES 
('education', '教育用途'),
('research', '研究用途'),
('public_welfare', '公益用途'),
('personal', '個人自用'),
('commercial', '商業用途'),
('other', '其他用途');

-- 印幣交易記錄表
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

-- 用戶印幣餘額表
CREATE TABLE IF NOT EXISTS user_coins (
    user_id INT PRIMARY KEY,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_earned DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_spent DECIMAL(10,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 更新專案表
ALTER TABLE projects
    ADD COLUMN usage_type_id INT NULL,
    ADD COLUMN usage_description TEXT NULL,
    ADD COLUMN reward_amount DECIMAL(10,2) NULL DEFAULT 0,
    ADD COLUMN reward_status ENUM('pending', 'locked', 'paid', 'cancelled') NULL,
    ADD COLUMN reward_paid_at TIMESTAMP NULL,
    ADD FOREIGN KEY (usage_type_id) REFERENCES usage_types(type_id);

-- 專案用途關聯表（支援多重用途）
CREATE TABLE IF NOT EXISTS project_usages (
    project_id INT NOT NULL,
    usage_type_id INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id, usage_type_id),
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (usage_type_id) REFERENCES usage_types(type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 更新報價表
ALTER TABLE quotes
    ADD COLUMN reward_claimed BOOLEAN DEFAULT FALSE,
    ADD COLUMN reward_claimed_at TIMESTAMP NULL; 