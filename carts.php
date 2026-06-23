<?php
//  интеративные карты


// подключение к бд
require_once 'config.php';
//запуск сессии для проверки авторизованного пользователя
session_start();

// список эпох для фильтра
$periods = [];
$period_res = $conn->query("SELECT ID_period, name FROM periods ORDER BY name");
if ($period_res) {
    while ($row = $period_res->fetch_assoc()) {
        $periods[] = $row;
    }
}

// исторические места
$locations = [];
$table_check = $conn->query("SHOW TABLES LIKE 'locations'");

if ($table_check && $table_check->num_rows > 0) {
    $sql = "SELECT 
                l.ID_location,
                l.name,
                l.region,
                l.description,
                l.image_url,
                l.latitude,
                l.longitude,
                p.name AS period_name,
                p.ID_period
            FROM locations l
            LEFT JOIN periods p ON l.ID_period = p.ID_period
            ORDER BY l.name ASC";
    
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $locations[] = $row;
        }
    }
}

// популярыне места
$popular_places = array_slice($locations, 0, 4);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HistoRIZZ | Карты</title>
    
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/footer.css">
    <link rel="stylesheet" href="css/carts.css">
    
    <!-- для карты -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
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
                <!-- Типы пока статичные, можно будет добавить в БД позже -->
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

        <!-- ===== ИНТЕРАКТИВНАЯ КАРТА ===== -->
        <div id="map" style="width: 100%; height: 500px; border-radius: 24px; border: 2px solid rgba(233,103,43,0.3); margin-bottom: 40px;"></div>

        <!-- ===== ПОПУЛЯРНЫЕ МЕСТА ===== -->
        <section class="popular-places">
            <h2 class="section-title">Популярные исторические места</h2>
            <div class="section-line"></div>
            <?php if (!empty($popular_places)): ?>
            <div class="popular-grid">
                <?php foreach ($popular_places as $place): ?>
                    <div class="popular-card">
                        <div class="popular-image">
                            <?php if (!empty($place['image_url'])): ?>
                                <!-- если есть картинка -->
                                <img src="<?= htmlspecialchars($place['image_url']) ?>" alt="<?= htmlspecialchars($place['name']) ?>">
                            <?php else: ?>
                                <!-- если нет, то икнка -->
                                <div class="no-image">🏛️</div>
                            <?php endif; ?>
                        </div>
                        <div class="popular-content">
                            <div class="popular-title"><?= htmlspecialchars($place['name']) ?></div>
                            <div class="popular-meta"><?= htmlspecialchars($place['region'] ?? '') ?>, <?= htmlspecialchars($place['period_name'] ?? '') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="text-align: center; color: var(--text-gray); padding: 20px 0;">
                Популярные места будут добавлены скоро
            </p>
            <?php endif; ?>
        </section>

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

<!-- модальное окно викторин -->
<div id="quizModalOverlay" class="modal-overlay">
    <div class="modal" style="width: 700px; max-width: 95%;">
        <span class="modal-close">&times;</span>
        <button class="modal-save" id="saveQuizBtn" title="Сохранить викторину">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
            </svg>
        </button>
        <div id="quizModalBody"></div>
    </div>
</div>

<script src="js/auth.js"></script>


<script>
$(document).ready(function() {
    // ИНТЕРАКТИВНАЯ КАРТА (LEAFLET)
    
    // Центр карты по умолчанию — посередине мира
    var centerMap = [20, 10];
    
    // создание карты и привязывание к div с id="map"
    var map = L.map('map', {
        center: centerMap,
        zoom: 2,
        zoomControl: true
    });
    
    // темная тема для карты
    // бесплатные тайлы от CartoDB
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, &copy; CartoDB'
    }).addTo(map);
    
    // массив для хранения маркеров
    var markers = [];
    // Группа слоев для маркеров
    var markersLayer = L.layerGroup().addTo(map);
    
    // передача данных из PHP в JavaScript в виде JSON
    var locationsData = <?= json_encode($locations) ?>;
    
    // Функция для создания содержимого попапа
    function createPopup(location) {
        // Если есть картинка, показываю её в попапе
        var imageHtml = location.image_url ? 
            '<img src="' + location.image_url + '" style="width:100%; max-height:120px; object-fit:cover; border-radius:8px; margin-bottom:8px;">' : 
            '';
        // Возвращаю HTML с информацией о месте
        return '<div style="font-family: Inter, sans-serif; color: #fff; min-width: 200px;">' +
            imageHtml +
            '<h3 style="color: #e9672b; margin: 0 0 4px 0; font-family: Prosto One, sans-serif;">' + location.name + '</h3>' +
            '<div style="color: #b0b0c0; font-size: 14px; margin-bottom: 4px;">' + (location.region || '') + '</div>' +
            '<div style="color: #888; font-size: 13px; margin-bottom: 8px;">' + (location.period_name || 'Период не указан') + '</div>' +
            '<div style="color: #b0b0c0; font-size: 13px; line-height: 1.4;">' + (location.description || '') + '</div>' +
            '</div>';
    }
    
    // маркеры на карту
    locationsData.forEach(function(location) {
        // наличие координат
        if (location.latitude && location.longitude) {
            var marker = L.marker([location.latitude, location.longitude], {
                title: location.name
            });
            
            // привязка попапа к маркеру
            marker.bindPopup(createPopup(location), {
                maxWidth: 280,
                className: 'custom-popup'
            });
            
            // маркер на карту
            markersLayer.addLayer(marker);
            markers.push({
                marker: marker,
                location: location
            });
        }
    });
    
    // центрирование маркеров
    if (markers.length > 0) {
        var firstLocation = markers[0].location;
        if (firstLocation.latitude && firstLocation.longitude) {
            map.setView([firstLocation.latitude, firstLocation.longitude], 3);
        }
    }
    
    // ФИЛЬТРАЦИЯ МЕСТ (И КАРТОЧЕК, И МАРКЕРОВ)
    // При изменении фильтров вызов filterLocations()
    $('#period, #type').change(function() {
        filterLocations();
    });
    
    // При вводе текста в поиске вызов filterLocations()
    $('#search').on('keyup', function() {
        filterLocations();
    });
    
    //  функция фильтрации
    function filterLocations() {
        var period = $('#period').val();
        var type = $('#type').val();
        var search = $('#search').val().toLowerCase();
        
        // фильтр карточки мест (скрываю/показываю)
        $('.location-card').each(function() {
            var $card = $(this);
            var cardPeriod = $card.data('period') || 0;
            var name = $card.data('name').toLowerCase();
            var region = $card.data('region').toLowerCase();
            var show = true;
            
            //  фильтр по эпохе
            if (period != 0 && cardPeriod != period) {
                show = false;
            }
            
            //  поиск по названию/региону
            if (search && !region.includes(search) && !name.includes(search)) {
                show = false;
            }
            
            if (show) {
                $card.show();
            } else {
                $card.hide();
            }
        });
        
        // филтрация маркеров на карте
        markersLayer.clearLayers();
        
        markers.forEach(function(item) {
            var loc = item.location;
            var show = true;
            
            //  фильтр по эпохе
            if (period != 0 && loc.ID_period != period) {
                show = false;
            }
            
            //  поиск
            if (search && !(loc.region || '').toLowerCase().includes(search) && !loc.name.toLowerCase().includes(search)) {
                show = false;
            }
            
            // Если подходит, маркер обратно на карту
            if (show) {
                markersLayer.addLayer(item.marker);
            }
        });
    }

    // ЗАКРЫТИЕ МОДАЛЬНЫХ ОКОН    
    // Закрытие по клику на крестик
    $('.modal-close').click(function(e) {
        e.stopPropagation();
        $(this).closest('.modal-overlay').removeClass('active');
    });

    // Закрытие по клику на фон (overlay)
    $('.modal-overlay').click(function(e) {
        if (e.target === this) {
            $(this).removeClass('active');
        }
    });

    // Предотвращаю закрытие при клике на содержимое модалки
    $('.modal').click(function(e) {
        e.stopPropagation();
    });

    // ОТКРЫТИЕ МОДАЛОК ВХОДА/РЕГИСТРАЦИИ
    // Кнопки "войти" и "регистрация" открывают соответствующие модалки
    // Открытие модалки входа
    $(document).on('click', '#loginBtn', function(e) {
        e.preventDefault();
        $('#loginModalOverlay').addClass('active');
    });

    // Открытие модалки регистрации
    $(document).on('click', '#registerBtn', function(e) {
        e.preventDefault();
        $('#registerModalOverlay').addClass('active');
    });

    // Переключение с входа на регистрацию
    $(document).on('click', '#switchToRegister', function(e) {
        e.preventDefault();
        $('#loginModalOverlay').removeClass('active');
        $('#registerModalOverlay').addClass('active');
    });

    // Переключение с регистрации на вход
    $(document).on('click', '#switchToLogin', function(e) {
        e.preventDefault();
        $('#registerModalOverlay').removeClass('active');
        $('#loginModalOverlay').addClass('active');
    });
});
</script>

</body>
</html>
