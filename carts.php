<?php
// ============================================================
// carts.php — СТРАНИЦА ИНТЕРАКТИВНЫХ КАРТ
// ============================================================
require_once 'config.php';
session_start();

// ========== ПОЛУЧАЕМ СПИСОК ЭПОХ ДЛЯ ФИЛЬТРА ==========
$periods = [];
$period_res = $conn->query("SELECT ID_period, name FROM periods ORDER BY name");
if ($period_res) {
    while ($row = $period_res->fetch_assoc()) {
        $periods[] = $row;
    }
}

// ========== ПОЛУЧАЕМ ИСТОРИЧЕСКИЕ МЕСТА ==========
$locations = [];
$table_check = $conn->query("SHOW TABLES LIKE 'locations'");

if ($table_check && $table_check->num_rows > 0) {
    $sql = "SELECT 
                l.ID_location,
                l.name,
                l.region,
                l.description,
                l.image_url,
                p.name AS period_name
            FROM locations l
            LEFT JOIN periods p ON l.ID_period = p.ID_period
            ORDER BY l.name ASC
            LIMIT 6";
    
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $locations[] = $row;
        }
    }
}

// Популярные места — берём первые 4 из БД
$popular_places = array_slice($locations, 0, 4);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HistoRIZZ | Карты</title>
    
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/footer.css">
    <link rel="stylesheet" href="css/carts.css">
    
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
    <div class="container">

        <!-- ===== ЗАГОЛОВОК ===== -->
        <div class="hero-text">
            <h1 class="hero-title">Интерактивная карта</h1>
            <p class="hero-subtitle">Путешествуй по местам великих событий</p>
        </div>

        <!-- ===== ФИЛЬТРЫ ===== -->
        <div class="filters">
            <div class="filter-group">
                <label for="period">Фильтр по эпохе:</label>
                <select id="period" class="filter-select">
                    <option value="0">Все эпохи</option>
                    <?php foreach ($periods as $p): ?>
                        <option value="<?= $p['ID_period'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="type">Фильтр по типу места:</label>
                <select id="type" class="filter-select">
                    <option value="0">Все места</option>
                    <option value="city">Города</option>
                    <option value="monument">Памятники</option>
                    <option value="battlefield">Поля сражений</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="search">Поиск по региону</label>
                <input type="text" id="search" class="filter-search" placeholder="Например, Япония">
            </div>
        </div>

        <!-- ===== КАРТА (заглушка) ===== -->
        <div class="map-placeholder">
            <div class="map-overlay">
                <span class="map-icon">🗺️</span>
                <p class="map-text">Интерактивная карта загружается...</p>
                <p class="map-subtext">Здесь будет отображаться карта с историческими местами</p>
            </div>
        </div>

        <!-- ===== СПИСОК МЕСТ (ИЗОБРАЖЕНИЕ КАК ФОН) ===== -->
        <?php if (!empty($locations)): ?>
        <h2 class="section-title">Популярные исторические места</h2>
        <div class="locations-grid">
            <?php foreach ($locations as $loc): ?>
                <div class="location-card" style="background-image: url('<?= htmlspecialchars($loc['image_url'] ?? '') ?>');">
                    <div class="location-info">
                        <div class="location-name"><?= htmlspecialchars($loc['name']) ?></div>
                        <div class="location-country"><?= htmlspecialchars($loc['region'] ?? '') ?>, <?= htmlspecialchars($loc['period_name'] ?? '') ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="locations-empty">
            <p style="text-align: center; color: var(--text-gray); padding: 40px 0; font-size: 18px;">
                🏛️ Исторические места будут добавлены скоро
            </p>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- ФУТЕР -->
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

<!-- модальные окна регистрации и авторизации -->
<?php include 'login_reg_modal.php'; ?>

<script src="js/auth.js"></script>
<script>
$(document).ready(function() {
    // ===== ЗАКРЫТИЕ МОДАЛЬНЫХ ОКОН =====
    $('.modal-close').click(function(e) {
        e.stopPropagation();
        $(this).closest('.modal-overlay').removeClass('active');
    });

    $('.modal-overlay').click(function(e) {
        if (e.target === this) {
            $(this).removeClass('active');
        }
    });

    $('.modal').click(function(e) {
        e.stopPropagation();
    });

    // ===== ФИЛЬТРАЦИЯ =====
    $('#period, #type').change(function() {
        filterLocations();
    });

    $('#search').on('keyup', function() {
        filterLocations();
    });

    function filterLocations() {
        var search = $('#search').val().toLowerCase();

        $('.location-card').each(function() {
            var $card = $(this);
            var region = $card.find('.location-country').text().toLowerCase();
            var name = $card.find('.location-name').text().toLowerCase();
            var show = true;

            if (search && !region.includes(search) && !name.includes(search)) {
                show = false;
            }

            if (show) {
                $card.show();
            } else {
                $card.hide();
            }
        });
    }

    // ===== ОТКРЫТИЕ МОДАЛОК ВХОДА/РЕГИСТРАЦИИ =====
    $(document).on('click', '#loginBtn', function(e) {
        e.preventDefault();
        $('#loginModalOverlay').addClass('active');
    });

    $(document).on('click', '#registerBtn', function(e) {
        e.preventDefault();
        $('#registerModalOverlay').addClass('active');
    });

    $(document).on('click', '#switchToRegister', function(e) {
        e.preventDefault();
        $('#loginModalOverlay').removeClass('active');
        $('#registerModalOverlay').addClass('active');
    });

    $(document).on('click', '#switchToLogin', function(e) {
        e.preventDefault();
        $('#registerModalOverlay').removeClass('active');
        $('#loginModalOverlay').addClass('active');
    });
});
</script>

</body>
</html>