<?php
// diary.php
require_once 'config.php';
requireAuth();

$diaryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$diaryId) {
    header('Location: diaries.php');
    exit;
}

// Получаем информацию о дневнике
$stmt = $pdo->prepare("
    SELECT d.*, 
           s.id as student_id,
           s.first_name as student_first_name, 
           s.last_name as student_last_name,
           s.class as student_class,
           c.name as category_name,
           c.color as category_color
    FROM diaries d
    JOIN students s ON d.student_id = s.id
    LEFT JOIN categories c ON d.category_id = c.id
    WHERE d.id = ? AND d.tutor_id = ?
");
$stmt->execute([$diaryId, $_SESSION['user_id']]);
$diary = $stmt->fetch();

if (!$diary) {
    header('Location: diaries.php');
    exit;
}

$pageTitle = 'Дневник: ' . $diary['name'];
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

// Обработка действий с занятиями
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_lesson'])) {
        $lessonId = $_POST['lesson_id'] ?? null;
        $lessonDate = $_POST['lesson_date'] ?? date('Y-m-d');
        $startTime = $_POST['start_time'] ?? '00:00';
        $duration = (int)($_POST['duration'] ?? $diary['lesson_duration'] ?? 60);
        $cost = $_POST['cost'] !== '' ? (float)$_POST['cost'] : $diary['lesson_cost'];
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
            
            if ($lessonId) {
                // Обновление существующего занятия
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
                    $lessonId, $diaryId
                ]);
                
                // Удаляем старые связи
                $stmt = $pdo->prepare("DELETE FROM lesson_topics WHERE lesson_id = ?");
                $stmt->execute([$lessonId]);
                
                $stmt = $pdo->prepare("DELETE FROM lesson_resources WHERE lesson_id = ?");
                $stmt->execute([$lessonId]);
                
                $message = 'Занятие обновлено';
            } else {
                // Добавление нового занятия
                $stmt = $pdo->prepare("
                    INSERT INTO lessons (
                        diary_id, lesson_date, start_time, duration, cost,
                        is_conducted, is_paid, homework, comment,
                        link_url, link_comment, grade_lesson, grade_comment,
                        grade_homework, grade_homework_comment, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $diaryId, $lessonDate, $startTime, $duration, $cost,
                    $isConducted, $isPaid, $homework, $comment,
                    $linkUrl, $linkComment, $gradeLesson, $gradeComment,
                    $gradeHomework, $gradeHomeworkComment, $currentUser['id']
                ]);
                $lessonId = $pdo->lastInsertId();
                
                $message = 'Занятие добавлено';
            }
            
            // Добавляем темы
            if (!empty($selectedTopics)) {
                $stmt = $pdo->prepare("INSERT INTO lesson_topics (lesson_id, topic_id) VALUES (?, ?)");
                foreach ($selectedTopics as $topicId) {
                    $stmt->execute([$lessonId, $topicId]);
                }
            }
            
            // Добавляем ресурсы с комментариями
            if (!empty($selectedResources)) {
                $stmt = $pdo->prepare("INSERT INTO lesson_resources (lesson_id, resource_id, comment) VALUES (?, ?, ?)");
                foreach ($selectedResources as $resourceData) {
                    if (!empty($resourceData['id'])) {
                        $stmt->execute([$lessonId, $resourceData['id'], $resourceData['comment'] ?? '']);
                    }
                }
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ошибка при сохранении занятия: ' . $e->getMessage();
        }
    } elseif (isset($_POST['delete_lesson'])) {
        $lessonId = $_POST['lesson_id'] ?? null;
        
        if ($lessonId) {
            try {
                $pdo->beginTransaction();
                
                // Удаляем связи
                $stmt = $pdo->prepare("DELETE FROM lesson_topics WHERE lesson_id = ?");
                $stmt->execute([$lessonId]);
                
                $stmt = $pdo->prepare("DELETE FROM lesson_resources WHERE lesson_id = ?");
                $stmt->execute([$lessonId]);
                
                // Удаляем занятие
                $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ? AND diary_id = ?");
                $stmt->execute([$lessonId, $diaryId]);
                
                $pdo->commit();
                $message = 'Занятие удалено';
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Ошибка при удалении занятия: ' . $e->getMessage();
            }
        }
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

// Получение списка занятий
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$startDate = "$year-$month-01";
$endDate = date('Y-m-t', strtotime($startDate));

$stmt = $pdo->prepare("
    SELECT l.*, 
           GROUP_CONCAT(DISTINCT t.id) as topic_ids,
           GROUP_CONCAT(DISTINCT t.name) as topic_names,
           GROUP_CONCAT(DISTINCT CONCAT(lr.resource_id, ':', lr.comment)) as resource_data
    FROM lessons l
    LEFT JOIN lesson_topics lt ON l.id = lt.lesson_id
    LEFT JOIN topics t ON lt.topic_id = t.id
    LEFT JOIN lesson_resources lr ON l.id = lr.lesson_id
    WHERE l.diary_id = ? AND l.lesson_date BETWEEN ? AND ?
    GROUP BY l.id
    ORDER BY l.lesson_date DESC, l.start_time DESC
");
$stmt->execute([$diaryId, $startDate, $endDate]);
$lessons = $stmt->fetchAll();

// Получение статистики
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_lessons,
        SUM(CASE WHEN is_conducted = 1 THEN 1 ELSE 0 END) as conducted_lessons,
        SUM(CASE WHEN is_paid = 1 THEN cost ELSE 0 END) as total_paid,
        SUM(cost) as total_cost,
        AVG(grade_lesson) as avg_grade,
        AVG(grade_homework) as avg_homework_grade
    FROM lessons
    WHERE diary_id = ?
");
$stmt->execute([$diaryId]);
$stats = $stmt->fetch();

// Получение данных для редактирования
$editLesson = null;
if (isset($_GET['edit_lesson'])) {
    $stmt = $pdo->prepare("
        SELECT l.*, 
               GROUP_CONCAT(DISTINCT lt.topic_id) as selected_topics,
               GROUP_CONCAT(DISTINCT CONCAT(lr.resource_id, ':', lr.comment)) as selected_resources
        FROM lessons l
        LEFT JOIN lesson_topics lt ON l.id = lt.lesson_id
        LEFT JOIN lesson_resources lr ON l.id = lr.lesson_id
        WHERE l.id = ? AND l.diary_id = ?
        GROUP BY l.id
    ");
    $stmt->execute([$_GET['edit_lesson'], $diaryId]);
    $editLesson = $stmt->fetch();
    
    if ($editLesson) {
        $editLesson['selected_topics'] = $editLesson['selected_topics'] ? explode(',', $editLesson['selected_topics']) : [];
        
        $editLesson['selected_resources'] = [];
        if ($editLesson['selected_resources']) {
            $resources = explode(',', $editLesson['selected_resources']);
            foreach ($resources as $resource) {
                list($id, $comment) = explode(':', $resource);
                $editLesson['selected_resources'][$id] = $comment;
            }
        }
    }
}

include 'header.php';
?>

<div class="container-fluid px-4">
    <!-- Заголовок и навигация -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3"><?php echo htmlspecialchars($diary['name']); ?></h1>
            <p class="text-muted">
                Ученик: <strong><?php echo htmlspecialchars($diary['student_last_name'] . ' ' . $diary['student_first_name']); ?></strong>
                <?php if ($diary['student_class']): ?>(<?php echo htmlspecialchars($diary['student_class']); ?> класс)<?php endif; ?>
                <?php if ($diary['category_name']): ?>
                    <span class="badge ms-2" style="background-color: <?php echo $diary['category_color']; ?>; color: white;">
                        <?php echo htmlspecialchars($diary['category_name']); ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <a href="diaries.php" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> К списку
            </a>
            <button type="button" class="btn btn-success" onclick="showAddLessonModal()">
                <i class="bi bi-plus-circle"></i> Добавить занятие
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
                    <h6 class="card-title">Всего занятий</h6>
                    <h3><?php echo $stats['total_lessons'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Проведено</h6>
                    <h3><?php echo $stats['conducted_lessons'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Оплачено</h6>
                    <h3><?php echo number_format($stats['total_paid'] ?? 0, 0, ',', ' '); ?> ₽</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Средняя оценка</h6>
                    <h3><?php echo $stats['avg_grade'] ? number_format($stats['avg_grade'], 1) : '—'; ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Навигация по месяцам -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <a href="?id=<?php echo $diaryId; ?>&month=<?php echo $month == 1 ? 12 : $month - 1; ?>&year=<?php echo $month == 1 ? $year - 1 : $year; ?>" class="btn btn-outline-primary">
                    <i class="bi bi-chevron-left"></i> Предыдущий месяц
                </a>
                <h5><?php echo date('F Y', strtotime("$year-$month-01")); ?></h5>
                <a href="?id=<?php echo $diaryId; ?>&month=<?php echo $month == 12 ? 1 : $month + 1; ?>&year=<?php echo $month == 12 ? $year + 1 : $year; ?>" class="btn btn-outline-primary">
                    Следующий месяц <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Список занятий -->
    <div class="row">
        <?php if (empty($lessons)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Нет занятий за этот месяц. Нажмите "Добавить занятие", чтобы создать первое занятие.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($lessons as $lesson): ?>
                <div class="col-md-6 col-lg-4 mb-4" id="lesson_<?php echo $lesson['id']; ?>">
                    <div class="card h-100 lesson-card <?php echo $lesson['is_conducted'] ? 'border-success' : ''; ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo date('d.m.Y', strtotime($lesson['lesson_date'])); ?></strong>
                                <span class="badge bg-secondary ms-2"><?php echo substr($lesson['start_time'], 0, 5); ?></span>
                                <?php if ($lesson['is_conducted']): ?>
                                    <span class="badge bg-success ms-2">Проведено</span>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button class="dropdown-item" onclick="editLesson(<?php echo $lesson['id']; ?>)">
                                            <i class="bi bi-pencil"></i> Редактировать
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item text-danger" onclick="deleteLesson(<?php echo $lesson['id']; ?>)">
                                            <i class="bi bi-trash"></i> Удалить
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Темы -->
                            <?php if ($lesson['topic_names']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Темы:</small>
                                    <div>
                                        <?php 
                                        $topicsList = explode(',', $lesson['topic_names']);
                                        foreach ($topicsList as $topicName): 
                                        ?>
                                            <span class="badge bg-info me-1"><?php echo htmlspecialchars(trim($topicName)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Комментарий -->
                            <?php if ($lesson['comment']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Комментарий:</small>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($lesson['comment'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Домашнее задание -->
                            <?php if ($lesson['homework']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">ДЗ:</small>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($lesson['homework'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Ссылка -->
                            <?php if ($lesson['link_url']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Ссылка:</small><br>
                                    <a href="<?php echo htmlspecialchars($lesson['link_url']); ?>" target="_blank" class="small">
                                        <?php echo $lesson['link_comment'] ?: $lesson['link_url']; ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Оценки -->
                            <div class="row mt-3">
                                <div class="col-6">
                                    <small class="text-muted">Оценка за занятие:</small>
                                    <div>
                                        <?php if ($lesson['grade_lesson'] !== null): ?>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?php echo $i <= $lesson['grade_lesson'] ? '-fill' : ''; ?> text-warning"></i>
                                            <?php endfor; ?>
                                            <small class="ms-1">(<?php echo $lesson['grade_lesson']; ?>)</small>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Оценка за ДЗ:</small>
                                    <div>
                                        <?php if ($lesson['grade_homework'] !== null): ?>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?php echo $i <= $lesson['grade_homework'] ? '-fill' : ''; ?> text-warning"></i>
                                            <?php endfor; ?>
                                            <small class="ms-1">(<?php echo $lesson['grade_homework']; ?>)</small>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Финансы -->
                            <div class="mt-3 d-flex justify-content-between">
                                <span>
                                    <i class="bi bi-clock"></i> <?php echo $lesson['duration']; ?> мин
                                </span>
                                <span>
                                    <i class="bi bi-currency-ruble"></i> <?php echo number_format($lesson['cost'], 0, ',', ' '); ?>
                                </span>
                                <span>
                                    <?php if ($lesson['is_paid']): ?>
                                        <i class="bi bi-check-circle-fill text-success" title="Оплачено"></i>
                                    <?php else: ?>
                                        <i class="bi bi-circle text-secondary" title="Не оплачено"></i>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно для добавления/редактирования занятия -->
<div class="modal fade" id="lessonModal" tabindex="-1" data-bs-backdrop="static" data-bs-size="xl">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lessonModalTitle">Добавление занятия</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="diary.php?id=<?php echo $diaryId; ?>" id="lessonForm">
                <input type="hidden" name="lesson_id" id="lesson_id" value="<?php echo $editLesson['id'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="row">
                        <!-- Левая колонка -->
                        <div class="col-md-6">
                            <h6 class="mb-3">Основная информация</h6>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Дата</label>
                                    <input type="date" class="form-control" name="lesson_date" id="lesson_date" 
                                           value="<?php echo $editLesson['lesson_date'] ?? date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Время начала</label>
                                    <input type="time" class="form-control" name="start_time" id="start_time" 
                                           value="<?php echo substr($editLesson['start_time'] ?? '00:00', 0, 5); ?>" 
                                           step="60" required>
                                    <small class="text-muted">Минуты округляются до 00</small>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Длительность (мин)</label>
                                    <input type="number" class="form-control" name="duration" id="duration" 
                                           value="<?php echo $editLesson['duration'] ?? $diary['lesson_duration'] ?? 60; ?>" 
                                           min="15" step="15">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Стоимость (₽)</label>
                                    <input type="number" class="form-control" name="cost" id="cost" 
                                           value="<?php echo $editLesson['cost'] ?? $diary['lesson_cost'] ?? ''; ?>" 
                                           step="100" min="0">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_conducted" id="is_conducted" 
                                               <?php echo ($editLesson['is_conducted'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_conducted">
                                            Занятие проведено
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_paid" id="is_paid" 
                                               <?php echo ($editLesson['is_paid'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_paid">
                                            Оплачено
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Комментарий к занятию</label>
                                <textarea class="form-control" name="comment" id="comment" rows="3"><?php echo htmlspecialchars($editLesson['comment'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Ссылка</label>
                                <input type="url" class="form-control" name="link_url" id="link_url" 
                                       value="<?php echo htmlspecialchars($editLesson['link_url'] ?? ''); ?>" placeholder="https://...">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Комментарий к ссылке</label>
                                <input type="text" class="form-control" name="link_comment" id="link_comment" 
                                       value="<?php echo htmlspecialchars($editLesson['link_comment'] ?? ''); ?>" placeholder="Описание ссылки">
                            </div>
                        </div>
                        
                        <!-- Правая колонка -->
                        <div class="col-md-6">
                            <h6 class="mb-3">Темы и оценки</h6>
                            
                            <!-- Выбор тем -->
                            <div class="mb-3">
                                <label class="form-label">Темы занятия</label>
                                <div class="mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="showTopicSelector()">
                                        <i class="bi bi-book"></i> Выбрать из списка
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="showAddTopicModal()">
                                        <i class="bi bi-plus-circle"></i> Добавить новую
                                    </button>
                                </div>
                                <div id="selectedTopics" class="border rounded p-2 bg-light" style="min-height: 60px;">
                                    <!-- Выбранные темы будут отображаться здесь -->
                                </div>
                            </div>
                            
                            <!-- Домашнее задание -->
                            <div class="mb-3">
                                <label class="form-label">Домашнее задание</label>
                                <textarea class="form-control" name="homework" id="homework" rows="3"><?php echo htmlspecialchars($editLesson['homework'] ?? ''); ?></textarea>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="searchHomework()">
                                        <i class="bi bi-search"></i> Поиск в ДЗ
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Оценки -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Оценка за занятие (0-5)</label>
                                    <input type="number" class="form-control" name="grade_lesson" id="grade_lesson" 
                                           value="<?php echo $editLesson['grade_lesson'] ?? ''; ?>" 
                                           min="0" max="5" step="0.1">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Оценка за ДЗ (0-5)</label>
                                    <input type="number" class="form-control" name="grade_homework" id="grade_homework" 
                                           value="<?php echo $editLesson['grade_homework'] ?? ''; ?>" 
                                           min="0" max="5" step="0.1">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Комментарий к оценке</label>
                                <textarea class="form-control" name="grade_comment" id="grade_comment" rows="2"><?php echo htmlspecialchars($editLesson['grade_comment'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Комментарий к ДЗ</label>
                                <textarea class="form-control" name="grade_homework_comment" id="grade_homework_comment" rows="2"><?php echo htmlspecialchars($editLesson['grade_homework_comment'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Ресурсы -->
                            <div class="mb-3">
                                <label class="form-label">Ресурсы из банка</label>
                                <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="showResourceSelector()">
                                    <i class="bi bi-files"></i> Выбрать
                                </button>
                                <div id="selectedResources" class="mt-2 border rounded p-2 bg-light" style="min-height: 60px;">
                                    <!-- Выбранные ресурсы будут отображаться здесь -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="save_lesson" class="btn btn-primary">Сохранить занятие</button>
                </div>
            </form>
        </div>
    </div>
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
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="homeworkSearchText" placeholder="Поиск по тексту">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="homeworkTagFilter">
                            <option value="">Все метки</option>
                            <?php foreach ($tags as $tag): ?>
                                <option value="<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" class="form-control" id="homeworkDateFilter" placeholder="Дата">
                    </div>
                </div>
                <div id="homeworkResults">
                    <!-- Результаты поиска будут загружаться сюда -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Скрытые формы -->
<form method="POST" id="deleteLessonForm" style="display: none;">
    <input type="hidden" name="lesson_id" id="delete_lesson_id">
    <input type="hidden" name="delete_lesson">
</form>

<form method="POST" id="addTopicForm" style="display: none;">
    <input type="hidden" name="new_topic_name" id="add_topic_name">
    <input type="hidden" name="add_topic">
    <input type="hidden" name="ajax" value="1">
</form>

<style>
.lesson-card {
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}
.lesson-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}
.lesson-card.border-success {
    border-width: 2px;
}
.lesson-card .card-header {
    padding: 0.75rem 1rem;
}
.topic-item, .resource-item {
    cursor: pointer;
}
</style>

<script>
let selectedTopics = new Map();
let selectedResources = new Map();

// Функции для работы с занятиями
function showAddLessonModal() {
    resetLessonForm();
    document.getElementById('lessonModalTitle').textContent = 'Добавление занятия';
    
    // Устанавливаем время с минутами 00
    const now = new Date();
    now.setMinutes(0);
    const timeString = now.getHours().toString().padStart(2, '0') + ':00';
    document.getElementById('start_time').value = timeString;
    
    var modal = new bootstrap.Modal(document.getElementById('lessonModal'));
    modal.show();
}

function editLesson(id) {
    window.location.href = 'diary.php?id=<?php echo $diaryId; ?>&edit_lesson=' + id;
}

function deleteLesson(id) {
    if (confirm('Вы уверены, что хотите удалить это занятие?')) {
        document.getElementById('delete_lesson_id').value = id;
        document.getElementById('deleteLessonForm').submit();
    }
}

function resetLessonForm() {
    document.getElementById('lesson_id').value = '';
    document.getElementById('lesson_date').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('duration').value = '<?php echo $diary['lesson_duration'] ?? 60; ?>';
    document.getElementById('cost').value = '<?php echo $diary['lesson_cost'] ?? ''; ?>';
    document.getElementById('is_conducted').checked = false;
    document.getElementById('is_paid').checked = false;
    document.getElementById('comment').value = '';
    document.getElementById('link_url').value = '';
    document.getElementById('link_comment').value = '';
    document.getElementById('homework').value = '';
    document.getElementById('grade_lesson').value = '';
    document.getElementById('grade_homework').value = '';
    document.getElementById('grade_comment').value = '';
    document.getElementById('grade_homework_comment').value = '';
    
    selectedTopics.clear();
    selectedResources.clear();
    updateSelectedTopics();
    updateSelectedResources();
}

// Функции для работы с темами
function showTopicSelector() {
    // Сбрасываем фильтры
    document.getElementById('topicSearch').value = '';
    document.getElementById('topicCategoryFilter').value = '';
    
    // Отмечаем уже выбранные темы
    document.querySelectorAll('.topic-checkbox').forEach(cb => {
        cb.checked = selectedTopics.has(parseInt(cb.value));
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
    
    fetch('diary.php?id=<?php echo $diaryId; ?>', {
        method: 'POST',
        body: new FormData(document.getElementById('addTopicForm'))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Добавляем тему в список выбранных
            selectedTopics.set(data.id, data.name);
            updateSelectedTopics();
            
            // Добавляем тему в селектор (если нужно)
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
                <span>Ресурс #${id}</span>
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

// Фильтрация тем в селекторе
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

// Фильтрация ресурсов в селекторе
document.getElementById('resourceSearch')?.addEventListener('input', function() {
    const search = this.value.toLowerCase();
    document.querySelectorAll('.resource-item').forEach(item => {
        const name = item.dataset.name;
        item.style.display = name.includes(search) ? 'block' : 'none';
    });
});

document.getElementById('resourceTypeFilter')?.addEventListener('change', function() {
    const type = this.value;
    filterResources();
});

document.getElementById('resourceTagFilter')?.addEventListener('change', function() {
    filterResources();
});

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

// Функция для поиска домашних заданий
function searchHomework() {
    var modal = new bootstrap.Modal(document.getElementById('homeworkSearchModal'));
    modal.show();
    loadHomeworkResults();
}

function loadHomeworkResults() {
    const searchText = document.getElementById('homeworkSearchText')?.value || '';
    const tagId = document.getElementById('homeworkTagFilter')?.value || '';
    const date = document.getElementById('homeworkDateFilter')?.value || '';
    
    // Здесь должен быть AJAX запрос для поиска ДЗ
    // Для примера показываем заглушку
    document.getElementById('homeworkResults').innerHTML = '<div class="alert alert-info">Функция поиска в разработке</div>';
}

// Обработка выбора ресурса в модальном окне
document.querySelectorAll('.resource-checkbox')?.forEach(cb => {
    cb.addEventListener('change', function() {
        const id = this.value;
        const commentDiv = document.getElementById('resource_comment_' + id);
        if (commentDiv) {
            commentDiv.style.display = this.checked ? 'block' : 'none';
        }
    });
});

// Округление минут до 00 при вводе времени
document.getElementById('start_time')?.addEventListener('change', function() {
    const time = this.value;
    if (time) {
        const [hours, minutes] = time.split(':');
        this.value = hours + ':00';
    }
});

// Показываем модальное окно если есть редактируемое занятие
<?php if ($editLesson): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Загружаем выбранные темы
    <?php if (!empty($editLesson['selected_topics'])): ?>
        <?php foreach ($editLesson['selected_topics'] as $topicId): ?>
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
    <?php endif; ?>
    
    // Загружаем выбранные ресурсы
    <?php if (!empty($editLesson['selected_resources'])): ?>
        <?php foreach ($editLesson['selected_resources'] as $resourceId => $comment): ?>
            selectedResources.set(<?php echo $resourceId; ?>, '<?php echo addslashes($comment); ?>');
        <?php endforeach; ?>
    <?php endif; ?>
    
    updateSelectedTopics();
    updateSelectedResources();
    
    var lessonModal = new bootstrap.Modal(document.getElementById('lessonModal'));
    lessonModal.show();
});
<?php endif; ?>

// Клик по карточке занятия
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.lesson-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown') && !e.target.closest('button')) {
                const id = this.closest('[id^="lesson_"]').id.replace('lesson_', '');
                editLesson(id);
            }
        });
    });
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