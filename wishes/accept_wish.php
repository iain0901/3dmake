<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!is_logged_in() || $_SESSION['role'] != 'creator') {
    redirect('/login.php');
}

$wish_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$wish_id) {
    redirect('index.php');
}

$db = new Database();
$conn = $db->getConnection();

// 更新許願單狀態
$stmt = $conn->prepare("
    UPDATE wishes 
    SET status = 'processing'
    WHERE wish_id = ? AND status = 'pending'
");

if ($stmt->execute([$wish_id])) {
    // 發送通知給許願單作者
    $stmt = $conn->prepare("
        SELECT w.user_id, w.title 
        FROM wishes w 
        WHERE w.wish_id = ?
    ");
    $stmt->execute([$wish_id]);
    $wish = $stmt->fetch();
    
    if ($wish) {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, message)
            VALUES (?, ?)
        ");
        $notification = "您的許願「" . $wish['title'] . "」已被接受製作";
        $stmt->execute([$wish['user_id'], $notification]);
    }
}

redirect("view.php?id=" . $wish_id); 