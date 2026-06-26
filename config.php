<?php
// подключение к бд
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'historizz';

define('API_NINJAS_KEY', 'mRI5JIFgdU01kfGrNG0YrSLdqc10L9o3GnWj08fY');

$conn = new mysqli($host, $user, $password, $database);


if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Функция для получения настроек сайта (для админки)
function getSetting($key) {
    global $conn;
    $stmt = $conn->prepare("SELECT value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) return $row['value'];
    return null;
}

// Функция для проверки, авторизован ли пользователь и имеет ли роль admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}
?>
