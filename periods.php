<?php
// страница исторических периодов с событиями из API-Ninjas
require_once 'config.php';
session_start();

// ========== ФУНКЦИЯ ПЕРЕВОДА ==========
function translateToRussian($text) {
    global $conn;
    
    if (empty($text) || preg_match('/[А-Яа-яЁё]/u', $text)) {
        return $text;
    }
    
    // Проверяем кеш
    $stmt = $conn->prepare("SELECT translated_text FROM translations WHERE source_text = ? AND language = 'ru' LIMIT 1");
    if (!$stmt) return $text;
    $stmt->bind_param("s", $text);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['translated_text'];
    }
    $stmt->close();
    
    // Перевод
    $translated = $text;
    $url = 'https://libretranslate.com/translate';
    $data = [
        'q' => $text,
        'source' => 'en',
        'target' => 'ru',
        'format' => 'text'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['translatedText']) && $result['translatedText'] !== $text) {
            $translated = $result['translatedText'];
        }
    }
    
    // Сохраняем в кеш
    $stmt = $conn->prepare("INSERT INTO translations (source_text, translated_text, language) VALUES (?, ?, 'ru') ON DUPLICATE KEY UPDATE translated_text = VALUES(translated_text)");
    if ($stmt) {
        $stmt->bind_param("ss", $text, $translated);
        $stmt->execute();
        $stmt->close();
    }
    
    return $translated;
}

function formatYear($year) {
    if ($year < 0) {
        return abs($year) . ' г. до н.э.';
    }
    return $year . ' г.';
}

$selected_period = isset($_GET['period_id']) ? (int)$_GET['period_id'] : 0;
$selected_year   = isset($_GET['year']) ? (int)$_GET['year'] : 0;

$periods = [];
$periods_sql = "SELECT ID_period, name, description, start_year, end_year FROM periods ORDER BY ID_period";
$periods_res = $conn->query($periods_sql);
if (!$periods_res) die('Ошибка periods: ' . $conn->error);
while ($row = $periods_res->fetch_assoc()) {
    $years_str = '';
    if ($row['start_year'] && $row['end_year']) {
        $years_str = $row['start_year'] . '–' . $row['end_year'] . ' гг.';
    } elseif ($row['start_year']) {
        $years_str = 'с ' . $row['start_year'] . ' г.';
    } elseif ($row['end_year']) {
        $years_str = 'до ' . $row['end_year'] . ' г.';
    } else {
        $years_str = 'Годы неизвестны';
    }
    $row['years_display'] = $years_str;
    $periods[] = $row;
}

if ($selected_period === 0 && !empty($periods)) {
    $selected_period = $periods[0]['ID_period'];
}

$events = [];
$years = [];
$error_message = '';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host . '/historizz/';

$apiProxyUrl = $baseUrl . "api_events.php?period=" . $selected_period;
if ($selected_year > 0) {
    $apiProxyUrl .= "&year=" . $selected_year;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiProxyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    $error_message = 'Ошибка cURL: ' . $curlError;
} elseif ($httpCode !== 200) {
    $error_data = json_decode($apiResponse, true);
    $error_message = isset($error_data['error']) ? $error_data['error'] : 'Ошибка HTTP ' . $httpCode;
} else {
    $apiData = json_decode($apiResponse, true);
    if (is_array($apiData) && !empty($apiData)) {
        $events = $apiData;
        
        usort($events, function($a, $b) {
            return (int)$a['year'] - (int)$b['year'];
        });
        
        // Перевод только для отображаемых событий (максимум 20)
        $displayEvents = array_slice($events, 0, 20);
        foreach ($displayEvents as &$event) {
            if (isset($event['event']) && !empty($event['event'])) {
                $event['event'] = translateToRussian($event['event']);
            }
        }
        unset($event);
        
        // Обновляем события (оставляем переведённые, остальные без перевода)
        foreach ($events as $i => &$e) {
            if ($i < 20 && isset($displayEvents[$i]['event'])) {
                $e['event'] = $displayEvents[$i]['event'];
            }
        }
        unset($e);
        
        $yearSet = [];
        foreach ($events as $event) {
            if (isset($event['year'])) {
                $yearSet[] = (int)$event['year'];
            }
        }
        $years = array_unique($yearSet);
        sort($years);
        
        if ($selected_period > 0) {
            $period_start = null;
            $period_end = null;
            foreach ($periods as $p) {
                if ($p['ID_period'] == $selected_period) {
                    $period_start = $p['start_year'];
                    $period_end = $p['end_year'];
                    break;
                }
            }
            if ($period_start !== null && $period_end !== null) {
                $events = array_filter($events, function($e) use ($period_start, $period_end) {
                    return (int)$e['year'] >= $period_start && (int)$e['year'] <= $period_end;
                });
                $events = array_values($events);
                usort($events, function($a, $b) {
                    return (int)$a['year'] - (int)$b['year'];
                });
                
                $yearSet = [];
                foreach ($events as $event) {
                    if (isset($event['year'])) {
                        $yearSet[] = (int)$event['year'];
                    }
                }
                $years = array_unique($yearSet);
                sort($years);
            }
        }
    } else {
        $error_message = 'Нет данных от API';
    }
}

$period_name = '';
foreach ($periods as $p) {
    if ($p['ID_period'] == $selected_period) {
        $period_name = $p['name'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HistoRIZZ | Периоды</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/footer.css">
    <link rel="stylesheet" href="css/periods.css">

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
        <div class="hero-text">
            <h1 class="hero-title">Исторические периоды</h1>
            <p class="hero-subtitle">Выбери эпоху и погрузись в события</p>
        </div>

        <div class="periods-grid">
            <?php foreach ($periods as $period): ?>
            <a href="?period_id=<?= $period['ID_period'] ?>" class="period-card <?= $selected_period == $period['ID_period'] ? 'active' : '' ?>">
                <h3 class="period-name"><?= htmlspecialchars($period['name']) ?></h3>
                <div class="period-years"><?= htmlspecialchars($period['years_display']) ?></div>
                <p class="period-description"><?= htmlspecialchars($period['description'] ?? '') ?></p>
                <span class="period-btn">подробнее →</span>
            </a>
            <?php endforeach; ?>
        </div>

        <section class="timeline">
            <h2 class="section-title">Лента событий: <?= htmlspecialchars($period_name ?: 'Все') ?></h2>

            <?php if (!empty($error_message)): ?>
                <div style="background: rgba(255,0,0,0.1); padding: 12px; border-radius: 8px; margin-bottom: 20px; color: #ff6b6b; border: 1px solid #ff6b6b;">
                    <strong>Ошибка:</strong> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <div class="timeline-filters">
                <div class="filter-group">
                    <label for="periodFilter">Фильтр по эпохе:</label>
                    <select id="periodFilter" class="filter-select" onchange="window.location.href='?period_id='+this.value">
                        <option value="0">Все эпохи</option>
                        <?php foreach ($periods as $p): ?>
                            <option value="<?= $p['ID_period'] ?>" <?= $selected_period == $p['ID_period'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="yearFilter">Год:</label>
                    <select id="yearFilter" class="filter-select" onchange="window.location.href='?period_id=<?= $selected_period ?>&year='+this.value">
                        <option value="0">Все годы</option>
                        <?php 
                        $sorted_years = $years;
                        sort($sorted_years);
                        foreach ($sorted_years as $y): ?>
                            <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>>
                                <?= formatYear($y) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="timeline-bar">
                <?php if (count($years) > 1): ?>
                    <?php 
                    $min_year = min($years);
                    $max_year = max($years);
                    $range = $max_year - $min_year;
                    if ($range == 0) $range = 1;
                    ?>
                    <div class="timeline-track">
                        <?php foreach ($years as $y): 
                            $position = (($y - $min_year) / $range) * 100;
                            $event_title = '';
                            foreach ($events as $e) {
                                if ((int)$e['year'] == $y) {
                                    $event_title = $e['event'] ?? $e['title'] ?? '';
                                    break;
                                }
                            }
                        ?>
                        <div class="timeline-dot" style="left: <?= $position ?>%;" 
                             data-year="<?= $y ?>" 
                             data-title="<?= htmlspecialchars($event_title) ?>">
                            <span class="dot-year"><?= $y ?></span>
                            <div class="dot-tooltip"><?= htmlspecialchars($event_title) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="timeline-labels">
                        <span class="timeline-label"><?= formatYear($min_year) ?></span>
                        <span class="timeline-label"><?= formatYear($max_year) ?></span>
                    </div>
                <?php elseif (count($years) == 1): ?>
                    <div class="timeline-track" style="justify-content: center;">
                        <div class="timeline-dot" style="position: static; transform: none;">
                            <span class="dot-year"><?= $years[0] ?></span>
                            <div class="dot-tooltip" style="opacity: 1; visibility: visible; position: static; transform: none; margin-top: 8px;">
                                <?php 
                                $title = '';
                                foreach ($events as $e) {
                                    if ((int)$e['year'] == $years[0]) {
                                        $title = $e['event'] ?? $e['title'] ?? '';
                                        break;
                                    }
                                }
                                echo htmlspecialchars($title);
                                ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="no-years">Нет событий для выбранного периода</p>
                <?php endif; ?>
            </div>

            <div class="events-grid">
                <?php if (count($events) > 0): ?>
                    <?php foreach ($events as $event): ?>
                        <div class="event-card">
                            <div class="event-year"><?= formatYear($event['year']) ?></div>
                            <h4 class="event-title"><?= htmlspecialchars($event['event'] ?? $event['title'] ?? '') ?></h4>
                            <p class="event-description">
                                <?php 
                                $desc = '';
                                if (isset($event['month']) && isset($event['day'])) {
                                    $desc .= $event['month'] . '/' . $event['day'] . ' — ';
                                }
                                $desc .= htmlspecialchars($event['event'] ?? $event['description'] ?? '');
                                echo $desc;
                                ?>
                            </p>
                            <a href="#" class="event-btn">подробнее →</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-data">Событий не найдено для выбранных фильтров.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

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

<?php include 'login_reg_modal.php'; ?>
<script src="js/auth.js"></script>
</body>
</html>