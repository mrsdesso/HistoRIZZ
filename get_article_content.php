<?php
require_once 'config.php';

$person_id = isset($_GET['person_id']) ? (int)$_GET['person_id'] : 0;

if ($person_id <= 0) {
    die('Неверный ID');
}

// Находим статью, связанную с личностью
$name_sql = "SELECT name FROM lichnosti WHERE ID_lichnost = ?";
$stmt = $conn->prepare($name_sql);
$stmt->bind_param("i", $person_id);
$stmt->execute();
$name_result = $stmt->get_result();
if ($name_result->num_rows === 0) {
    die('Личность не найдена');
}
$person = $name_result->fetch_assoc();
$person_name = $person['name'];

// Ищем статью по названию
$article_sql = "SELECT content, title, subtitle, image_url FROM articles WHERE title = ?";
$stmt = $conn->prepare($article_sql);
$stmt->bind_param("s", $person_name);
$stmt->execute();
$article_result = $stmt->get_result();
if ($article_result->num_rows === 0) {
    die('Статья не найдена для этой личности');
}
$article = $article_result->fetch_assoc();

// Выводим контент в модальном окне
?>
<div class="person-modal-content" style="padding: 10px;">
    <?php if (!empty($article['image_url'])): ?>
        <img src="<?= htmlspecialchars($article['image_url']) ?>" alt="<?= htmlspecialchars($article['title']) ?>" style="float:right; max-width:200px; border-radius:12px; margin-left:20px; margin-bottom:10px;">
    <?php endif; ?>
    <h2><?= htmlspecialchars($article['title']) ?></h2>
    <p><em><?= htmlspecialchars($article['subtitle']) ?></em></p>
    <hr style="border-color: rgba(233,103,43,0.3); margin: 15px 0;">
    <div style="font-size: 15px; line-height: 1.7; color: #b0b0c0;">
        <?= nl2br(htmlspecialchars($article['content'])) ?>
    </div>
</div>