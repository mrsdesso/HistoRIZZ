<?php
//ОБРАБОТКА КОММЕНТАРИЕВ
require_once 'config.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$is_post = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($is_post) {
    header('Content-Type: application/json');
} else {
    header('Content-Type: text/html; charset=utf-8');
}

$article_id = isset($_GET['article_id']) ? (int)$_GET['article_id'] : (isset($_POST['article_id']) ? (int)$_POST['article_id'] : 0);

if ($article_id < 0) {
    if ($is_post) {
        echo json_encode(['success' => false, 'error' => 'Неверный ID статьи']);
    } else {
        echo '<p style="color: red;">Неверный ID статьи</p>';
    }
    exit;
}

//  POST: ДОБАВЛЕНИЕ КОММЕНТАРИЯ 
if ($is_post) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Не авторизован']);
        exit;
    }
    
    $comment = trim($_POST['comment'] ?? '');
    if (empty($comment)) {
        echo json_encode(['success' => false, 'error' => 'Комментарий не может быть пустым']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Проверяем, существует ли таблица
    $table_check = $conn->query("SHOW TABLES LIKE 'article_comments'");
    if ($table_check->num_rows == 0) {
        echo json_encode(['success' => false, 'error' => 'Таблица article_comments не существует']);
        exit;
    }
    
    // Вставляем комментарий
    $insert = $conn->prepare("INSERT INTO article_comments (ID_article, ID_user, comment) VALUES (?, ?, ?)");
    if (!$insert) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подготовки: ' . $conn->error]);
        exit;
    }
    $insert->bind_param("iis", $article_id, $user_id, $comment);
    
    if ($insert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Комментарий добавлен']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $insert->error]);
    }
    $insert->close();
    exit;
}

// ПОЛУЧЕНИЕ КОММЕНТАРИЕВ 
$sql = "SELECT c.comment, c.created_at, u.name AS username 
        FROM article_comments c 
        LEFT JOIN users u ON c.ID_user = u.ID_user 
        WHERE c.ID_article = ? 
        ORDER BY c.created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo 'Ошибка подготовки: ' . $conn->error;
    exit;
}
$stmt->bind_param("i", $article_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<p style="color: var(--text-gray); text-align: center; padding: 20px;">Пока нет комментариев. Будьте первым!</p>';
} else {
    while ($row = $result->fetch_assoc()) {
        echo '<div class="comment-item">';
        echo '<div class="comment-author"><strong>' . htmlspecialchars($row['username'] ?? 'Аноним') . '</strong> <span style="font-size: 12px; color: var(--text-gray);">' . date('d.m.Y H:i', strtotime($row['created_at'])) . '</span></div>';
        echo '<div class="comment-text">' . nl2br(htmlspecialchars($row['comment'])) . '</div>';
        echo '</div>';
    }
}
$stmt->close();
?>  