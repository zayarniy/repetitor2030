<?php
// setup.php
require_once 'config.php';

// Проверяем, существует ли уже admin
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'admin@example.com'");
$stmt->execute();
$admin = $stmt->fetch();

if (!$admin) {
    $hash = password_hash('123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password_hash, first_name, last_name, role) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute(['admin', 'admin@example.com', $hash, 'Admin', 'User', 'admin']);
    echo "Тестовый администратор создан!<br>";
} else {
    echo "Тестовый администратор уже существует<br>";
}

echo "Email: admin@example.com<br>";
echo "Пароль: 123<br>";
echo '<a href="login.php">Перейти к входу</a>';
?>