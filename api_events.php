<?php
// для запросов к API-Ninjas Historical Events

require_once 'config.php';

if (!defined('API_NINJAS_KEY') || API_NINJAS_KEY === '') {
    http_response_code(500);
    echo json_encode(['error' => 'API ключ не задан']);
    exit;
}

$period = isset($_GET['period']) ? (int)$_GET['period'] : 0;
$year   = isset($_GET['year']) ? (int)$_GET['year'] : 0;

// Ключевые слова для каждого периода
$searchTerms = [
    1 => ['ancient', 'roman', 'greece', 'egypt', 'antiquity', 'athens', 'sparta'],
    2 => ['medieval', 'middle ages', 'crusades', 'knight', 'viking', 'feudal'],
    3 => ['renaissance', 'leonardo', 'michelangelo', 'davinci', 'tudor', 'medici'],
    4 => ['modern', 'industrial', 'revolution', 'world war', 'victorian', 'napoleon'],
    5 => ['contemporary', 'cold war', 'space', '20th century', 'world war ii', 'modern history'],
];

$allEvents = [];
$uniqueKeys = [];

if ($period > 0 && isset($searchTerms[$period])) {
    $keywords = $searchTerms[$period];
    
    // Если выбран год, используем меньше ключевых слов
    if ($year > 0) {
        $keywords = array_slice($keywords, 0, 3);
    }
    
    foreach ($keywords as $keyword) {
        $queryParams = ['text' => $keyword];
        if ($year > 0) {
            $queryParams['year'] = $year;
        }
        
        $apiUrl = 'https://api.api-ninjas.com/v1/historicalevents?' . http_build_query($queryParams);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Api-Key: ' . API_NINJAS_KEY]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (is_array($data)) {
                foreach ($data as $event) {
                    $key = ($event['year'] ?? '') . '|' . ($event['event'] ?? '');
                    if (!isset($uniqueKeys[$key])) {
                        $uniqueKeys[$key] = true;
                        $allEvents[] = $event;
                    }
                }
            }
        }
    }
}

// Если событий нет, пробуем получить общие события
if (empty($allEvents)) {
    $queryParams = ['text' => 'history'];
    if ($year > 0) {
        $queryParams['year'] = $year;
    }
    $apiUrl = 'https://api.api-ninjas.com/v1/historicalevents?' . http_build_query($queryParams);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Api-Key: ' . API_NINJAS_KEY]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if (is_array($data)) {
            foreach ($data as $event) {
                $key = ($event['year'] ?? '') . '|' . ($event['event'] ?? '');
                if (!isset($uniqueKeys[$key])) {
                    $uniqueKeys[$key] = true;
                    $allEvents[] = $event;
                }
            }
        }
    }
}

// Сортируем по году
usort($allEvents, function($a, $b) {
    return (int)($a['year'] ?? 0) - (int)($b['year'] ?? 0);
});

header('Content-Type: application/json');
echo json_encode($allEvents);
?>