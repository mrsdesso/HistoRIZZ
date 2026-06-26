<?php
// // изменение данных пользователя (для админки)
require_once 'config.php';
session_start();

header('Content-Type: application/json');

// Проверка авторизации и прав администратора
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Недостаточно прав']);
    exit;
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$name = trim($_POST['name'] ?? '');
$surname = trim($_POST['surname'] ?? '');
$email = trim(strtolower($_POST['email'] ?? ''));
$phone = trim($_POST['phone'] ?? '');
$role = trim($_POST['role'] ?? 'user');

if ($user_id <= 0 || empty($name) || empty($surname) || empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Все поля обязательны']);
    exit;
}

// Проверка, что email не занят другим пользователем
$check = $conn->prepare("SELECT ID_user FROM users WHERE email = ? AND ID_user != ?");
$check->bind_param("si", $email, $user_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'Пользователь с таким email уже существует']);
    $check->close();
    exit;
}
$check->close();

// Обновление пользователя
$stmt = $conn->prepare("UPDATE users SET name = ?, surname = ?, email = ?, phone = ?, role = ? WHERE ID_user = ?");
$stmt->bind_param("sssssi", $name, $surname, $email, $phone, $role, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $stmt->error]);
}
$stmt->close();
?>