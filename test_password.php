<?php
$stored_hash = '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN.6P.3Hb9Emk.3o5Eim';
$test_passwords = [
    'admin123',
    'password',
    'admin',
    'Admin123',
    'admin@123'
];

foreach ($test_passwords as $password) {
    $result = password_verify($password, $stored_hash);
    echo "Testing password: $password\n";
    echo "Verification result: " . ($result ? "成功" : "失敗") . "\n\n";
}

// 生成新的雜湊
echo "生成新的雜湊值：\n";
foreach ($test_passwords as $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "$password: $hash\n";
}
?> 