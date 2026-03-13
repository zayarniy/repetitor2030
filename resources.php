<?php
// resources.php
require_once 'config.php';
requireAuth();

$pageTitle = 'Банк ресурсов';
$currentUser = getCurrentUser($pdo);

$message = '';
$error = '';

// Получение списка категорий
$stmt = $pdo->query("SELECT * FROM categories WHERE is_visible = 1 OR 1=1 ORDER BY sort_order, name");
$categories = $stmt->fetchAll();

// Получение списка тем для выбора
$stmt = $pdo->query("
    SELECT t.*, c.name as category_name 
    FROM topics t
    LEFT JOIN categories c ON t.category_id = c.id
    ORDER BY t.name
");
$topics = $stmt->fetchAll();

// Типы ресурсов
$resourceTypes = [
    'page' => 'Страница',
    'document' => 'Документ',
    'video' => 'Видео',
    'audio' => 'Звук',
    'other' => 'Другое'
];

// Обработка действий с ресурсами
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_resource'])) {
        $resourceId = $_POST['resource_id'] ?? null;
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $type = $_POST['type'] ?? 'page';
        $categoryId = $_POST['category_id'] ?: null;
        $topicId = $_POST['topic_id'] ?: null;
        $quality = (int)($_POST['quality'] ?? 0);
        $createdAt = $_POST['created_at'] ?? date('Y-m-d H:i:s');
        
        if (empty($url)) {
            $error = 'URL ресурса обязателен';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error = 'Введите корректный URL';
        } elseif ($quality < 0 || $quality > 5) {
            $error = 'Оценка качества должна быть от 0 до 5';
        } else {
            try {
                if ($resourceId) {
                    // Обновление существующего ресурса
                    $stmt = $pdo->prepare("
                        UPDATE resources 
                        SET url = ?, description = ?, type = ?, category_id = ?, 
                            topic_id = ?, quality = ?, updated_at = NOW()
                        WHERE id = ? AND (created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
                    ");
                    $stmt->execute([$url, $description, $type, $categoryId, $topicId, $quality, $resourceId, $currentUser['id'], $currentUser['id']]);
                    $message = 'Ресурс обновлен';
                } else {
                    // Добавление нового ресурса
                    $stmt = $pdo->prepare("
                        INSERT INTO resources (url, description, type, category_id, topic_id, quality, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$url, $description, $type, $categoryId, $topicId, $quality, $currentUser['id'], $createdAt]);
                    $message = 'Ресурс добавлен';
                }
            } catch (Exception $e) {
                $error = 'Ошибка при сохранении ресурса: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_resource'])) {
        $resourceId = $_POST['resource_id'] ?? null;
        
        if ($resourceId) {
            // Проверяем, используется ли ресурс в занятиях
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_resources WHERE resource_id = ?");
            $stmt->execute([$resourceId]);
            $lessonsCount = $stmt->fetchColumn();
            
            if ($lessonsCount > 0) {
                $error = 'Невозможно удалить ресурс, так как он используется в ' . $lessonsCount . ' занятиях';
            } else {
                $stmt = $pdo->prepare("DELETE FROM resources WHERE id = ? AND (created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))");
                $stmt->execute([$resourceId, $currentUser['id'], $currentUser['id']]);
                $message = 'Ресурс успешно удален';
            }
        }
    } elseif (isset($_POST['clear_tags'])) {
        // Очистка меток ресурса (теперь не используется, оставлено для совместимости)
        $resourceId = $_POST['resource_id'] ?? null;
        
        if ($resourceId) {
            $stmt = $pdo->prepare("UPDATE resources SET tags = '' WHERE id = ?");
            $stmt->execute([$resourceId]);
            $message = 'Метки очищены';
        }
    }
}

// Получение списка ресурсов с фильтрацией
$typeFilter = $_GET['type'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$topicFilter = $_GET['topic'] ?? '';
$qualityFilter = $_GET['quality'] ?? '';
$searchTerm = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$query = "
    SELECT r.*, 
           c.name as category_name,
           c.color as category_color,
           t.name as topic_name
    FROM resources r
    LEFT JOIN categories c ON r.category_id = c.id
    LEFT JOIN topics t ON r.topic_id = t.id
    WHERE 1=1
";
$params = [];

if (!empty($typeFilter)) {
    $query .= " AND r.type = ?";
    $params[] = $typeFilter;
}

if (!empty($categoryFilter)) {
    $query .= " AND r.category_id = ?";
    $params[] = $categoryFilter;
}

if (!empty($topicFilter)) {
    $query .= " AND r.topic_id = ?";
    $params[] = $topicFilter;
}

if ($qualityFilter !== '') {
    if ($qualityFilter === '0') {
        $query .= " AND (r.quality = 0 OR r.quality IS NULL)";
    } else {
        $query .= " AND r.quality = ?";
        $params[] = $qualityFilter;
    }
}

if (!empty($searchTerm)) {
    $query .= " AND (r.description LIKE ? OR r.url LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

if (!empty($dateFrom)) {
    $query .= " AND DATE(r.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $query .= " AND DATE(r.created_at) <= ?";
    $params[] = $dateTo;
}

$query .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$resources = $stmt->fetchAll();

// Получаем данные для редактирования
$editResource = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ? AND (created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))");
    $stmt->execute([$_GET['edit'], $currentUser['id'], $currentUser['id']]);
    $editResource = $stmt->fetch();
}

include 'header.php';
?>

<div class="container-fluid px-4">
    <!-- Заголовок и кнопки действий -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Банк ресурсов</h1>
        <div>
            <button type="button" class="btn btn-success" onclick="showAddResourceModal()">
                <i class="bi bi-plus-circle"></i> Добавить ресурс
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
    
    <!-- Фильтры и поиск -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Поиск</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Описание или URL">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Тип</label>
                    <select class="form-select" name="type">
                        <option value="">Все типы</option>
                        <?php foreach ($resourceTypes as $key => $typeName): ?>
                            <option value="<?php echo $key; ?>" <?php echo $typeFilter == $key ? 'selected' : ''; ?>>
                                <?php echo $typeName; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Категория</label>
                    <select class="form-select" name="category">
                        <option value="">Все категории</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Тема</label>
                    <select class="form-select" name="topic">
                        <option value="">Все темы</option>
                        <?php foreach ($topics as $topic): ?>
                            <option value="<?php echo $topic['id']; ?>" <?php echo $topicFilter == $topic['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($topic['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Качество</label>
                    <select class="form-select" name="quality">
                        <option value="">Все</option>
                        <option value="5" <?php echo $qualityFilter == '5' ? 'selected' : ''; ?>>5 - Отличное</option>
                        <option value="4" <?php echo $qualityFilter == '4' ? 'selected' : ''; ?>>4 - Хорошее</option>
                        <option value="3" <?php echo $qualityFilter == '3' ? 'selected' : ''; ?>>3 - Среднее</option>
                        <option value="2" <?php echo $qualityFilter == '2' ? 'selected' : ''; ?>>2 - Ниже среднего</option>
                        <option value="1" <?php echo $qualityFilter == '1' ? 'selected' : ''; ?>>1 - Плохое</option>
                        <option value="0" <?php echo $qualityFilter === '0' ? 'selected' : ''; ?>>Без оценки</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Дата с</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Дата по</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
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
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Всего ресурсов</h6>
                    <h3><?php echo count($resources); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Среднее качество</h6>
                    <h3>
                        <?php 
                        $avgQuality = array_sum(array_column($resources, 'quality')) / max(count($resources), 1);
                        echo number_format($avgQuality, 1);
                        ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">С темами</h6>
                    <h3><?php echo count(array_filter($resources, function($r) { return !empty($r['topic_id']); })); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Без оценки</h6>
                    <h3><?php echo count(array_filter($resources, function($r) { return empty($r['quality']); })); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Список ресурсов -->
    <div class="row">
        <?php if (empty($resources)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Нет ресурсов. Нажмите "Добавить ресурс", чтобы создать первый ресурс.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($resources as $resource): ?>
                <div class="col-md-6 col-lg-4 mb-4" id="resource_<?php echo $resource['id']; ?>">
                    <div class="card h-100 resource-card">
                        <div class="card-header d-flex justify-content-between align-items-center"
                             style="<?php echo $resource['category_color'] ? 'border-left: 4px solid ' . $resource['category_color'] : ''; ?>">
                            <div class="d-flex align-items-center">
                                <?php 
                                $typeIcon = match($resource['type']) {
                                    'page' => 'bi-file-text',
                                    'document' => 'bi-file-pdf',
                                    'video' => 'bi-camera-video',
                                    'audio' => 'bi-mic',
                                    default => 'bi-link'
                                };
                                ?>
                                <i class="bi <?php echo $typeIcon; ?> me-2"></i>
                                <span class="badge bg-secondary"><?php echo $resourceTypes[$resource['type']] ?? $resource['type']; ?></span>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button class="dropdown-item" onclick="editResource(<?php echo $resource['id']; ?>)">
                                            <i class="bi bi-pencil"></i> Редактировать
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button class="dropdown-item text-danger" onclick="deleteResource(<?php echo $resource['id']; ?>)">
                                            <i class="bi bi-trash"></i> Удалить
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title">
                                <a href="<?php echo htmlspecialchars($resource['url']); ?>" target="_blank" class="text-decoration-none">
                                    <?php echo htmlspecialchars($resource['description'] ?: $resource['url']); ?>
                                </a>
                            </h6>
                            
                            <div class="mt-2">
                                <?php if ($resource['category_name']): ?>
                                    <span class="badge mb-1" style="background-color: <?php echo $resource['category_color']; ?>; color: white;">
                                        <i class="bi bi-folder"></i> <?php echo htmlspecialchars($resource['category_name']); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($resource['topic_name']): ?>
                                    <span class="badge bg-info mb-1">
                                        <i class="bi bi-book"></i> <?php echo htmlspecialchars($resource['topic_name']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Оценка качества -->
                            <div class="mt-3">
                                <small class="text-muted">Качество:</small>
                                <div class="quality-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $resource['quality']): ?>
                                            <i class="bi bi-star-fill text-warning"></i>
                                        <?php else: ?>
                                            <i class="bi bi-star text-secondary"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <?php if ($resource['quality'] > 0): ?>
                                        <small class="ms-2 text-muted">(<?php echo $resource['quality']; ?>/5)</small>
                                    <?php else: ?>
                                        <small class="ms-2 text-muted">(нет оценки)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Дата создания -->
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="bi bi-calendar"></i> Добавлен: <?php echo date('d.m.Y H:i', strtotime($resource['created_at'])); ?>
                                </small>
                            </div>
                            
                            <!-- Превью URL -->
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-link"></i> 
                                    <?php 
                                    $shortUrl = substr($resource['url'], 0, 50);
                                    if (strlen($resource['url']) > 50) $shortUrl .= '...';
                                    echo htmlspecialchars($shortUrl);
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно для добавления/редактирования ресурса -->
<div class="modal fade" id="resourceModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resourceModalTitle">Добавление ресурса</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="resources.php" id="resourceForm">
                <input type="hidden" name="resource_id" id="resource_id" value="<?php echo $editResource['id'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">URL <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" name="url" id="resource_url" 
                                   value="<?php echo htmlspecialchars($editResource['url'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Тип</label>
                            <select class="form-select" name="type" id="resource_type">
                                <?php foreach ($resourceTypes as $key => $typeName): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($editResource['type'] ?? 'page') == $key ? 'selected' : ''; ?>>
                                        <?php echo $typeName; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Описание</label>
                        <textarea class="form-control" name="description" id="resource_description" rows="2"><?php echo htmlspecialchars($editResource['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Категория</label>
                            <select class="form-select" name="category_id" id="resource_category">
                                <option value="">Без категории</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                            style="color: <?php echo $cat['color']; ?>;"
                                            <?php echo ($editResource['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Тема (из банка тем)</label>
                            <select class="form-select" name="topic_id" id="resource_topic">
                                <option value="">Без темы</option>
                                <?php foreach ($topics as $topic): ?>
                                    <option value="<?php echo $topic['id']; ?>" 
                                            <?php echo ($editResource['topic_id'] ?? '') == $topic['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($topic['name']); ?>
                                        <?php if ($topic['category_name']): ?>
                                            (<?php echo htmlspecialchars($topic['category_name']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Оценка качества</label>
                            <div class="quality-selector">
                                <div class="btn-group" role="group">
                                    <?php for ($i = 0; $i <= 5; $i++): ?>
                                        <input type="radio" class="btn-check" name="quality" id="quality_<?php echo $i; ?>" 
                                               value="<?php echo $i; ?>" 
                                               <?php echo ($editResource['quality'] ?? 0) == $i ? 'checked' : ''; ?>
                                               <?php echo $i === 0 ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-<?php 
                                            echo $i === 0 ? 'secondary' : 
                                                ($i >= 4 ? 'success' : 
                                                ($i >= 2 ? 'warning' : 'danger')); 
                                        ?>" for="quality_<?php echo $i; ?>">
                                            <?php echo $i === 0 ? 'Без оценки' : $i . '★'; ?>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                1 - Плохое, 2 - Ниже среднего, 3 - Среднее, 4 - Хорошее, 5 - Отличное
                            </small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Время создания</label>
                            <input type="datetime-local" class="form-control" name="created_at" id="resource_created_at" 
                                   value="<?php echo isset($editResource) ? date('Y-m-d\TH:i', strtotime($editResource['created_at'])) : date('Y-m-d\TH:i'); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="save_resource" class="btn btn-primary">Сохранить ресурс</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Скрытая форма для удаления -->
<form method="POST" id="deleteResourceForm" style="display: none;">
    <input type="hidden" name="resource_id" id="delete_resource_id">
    <input type="hidden" name="delete_resource">
</form>

<style>
.resource-card {
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}
.resource-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}
.resource-card .card-header {
    padding: 0.75rem 1rem;
}
.quality-stars {
    font-size: 1.1rem;
}
.quality-stars .bi-star-fill {
    color: #ffc107;
}
.quality-selector .btn-group {
    flex-wrap: wrap;
}
.quality-selector .btn {
    min-width: 60px;
}
</style>

<script>
// Функции для работы с ресурсами
function showAddResourceModal() {
    document.getElementById('resource_id').value = '';
    document.getElementById('resource_url').value = '';
    document.getElementById('resource_description').value = '';
    document.getElementById('resource_type').value = 'page';
    document.getElementById('resource_category').value = '';
    document.getElementById('resource_topic').value = '';
    document.getElementById('resource_created_at').value = new Date().toISOString().slice(0, 16);
    document.getElementById('quality_0').checked = true;
    document.getElementById('resourceModalTitle').textContent = 'Добавление ресурса';
    
    var modal = new bootstrap.Modal(document.getElementById('resourceModal'));
    modal.show();
}

function editResource(id) {
    window.location.href = 'resources.php?edit=' + id;
}

function deleteResource(id) {
    if (confirm('Вы уверены, что хотите удалить этот ресурс?')) {
        document.getElementById('delete_resource_id').value = id;
        document.getElementById('deleteResourceForm').submit();
    }
}

// Показываем модальное окно если есть редактируемый ресурс
<?php if ($editResource): ?>
document.addEventListener('DOMContentLoaded', function() {
    var resourceModal = new bootstrap.Modal(document.getElementById('resourceModal'));
    resourceModal.show();
});
<?php endif; ?>

// Клик по карточке ресурса
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.resource-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown') && !e.target.closest('button') && !e.target.closest('a')) {
                const id = this.closest('[id^="resource_"]').id.replace('resource_', '');
                editResource(id);
            }
        });
    });
});

// Валидация URL
document.getElementById('resourceForm')?.addEventListener('submit', function(e) {
    const url = document.getElementById('resource_url').value.trim();
    try {
        new URL(url);
    } catch (err) {
        e.preventDefault();
        alert('Введите корректный URL (включая http:// или https://)');
    }
});

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