<?php
// admin.php
require_once 'config.php';
requireAuth();

// Проверка прав администратора
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Администрирование';
$currentUser = getCurrentUser($pdo);

$message = '';
$error = '';

// Обработка действий с пользователями
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_user'])) {
        $userId = $_POST['user_id'] ?? null;
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'tutor';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($email) || empty($firstName) || empty($lastName)) {
            $error = 'Заполните обязательные поля';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Введите корректный email';
        } else {
            // Проверка уникальности username и email
            if ($userId) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $userId]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
            }
            
            if ($stmt->fetch()) {
                $error = 'Пользователь с таким именем или email уже существует';
            } else {
                try {
                    if ($userId) {
                        // Обновление существующего пользователя
                        if (!empty($password)) {
                            // Смена пароля
                            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("
                                UPDATE users SET 
                                    username = ?, email = ?, first_name = ?, last_name = ?, 
                                    middle_name = ?, phone = ?, role = ?, is_active = ?,
                                    password_hash = ?, updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([$username, $email, $firstName, $lastName, $middleName, $phone, $role, $isActive, $passwordHash, $userId]);
                        } else {
                            // Без смены пароля
                            $stmt = $pdo->prepare("
                                UPDATE users SET 
                                    username = ?, email = ?, first_name = ?, last_name = ?, 
                                    middle_name = ?, phone = ?, role = ?, is_active = ?,
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([$username, $email, $firstName, $lastName, $middleName, $phone, $role, $isActive, $userId]);
                        }
                        $message = 'Пользователь обновлен';
                    } else {
                        // Добавление нового пользователя
                        if (empty($password)) {
                            $error = 'Введите пароль для нового пользователя';
                        } else {
                            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("
                                INSERT INTO users (username, email, password_hash, first_name, last_name, middle_name, phone, role, is_active, created_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$username, $email, $passwordHash, $firstName, $lastName, $middleName, $phone, $role, $isActive, $currentUser['id']]);
                            $message = 'Пользователь добавлен';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Ошибка при сохранении: ' . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $userId = $_POST['user_id'] ?? null;
        
        if ($userId && $userId != $currentUser['id']) { // Нельзя удалить самого себя
            // Проверяем, есть ли у пользователя связанные данные
            $stmt = $pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM students WHERE tutor_id = ?) as students_count,
                    (SELECT COUNT(*) FROM diaries WHERE tutor_id = ?) as diaries_count,
                    (SELECT COUNT(*) FROM topics WHERE created_by = ?) as topics_count
            ");
            $stmt->execute([$userId, $userId, $userId]);
            $usage = $stmt->fetch();
            
            if ($usage['students_count'] > 0 || $usage['diaries_count'] > 0 || $usage['topics_count'] > 0) {
                $error = 'Невозможно удалить пользователя, так как у него есть связанные данные. Сначала переназначьте их другому пользователю.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $message = 'Пользователь удален';
            }
        } else {
            $error = 'Нельзя удалить самого себя';
        }
    } elseif (isset($_POST['toggle_user_status'])) {
        $userId = $_POST['user_id'] ?? null;
        $currentStatus = $_POST['current_status'] ?? 0;
        
        if ($userId && $userId != $currentUser['id']) { // Нельзя деактивировать самого себя
            $newStatus = $currentStatus ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->execute([$newStatus, $userId]);
            
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'is_active' => $newStatus]);
                exit;
            }
            
            $message = 'Статус пользователя изменен';
        }
    } elseif (isset($_POST['reset_password'])) {
        $userId = $_POST['user_id'] ?? null;
        $newPassword = $_POST['new_password'] ?? '';
        
        if ($userId && !empty($newPassword)) {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$passwordHash, $userId]);
            
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
            
            $message = 'Пароль сброшен';
        }
    } elseif (isset($_POST['switch_user'])) {
        $userId = $_POST['user_id'] ?? null;
        
        if ($userId && $userId != $currentUser['id']) {
            // Сохраняем информацию о том, что мы переключились
            $_SESSION['admin_id'] = $currentUser['id'];
            $_SESSION['user_id'] = $userId;
            
            // Получаем данные пользователя для обновления сессии
            $stmt = $pdo->prepare("SELECT first_name, last_name, role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_role'] = $user['role'];
            }
            
            header('Location: dashboard.php');
            exit;
        }
    } elseif (isset($_POST['return_to_admin'])) {
        if (isset($_SESSION['admin_id'])) {
            $_SESSION['user_id'] = $_SESSION['admin_id'];
            
            // Получаем данные администратора
            $stmt = $pdo->prepare("SELECT first_name, last_name, role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_role'] = $user['role'];
            }
            
            unset($_SESSION['admin_id']);
        }
        header('Location: admin.php');
        exit;
    }
}

// Получение списка пользователей
$searchTerm = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : '';

$query = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM students WHERE tutor_id = u.id) as students_count,
           (SELECT COUNT(*) FROM diaries WHERE tutor_id = u.id) as diaries_count,
           (SELECT COUNT(*) FROM topics WHERE created_by = u.id) as topics_count,
           creator.username as creator_username
    FROM users u
    LEFT JOIN users creator ON u.created_by = creator.id
    WHERE 1=1
";
$params = [];

if (!empty($searchTerm)) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

if (!empty($roleFilter)) {
    $query .= " AND u.role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter !== '') {
    $query .= " AND u.is_active = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY u.role, u.last_name, u.first_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Получение данных для редактирования
$editUser = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editUser = $stmt->fetch();
}

// Проверка, находимся ли мы в режиме переключения
$isSwitched = isset($_SESSION['admin_id']);

include 'header.php';
?>

<div class="container-fluid px-4">
    <!-- Заголовок и кнопки действий -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="bi bi-shield-lock"></i> Администрирование
            <?php if ($isSwitched): ?>
                <span class="badge bg-warning ms-2">Режим просмотра от имени другого пользователя</span>
            <?php endif; ?>
        </h1>
        <div>
            <?php if ($isSwitched): ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="return_to_admin" class="btn btn-warning">
                        <i class="bi bi-arrow-return-left"></i> Вернуться к себе
                    </button>
                </form>
            <?php endif; ?>
            <button type="button" class="btn btn-success" onclick="showAddUserModal()">
                <i class="bi bi-plus-circle"></i> Добавить пользователя
            </button>
        </div>
    </div>
    
    <!-- Сообщения -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Статистика -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Всего пользователей</h6>
                    <h3><?php echo count($users); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Администраторов</h6>
                    <h3><?php echo count(array_filter($users, function($u) { return $u['role'] == 'admin'; })); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Репетиторов</h6>
                    <h3><?php echo count(array_filter($users, function($u) { return $u['role'] == 'tutor'; })); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Активных</h6>
                    <h3><?php echo count(array_filter($users, function($u) { return $u['is_active']; })); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Фильтры и поиск -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Поиск</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" 
                           placeholder="Имя, email или логин">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Роль</label>
                    <select class="form-select" name="role">
                        <option value="">Все роли</option>
                        <option value="admin" <?php echo $roleFilter == 'admin' ? 'selected' : ''; ?>>Администратор</option>
                        <option value="tutor" <?php echo $roleFilter == 'tutor' ? 'selected' : ''; ?>>Репетитор</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Статус</label>
                    <select class="form-select" name="status">
                        <option value="">Все</option>
                        <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>Активные</option>
                        <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>Неактивные</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Применить
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Таблица пользователей -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Управление пользователями</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Пользователь</th>
                            <th>Контакты</th>
                            <th>Роль</th>
                            <th>Статистика</th>
                            <th>Статус</th>
                            <th>Последний вход</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Нет пользователей</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="<?php echo !$user['is_active'] ? 'table-secondary' : ''; ?> 
                                           <?php echo $user['id'] == $currentUser['id'] ? 'table-primary' : ''; ?>">
                                    <td>#<?php echo $user['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['last_name'] . ' ' . $user['first_name']); ?></strong>
                                        <?php if ($user['middle_name']): ?>
                                            <br><small><?php echo htmlspecialchars($user['middle_name']); ?></small>
                                        <?php endif; ?>
                                        <br><small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                    </td>
                                    <td>
                                        <small>
                                            <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['email']); ?><br>
                                            <?php if ($user['phone']): ?>
                                                <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($user['phone']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($user['role'] == 'admin'): ?>
                                            <span class="badge bg-danger">Администратор</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Репетитор</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <i class="bi bi-people"></i> Учеников: <?php echo $user['students_count']; ?><br>
                                            <i class="bi bi-journals"></i> Дневников: <?php echo $user['diaries_count']; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($user['is_active']): ?>
                                            <span class="badge bg-success">Активен</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Неактивен</span>
                                        <?php endif; ?>
                                        <?php if ($user['id'] == $currentUser['id']): ?>
                                            <br><span class="badge bg-warning mt-1">Это вы</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Никогда'; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-info" onclick="editUser(<?php echo $user['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            
                                            <?php if ($user['id'] != $currentUser['id']): ?>
                                                <?php if ($user['is_active']): ?>
                                                    <button class="btn btn-sm btn-warning" onclick="toggleUserStatus(<?php echo $user['id']; ?>, 1)">
                                                        <i class="bi bi-person-x"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-success" onclick="toggleUserStatus(<?php echo $user['id']; ?>, 0)">
                                                        <i class="bi bi-person-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-sm btn-primary" onclick="showResetPasswordModal(<?php echo $user['id']; ?>)">
                                                    <i class="bi bi-key"></i>
                                                </button>
                                                
                                                <button class="btn btn-sm btn-secondary" onclick="switchToUser(<?php echo $user['id']; ?>)">
                                                    <i class="bi bi-person-switch"></i>
                                                </button>
                                                
                                                <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Информация о безопасности -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-shield-check"></i> Безопасность</h5>
                </div>
                <div class="card-body">
                    <p><strong>Рекомендации по паролям:</strong></p>
                    <ul>
                        <li>Минимальная длина пароля: 6 символов</li>
                        <li>Используйте разные пароли для разных сервисов</li>
                        <li>Регулярно меняйте пароли</li>
                        <li>Не сообщайте пароли третьим лицам</li>
                    </ul>
                    <p class="mb-0 text-muted small">
                        <i class="bi bi-info-circle"></i> Все пароли хранятся в зашифрованном виде
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-activity"></i> Активность</h5>
                </div>
                <div class="card-body">
                    <p><strong>Статистика входов:</strong></p>
                    <ul>
                        <li>Пользователей с доступом: <?php echo count($users); ?></li>
                        <li>Активных сегодня: 
                            <?php
                            $today = date('Y-m-d');
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(last_login) = ?");
                            $stmt->execute([$today]);
                            echo $stmt->fetchColumn();
                            ?>
                        </li>
                        <li>Никогда не входили:
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE last_login IS NULL");
                            echo $stmt->fetchColumn();
                            ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для добавления/редактирования пользователя -->
<div class="modal fade" id="userModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Добавление пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="admin.php" id="userForm">
                <input type="hidden" name="user_id" id="user_id" value="<?php echo $editUser['id'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Логин <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" id="user_username" 
                                   value="<?php echo htmlspecialchars($editUser['username'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="user_email" 
                                   value="<?php echo htmlspecialchars($editUser['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Фамилия <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" id="user_last_name" 
                                   value="<?php echo htmlspecialchars($editUser['last_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Имя <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" id="user_first_name" 
                                   value="<?php echo htmlspecialchars($editUser['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Отчество</label>
                            <input type="text" class="form-control" name="middle_name" id="user_middle_name" 
                                   value="<?php echo htmlspecialchars($editUser['middle_name'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Телефон</label>
                            <input type="text" class="form-control" name="phone" id="user_phone" 
                                   value="<?php echo htmlspecialchars($editUser['phone'] ?? ''); ?>" 
                                   placeholder="+7 (999) 999-99-99">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Роль</label>
                            <select class="form-select" name="role" id="user_role">
                                <option value="tutor" <?php echo ($editUser['role'] ?? '') == 'tutor' ? 'selected' : ''; ?>>Репетитор</option>
                                <option value="admin" <?php echo ($editUser['role'] ?? '') == 'admin' ? 'selected' : ''; ?>>Администратор</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="user_is_active" 
                                   <?php echo !isset($editUser) || $editUser['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="user_is_active">
                                Активный пользователь
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <?php echo $editUser ? 'Новый пароль (оставьте пустым, чтобы не менять)' : 'Пароль <span class="text-danger">*</span>'; ?>
                        </label>
                        <input type="password" class="form-control" name="password" id="user_password" 
                               <?php echo !$editUser ? 'required' : ''; ?>>
                        <?php if (!$editUser): ?>
                            <small class="text-muted">Минимальная длина: 6 символов</small>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($editUser): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            Оставьте поле пароля пустым, если не хотите его менять.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="save_user" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно для сброса пароля -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Сброс пароля</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="admin.php" id="resetPasswordForm">
                <input type="hidden" name="user_id" id="reset_user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Новый пароль</label>
                        <input type="password" class="form-control" name="new_password" id="new_password" required>
                        <small class="text-muted">Минимальная длина: 6 символов</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Подтверждение</label>
                        <input type="password" class="form-control" id="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="reset_password" class="btn btn-warning">Сбросить пароль</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно подтверждения удаления -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Подтверждение удаления</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Вы уверены, что хотите удалить этого пользователя?</p>
                <p class="text-danger">Это действие нельзя отменить!</p>
                <p>Пользователь будет удален только если у него нет связанных данных.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <form method="POST" action="admin.php" style="display: inline;">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <button type="submit" name="delete_user" class="btn btn-danger">Удалить</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Скрытые формы для AJAX -->
<form method="POST" id="toggleStatusForm" style="display: none;">
    <input type="hidden" name="user_id" id="toggle_user_id">
    <input type="hidden" name="current_status" id="toggle_current_status">
    <input type="hidden" name="toggle_user_status">
    <input type="hidden" name="ajax" value="1">
</form>

<form method="POST" id="switchUserForm" style="display: none;">
    <input type="hidden" name="user_id" id="switch_user_id">
    <input type="hidden" name="switch_user">
</form>

<style>
.table td {
    vertical-align: middle;
}
.btn-group .btn {
    margin-right: 2px;
}
.btn-group .btn:last-child {
    margin-right: 0;
}
</style>

<script>
// Функции для работы с пользователями
function showAddUserModal() {
    document.getElementById('user_id').value = '';
    document.getElementById('user_username').value = '';
    document.getElementById('user_email').value = '';
    document.getElementById('user_last_name').value = '';
    document.getElementById('user_first_name').value = '';
    document.getElementById('user_middle_name').value = '';
    document.getElementById('user_phone').value = '';
    document.getElementById('user_role').value = 'tutor';
    document.getElementById('user_is_active').checked = true;
    document.getElementById('user_password').value = '';
    document.getElementById('user_password').required = true;
    document.getElementById('userModalTitle').textContent = 'Добавление пользователя';
    
    var modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();
}

function editUser(id) {
    window.location.href = 'admin.php?edit=' + id;
}

function deleteUser(id) {
    document.getElementById('delete_user_id').value = id;
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function toggleUserStatus(id, currentStatus) {
    if (confirm('Вы уверены, что хотите изменить статус пользователя?')) {
        document.getElementById('toggle_user_id').value = id;
        document.getElementById('toggle_current_status').value = currentStatus;
        
        fetch('admin.php', {
            method: 'POST',
            body: new FormData(document.getElementById('toggleStatusForm'))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => {
            alert('Ошибка при изменении статуса');
        });
    }
}

function showResetPasswordModal(id) {
    document.getElementById('reset_user_id').value = id;
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_password').value = '';
    
    var modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
    modal.show();
}

function switchToUser(id) {
    if (confirm('Переключиться на этого пользователя? Вы сможете вернуться обратно через кнопку "Вернуться к себе".')) {
        document.getElementById('switch_user_id').value = id;
        document.getElementById('switchUserForm').submit();
    }
}

// Валидация формы сброса пароля
document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
    const password = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Пароль должен быть не менее 6 символов');
        return;
    }
    
    if (password !== confirm) {
        e.preventDefault();
        alert('Пароли не совпадают');
        return;
    }
});

// Форматирование телефона
document.getElementById('user_phone')?.addEventListener('input', function(e) {
    let value = this.value.replace(/\D/g, '');
    if (value.length > 0) {
        if (value.length <= 1) {
            value = '+7 (' + value;
        } else if (value.length <= 4) {
            value = '+7 (' + value.substring(1, 4);
        } else if (value.length <= 7) {
            value = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4, 7);
        } else if (value.length <= 9) {
            value = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4, 7) + '-' + value.substring(7, 9);
        } else {
            value = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4, 7) + '-' + value.substring(7, 9) + '-' + value.substring(9, 11);
        }
        this.value = value;
    }
});

// Показываем модальное окно если есть редактируемый пользователь
<?php if ($editUser): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('user_password').required = false;
    document.getElementById('userModalTitle').textContent = 'Редактирование пользователя';
    var userModal = new bootstrap.Modal(document.getElementById('userModal'));
    userModal.show();
});
<?php endif; ?>

// Автоматическое скрытие сообщений через 5 секунд
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        if (!alert.classList.contains('alert-permanent')) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    });
}, 5000);
</script>

<?php include 'footer.php'; ?>