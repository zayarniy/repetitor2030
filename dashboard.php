<?php
// dashboard.php
require_once 'config.php';
requireAuth();

$pageTitle = 'Дашборд';
$currentUser = getCurrentUser($pdo);

// Получение статистики
$stats = [];

// Количество активных учеников
$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE tutor_id = ? AND is_active = 1");
$stmt->execute([$currentUser['id']]);
$stats['active_students'] = $stmt->fetchColumn();

// Запланированные занятия на неделю
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    WHERE d.tutor_id = ? 
    AND l.lesson_date BETWEEN ? AND ?
    AND l.is_conducted = 0
");
$stmt->execute([$currentUser['id'], $weekStart, $weekEnd]);
$stats['planned_lessons'] = $stmt->fetchColumn();

// Количество часов в неделю
$stmt = $pdo->prepare("
    SELECT SUM(l.duration)/60 FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    WHERE d.tutor_id = ? 
    AND l.lesson_date BETWEEN ? AND ?
");
$stmt->execute([$currentUser['id'], $weekStart, $weekEnd]);
$stats['weekly_hours'] = round($stmt->fetchColumn() ?? 0, 1);

// Количество уроков сегодня
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    WHERE d.tutor_id = ? 
    AND l.lesson_date = ?
");
$stmt->execute([$currentUser['id'], $today]);
$stats['today_lessons'] = $stmt->fetchColumn();

// Возможный доход за неделю
$stmt = $pdo->prepare("
    SELECT SUM(l.cost) FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    WHERE d.tutor_id = ? 
    AND l.lesson_date BETWEEN ? AND ?
");
$stmt->execute([$currentUser['id'], $weekStart, $weekEnd]);
$stats['potential_income'] = $stmt->fetchColumn() ?? 0;

// Полученный доход за неделю
$stmt = $pdo->prepare("
    SELECT SUM(l.cost) FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    WHERE d.tutor_id = ? 
    AND l.lesson_date BETWEEN ? AND ?
    AND l.is_paid = 1
");
$stmt->execute([$currentUser['id'], $weekStart, $weekEnd]);
$stats['received_income'] = $stmt->fetchColumn() ?? 0;

// Расписание на неделю
$stmt = $pdo->prepare("
    SELECT 
        l.*,
        d.name as diary_name,
        d.student_id,
        s.first_name as student_first_name,
        s.last_name as student_last_name,
        GROUP_CONCAT(t.name) as topics
    FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    JOIN students s ON d.student_id = s.id
    LEFT JOIN lesson_topics lt ON l.id = lt.lesson_id
    LEFT JOIN topics t ON lt.topic_id = t.id
    WHERE d.tutor_id = ? 
    AND l.lesson_date BETWEEN ? AND ?
    GROUP BY l.id
    ORDER BY l.lesson_date, l.start_time
");
$stmt->execute([$currentUser['id'], $weekStart, $weekEnd]);
$schedule = $stmt->fetchAll();

// Список учеников для фильтра
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM students WHERE tutor_id = ? AND is_active = 1 ORDER BY last_name, first_name");
$stmt->execute([$currentUser['id']]);
$students = $stmt->fetchAll();

// Список меток для фильтра
$stmt = $pdo->prepare("SELECT * FROM tags ORDER BY name");
$stmt->execute();
$tags = $stmt->fetchAll();

include 'header.php';
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="module-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Активные ученики</h6>
                    <h3 class="mb-0"><?php echo $stats['active_students']; ?></h3>
                </div>
                <div class="module-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="bi bi-people"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="module-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Занятий на неделю</h6>
                    <h3 class="mb-0"><?php echo $stats['planned_lessons']; ?></h3>
                </div>
                <div class="module-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="bi bi-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="module-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Часов в неделю</h6>
                    <h3 class="mb-0"><?php echo $stats['weekly_hours']; ?></h3>
                </div>
                <div class="module-icon" style="background: linear-gradient(135deg, #5ea9dd 0%, #2563eb 100%);">
                    <i class="bi bi-clock"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="module-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Занятий сегодня</h6>
                    <h3 class="mb-0"><?php echo $stats['today_lessons']; ?></h3>
                </div>
                <div class="module-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="bi bi-calendar-day"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="module-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Потенциальный доход (неделя)</h5>
                <h4 class="text-success"><?php echo number_format($stats['potential_income'], 0, ',', ' '); ?> ₽</h4>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="module-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Получено (неделя)</h5>
                <h4 class="text-primary"><?php echo number_format($stats['received_income'], 0, ',', ' '); ?> ₽</h4>
            </div>
        </div>
    </div>
</div>

<!-- Фильтры -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="module-card">
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">Выберите ученика</label>
                    <select class="form-select" id="studentFilter">
                        <option value="">Все ученики</option>
                        <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>">
                            <?php echo $student['last_name'] . ' ' . $student['first_name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Фильтр по меткам</label>
                    <select class="form-select" id="tagFilterMode">
                        <option value="OR">ИЛИ</option>
                        <option value="AND">И</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Метки</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($tags as $tag): ?>
                        <div class="form-check">
                            <input class="form-check-input tag-checkbox" type="checkbox" 
                                   value="<?php echo $tag['id']; ?>" id="tag_<?php echo $tag['id']; ?>">
                            <label class="form-check-label" for="tag_<?php echo $tag['id']; ?>">
                                <?php echo $tag['name']; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Расписание -->
<div class="row">
    <div class="col-md-12">
        <div class="module-card">
            <h5 class="mb-3">Расписание на неделю</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Время</th>
                            <th>Ученик</th>
                            <th>Дневник</th>
                            <th>Длительность</th>
                            <th>Статус</th>
                            <th>Оплата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($schedule)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Нет занятий на эту неделю</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($schedule as $lesson): ?>
                            <tr class="lesson-row <?php echo $lesson['is_conducted'] ? 'table-success' : ''; ?>"
                                data-lesson-id="<?php echo $lesson['id']; ?>"
                                data-student-id="<?php echo $lesson['student_id']; ?>"
                                data-bs-toggle="tooltip"
                                title="Темы: <?php echo $lesson['topics'] ?? 'не указаны'; ?> 
                                       Комментарий: <?php echo $lesson['comment'] ?? 'нет'; ?>">
                                <td><?php echo date('d.m.Y', strtotime($lesson['lesson_date'])); ?></td>
                                <td><?php echo substr($lesson['start_time'], 0, 5); ?></td>
                                <td><?php echo $lesson['student_last_name'] . ' ' . $lesson['student_first_name']; ?></td>
                                <td><?php echo $lesson['diary_name']; ?></td>
                                <td><?php echo $lesson['duration']; ?> мин</td>
                                <td>
                                    <?php if ($lesson['is_conducted']): ?>
                                        <span class="badge bg-success">Проведено</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Запланировано</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($lesson['is_paid']): ?>
                                        <span class="badge bg-success">Оплачено</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Не оплачено</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Быстрый доступ к модулям -->
<div class="row mt-4">
    <div class="col-md-12">
        <h5 class="mb-3">Модули</h5>
    </div>
    <div class="col-md-3">
        <a href="students.php" class="text-decoration-none">
            <div class="module-card">
                <div class="module-icon">
                    <i class="bi bi-people"></i>
                </div>
                <h6>Ученики</h6>
                <small class="text-muted">Управление учениками и родителями</small>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="categories.php" class="text-decoration-none">
            <div class="module-card">
                <div class="module-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="bi bi-tags"></i>
                </div>
                <h6>Категории</h6>
                <small class="text-muted">Управление категориями</small>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="topics.php" class="text-decoration-none">
            <div class="module-card">
                <div class="module-icon" style="background: linear-gradient(135deg, #5ea9dd 0%, #2563eb 100%);">
                    <i class="bi bi-book"></i>
                </div>
                <h6>Темы</h6>
                <small class="text-muted">Банк тем занятий</small>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="tags.php" class="text-decoration-none">
            <div class="module-card">
                <div class="module-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="bi bi-bookmark"></i>
                </div>
                <h6>Метки</h6>
                <small class="text-muted">Управление метками</small>
            </div>
        </a>
    </div>
    <div class="col-md-3 mt-3">
        <a href="resources.php" class="text-decoration-none">
            <div class="module-card">
                <div class="module-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <i class="bi bi-files"></i>
                </div>
                <h6>Ресурсы</h6>
                <small class="text-muted">Банк учебных ресурсов</small>
            </div>
        </a>
    </div>
    <div class="col-md-3 mt-3">
        <a href="diaries.php" class="text-decoration-none">
            <div class="module-card">
                <div class="module-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <i class="bi bi-journals"></i>
                </div>
                <h6>Дневники</h6>
                <small class="text-muted">Все дневники учеников</small>
            </div>
        </a>
    </div>
    <div class="col-md-3 mt-3">
        <a href="statistics.php" class="text-decoration-none">
            <div class="module-card">
                <div class="module-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="bi bi-graph-up"></i>
                </div>
                <h6>Статистика</h6>
                <small class="text-muted">Отчеты и аналитика</small>
            </div>
        </a>
    </div>
    <?php if (isAdmin()): ?>
    <div class="col-md-3 mt-3">
        <a href="admin.php" class="text-decoration-none">
            <div class="module-card">
                <div class="module-icon" style="background: linear-gradient(135deg, #e65c5c 0%, #ab2c2c 100%);">
                    <i class="bi bi-gear"></i>
                </div>
                <h6>Администрирование</h6>
                <small class="text-muted">Управление пользователями</small>
            </div>
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Инициализация подсказок
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Клик по занятию для перехода к редактированию
    document.querySelectorAll('.lesson-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('td')) return;
            const lessonId = this.dataset.lessonId;
            window.location.href = 'edit_lesson.php?id=' + lessonId;
        });
    });
    
    // Фильтр по ученику
    document.getElementById('studentFilter').addEventListener('change', function() {
        filterLessons();
    });
    
    // Фильтр по меткам
    document.querySelectorAll('.tag-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', filterLessons);
    });
    
    function filterLessons() {
        const studentId = document.getElementById('studentFilter').value;
        const tagMode = document.getElementById('tagFilterMode').value;
        const selectedTags = Array.from(document.querySelectorAll('.tag-checkbox:checked')).map(cb => cb.value);
        
        document.querySelectorAll('.lesson-row').forEach(row => {
            let show = true;
            
            if (studentId && row.dataset.studentId != studentId) {
                show = false;
            }
            
            // Здесь должна быть логика фильтрации по меткам
            // В реальном приложении нужно получать метки занятия из БД
            
            row.style.display = show ? '' : 'none';
        });
    }
});
</script>

<?php include 'footer.php'; ?>