<?php
// изменение данных пользователя
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit;
}

$user_id = $_SESSION['user_id'];
$name = trim($_POST['name']);
$surname = trim($_POST['surname']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);

// Валидация
if (empty($name) || empty($surname) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Заполните все обязательные поля']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Некорректный email']);
    exit;
}

// Проверка на уникальность email (исключая текущего пользователя)
$check = $conn->prepare("SELECT ID_user FROM users WHERE email = ? AND ID_user != ?");
$check->bind_param("si", $email, $user_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email уже используется другим пользователем']);
    exit;
}
$check->close();

$stmt = $conn->prepare("UPDATE users SET name = ?, surname = ?, email = ?, phone = ? WHERE ID_user = ?");
$stmt->bind_param("ssssi", $name, $surname, $email, $phone, $user_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Профиль обновлён']);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка обновления']);
}
$stmt->close();
?>