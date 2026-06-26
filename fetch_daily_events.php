<?php
//  обновляет таблицу events событиями за сегодня
require_once 'config.php';

// --- Конфигурация API---
$api_key = 'mRI5JIFgdU01kfGrNG0YrSLdqc10L9o3GnWj08fY';
$api_url = 'https://api.api-ninjas.com/v1/dayinhistory';

// --- Получаем сегодняшний месяц и день ---
$month = date('n'); // 1..12
$day   = date('j'); // 1..31
$today = date('Y-m-d');

// --- Проверяем, есть ли уже события за сегодня ---
$check = $conn->prepare("SELECT COUNT(*) as cnt FROM events WHERE date = ?");
$check->bind_param("s", $today);
$check->execute();
$result = $check->get_result();
$row = $result->fetch_assoc();

if ($row['cnt'] > 0) {
    echo "События за сегодня уже есть в базе.\n";
    $conn->close();
    exit;
}

// --- Запрос к API ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url . "?month=" . $month . "&day=" . $day);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Api-Key: ' . $api_key]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    echo "Ошибка API. HTTP-код: $http_code\n";
    $conn->close();
    exit;
}

$events = json_decode($response, true);
if (empty($events)) {
    echo "API не вернул событий.\n";
    $conn->close();
    exit;
}

// --- Сохраняеnn события в таблицу (используем INSERT IGNORE для защиты от дубликатов) ---
$insert = $conn->prepare("INSERT IGNORE INTO events (date, year, event_title, description) VALUES (?, ?, ?, ?)");
$inserted = 0;
foreach ($events as $event) {
    $year = $event['year'];
    $title = $event['event'];
    $desc = $event['event']; // можно использовать то же описание
    $insert->bind_param("siss", $today, $year, $title, $desc);
    if ($insert->execute()) {
        $inserted++;
    }
}
$insert->close();
$conn->close();

echo "Готово. Добавлено $inserted событий за $today.\n";
?>