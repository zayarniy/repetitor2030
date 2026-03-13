<?php
// statistics.php
require_once 'config.php';
requireAuth();

$pageTitle = 'Статистика';
$currentUser = getCurrentUser($pdo);

$message = '';
$error = '';

// Получение списка учеников для фильтра
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, class 
    FROM students 
    WHERE tutor_id = ? 
    ORDER BY last_name, first_name
");
$stmt->execute([$currentUser['id']]);
$students = $stmt->fetchAll();

// Параметры фильтрации
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$period = $_GET['period'] ?? 'month';
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

// Определение дат в зависимости от периода
switch ($period) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        $periodTitle = 'Текущая неделя';
        break;
    case 'month':
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        $periodTitle = date('F Y', strtotime($startDate));
        break;
    case 'year':
        $startDate = "$year-01-01";
        $endDate = "$year-12-31";
        $periodTitle = "$year год";
        break;
    case 'all':
        $startDate = '2000-01-01';
        $endDate = date('Y-m-d');
        $periodTitle = 'За все время';
        break;
    default:
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        $periodTitle = date('F Y', strtotime($startDate));
}

// Общая статистика
if ($studentId > 0) {
    // Статистика по конкретному ученику
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            COUNT(DISTINCT l.id) as total_lessons,
            SUM(CASE WHEN l.is_conducted = 1 THEN 1 ELSE 0 END) as conducted_lessons,
            SUM(CASE WHEN l.is_paid = 1 THEN l.cost ELSE 0 END) as total_paid,
            SUM(l.cost) as total_cost,
            AVG(l.grade_lesson) as avg_grade,
            AVG(l.grade_homework) as avg_homework_grade,
            MIN(l.lesson_date) as first_lesson,
            MAX(l.lesson_date) as last_lesson,
            COUNT(DISTINCT lt.topic_id) as unique_topics
        FROM students s
        LEFT JOIN diaries d ON d.student_id = s.id
        LEFT JOIN lessons l ON l.diary_id = d.id AND l.lesson_date BETWEEN ? AND ?
        LEFT JOIN lesson_topics lt ON lt.lesson_id = l.id
        WHERE s.id = ? AND s.tutor_id = ?
        GROUP BY s.id
    ");
    $stmt->execute([$startDate, $endDate, $studentId, $currentUser['id']]);
    $studentStats = $stmt->fetch();
} else {
    // Статистика по всем ученикам
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT s.id) as total_students,
            COUNT(DISTINCT d.id) as total_diaries,
            COUNT(DISTINCT l.id) as total_lessons,
            SUM(CASE WHEN l.is_conducted = 1 THEN 1 ELSE 0 END) as conducted_lessons,
            SUM(CASE WHEN l.is_paid = 1 THEN l.cost ELSE 0 END) as total_paid,
            SUM(l.cost) as total_cost,
            AVG(l.grade_lesson) as avg_grade,
            AVG(l.grade_homework) as avg_homework_grade,
            COUNT(DISTINCT DATE(l.lesson_date)) as days_with_lessons
        FROM students s
        LEFT JOIN diaries d ON d.student_id = s.id
        LEFT JOIN lessons l ON l.diary_id = d.id AND l.lesson_date BETWEEN ? AND ?
        WHERE s.tutor_id = ?
    ");
    $stmt->execute([$startDate, $endDate, $currentUser['id']]);
    $overallStats = $stmt->fetch();
}

// Динамика по месяцам
$monthlyStats = [];
if ($period == 'year' || $period == 'all') {
    for ($m = 1; $m <= 12; $m++) {
        $monthStart = "$year-" . sprintf("%02d", $m) . "-01";
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        
        if ($studentId > 0) {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as lessons_count,
                    SUM(CASE WHEN is_conducted = 1 THEN 1 ELSE 0 END) as conducted_count,
                    SUM(cost) as total_cost,
                    SUM(CASE WHEN is_paid = 1 THEN cost ELSE 0 END) as paid_cost
                FROM lessons l
                JOIN diaries d ON l.diary_id = d.id
                WHERE d.student_id = ? AND l.lesson_date BETWEEN ? AND ?
            ");
            $stmt->execute([$studentId, $monthStart, $monthEnd]);
        } else {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as lessons_count,
                    SUM(CASE WHEN is_conducted = 1 THEN 1 ELSE 0 END) as conducted_count,
                    SUM(cost) as total_cost,
                    SUM(CASE WHEN is_paid = 1 THEN cost ELSE 0 END) as paid_cost
                FROM lessons l
                JOIN diaries d ON l.diary_id = d.id
                WHERE d.tutor_id = ? AND l.lesson_date BETWEEN ? AND ?
            ");
            $stmt->execute([$currentUser['id'], $monthStart, $monthEnd]);
        }
        
        $monthlyStats[$m] = $stmt->fetch();
    }
}

// Топ тем
if ($studentId > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.name,
            cat.name as category_name,
            COUNT(*) as usage_count
        FROM topics t
        JOIN lesson_topics lt ON lt.topic_id = t.id
        JOIN lessons l ON lt.lesson_id = l.id
        JOIN diaries d ON l.diary_id = d.id
        LEFT JOIN categories cat ON t.category_id = cat.id
        WHERE d.student_id = ? AND l.lesson_date BETWEEN ? AND ?
        GROUP BY t.id
        ORDER BY usage_count DESC
        LIMIT 10
    ");
    $stmt->execute([$studentId, $startDate, $endDate]);
} else {
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.name,
            cat.name as category_name,
            COUNT(*) as usage_count
        FROM topics t
        JOIN lesson_topics lt ON lt.topic_id = t.id
        JOIN lessons l ON lt.lesson_id = l.id
        JOIN diaries d ON l.diary_id = d.id
        LEFT JOIN categories cat ON t.category_id = cat.id
        WHERE d.tutor_id = ? AND l.lesson_date BETWEEN ? AND ?
        GROUP BY t.id
        ORDER BY usage_count DESC
        LIMIT 10
    ");
    $stmt->execute([$currentUser['id'], $startDate, $endDate]);
}
$topTopics = $stmt->fetchAll();

// Статистика по дням недели
$stmt = $pdo->prepare("
    SELECT 
        DAYOFWEEK(l.lesson_date) as day_of_week,
        COUNT(*) as lessons_count,
        AVG(l.grade_lesson) as avg_grade
    FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    WHERE d.tutor_id = ? AND l.lesson_date BETWEEN ? AND ?
    GROUP BY DAYOFWEEK(l.lesson_date)
    ORDER BY day_of_week
");
$stmt->execute([$currentUser['id'], $startDate, $endDate]);
$weekdayStats = $stmt->fetchAll();

$weekdays = [
    1 => 'Воскресенье',
    2 => 'Понедельник',
    3 => 'Вторник',
    4 => 'Среда',
    5 => 'Четверг',
    6 => 'Пятница',
    7 => 'Суббота'
];

// Распределение по классам
$stmt = $pdo->prepare("
    SELECT 
        s.class,
        COUNT(DISTINCT s.id) as students_count,
        COUNT(l.id) as lessons_count,
        SUM(l.cost) as total_cost
    FROM students s
    LEFT JOIN diaries d ON d.student_id = s.id
    LEFT JOIN lessons l ON l.diary_id = d.id AND l.lesson_date BETWEEN ? AND ?
    WHERE s.tutor_id = ? AND s.class != '' AND s.class IS NOT NULL
    GROUP BY s.class
    ORDER BY s.class
");
$stmt->execute([$startDate, $endDate, $currentUser['id']]);
$classStats = $stmt->fetchAll();

include 'header.php';
?>

<div class="container-fluid px-4">
    <!-- Заголовок -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="bi bi-graph-up"></i> Статистика
            <?php if ($studentId > 0 && $studentStats): ?>
                <small class="text-muted fs-6">по ученику: <?php echo htmlspecialchars($studentStats['last_name'] . ' ' . $studentStats['first_name']); ?></small>
            <?php endif; ?>
        </h1>
        <div>
            <a href="?period=week<?php echo $studentId ? '&student_id=' . $studentId : ''; ?>" class="btn btn-outline-primary <?php echo $period == 'week' ? 'active' : ''; ?>">
                Неделя
            </a>
            <a href="?period=month&year=<?php echo $year; ?>&month=<?php echo $month; ?><?php echo $studentId ? '&student_id=' . $studentId : ''; ?>" class="btn btn-outline-primary <?php echo $period == 'month' ? 'active' : ''; ?>">
                Месяц
            </a>
            <a href="?period=year&year=<?php echo $year; ?><?php echo $studentId ? '&student_id=' . $studentId : ''; ?>" class="btn btn-outline-primary <?php echo $period == 'year' ? 'active' : ''; ?>">
                Год
            </a>
            <a href="?period=all<?php echo $studentId ? '&student_id=' . $studentId : ''; ?>" class="btn btn-outline-primary <?php echo $period == 'all' ? 'active' : ''; ?>">
                Все время
            </a>
        </div>
    </div>
    
    <!-- Фильтры -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Ученик</label>
                    <select class="form-select" name="student_id">
                        <option value="">Все ученики</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>" <?php echo $studentId == $student['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?>
                                <?php if ($student['class']): ?>(<?php echo htmlspecialchars($student['class']); ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Год</label>
                    <select class="form-select" name="year">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Месяц</label>
                    <select class="form-select" name="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Период</label>
                    <select class="form-select" name="period">
                        <option value="week" <?php echo $period == 'week' ? 'selected' : ''; ?>>Неделя</option>
                        <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>Месяц</option>
                        <option value="year" <?php echo $period == 'year' ? 'selected' : ''; ?>>Год</option>
                        <option value="all" <?php echo $period == 'all' ? 'selected' : ''; ?>>Все время</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Применить
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Заголовок периода -->
    <div class="alert alert-info">
        <i class="bi bi-calendar"></i> Период: <strong><?php echo $periodTitle; ?></strong>
        <?php if ($period == 'month'): ?>
            (<?php echo date('d.m.Y', strtotime($startDate)); ?> - <?php echo date('d.m.Y', strtotime($endDate)); ?>)
        <?php endif; ?>
    </div>
    
    <?php if ($studentId > 0 && $studentStats): ?>
        <!-- Детальная статистика по ученику -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="card-title">Всего занятий</h6>
                        <h3><?php echo $studentStats['total_lessons'] ?? 0; ?></h3>
                        <small>за период</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6 class="card-title">Проведено</h6>
                        <h3><?php echo $studentStats['conducted_lessons'] ?? 0; ?></h3>
                        <small><?php echo $studentStats['total_lessons'] > 0 ? round(($studentStats['conducted_lessons'] / $studentStats['total_lessons']) * 100, 1) : 0; ?>%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6 class="card-title">Средняя оценка</h6>
                        <h3><?php echo $studentStats['avg_grade'] ? number_format($studentStats['avg_grade'], 1) : '—'; ?></h3>
                        <small>за занятие</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h6 class="card-title">Уникальных тем</h6>
                        <h3><?php echo $studentStats['unique_topics'] ?? 0; ?></h3>
                        <small>изучено</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Финансы</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h5><?php echo number_format($studentStats['total_cost'] ?? 0, 0, ',', ' '); ?> ₽</h5>
                                <small class="text-muted">Начислено</small>
                            </div>
                            <div class="col-6">
                                <h5 class="text-success"><?php echo number_format($studentStats['total_paid'] ?? 0, 0, ',', ' '); ?> ₽</h5>
                                <small class="text-muted">Оплачено</small>
                            </div>
                        </div>
                        <?php if ($studentStats['total_cost'] > 0): ?>
                            <div class="progress mt-2" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: <?php echo ($studentStats['total_paid'] / $studentStats['total_cost']) * 100; ?>%">
                                    <?php echo round(($studentStats['total_paid'] / $studentStats['total_cost']) * 100, 1); ?>%
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Период занятий</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Первое занятие:</small>
                                <h6><?php echo $studentStats['first_lesson'] ? date('d.m.Y', strtotime($studentStats['first_lesson'])) : '—'; ?></h6>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Последнее занятие:</small>
                                <h6><?php echo $studentStats['last_lesson'] ? date('d.m.Y', strtotime($studentStats['last_lesson'])) : '—'; ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Общая статистика -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="card-title">Учеников</h6>
                        <h3><?php echo $overallStats['total_students'] ?? 0; ?></h3>
                        <small>активных</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6 class="card-title">Занятий</h6>
                        <h3><?php echo $overallStats['total_lessons'] ?? 0; ?></h3>
                        <small>проведено: <?php echo $overallStats['conducted_lessons'] ?? 0; ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6 class="card-title">Доход</h6>
                        <h3><?php echo number_format($overallStats['total_paid'] ?? 0, 0, ',', ' '); ?> ₽</h3>
                        <small>получено</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h6 class="card-title">Ср. оценка</h6>
                        <h3><?php echo $overallStats['avg_grade'] ? number_format($overallStats['avg_grade'], 1) : '—'; ?></h3>
                        <small>по занятиям</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Финансовые показатели</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h5><?php echo number_format($overallStats['total_cost'] ?? 0, 0, ',', ' '); ?> ₽</h5>
                                <small class="text-muted">Начислено всего</small>
                            </div>
                            <div class="col-6">
                                <h5 class="text-success"><?php echo number_format($overallStats['total_paid'] ?? 0, 0, ',', ' '); ?> ₽</h5>
                                <small class="text-muted">Оплачено</small>
                            </div>
                        </div>
                        <?php if ($overallStats['total_cost'] > 0): ?>
                            <div class="progress mt-2" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: <?php echo ($overallStats['total_paid'] / $overallStats['total_cost']) * 100; ?>%">
                                    <?php echo round(($overallStats['total_paid'] / $overallStats['total_cost']) * 100, 1); ?>%
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="mt-3">
                            <small class="text-muted">Дней с занятиями: <?php echo $overallStats['days_with_lessons'] ?? 0; ?></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Распределение по дням недели</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="weekdayChart" style="max-height: 200px;"></canvas>
                        <div class="mt-3">
                            <?php foreach ($weekdayStats as $day): ?>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span><?php echo $weekdays[$day['day_of_week']]; ?></span>
                                    <span>
                                        <strong><?php echo $day['lessons_count']; ?></strong> зан.
                                        <?php if ($day['avg_grade']): ?>
                                            <small class="text-muted">(ср. <?php echo number_format($day['avg_grade'], 1); ?>)</small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Динамика по месяцам -->
    <?php if ($period == 'year' || $period == 'all'): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Динамика по месяцам <?php echo $year; ?> года</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyChart" style="max-height: 300px;"></canvas>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Месяц</th>
                                        <th>Занятий</th>
                                        <th>Проведено</th>
                                        <th>Начислено</th>
                                        <th>Оплачено</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthlyStats as $monthNum => $stats): ?>
                                        <?php if ($stats && $stats['lessons_count'] > 0): ?>
                                            <tr>
                                                <td><?php echo date('F', mktime(0, 0, 0, $monthNum, 1)); ?></td>
                                                <td><?php echo $stats['lessons_count']; ?></td>
                                                <td><?php echo $stats['conducted_count']; ?></td>
                                                <td><?php echo number_format($stats['total_cost'] ?? 0, 0, ',', ' '); ?> ₽</td>
                                                <td class="text-success"><?php echo number_format($stats['paid_cost'] ?? 0, 0, ',', ' '); ?> ₽</td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Топ тем -->
    <?php if (!empty($topTopics)): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Популярные темы</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="topicsChart" style="max-height: 250px;"></canvas>
                        <div class="mt-3">
                            <?php foreach ($topTopics as $topic): ?>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>
                                        <?php echo htmlspecialchars($topic['name']); ?>
                                        <?php if ($topic['category_name']): ?>
                                            <small class="text-muted">(<?php echo htmlspecialchars($topic['category_name']); ?>)</small>
                                        <?php endif; ?>
                                    </span>
                                    <span class="badge bg-primary"><?php echo $topic['usage_count']; ?> раз</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Распределение по классам -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Распределение по классам</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="classesChart" style="max-height: 250px;"></canvas>
                        <div class="mt-3">
                            <?php foreach ($classStats as $class): ?>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span><?php echo $class['class'] ?: 'Без класса'; ?></span>
                                    <span>
                                        <strong><?php echo $class['students_count']; ?></strong> уч.
                                        (<?php echo $class['lessons_count']; ?> зан.)
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Список учеников с показателями -->
    <?php if (!$studentId): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Ученики</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ученик</th>
                                        <th>Класс</th>
                                        <th>Занятий</th>
                                        <th>Проведено</th>
                                        <th>Ср. оценка</th>
                                        <th>Начислено</th>
                                        <th>Оплачено</th>
                                        <th>Долг</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <?php
                                        $stmt = $pdo->prepare("
                                            SELECT 
                                                COUNT(l.id) as total,
                                                SUM(CASE WHEN l.is_conducted = 1 THEN 1 ELSE 0 END) as conducted,
                                                AVG(l.grade_lesson) as avg_grade,
                                                SUM(l.cost) as total_cost,
                                                SUM(CASE WHEN l.is_paid = 1 THEN l.cost ELSE 0 END) as paid
                                            FROM lessons l
                                            JOIN diaries d ON l.diary_id = d.id
                                            WHERE d.student_id = ? AND l.lesson_date BETWEEN ? AND ?
                                        ");
                                        $stmt->execute([$student['id'], $startDate, $endDate]);
                                        $studentPeriodStats = $stmt->fetch();
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="?student_id=<?php echo $student['id']; ?>&period=<?php echo $period; ?>&year=<?php echo $year; ?>&month=<?php echo $month; ?>">
                                                    <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $student['class'] ?: '—'; ?></td>
                                            <td><?php echo $studentPeriodStats['total'] ?? 0; ?></td>
                                            <td><?php echo $studentPeriodStats['conducted'] ?? 0; ?></td>
                                            <td><?php echo $studentPeriodStats['avg_grade'] ? number_format($studentPeriodStats['avg_grade'], 1) : '—'; ?></td>
                                            <td><?php echo number_format($studentPeriodStats['total_cost'] ?? 0, 0, ',', ' '); ?> ₽</td>
                                            <td class="text-success"><?php echo number_format($studentPeriodStats['paid'] ?? 0, 0, ',', ' '); ?> ₽</td>
                                            <td class="text-danger">
                                                <?php 
                                                $debt = ($studentPeriodStats['total_cost'] ?? 0) - ($studentPeriodStats['paid'] ?? 0);
                                                echo $debt > 0 ? number_format($debt, 0, ',', ' ') . ' ₽' : '—';
                                                ?>
                                            </td>
                                            <td>
                                                <a href="statistics.php?student_id=<?php echo $student['id']; ?>&period=<?php echo $period; ?>&year=<?php echo $year; ?>&month=<?php echo $month; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-graph-up"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Скрипты для графиков -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // График по дням недели
    const weekdayCtx = document.getElementById('weekdayChart')?.getContext('2d');
    if (weekdayCtx) {
        const weekdays = <?php echo json_encode(array_values($weekdays)); ?>;
        const weekdayData = <?php 
            $data = array_fill(1, 7, 0);
            foreach ($weekdayStats as $day) {
                $data[$day['day_of_week']] = $day['lessons_count'];
            }
            echo json_encode(array_values($data));
        ?>;
        
        new Chart(weekdayCtx, {
            type: 'bar',
            data: {
                labels: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
                datasets: [{
                    label: 'Количество занятий',
                    data: weekdayData,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
    
    // Месячный график
    const monthlyCtx = document.getElementById('monthlyChart')?.getContext('2d');
    if (monthlyCtx) {
        const months = <?php 
            $monthNames = [];
            for ($m = 1; $m <= 12; $m++) {
                $monthNames[] = date('M', mktime(0, 0, 0, $m, 1));
            }
            echo json_encode($monthNames);
        ?>;
        const lessonsData = <?php 
            $data = [];
            foreach ($monthlyStats as $stats) {
                $data[] = $stats ? $stats['lessons_count'] : 0;
            }
            echo json_encode($data);
        ?>;
        const paidData = <?php 
            $data = [];
            foreach ($monthlyStats as $stats) {
                $data[] = $stats ? ($stats['paid_cost'] ?? 0) : 0;
            }
            echo json_encode($data);
        ?>;
        
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Занятий',
                        data: lessonsData,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        tension: 0.1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Оплата (тыс. ₽)',
                        data: paidData.map(v => v / 1000),
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        ticks: {
                            stepSize: 1
                        },
                        title: {
                            display: true,
                            text: 'Количество занятий'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        },
                        title: {
                            display: true,
                            text: 'Тыс. рублей'
                        }
                    }
                }
            }
        });
    }
    
    // График популярных тем
    const topicsCtx = document.getElementById('topicsChart')?.getContext('2d');
    if (topicsCtx) {
        const topicNames = <?php echo json_encode(array_column($topTopics, 'name')); ?>;
        const topicCounts = <?php echo json_encode(array_column($topTopics, 'usage_count')); ?>;
        
        new Chart(topicsCtx, {
            type: 'doughnut',
            data: {
                labels: topicNames,
                datasets: [{
                    data: topicCounts,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(199, 199, 199, 0.7)',
                        'rgba(83, 102, 255, 0.7)',
                        'rgba(255, 99, 255, 0.7)',
                        'rgba(54, 162, 132, 0.7)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // График классов
    const classesCtx = document.getElementById('classesChart')?.getContext('2d');
    if (classesCtx) {
        const classNames = <?php echo json_encode(array_column($classStats, 'class')); ?>;
        const studentCounts = <?php echo json_encode(array_column($classStats, 'students_count')); ?>;
        
        new Chart(classesCtx, {
            type: 'pie',
            data: {
                labels: classNames,
                datasets: [{
                    data: studentCounts,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
});
</script>

<style>
.card {
    margin-bottom: 1rem;
}
.progress {
    border-radius: 10px;
}
.table td {
    vertical-align: middle;
}
</style>

<?php include 'footer.php'; ?>