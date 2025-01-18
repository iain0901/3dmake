<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    redirect('/login.php');
}

$comment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$wish_id = isset($_GET['wish_id']) ? (int)$_GET['wish_id'] : 0;

if (!$comment_id || !$wish_id) {
    redirect('index.php');
}

$db = new Database();
$conn = $db->getConnection();

// 檢查留言是否屬於當前用戶
$stmt = $conn->prepare("DELETE FROM comments WHERE comment_id = ? AND user_id = ?");
$stmt->execute([$comment_id, $_SESSION['user_id']]);

redirect("view.php?id=" . $wish_id); 