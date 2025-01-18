<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    redirect('/login.php');
}

$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$notification_id) {
    redirect('index.php');
}

$db = new Database();
$conn = $db->getConnection();

// 刪除通知
$stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
$stmt->execute([$notification_id, $_SESSION['user_id']]);

redirect('index.php'); 