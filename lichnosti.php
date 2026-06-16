<!-- подключение к бд -->
<?php
require_once 'config.php';
// запуск сессии для проверки авторизованного пользователя
session_start();

// блок обработки AJAX-ЗАПРОСОВ
if (isset($_GET['embed']) && $_GET['embed'] == 1) {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        // запрос на получение данных личностей 
        $sql = "SELECT 
                    l.name, l.birth_year, l.death_year, l.description, l.image_url,
                    p.name AS period_name,
                    c.name AS category_name
                FROM lichnosti l
                LEFT JOIN periods p ON l.ID_period = p.ID_period
                LEFT JOIN lich_categories c ON l.ID_lich_category = c.ID_lich_category
                WHERE l.ID_lichnost = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $years = '';
            if ($row['birth_year']) {
                $years = $row['birth_year'] . '–' . ($row['death_year'] ? $row['death_year'] : '...') . ' гг.';
            } else {
                $years = 'Годы неизвестны';
            }
            // данные для модального окна
            echo '<div class="person-modal-content" style="padding:20px;">';
            if (!empty($row['image_url'])) {
                echo '<img src="' . htmlspecialchars($row['image_url']) . '" alt="' . htmlspecialchars($row['name']) . '" style="float:right; max-width:200px; border-radius:12px; margin-left:20px;">';
            }
            echo '<h2>' . htmlspecialchars($row['name']) . '</h2>';
            echo '<p><strong>Эпоха:</strong> ' . htmlspecialchars($row['period_name'] ?? 'Не указана') . '</p>';
            echo '<p><strong>Годы:</strong> ' . htmlspecialchars($years) . '</p>';
            if ($row['category_name']) {
                echo '<p><strong>Категория:</strong> ' . htmlspecialchars($row['category_name']) . '</p>';
            }
            echo '<p><strong>Описание:</strong></p>';
            // nl2br для переносов строк, htmlspecialchars для безопасности
            echo '<p>' . nl2br(htmlspecialchars($row['description'] ?? '')) . '</p>';
            echo '</div>';
        } else {
            echo '<p>Личность не найдена.</p>';
        }
    } else {
        echo '<p>Неверный ID.</p>';
    }
    exit;
}

// СПИСОК ЛИЧНОСТЕЙ С ФИЛЬТРАМИ

// значения фильтров
$filter_period = isset($_GET['period']) ? (int)$_GET['period'] : 0;
$filter_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$filter_search = isset($_GET['search'])  ? trim($_GET['search'])  : '';

$sql = "
    SELECT 
        l.ID_lichnost,
        l.name,
        l.birth_year,
        l.death_year,
        l.description,
        l.image_url,
        p.name AS period_name,
        c.name AS category_name
    FROM lichnosti l
    LEFT JOIN periods p ON l.ID_period = p.ID_period
    LEFT JOIN lich_categories c ON l.ID_lich_category = c.ID_lich_category
    WHERE 1=1
";

// массив
$params = [];
$types  = '';

// условия фильтрации
if ($filter_period > 0) {
    $sql .= " AND l.ID_period = ?";
    $params[] = $filter_period;
    $types .= 'i';
}
if ($filter_category > 0) {
    $sql .= " AND l.ID_lich_category = ?";
    $params[] = $filter_category;
    $types .= 'i';
}
if (!empty($filter_search)) {
    $sql .= " AND l.name LIKE ?";
    $params[] = '%' . $filter_search . '%';
    $types .= 's';
}
// сортировка по имени
$sql .= " ORDER BY l.name ASC";

// выполнение запроса
$stmt = $conn->prepare($sql);
if (!$stmt) die('Ошибка запроса: ' . $conn->error);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// сохранение личностей в массив
$persons = [];
while ($row = $result->fetch_assoc()) {
    $persons[] = $row;
}

// список эпох
$periods = [];
$period_res = $conn->query("SELECT ID_period, name FROM periods ORDER BY name");
while ($row = $period_res->fetch_assoc()) $periods[] = $row;

// список категорий
$categories = [];
$cat_res = $conn->query("SELECT ID_lich_category, name FROM lich_categories ORDER BY name");
while ($row = $cat_res->fetch_assoc()) $categories[] = $row;
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HistoRIZZ | Исторические личности</title>

    <link rel="stylesheet" href="css/lichnosti.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/footer.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&family=Prosto+One&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prosto+One&display=swap" rel="stylesheet">

    <link rel="icon" href="images/favicon.svg">
    <!-- подключение jQuery для AJAX-запросов и манипуляции с DOM -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<!-- подключение навигации -->
<?php include 'header.php'; ?>

<main>
    <div class="container">
        <div class="hero-text">
            <h1 class="hero-title">Исторические личности</h1>
            <p class="hero-subtitle">Узнай больше о тех, кто менял мир</p>
        </div>

        <!-- фильтры -->
        <form method="GET" action="" id="filterForm">
            <div class="filters">
                <div class="filter-group">
                    <label>Фильтр по эпохе:</label>
                    <select name="period" class="filter-select">
                        <option value="0">Все эпохи</option>
                        <?php foreach ($periods as $p): ?>
                            <option value="<?= $p['ID_period'] ?>" <?= ($filter_period == $p['ID_period']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Фильтр по категории:</label>
                    <select name="category" class="filter-select">
                        <option value="0">Все</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['ID_lich_category'] ?>" <?= ($filter_category == $c['ID_lich_category']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Поиск по имени...</label>
                    <input type="text" name="search" class="filter-search" 
                           placeholder="Например, Цезарь" value="<?= htmlspecialchars($filter_search) ?>">
                </div>
            </div>
        </form>
        
        <!--личности -->
        <div class="persons-grid">
            <?php if (count($persons) > 0): ?>
                <?php foreach ($persons as $person): 
                    $years = '';
                    if ($person['birth_year']) {
                        $birth = $person['birth_year'];
                        $death = $person['death_year'] ? $person['death_year'] : '...';
                        $years = $birth . '–' . $death . ' гг.';
                    } else {
                        $years = 'Годы неизвестны';
                    }
                    // деф картинка
                    $img = !empty($person['image_url']) ? $person['image_url'] : 'images/lich/default.jpg';
                    
                    // переход на страницу толстого
                    if ($person['name'] === 'Лев Толстой') {
                        $link = 'lev_tolstoi.html';
                        $data_id = '';
                    } else {
                        $link = '#';
                        $data_id = 'data-id="' . $person['ID_lichnost'] . '"';
                    }
                    $category_display = !empty($person['category_name']) ? $person['category_name'] : '';
                ?>

                <!-- наполнение карточки из таблицы -->
                <div class="person-card">
                    <div class="person-photo">
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($person['name']) ?>">
                    </div>
                    <div class="person-info">
                        <h3 class="person-name"><?= htmlspecialchars($person['name']) ?></h3>
                        <div class="person-years"><?= htmlspecialchars($years) ?></div>
                        <?php if ($category_display): ?>
                            <div class="person-category"><?= htmlspecialchars($category_display) ?></div>
                        <?php endif; ?>
                        <p class="person-description"><?= htmlspecialchars($person['description'] ?? '') ?></p>
                        <!-- Сама кнопка "читать →" -->
                        <a href="<?= $link ?>" class="person-btn" <?= $data_id ?>>читать →</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-data">Личности не найдены. Попробуйте изменить фильтры.</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- ========== ФУТЕР ========== -->
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

<!-- модальные окна регситрации и авторизации -->
<?php include 'login_reg_modal.php'; ?>

<!-- модальное окно для личностей -->
<div id="personModalOverlay" class="modal-overlay">
    <div class="modal" style="width: 700px; max-width: 95%;">
        <span class="modal-close">&times;</span>
        <div id="personModalBody"></div>
    </div>
</div>

<script src="js/auth.js"></script>
<script>
$(document).ready(function() {
    // автоотправка фильтров
    $('#filterForm select, #filterForm input').on('change keyup', function() {
        clearTimeout(window.filterTimer);
        window.filterTimer = setTimeout(function() {
            $('#filterForm').submit();
        }, 300);
    });

    // кнока читать
    $(document).on('click', '.person-btn', function(e) {
        var href = $(this).attr('href');
        // Если ссылка не на # — разрешаем переход на отдельную страницу
        if (href !== '#') {
            return true;
        }
        // иначе отменяем переход и открываем модалку
        e.preventDefault();
        var id = $(this).data('id');
        if (!id) {
            alert('Ошибка: ID личности не найден');
            return;
        }
        // Показываем спиннер "Загрузка..."
        $('#personModalBody').html('<div style="text-align:center; padding:30px; font-size:18px;">Загрузка...</div>');
        $('#personModalOverlay').addClass('active');

        // отправляем AJAX-запрос на этот же файл
        $.ajax({
            url: 'lichnosti.php',
            type: 'GET',
            data: { embed: 1, id: id },
            dataType: 'html',
            success: function(html) {
                $('#personModalBody').html(html);
            },
            error: function() {
                $('#personModalBody').html('<p style="color:red;">Ошибка загрузки данных.</p>');
            }
        });
    });

    // закрытие модального окна по клику на крестик или на фон
    $(document).on('click', '.modal-close, .modal-overlay', function() {
        $('.modal-overlay').removeClass('active');
    });
});
</script>
</body>
</html>
