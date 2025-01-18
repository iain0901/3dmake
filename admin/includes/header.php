<?php
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    redirect('/login.php');
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>後台管理 - 3D列印許願平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #f8f9fa;
        }
        .content {
            padding: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">後台管理系統</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/" target="_blank">
                            <i class="fas fa-external-link-alt"></i> 前台首頁
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/logout.php">
                            <i class="fas fa-sign-out-alt"></i> 登出
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar">
                <div class="list-group mt-3">
                    <a href="/admin/" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt"></i> 儀表板
                    </a>
                    <a href="/admin/users.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users"></i> 用戶管理
                    </a>
                    <a href="/admin/wishes.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-list"></i> 許願管理
                    </a>
                    <a href="/admin/comments.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-comments"></i> 留言管理
                    </a>
                    <a href="/admin/settings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog"></i> 系統設置
                    </a>
                </div>
            </div>
            <div class="col-md-10 content"> 