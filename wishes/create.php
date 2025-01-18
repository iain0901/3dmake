<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// 檢查用戶是否已登入
if (!is_logged_in()) {
    set_flash_message('warning', '請先登入後再許願');
    redirect('/login.php');
}

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // 驗證輸入
        $errors = [];
        
        $title = sanitize_input($_POST['title']);
        $description = sanitize_input($_POST['description']);
        $tags = isset($_POST['tags']) ? array_map('trim', explode(',', $_POST['tags'])) : [];
        
        if (empty($title)) {
            $errors[] = '請輸入願望標題';
        } elseif (strlen($title) > 100) {
            $errors[] = '標題不能超過100個字元';
        }
        
        if (empty($description)) {
            $errors[] = '請輸入願望描述';
        }
        
        // 處理圖片上傳
        $image_url = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            $file = $_FILES['image'];
            
            if (!in_array($file['type'], $allowed_types)) {
                $errors[] = '只允許上傳 JPG、PNG 或 GIF 格式的圖片';
            } elseif ($file['size'] > $max_size) {
                $errors[] = '圖片大小不能超過 5MB';
            } else {
                // 創建上傳目錄
                $upload_dir = __DIR__ . '/../uploads/wishes/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // 生成唯一檔名
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid('wish_') . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                // 移動上傳的檔案
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $image_url = 'uploads/wishes/' . $filename;
                    
                    // 壓縮圖片
                    if (in_array($file['type'], ['image/jpeg', 'image/png'])) {
                        $image = $file['type'] === 'image/jpeg' ? 
                                imagecreatefromjpeg($filepath) : 
                                imagecreatefrompng($filepath);
                        
                        if ($image) {
                            $width = imagesx($image);
                            $height = imagesy($image);
                            
                            // 如果圖片太大，進行縮放
                            if ($width > 1200 || $height > 1200) {
                                $ratio = min(1200/$width, 1200/$height);
                                $new_width = round($width * $ratio);
                                $new_height = round($height * $ratio);
                                
                                $new_image = imagecreatetruecolor($new_width, $new_height);
                                
                                // 保持 PNG 透明度
                                if ($file['type'] === 'image/png') {
                                    imagealphablending($new_image, false);
                                    imagesavealpha($new_image, true);
                                }
                                
                                imagecopyresampled(
                                    $new_image, $image,
                                    0, 0, 0, 0,
                                    $new_width, $new_height,
                                    $width, $height
                                );
                                
                                if ($file['type'] === 'image/jpeg') {
                                    imagejpeg($new_image, $filepath, 80);
                                } else {
                                    imagepng($new_image, $filepath, 8);
                                }
                                
                                imagedestroy($new_image);
                            }
                            imagedestroy($image);
                        }
                    }
                } else {
                    $errors[] = '圖片上傳失敗';
                }
            }
        }
        
        if (empty($errors)) {
            // 開始交易
            $conn->beginTransaction();
            
            // 插入願望
            $stmt = $conn->prepare("
                INSERT INTO wishes (user_id, title, description, image_url, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $title, $description, $image_url]);
            
            $wish_id = $conn->lastInsertId();
            
            // 插入標籤
            if (!empty($tags)) {
                $stmt = $conn->prepare("
                    INSERT INTO wish_tags (wish_id, tag)
                    VALUES (?, ?)
                ");
                
                foreach ($tags as $tag) {
                    if (!empty($tag)) {
                        $stmt->execute([$wish_id, $tag]);
                    }
                }
            }
            
            // 提交交易
            $conn->commit();
            
            set_flash_message('success', '願望已成功發布');
            redirect("/wishes/view.php?id=$wish_id");
        } else {
            set_flash_message('danger', implode('<br>', $errors));
        }
        
    } catch (Exception $e) {
        // 如果發生錯誤，回滾交易
        if (isset($conn)) {
            $conn->rollBack();
        }
        
        error_log($e->getMessage());
        set_flash_message('danger', '發布願望時發生錯誤，請稍後再試');
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>許下願望 - 3D列印許願平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <style>
        .preview-image {
            max-height: 200px;
            object-fit: contain;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if ($flash = get_flash_message()): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <h1 class="card-title h2 mb-4">許下願望</h1>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">願望標題</label>
                                <input type="text" class="form-control" id="title" name="title" required maxlength="100"
                                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                                <div class="form-text">請簡短描述你想要的3D模型（最多100字）</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">詳細描述</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required><?php 
                                    echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; 
                                ?></textarea>
                                <div class="form-text">請詳細描述你想要的3D模型的細節、用途、尺寸等資訊</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="tags" class="form-label">標籤</label>
                                <input type="text" class="form-control" id="tags" name="tags"
                                       value="<?php echo isset($_POST['tags']) ? htmlspecialchars($_POST['tags']) : ''; ?>">
                                <div class="form-text">用逗號分隔多個標籤</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="image" class="form-label">參考圖片</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <div class="form-text">支援 JPG、PNG、GIF 格式，檔案大小不超過 5MB</div>
                            </div>
                            
                            <div id="preview" class="mb-3 d-none">
                                <label class="form-label">圖片預覽</label>
                                <div class="border rounded p-2">
                                    <img src="" class="preview-image" alt="預覽圖">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>發布願望
                                </button>
                                <a href="/wishes/" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>返回列表
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../templates/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script>
        // 初始化標籤輸入
        new TomSelect('#tags', {
            persist: false,
            createOnBlur: true,
            create: true,
            delimiter: ',',
            placeholder: '輸入標籤...'
        });
        
        // 圖片預覽
        document.getElementById('image').addEventListener('change', function(e) {
            const preview = document.getElementById('preview');
            const previewImg = preview.querySelector('img');
            const file = e.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.classList.remove('d-none');
                }
                reader.readAsDataURL(file);
            } else {
                preview.classList.add('d-none');
            }
        });
    </script>
</body>
</html> 