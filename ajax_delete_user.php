<?php
//  УДАЛЕНИЕ ПОЛЬЗОВАТЕЛЯ (для админки)
require_once 'config.php';
session_start();

header('Content-Type: application/json');

// Проверка авторизации и прав администратора
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Недостаточно прав']);
    exit;
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Неверный ID пользователя']);
    exit;
}

// Запрещаем удалять самого себя
if ($user_id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'Нельзя удалить самого себя']);
    exit;
}

// Удаление пользователя
$stmt = $conn->prepare("DELETE FROM users WHERE ID_user = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Пользователь не найден']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $stmt->error]);
}
$stmt->close();
?>