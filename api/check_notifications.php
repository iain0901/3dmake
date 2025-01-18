<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// 獲取未讀通知數量
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0
");
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetchColumn();

echo json_encode([
    'unread_count' => $unread_count
]); 