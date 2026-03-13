<?php
// topics.php
require_once 'config.php';
requireAuth();

$pageTitle = 'Банк тем';
$currentUser = getCurrentUser($pdo);

$message = '';
$error = '';

// Получение списка категорий для выпадающего списка
$stmt = $pdo->query("SELECT * FROM categories WHERE is_visible = 1 OR 1=1 ORDER BY sort_order, name");
$categories = $stmt->fetchAll();

// Получение списка меток
$stmt = $pdo->query("SELECT * FROM tags ORDER BY 
    CASE color 
        WHEN 'green' THEN 1 
        WHEN 'red' THEN 2 
        WHEN 'blue' THEN 3 
        WHEN 'grey' THEN 4 
    END, name");
$allTags = $stmt->fetchAll();

// Обработка действий с темами
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_topic'])) {
        $topicId = $_POST['topic_id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $categoryId = $_POST['category_id'] ?: null;
        $selectedTags = $_POST['tags'] ?? [];
        $resources = $_POST['resources'] ?? [];
        
        if (empty($name)) {
            $error = 'Название темы обязательно';
        } else {
            try {
                $pdo->beginTransaction();
                
                if ($topicId) {
                    // Обновление существующей темы
                    $stmt = $pdo->prepare("
                        UPDATE topics 
                        SET name = ?, category_id = ?, updated_at = NOW()
                        WHERE id = ? AND (created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
                    ");
                    $stmt->execute([$name, $categoryId, $topicId, $currentUser['id'], $currentUser['id']]);
                    
                    // Удаляем старые связи с метками
                    $stmt = $pdo->prepare("DELETE FROM topic_tags WHERE topic_id = ?");
                    $stmt->execute([$topicId]);
                    
                    // Удаляем старые ресурсы
                    $stmt = $pdo->prepare("DELETE FROM topic_resources WHERE topic_id = ?");
                    $stmt->execute([$topicId]);
                    
                    $message = 'Тема обновлена';
                } else {
                    // Добавление новой темы
                    $stmt = $pdo->prepare("
                        INSERT INTO topics (name, category_id, created_by)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$name, $categoryId, $currentUser['id']]);
                    $topicId = $pdo->lastInsertId();
                    
                    $message = 'Тема добавлена';
                }
                
                // Добавляем новые связи с метками
                if (!empty($selectedTags)) {
                    $stmt = $pdo->prepare("INSERT INTO topic_tags (topic_id, tag_id) VALUES (?, ?)");
                    foreach ($selectedTags as $tagId) {
                        $stmt->execute([$topicId, $tagId]);
                    }
                }
                
                // Добавляем новые ресурсы
                if (!empty($resources)) {
                    $stmt = $pdo->prepare("INSERT INTO topic_resources (topic_id, url, description) VALUES (?, ?, ?)");
                    foreach ($resources as $resource) {
                        if (!empty($resource['url'])) {
                            $stmt->execute([$topicId, $resource['url'], $resource['description'] ?? '']);
                        }
                    }
                }
                
                $pdo->commit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Ошибка при сохранении темы: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_topic'])) {
        $topicId = $_POST['topic_id'] ?? null;
        
        if ($topicId) {
            // Проверяем, используется ли тема в занятиях
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_topics WHERE topic_id = ?");
            $stmt->execute([$topicId]);
            $lessonsCount = $stmt->fetchColumn();
            
            if ($lessonsCount > 0) {
                $error = 'Невозможно удалить тему, так как она используется в ' . $lessonsCount . ' занятиях';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Удаляем связи с метками
                    $stmt = $pdo->prepare("DELETE FROM topic_tags WHERE topic_id = ?");
                    $stmt->execute([$topicId]);
                    
                    // Удаляем ресурсы темы
                    $stmt = $pdo->prepare("DELETE FROM topic_resources WHERE topic_id = ?");
                    $stmt->execute([$topicId]);
                    
                    // Удаляем тему
                    $stmt = $pdo->prepare("DELETE FROM topics WHERE id = ? AND (created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))");
                    $stmt->execute([$topicId, $currentUser['id'], $currentUser['id']]);
                    
                    $pdo->commit();
                    $message = 'Тема успешно удалена';
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Ошибка при удалении темы: ' . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['clear_all_topics'])) {
        // Очистка всех тем (только для администратора)
        if (isAdmin()) {
            // Проверяем, используются ли темы в занятиях
            $stmt = $pdo->query("SELECT COUNT(*) FROM lesson_topics");
            $lessonsCount = $stmt->fetchColumn();
            
            if ($lessonsCount > 0) {
                $error = 'Невозможно очистить все темы, так как они используются в занятиях';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Удаляем все связи с метками
                    $stmt = $pdo->prepare("DELETE FROM topic_tags");
                    $stmt->execute();
                    
                    // Удаляем все ресурсы тем
                    $stmt = $pdo->prepare("DELETE FROM topic_resources");
                    $stmt->execute();
                    
                    // Удаляем все темы
                    $stmt = $pdo->prepare("DELETE FROM topics");
                    $stmt->execute();
                    
                    $pdo->commit();
                    $message = 'Все темы успешно удалены';
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Ошибка при очистке тем: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'Недостаточно прав для выполнения операции';
        }
    } elseif (isset($_POST['import_topics'])) {
        // Импорт тем из CSV
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            $parentCategoryId = $_POST['parent_category_id'] ?: null;
            
            if ($handle) {
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
                    $nameIndex = array_search('name', $headers);
                    $categoryIndex = array_search('category', $headers);
                    $tagsIndex = array_search('tags', $headers);
                    
                    if ($nameIndex !== false) {
                        while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                            $line++;
                            $name = trim($data[$nameIndex] ?? '');
                            
                            if (!empty($name)) {
                                try {
                                    // Определяем категорию
                                    $categoryId = $parentCategoryId;
                                    if ($categoryIndex !== false && !empty($data[$categoryIndex])) {
                                        $categoryName = trim($data[$categoryIndex]);
                                        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                                        $stmt->execute([$categoryName]);
                                        $foundCategory = $stmt->fetch();
                                        if ($foundCategory) {
                                            $categoryId = $foundCategory['id'];
                                        }
                                    }
                                    
                                    // Создаем тему
                                    $stmt = $pdo->prepare("
                                        INSERT INTO topics (name, category_id, created_by)
                                        VALUES (?, ?, ?)
                                    ");
                                    $stmt->execute([$name, $categoryId, $currentUser['id']]);
                                    $topicId = $pdo->lastInsertId();
                                    
                                    // Обрабатываем метки
                                    if ($tagsIndex !== false && !empty($data[$tagsIndex])) {
                                        $tagNames = array_map('trim', explode(',', $data[$tagsIndex]));
                                        foreach ($tagNames as $tagName) {
                                            if (!empty($tagName)) {
                                                $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                                                $stmt->execute([$tagName]);
                                                $tag = $stmt->fetch();
                                                if ($tag) {
                                                    $stmt = $pdo->prepare("INSERT INTO topic_tags (topic_id, tag_id) VALUES (?, ?)");
                                                    $stmt->execute([$topicId, $tag['id']]);
                                                }
                                            }
                                        }
                                    }
                                    
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
                            VALUES (?, 'topics', 'import', 'csv', ?, ?, ?)
                        ");
                        $status = $errors === 0 ? 'success' : 'failed';
                        $details = "Импортировано: $imported, ошибок: $errors";
                        $stmt->execute([$currentUser['id'], $_FILES['csv_file']['name'], $status, $details]);
                        
                        $message = "Импорт завершен. Добавлено: $imported, пропущено: $errors";
                    } else {
                        $error = 'CSV файл должен содержать колонку "name"';
                    }
                } else {
                    $error = 'Неверный формат CSV файла';
                }
                fclose($handle);
            } else {
                $error = 'Ошибка при открытии файла';
            }
        } else {
            $error = 'Выберите файл для импорта';
        }
    } elseif (isset($_POST['import_topics_json'])) {
        // Импорт тем из JSON
        if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
            $jsonContent = file_get_contents($_FILES['json_file']['tmp_name']);
            $topics = json_decode($jsonContent, true);
            
            if (is_array($topics)) {
                $imported = 0;
                $errors = 0;
                
                foreach ($topics as $topic) {
                    $name = trim($topic['name'] ?? '');
                    
                    if (!empty($name)) {
                        try {
                            $categoryId = null;
                            if (!empty($topic['category'])) {
                                $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                                $stmt->execute([$topic['category']]);
                                $category = $stmt->fetch();
                                if ($category) {
                                    $categoryId = $category['id'];
                                }
                            }
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO topics (name, category_id, created_by)
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([$name, $categoryId, $currentUser['id']]);
                            $topicId = $pdo->lastInsertId();
                            
                            // Добавляем метки
                            if (!empty($topic['tags']) && is_array($topic['tags'])) {
                                foreach ($topic['tags'] as $tagName) {
                                    $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                                    $stmt->execute([$tagName]);
                                    $tag = $stmt->fetch();
                                    if ($tag) {
                                        $stmt = $pdo->prepare("INSERT INTO topic_tags (topic_id, tag_id) VALUES (?, ?)");
                                        $stmt->execute([$topicId, $tag['id']]);
                                    }
                                }
                            }
                            
                            // Добавляем ресурсы
                            if (!empty($topic['resources']) && is_array($topic['resources'])) {
                                $stmt = $pdo->prepare("INSERT INTO topic_resources (topic_id, url, description) VALUES (?, ?, ?)");
                                foreach ($topic['resources'] as $resource) {
                                    if (!empty($resource['url'])) {
                                        $stmt->execute([$topicId, $resource['url'], $resource['description'] ?? '']);
                                    }
                                }
                            }
                            
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
                    VALUES (?, 'topics', 'import', 'json', ?, ?, ?)
                ");
                $status = $errors === 0 ? 'success' : 'failed';
                $details = "Импортировано: $imported, ошибок: $errors";
                $stmt->execute([$currentUser['id'], $_FILES['json_file']['name'], $status, $details]);
                
                $message = "Импорт завершен. Добавлено: $imported, пропущено: $errors";
            } else {
                $error = 'Неверный формат JSON файла';
            }
        } else {
            $error = 'Выберите файл для импорта';
        }
    }
}

// Экспорт тем в CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("
        SELECT 
            t.name,
            c.name as category_name,
            GROUP_CONCAT(DISTINCT tg.name SEPARATOR ', ') as tags
        FROM topics t
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN topic_tags tt ON t.id = tt.topic_id
        LEFT JOIN tags tg ON tt.tag_id = tg.id
        GROUP BY t.id
        ORDER BY t.name
    ");
    $topics = $stmt->fetchAll();
    
    // Логируем экспорт
    $stmt = $pdo->prepare("
        INSERT INTO import_export_log (user_id, entity_type, action, file_format, status)
        VALUES (?, 'topics', 'export', 'csv', 'success')
    ");
    $stmt->execute([$currentUser['id']]);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=topics_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM для UTF-8
    
    // Заголовки
    fputcsv($output, ['name', 'category', 'tags'], ';');
    
    // Данные
    foreach ($topics as $topic) {
        fputcsv($output, [
            $topic['name'],
            $topic['category_name'] ?? '',
            $topic['tags'] ?? ''
        ], ';');
    }
    
    fclose($output);
    exit;
}

// Экспорт тем в JSON
if (isset($_GET['export']) && $_GET['export'] === 'json') {
    $stmt = $pdo->query("
        SELECT 
            t.id,
            t.name,
            c.name as category,
            (SELECT GROUP_CONCAT(tg.name) 
             FROM topic_tags tt 
             JOIN tags tg ON tt.tag_id = tg.id 
             WHERE tt.topic_id = t.id) as tags_string,
            (SELECT JSON_ARRAYAGG(
                JSON_OBJECT('url', tr.url, 'description', tr.description)
             ) FROM topic_resources tr WHERE tr.topic_id = t.id) as resources_json
        FROM topics t
        LEFT JOIN categories c ON t.category_id = c.id
        ORDER BY t.name
    ");
    
    $topics = [];
    while ($row = $stmt->fetch()) {
        $topic = [
            'name' => $row['name'],
            'category' => $row['category']
        ];
        
        if ($row['tags_string']) {
            $topic['tags'] = explode(',', $row['tags_string']);
        }
        
        if ($row['resources_json'] && $row['resources_json'] != 'null') {
            $topic['resources'] = json_decode($row['resources_json'], true);
        }
        
        $topics[] = $topic;
    }
    
    // Логируем экспорт
    $stmt = $pdo->prepare("
        INSERT INTO import_export_log (user_id, entity_type, action, file_format, status)
        VALUES (?, 'topics', 'export', 'json', 'success')
    ");
    $stmt->execute([$currentUser['id']]);
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=topics_' . date('Y-m-d') . '.json');
    
    echo json_encode($topics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Получение списка тем с фильтрацией
$categoryFilter = $_GET['category'] ?? '';
$tagFilter = $_GET['tag'] ?? '';
$searchTerm = $_GET['search'] ?? '';

$query = "
    SELECT 
        t.*,
        c.name as category_name,
        c.color as category_color,
        GROUP_CONCAT(DISTINCT tg.name) as tag_names,
        GROUP_CONCAT(DISTINCT tg.id) as tag_ids,
        (SELECT COUNT(*) FROM lesson_topics lt WHERE lt.topic_id = t.id) as lessons_count
    FROM topics t
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN topic_tags tt ON t.id = tt.topic_id
    LEFT JOIN tags tg ON tt.tag_id = tg.id
    WHERE 1=1
";
$params = [];

if (!empty($categoryFilter)) {
    $query .= " AND t.category_id = ?";
    $params[] = $categoryFilter;
}

if (!empty($tagFilter)) {
    $query .= " AND tt.tag_id = ?";
    $params[] = $tagFilter;
}

if (!empty($searchTerm)) {
    $query .= " AND t.name LIKE ?";
    $params[] = "%$searchTerm%";
}

$query .= " GROUP BY t.id ORDER BY t.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$topics = $stmt->fetchAll();

// Получаем ресурсы для каждой темы
foreach ($topics as &$topic) {
    $stmt = $pdo->prepare("SELECT * FROM topic_resources WHERE topic_id = ? ORDER BY id");
    $stmt->execute([$topic['id']]);
    $topic['resources'] = $stmt->fetchAll();
}

// Получаем данные для редактирования
$editTopic = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("
        SELECT t.*, GROUP_CONCAT(tt.tag_id) as selected_tags
        FROM topics t
        LEFT JOIN topic_tags tt ON t.id = tt.topic_id
        WHERE t.id = ? AND (t.created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
        GROUP BY t.id
    ");
    $stmt->execute([$_GET['edit'], $currentUser['id'], $currentUser['id']]);
    $editTopic = $stmt->fetch();
    
    if ($editTopic) {
        $editTopic['selected_tags'] = $editTopic['selected_tags'] ? explode(',', $editTopic['selected_tags']) : [];
        
        // Получаем ресурсы
        $stmt = $pdo->prepare("SELECT * FROM topic_resources WHERE topic_id = ? ORDER BY id");
        $stmt->execute([$editTopic['id']]);
        $editTopic['resources'] = $stmt->fetchAll();
    }
}

include 'header.php';
?>

<div class="container-fluid px-4">
    <!-- Заголовок и кнопки действий -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Банк тем</h1>
        <div>
            <button type="button" class="btn btn-success" onclick="showAddTopicModal()">
                <i class="bi bi-plus-circle"></i> Добавить тему
            </button>
            <button type="button" class="btn btn-warning" onclick="showImportModal()" <?php echo !isAdmin() ? 'disabled' : ''; ?>>
                <i class="bi bi-upload"></i> Импорт
            </button>
            <div class="btn-group">
                <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Экспорт
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="?export=csv">CSV</a></li>
                    <li><a class="dropdown-item" href="?export=json">JSON</a></li>
                </ul>
            </div>
            <?php if (isAdmin()): ?>
            <button type="button" class="btn btn-danger" onclick="clearAllTopics()">
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
                <div class="col-md-4">
                    <label class="form-label">Поиск по названию</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Введите название темы">
                </div>
                <div class="col-md-3">
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
                <div class="col-md-3">
                    <label class="form-label">Метка</label>
                    <select class="form-select" name="tag">
                        <option value="">Все метки</option>
                        <?php foreach ($allTags as $tag): ?>
                            <option value="<?php echo $tag['id']; ?>" <?php echo $tagFilter == $tag['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tag['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Применить
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Сетка тем -->
    <div class="row" id="topicsGrid">
        <?php if (empty($topics)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Нет тем. Нажмите "Добавить тему", чтобы создать первую тему.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($topics as $topic): ?>
                <div class="col-xl-4 col-lg-6 col-md-6 mb-4" id="topic_<?php echo $topic['id']; ?>">
                    <div class="card h-100 topic-card">
                        <div class="card-header d-flex justify-content-between align-items-center" 
                             style="<?php echo $topic['category_color'] ? 'border-left: 4px solid ' . $topic['category_color'] : ''; ?>">
                            <h6 class="mb-0 fw-bold">
                                <?php echo htmlspecialchars($topic['name']); ?>
                                <?php if ($topic['lessons_count'] > 0): ?>
                                    <span class="badge bg-info ms-2"><?php echo $topic['lessons_count']; ?> зан.</span>
                                <?php endif; ?>
                            </h6>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button class="dropdown-item" onclick="editTopic(<?php echo $topic['id']; ?>)">
                                            <i class="bi bi-pencil"></i> Редактировать
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item text-danger" onclick="deleteTopic(<?php echo $topic['id']; ?>)">
                                            <i class="bi bi-trash"></i> Удалить
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($topic['category_name']): ?>
                                <div class="mb-2">
                                    <span class="badge" style="background-color: <?php echo $topic['category_color']; ?>; color: white;">
                                        <i class="bi bi-folder"></i> <?php echo htmlspecialchars($topic['category_name']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($topic['tag_names']): ?>
                                <div class="mb-3">
                                    <?php 
                                    $tagList = explode(',', $topic['tag_names']);
                                    foreach ($tagList as $tagName): 
                                        $tagName = trim($tagName);
                                        if ($tagName):
                                    ?>
                                        <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($tagName); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($topic['resources'])): ?>
                                <div class="mt-3">
                                    <small class="text-muted fw-bold">Ресурсы:</small>
                                    <ul class="list-unstyled mt-2">
                                        <?php foreach (array_slice($topic['resources'], 0, 3) as $resource): ?>
                                            <li class="mb-1">
                                                <a href="<?php echo htmlspecialchars($resource['url']); ?>" target="_blank" class="text-decoration-none small">
                                                    <i class="bi bi-link"></i> 
                                                    <?php echo $resource['description'] ? htmlspecialchars($resource['description']) : 'Ссылка'; ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if (count($topic['resources']) > 3): ?>
                                            <li class="text-muted small">и еще <?php echo count($topic['resources']) - 3; ?>...</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-calendar"></i> <?php echo date('d.m.Y', strtotime($topic['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно для добавления/редактирования темы -->
<div class="modal fade" id="topicModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="topicModalTitle">Добавление темы</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="topics.php" id="topicForm">
                <input type="hidden" name="topic_id" id="topic_id" value="<?php echo $editTopic['id'] ?? ''; ?>">
                <div class="modal-body">
                    <!-- Основная информация -->
                    <div class="mb-3">
                        <label class="form-label">Название темы <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="topic_name" 
                               value="<?php echo htmlspecialchars($editTopic['name'] ?? ''); ?>" required maxlength="255">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Категория</label>
                        <select class="form-select" name="category_id" id="topic_category">
                            <option value="">Без категории</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" 
                                        style="color: <?php echo $cat['color']; ?>;"
                                        <?php echo ($editTopic['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Метки -->
                    <div class="mb-3">
                        <label class="form-label">Метки</label>
                        <div class="row g-2" id="tagsContainer">
                            <?php foreach ($allTags as $tag): ?>
                                <?php 
                                $colorClass = '';
                                switch($tag['color']) {
                                    case 'green': $colorClass = 'bg-success'; break;
                                    case 'red': $colorClass = 'bg-danger'; break;
                                    case 'blue': $colorClass = 'bg-info'; break;
                                    default: $colorClass = 'bg-secondary';
                                }
                                $checked = ($editTopic && in_array($tag['id'], $editTopic['selected_tags'] ?? [])) ? 'checked' : '';
                                ?>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="tags[]" 
                                               value="<?php echo $tag['id']; ?>" id="tag_<?php echo $tag['id']; ?>" <?php echo $checked; ?>>
                                        <label class="form-check-label" for="tag_<?php echo $tag['id']; ?>">
                                            <span class="badge <?php echo $colorClass; ?>"><?php echo htmlspecialchars($tag['name']); ?></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Ресурсы -->
                    <div class="mb-3">
                        <label class="form-label">Ресурсы (ссылки)</label>
                        <div id="resourcesContainer">
                            <?php if ($editTopic && !empty($editTopic['resources'])): ?>
                                <?php foreach ($editTopic['resources'] as $index => $resource): ?>
                                    <div class="row mb-2 resource-row">
                                        <div class="col-md-5">
                                            <input type="url" class="form-control" name="resources[<?php echo $index; ?>][url]" 
                                                   value="<?php echo htmlspecialchars($resource['url']); ?>" placeholder="https://...">
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" name="resources[<?php echo $index; ?>][description]" 
                                                   value="<?php echo htmlspecialchars($resource['description']); ?>" placeholder="Описание">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-danger btn-sm" onclick="removeResource(this)">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addResource()">
                            <i class="bi bi-plus-circle"></i> Добавить ресурс
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="save_topic" class="btn btn-primary">Сохранить тему</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно для импорта -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Импорт тем</h5>
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
                    <form method="POST" action="topics.php" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Родительская категория (для всех тем)</label>
                            <select class="form-select" name="parent_category_id">
                                <option value="">Без категории</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Если в CSV не указана категория, будет использована эта</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Выберите CSV файл</label>
                            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Формат файла:</h6>
                            <p>CSV с разделителем ; или ,</p>
                            <p>Обязательная колонка: <strong>name</strong></p>
                            <p>Опционально: category, tags</p>
                            <pre>name;category;tags
Математика;Точные науки;алгебра,геометрия
Физика;Точные науки;механика</pre>
                        </div>
                        <button type="submit" name="import_topics" class="btn btn-success w-100">Импортировать CSV</button>
                    </form>
                </div>
                
                <!-- JSON импорт -->
                <div class="tab-pane fade p-3" id="json" role="tabpanel">
                    <form method="POST" action="topics.php" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Выберите JSON файл</label>
                            <input type="file" class="form-control" name="json_file" accept=".json" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Формат файла:</h6>
                            <pre>[
  {
    "name": "Математика",
    "category": "Точные науки",
    "tags": ["алгебра", "геометрия"],
    "resources": [
      {"url": "https://...", "description": "Учебник"}
    ]
  }
]</pre>
                        </div>
                        <button type="submit" name="import_topics_json" class="btn btn-success w-100">Импортировать JSON</button>
                    </form>
                </div>
            </div>
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
                <p>Вы уверены, что хотите удалить <strong>все</strong> темы?</p>
                <p class="text-danger">Это действие нельзя отменить!</p>
                <p>Темы будут удалены только если они не используются в занятиях.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <form method="POST" action="topics.php" style="display: inline;">
                    <button type="submit" name="clear_all_topics" class="btn btn-danger">Очистить все</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Скрытая форма для удаления -->
<form method="POST" id="deleteTopicForm" style="display: none;">
    <input type="hidden" name="topic_id" id="delete_topic_id">
    <input type="hidden" name="delete_topic">
</form>

<style>
.topic-card {
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}
.topic-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}
.topic-card .card-header {
    padding: 0.75rem 1rem;
}
.resource-row {
    transition: background-color 0.2s;
}
.resource-row:hover {
    background-color: #f8f9fa;
}
</style>

<script>
let resourceIndex = <?php echo ($editTopic && !empty($editTopic['resources'])) ? count($editTopic['resources']) : 0; ?>;

// Функции для работы с темами
function showAddTopicModal() {
    document.getElementById('topic_id').value = '';
    document.getElementById('topic_name').value = '';
    document.getElementById('topic_category').value = '';
    document.querySelectorAll('input[name="tags[]"]').forEach(cb => cb.checked = false);
    document.getElementById('resourcesContainer').innerHTML = '';
    resourceIndex = 0;
    document.getElementById('topicModalTitle').textContent = 'Добавление темы';
    
    var modal = new bootstrap.Modal(document.getElementById('topicModal'));
    modal.show();
}

function editTopic(id) {
    window.location.href = 'topics.php?edit=' + id;
}

function deleteTopic(id) {
    if (confirm('Вы уверены, что хотите удалить эту тему?')) {
        document.getElementById('delete_topic_id').value = id;
        document.getElementById('deleteTopicForm').submit();
    }
}

function clearAllTopics() {
    var modal = new bootstrap.Modal(document.getElementById('clearAllModal'));
    modal.show();
}

function showImportModal() {
    var modal = new bootstrap.Modal(document.getElementById('importModal'));
    modal.show();
}

// Функции для работы с ресурсами
function addResource() {
    const container = document.getElementById('resourcesContainer');
    const newRow = document.createElement('div');
    newRow.className = 'row mb-2 resource-row';
    newRow.innerHTML = `
        <div class="col-md-5">
            <input type="url" class="form-control" name="resources[${resourceIndex}][url]" placeholder="https://...">
        </div>
        <div class="col-md-6">
            <input type="text" class="form-control" name="resources[${resourceIndex}][description]" placeholder="Описание">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm" onclick="removeResource(this)">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `;
    container.appendChild(newRow);
    resourceIndex++;
}

function removeResource(button) {
    button.closest('.resource-row').remove();
}

// Показываем модальное окно если есть редактируемая тема
<?php if ($editTopic): ?>
document.addEventListener('DOMContentLoaded', function() {
    var topicModal = new bootstrap.Modal(document.getElementById('topicModal'));
    topicModal.show();
    
    <?php if ($message): ?>
    // Если есть сообщение, показываем его после загрузки
    setTimeout(function() {
        const alert = document.querySelector('.alert-success');
        if (alert) alert.style.display = 'block';
    }, 500);
    <?php endif; ?>
});
<?php endif; ?>

// Клик по карточке темы
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.topic-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown') && !e.target.closest('button') && !e.target.closest('a')) {
                const id = this.closest('[id^="topic_"]').id.replace('topic_', '');
                editTopic(id);
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