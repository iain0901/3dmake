<?php
// 用戶相關函數
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_user_role() {
    return $_SESSION['user_role'] ?? null;
}

function is_admin() {
    return get_user_role() === 'admin';
}

function is_creator() {
    return get_user_role() === 'creator';
}

// 安全相關函數
function sanitize_input($input) {
    if (is_array($input)) {
        return array_map('sanitize_input', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token() {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        throw new Exception('CSRF 驗證失敗');
    }
}

// 檔案處理函數
function handle_file_upload($file, $type, $allowed_extensions = []) {
    try {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('檔案上傳失敗：' . get_upload_error_message($file['error']));
        }
        
        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension']);
        
        if (!empty($allowed_extensions) && !in_array($extension, $allowed_extensions)) {
            throw new Exception('不支援的檔案格式');
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('檔案大小超過限制');
        }
        
        $upload_dir = UPLOAD_PATH . '/' . $type;
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $filename = uniqid() . '.' . $extension;
        $filepath = $upload_dir . '/' . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('檔案移動失敗');
        }
        
        return [
            'success' => true,
            'path' => $type . '/' . $filename,
            'original_name' => $file['name'],
            'mime_type' => $file['type'],
            'size' => $file['size']
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function get_upload_error_message($error_code) {
    return match($error_code) {
        UPLOAD_ERR_INI_SIZE => '檔案超過 PHP 設定的上傳限制',
        UPLOAD_ERR_FORM_SIZE => '檔案超過表單設定的上傳限制',
        UPLOAD_ERR_PARTIAL => '檔案只有部分被上傳',
        UPLOAD_ERR_NO_FILE => '沒有檔案被上傳',
        UPLOAD_ERR_NO_TMP_DIR => '找不到暫存資料夾',
        UPLOAD_ERR_CANT_WRITE => '無法寫入檔案',
        UPLOAD_ERR_EXTENSION => '檔案上傳被 PHP 擴充功能停止',
        default => '未知的上傳錯誤'
    };
}

// 訊息處理函數
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// 導向函數
function redirect($url) {
    header("Location: $url");
    exit;
}

// 印幣相關函數
function format_coin_amount($amount) {
    return number_format($amount);
}

function get_user_coin_balance($user_id) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT balance 
            FROM user_coins 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['balance'] : 0;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return 0;
    }
}

// 日期時間相關函數
function format_date($date) {
    return date('Y/m/d', strtotime($date));
}

function format_datetime($datetime) {
    return date('Y/m/d H:i', strtotime($datetime));
}

function get_time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    return match(true) {
        $diff < 60 => '剛剛',
        $diff < 3600 => floor($diff / 60) . '分鐘前',
        $diff < 86400 => floor($diff / 3600) . '小時前',
        $diff < 604800 => floor($diff / 86400) . '天前',
        $diff < 2592000 => floor($diff / 604800) . '週前',
        $diff < 31536000 => floor($diff / 2592000) . '個月前',
        default => floor($diff / 31536000) . '年前'
    };
}

// 專案相關函數
function get_project_status_text($status) {
    return match($status) {
        'pending' => '等待中',
        'in_progress' => '進行中',
        'completed' => '已完成',
        'cancelled' => '已取消',
        default => '未知狀態'
    };
}

function get_project_status_class($status) {
    return match($status) {
        'pending' => 'warning',
        'in_progress' => 'info',
        'completed' => 'success',
        'cancelled' => 'secondary',
        default => 'secondary'
    };
}

// 錯誤處理函數
function log_error($message, $context = []) {
    $log_message = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($context)) {
        $log_message .= ' - ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    error_log($log_message);
} 