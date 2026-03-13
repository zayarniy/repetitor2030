<?php
// categories.php (обновленная версия с функцией скрытия категории)
require_once 'config.php';
requireAuth();

$pageTitle = 'Банк категорий';
$currentUser = getCurrentUser($pdo);

$message = '';
$error = '';

// Обработка действий с категориями
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_category'])) {
        $categoryId = $_POST['category_id'] ?? null;
        $name = $_POST['name'] ?? '';
        $color = $_POST['color'] ?? '#6c757d';
        $isVisible = isset($_POST['is_visible']) ? 1 : 0;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        
        if (empty($name)) {
            $error = 'Название категории обязательно';
        } else {
            if ($categoryId) {
                // Обновление существующей категории
                $stmt = $pdo->prepare("
                    UPDATE categories 
                    SET name = ?, color = ?, is_visible = ?, sort_order = ?, updated_at = NOW()
                    WHERE id = ? AND (created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
                ");
                $stmt->execute([$name, $color, $isVisible, $sortOrder, $categoryId, $currentUser['id'], $currentUser['id']]);
                $message = 'Категория обновлена';
            } else {
                // Добавление новой категории
                $stmt = $pdo->prepare("
                    INSERT INTO categories (name, color, is_visible, sort_order, created_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $color, $isVisible, $sortOrder, $currentUser['id']]);
                $message = 'Категория добавлена';
            }
        }
    } elseif (isset($_POST['delete_category'])) {
        $categoryId = $_POST['category_id'] ?? null;
        
        if ($categoryId) {
            // Проверяем, используется ли категория
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM topics WHERE category_id = ?
                UNION ALL
                SELECT COUNT(*) FROM resources WHERE category_id = ?
                UNION ALL
                SELECT COUNT(*) FROM diaries WHERE category_id = ?
            ");
            $stmt->execute([$categoryId, $categoryId, $categoryId]);
            $counts = $stmt->fetchAll();
            $totalUsage = array_sum(array_column($counts, 'COUNT(*)'));
            
            if ($totalUsage > 0) {
                $error = 'Невозможно удалить категорию, так как она используется в темах, ресурсах или дневниках';
            } else {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND (created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))");
                $stmt->execute([$categoryId, $currentUser['id'], $currentUser['id']]);
                $message = 'Категория удалена';
            }
        }
    } elseif (isset($_POST['clear_all_categories'])) {
        // Очистка всех категорий (только для администратора)
        if (isAdmin()) {
            // Проверяем, используются ли категории
            $stmt = $pdo->query("
                SELECT 
                    (SELECT COUNT(*) FROM topics WHERE category_id IS NOT NULL) as topics_count,
                    (SELECT COUNT(*) FROM resources WHERE category_id IS NOT NULL) as resources_count,
                    (SELECT COUNT(*) FROM diaries WHERE category_id IS NOT NULL) as diaries_count
            ");
            $usage = $stmt->fetch();
            
            if ($usage['topics_count'] > 0 || $usage['resources_count'] > 0 || $usage['diaries_count'] > 0) {
                $error = 'Невозможно очистить все категории, так как они используются';
            } else {
                $stmt = $pdo->prepare("DELETE FROM categories");
                $stmt->execute();
                $message = 'Все категории удалены';
            }
        } else {
            $error = 'Недостаточно прав для выполнения операции';
        }
    } elseif (isset($_POST['import_categories'])) {
        // Импорт категорий из CSV
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            
            if ($handle) {
                $imported = 0;
                $errors = 0;
                $line = 0;
                
                // Читаем заголовки
                $headers = fgetcsv($handle, 0, ';');
                
                // Проверяем правильность формата
                if ($headers && count($headers) >= 2 && $headers[0] === 'name' && $headers[1] === 'color') {
                    
                    while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
                        $line++;
                        $name = $data[0] ?? '';
                        $color = $data[1] ?? '#6c757d';
                        $isVisible = isset($data[2]) ? (int)$data[2] : 1;
                        $sortOrder = isset($data[3]) ? (int)$data[3] : 0;
                        
                        if (!empty($name)) {
                            try {
                                $stmt = $pdo->prepare("
                                    INSERT INTO categories (name, color, is_visible, sort_order, created_by)
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([$name, $color, $isVisible, $sortOrder, $currentUser['id']]);
                                $imported++;
                            } catch (Exception $e) {
                                $errors++;
                            }
                        } else {
                            $errors++;
                        }
                    }
                    
                    // Логируем импорт
                    $stmt = $pdo->prepare("
                        INSERT INTO import_export_log (user_id, entity_type, action, file_format, file_name, status, details)
                        VALUES (?, 'categories', 'import', 'csv', ?, ?, ?)
                    ");
                    $status = $errors === 0 ? 'success' : 'failed';
                    $details = "Импортировано: $imported, ошибок: $errors";
                    $stmt->execute([$currentUser['id'], $_FILES['csv_file']['name'], $status, $details]);
                    
                    $message = "Импорт завершен. Добавлено: $imported, ошибок: $errors";
                } else {
                    $error = 'Неверный формат CSV файла. Первая строка должна содержать: name;color;is_visible;sort_order';
                }
                fclose($handle);
            } else {
                $error = 'Ошибка при открытии файла';
            }
        } else {
            $error = 'Выберите файл для импорта';
        }
    } elseif (isset($_POST['toggle_visibility'])) {
        // Быстрое переключение видимости категории
        $categoryId = $_POST['category_id'] ?? null;
        $currentVisibility = $_POST['current_visibility'] ?? 0;
        
        if ($categoryId) {
            $newVisibility = $currentVisibility ? 0 : 1;
            $stmt = $pdo->prepare("
                UPDATE categories SET is_visible = ? WHERE id = ? AND (created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
            ");
            $stmt->execute([$newVisibility, $categoryId, $currentUser['id'], $currentUser['id']]);
            
            // Отправляем JSON ответ для AJAX
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'is_visible' => $newVisibility]);
                exit;
            }
            
            $message = 'Видимость категории изменена';
        }
    } elseif (isset($_POST['hide_category'])) {
        // Скрытие категории (отдельная функция)
        $categoryId = $_POST['category_id'] ?? null;
        
        if ($categoryId) {
            $stmt = $pdo->prepare("
                UPDATE categories SET is_visible = 0 WHERE id = ? AND (created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
            ");
            $stmt->execute([$categoryId, $currentUser['id'], $currentUser['id']]);
            
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'is_visible' => 0]);
                exit;
            }
            
            $message = 'Категория скрыта';
        }
    } elseif (isset($_POST['show_category'])) {
        // Показ категории (отдельная функция)
        $categoryId = $_POST['category_id'] ?? null;
        
        if ($categoryId) {
            $stmt = $pdo->prepare("
                UPDATE categories SET is_visible = 1 WHERE id = ? AND (created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
            ");
            $stmt->execute([$categoryId, $currentUser['id'], $currentUser['id']]);
            
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'is_visible' => 1]);
                exit;
            }
            
            $message = 'Категория показана';
        }
    }
}

// Экспорт категорий в CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("SELECT name, color, is_visible, sort_order FROM categories ORDER BY sort_order, name");
    $categories = $stmt->fetchAll();
    
    // Логируем экспорт
    $stmt = $pdo->prepare("
        INSERT INTO import_export_log (user_id, entity_type, action, file_format, status)
        VALUES (?, 'categories', 'export', 'csv', 'success')
    ");
    $stmt->execute([$currentUser['id']]);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=categories_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM для UTF-8
    
    // Заголовки
    fputcsv($output, ['name', 'color', 'is_visible', 'sort_order'], ';');
    
    // Данные
    foreach ($categories as $category) {
        fputcsv($output, [
            $category['name'],
            $category['color'],
            $category['is_visible'],
            $category['sort_order']
        ], ';');
    }
    
    fclose($output);
    exit;
}

// Получение списка категорий
$filterVisible = isset($_GET['show_hidden']) ? (bool)$_GET['show_hidden'] : false;
$searchTerm = $_GET['search'] ?? '';

$query = "SELECT * FROM categories WHERE 1=1";
$params = [];

if (!$filterVisible) {
    $query .= " AND is_visible = 1";
}

if (!empty($searchTerm)) {
    $query .= " AND name LIKE ?";
    $params[] = "%$searchTerm%";
}

$query .= " ORDER BY sort_order, name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$categories = $stmt->fetchAll();

// Получаем статистику использования категорий
$usageStats = [];
$stmt = $pdo->query("
    SELECT 
        category_id,
        COUNT(*) as topics_count
    FROM topics 
    WHERE category_id IS NOT NULL
    GROUP BY category_id
");
$topicsByCategory = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->query("
    SELECT 
        category_id,
        COUNT(*) as resources_count
    FROM resources 
    WHERE category_id IS NOT NULL
    GROUP BY category_id
");
$resourcesByCategory = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->query("
    SELECT 
        category_id,
        COUNT(*) as diaries_count
    FROM diaries 
    WHERE category_id IS NOT NULL
    GROUP BY category_id
");
$diariesByCategory = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

include 'header.php';
?>

<div class="container-fluid px-4">
    <!-- Заголовок и кнопки действий -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Банк категорий</h1>
        <div>
            <button type="button" class="btn btn-success" onclick="showAddCategoryModal()">
                <i class="bi bi-plus-circle"></i> Добавить категорию
            </button>
            <button type="button" class="btn btn-warning" onclick="showImportModal()" <?php echo !isAdmin() ? 'disabled' : ''; ?>>
                <i class="bi bi-upload"></i> Импорт CSV
            </button>
            <a href="?export=csv" class="btn btn-info">
                <i class="bi bi-download"></i> Экспорт CSV
            </a>
            <?php if (isAdmin()): ?>
            <button type="button" class="btn btn-danger" onclick="clearAllCategories()">
                <i class="bi bi-trash"></i> Очистить все
            </button>
            <?php endif; ?>
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
    
    <!-- Фильтры и поиск -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Поиск по названию</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Введите название категории">
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_hidden" value="1" id="showHidden" <?php echo $filterVisible ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="showHidden">
                            Показывать скрытые категории
                        </label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Применить
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Панель быстрых действий с видимостью -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-secondary" onclick="hideAllVisible()">
                    <i class="bi bi-eye-slash"></i> Скрыть все видимые
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="showAllHidden()">
                    <i class="bi bi-eye"></i> Показать все скрытые
                </button>
            </div>
            <small class="text-muted ms-2">
                Видимых: <span id="visibleCount"><?php echo count(array_filter($categories, function($c) { return $c['is_visible']; })); ?></span> |
                Скрытых: <span id="hiddenCount"><?php echo count(array_filter($categories, function($c) { return !$c['is_visible']; })); ?></span>
            </small>
        </div>
    </div>
    
    <!-- Сетка категорий -->
    <div class="row" id="categoriesGrid">
        <?php if (empty($categories)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Нет категорий. Нажмите "Добавить категорию", чтобы создать первую категорию.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($categories as $category): ?>
                <div class="col-xl-3 col-lg-4 col-md-6 mb-4 category-item" id="category_<?php echo $category['id']; ?>" data-visible="<?php echo $category['is_visible']; ?>">
                    <div class="card h-100 category-card <?php echo !$category['is_visible'] ? 'border-warning bg-light' : ''; ?>">
                        <div class="card-header d-flex justify-content-between align-items-center" style="background-color: <?php echo $category['color']; ?>20; border-left: 4px solid <?php echo $category['color']; ?>;">
                            <h6 class="mb-0 fw-bold" style="color: <?php echo $category['color']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </h6>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button class="dropdown-item" onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo $category['color']; ?>', <?php echo $category['is_visible']; ?>, <?php echo $category['sort_order']; ?>)">
                                            <i class="bi bi-pencil"></i> Редактировать
                                        </button>
                                    </li>
                                    <li>
                                        <?php if ($category['is_visible']): ?>
                                        <button class="dropdown-item" onclick="hideCategory(<?php echo $category['id']; ?>)">
                                            <i class="bi bi-eye-slash"></i> Скрыть
                                        </button>
                                        <?php else: ?>
                                        <button class="dropdown-item" onclick="showCategory(<?php echo $category['id']; ?>)">
                                            <i class="bi bi-eye"></i> Показать
                                        </button>
                                        <?php endif; ?>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button class="dropdown-item text-danger" onclick="deleteCategory(<?php echo $category['id']; ?>)">
                                            <i class="bi bi-trash"></i> Удалить
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge" style="background-color: <?php echo $category['color']; ?>; color: white;">
                                    <?php echo $category['color']; ?>
                                </span>
                                <small class="text-muted">Порядок: <?php echo $category['sort_order']; ?></small>
                            </div>
                            
                            <!-- Статистика использования -->
                            <div class="mt-3">
                                <small class="text-muted d-block">
                                    <i class="bi bi-book"></i> Темы: <?php echo $topicsByCategory[$category['id']] ?? 0; ?>
                                </small>
                                <small class="text-muted d-block">
                                    <i class="bi bi-files"></i> Ресурсы: <?php echo $resourcesByCategory[$category['id']] ?? 0; ?>
                                </small>
                                <small class="text-muted d-block">
                                    <i class="bi bi-journal"></i> Дневники: <?php echo $diariesByCategory[$category['id']] ?? 0; ?>
                                </small>
                            </div>
                            
                            <!-- Индикатор скрытости -->
                            <?php if (!$category['is_visible']): ?>
                                <div class="mt-2">
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-eye-slash"></i> Скрыта
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-calendar"></i> <?php echo date('d.m.Y', strtotime($category['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно для добавления/редактирования категории -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalTitle">Добавление категории</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="categories.php" id="categoryForm">
                <input type="hidden" name="category_id" id="category_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="category_name" required maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Цвет</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" name="color" id="category_color" value="#6c757d" title="Выберите цвет">
                            <input type="text" class="form-control" id="color_hex" value="#6c757d" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Порядок сортировки</label>
                        <input type="number" class="form-control" name="sort_order" id="category_sort_order" value="0" min="0" step="1">
                        <small class="text-muted">Меньшее число = выше в списке</small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_visible" id="category_is_visible" checked>
                        <label class="form-check-label" for="category_is_visible">
                            Показывать категорию
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="save_category" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно для импорта CSV -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Импорт категорий из CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="categories.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Выберите CSV файл</label>
                        <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6>Формат файла:</h6>
                        <p class="mb-0">Первая строка должна содержать заголовки:</p>
                        <code>name;color;is_visible;sort_order</code>
                        <p class="mt-2 mb-0">Пример:</p>
                        <code>Математика;#ff0000;1;10<br>Физика;#00ff00;0;20</code>
                        <p class="mt-2 mb-0"><small>is_visible: 1 - показать, 0 - скрыть</small></p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        Импорт добавит новые категории к существующим. Существующие категории не будут изменены.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="import_categories" class="btn btn-success">Импортировать</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно подтверждения очистки -->
<div class="modal fade" id="clearAllModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Подтверждение очистки</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Вы уверены, что хотите удалить <strong>все</strong> категории?</p>
                <p class="text-danger">Это действие нельзя отменить!</p>
                <p>Категории будут удалены только если они не используются в темах, ресурсах или дневниках.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <form method="POST" action="categories.php" style="display: inline;">
                    <button type="submit" name="clear_all_categories" class="btn btn-danger">Очистить все</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Скрытые формы для быстрых действий -->
<form method="POST" id="hideCategoryForm" style="display: none;">
    <input type="hidden" name="category_id" id="hide_category_id">
    <input type="hidden" name="hide_category">
    <input type="hidden" name="ajax" value="1">
</form>

<form method="POST" id="showCategoryForm" style="display: none;">
    <input type="hidden" name="category_id" id="show_category_id">
    <input type="hidden" name="show_category">
    <input type="hidden" name="ajax" value="1">
</form>

<form method="POST" id="deleteCategoryForm" style="display: none;">
    <input type="hidden" name="category_id" id="delete_category_id">
    <input type="hidden" name="delete_category">
</form>

<style>
.category-card {
    transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;
    cursor: pointer;
}
.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}
.category-card.border-warning {
    opacity: 0.8;
}
.category-card.border-warning:hover {
    opacity: 1;
}
.category-card .card-header {
    padding: 0.75rem 1rem;
}
.color-preview {
    width: 30px;
    height: 30px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}
.visibility-toggle {
    cursor: pointer;
    transition: transform 0.2s;
}
.visibility-toggle:hover {
    transform: scale(1.1);
}
</style>

<script>
// Функции для работы с категориями
function showAddCategoryModal() {
    document.getElementById('category_id').value = '';
    document.getElementById('category_name').value = '';
    document.getElementById('category_color').value = '#6c757d';
    document.getElementById('color_hex').value = '#6c757d';
    document.getElementById('category_sort_order').value = '0';
    document.getElementById('category_is_visible').checked = true;
    document.getElementById('categoryModalTitle').textContent = 'Добавление категории';
    
    var modal = new bootstrap.Modal(document.getElementById('categoryModal'));
    modal.show();
}

function editCategory(id, name, color, isVisible, sortOrder) {
    document.getElementById('category_id').value = id;
    document.getElementById('category_name').value = name;
    document.getElementById('category_color').value = color;
    document.getElementById('color_hex').value = color;
    document.getElementById('category_sort_order').value = sortOrder;
    document.getElementById('category_is_visible').checked = isVisible == 1;
    document.getElementById('categoryModalTitle').textContent = 'Редактирование категории';
    
    var modal = new bootstrap.Modal(document.getElementById('categoryModal'));
    modal.show();
}

// Функция скрытия категории
function hideCategory(id) {
    document.getElementById('hide_category_id').value = id;
    
    fetch('categories.php', {
        method: 'POST',
        body: new FormData(document.getElementById('hideCategoryForm'))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Обновляем карточку категории
            const card = document.getElementById('category_' + id);
            
            // Меняем классы карточки
            card.querySelector('.card').classList.add('border-warning', 'bg-light');
            card.dataset.visible = '0';
            
            // Добавляем индикатор скрытости, если его нет
            if (!card.querySelector('.badge.bg-warning')) {
                const badge = document.createElement('div');
                badge.className = 'mt-2';
                badge.innerHTML = '<span class="badge bg-warning text-dark"><i class="bi bi-eye-slash"></i> Скрыта</span>';
                card.querySelector('.card-body').appendChild(badge);
            }
            
            // Меняем пункт меню с "Скрыть" на "Показать"
            const dropdownMenu = card.querySelector('.dropdown-menu');
            const visibilityItem = dropdownMenu.querySelector('li:nth-child(2)');
            visibilityItem.innerHTML = '<button class="dropdown-item" onclick="showCategory(' + id + ')"><i class="bi bi-eye"></i> Показать</button>';
            
            // Обновляем счетчики
            updateVisibilityCounters();
        }
    })
    .catch(error => {
        alert('Ошибка при скрытии категории');
    });
}

// Функция показа категории
function showCategory(id) {
    document.getElementById('show_category_id').value = id;
    
    fetch('categories.php', {
        method: 'POST',
        body: new FormData(document.getElementById('showCategoryForm'))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Обновляем карточку категории
            const card = document.getElementById('category_' + id);
            
            // Меняем классы карточки
            card.querySelector('.card').classList.remove('border-warning', 'bg-light');
            card.dataset.visible = '1';
            
            // Удаляем индикатор скрытости
            const badge = card.querySelector('.badge.bg-warning');
            if (badge) {
                badge.parentElement.remove();
            }
            
            // Меняем пункт меню с "Показать" на "Скрыть"
            const dropdownMenu = card.querySelector('.dropdown-menu');
            const visibilityItem = dropdownMenu.querySelector('li:nth-child(2)');
            visibilityItem.innerHTML = '<button class="dropdown-item" onclick="hideCategory(' + id + ')"><i class="bi bi-eye-slash"></i> Скрыть</button>';
            
            // Обновляем счетчики
            updateVisibilityCounters();
        }
    })
    .catch(error => {
        alert('Ошибка при показе категории');
    });
}

// Функция удаления категории
function deleteCategory(id) {
    if (confirm('Вы уверены, что хотите удалить эту категорию?')) {
        document.getElementById('delete_category_id').value = id;
        document.getElementById('deleteCategoryForm').submit();
    }
}

// Функция очистки всех категорий
function clearAllCategories() {
    var modal = new bootstrap.Modal(document.getElementById('clearAllModal'));
    modal.show();
}

// Функция показа модального окна импорта
function showImportModal() {
    var modal = new bootstrap.Modal(document.getElementById('importModal'));
    modal.show();
}

// Функция скрытия всех видимых категорий
function hideAllVisible() {
    if (confirm('Скрыть все видимые категории?')) {
        document.querySelectorAll('.category-item[data-visible="1"]').forEach(item => {
            const id = item.id.replace('category_', '');
            hideCategory(id);
        });
    }
}

// Функция показа всех скрытых категорий
function showAllHidden() {
    if (confirm('Показать все скрытые категории?')) {
        document.querySelectorAll('.category-item[data-visible="0"]').forEach(item => {
            const id = item.id.replace('category_', '');
            showCategory(id);
        });
    }
}

// Функция обновления счетчиков видимости
function updateVisibilityCounters() {
    const visibleCount = document.querySelectorAll('.category-item[data-visible="1"]').length;
    const hiddenCount = document.querySelectorAll('.category-item[data-visible="0"]').length;
    
    document.getElementById('visibleCount').textContent = visibleCount;
    document.getElementById('hiddenCount').textContent = hiddenCount;
}

// Синхронизация выбора цвета
document.addEventListener('DOMContentLoaded', function() {
    const colorPicker = document.getElementById('category_color');
    const colorHex = document.getElementById('color_hex');
    
    if (colorPicker && colorHex) {
        colorPicker.addEventListener('input', function() {
            colorHex.value = this.value;
        });
        
        colorHex.addEventListener('input', function() {
            if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                colorPicker.value = this.value;
            }
        });
    }
    
    // Добавляем предпросмотр цвета в карточках
    document.querySelectorAll('.category-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown') && !e.target.closest('button')) {
                const id = this.closest('.category-item').id.replace('category_', '');
                // Здесь можно добавить переход к темам этой категории
                // window.location.href = 'topics.php?category=' + id;
            }
        });
    });
    
    // Инициализация счетчиков
    updateVisibilityCounters();
});

// Подтверждение удаления
document.querySelectorAll('.delete-category').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('Вы уверены?')) {
            this.closest('form').submit();
        }
    });
});
</script>

<?php include 'footer.php'; ?>