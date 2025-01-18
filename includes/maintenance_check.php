<?php
function check_maintenance_mode() {
    global $conn;
    
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
    $stmt->execute();
    $maintenance_mode = $stmt->fetchColumn();
    
    if ($maintenance_mode == '1' && !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        include '../templates/maintenance.php';
        exit;
    }
} 