<?php
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// API 路由
$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// API 認證
function authenticate_api() {
    $headers = getallheaders();
    $api_key = $headers['X-API-Key'] ?? null;
    
    if (!$api_key) {
        http_response_code(401);
        echo json_encode(['error' => 'No API key provided']);
        exit;
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT user_id FROM api_keys WHERE api_key = ? AND active = 1");
    $stmt->execute([$api_key]);
    $user_id = $stmt->fetchColumn();
    
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        exit;
    }
    
    return $user_id;
}

// 速率限制
function check_rate_limit($user_id) {
    $db = new Database();
    $conn = $db->getConnection();
    
    // 清理舊的請求記錄
    $conn->exec("DELETE FROM api_requests WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    
    // 檢查請求次數
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM api_requests 
        WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$user_id]);
    $request_count = $stmt->fetchColumn();
    
    if ($request_count >= 1000) { // 每小時1000次請求限制
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded']);
        exit;
    }
    
    // 記錄新的請求
    $stmt = $conn->prepare("INSERT INTO api_requests (user_id) VALUES (?)");
    $stmt->execute([$user_id]);
}

// 路由處理
try {
    $user_id = authenticate_api();
    check_rate_limit($user_id);
    
    switch ($route) {
        case 'wishes':
            require_once 'wishes.php';
            break;
            
        case 'users':
            require_once 'users.php';
            break;
            
        case 'comments':
            require_once 'comments.php';
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
} 