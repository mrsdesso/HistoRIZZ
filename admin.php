<?php
//АДМИНИСТРАТИВНАЯ ПАНЕЛЬ

require_once 'config.php';
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: main.php');
    exit;
}

// Проверка роли администратора
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: lk.php');
    exit;
}

// перевод названий таблиц на русскиий
$tableNamesRu = [
    'users' => 'Пользователи',
    'periods' => 'Периоды',
    'lichnosti' => 'Личности',
    'lich_categories' => 'Категории личностей',
    'articles' => 'Статьи',
    'article_categories' => 'Категории статей',
    'article_comments' => 'Комментарии к статьям',
    'article_likes' => 'Лайки статей',
    'quizzes' => 'Викторины',
    'quiz_categories' => 'Категории викторин',
    'questions' => 'Вопросы',
    'answers' => 'Ответы',
    'quiz_ratings' => 'Оценки викторин',
    'user_results' => 'Результаты пользователей',
    'locations' => 'Локации',
    'events' => 'События',
    'bookmarks' => 'Закладки',
    'translations' => 'Переводы'
];

// перевод названий представлений на русскиий
$viewNamesRu = [
    'view_articles_by_category' => 'Статьи по категориям',
    'view_events_by_period' => 'События по периодам',
    'view_locations_by_region' => 'Локации по регионам',
    'view_personalities_by_period' => 'Личности по периодам',
    'view_quizzes_list' => 'Список викторин'
];

// фильтры
$viewFilters = [
    'view_articles_by_category' => [
        'field' => 'category_name',
        'label' => 'Категория',
        'options' => []
    ],
    'view_events_by_period' => [
        'field' => 'period_name',
        'label' => 'Период',
        'options' => []
    ],
    'view_locations_by_region' => [
        'field' => 'region',
        'label' => 'Регион',
        'options' => []
    ],
    'view_personalities_by_period' => [
        'field' => 'period_name',
        'label' => 'Период',
        'options' => []
    ],
    'view_quizzes_list' => [
        'field' => 'category_name',
        'label' => 'Категория',
        'options' => []
    ]
];

// список таблиц
$tables = [];
$result = $conn->query("SHOW FULL TABLES");
while ($row = $result->fetch_row()) {
    $tableName = $row[0];
    $tableType = $row[1];
    if ($tableType !== 'VIEW') {
        $tables[] = $tableName;
    }
}

//  представления для просмотра
$views = [];
$result = $conn->query("SHOW FULL TABLES");
while ($row = $result->fetch_row()) {
    if ($row[1] === 'VIEW') {
        $views[] = $row[0];
    }
}

// обработка действий с таблицами   
$action = isset($_GET['action']) ? $_GET['action'] : '';
$table = isset($_GET['table']) ? $_GET['table'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$filterValue = isset($_GET['filter']) ? $_GET['filter'] : '';
$message = '';
$messageType = '';

// фильтры для представленй
foreach ($viewFilters as $viewName => &$filter) {
    if (in_array($viewName, $views)) {
        $sql = "SELECT DISTINCT {$filter['field']} FROM `$viewName` WHERE {$filter['field']} IS NOT NULL ORDER BY {$filter['field']}";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (!empty($row[$filter['field']])) {
                    $filter['options'][] = $row[$filter['field']];
                }
            }
        }
    }
}
unset($filter);

// данные для редактирования
$editData = null;
if ($action === 'edit' && $table && $id > 0) {
    $pkResult = $conn->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
    $pkRow = $pkResult->fetch_assoc();
    $primaryKey = $pkRow ? $pkRow['Column_name'] : 'ID_' . $table;
    
    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$primaryKey` = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Удаление записи
if ($action === 'delete' && $table && $id > 0) {
    $pkResult = $conn->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
    $pkRow = $pkResult->fetch_assoc();
    $primaryKey = $pkRow ? $pkRow['Column_name'] : 'ID_' . $table;
    
    $stmt = $conn->prepare("DELETE FROM `$table` WHERE `$primaryKey` = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = 'Запись успешно удалена!';
        $messageType = 'success';
    } else {
        $message = 'Ошибка удаления: ' . $stmt->error;
        $messageType = 'error';
    }
    $stmt->close();
}

// Добавление записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_record']) && $table) {
    $columns = [];
    $colResult = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($col = $colResult->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
    
    $fields = [];
    $values = [];
    $placeholders = [];
    $types = '';
    
    foreach ($columns as $col) {
        if (strpos($col, 'ID_') === 0 && $col !== 'ID_user' && $col !== 'ID_period' && $col !== 'ID_article') {
            continue;
        }
        if (isset($_POST[$col]) && $_POST[$col] !== '') {
            $fields[] = $col;
            $values[] = $_POST[$col];
            $placeholders[] = '?';
            if (is_numeric($_POST[$col])) {
                $types .= 'i';
            } else {
                $types .= 's';
            }
        }
    }
    
    if (!empty($fields)) {
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$values);
            if ($stmt->execute()) {
                $message = 'Запись успешно добавлена!';
                $messageType = 'success';
            } else {
                $message = 'Ошибка добавления: ' . $stmt->error;
                $messageType = 'error';
            }
            $stmt->close();
        } else {
            $message = 'Ошибка подготовки запроса: ' . $conn->error;
            $messageType = 'error';
        }
    }
}

// Обновление записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_record']) && $table && $id > 0) {
    $pkResult = $conn->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
    $pkRow = $pkResult->fetch_assoc();
    $primaryKey = $pkRow ? $pkRow['Column_name'] : 'ID_' . $table;
    
    $columns = [];
    $colResult = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($col = $colResult->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
    
    $setParts = [];
    $values = [];
    $types = '';
    
    foreach ($columns as $col) {
        if ($col === $primaryKey) continue;
        if (isset($_POST[$col]) && $_POST[$col] !== '') {
            $setParts[] = "`$col` = ?";
            $values[] = $_POST[$col];
            if (is_numeric($_POST[$col])) {
                $types .= 'i';
            } else {
                $types .= 's';
            }
        }
    }
    
    $values[] = $id;
    $types .= 'i';
    
    if (!empty($setParts)) {
        $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE `$primaryKey` = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$values);
            if ($stmt->execute()) {
                $message = 'Запись успешно обновлена!';
                $messageType = 'success';
            } else {
                $message = 'Ошибка обновления: ' . $stmt->error;
                $messageType = 'error';
            }
            $stmt->close();
        } else {
            $message = 'Ошибка подготовки запроса: ' . $conn->error;
            $messageType = 'error';
        }
    }
}

// Получение данных для выбранной таблицы
$tableData = [];
$columns = [];
$primaryKey = 'ID_' . $table;

if ($table) {
    // Проверяем, является ли таблица представлением
    $isView = in_array($table, $views);
    
    $colResult = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($col = $colResult->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
    
    $pkResult = $conn->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
    $pkRow = $pkResult->fetch_assoc();
    $primaryKey = $pkRow ? $pkRow['Column_name'] : 'ID_' . $table;
    
    $sql = "SELECT * FROM `$table`";
    
    // Если это представление и есть фильтр
    if ($isView && isset($viewFilters[$table]) && !empty($filterValue)) {
        $filterField = $viewFilters[$table]['field'];
        $sql .= " WHERE `$filterField` = '" . $conn->real_escape_string($filterValue) . "'";
    }
    
    $sql .= " LIMIT 50";
    
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tableData[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | HistoRIZZ</title>
    
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/footer.css">
    <link rel="stylesheet" href="css/admin.css">
    

    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&family=Prosto+One&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prosto+One&display=swap" rel="stylesheet">

    <link rel="icon" href="images/favicon.svg">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<?php include 'header.php'; ?>

<main>
    <div class="admin-wrapper">

        <!-- боковое меню -->
        <aside class="admin-sidebar">
            <h2>Admin Panel</h2>
            <div class="sidebar-header">
                <h3>МЕНЮ</h3>
            </div>
            <nav class="sidebar-nav">
                <a href="admin.php" <?= !isset($_GET['table']) && !isset($_GET['views']) ? 'class="active"' : '' ?>>Дашборд</a>
                <?php foreach ($tables as $t): 
                    $displayName = $tableNamesRu[$t] ?? ucfirst($t);
                ?>
                    <a href="admin.php?table=<?= $t ?>" <?= (isset($_GET['table']) && $_GET['table'] === $t) ? 'class="active"' : '' ?>>
                        <?= htmlspecialchars($displayName) ?>
                    </a>
                <?php endforeach; ?>
                <a href="admin.php?views=1" <?= isset($_GET['views']) ? 'class="active"' : '' ?>>Представления</a>
            </nav>
        </aside>
            
        <div class="admin-content">
            <div class="admin-header">
                <div class="admin-header-left">
                    <?php if (isset($_GET['views'])): ?>
                        <h1>Представления</h1>
                    <?php elseif (isset($_GET['table']) && isset($viewNamesRu[$_GET['table']])): ?>
                        <h1><?= htmlspecialchars($viewNamesRu[$_GET['table']]) ?></h1>
                    <?php elseif (isset($_GET['table']) && isset($tableNamesRu[$_GET['table']])): ?>
                        <h1><?= htmlspecialchars($tableNamesRu[$_GET['table']]) ?></h1>
                    <?php elseif (isset($_GET['table'])): ?>
                        <h1><?= ucfirst(htmlspecialchars($_GET['table'])) ?></h1>
                    <?php else: ?>
                        <h1>Дашборд</h1>
                    <?php endif; ?>
                    <div class="bio-divider"></div>
                </div>
                <span class="admin-date"><?= date('d.m.Y') ?></span>
            </div>

            <?php if ($message): ?>
                <div style="padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; background: <?= $messageType === 'success' ? 'rgba(76,175,80,0.15)' : 'rgba(227,38,54,0.15)'; ?>; border: 1px solid <?= $messageType === 'success' ? '#4caf50' : '#e32636'; ?>; color: <?= $messageType === 'success' ? '#4caf50' : '#e32636'; ?>;">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['views'])): ?>
                <!-- представлений -->
                <div style="background: #1A1A2A; border-radius: 16px; padding: 24px; border: 1px solid rgba(233,103,43,0.1);">
                    <h2 style="font-family: var(--font-heading); font-size: 22px; color: var(--text-white); margin: 0 0 20px 0;">Список представлений</h2>
                    <?php foreach ($views as $view): 
                        $displayName = $viewNamesRu[$view] ?? $view;
                    ?>
                        <div style="padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--text-white); font-family: var(--font-main);"><?= htmlspecialchars($displayName) ?></span>
                            <a href="admin.php?table=<?= $view ?>" style="color: var(--accent-orange); text-decoration: none; font-size: 14px;">→ Просмотр</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!isset($_GET['table'])): ?>
                <!-- дашборд -->
                <?php
                $stats = [];
                foreach ($tables as $t) {
                    $result = $conn->query("SELECT COUNT(*) as count FROM `$t`");
                    $stats[$t] = $result ? $result->fetch_assoc()['count'] : 0;
                }
                ?>
                <div class="stats-grid">
                    <?php foreach ($tables as $t): 
                        $displayName = $tableNamesRu[$t] ?? ucfirst($t);
                    ?>
                        <a href="admin.php?table=<?= $t ?>" class="stat-card" style="text-decoration: none; display: block;">
                            <div class="stat-number"><?= number_format($stats[$t] ?? 0) ?></div>
                            <div class="stat-label"><?= htmlspecialchars($displayName) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: 
                // управление таблицами
                $table = $_GET['table'];
                $isView = in_array($table, $views);
                $tableDisplayName = $viewNamesRu[$table] ?? $tableNamesRu[$table] ?? ucfirst($table);
            ?>
                
                <!-- Фильтр для представлений -->
                <?php if ($isView && isset($viewFilters[$table]) && !empty($viewFilters[$table]['options'])): ?>
                    <div style="background: #1A1A2A; border-radius: 16px; padding: 16px 20px; margin-bottom: 20px; border: 1px solid rgba(233,103,43,0.1); display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                        <label style="color: var(--text-gray); font-weight: 500; font-size: 14px;">Фильтр по <?= $viewFilters[$table]['label'] ?>:</label>
                        <select onchange="window.location.href='admin.php?table=<?= $table ?>&filter='+this.value" style="padding: 8px 16px; background: rgba(0,0,0,0.4); border: 1px solid rgba(233,103,43,0.5); border-radius: 40px; color: var(--text-white); font-family: var(--font-main); outline: none;">
                            <option value="">Все</option>
                            <?php foreach ($viewFilters[$table]['options'] as $option): ?>
                                <option value="<?= htmlspecialchars($option) ?>" <?= $filterValue === $option ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($filterValue): ?>
                            <a href="admin.php?table=<?= $table ?>" style="color: var(--accent-orange); text-decoration: none; font-size: 14px;">✕ Сбросить</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$isView): ?>
                <!-- Кнопка добавления (только для таблиц) -->
                <div style="margin-bottom: 20px;">
                    <button onclick="document.getElementById('addForm').style.display='block'" class="btn-save" style="padding: 10px 24px; background: var(--accent-orange); color: #fff; border: none; border-radius: 40px; cursor: pointer; font-weight: 600;">
                        + Добавить запись
                    </button>
                </div>

                <!-- Форма добавления (только для таблиц) -->
                <div id="addForm" style="display: none; background: #1A1A2A; border-radius: 16px; padding: 24px; margin-bottom: 24px; border: 1px solid rgba(233,103,43,0.1);">
                    <h3 style="color: var(--text-white); margin-bottom: 16px;">Добавить запись в <?= htmlspecialchars($tableDisplayName) ?></h3>
                    <form method="POST">
                        <input type="hidden" name="add_record" value="1">
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px;">
                            <?php
                            $colResult = $conn->query("SHOW COLUMNS FROM `$table`");
                            $colNames = [];
                            while ($col = $colResult->fetch_assoc()) {
                                $colNames[] = $col['Field'];
                            }
                            $pkResult = $conn->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
                            $pkRow = $pkResult->fetch_assoc();
                            $primaryKey = $pkRow ? $pkRow['Column_name'] : 'ID_' . $table;
                            
                            foreach ($colNames as $col):
                                if (strpos($col, 'ID_') === 0 && $col !== 'ID_user' && $col !== 'ID_period' && $col !== 'ID_article' && $col !== 'ID_quiz' && $col !== 'ID_question' && $col !== 'ID_lichnost' && $col !== 'ID_location' && $col !== 'ID_event') continue;
                                if ($col === 'created_at' || $col === 'date_taken' || $col === 'percent') continue;
                            ?>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <label style="color: var(--text-gray); font-size: 13px;"><?= htmlspecialchars($col) ?></label>
                                    <input type="text" name="<?= htmlspecialchars($col) ?>" placeholder="<?= htmlspecialchars($col) ?>" style="padding: 8px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(233,103,43,0.3); border-radius: 8px; color: white; font-family: var(--font-main);">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 16px; display: flex; gap: 12px;">
                            <button type="submit" style="padding: 10px 24px; background: var(--accent-orange); color: #fff; border: none; border-radius: 40px; cursor: pointer; font-weight: 600;">Сохранить</button>
                            <button type="button" onclick="document.getElementById('addForm').style.display='none'" style="padding: 10px 24px; background: transparent; border: 1px solid var(--text-gray); color: var(--text-gray); border-radius: 40px; cursor: pointer;">Отмена</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Таблица данных -->
                <div class="table-wrapper" style="overflow-x: auto;">
                    <?php if ($isView): ?>
                        <div style="margin-bottom: 16px; padding: 12px 16px; background: rgba(0,240,255,0.05); border-radius: 8px; border-left: 3px solid #00F0FF;">
                            <span style="color: var(--text-gray); font-size: 14px;">Это представление. Данные доступны только для просмотра.</span>
                        </div>
                    <?php endif; ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <?php foreach ($columns as $col): ?>
                                    <th><?= htmlspecialchars($col) ?></th>
                                <?php endforeach; ?>
                                <?php if (!$isView): ?>
                                    <th>Действия</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($tableData)): ?>
                                <?php foreach ($tableData as $row): ?>
                                    <tr>
                                        <?php foreach ($columns as $col): ?>
                                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?php 
                                                $val = $row[$col];
                                                if (is_null($val)) {
                                                    echo '<span style="color: var(--text-muted);">NULL</span>';
                                                } elseif (strlen($val) > 50) {
                                                    echo htmlspecialchars(substr($val, 0, 50)) . '...';
                                                } else {
                                                    echo htmlspecialchars($val);
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <?php if (!$isView): ?>
                                            <td>
                                                <a href="admin.php?table=<?= $table ?>&action=edit&id=<?= $row[$primaryKey] ?>" class="action-btn edit-btn">✏️</a>
                                                <a href="admin.php?table=<?= $table ?>&action=delete&id=<?= $row[$primaryKey] ?>" class="action-btn delete-btn" onclick="return confirm('Удалить запись?')">🗑️</a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="<?= count($columns) + ($isView ? 0 : 1) ?>" style="text-align:center; color:var(--text-muted); padding:30px;">Нет данных</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Форма редактирования (только для таблиц) -->
                <?php if (!$isView && $editData && $action === 'edit'): ?>
                <div style="background: #1A1A2A; border-radius: 16px; padding: 24px; margin-top: 24px; border: 1px solid rgba(233,103,43,0.1);">
                    <h3 style="color: var(--text-white); margin-bottom: 16px;">Редактировать запись в <?= htmlspecialchars($tableDisplayName) ?></h3>
                    <form method="POST">
                        <input type="hidden" name="edit_record" value="1">
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px;">
                            <?php foreach ($columns as $col): ?>
                                <?php if ($col === $primaryKey) continue; ?>
                                <?php if ($col === 'created_at' || $col === 'date_taken' || $col === 'percent') continue; ?>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <label style="color: var(--text-gray); font-size: 13px;"><?= htmlspecialchars($col) ?></label>
                                    <?php if (strpos($col, 'password') !== false): ?>
                                        <input type="password" name="<?= htmlspecialchars($col) ?>" value="<?= htmlspecialchars($editData[$col] ?? '') ?>" style="padding: 8px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(233,103,43,0.3); border-radius: 8px; color: white; font-family: var(--font-main);">
                                    <?php elseif (strpos($col, 'description') !== false || strpos($col, 'content') !== false || strpos($col, 'comment') !== false): ?>
                                        <textarea name="<?= htmlspecialchars($col) ?>" style="padding: 8px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(233,103,43,0.3); border-radius: 8px; color: white; font-family: var(--font-main); min-height: 80px;"><?= htmlspecialchars($editData[$col] ?? '') ?></textarea>
                                    <?php else: ?>
                                        <input type="text" name="<?= htmlspecialchars($col) ?>" value="<?= htmlspecialchars($editData[$col] ?? '') ?>" style="padding: 8px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(233,103,43,0.3); border-radius: 8px; color: white; font-family: var(--font-main);">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 16px; display: flex; gap: 12px;">
                            <button type="submit" style="padding: 10px 24px; background: var(--accent-orange); color: #fff; border: none; border-radius: 40px; cursor: pointer; font-weight: 600;">Сохранить изменения</button>
                            <a href="admin.php?table=<?= $table ?>" style="padding: 10px 24px; background: transparent; border: 1px solid var(--text-gray); color: var(--text-gray); border-radius: 40px; text-decoration: none;">Отмена</a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>
</main>

<!-- Футер -->
<footer class="footer">
    <div class="container footer-inner">
        <img src="images/logo.png" alt="HistoRIZZ" class="footer-logo">
        <p class="copyright">© 2026 HistoRIZZ — история с характером</p>
        <div class="footer-contacts">
            <a href="mailto:hello@historizz.ru">📧 hello@historizz.ru</a> |
            <a href="#">📱 @historizz</a> |
            <a href="#">🎵 #историясхарактером</a>
        </div>
    </div>
</footer>
</body>
</html>
