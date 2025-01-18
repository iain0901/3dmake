<?php
function custom_error_handler($errno, $errstr, $errfile, $errline) {
    $error_message = "Error [$errno] $errstr in $errfile on line $errline";
    
    // 記錄錯誤
    log_action('system_error', $error_message);
    
    // 對於致命錯誤，顯示友好的錯誤頁面
    if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        ob_clean();
        include '../templates/error.php';
        exit;
    }
    
    return false;
}

set_error_handler('custom_error_handler'); 