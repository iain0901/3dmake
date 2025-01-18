<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once 'includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// 定義設置項
$settings = [
    'site_name' => '網站名稱',
    'site_description' => '網站描述',
    'admin_email' => '管理員郵箱',
    'items_per_page' => '每頁顯示數量',
    'allow_registration' => '允許註冊',
    'maintenance_mode' => '維護模式',
    'smtp_host' => 'SMTP主機',
    'smtp_port' => 'SMTP端口',
    'smtp_user' => 'SMTP用戶名',
    'smtp_pass' => 'SMTP密碼',
];

// 處理設置更新
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } else {
        foreach ($settings as $key => $label) {
            if (isset($_POST[$key])) {
                $value = $_POST[$key];
                
                // 檢查設置是否存在
                $stmt = $conn->prepare("SELECT setting_id FROM settings WHERE setting_key = ?");
                $stmt->execute([$key]);
                
                if ($stmt->fetch()) {
                    // 更新設置
                    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                } else {
                    // 插入新設置
                    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                }
                
                $stmt->execute([$value, $key]);
            }
        }
        
        $success = '設置已更新';
    }
}

// 獲取當前設置
$current_settings = [];
$stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $current_settings[$row['setting_key']] = $row['setting_value'];
}

$csrf_token = generate_csrf_token();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>系統設置</h1>
</div>

<div class="card">
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="row">
                <div class="col-md-6">
                    <h4 class="mb-3">基本設置</h4>
                    
                    <?php foreach (['site_name', 'site_description', 'admin_email', 'items_per_page'] as $key): ?>
                        <div class="mb-3">
                            <label for="<?php echo $key; ?>" class="form-label">
                                <?php echo $settings[$key]; ?>
                            </label>
                            <input type="text" class="form-control" id="<?php echo $key; ?>" 
                                   name="<?php echo $key; ?>" 
                                   value="<?php echo htmlspecialchars($current_settings[$key] ?? ''); ?>">
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="allow_registration" 
                                   name="allow_registration" value="1" 
                                   <?php echo ($current_settings['allow_registration'] ?? '') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="allow_registration">
                                <?php echo $settings['allow_registration']; ?>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="maintenance_mode" 
                                   name="maintenance_mode" value="1" 
                                   <?php echo ($current_settings['maintenance_mode'] ?? '') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="maintenance_mode">
                                <?php echo $settings['maintenance_mode']; ?>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h4 class="mb-3">郵件設置</h4>
                    
                    <?php foreach (['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass'] as $key): ?>
                        <div class="mb-3">
                            <label for="<?php echo $key; ?>" class="form-label">
                                <?php echo $settings[$key]; ?>
                            </label>
                            <input type="<?php echo $key == 'smtp_pass' ? 'password' : 'text'; ?>" 
                                   class="form-control" id="<?php echo $key; ?>" 
                                   name="<?php echo $key; ?>" 
                                   value="<?php echo htmlspecialchars($current_settings[$key] ?? ''); ?>">
                        </div>
                    <?php endforeach; ?>
                    
                    <button type="button" class="btn btn-info" onclick="testEmail()">
                        測試郵件設置
                    </button>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">保存設置</button>
            </div>
        </form>
    </div>
</div>

<script>
function testEmail() {
    // 實現郵件測試功能
    alert('此功能尚未實現');
}
</script>

<?php require_once 'includes/footer.php'; ?> 