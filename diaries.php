<?php
// diaries.php
require_once 'config.php';
requireAuth();

$pageTitle = 'Дневники';
$currentUser = getCurrentUser($pdo);

$message = '';
$error = '';

// Получение списка учеников для выпадающего списка
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM students WHERE tutor_id = ? ORDER BY last_name, first_name");
$stmt->execute([$currentUser['id']]);
$students = $stmt->fetchAll();

// Получение списка категорий
$stmt = $pdo->query("SELECT * FROM categories WHERE is_visible = 1 OR 1=1 ORDER BY sort_order, name");
$categories = $stmt->fetchAll();

// Обработка действий с дневниками
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_diary'])) {
        $diaryId = $_POST['diary_id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $studentId = $_POST['student_id'] ?? null;
        $categoryId = $_POST['category_id'] ?: null;
        $lessonCost = $_POST['lesson_cost'] ? (float)$_POST['lesson_cost'] : null;
        $lessonDuration = $_POST['lesson_duration'] ? (int)$_POST['lesson_duration'] : null;
        $notes = $_POST['notes'] ?? '';
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        
        if (empty($name)) {
            $error = 'Название дневника обязательно';
        } elseif (empty($studentId)) {
            $error = 'Выберите ученика';
        } else {
            try {
                if ($diaryId) {
                    // Обновление существующего дневника
                    $stmt = $pdo->prepare("
                        UPDATE diaries 
                        SET name = ?, student_id = ?, category_id = ?, lesson_cost = ?, 
                            lesson_duration = ?, notes = ?, is_public = ?, updated_at = NOW()
                        WHERE id = ? AND tutor_id = ?
                    ");
                    $stmt->execute([$name, $studentId, $categoryId, $lessonCost, $lessonDuration, $notes, $isPublic, $diaryId, $currentUser['id']]);
                    
                    // Сохраняем в историю
                    $stmt = $pdo->prepare("
                        INSERT INTO diary_history (diary_id, changed_by, change_type, comment)
                        VALUES (?, ?, 'update', ?)
                    ");
                    $stmt->execute([$diaryId, $currentUser['id'], 'Обновлен дневник']);
                    
                    $message = 'Дневник обновлен';
                } else {
                    // Генерация публичного токена
                    $publicToken = bin2hex(random_bytes(32));
                    
                    // Добавление нового дневника
                    $stmt = $pdo->prepare("
                        INSERT INTO diaries (name, student_id, tutor_id, category_id, lesson_cost, lesson_duration, notes, is_public, public_token, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $studentId, $currentUser['id'], $categoryId, $lessonCost, $lessonDuration, $notes, $isPublic, $publicToken, $currentUser['id']]);
                    $diaryId = $pdo->lastInsertId();
                    
                    // Сохраняем в историю
                    $stmt = $pdo->prepare("
                        INSERT INTO diary_history (diary_id, changed_by, change_type, comment)
                        VALUES (?, ?, 'create', ?)
                    ");
                    $stmt->execute([$diaryId, $currentUser['id'], 'Создан новый дневник']);
                    
                    $message = 'Дневник добавлен';
                }
            } catch (Exception $e) {
                $error = 'Ошибка при сохранении дневника: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_diary'])) {
        $diaryId = $_POST['diary_id'] ?? null;
        
        if ($diaryId) {
            // Проверяем, есть ли занятия в дневнике
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE diary_id = ?");
            $stmt->execute([$diaryId]);
            $lessonsCount = $stmt->fetchColumn();
            
            if ($lessonsCount > 0) {
                $error = 'Невозможно удалить дневник, так как в нем есть ' . $lessonsCount . ' занятий';
            } else {
                try {
                    // Сохраняем в историю перед удалением
                    $stmt = $pdo->prepare("
                        INSERT INTO diary_history (diary_id, changed_by, change_type, comment)
                        VALUES (?, ?, 'delete', ?)
                    ");
                    $stmt->execute([$diaryId, $currentUser['id'], 'Дневник удален']);
                    
                    // Удаляем дневник
                    $stmt = $pdo->prepare("DELETE FROM diaries WHERE id = ? AND tutor_id = ?");
                    $stmt->execute([$diaryId, $currentUser['id']]);
                    
                    $message = 'Дневник успешно удален';
                } catch (Exception $e) {
                    $error = 'Ошибка при удалении дневника: ' . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['copy_diary'])) {
        $diaryId = $_POST['diary_id'] ?? null;
        $newName = trim($_POST['new_name'] ?? '');
        
        if ($diaryId && !empty($newName)) {
            try {
                // Получаем исходный дневник
                $stmt = $pdo->prepare("SELECT * FROM diaries WHERE id = ? AND tutor_id = ?");
                $stmt->execute([$diaryId, $currentUser['id']]);
                $sourceDiary = $stmt->fetch();
                
                if ($sourceDiary) {
                    // Генерация нового публичного токена
                    $publicToken = bin2hex(random_bytes(32));
                    
                    // Создаем копию
                    $stmt = $pdo->prepare("
                        INSERT INTO diaries (name, student_id, tutor_id, category_id, lesson_cost, lesson_duration, notes, is_public, public_token, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $newName, 
                        $sourceDiary['student_id'], 
                        $currentUser['id'], 
                        $sourceDiary['category_id'], 
                        $sourceDiary['lesson_cost'], 
                        $sourceDiary['lesson_duration'], 
                        $sourceDiary['notes'], 
                        0, // Новая копия не публична по умолчанию
                        $publicToken, 
                        $currentUser['id']
                    ]);
                    $newDiaryId = $pdo->lastInsertId();
                    
                    // Сохраняем в историю
                    $stmt = $pdo->prepare("
                        INSERT INTO diary_history (diary_id, changed_by, change_type, comment)
                        VALUES (?, ?, 'copy', ?)
                    ");
                    $stmt->execute([$newDiaryId, $currentUser['id'], 'Создана копия из дневника ID: ' . $diaryId]);
                    
                    $message = 'Копия дневника успешно создана';
                }
            } catch (Exception $e) {
                $error = 'Ошибка при копировании дневника: ' . $e->getMessage();
            }
        } else {
            $error = 'Укажите название для копии';
        }
    } elseif (isset($_POST['generate_public_link'])) {
        $diaryId = $_POST['diary_id'] ?? null;
        
        if ($diaryId) {
            // Генерируем новый токен
            $publicToken = bin2hex(random_bytes(32));
            
            $stmt = $pdo->prepare("UPDATE diaries SET public_token = ?, is_public = 1 WHERE id = ? AND tutor_id = ?");
            $stmt->execute([$publicToken, $diaryId, $currentUser['id']]);
            
            // Сохраняем в историю
            $stmt = $pdo->prepare("
                INSERT INTO diary_history (diary_id, changed_by, change_type, comment)
                VALUES (?, ?, 'settings_change', ?)
            ");
            $stmt->execute([$diaryId, $currentUser['id'], 'Сгенерирована публичная ссылка']);
            
            // Возвращаем ссылку для AJAX
            if (isset($_POST['ajax'])) {
                $baseUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/public_diary.php?token=' . $publicToken;
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'url' => $baseUrl]);
                exit;
            }
            
            $message = 'Публичная ссылка сгенерирована';
        }
    } elseif (isset($_POST['toggle_public'])) {
        $diaryId = $_POST['diary_id'] ?? null;
        $currentStatus = $_POST['current_status'] ?? 0;
        
        if ($diaryId) {
            $newStatus = $currentStatus ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE diaries SET is_public = ? WHERE id = ? AND tutor_id = ?");
            $stmt->execute([$newStatus, $diaryId, $currentUser['id']]);
            
            // Сохраняем в историю
            $stmt = $pdo->prepare("
                INSERT INTO diary_history (diary_id, changed_by, change_type, comment)
                VALUES (?, ?, 'settings_change', ?)
            ");
            $stmt->execute([$diaryId, $currentUser['id'], 'Изменен статус публичности']);
            
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'is_public' => $newStatus]);
                exit;
            }
            
            $message = 'Статус публичности изменен';
        }
    } elseif (isset($_POST['import_diary_csv'])) {
        // Импорт дневника из CSV
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            $studentId = $_POST['import_student_id'] ?? null;
            
            if ($handle && $studentId) {
                $imported = 0;
                $errors = 0;
                $line = 0;
                
                // Определяем разделитель
                $firstLine = fgets($handle);
                rewind($handle);
                $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
                
                // Читаем заголовки
                $headers = fgetcsv($handle, 0, $delimiter);
                
                if ($headers) {
                    // Создаем новый дневник для импорта
                    $diaryName = 'Импорт ' . date('d.m.Y H:i');
                    $publicToken = bin2hex(random_bytes(32));
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO diaries (name, student_id, tutor_id, created_by, public_token)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$diaryName, $studentId, $currentUser['id'], $currentUser['id'], $publicToken]);
                    $diaryId = $pdo->lastInsertId();
                    
                    while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                        $line++;
                        
                        // Ожидаемый формат: дата;время;тема;комментарий;длительность;стоимость
                        if (count($data) >= 3) {
                            $lessonDate = $data[0] ?? '';
                            $startTime = $data[1] ?? '00:00';
                            $topic = $data[2] ?? '';
                            $comment = $data[3] ?? '';
                            $duration = isset($data[4]) ? (int)$data[4] : 60;
                            $cost = isset($data[5]) ? (float)$data[5] : null;
                            
                            // Преобразуем дату
                            $lessonDate = date('Y-m-d', strtotime($lessonDate));
                            
                            if ($lessonDate && $lessonDate != '1970-01-01') {
                                try {
                                    $stmt = $pdo->prepare("
                                        INSERT INTO lessons (diary_id, lesson_date, start_time, duration, cost, comment, created_by)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)
                                    ");
                                    $stmt->execute([$diaryId, $lessonDate, $startTime, $duration, $cost, $comment, $currentUser['id']]);
                                    
                                    // Если указана тема, пробуем найти или создать
                                    if (!empty($topic)) {
                                        $stmt = $pdo->prepare("SELECT id FROM topics WHERE name = ?");
                                        $stmt->execute([$topic]);
                                        $topicId = $stmt->fetchColumn();
                                        
                                        if ($topicId) {
                                            $lessonId = $pdo->lastInsertId();
                                            $stmt = $pdo->prepare("INSERT INTO lesson_topics (lesson_id, topic_id) VALUES (?, ?)");
                                            $stmt->execute([$lessonId, $topicId]);
                                        }
                                    }
                                    
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
                        VALUES (?, 'diaries', 'import', 'csv', ?, ?, ?)
                    ");
                    $status = $errors === 0 ? 'success' : 'failed';
                    $details = "Импортировано занятий: $imported, ошибок: $errors";
                    $stmt->execute([$currentUser['id'], $_FILES['csv_file']['name'], $status, $details]);
                    
                    $message = "Импорт завершен. Создан дневник '$diaryName' с $imported занятиями. Ошибок: $errors";
                } else {
                    $error = 'Неверный формат CSV файла';
                }
                fclose($handle);
            } else {
                $error = 'Выберите ученика для импорта';
            }
        } else {
            $error = 'Выберите файл для импорта';
        }
    } elseif (isset($_POST['import_diary_json'])) {
        // Импорт дневника из JSON
        if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
            $jsonContent = file_get_contents($_FILES['json_file']['tmp_name']);
            $data = json_decode($jsonContent, true);
            $studentId = $_POST['import_student_id_json'] ?? null;
            
            if (is_array($data) && $studentId) {
                try {
                    // Создаем новый дневник
                    $diaryName = $data['name'] ?? ('Импорт ' . date('d.m.Y H:i'));
                    $publicToken = bin2hex(random_bytes(32));
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO diaries (name, student_id, tutor_id, lesson_cost, lesson_duration, notes, created_by, public_token)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $diaryName, 
                        $studentId, 
                        $currentUser['id'], 
                        $data['lesson_cost'] ?? null, 
                        $data['lesson_duration'] ?? null, 
                        $data['notes'] ?? null, 
                        $currentUser['id'],
                        $publicToken
                    ]);
                    $diaryId = $pdo->lastInsertId();
                    
                    $imported = 0;
                    
                    // Импортируем занятия
                    if (isset($data['lessons']) && is_array($data['lessons'])) {
                        foreach ($data['lessons'] as $lesson) {
                            $stmt = $pdo->prepare("
                                INSERT INTO lessons (diary_id, lesson_date, start_time, duration, cost, is_conducted, is_paid, homework, comment, link_url, link_comment, grade_lesson, grade_comment, grade_homework, grade_homework_comment, created_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $diaryId,
                                $lesson['lesson_date'] ?? date('Y-m-d'),
                                $lesson['start_time'] ?? '00:00',
                                $lesson['duration'] ?? 60,
                                $lesson['cost'] ?? null,
                                $lesson['is_conducted'] ?? 0,
                                $lesson['is_paid'] ?? 0,
                                $lesson['homework'] ?? null,
                                $lesson['comment'] ?? null,
                                $lesson['link_url'] ?? null,
                                $lesson['link_comment'] ?? null,
                                $lesson['grade_lesson'] ?? null,
                                $lesson['grade_comment'] ?? null,
                                $lesson['grade_homework'] ?? null,
                                $lesson['grade_homework_comment'] ?? null,
                                $currentUser['id']
                            ]);
                            
                            $lessonId = $pdo->lastInsertId();
                            $imported++;
                        }
                    }
                    
                    // Логируем импорт
                    $stmt = $pdo->prepare("
                        INSERT INTO import_export_log (user_id, entity_type, action, file_format, file_name, status, details)
                        VALUES (?, 'diaries', 'import', 'json', ?, 'success', ?)
                    ");
                    $details = "Импортировано занятий: $imported";
                    $stmt->execute([$currentUser['id'], $_FILES['json_file']['name'], $details]);
                    
                    $message = "Импорт завершен. Создан дневник '$diaryName' с $imported занятиями";
                    
                } catch (Exception $e) {
                    $error = 'Ошибка при импорте JSON: ' . $e->getMessage();
                }
            } else {
                $error = 'Неверный формат JSON файла или не выбран ученик';
            }
        } else {
            $error = 'Выберите файл для импорта';
        }
    }
}

// Экспорт дневника
if (isset($_GET['export']) && isset($_GET['id'])) {
    $diaryId = $_GET['id'];
    $format = $_GET['export'];
    
    // Получаем данные дневника
    $stmt = $pdo->prepare("
        SELECT d.*, s.first_name as student_first_name, s.last_name as student_last_name
        FROM diaries d
        JOIN students s ON d.student_id = s.id
        WHERE d.id = ? AND d.tutor_id = ?
    ");
    $stmt->execute([$diaryId, $currentUser['id']]);
    $diary = $stmt->fetch();
    
    if ($diary) {
        // Получаем занятия
        $stmt = $pdo->prepare("
            SELECT l.*, 
                   GROUP_CONCAT(t.name) as topic_names
            FROM lessons l
            LEFT JOIN lesson_topics lt ON l.id = lt.lesson_id
            LEFT JOIN topics t ON lt.topic_id = t.id
            WHERE l.diary_id = ?
            GROUP BY l.id
            ORDER BY l.lesson_date, l.start_time
        ");
        $stmt->execute([$diaryId]);
        $lessons = $stmt->fetchAll();
        
        if ($format === 'csv') {
            // Экспорт в CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=diary_' . $diaryId . '_' . date('Y-m-d') . '.csv');
            
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM для UTF-8
            
            // Заголовки
            fputcsv($output, ['Дата', 'Время', 'Темы', 'Комментарий', 'Длительность', 'Стоимость', 'Проведено', 'Оплачено', 'ДЗ', 'Оценка'], ';');
            
            // Данные
            foreach ($lessons as $lesson) {
                fputcsv($output, [
                    $lesson['lesson_date'],
                    substr($lesson['start_time'], 0, 5),
                    $lesson['topic_names'] ?? '',
                    $lesson['comment'] ?? '',
                    $lesson['duration'],
                    $lesson['cost'],
                    $lesson['is_conducted'] ? 'Да' : 'Нет',
                    $lesson['is_paid'] ? 'Да' : 'Нет',
                    $lesson['homework'] ?? '',
                    $lesson['grade_lesson'] ?? ''
                ], ';');
            }
            
            fclose($output);
            exit;
            
        } elseif ($format === 'json') {
            // Экспорт в JSON
            $exportData = [
                'name' => $diary['name'],
                'student' => $diary['student_first_name'] . ' ' . $diary['student_last_name'],
                'lesson_cost' => $diary['lesson_cost'],
                'lesson_duration' => $diary['lesson_duration'],
                'notes' => $diary['notes'],
                'created_at' => $diary['created_at'],
                'lessons' => []
            ];
            
            foreach ($lessons as $lesson) {
                $exportData['lessons'][] = [
                    'lesson_date' => $lesson['lesson_date'],
                    'start_time' => $lesson['start_time'],
                    'duration' => $lesson['duration'],
                    'cost' => $lesson['cost'],
                    'is_conducted' => (bool)$lesson['is_conducted'],
                    'is_paid' => (bool)$lesson['is_paid'],
                    'homework' => $lesson['homework'],
                    'comment' => $lesson['comment'],
                    'link_url' => $lesson['link_url'],
                    'link_comment' => $lesson['link_comment'],
                    'grade_lesson' => $lesson['grade_lesson'],
                    'grade_comment' => $lesson['grade_comment'],
                    'grade_homework' => $lesson['grade_homework'],
                    'grade_homework_comment' => $lesson['grade_homework_comment'],
                    'topics' => $lesson['topic_names'] ? explode(',', $lesson['topic_names']) : []
                ];
            }
            
            // Логируем экспорт
            $stmt = $pdo->prepare("
                INSERT INTO import_export_log (user_id, entity_type, action, file_format, status)
                VALUES (?, 'diaries', 'export', ?, 'success')
            ");
            $stmt->execute([$currentUser['id'], $format]);
            
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename=diary_' . $diaryId . '_' . date('Y-m-d') . '.json');
            
            echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// Получение списка дневников с фильтрацией
$categoryFilter = $_GET['category'] ?? '';
$studentFilter = $_GET['student'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$searchTerm = $_GET['search'] ?? '';

$query = "
    SELECT d.*, 
           s.first_name as student_first_name, 
           s.last_name as student_last_name,
           c.name as category_name,
           c.color as category_color,
           (SELECT COUNT(*) FROM lessons WHERE diary_id = d.id) as lessons_count,
           (SELECT COUNT(*) FROM lessons WHERE diary_id = d.id AND is_conducted = 1) as conducted_count,
           (SELECT SUM(cost) FROM lessons WHERE diary_id = d.id AND is_paid = 1) as total_paid
    FROM diaries d
    JOIN students s ON d.student_id = s.id
    LEFT JOIN categories c ON d.category_id = c.id
    WHERE d.tutor_id = ?
";
$params = [$currentUser['id']];

if (!empty($categoryFilter)) {
    $query .= " AND d.category_id = ?";
    $params[] = $categoryFilter;
}

if (!empty($studentFilter)) {
    $query .= " AND d.student_id = ?";
    $params[] = $studentFilter;
}

if (!empty($dateFrom)) {
    $query .= " AND DATE(d.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $query .= " AND DATE(d.created_at) <= ?";
    $params[] = $dateTo;
}

if (!empty($searchTerm)) {
    $query .= " AND (d.name LIKE ? OR s.last_name LIKE ? OR s.first_name LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

$query .= " ORDER BY d.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$diaries = $stmt->fetchAll();

// Получаем данные для редактирования
$editDiary = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM diaries WHERE id = ? AND tutor_id = ?");
    $stmt->execute([$_GET['edit'], $currentUser['id']]);
    $editDiary = $stmt->fetch();
}

include 'header.php';
?>

<div class="container-fluid px-4">
    <!-- Заголовок и кнопки действий -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Дневники</h1>
        <div>
            <button type="button" class="btn btn-success" onclick="showAddDiaryModal()">
                <i class="bi bi-plus-circle"></i> Создать дневник
            </button>
            <button type="button" class="btn btn-warning" onclick="showImportModal()">
                <i class="bi bi-upload"></i> Импорт
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
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Название или ученик">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ученик</label>
                    <select class="form-select" name="student">
                        <option value="">Все ученики</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>" <?php echo $studentFilter == $student['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?>
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
                    <label class="form-label">Дата с</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Дата по</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Список дневников -->
    <div class="row">
        <?php if (empty($diaries)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Нет дневников. Нажмите "Создать дневник", чтобы создать первый дневник.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($diaries as $diary): ?>
                <div class="col-md-6 col-lg-4 mb-4" id="diary_<?php echo $diary['id']; ?>">
                    <div class="card h-100 diary-card">
                        <div class="card-header d-flex justify-content-between align-items-center"
                             style="<?php echo $diary['category_color'] ? 'border-left: 4px solid ' . $diary['category_color'] : ''; ?>">
                            <h6 class="mb-0 fw-bold">
                                <?php echo htmlspecialchars($diary['name']); ?>
                                <?php if ($diary['is_public']): ?>
                                    <span class="badge bg-info ms-1" title="Публичный дневник">
                                        <i class="bi bi-globe"></i>
                                    </span>
                                <?php endif; ?>
                            </h6>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="diary.php?id=<?php echo $diary['id']; ?>">
                                            <i class="bi bi-journal-text"></i> Перейти к дневнику
                                        </a>
                                    </li>
                                    <li>
                                        <button class="dropdown-item" onclick="editDiary(<?php echo $diary['id']; ?>)">
                                            <i class="bi bi-pencil"></i> Редактировать
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item" onclick="showCopyModal(<?php echo $diary['id']; ?>, '<?php echo htmlspecialchars($diary['name']); ?>')">
                                            <i class="bi bi-files"></i> Создать копию
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item" onclick="generatePublicLink(<?php echo $diary['id']; ?>)">
                                            <i class="bi bi-link"></i> Публичная ссылка
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item" onclick="togglePublic(<?php echo $diary['id']; ?>, <?php echo $diary['is_public']; ?>)">
                                            <i class="bi bi-<?php echo $diary['is_public'] ? 'eye-slash' : 'eye'; ?>"></i> 
                                            <?php echo $diary['is_public'] ? 'Скрыть' : 'Опубликовать'; ?>
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <div class="btn-group ms-2" role="group">
                                            <a href="?export=csv&id=<?php echo $diary['id']; ?>" class="btn btn-sm btn-outline-info">
                                                CSV
                                            </a>
                                            <a href="?export=json&id=<?php echo $diary['id']; ?>" class="btn btn-sm btn-outline-info">
                                                JSON
                                            </a>
                                        </div>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button class="dropdown-item text-danger" onclick="deleteDiary(<?php echo $diary['id']; ?>)">
                                            <i class="bi bi-trash"></i> Удалить
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>Ученик:</strong> 
                                <a href="students.php?edit=<?php echo $diary['student_id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($diary['student_last_name'] . ' ' . $diary['student_first_name']); ?>
                                </a>
                            </div>
                            
                            <?php if ($diary['category_name']): ?>
                                <div class="mb-2">
                                    <span class="badge" style="background-color: <?php echo $diary['category_color']; ?>; color: white;">
                                        <i class="bi bi-folder"></i> <?php echo htmlspecialchars($diary['category_name']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row mt-3">
                                <div class="col-6">
                                    <small class="text-muted d-block">
                                        <i class="bi bi-currency-ruble"></i> <?php echo $diary['lesson_cost'] ? number_format($diary['lesson_cost'], 0, ',', ' ') . ' ₽' : 'не указано'; ?>
                                    </small>
                                    <small class="text-muted d-block">
                                        <i class="bi bi-clock"></i> <?php echo $diary['lesson_duration'] ? $diary['lesson_duration'] . ' мин' : 'не указано'; ?>
                                    </small>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">
                                        <i class="bi bi-calendar-check"></i> Занятий: <?php echo $diary['lessons_count']; ?>
                                    </small>
                                    <small class="text-muted d-block">
                                        <i class="bi bi-check-circle"></i> Проведено: <?php echo $diary['conducted_count']; ?>
                                    </small>
                                </div>
                            </div>
                            
                            <?php if ($diary['total_paid'] > 0): ?>
                                <div class="mt-2">
                                    <small class="text-success">
                                        <i class="bi bi-cash"></i> Оплачено: <?php echo number_format($diary['total_paid'], 0, ',', ' '); ?> ₽
                                    </small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($diary['notes']): ?>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="bi bi-chat"></i> <?php echo nl2br(htmlspecialchars(substr($diary['notes'], 0, 100))); ?>
                                        <?php if (strlen($diary['notes']) > 100): ?>...<?php endif; ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                            
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-calendar"></i> Создан: <?php echo date('d.m.Y', strtotime($diary['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно для создания/редактирования дневника -->
<div class="modal fade" id="diaryModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="diaryModalTitle">Создание дневника</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="diaries.php" id="diaryForm">
                <input type="hidden" name="diary_id" id="diary_id" value="<?php echo $editDiary['id'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название дневника <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="diary_name" 
                               value="<?php echo htmlspecialchars($editDiary['name'] ?? ''); ?>" required maxlength="255">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ученик <span class="text-danger">*</span></label>
                        <select class="form-select" name="student_id" id="diary_student" required>
                            <option value="">Выберите ученика</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo ($editDiary['student_id'] ?? '') == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Категория</label>
                        <select class="form-select" name="category_id" id="diary_category">
                            <option value="">Без категории</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" 
                                        style="color: <?php echo $cat['color']; ?>;"
                                        <?php echo ($editDiary['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Стоимость занятия (₽)</label>
                            <input type="number" class="form-control" name="lesson_cost" id="diary_cost" 
                                   value="<?php echo $editDiary['lesson_cost'] ?? ''; ?>" step="100" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Длительность занятия (минут)</label>
                            <input type="number" class="form-control" name="lesson_duration" id="diary_duration" 
                                   value="<?php echo $editDiary['lesson_duration'] ?? '60'; ?>" min="15" step="15">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Заметки</label>
                        <textarea class="form-control" name="notes" id="diary_notes" rows="3"><?php echo htmlspecialchars($editDiary['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_public" id="diary_public" 
                               <?php echo ($editDiary['is_public'] ?? 0) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="diary_public">
                            Публичный дневник (доступен по ссылке)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="save_diary" class="btn btn-primary">Сохранить дневник</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно для импорта -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Импорт дневника</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <ul class="nav nav-tabs" id="importTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="csv-tab" data-bs-toggle="tab" data-bs-target="#csv" type="button" role="tab">CSV</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="json-tab" data-bs-toggle="tab" data-bs-target="#json" type="button" role="tab">JSON</button>
                </li>
            </ul>
            <div class="tab-content" id="importTabsContent">
                <!-- CSV импорт -->
                <div class="tab-pane fade show active p-3" id="csv" role="tabpanel">
                    <form method="POST" action="diaries.php" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Ученик <span class="text-danger">*</span></label>
                            <select class="form-select" name="import_student_id" required>
                                <option value="">Выберите ученика</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Выберите CSV файл</label>
                            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Формат файла:</h6>
                            <p>CSV с разделителем ; или ,</p>
                            <p>Колонки: дата;время;тема;комментарий;длительность;стоимость</p>
                            <pre>01.02.2024;15:00;Алгебра;Уравнения;60;1500
05.02.2024;16:30;Геометрия;Треугольники;60;1500</pre>
                        </div>
                        <button type="submit" name="import_diary_csv" class="btn btn-success w-100">Импортировать CSV</button>
                    </form>
                </div>
                
                <!-- JSON импорт -->
                <div class="tab-pane fade p-3" id="json" role="tabpanel">
                    <form method="POST" action="diaries.php" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Ученик <span class="text-danger">*</span></label>
                            <select class="form-select" name="import_student_id_json" required>
                                <option value="">Выберите ученика</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Выберите JSON файл</label>
                            <input type="file" class="form-control" name="json_file" accept=".json" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Формат файла:</h6>
                            <pre>{
  "name": "Дневник по математике",
  "lesson_cost": 1500,
  "lesson_duration": 60,
  "notes": "Заметки",
  "lessons": [
    {
      "lesson_date": "2024-02-01",
      "start_time": "15:00",
      "duration": 60,
      "cost": 1500,
      "comment": "Уравнения"
    }
  ]
}</pre>
                        </div>
                        <button type="submit" name="import_diary_json" class="btn btn-success w-100">Импортировать JSON</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для создания копии -->
<div class="modal fade" id="copyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Создание копии дневника</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="diaries.php">
                <input type="hidden" name="diary_id" id="copy_diary_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название для копии <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="new_name" id="copy_diary_name" required>
                        <small class="text-muted">Будет создана новая копия дневника без занятий</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="copy_diary" class="btn btn-primary">Создать копию</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно для публичной ссылки -->
<div class="modal fade" id="publicLinkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Публичная ссылка</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Ссылка для публичного доступа к дневнику:</p>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="publicLinkUrl" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="copyPublicLink()">
                        <i class="bi bi-files"></i>
                    </button>
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> По этой ссылке любой сможет просматривать дневник без авторизации.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Скрытые формы -->
<form method="POST" id="deleteDiaryForm" style="display: none;">
    <input type="hidden" name="diary_id" id="delete_diary_id">
    <input type="hidden" name="delete_diary">
</form>

<form method="POST" id="publicLinkForm" style="display: none;">
    <input type="hidden" name="diary_id" id="public_link_diary_id">
    <input type="hidden" name="generate_public_link">
    <input type="hidden" name="ajax" value="1">
</form>

<form method="POST" id="togglePublicForm" style="display: none;">
    <input type="hidden" name="diary_id" id="toggle_diary_id">
    <input type="hidden" name="current_status" id="toggle_current_status">
    <input type="hidden" name="toggle_public">
    <input type="hidden" name="ajax" value="1">
</form>

<style>
.diary-card {
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}
.diary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}
.diary-card .card-header {
    padding: 0.75rem 1rem;
}
</style>

<script>
// Функции для работы с дневниками
function showAddDiaryModal() {
    document.getElementById('diary_id').value = '';
    document.getElementById('diary_name').value = '';
    document.getElementById('diary_student').value = '';
    document.getElementById('diary_category').value = '';
    document.getElementById('diary_cost').value = '';
    document.getElementById('diary_duration').value = '60';
    document.getElementById('diary_notes').value = '';
    document.getElementById('diary_public').checked = false;
    document.getElementById('diaryModalTitle').textContent = 'Создание дневника';
    
    var modal = new bootstrap.Modal(document.getElementById('diaryModal'));
    modal.show();
}

function editDiary(id) {
    window.location.href = 'diaries.php?edit=' + id;
}

function deleteDiary(id) {
    if (confirm('Вы уверены, что хотите удалить этот дневник?')) {
        document.getElementById('delete_diary_id').value = id;
        document.getElementById('deleteDiaryForm').submit();
    }
}

function showCopyModal(id, name) {
    document.getElementById('copy_diary_id').value = id;
    document.getElementById('copy_diary_name').value = 'Копия - ' + name;
    
    var modal = new bootstrap.Modal(document.getElementById('copyModal'));
    modal.show();
}

function generatePublicLink(id) {
    document.getElementById('public_link_diary_id').value = id;
    
    fetch('diaries.php', {
        method: 'POST',
        body: new FormData(document.getElementById('publicLinkForm'))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('publicLinkUrl').value = data.url;
            var modal = new bootstrap.Modal(document.getElementById('publicLinkModal'));
            modal.show();
        }
    })
    .catch(error => {
        alert('Ошибка при генерации ссылки');
    });
}

function togglePublic(id, currentStatus) {
    document.getElementById('toggle_diary_id').value = id;
    document.getElementById('toggle_current_status').value = currentStatus;
    
    fetch('diaries.php', {
        method: 'POST',
        body: new FormData(document.getElementById('togglePublicForm'))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Обновляем иконку в карточке
            const card = document.getElementById('diary_' + id);
            const publicIcon = card.querySelector('.badge.bg-info');
            const menuItem = card.querySelector('.dropdown-item:nth-child(4) i');
            const menuText = card.querySelector('.dropdown-item:nth-child(4)');
            
            if (data.is_public) {
                if (!publicIcon) {
                    const title = card.querySelector('.card-header h6');
                    title.innerHTML += ' <span class="badge bg-info ms-1" title="Публичный дневник"><i class="bi bi-globe"></i></span>';
                }
                menuItem.className = 'bi bi-eye-slash';
                menuText.innerHTML = '<i class="bi bi-eye-slash"></i> Скрыть';
            } else {
                if (publicIcon) publicIcon.remove();
                menuItem.className = 'bi bi-eye';
                menuText.innerHTML = '<i class="bi bi-eye"></i> Опубликовать';
            }
        }
    })
    .catch(error => {
        alert('Ошибка при изменении статуса');
    });
}

function showImportModal() {
    var modal = new bootstrap.Modal(document.getElementById('importModal'));
    modal.show();
}

function copyPublicLink() {
    const link = document.getElementById('publicLinkUrl');
    link.select();
    document.execCommand('copy');
    alert('Ссылка скопирована в буфер обмена');
}

// Показываем модальное окно если есть редактируемый дневник
<?php if ($editDiary): ?>
document.addEventListener('DOMContentLoaded', function() {
    var diaryModal = new bootstrap.Modal(document.getElementById('diaryModal'));
    diaryModal.show();
});
<?php endif; ?>

// Клик по карточке дневника
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.diary-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown') && !e.target.closest('button') && !e.target.closest('a')) {
                const id = this.closest('[id^="diary_"]').id.replace('diary_', '');
                window.location.href = 'diary.php?id=' + id;
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