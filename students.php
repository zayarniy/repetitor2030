<?php
// students.php (обновленная версия с функцией редактирования представителя)
require_once 'config.php';
requireAuth();

$pageTitle = 'Ученики';
$currentUser = getCurrentUser($pdo);

$message = '';
$error = '';

// Обработка добавления/редактирования ученика
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_student'])) {
        $studentId = $_POST['student_id'] ?? null;
        
        // Основная информация
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $middleName = $_POST['middle_name'] ?? '';
        $birthYear = $_POST['birth_year'] ? (int)$_POST['birth_year'] : null;
        $class = $_POST['class'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $city = $_POST['city'] ?? '';
        $messenger1 = $_POST['messenger1'] ?? '';
        $messenger2 = $_POST['messenger2'] ?? '';
        $messenger3 = $_POST['messenger3'] ?? '';
        
        // Информация о занятиях
        $lessonCost = $_POST['lesson_cost'] ? (float)$_POST['lesson_cost'] : null;
        $lessonDuration = $_POST['lesson_duration'] ? (int)$_POST['lesson_duration'] : null;
        $lessonsPerWeek = $_POST['lessons_per_week'] ? (int)$_POST['lessons_per_week'] : 1;
        $goals = $_POST['goals'] ?? '';
        
        // Дополнительная информация
        $additionalInfo = $_POST['additional_info'] ?? '';
        $startDate = $_POST['start_date'] ?: null;
        $plannedEndDate = $_POST['planned_end_date'] ?: null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Валидация
        if (empty($firstName) || empty($lastName)) {
            $error = 'Имя и фамилия обязательны для заполнения';
        } else {
            if ($studentId) {
                // Обновление существующего ученика
                $stmt = $pdo->prepare("
                    UPDATE students SET 
                        first_name = ?, last_name = ?, middle_name = ?, birth_year = ?, 
                        class = ?, phone = ?, email = ?, city = ?, messenger1 = ?, 
                        messenger2 = ?, messenger3 = ?, lesson_cost = ?, lesson_duration = ?,
                        lessons_per_week = ?, goals = ?, additional_info = ?, start_date = ?,
                        planned_end_date = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ? AND tutor_id = ?
                ");
                
                if ($stmt->execute([
                    $firstName, $lastName, $middleName, $birthYear, $class, $phone, $email,
                    $city, $messenger1, $messenger2, $messenger3, $lessonCost, $lessonDuration,
                    $lessonsPerWeek, $goals, $additionalInfo, $startDate, $plannedEndDate,
                    $isActive, $studentId, $currentUser['id']
                ])) {
                    // Сохраняем в историю
                    $stmt = $pdo->prepare("
                        INSERT INTO student_history (student_id, changed_by, change_type, new_data)
                        VALUES (?, ?, 'update', ?)
                    ");
                    $stmt->execute([$studentId, $currentUser['id'], json_encode($_POST)]);
                    
                    $message = 'Информация об ученике успешно обновлена';
                } else {
                    $error = 'Ошибка при обновлении информации';
                }
            } else {
                // Добавление нового ученика
                $stmt = $pdo->prepare("
                    INSERT INTO students (
                        first_name, last_name, middle_name, birth_year, class, phone, email,
                        city, messenger1, messenger2, messenger3, lesson_cost, lesson_duration,
                        lessons_per_week, goals, additional_info, start_date, planned_end_date,
                        is_active, tutor_id, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([
                    $firstName, $lastName, $middleName, $birthYear, $class, $phone, $email,
                    $city, $messenger1, $messenger2, $messenger3, $lessonCost, $lessonDuration,
                    $lessonsPerWeek, $goals, $additionalInfo, $startDate, $plannedEndDate,
                    $isActive, $currentUser['id'], $currentUser['id']
                ])) {
                    $newId = $pdo->lastInsertId();
                    
                    // Сохраняем в историю
                    $stmt = $pdo->prepare("
                        INSERT INTO student_history (student_id, changed_by, change_type, new_data)
                        VALUES (?, ?, 'create', ?)
                    ");
                    $stmt->execute([$newId, $currentUser['id'], json_encode($_POST)]);
                    
                    $message = 'Ученик успешно добавлен';
                } else {
                    $error = 'Ошибка при добавлении ученика';
                }
            }
        }
    } elseif (isset($_POST['delete_student'])) {
        // Деактивация ученика (мягкое удаление)
        $studentId = $_POST['student_id'] ?? null;
        
        if ($studentId) {
            $stmt = $pdo->prepare("
                UPDATE students SET is_active = 0, updated_at = NOW() 
                WHERE id = ? AND tutor_id = ?
            ");
            if ($stmt->execute([$studentId, $currentUser['id']])) {
                // Сохраняем в историю
                $stmt = $pdo->prepare("
                    INSERT INTO student_history (student_id, changed_by, change_type, new_data)
                    VALUES (?, ?, 'deactivate', ?)
                ");
                $stmt->execute([$studentId, $currentUser['id'], json_encode(['is_active' => 0])]);
                
                $message = 'Ученик деактивирован';
            } else {
                $error = 'Ошибка при деактивации ученика';
            }
        }
    } elseif (isset($_POST['activate_student'])) {
        // Активация ученика
        $studentId = $_POST['student_id'] ?? null;
        
        if ($studentId) {
            $stmt = $pdo->prepare("
                UPDATE students SET is_active = 1, updated_at = NOW() 
                WHERE id = ? AND tutor_id = ?
            ");
            if ($stmt->execute([$studentId, $currentUser['id']])) {
                // Сохраняем в историю
                $stmt = $pdo->prepare("
                    INSERT INTO student_history (student_id, changed_by, change_type, new_data)
                    VALUES (?, ?, 'activate', ?)
                ");
                $stmt->execute([$studentId, $currentUser['id'], json_encode(['is_active' => 1])]);
                
                $message = 'Ученик активирован';
            } else {
                $error = 'Ошибка при активации ученика';
            }
        }
    } elseif (isset($_POST['add_comment'])) {
        // Добавление комментария
        $studentId = $_POST['student_id'] ?? null;
        $comment = $_POST['comment'] ?? '';
        
        if ($studentId && !empty($comment)) {
            $stmt = $pdo->prepare("
                INSERT INTO student_comments (student_id, author_id, comment)
                VALUES (?, ?, ?)
            ");
            if ($stmt->execute([$studentId, $currentUser['id'], $comment])) {
                $message = 'Комментарий добавлен';
            } else {
                $error = 'Ошибка при добавлении комментария';
            }
        }
    } elseif (isset($_POST['delete_comment'])) {
        // Удаление комментария
        $commentId = $_POST['comment_id'] ?? null;
        
        if ($commentId) {
            $stmt = $pdo->prepare("
                UPDATE student_comments SET is_deleted = 1 
                WHERE id = ? AND author_id = ?
            ");
            if ($stmt->execute([$commentId, $currentUser['id']])) {
                $message = 'Комментарий удален';
            } else {
                $error = 'Ошибка при удалении комментария';
            }
        }
    } elseif (isset($_POST['save_representative'])) {
        // Сохранение представителя
        $studentId = $_POST['student_id'] ?? null;
        $repId = $_POST['rep_id'] ?? null;
        
        $relationship = $_POST['relationship'] ?? '';
        $repFirstName = $_POST['rep_first_name'] ?? '';
        $repLastName = $_POST['rep_last_name'] ?? '';
        $repMiddleName = $_POST['rep_middle_name'] ?? '';
        $repPhone = $_POST['rep_phone'] ?? '';
        $repMessenger = $_POST['rep_messenger'] ?? '';
        $repEmail = $_POST['rep_email'] ?? '';
        $isPrimary = isset($_POST['rep_is_primary']) ? 1 : 0;
        
        if ($studentId && !empty($repFirstName) && !empty($repLastName)) {
            // Проверяем, не превышен ли лимит представителей (максимум 3)
            if (!$repId) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM representatives WHERE student_id = ?");
                $stmt->execute([$studentId]);
                $count = $stmt->fetchColumn();
                
                if ($count >= 3) {
                    $error = 'Нельзя добавить более 3 представителей';
                }
            }
            
            if (!$error) {
                // Если устанавливаем этого представителя основным, снимаем флаг с других
                if ($isPrimary) {
                    $stmt = $pdo->prepare("UPDATE representatives SET is_primary = 0 WHERE student_id = ?");
                    $stmt->execute([$studentId]);
                }
                
                if ($repId) {
                    // Обновление
                    $stmt = $pdo->prepare("
                        UPDATE representatives SET
                            relationship = ?, first_name = ?, last_name = ?, middle_name = ?,
                            phone = ?, messenger_contact = ?, email = ?, is_primary = ?
                        WHERE id = ? AND student_id = ?
                    ");
                    $stmt->execute([
                        $relationship, $repFirstName, $repLastName, $repMiddleName,
                        $repPhone, $repMessenger, $repEmail, $isPrimary, $repId, $studentId
                    ]);
                    $message = 'Представитель обновлен';
                } else {
                    // Добавление
                    $stmt = $pdo->prepare("
                        INSERT INTO representatives (
                            student_id, relationship, first_name, last_name, middle_name,
                            phone, messenger_contact, email, is_primary
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $studentId, $relationship, $repFirstName, $repLastName, $repMiddleName,
                        $repPhone, $repMessenger, $repEmail, $isPrimary
                    ]);
                    $message = 'Представитель добавлен';
                }
            }
        } else {
            $error = 'Имя и фамилия представителя обязательны';
        }
    } elseif (isset($_POST['delete_representative'])) {
        // Удаление представителя
        $repId = $_POST['rep_id'] ?? null;
        
        if ($repId) {
            $stmt = $pdo->prepare("DELETE FROM representatives WHERE id = ?");
            $stmt->execute([$repId]);
            $message = 'Представитель удален';
        }
    }
}

// Получение списка учеников
$filterClass = $_GET['class'] ?? '';
$searchName = $_GET['search'] ?? '';
$filterActive = isset($_GET['active']) ? (int)$_GET['active'] : 1;

$query = "
    SELECT s.*, 
           (SELECT MIN(CONCAT(lesson_date, ' ', start_time)) 
            FROM lessons l 
            JOIN diaries d ON l.diary_id = d.id 
            WHERE d.student_id = s.id AND l.lesson_date >= CURDATE() AND l.is_conducted = 0
           ) as next_lesson
    FROM students s
    WHERE s.tutor_id = ?
";

$params = [$currentUser['id']];

if ($filterClass !== '') {
    $query .= " AND s.class = ?";
    $params[] = $filterClass;
}

if ($searchName !== '') {
    $query .= " AND CONCAT(s.last_name, ' ', s.first_name, ' ', COALESCE(s.middle_name, '')) LIKE ?";
    $params[] = "%$searchName%";
}

if ($filterActive !== '') {
    $query .= " AND s.is_active = ?";
    $params[] = $filterActive;
}

$query .= " ORDER BY s.last_name, s.first_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Получение уникальных классов для фильтра
$stmt = $pdo->prepare("SELECT DISTINCT class FROM students WHERE tutor_id = ? AND class != '' ORDER BY class");
$stmt->execute([$currentUser['id']]);
$classes = $stmt->fetchAll();

// Получение данных для редактирования
$editStudent = null;
$representatives = [];
$comments = [];

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND tutor_id = ?");
    $stmt->execute([$_GET['edit'], $currentUser['id']]);
    $editStudent = $stmt->fetch();
    
    if ($editStudent) {
        // Получаем представителей
        $stmt = $pdo->prepare("SELECT * FROM representatives WHERE student_id = ? ORDER BY is_primary DESC, last_name, first_name");
        $stmt->execute([$editStudent['id']]);
        $representatives = $stmt->fetchAll();
        
        // Получаем комментарии
        $stmt = $pdo->prepare("
            SELECT sc.*, u.first_name, u.last_name 
            FROM student_comments sc
            LEFT JOIN users u ON sc.author_id = u.id
            WHERE sc.student_id = ? AND sc.is_deleted = 0
            ORDER BY sc.created_at DESC
        ");
        $stmt->execute([$editStudent['id']]);
        $comments = $stmt->fetchAll();
    }
}

// AJAX обработчик для получения данных представителя
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_representative') {
    $repId = $_GET['id'] ?? 0;
    
    if ($repId) {
        $stmt = $pdo->prepare("SELECT * FROM representatives WHERE id = ?");
        $stmt->execute([$repId]);
        $rep = $stmt->fetch();
        
        if ($rep) {
            header('Content-Type: application/json');
            echo json_encode($rep);
            exit;
        }
    }
    
    header('HTTP/1.0 404 Not Found');
    echo json_encode(['error' => 'Представитель не найден']);
    exit;
}

include 'header.php';
?>

<!-- Сообщения -->
<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Кнопка добавления ученика -->
<div class="mb-4">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal">
        <i class="bi bi-plus-circle"></i> Добавить ученика
    </button>
</div>

<!-- Фильтры и поиск -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Поиск по ФИО</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($searchName); ?>" placeholder="Введите ФИО">
            </div>
            <div class="col-md-3">
                <label class="form-label">Класс</label>
                <select class="form-select" name="class">
                    <option value="">Все классы</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['class']; ?>" <?php echo $filterClass == $class['class'] ? 'selected' : ''; ?>>
                            <?php echo $class['class']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Статус</label>
                <select class="form-select" name="active">
                    <option value="1" <?php echo $filterActive == 1 ? 'selected' : ''; ?>>Активные</option>
                    <option value="0" <?php echo $filterActive === '0' ? 'selected' : ''; ?>>Неактивные</option>
                    <option value="" <?php echo $filterActive === '' ? 'selected' : ''; ?>>Все</option>
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

<!-- Список учеников -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Список учеников</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ФИО</th>
                        <th>Класс</th>
                        <th>Телефон</th>
                        <th>Представители</th>
                        <th>Стоимость</th>
                        <th>Занятий/нед</th>
                        <th>Ближайшее занятие</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">Нет учеников</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <?php
                            // Получаем представителей для этого ученика (для отображения)
                            $stmt = $pdo->prepare("SELECT * FROM representatives WHERE student_id = ? LIMIT 2");
                            $stmt->execute([$student['id']]);
                            $reps = $stmt->fetchAll();
                            $repCount = $pdo->prepare("SELECT COUNT(*) FROM representatives WHERE student_id = ?");
                            $repCount->execute([$student['id']]);
                            $totalReps = $repCount->fetchColumn();
                            ?>
                            <tr class="<?php echo !$student['is_active'] ? 'table-secondary' : ''; ?>">
                                <td>
                                    <?php echo $student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? ''); ?>
                                </td>
                                <td><?php echo $student['class'] ?? '-'; ?></td>
                                <td><?php echo $student['phone'] ?? '-'; ?></td>
                                <td>
                                    <?php if (!empty($reps)): ?>
                                        <?php foreach ($reps as $rep): ?>
                                            <small><?php echo $rep['last_name'] . ' ' . $rep['first_name']; ?><?php echo $rep['is_primary'] ? ' <span class="badge bg-info">осн.</span>' : ''; ?></small><br>
                                        <?php endforeach; ?>
                                        <?php if ($totalReps > 2): ?>
                                            <small class="text-muted">и еще <?php echo $totalReps - 2; ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">нет</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $student['lesson_cost'] ? number_format($student['lesson_cost'], 0, ',', ' ') . ' ₽' : '-'; ?></td>
                                <td><?php echo $student['lessons_per_week'] ?? '1'; ?></td>
                                <td>
                                    <?php if ($student['next_lesson']): ?>
                                        <?php echo date('d.m.Y H:i', strtotime($student['next_lesson'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Нет</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($student['is_active']): ?>
                                        <span class="badge bg-success">Активен</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Неактивен</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="editStudent(<?php echo $student['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($student['is_active']): ?>
                                        <button class="btn btn-sm btn-warning" onclick="deactivateStudent(<?php echo $student['id']; ?>)">
                                            <i class="bi bi-person-x"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-success" onclick="activateStudent(<?php echo $student['id']; ?>)">
                                            <i class="bi bi-person-check"></i>
                                        </button>
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

<!-- Модальное окно для добавления/редактирования ученика -->
<div class="modal fade" id="studentModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentModalTitle">
                    <?php echo $editStudent ? 'Редактирование ученика: ' . $editStudent['last_name'] . ' ' . $editStudent['first_name'] : 'Добавление ученика'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="students.php" id="studentForm">
                <input type="hidden" name="student_id" id="student_id" value="<?php echo $editStudent['id'] ?? ''; ?>">
                <div class="modal-body">
                    <!-- Вкладки -->
                    <ul class="nav nav-tabs" id="studentTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="main-tab" data-bs-toggle="tab" data-bs-target="#main" type="button" role="tab">
                                <i class="bi bi-person"></i> Основная информация
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="lesson-tab" data-bs-toggle="tab" data-bs-target="#lesson" type="button" role="tab">
                                <i class="bi bi-journal-bookmark-fill"></i> Информация о занятиях
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="additional-tab" data-bs-toggle="tab" data-bs-target="#additional" type="button" role="tab">
                                <i class="bi bi-info-circle"></i> Дополнительная информация
                            </button>
                        </li>
                        <?php if ($editStudent): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="representatives-tab" data-bs-toggle="tab" data-bs-target="#representatives" type="button" role="tab">
                                <i class="bi bi-people"></i> Представители <span class="badge bg-secondary"><?php echo count($representatives); ?>/3</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="comments-tab" data-bs-toggle="tab" data-bs-target="#comments" type="button" role="tab">
                                <i class="bi bi-chat"></i> Комментарии <span class="badge bg-secondary"><?php echo count($comments); ?></span>
                            </button>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <!-- Содержимое вкладок -->
                    <div class="tab-content mt-3" id="studentTabsContent">
                        <!-- Вкладка: Основная информация -->
                        <div class="tab-pane fade show active" id="main" role="tabpanel">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Фамилия <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="last_name" 
                                           value="<?php echo $editStudent['last_name'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Имя <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="first_name" 
                                           value="<?php echo $editStudent['first_name'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Отчество</label>
                                    <input type="text" class="form-control" name="middle_name" 
                                           value="<?php echo $editStudent['middle_name'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Год рождения</label>
                                    <input type="number" class="form-control" name="birth_year" 
                                           value="<?php echo $editStudent['birth_year'] ?? ''; ?>" 
                                           min="1900" max="<?php echo date('Y'); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Класс</label>
                                    <input type="text" class="form-control" name="class" 
                                           value="<?php echo $editStudent['class'] ?? ''; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Город</label>
                                    <input type="text" class="form-control" name="city" 
                                           value="<?php echo $editStudent['city'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Телефон</label>
                                    <input type="text" class="form-control" name="phone" 
                                           value="<?php echo $editStudent['phone'] ?? ''; ?>" placeholder="+7 (999) 999-99-99">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo $editStudent['email'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Мессенджер 1</label>
                                    <input type="text" class="form-control" name="messenger1" 
                                           value="<?php echo $editStudent['messenger1'] ?? ''; ?>" placeholder="Telegram: @username">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Мессенджер 2</label>
                                    <input type="text" class="form-control" name="messenger2" 
                                           value="<?php echo $editStudent['messenger2'] ?? ''; ?>" placeholder="WhatsApp: +7...">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Мессенджер 3</label>
                                    <input type="text" class="form-control" name="messenger3" 
                                           value="<?php echo $editStudent['messenger3'] ?? ''; ?>" placeholder="Viber: +7...">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Вкладка: Информация о занятиях -->
                        <div class="tab-pane fade" id="lesson" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Стоимость занятия (₽)</label>
                                    <input type="number" class="form-control" name="lesson_cost" 
                                           value="<?php echo $editStudent['lesson_cost'] ?? ''; ?>" step="100" min="0">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Длительность занятия (минут)</label>
                                    <input type="number" class="form-control" name="lesson_duration" 
                                           value="<?php echo $editStudent['lesson_duration'] ?? '60'; ?>" min="15" step="15">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Количество занятий в неделю</label>
                                <input type="number" class="form-control" name="lessons_per_week" 
                                       value="<?php echo $editStudent['lessons_per_week'] ?? '1'; ?>" min="1" max="7">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Цели обучения</label>
                                <textarea class="form-control" name="goals" rows="3" placeholder="Опишите цели обучения..."><?php echo $editStudent['goals'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_active" 
                                       id="is_active" <?php echo !isset($editStudent) || $editStudent['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    Активный ученик
                                </label>
                            </div>
                        </div>
                        
                        <!-- Вкладка: Дополнительная информация -->
                        <div class="tab-pane fade" id="additional" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Дата начала занятий</label>
                                    <input type="date" class="form-control" name="start_date" 
                                           value="<?php echo $editStudent['start_date'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Планируемая дата окончания</label>
                                    <input type="date" class="form-control" name="planned_end_date" 
                                           value="<?php echo $editStudent['planned_end_date'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Дополнительная информация</label>
                                <textarea class="form-control" name="additional_info" rows="4" placeholder="Любая дополнительная информация..."><?php echo $editStudent['additional_info'] ?? ''; ?></textarea>
                            </div>
                        </div>
                        
                        <?php if ($editStudent): ?>
                        <!-- Вкладка: Представители -->
                        <div class="tab-pane fade" id="representatives" role="tabpanel">
                            <div class="mb-3">
                                <button type="button" class="btn btn-success" onclick="showAddRepresentativeModal(<?php echo $editStudent['id']; ?>)">
                                    <i class="bi bi-plus-circle"></i> Добавить представителя
                                </button>
                                <small class="text-muted ms-2">Максимум 3 представителя</small>
                            </div>
                            
                            <div id="representativesList">
                                <?php if (empty($representatives)): ?>
                                    <div class="alert alert-info">
                                        Нет добавленных представителей. Нажмите "Добавить представителя", чтобы добавить.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($representatives as $rep): ?>
                                    <div class="card mb-3" id="rep_card_<?php echo $rep['id']; ?>">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo $rep['last_name'] . ' ' . $rep['first_name'] . ' ' . ($rep['middle_name'] ?? ''); ?></strong>
                                                <?php if ($rep['relationship']): ?>
                                                    <span class="badge bg-info ms-2"><?php echo $rep['relationship']; ?></span>
                                                <?php endif; ?>
                                                <?php if ($rep['is_primary']): ?>
                                                    <span class="badge bg-warning">Основной представитель</span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-primary" onclick="editRepresentative(<?php echo $rep['id']; ?>, <?php echo $editStudent['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteRepresentative(<?php echo $rep['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <small class="text-muted d-block"><i class="bi bi-telephone"></i> Телефон: <?php echo $rep['phone'] ?? 'не указан'; ?></small>
                                                    <small class="text-muted d-block"><i class="bi bi-envelope"></i> Email: <?php echo $rep['email'] ?? 'не указан'; ?></small>
                                                </div>
                                                <div class="col-md-6">
                                                    <small class="text-muted d-block"><i class="bi bi-chat"></i> Мессенджер: <?php echo $rep['messenger_contact'] ?? 'не указан'; ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Вкладка: Комментарии -->
                        <div class="tab-pane fade" id="comments" role="tabpanel">
                            <div class="mb-3">
                                <textarea class="form-control" id="newComment" rows="3" placeholder="Введите комментарий..."></textarea>
                                <button type="button" class="btn btn-primary mt-2" onclick="addComment(<?php echo $editStudent['id']; ?>)">
                                    <i class="bi bi-chat-dots"></i> Добавить комментарий
                                </button>
                            </div>
                            
                            <div id="commentsList">
                                <?php if (empty($comments)): ?>
                                    <div class="alert alert-info">
                                        Нет комментариев. Добавьте первый комментарий.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($comments as $comment): ?>
                                    <div class="card mb-2" id="comment_<?php echo $comment['id']; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <strong><?php echo $comment['first_name'] . ' ' . $comment['last_name']; ?></strong>
                                                    <small class="text-muted ms-2"><?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?></small>
                                                </div>
                                                <?php if ($comment['author_id'] == $currentUser['id']): ?>
                                                <button class="btn btn-sm btn-danger" onclick="deleteComment(<?php echo $comment['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mt-2 mb-0"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="save_student" class="btn btn-primary">Сохранить ученика</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно для добавления/редактирования представителя -->
<div class="modal fade" id="representativeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="representativeModalTitle">Добавление представителя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="students.php" id="representativeForm">
                <input type="hidden" name="student_id" id="modal_student_id" value="<?php echo $editStudent['id'] ?? ''; ?>">
                <input type="hidden" name="rep_id" id="modal_rep_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Родство</label>
                        <select class="form-select" name="relationship" id="modal_relationship">
                            <option value="">Выберите...</option>
                            <option value="Мать">Мать</option>
                            <option value="Отец">Отец</option>
                            <option value="Бабушка">Бабушка</option>
                            <option value="Дедушка">Дедушка</option>
                            <option value="Опекун">Опекун</option>
                            <option value="Другое">Другое</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Фамилия <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="rep_last_name" id="modal_rep_last_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Имя <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="rep_first_name" id="modal_rep_first_name" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Отчество</label>
                        <input type="text" class="form-control" name="rep_middle_name" id="modal_rep_middle_name">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Телефон</label>
                        <input type="text" class="form-control" name="rep_phone" id="modal_rep_phone" placeholder="+7 (999) 999-99-99">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Мессенджер</label>
                        <input type="text" class="form-control" name="rep_messenger" id="modal_rep_messenger" placeholder="Telegram/WhatsApp/Viber">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="rep_email" id="modal_rep_email">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="rep_is_primary" id="modal_rep_is_primary">
                        <label class="form-check-label" for="modal_rep_is_primary">
                            Основной представитель
                        </label>
                        <small class="text-muted d-block">Основной представитель будет отображаться первым</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="save_representative" class="btn btn-primary">Сохранить представителя</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Формы для действий -->
<form method="POST" id="deactivateForm" style="display:none;">
    <input type="hidden" name="student_id" id="deactivate_id">
    <input type="hidden" name="delete_student">
</form>

<form method="POST" id="activateForm" style="display:none;">
    <input type="hidden" name="student_id" id="activate_id">
    <input type="hidden" name="activate_student">
</form>

<form method="POST" id="commentForm" style="display:none;">
    <input type="hidden" name="student_id" id="comment_student_id">
    <input type="hidden" name="comment" id="comment_text">
    <input type="hidden" name="add_comment">
</form>

<form method="POST" id="deleteCommentForm" style="display:none;">
    <input type="hidden" name="comment_id" id="delete_comment_id">
    <input type="hidden" name="delete_comment">
</form>

<form method="POST" id="deleteRepresentativeForm" style="display:none;">
    <input type="hidden" name="rep_id" id="delete_rep_id">
    <input type="hidden" name="delete_representative">
</form>

<script>
// Функции для работы с учениками
function editStudent(id) {
    window.location.href = 'students.php?edit=' + id;
}

function deactivateStudent(id) {
    if (confirm('Вы уверены, что хотите деактивировать ученика?')) {
        document.getElementById('deactivate_id').value = id;
        document.getElementById('deactivateForm').submit();
    }
}

function activateStudent(id) {
    if (confirm('Вы уверены, что хотите активировать ученика?')) {
        document.getElementById('activate_id').value = id;
        document.getElementById('activateForm').submit();
    }
}

// Функции для комментариев
function addComment(studentId) {
    const comment = document.getElementById('newComment').value.trim();
    if (comment) {
        document.getElementById('comment_student_id').value = studentId;
        document.getElementById('comment_text').value = comment;
        document.getElementById('commentForm').submit();
    } else {
        alert('Введите комментарий');
    }
}

function deleteComment(commentId) {
    if (confirm('Удалить комментарий?')) {
        document.getElementById('delete_comment_id').value = commentId;
        document.getElementById('deleteCommentForm').submit();
    }
}

// Функции для представителей
function showAddRepresentativeModal(studentId) {
    // Очищаем форму
    document.getElementById('modal_rep_id').value = '';
    document.getElementById('modal_relationship').value = '';
    document.getElementById('modal_rep_last_name').value = '';
    document.getElementById('modal_rep_first_name').value = '';
    document.getElementById('modal_rep_middle_name').value = '';
    document.getElementById('modal_rep_phone').value = '';
    document.getElementById('modal_rep_messenger').value = '';
    document.getElementById('modal_rep_email').value = '';
    document.getElementById('modal_rep_is_primary').checked = false;
    
    document.getElementById('modal_student_id').value = studentId;
    document.getElementById('representativeModalTitle').textContent = 'Добавление представителя';
    
    var repModal = new bootstrap.Modal(document.getElementById('representativeModal'));
    repModal.show();
}

function editRepresentative(repId, studentId) {
    // Показываем индикатор загрузки
    document.getElementById('representativeModalTitle').textContent = 'Загрузка...';
    
    // Открываем модальное окно
    var repModal = new bootstrap.Modal(document.getElementById('representativeModal'));
    repModal.show();
    
    // Выполняем AJAX запрос для получения данных представителя
    fetch('students.php?ajax=get_representative&id=' + repId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Представитель не найден');
            }
            return response.json();
        })
        .then(data => {
            // Заполняем форму данными
            document.getElementById('modal_rep_id').value = data.id;
            document.getElementById('modal_relationship').value = data.relationship || '';
            document.getElementById('modal_rep_last_name').value = data.last_name || '';
            document.getElementById('modal_rep_first_name').value = data.first_name || '';
            document.getElementById('modal_rep_middle_name').value = data.middle_name || '';
            document.getElementById('modal_rep_phone').value = data.phone || '';
            document.getElementById('modal_rep_messenger').value = data.messenger_contact || '';
            document.getElementById('modal_rep_email').value = data.email || '';
            document.getElementById('modal_rep_is_primary').checked = data.is_primary == 1;
            
            document.getElementById('modal_student_id').value = studentId;
            document.getElementById('representativeModalTitle').textContent = 'Редактирование представителя';
        })
        .catch(error => {
            alert('Ошибка при загрузке данных представителя: ' + error.message);
            repModal.hide();
        });
}

function deleteRepresentative(repId) {
    if (confirm('Вы уверены, что хотите удалить представителя?')) {
        document.getElementById('delete_rep_id').value = repId;
        document.getElementById('deleteRepresentativeForm').submit();
    }
}

// Показываем модальное окно если есть редактируемый ученик
<?php if ($editStudent): ?>
document.addEventListener('DOMContentLoaded', function() {
    var studentModal = new bootstrap.Modal(document.getElementById('studentModal'));
    studentModal.show();
    
    // Если есть ошибка с представителями, показываем вкладку с представителями
    <?php if ($error && strpos($error, 'представител') !== false): ?>
    var representativesTab = new bootstrap.Tab(document.getElementById('representatives-tab'));
    representativesTab.show();
    <?php endif; ?>
});
<?php endif; ?>

// Автоматическое форматирование телефона
document.addEventListener('DOMContentLoaded', function() {
    const phoneInputs = document.querySelectorAll('input[type="text"][placeholder*="+7"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
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
    });
});

// Обработка отправки формы представителя
document.getElementById('representativeForm').addEventListener('submit', function(e) {
    const lastName = document.getElementById('modal_rep_last_name').value.trim();
    const firstName = document.getElementById('modal_rep_first_name').value.trim();
    
    if (!lastName || !firstName) {
        e.preventDefault();
        alert('Имя и фамилия представителя обязательны для заполнения');
        return false;
    }
});
</script>

<?php include 'footer.php'; ?>