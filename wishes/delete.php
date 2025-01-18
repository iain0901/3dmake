<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    redirect('/login.php');
}

$wish_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$wish_id) {
    redirect('index.php');
}

$db = new Database();
$conn = $db->getConnection();

// 檢查許願單是否屬於當前用戶
$stmt = $conn->prepare("SELECT image_url FROM wishes WHERE wish_id = ? AND user_id = ?");
$stmt->execute([$wish_id, $_SESSION['user_id']]);
$wish = $stmt->fetch(PDO::FETCH_ASSOC);

if ($wish) {
    // 開始事務
    $conn->beginTransaction();
    
    try {
        // 刪除相關的評論
        $stmt = $conn->prepare("DELETE FROM comments WHERE wish_id = ?");
        $stmt->execute([$wish_id]);
        
        // 刪除許願單
        $stmt = $conn->prepare("DELETE FROM wishes WHERE wish_id = ? AND user_id = ?");
        $stmt->execute([$wish_id, $_SESSION['user_id']]);
        
        // 如果有圖片，刪除圖片文件
        if ($wish['image_url'] && file_exists('../' . $wish['image_url'])) {
            unlink('../' . $wish['image_url']);
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
    }
}

redirect('index.php'); 