<?php
// header.php
if (!isset($currentUser)) {
    $currentUser = getCurrentUser($pdo);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Дневник репетитора'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 2px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            border-left: 4px solid #667eea;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .navbar-top {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 30px;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }
        .module-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            border: 1px solid #e0e0e0;
        }
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .module-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Боковое меню -->
            <div class="col-md-2 p-0 sidebar">
                <div class="p-4">
                    <h5 class="text-white mb-4">Дневник</h5>
                    <h6 class="text-white-50 mb-3">Главное</h6>
                    <nav class="nav flex-column">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Дашборд
                        </a>
                        <a class="nav-link" href="students.php">
                            <i class="bi bi-people"></i> Ученики
                        </a>
                        <a class="nav-link" href="diaries.php">
                            <i class="bi bi-journals"></i> Дневники
                        </a>
                    </nav>
                    
                    <h6 class="text-white-50 mt-4 mb-3">Банки</h6>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="categories.php">
                            <i class="bi bi-tags"></i> Категории
                        </a>
                        <a class="nav-link" href="topics.php">
                            <i class="bi bi-book"></i> Темы
                        </a>
                        <a class="nav-link" href="tags.php">
                            <i class="bi bi-bookmark"></i> Метки
                        </a>
                        <a class="nav-link" href="resources.php">
                            <i class="bi bi-files"></i> Ресурсы
                        </a>
                    </nav>
                    
                    <?php if (isAdmin()): ?>
                    <h6 class="text-white-50 mt-4 mb-3">Управление</h6>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="admin.php">
                            <i class="bi bi-gear"></i> Администрирование
                        </a>
                    </nav>
                    <?php endif; ?>
                    
                    <h6 class="text-white-50 mt-4 mb-3">Отчеты</h6>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="statistics.php">
                            <i class="bi bi-graph-up"></i> Статистика
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Основной контент -->
            <div class="col-md-10 p-0 main-content">
                <div class="navbar-top d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?php echo $pageTitle ?? 'Дашборд'; ?></h5>
                    <div class="d-flex align-items-center">
                        <div class="dropdown">
                            <button class="btn btn-link dropdown-toggle text-dark" type="button" data-bs-toggle="dropdown">
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-2">
                                        <?php echo strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1) . substr($currentUser['last_name'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <span><?php echo $currentUser['first_name'] ?? 'Пользователь'; ?> <?php echo $currentUser['last_name'] ?? ''; ?></span>
                                </div>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Профиль</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Выход</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="p-4"></div>