<?php
//  ОБРАБОТКА ВХОДА И РЕГИСТРАЦИИ
session_start();
require_once 'config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

// ВХОД
if ($action === 'login') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['error' => 'Заполните все поля']);
        exit;
    }

    $stmt = $conn->prepare("SELECT ID_user, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['ID_user'];
        $_SESSION['user_role'] = $user['role']; // Сохраняем роль в сессию
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Неверный email или пароль']);
    }
    exit;
}

// РЕГИСТРАЦИЯ
if ($action === 'register') {
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Валидация
    if (empty($name) || empty($surname) || empty($email) || empty($password)) {
        echo json_encode(['error' => 'Все поля обязательны']);
        exit;
    }
    if ($password !== $password_confirm) {
        echo json_encode(['error' => 'Пароли не совпадают']);
        exit;
    }
    if (strlen($password) < 5) {
        echo json_encode(['error' => 'Пароль должен быть не менее 5 символов']);
        exit;
    }

    // Проверка существования email
    $check = $conn->prepare("SELECT ID_user FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['error' => 'Пользователь с таким email уже существует']);
        $check->close();
        exit;
    }
    $check->close();

    // Определяем роль (если email admin@mail.ru — делаем админом)
    $role = 'user';
    if ($email === 'admin@mail.ru') {
        $role = 'admin';
    }

    // Хэширование и вставка
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, surname, email, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $surname, $email, $hashed, $role);

    if ($stmt->execute()) {
        $_SESSION['user_id'] = $stmt->insert_id;
        $_SESSION['user_role'] = $role; // Сохраняем роль в сессию
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Ошибка БД: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

echo json_encode(['error' => 'Неизвестное действие']);
?>