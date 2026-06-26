<?php
//оценка статей
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$article_id = isset($_POST['article_id']) ? (int)$_POST['article_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$user_id = $_SESSION['user_id'];

if ($article_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Неверные данные']);
    exit;
}

// Проверяем, не оценивал ли пользователь уже эту статью
$check = $conn->prepare("SELECT ID_article_like FROM article_likes WHERE ID_article = ? AND ID_user = ?");
$check->bind_param("ii", $article_id, $user_id);
$check->execute();
$check_result = $check->get_result();

if ($check_result->num_rows > 0) {
    // Обновляем
    $update = $conn->prepare("UPDATE article_likes SET rating = ?, created_at = NOW() WHERE ID_article = ? AND ID_user = ?");
    $update->bind_param("iii", $rating, $article_id, $user_id);
    $update->execute();
} else {
    // Вставляем
    $insert = $conn->prepare("INSERT INTO article_likes (ID_article, ID_user, rating) VALUES (?, ?, ?)");
    $insert->bind_param("iii", $article_id, $user_id, $rating);
    $insert->execute();
}

// Получаем новый средний рейтинг
$avg_sql = "SELECT AVG(rating) as avg_rating FROM article_likes WHERE ID_article = ?";
$avg_stmt = $conn->prepare($avg_sql);
$avg_stmt->bind_param("i", $article_id);
$avg_stmt->execute();
$avg_result = $avg_stmt->get_result();
$avg_row = $avg_result->fetch_assoc();
$avg_rating = round($avg_row['avg_rating'] ?? 0, 1);

echo json_encode(['success' => true, 'avg_rating' => $avg_rating]);
?>