<?php
// edit_lesson.php
require_once 'config.php';
requireAuth();

$lessonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$lessonId) {
    header('Location: diaries.php');
    exit;
}

// Получаем информацию о занятии
$stmt = $pdo->prepare("
    SELECT l.*, 
           d.id as diary_id,
           d.name as diary_name,
           d.tutor_id,
           d.lesson_cost as default_cost,
           d.lesson_duration as default_duration,
           s.id as student_id,
           s.first_name as student_first_name,
           s.last_name as student_last_name,
           GROUP_CONCAT(DISTINCT lt.topic_id) as selected_topics,
           GROUP_CONCAT(DISTINCT CONCAT(lr.resource_id, ':', COALESCE(lr.comment, ''))) as selected_resources
    FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    JOIN students s ON d.student_id = s.id
    LEFT JOIN lesson_topics lt ON l.id = lt.lesson_id
    LEFT JOIN lesson_resources lr ON l.id = lr.lesson_id
    WHERE l.id = ? AND d.tutor_id = ?
    GROUP BY l.id
");
$stmt->execute([$lessonId, $_SESSION['user_id']]);
$lesson = $stmt->fetch();

if (!$lesson) {
    header('Location: diaries.php');
    exit;
}

$pageTitle = 'Редактирование занятия';
$currentUser = getCurrentUser($pdo);

$message = '';
$error = '';

// Получение списка тем для выбора
$stmt = $pdo->query("
    SELECT t.*, c.name as category_name, c.color as category_color
    FROM topics t
    LEFT JOIN categories c ON t.category_id = c.id
    ORDER BY t.name
");
$topics = $stmt->fetchAll();

// Получение списка меток для фильтрации
$stmt = $pdo->query("SELECT * FROM tags ORDER BY 
    CASE color 
        WHEN 'green' THEN 1 
        WHEN 'red' THEN 2 
        WHEN 'blue' THEN 3 
        WHEN 'grey' THEN 4 
    END, name");
$tags = $stmt->fetchAll();

// Получение списка ресурсов
$stmt = $pdo->query("
    SELECT r.*, c.name as category_name 
    FROM resources r
    LEFT JOIN categories c ON r.category_id = c.id
    ORDER BY r.quality DESC, r.created_at DESC
");
$resources = $stmt->fetchAll();

// Получение списка всех домашних заданий для поиска
$stmt = $pdo->prepare("
    SELECT DISTINCT l.homework, l.lesson_date, d.name as diary_name,
           s.first_name, s.last_name
    FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    JOIN students s ON d.student_id = s.id
    WHERE l.homework IS NOT NULL AND l.homework != ''
    ORDER BY l.lesson_date DESC
    LIMIT 50
");
$stmt->execute();
$allHomeworks = $stmt->fetchAll();

// Обработка сохранения занятия
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_lesson'])) {
        $lessonDate = $_POST['lesson_date'] ?? $lesson['lesson_date'];
        $startTime = $_POST['start_time'] ?? substr($lesson['start_time'] ?? '00:00', 0, 5);
        $duration = (int)($_POST['duration'] ?? $lesson['default_duration'] ?? 60);
        $cost = $_POST['cost'] !== '' ? (float)$_POST['cost'] : $lesson['default_cost'];
        $isConducted = isset($_POST['is_conducted']) ? 1 : 0;
        $isPaid = isset($_POST['is_paid']) ? 1 : 0;
        $homework = $_POST['homework'] ?? '';
        $comment = $_POST['comment'] ?? '';
        $linkUrl = $_POST['link_url'] ?? '';
        $linkComment = $_POST['link_comment'] ?? '';
        $gradeLesson = $_POST['grade_lesson'] !== '' ? (float)$_POST['grade_lesson'] : null;
        $gradeComment = $_POST['grade_comment'] ?? '';
        $gradeHomework = $_POST['grade_homework'] !== '' ? (float)$_POST['grade_homework'] : null;
        $gradeHomeworkComment = $_POST['grade_homework_comment'] ?? '';
        
        $selectedTopics = $_POST['topics'] ?? [];
        $selectedResources = $_POST['resources'] ?? [];
        
        try {
            $pdo->beginTransaction();
            
            // Обновление занятия
            $stmt = $pdo->prepare("
                UPDATE lessons SET
                    lesson_date = ?, start_time = ?, duration = ?, cost = ?,
                    is_conducted = ?, is_paid = ?, homework = ?, comment = ?,
                    link_url = ?, link_comment = ?, grade_lesson = ?,
                    grade_comment = ?, grade_homework = ?, grade_homework_comment = ?,
                    updated_at = NOW()
                WHERE id = ? AND diary_id = ?
            ");
            $stmt->execute([
                $lessonDate, $startTime, $duration, $cost,
                $isConducted, $isPaid, $homework, $comment,
                $linkUrl, $linkComment, $gradeLesson,
                $gradeComment, $gradeHomework, $gradeHomeworkComment,
                $lessonId, $lesson['diary_id']
            ]);
            
            // Удаляем старые связи
            $stmt = $pdo->prepare("DELETE FROM lesson_topics WHERE lesson_id = ?");
            $stmt->execute([$lessonId]);
            
            $stmt = $pdo->prepare("DELETE FROM lesson_resources WHERE lesson_id = ?");
            $stmt->execute([$lessonId]);
            
            // Добавляем новые темы
            if (!empty($selectedTopics)) {
                $stmt = $pdo->prepare("INSERT INTO lesson_topics (lesson_id, topic_id) VALUES (?, ?)");
                foreach ($selectedTopics as $topicId) {
                    $stmt->execute([$lessonId, $topicId]);
                }
            }
            
            // Добавляем новые ресурсы с комментариями
            if (!empty($selectedResources)) {
                $stmt = $pdo->prepare("INSERT INTO lesson_resources (lesson_id, resource_id, comment) VALUES (?, ?, ?)");
                foreach ($selectedResources as $resourceData) {
                    if (!empty($resourceData['id'])) {
                        $stmt->execute([$lessonId, $resourceData['id'], $resourceData['comment'] ?? '']);
                    }
                }
            }
            
            $pdo->commit();
            $message = 'Занятие успешно обновлено';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ошибка при сохранении занятия: ' . $e->getMessage();
        }
    } elseif (isset($_POST['delete_lesson'])) {
        // Перенаправляем на страницу подтверждения удаления
        header('Location: diary.php?id=' . $lesson['diary_id'] . '&delete_lesson=' . $lessonId);
        exit;
    } elseif (isset($_POST['add_topic'])) {
        // Быстрое добавление новой темы
        $topicName = trim($_POST['new_topic_name'] ?? '');
        
        if (!empty($topicName)) {
            $stmt = $pdo->prepare("INSERT INTO topics (name, created_by) VALUES (?, ?)");
            $stmt->execute([$topicName, $currentUser['id']]);
            $newTopicId = $pdo->lastInsertId();
            
            // Возвращаем JSON для AJAX
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'id' => $newTopicId, 'name' => $topicName]);
                exit;
            }
        }
    }
}

// Подготовка выбранных тем и ресурсов
$selectedTopics = [];
if ($lesson['selected_topics']) {
    $selectedTopics = explode(',', $lesson['selected_topics']);
}

$selectedResources = [];
if ($lesson['selected_resources']) {
    $resourcesData = explode(',', $lesson['selected_resources']);
    foreach ($resourcesData as $resource) {
        $parts = explode(':', $resource);
        if (count($parts) >= 2) {
            $resourceId = $parts[0];
            $comment = $parts[1];
            $selectedResources[$resourceId] = $comment;
        }
    }
}

include 'header.php';
?>

<div class="container-fluid px-4">
    <!-- Заголовок и навигация -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3">Редактирование занятия</h1>
            <p class="text-muted">
                <a href="diary.php?id=<?php echo $lesson['diary_id']; ?>" class="text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Вернуться к дневнику
                </a>
                <span class="mx-2">|</span>
                <strong><?php echo htmlspecialchars($lesson['diary_name']); ?></strong>
                <span class="mx-2">•</span>
                <?php echo htmlspecialchars($lesson['student_last_name'] . ' ' . $lesson['student_first_name']); ?>
                <span class="mx-2">•</span>
                <?php echo date('d.m.Y', strtotime($lesson['lesson_date'])); ?> 
                <?php echo substr($lesson['start_time'], 0, 5); ?>
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                <i class="bi bi-trash"></i> Удалить занятие
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
    
    <!-- Форма редактирования -->
    <form method="POST" action="edit_lesson.php?id=<?php echo $lessonId; ?>" id="lessonForm">
        <div class="row">
            <!-- Левая колонка -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Основная информация</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Дата</label>
                                <input type="date" class="form-control" name="lesson_date" 
                                       value="<?php echo $lesson['lesson_date']; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Время начала</label>
                                <input type="time" class="form-control" name="start_time" 
                                       value="<?php echo substr($lesson['start_time'], 0, 5); ?>" 
                                       step="60" required>
                                <small class="text-muted">Минуты округляются до 00</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Длительность (мин)</label>
                                <input type="number" class="form-control" name="duration" 
                                       value="<?php echo $lesson['duration'] ?: $lesson['default_duration']; ?>" 
                                       min="15" step="15">
                                <small class="text-muted">По умолчанию: <?php echo $lesson['default_duration'] ?: 60; ?> мин</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Стоимость (₽)</label>
                                <input type="number" class="form-control" name="cost" 
                                       value="<?php echo $lesson['cost'] ?: $lesson['default_cost']; ?>" 
                                       step="100" min="0">
                                <small class="text-muted">По умолчанию: <?php echo $lesson['default_cost'] ?: 'не указана'; ?> ₽</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_conducted" id="is_conducted" 
                                           <?php echo $lesson['is_conducted'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_conducted">
                                        <i class="bi bi-check-circle"></i> Занятие проведено
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_paid" id="is_paid" 
                                           <?php echo $lesson['is_paid'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_paid">
                                        <i class="bi bi-cash"></i> Оплачено
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Комментарий к занятию</label>
                            <textarea class="form-control" name="comment" rows="3"><?php echo htmlspecialchars($lesson['comment'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ссылка</label>
                            <input type="url" class="form-control" name="link_url" 
                                   value="<?php echo htmlspecialchars($lesson['link_url'] ?? ''); ?>" 
                                   placeholder="https://...">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Комментарий к ссылке</label>
                            <input type="text" class="form-control" name="link_comment" 
                                   value="<?php echo htmlspecialchars($lesson['link_comment'] ?? ''); ?>" 
                                   placeholder="Описание ссылки">
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Оценки</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Оценка за занятие (0-5)</label>
                                <input type="number" class="form-control" name="grade_lesson" 
                                       value="<?php echo $lesson['grade_lesson']; ?>" 
                                       min="0" max="5" step="0.1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Оценка за ДЗ (0-5)</label>
                                <input type="number" class="form-control" name="grade_homework" 
                                       value="<?php echo $lesson['grade_homework']; ?>" 
                                       min="0" max="5" step="0.1">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Комментарий к оценке</label>
                            <textarea class="form-control" name="grade_comment" rows="2"><?php echo htmlspecialchars($lesson['grade_comment'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Комментарий к ДЗ</label>
                            <textarea class="form-control" name="grade_homework_comment" rows="2"><?php echo htmlspecialchars($lesson['grade_homework_comment'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Правая колонка -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Темы занятия</h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="showTopicSelector()">
                                <i class="bi bi-book"></i> Выбрать
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="showAddTopicModal()">
                                <i class="bi bi-plus-circle"></i> Новая
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="selectedTopics" class="border rounded p-3 bg-light" style="min-height: 100px;">
                            <!-- Выбранные темы будут отображаться здесь -->
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Домашнее задание</h5>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="searchHomework()">
                            <i class="bi bi-search"></i> Поиск
                        </button>
                    </div>
                    <div class="card-body">
                        <textarea class="form-control" name="homework" rows="4"><?php echo htmlspecialchars($lesson['homework'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Ресурсы</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="showResourceSelector()">
                            <i class="bi bi-files"></i> Выбрать
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="selectedResources" class="border rounded p-3 bg-light" style="min-height: 100px;">
                            <!-- Выбранные ресурсы будут отображаться здесь -->
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Информация о занятии</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>Создано:</th>
                                <td><?php echo date('d.m.Y H:i', strtotime($lesson['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Последнее изменение:</th>
                                <td><?php echo $lesson['updated_at'] ? date('d.m.Y H:i', strtotime($lesson['updated_at'])) : '—'; ?></td>
                            </tr>
                            <tr>
                                <th>ID занятия:</th>
                                <td>#<?php echo $lesson['id']; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-12 text-center">
                <button type="submit" name="save_lesson" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-check-circle"></i> Сохранить изменения
                </button>
                <a href="diary.php?id=<?php echo $lesson['diary_id']; ?>" class="btn btn-secondary btn-lg px-5 ms-2">
                    <i class="bi bi-x-circle"></i> Отмена
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Модальное окно выбора тем -->
<div class="modal fade" id="topicSelectorModal" tabindex="-1" data-bs-size="lg">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Выбор тем</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="topicSearch" placeholder="Поиск по названию">
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" id="topicCategoryFilter">
                            <option value="">Все категории</option>
                            <?php 
                            $uniqueCategories = [];
                            foreach ($topics as $topic) {
                                if ($topic['category_name'] && !in_array($topic['category_name'], $uniqueCategories)) {
                                    $uniqueCategories[] = $topic['category_name'];
                                    echo '<option value="' . htmlspecialchars($topic['category_name']) . '">' . htmlspecialchars($topic['category_name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="row" id="topicsList">
                    <?php foreach ($topics as $topic): ?>
                        <div class="col-md-4 mb-2 topic-item" data-name="<?php echo strtolower($topic['name']); ?>" data-category="<?php echo strtolower($topic['category_name'] ?? ''); ?>">
                            <div class="form-check">
                                <input class="form-check-input topic-checkbox" type="checkbox" 
                                       value="<?php echo $topic['id']; ?>" id="topic_<?php echo $topic['id']; ?>">
                                <label class="form-check-label" for="topic_<?php echo $topic['id']; ?>">
                                    <?php echo htmlspecialchars($topic['name']); ?>
                                    <?php if ($topic['category_name']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($topic['category_name']); ?></small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="applySelectedTopics()">Применить</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно добавления новой темы -->
<div class="modal fade" id="addTopicModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Добавление новой темы</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Название темы</label>
                    <input type="text" class="form-control" id="newTopicName" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-success" onclick="addNewTopic()">Добавить</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно выбора ресурсов -->
<div class="modal fade" id="resourceSelectorModal" tabindex="-1" data-bs-size="lg">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Выбор ресурсов</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="resourceSearch" placeholder="Поиск">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="resourceTypeFilter">
                            <option value="">Все типы</option>
                            <option value="page">Страница</option>
                            <option value="document">Документ</option>
                            <option value="video">Видео</option>
                            <option value="audio">Звук</option>
                            <option value="other">Другое</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="resourceTagFilter">
                            <option value="">Все метки</option>
                            <?php foreach ($tags as $tag): ?>
                                <option value="<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row" id="resourcesList">
                    <?php foreach ($resources as $resource): ?>
                        <div class="col-md-6 mb-2 resource-item" 
                             data-name="<?php echo strtolower($resource['description'] . ' ' . $resource['url']); ?>"
                             data-type="<?php echo $resource['type']; ?>"
                             data-tags="<?php echo $resource['tags'] ?? ''; ?>">
                            <div class="card">
                                <div class="card-body">
                                    <div class="form-check">
                                        <input class="form-check-input resource-checkbox" type="checkbox" 
                                               value="<?php echo $resource['id']; ?>" id="resource_<?php echo $resource['id']; ?>">
                                        <label class="form-check-label" for="resource_<?php echo $resource['id']; ?>">
                                            <strong><?php echo htmlspecialchars($resource['description'] ?: 'Без описания'); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <i class="bi bi-link"></i> <?php echo htmlspecialchars(substr($resource['url'], 0, 50)); ?>...
                                                <br>
                                                <span class="badge bg-secondary"><?php echo $resource['type']; ?></span>
                                                <?php if ($resource['quality'] > 0): ?>
                                                    <span class="badge bg-warning">★ <?php echo $resource['quality']; ?></span>
                                                <?php endif; ?>
                                            </small>
                                        </label>
                                    </div>
                                    <div class="mt-2" id="resource_comment_<?php echo $resource['id']; ?>" style="display: none;">
                                        <input type="text" class="form-control form-control-sm" 
                                               placeholder="Комментарий к ресурсу" 
                                               id="resource_comment_input_<?php echo $resource['id']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="applySelectedResources()">Применить</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно поиска домашних заданий -->
<div class="modal fade" id="homeworkSearchModal" tabindex="-1" data-bs-size="lg">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Поиск в домашних заданиях</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <input type="text" class="form-control" id="homeworkSearchText" placeholder="Поиск по тексту">
                    </div>
                    <div class="col-md-4">
                        <input type="date" class="form-control" id="homeworkDateFilter" placeholder="Дата">
                    </div>
                </div>
                <div id="homeworkResults" class="list-group">
                    <?php foreach ($allHomeworks as $hw): ?>
                        <a href="#" class="list-group-item list-group-item-action" onclick="selectHomework('<?php echo htmlspecialchars(addslashes($hw['homework'])); ?>')">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($hw['diary_name']); ?></h6>
                                <small><?php echo date('d.m.Y', strtotime($hw['lesson_date'])); ?></small>
                            </div>
                            <p class="mb-1"><?php echo htmlspecialchars(substr($hw['homework'], 0, 100)) . (strlen($hw['homework']) > 100 ? '...' : ''); ?></p>
                            <small class="text-muted"><?php echo htmlspecialchars($hw['last_name'] . ' ' . $hw['first_name']); ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
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
                <p>Вы уверены, что хотите удалить это занятие?</p>
                <p class="text-danger">Это действие нельзя отменить!</p>
                <p>Будут удалены все связанные темы и ресурсы.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <form method="POST" action="edit_lesson.php?id=<?php echo $lessonId; ?>" style="display: inline;">
                    <button type="submit" name="delete_lesson" class="btn btn-danger">Удалить</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Скрытая форма для добавления темы -->
<form method="POST" id="addTopicForm" style="display: none;">
    <input type="hidden" name="new_topic_name" id="add_topic_name">
    <input type="hidden" name="add_topic">
    <input type="hidden" name="ajax" value="1">
</form>

<style>
.lesson-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.lesson-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
#selectedTopics .badge, #selectedResources .badge {
    font-size: 0.9rem;
    padding: 0.5rem 0.8rem;
}
.topic-item, .resource-item {
    cursor: pointer;
}
.list-group-item {
    cursor: pointer;
}
.list-group-item:hover {
    background-color: #f8f9fa;
}
</style>

<script>
// Массивы для хранения выбранных элементов
let selectedTopics = new Map();
let selectedResources = new Map();

// Инициализация выбранных тем и ресурсов
document.addEventListener('DOMContentLoaded', function() {
    // Загружаем выбранные темы
    <?php foreach ($selectedTopics as $topicId): ?>
        <?php 
        $topicName = '';
        foreach ($topics as $topic) {
            if ($topic['id'] == $topicId) {
                $topicName = $topic['name'];
                break;
            }
        }
        ?>
        selectedTopics.set(<?php echo $topicId; ?>, '<?php echo addslashes($topicName); ?>');
    <?php endforeach; ?>
    
    // Загружаем выбранные ресурсы
    <?php foreach ($selectedResources as $resourceId => $comment): ?>
        selectedResources.set(<?php echo $resourceId; ?>, '<?php echo addslashes($comment); ?>');
    <?php endforeach; ?>
    
    updateSelectedTopics();
    updateSelectedResources();
});

// Функции для работы с темами
function showTopicSelector() {
    // Сбрасываем фильтры
    document.getElementById('topicSearch').value = '';
    document.getElementById('topicCategoryFilter').value = '';
    
    // Отмечаем уже выбранные темы
    document.querySelectorAll('.topic-checkbox').forEach(cb => {
        cb.checked = selectedTopics.has(parseInt(cb.value));
    });
    
    // Показываем все темы
    document.querySelectorAll('.topic-item').forEach(item => {
        item.style.display = 'block';
    });
    
    var modal = new bootstrap.Modal(document.getElementById('topicSelectorModal'));
    modal.show();
}

function showAddTopicModal() {
    document.getElementById('newTopicName').value = '';
    var modal = new bootstrap.Modal(document.getElementById('addTopicModal'));
    modal.show();
}

function addNewTopic() {
    const name = document.getElementById('newTopicName').value.trim();
    if (!name) {
        alert('Введите название темы');
        return;
    }
    
    document.getElementById('add_topic_name').value = name;
    
    fetch('edit_lesson.php?id=<?php echo $lessonId; ?>', {
        method: 'POST',
        body: new FormData(document.getElementById('addTopicForm'))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Добавляем тему в список выбранных
            selectedTopics.set(data.id, data.name);
            updateSelectedTopics();
            
            // Добавляем тему в селектор
            const topicsList = document.getElementById('topicsList');
            const newTopicDiv = document.createElement('div');
            newTopicDiv.className = 'col-md-4 mb-2 topic-item';
            newTopicDiv.dataset.name = data.name.toLowerCase();
            newTopicDiv.dataset.category = '';
            newTopicDiv.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input topic-checkbox" type="checkbox" 
                           value="${data.id}" id="topic_${data.id}" checked>
                    <label class="form-check-label" for="topic_${data.id}">
                        ${data.name}
                    </label>
                </div>
            `;
            topicsList.appendChild(newTopicDiv);
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('addTopicModal'));
            modal.hide();
        }
    })
    .catch(error => {
        alert('Ошибка при добавлении темы');
    });
}

function applySelectedTopics() {
    selectedTopics.clear();
    document.querySelectorAll('.topic-checkbox:checked').forEach(cb => {
        const id = parseInt(cb.value);
        const label = cb.closest('.form-check').querySelector('.form-check-label').innerText.split('\n')[0].trim();
        selectedTopics.set(id, label);
    });
    
    updateSelectedTopics();
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('topicSelectorModal'));
    modal.hide();
}

function updateSelectedTopics() {
    const container = document.getElementById('selectedTopics');
    container.innerHTML = '';
    
    if (selectedTopics.size === 0) {
        container.innerHTML = '<span class="text-muted">Темы не выбраны</span>';
        return;
    }
    
    selectedTopics.forEach((name, id) => {
        const badge = document.createElement('span');
        badge.className = 'badge bg-info me-2 mb-2 p-2';
        badge.innerHTML = `${name} <i class="bi bi-x-circle ms-1" style="cursor: pointer;" onclick="removeTopic(${id})"></i>`;
        container.appendChild(badge);
        
        // Добавляем скрытое поле для отправки
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'topics[]';
        input.value = id;
        container.appendChild(input);
    });
}

function removeTopic(id) {
    selectedTopics.delete(id);
    updateSelectedTopics();
}

// Функции для работы с ресурсами
function showResourceSelector() {
    // Сбрасываем фильтры
    document.getElementById('resourceSearch').value = '';
    document.getElementById('resourceTypeFilter').value = '';
    document.getElementById('resourceTagFilter').value = '';
    
    // Отмечаем уже выбранные ресурсы
    document.querySelectorAll('.resource-checkbox').forEach(cb => {
        cb.checked = selectedResources.has(parseInt(cb.value));
        const commentDiv = document.getElementById('resource_comment_' + cb.value);
        if (commentDiv) {
            commentDiv.style.display = selectedResources.has(parseInt(cb.value)) ? 'block' : 'none';
            if (selectedResources.has(parseInt(cb.value))) {
                document.getElementById('resource_comment_input_' + cb.value).value = selectedResources.get(parseInt(cb.value)) || '';
            }
        }
    });
    
    // Показываем все ресурсы
    document.querySelectorAll('.resource-item').forEach(item => {
        item.style.display = 'block';
    });
    
    var modal = new bootstrap.Modal(document.getElementById('resourceSelectorModal'));
    modal.show();
}

function applySelectedResources() {
    selectedResources.clear();
    
    document.querySelectorAll('.resource-checkbox:checked').forEach(cb => {
        const id = parseInt(cb.value);
        const comment = document.getElementById('resource_comment_input_' + id)?.value || '';
        selectedResources.set(id, comment);
    });
    
    updateSelectedResources();
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('resourceSelectorModal'));
    modal.hide();
}

function updateSelectedResources() {
    const container = document.getElementById('selectedResources');
    container.innerHTML = '';
    
    if (selectedResources.size === 0) {
        container.innerHTML = '<span class="text-muted">Ресурсы не выбраны</span>';
        return;
    }
    
    selectedResources.forEach((comment, id) => {
        const div = document.createElement('div');
        div.className = 'mb-2 p-2 border rounded';
        div.innerHTML = `
            <div class="d-flex justify-content-between">
                <span><i class="bi bi-file-text"></i> Ресурс #${id}</span>
                <i class="bi bi-x-circle text-danger" style="cursor: pointer;" onclick="removeResource(${id})"></i>
            </div>
            <input type="hidden" name="resources[${id}][id]" value="${id}">
            <input type="text" class="form-control form-control-sm mt-1" 
                   name="resources[${id}][comment]" value="${comment}" placeholder="Комментарий к ресурсу">
        `;
        container.appendChild(div);
    });
}

function removeResource(id) {
    selectedResources.delete(id);
    updateSelectedResources();
}

// Фильтрация тем
document.getElementById('topicSearch')?.addEventListener('input', function() {
    const search = this.value.toLowerCase();
    document.querySelectorAll('.topic-item').forEach(item => {
        const name = item.dataset.name;
        item.style.display = name.includes(search) ? 'block' : 'none';
    });
});

document.getElementById('topicCategoryFilter')?.addEventListener('change', function() {
    const category = this.value.toLowerCase();
    document.querySelectorAll('.topic-item').forEach(item => {
        if (!category || item.dataset.category === category) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
});

// Фильтрация ресурсов
document.getElementById('resourceSearch')?.addEventListener('input', function() {
    const search = this.value.toLowerCase();
    document.querySelectorAll('.resource-item').forEach(item => {
        const name = item.dataset.name;
        item.style.display = name.includes(search) ? 'block' : 'none';
    });
});

document.getElementById('resourceTypeFilter')?.addEventListener('change', filterResources);
document.getElementById('resourceTagFilter')?.addEventListener('change', filterResources);

function filterResources() {
    const type = document.getElementById('resourceTypeFilter').value;
    const tagId = document.getElementById('resourceTagFilter').value;
    
    document.querySelectorAll('.resource-item').forEach(item => {
        let show = true;
        
        if (type && item.dataset.type !== type) {
            show = false;
        }
        
        if (tagId && item.dataset.tags) {
            const tags = item.dataset.tags.split(';');
            if (!tags.includes(tagId)) {
                show = false;
            }
        }
        
        item.style.display = show ? 'block' : 'none';
    });
}

// Обработка выбора ресурса
document.querySelectorAll('.resource-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
        const id = this.value;
        const commentDiv = document.getElementById('resource_comment_' + id);
        if (commentDiv) {
            commentDiv.style.display = this.checked ? 'block' : 'none';
        }
    });
});

// Функции для домашнего задания
function searchHomework() {
    var modal = new bootstrap.Modal(document.getElementById('homeworkSearchModal'));
    modal.show();
}

function selectHomework(text) {
    document.querySelector('textarea[name="homework"]').value = text;
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('homeworkSearchModal'));
    modal.hide();
}

// Фильтрация домашних заданий
document.getElementById('homeworkSearchText')?.addEventListener('input', function() {
    const search = this.value.toLowerCase();
    const date = document.getElementById('homeworkDateFilter')?.value;
    
    document.querySelectorAll('#homeworkResults .list-group-item').forEach(item => {
        const text = item.querySelector('p').textContent.toLowerCase();
        const itemDate = item.querySelector('small').textContent;
        
        let show = true;
        
        if (search && !text.includes(search)) {
            show = false;
        }
        
        if (date) {
            const itemDateFormatted = itemDate.split('.').reverse().join('-');
            if (itemDateFormatted !== date) {
                show = false;
            }
        }
        
        item.style.display = show ? 'block' : 'none';
    });
});

document.getElementById('homeworkDateFilter')?.addEventListener('change', function() {
    const event = new Event('input');
    document.getElementById('homeworkSearchText').dispatchEvent(event);
});

// Округление минут до 00
document.querySelector('input[name="start_time"]')?.addEventListener('change', function() {
    const time = this.value;
    if (time) {
        const [hours] = time.split(':');
        this.value = hours + ':00';
    }
});

// Подтверждение удаления
function confirmDelete() {
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

// Автоматическое скрытие сообщений
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