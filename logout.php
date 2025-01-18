<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 清除所有 session 變數
session_unset();

// 銷毀 session
session_destroy();

// 重定向到首頁
redirect('/'); 