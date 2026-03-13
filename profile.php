<?php
// profile.php
require_once 'config.php';
requireAuth();

$pageTitle = 'Профиль пользователя';
$currentUser = getCurrentUser($pdo);
$message = '';
$error = '';

// Обновление профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $middleName = $_POST['middle_name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        
        // Проверка уникальности email
        if ($email !== $currentUser['email']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $currentUser['id']]);
            if ($stmt->fetch()) {
                $error = 'Этот email уже используется';
            }
        }
        
        if (!$error) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, middle_name = ?, phone = ?, email = ?
                WHERE id = ?
            ");
            if ($stmt->execute([$firstName, $lastName, $middleName, $phone, $email, $currentUser['id']])) {
                $message = 'Профиль успешно обновлен';
                $currentUser = getCurrentUser($pdo);
                $_SESSION['user_name'] = $firstName . ' ' . $lastName;
            } else {
                $error = 'Ошибка при обновлении профиля';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($currentPassword, $currentUser['password_hash'])) {
            $error = 'Текущий пароль неверен';
        } elseif (strlen($newPassword) < 3) {
            $error = 'Новый пароль должен быть не менее 3 символов';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Пароли не совпадают';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($stmt->execute([$hash, $currentUser['id']])) {
                $message = 'Пароль успешно изменен';
            } else {
                $error = 'Ошибка при смене пароля';
            }
        }
    }
}

include 'header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Редактирование профиля</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Имя</label>
                            <input type="text" class="form-control" name="first_name" 
                                   value="<?php echo htmlspecialchars($currentUser['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Фамилия</label>
                            <input type="text" class="form-control" name="last_name" 
                                   value="<?php echo htmlspecialchars($currentUser['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Отчество</label>
                        <input type="text" class="form-control" name="middle_name" 
                               value="<?php echo htmlspecialchars($currentUser['middle_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Телефон</label>
                        <input type="text" class="form-control" name="phone" 
                               value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" required>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        Сохранить изменения
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Смена пароля</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Текущий пароль</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Новый пароль</label>
                        <input type="password" class="form-control" name="new_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Подтвердите новый пароль</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-warning">
                        Изменить пароль
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>