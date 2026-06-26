<?php
// ОБРАБОТКА ЗАКЛАДОК
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$item_type = $_POST['item_type'] ?? $_GET['item_type'] ?? '';
$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : (isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0);

// Преобразуем английские типы в русские для БД
$type_map = [
    'quiz' => 'викторина',
    'article' => 'статья',
    'person' => 'личность',
    'event' => 'событие'
];
$db_type = $type_map[$item_type] ?? $item_type;

// Логирование для отладки
error_log("Bookmark: action=$action, type=$db_type, id=$item_id, user=$user_id");

// Проверяем, что ID не отрицательный (0 — допустимое значение)
if ($item_id < 0) {
    echo json_encode(['error' => 'Неверный ID: ' . $item_id]);
    exit;
}

if (empty($action)) {
    echo json_encode(['error' => 'Неизвестное действие']);
    exit;
}

if (empty($db_type)) {
    echo json_encode(['error' => 'Неверный тип: ' . $item_type]);
    exit;
}

// ===== ПРОВЕРКА СТАТУСА =====
if ($action === 'check') {
    $stmt = $conn->prepare("SELECT ID_bookmark FROM bookmarks WHERE ID_user = ? AND item_type = ? AND ID_item = ?");
    if (!$stmt) {
        echo json_encode(['error' => 'Ошибка БД: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("isi", $user_id, $db_type, $item_id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    echo json_encode(['saved' => $exists]);
    exit;
}

// ДОБАВЛЕНИЕ
if ($action === 'add') {
    // Проверяем, не существует ли уже
    $check = $conn->prepare("SELECT ID_bookmark FROM bookmarks WHERE ID_user = ? AND item_type = ? AND ID_item = ?");
    if (!$check) {
        echo json_encode(['error' => 'Ошибка БД: ' . $conn->error]);
        exit;
    }
    $check->bind_param("isi", $user_id, $db_type, $item_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Уже сохранено']);
        $check->close();
        exit;
    }
    $check->close();
    
    $stmt = $conn->prepare("INSERT INTO bookmarks (ID_user, item_type, ID_item) VALUES (?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['error' => 'Ошибка БД: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("isi", $user_id, $db_type, $item_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Ошибка сохранения: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// УДАЛЕНИЕ 
if ($action === 'remove') {
    $stmt = $conn->prepare("DELETE FROM bookmarks WHERE ID_user = ? AND item_type = ? AND ID_item = ?");
    if (!$stmt) {
        echo json_encode(['error' => 'Ошибка БД: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("isi", $user_id, $db_type, $item_id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Закладка не найдена']);
        }
    } else {
        echo json_encode(['error' => 'Ошибка удаления: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

echo json_encode(['error' => 'Неизвестное действие: ' . $action]);
?>