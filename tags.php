<?php
// tags.php
require_once 'config.php';
requireAuth();

$pageTitle = 'Банк меток';
$currentUser = getCurrentUser($pdo);

$message = '';
$error = '';

// Определение цветов меток
$colorClasses = [
    'green' => ['bg-success', 'text-white', 'Положительная'],
    'red' => ['bg-danger', 'text-white', 'Отрицательная'],
    'blue' => ['bg-info', 'text-white', 'Важная информация'],
    'grey' => ['bg-secondary', 'text-white', 'Дополнительная информация']
];

// Обработка действий с метками
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_tag'])) {
        $tagId = $_POST['tag_id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $color = $_POST['color'] ?? 'grey';
        
        if (empty($name)) {
            $error = 'Название метки обязательно';
        } elseif (!in_array($color, ['green', 'red', 'blue', 'grey'])) {
            $error = 'Неверный цвет метки';
        } else {
            // Проверка на уникальность названия
            if ($tagId) {
                $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ? AND id != ?");
                $stmt->execute([$name, $tagId]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                $stmt->execute([$name]);
            }
            
            if ($stmt->fetch()) {
                $error = 'Метка с таким названием уже существует';
            } else {
                if ($tagId) {
                    // Обновление существующей метки
                    $stmt = $pdo->prepare("
                        UPDATE tags 
                        SET name = ?, color = ?, updated_at = NOW()
                        WHERE id = ? AND (created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
                    ");
                    $stmt->execute([$name, $color, $tagId, $currentUser['id'], $currentUser['id']]);
                    $message = 'Метка обновлена';
                } else {
                    // Добавление новой метки
                    $stmt = $pdo->prepare("
                        INSERT INTO tags (name, color, created_by)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$name, $color, $currentUser['id']]);
                    $message = 'Метка добавлена';
                }
            }
        }
    } elseif (isset($_POST['delete_tag'])) {
        $tagId = $_POST['tag_id'] ?? null;
        
        if ($tagId) {
            // Проверяем, используется ли метка
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM topic_tags WHERE tag_id = ?
                UNION ALL
                SELECT COUNT(*) FROM resources WHERE FIND_IN_SET(?, REPLACE(tags, ';', ',')) > 0
            ");
            $stmt->execute([$tagId, $tagId]);
            $counts = $stmt->fetchAll();
            $totalUsage = array_sum(array_column($counts, 'COUNT(*)'));
            
            if ($totalUsage > 0) {
                $error = 'Невозможно удалить метку, так как она используется в темах или ресурсах';
            } else {
                $stmt = $pdo->prepare("DELETE FROM tags WHERE id = ? AND (created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))");
                $stmt->execute([$tagId, $currentUser['id'], $currentUser['id']]);
                $message = 'Метка удалена';
            }
        }
    } elseif (isset($_POST['clear_all_tags'])) {
        // Очистка всех меток (только для администратора)
        if (isAdmin()) {
            // Проверяем, используются ли метки
            $stmt = $pdo->query("
                SELECT 
                    (SELECT COUNT(*) FROM topic_tags) as topics_count,
                    (SELECT COUNT(*) FROM resources WHERE tags != '') as resources_count
            ");
            $usage = $stmt->fetch();
            
            if ($usage['topics_count'] > 0 || $usage['resources_count'] > 0) {
                $error = 'Невозможно очистить все метки, так как они используются';
            } else {
                $stmt = $pdo->prepare("DELETE FROM tags");
                $stmt->execute();
                $message = 'Все метки удалены';
            }
        } else {
            $error = 'Недостаточно прав для выполнения операции';
        }
    } elseif (isset($_POST['import_tags'])) {
        // Импорт меток из JSON
        if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
            $jsonContent = file_get_contents($_FILES['json_file']['tmp_name']);
            $tags = json_decode($jsonContent, true);
            
            if (is_array($tags)) {
                $imported = 0;
                $errors = 0;
                
                foreach ($tags as $tag) {
                    $name = trim($tag['name'] ?? '');
                    $color = $tag['color'] ?? 'grey';
                    
                    if (!empty($name) && in_array($color, ['green', 'red', 'blue', 'grey'])) {
                        // Проверка на существование
                        $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                        $stmt->execute([$name]);
                        
                        if (!$stmt->fetch()) {
                            try {
                                $stmt = $pdo->prepare("
                                    INSERT INTO tags (name, color, created_by)
                                    VALUES (?, ?, ?)
                                ");
                                $stmt->execute([$name, $color, $currentUser['id']]);
                                $imported++;
                            } catch (Exception $e) {
                                $errors++;
                            }
                        } else {
                            $errors++;
                        }
                    } else {
                        $errors++;
                    }
                }
                
                // Логируем импорт
                $stmt = $pdo->prepare("
                    INSERT INTO import_export_log (user_id, entity_type, action, file_format, file_name, status, details)
                    VALUES (?, 'tags', 'import', 'json', ?, ?, ?)
                ");
                $status = $errors === 0 ? 'success' : 'failed';
                $details = "Импортировано: $imported, ошибок: $errors";
                $stmt->execute([$currentUser['id'], $_FILES['json_file']['name'], $status, $details]);
                
                $message = "Импорт завершен. Добавлено: $imported, ошибок: $errors";
            } else {
                $error = 'Неверный формат JSON файла';
            }
        } else {
            $error = 'Выберите файл для импорта';
        }
    }
}

// Экспорт меток в JSON
if (isset($_GET['export']) && $_GET['export'] === 'json') {
    $stmt = $pdo->query("SELECT name, color FROM tags ORDER BY name");
    $tags = $stmt->fetchAll();
    
    // Логируем экспорт
    $stmt = $pdo->prepare("
        INSERT INTO import_export_log (user_id, entity_type, action, file_format, status)
        VALUES (?, 'tags', 'export', 'json', 'success')
    ");
    $stmt->execute([$currentUser['id']]);
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=tags_' . date('Y-m-d') . '.json');
    
    echo json_encode($tags, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Получение списка меток
$searchTerm = $_GET['search'] ?? '';
$colorFilter = $_GET['color'] ?? '';

$query = "SELECT * FROM tags WHERE 1=1";
$params = [];

if (!empty($searchTerm)) {
    $query .= " AND name LIKE ?";
    $params[] = "%$searchTerm%";
}

if (!empty($colorFilter)) {
    $query .= " AND color = ?";
    $params[] = $colorFilter;
}

$query .= " ORDER BY 
    CASE color 
        WHEN 'green' THEN 1 
        WHEN 'red' THEN 2 
        WHEN 'blue' THEN 3 
        WHEN 'grey' THEN 4 
    END, name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tags = $stmt->fetchAll();

// Получаем статистику использования меток
$usageStats = [];
$stmt = $pdo->query("
    SELECT 
        tag_id,
        COUNT(*) as topics_count
    FROM topic_tags 
    GROUP BY tag_id
");
$topicsByTag = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Для ресурсов сложнее, так как теги хранятся в текстовом поле
$stmt = $pdo->query("SELECT id, tags FROM resources WHERE tags != ''");
$resourcesByTag = [];
while ($row = $stmt->fetch()) {
    $tagIds = explode(';', $row['tags']);
    foreach ($tagIds as $tagId) {
        $tagId = trim($tagId);
        if (is_numeric($tagId)) {
            $resourcesByTag[$tagId] = ($resourcesByTag[$tagId] ?? 0) + 1;
        }
    }
}

include 'header.php';
?>

<div class="container-fluid px-4">
    <!-- Заголовок и кнопки действий -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Банк меток</h1>
        <div>
            <button type="button" class="btn btn-success" onclick="showAddTagModal()">
                <i class="bi bi-plus-circle"></i> Добавить метку
            </button>
            <button type="button" class="btn btn-warning" onclick="showImportModal()" <?php echo !isAdmin() ? 'disabled' : ''; ?>>
                <i class="bi bi-upload"></i> Импорт JSON
            </button>
            <a href="?export=json" class="btn btn-info">
                <i class="bi bi-download"></i> Экспорт JSON
            </a>
            <?php if (isAdmin()): ?>
            <button type="button" class="btn btn-danger" onclick="clearAllTags()">
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
    
    <!-- Легенда цветов -->
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="card-title mb-3">Цветовая легенда:</h6>
            <div class="row">
                <div class="col-md-3">
                    <span class="badge bg-success">Зеленый</span> - Положительные
                </div>
                <div class="col-md-3">
                    <span class="badge bg-danger">Красный</span> - Отрицательные
                </div>
                <div class="col-md-3">
                    <span class="badge bg-info">Голубой</span> - Важная информация
                </div>
                <div class="col-md-3">
                    <span class="badge bg-secondary">Серый</span> - Дополнительная информация
                </div>
            </div>
        </div>
    </div>
    
    <!-- Фильтры и поиск -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Поиск по названию</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Введите название метки">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Фильтр по цвету</label>
                    <select class="form-select" name="color">
                        <option value="">Все цвета</option>
                        <option value="green" <?php echo $colorFilter == 'green' ? 'selected' : ''; ?>>Зеленые</option>
                        <option value="red" <?php echo $colorFilter == 'red' ? 'selected' : ''; ?>>Красные</option>
                        <option value="blue" <?php echo $colorFilter == 'blue' ? 'selected' : ''; ?>>Голубые</option>
                        <option value="grey" <?php echo $colorFilter == 'grey' ? 'selected' : ''; ?>>Серые</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Применить
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Статистика -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Зеленые</h6>
                    <h3><?php echo count(array_filter($tags, function($t) { return $t['color'] == 'green'; })); ?></h3>
                    <small>положительные</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title">Красные</h6>
                    <h3><?php echo count(array_filter($tags, function($t) { return $t['color'] == 'red'; })); ?></h3>
                    <small>отрицательные</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Голубые</h6>
                    <h3><?php echo count(array_filter($tags, function($t) { return $t['color'] == 'blue'; })); ?></h3>
                    <small>важная информация</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h6 class="card-title">Серые</h6>
                    <h3><?php echo count(array_filter($tags, function($t) { return $t['color'] == 'grey'; })); ?></h3>
                    <small>дополнительная информация</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Сетка меток -->
    <div class="row" id="tagsGrid">
        <?php if (empty($tags)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Нет меток. Нажмите "Добавить метку", чтобы создать первую метку.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($tags as $tag): ?>
                <?php 
                $colorInfo = $colorClasses[$tag['color']];
                $topicsCount = $topicsByTag[$tag['id']] ?? 0;
                $resourcesCount = $resourcesByTag[$tag['id']] ?? 0;
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6 mb-4" id="tag_<?php echo $tag['id']; ?>">
                    <div class="card h-100 tag-card">
                        <div class="card-header <?php echo $colorInfo[0]; ?> text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">
                                <i class="bi bi-tag-fill"></i> <?php echo htmlspecialchars($tag['name']); ?>
                            </h6>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-white" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button class="dropdown-item" onclick="editTag(<?php echo $tag['id']; ?>, '<?php echo htmlspecialchars($tag['name']); ?>', '<?php echo $tag['color']; ?>')">
                                            <i class="bi bi-pencil"></i> Редактировать
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button class="dropdown-item text-danger" onclick="deleteTag(<?php echo $tag['id']; ?>)">
                                            <i class="bi bi-trash"></i> Удалить
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <span class="badge <?php echo $colorInfo[0]; ?>"><?php echo $colorInfo[2]; ?></span>
                            </div>
                            
                            <!-- Статистика использования -->
                            <div class="mt-3">
                                <small class="text-muted d-block">
                                    <i class="bi bi-book"></i> В темах: <?php echo $topicsCount; ?>
                                </small>
                                <small class="text-muted d-block">
                                    <i class="bi bi-files"></i> В ресурсах: <?php echo $resourcesCount; ?>
                                </small>
                            </div>
                            
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-calendar"></i> <?php echo date('d.m.Y', strtotime($tag['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно для добавления/редактирования метки -->
<div class="modal fade" id="tagModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tagModalTitle">Добавление метки</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="tags.php" id="tagForm">
                <input type="hidden" name="tag_id" id="tag_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название метки <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="tag_name" required maxlength="50">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Цвет метки</label>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="color" id="color_green" value="green">
                                    <label class="form-check-label" for="color_green">
                                        <span class="badge bg-success">Зеленый</span> - Положительный
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="color" id="color_red" value="red">
                                    <label class="form-check-label" for="color_red">
                                        <span class="badge bg-danger">Красный</span> - Отрицательный
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="color" id="color_blue" value="blue">
                                    <label class="form-check-label" for="color_blue">
                                        <span class="badge bg-info">Голубой</span> - Важный
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="color" id="color_grey" value="grey" checked>
                                    <label class="form-check-label" for="color_grey">
                                        <span class="badge bg-secondary">Серый</span> - Дополнительный
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Предпросмотр -->
                    <div class="alert alert-light">
                        <small>Предпросмотр:</small><br>
                        <span class="badge" id="preview_badge">Название метки</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="save_tag" class="btn btn-primary">Сохранить метку</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно для импорта JSON -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Импорт меток из JSON</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="tags.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Выберите JSON файл</label>
                        <input type="file" class="form-control" name="json_file" accept=".json" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6>Формат файла:</h6>
                        <p class="mb-0">Массив объектов с полями name и color:</p>
                        <pre>[
  {"name": "Важно", "color": "red"},
  {"name": "Срочно", "color": "blue"},
  {"name": "Дополнительно", "color": "grey"}
]</pre>
                        <p class="mt-2 mb-0"><small>Допустимые цвета: green, red, blue, grey</small></p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        Метки с уже существующими названиями будут пропущены.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="import_tags" class="btn btn-success">Импортировать</button>
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
                <p>Вы уверены, что хотите удалить <strong>все</strong> метки?</p>
                <p class="text-danger">Это действие нельзя отменить!</p>
                <p>Метки будут удалены только если они не используются в темах или ресурсах.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <form method="POST" action="tags.php" style="display: inline;">
                    <button type="submit" name="clear_all_tags" class="btn btn-danger">Очистить все</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Скрытая форма для удаления -->
<form method="POST" id="deleteTagForm" style="display: none;">
    <input type="hidden" name="tag_id" id="delete_tag_id">
    <input type="hidden" name="delete_tag">
</form>

<style>
.tag-card {
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}
.tag-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}
.tag-card .card-header {
    padding: 0.75rem 1rem;
}
.color-preview {
    width: 30px;
    height: 30px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}
.badge-preview {
    font-size: 1rem;
    padding: 0.5rem 1rem;
}
</style>

<script>
// Функции для работы с метками
function showAddTagModal() {
    document.getElementById('tag_id').value = '';
    document.getElementById('tag_name').value = '';
    document.getElementById('color_grey').checked = true;
    updatePreview();
    document.getElementById('tagModalTitle').textContent = 'Добавление метки';
    
    var modal = new bootstrap.Modal(document.getElementById('tagModal'));
    modal.show();
}

function editTag(id, name, color) {
    document.getElementById('tag_id').value = id;
    document.getElementById('tag_name').value = name;
    document.getElementById('color_' + color).checked = true;
    updatePreview();
    document.getElementById('tagModalTitle').textContent = 'Редактирование метки';
    
    var modal = new bootstrap.Modal(document.getElementById('tagModal'));
    modal.show();
}

function deleteTag(id) {
    if (confirm('Вы уверены, что хотите удалить эту метку?')) {
        document.getElementById('delete_tag_id').value = id;
        document.getElementById('deleteTagForm').submit();
    }
}

function clearAllTags() {
    var modal = new bootstrap.Modal(document.getElementById('clearAllModal'));
    modal.show();
}

function showImportModal() {
    var modal = new bootstrap.Modal(document.getElementById('importModal'));
    modal.show();
}

// Обновление предпросмотра метки
function updatePreview() {
    const name = document.getElementById('tag_name').value || 'Название метки';
    const color = document.querySelector('input[name="color"]:checked')?.value || 'grey';
    
    const preview = document.getElementById('preview_badge');
    preview.textContent = name;
    
    switch(color) {
        case 'green':
            preview.className = 'badge bg-success';
            break;
        case 'red':
            preview.className = 'badge bg-danger';
            break;
        case 'blue':
            preview.className = 'badge bg-info';
            break;
        default:
            preview.className = 'badge bg-secondary';
    }
}

// Обработчики событий для предпросмотра
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('tag_name');
    const colorInputs = document.querySelectorAll('input[name="color"]');
    
    if (nameInput) {
        nameInput.addEventListener('input', updatePreview);
    }
    
    colorInputs.forEach(input => {
        input.addEventListener('change', updatePreview);
    });
    
    // Инициализация предпросмотра
    updatePreview();
    
    // Добавляем предпросмотр в карточках
    document.querySelectorAll('.tag-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown') && !e.target.closest('button')) {
                // Здесь можно добавить действие при клике на карточку
                // Например, показать все ресурсы с этой меткой
                console.log('Tag clicked');
            }
        });
    });
});

// Подтверждение удаления
document.querySelectorAll('.delete-tag').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('Вы уверены?')) {
            this.closest('form').submit();
        }
    });
});

// Валидация формы перед отправкой
document.getElementById('tagForm')?.addEventListener('submit', function(e) {
    const name = document.getElementById('tag_name').value.trim();
    if (!name) {
        e.preventDefault();
        alert('Введите название метки');
    }
});
</script>

<?php include 'footer.php'; ?>