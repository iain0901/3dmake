<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    set_flash_message('warning', '請先登入後再發布專案');
    redirect('../login.php');
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // 獲取用戶印幣餘額
    $stmt = $conn->prepare("
        SELECT balance 
        FROM user_coins 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_coins = $stmt->fetch(PDO::FETCH_ASSOC);
    $balance = $user_coins ? $user_coins['balance'] : 0;
    
    // 獲取用途類型列表
    $stmt = $conn->prepare("SELECT * FROM usage_types ORDER BY type_id");
    $stmt->execute();
    $usage_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 處理表單提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validate_csrf_token();
        
        $type = $_POST['type'] === 'model' ? 1 : 2;
        $title = sanitize_input($_POST['title']);
        $description = sanitize_input($_POST['description']);
        $budget = !empty($_POST['budget']) ? floatval($_POST['budget']) : null;
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        $reward_amount = !empty($_POST['reward_amount']) ? floatval($_POST['reward_amount']) : 0;
        $selected_usages = $_POST['usages'] ?? [];
        $usage_descriptions = $_POST['usage_descriptions'] ?? [];
        
        // 驗證輸入
        $errors = [];
        
        if (empty($title)) {
            $errors[] = '請輸入標題';
        }
        
        if (empty($description)) {
            $errors[] = '請輸入描述';
        }
        
        if ($reward_amount > 0 && $reward_amount > $balance) {
            $errors[] = '懸賞金額不能超過您的印幣餘額';
        }
        
        if (empty($selected_usages)) {
            $errors[] = '請至少選擇一個用途';
        }
        
        // 處理檔案上傳
        $image_url = null;
        $model_file = null;
        $model_url = !empty($_POST['model_url']) ? sanitize_input($_POST['model_url']) : null;
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handle_file_upload($_FILES['image'], 'images', ['jpg', 'jpeg', 'png', 'gif']);
            if ($upload_result['success']) {
                $image_url = $upload_result['path'];
            } else {
                $errors[] = $upload_result['error'];
            }
        }
        
        if ($type === 2 && isset($_FILES['model']) && $_FILES['model']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handle_file_upload($_FILES['model'], 'models', ['stl', 'obj', 'gcode']);
            if ($upload_result['success']) {
                $model_file = $upload_result['path'];
            } else {
                $errors[] = $upload_result['error'];
            }
        }
        
        if (empty($errors)) {
            $conn->beginTransaction();
            
            try {
                // 插入專案
                $stmt = $conn->prepare("
                    INSERT INTO projects (
                        user_id, type_id, title, description, image_url,
                        model_url, model_file, budget, deadline,
                        reward_amount, reward_status,
                        created_at, status
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?,
                        NOW(), 'pending'
                    )
                ");
                
                $stmt->execute([
                    $_SESSION['user_id'], $type, $title, $description, $image_url,
                    $model_url, $model_file, $budget, $deadline,
                    $reward_amount, $reward_amount > 0 ? 'pending' : null
                ]);
                
                $project_id = $conn->lastInsertId();
                
                // 插入用途
                $stmt = $conn->prepare("
                    INSERT INTO project_usages (project_id, usage_type_id, description)
                    VALUES (?, ?, ?)
                ");
                
                foreach ($selected_usages as $usage_type_id) {
                    $description = $usage_descriptions[$usage_type_id] ?? null;
                    $stmt->execute([$project_id, $usage_type_id, $description]);
                }
                
                // 如果設置了懸賞，扣除用戶印幣
                if ($reward_amount > 0) {
                    // 更新用戶印幣餘額
                    $stmt = $conn->prepare("
                        UPDATE user_coins 
                        SET balance = balance - ?,
                            total_spent = total_spent + ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$reward_amount, $reward_amount, $_SESSION['user_id']]);
                    
                    // 記錄交易
                    $stmt = $conn->prepare("
                        INSERT INTO coin_transactions (
                            user_id, amount, type, status,
                            reference_id, reference_type, description
                        ) VALUES (
                            ?, ?, 'reward', 'completed',
                            ?, 'project', '專案懸賞金'
                        )
                    ");
                    $stmt->execute([$_SESSION['user_id'], -$reward_amount, $project_id]);
                }
                
                $conn->commit();
                set_flash_message('success', '專案已成功發布');
                redirect("view.php?id=$project_id");
                
            } catch (Exception $e) {
                $conn->rollBack();
                error_log($e->getMessage());
                $errors[] = '發布專案時發生錯誤，請稍後再試';
            }
        }
        
        if (!empty($errors)) {
            set_flash_message('danger', implode('<br>', $errors));
        }
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    set_flash_message('danger', '載入頁面時發生錯誤，請稍後再試');
}

$type = $_GET['type'] ?? 'model';
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $type === 'model' ? '發布模型需求' : '發布代印需求'; ?> - 3D列印服務平台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <style>
        .usage-description {
            display: none;
        }
        .usage-description.active {
            display: block;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if ($flash = get_flash_message()): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <h1 class="card-title h2 mb-4">
                            <?php echo $type === 'model' ? '發布模型需求' : '發布代印需求'; ?>
                        </h1>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="type" value="<?php echo $type; ?>">
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">標題</label>
                                <input type="text" class="form-control" id="title" name="title" required maxlength="100"
                                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">詳細描述</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required><?php 
                                    echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; 
                                ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="image" class="form-label">參考圖片</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <div class="form-text">支援 JPG、PNG、GIF 格式</div>
                            </div>
                            
                            <?php if ($type === 'print'): ?>
                                <div class="mb-3">
                                    <label for="model" class="form-label">3D模型檔案</label>
                                    <input type="file" class="form-control" id="model" name="model" accept=".stl,.obj,.gcode">
                                    <div class="form-text">支援 STL、OBJ、GCODE 格式</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="model_url" class="form-label">或提供模型連結</label>
                                    <input type="url" class="form-control" id="model_url" name="model_url"
                                           value="<?php echo isset($_POST['model_url']) ? htmlspecialchars($_POST['model_url']) : ''; ?>">
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="budget" class="form-label">預算</label>
                                <div class="input-group">
                                    <span class="input-group-text">NT$</span>
                                    <input type="number" class="form-control" id="budget" name="budget" min="0" step="1"
                                           value="<?php echo isset($_POST['budget']) ? htmlspecialchars($_POST['budget']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="deadline" class="form-label">期限</label>
                                <input type="date" class="form-control" id="deadline" name="deadline"
                                       min="<?php echo date('Y-m-d'); ?>"
                                       value="<?php echo isset($_POST['deadline']) ? htmlspecialchars($_POST['deadline']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="reward_amount" class="form-label">懸賞金額（印幣）</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="reward_amount" name="reward_amount" 
                                           min="0" step="1" max="<?php echo $balance; ?>"
                                           value="<?php echo isset($_POST['reward_amount']) ? htmlspecialchars($_POST['reward_amount']) : '0'; ?>">
                                    <span class="input-group-text">印幣</span>
                                </div>
                                <div class="form-text">
                                    您目前的印幣餘額：<?php echo number_format($balance); ?> 印幣
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">用途（可多選）</label>
                                <?php foreach ($usage_types as $usage): ?>
                                    <div class="form-check">
                                        <input class="form-check-input usage-checkbox" type="checkbox" 
                                               name="usages[]" value="<?php echo $usage['type_id']; ?>" 
                                               id="usage_<?php echo $usage['type_id']; ?>"
                                               <?php echo in_array($usage['type_id'], $_POST['usages'] ?? []) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="usage_<?php echo $usage['type_id']; ?>">
                                            <?php echo htmlspecialchars($usage['description']); ?>
                                        </label>
                                    </div>
                                    <div class="mb-3 usage-description" id="usage_description_<?php echo $usage['type_id']; ?>">
                                        <textarea class="form-control" name="usage_descriptions[<?php echo $usage['type_id']; ?>]" 
                                                rows="2" placeholder="請說明用途..."><?php 
                                            echo isset($_POST['usage_descriptions'][$usage['type_id']]) 
                                                ? htmlspecialchars($_POST['usage_descriptions'][$usage['type_id']])
                                                : '';
                                        ?></textarea>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">發布專案</button>
                                <a href="../" class="btn btn-outline-secondary">取消</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../templates/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 用途說明顯示/隱藏
        document.querySelectorAll('.usage-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const descriptionDiv = document.getElementById('usage_description_' + this.value);
                if (this.checked) {
                    descriptionDiv.classList.add('active');
                } else {
                    descriptionDiv.classList.remove('active');
                }
            });
            
            // 初始化時檢查
            if (checkbox.checked) {
                document.getElementById('usage_description_' + checkbox.value).classList.add('active');
            }
        });
        
        // 預覽圖片
        document.getElementById('image').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.createElement('img');
                    preview.src = e.target.result;
                    preview.style.maxHeight = '200px';
                    preview.style.maxWidth = '100%';
                    preview.className = 'mt-2';
                    
                    const container = document.getElementById('image').parentNode;
                    const oldPreview = container.querySelector('img');
                    if (oldPreview) {
                        container.removeChild(oldPreview);
                    }
                    container.appendChild(preview);
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
</body>
</html> 