<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => '未授權的訪問']));
}

$db = new Database();
$conn = $db->getConnection();

// 獲取郵件設置
$stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'admin_email')");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// 配置 PHPMailer
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $settings['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $settings['smtp_user'];
    $mail->Password = $settings['smtp_pass'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $settings['smtp_port'];

    $mail->setFrom($settings['smtp_user'], '3D列印許願平台');
    $mail->addAddress($settings['admin_email']);

    $mail->isHTML(true);
    $mail->Subject = '郵件設置測試';
    $mail->Body = '如果您收到這封郵件，表示郵件設置正確。';

    $mail->send();
    echo json_encode(['success' => true, 'message' => '測試郵件已發送']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '郵件發送失敗：' . $mail->ErrorInfo]);
}
?> 