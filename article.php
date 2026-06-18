<?php
// ============================================================
// article.php — страница статьи (с оценками, чатом, шедеврами)
// ============================================================
require_once 'config.php';
session_start();

$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($article_id < 0) {
    die('Неверный ID статьи');
}

// ========== ЗАПРОС ДАННЫХ СТАТЬИ (добавлено hashtags) ==========
$sql = "SELECT 
            ID_article,
            title,
            subtitle,
            short_description,
            content,
            facts,
            facts_main,
            tsitsta,
            masterpieces,
            hashtags,
            image_url,
            reading_time,
            created_at
        FROM articles 
        WHERE ID_article = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Ошибка подготовки запроса: ' . $conn->error);
}
$stmt->bind_param("i", $article_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Статья не найдена');
}

$article = $result->fetch_assoc();
$stmt->close();

// ========== СРЕДНИЙ РЕЙТИНГ ==========
$avg_rating = 0;
$rating_count = 0;
$rating_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM article_likes WHERE ID_article = ?");
if ($rating_stmt) {
    $rating_stmt->bind_param("i", $article_id);
    $rating_stmt->execute();
    $rating_result = $rating_stmt->get_result();
    if ($rating_row = $rating_result->fetch_assoc()) {
        $avg_rating = round($rating_row['avg_rating'] ?? 0, 1);
        $rating_count = $rating_row['count'] ?? 0;
    }
    $rating_stmt->close();
}

// ========== ОЦЕНКА ТЕКУЩЕГО ПОЛЬЗОВАТЕЛЯ ==========
$user_rating = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_rating_stmt = $conn->prepare("SELECT rating FROM article_likes WHERE ID_article = ? AND ID_user = ?");
    if ($user_rating_stmt) {
        $user_rating_stmt->bind_param("ii", $article_id, $user_id);
        $user_rating_stmt->execute();
        $user_rating_result = $user_rating_stmt->get_result();
        if ($user_rating_row = $user_rating_result->fetch_assoc()) {
            $user_rating = $user_rating_row['rating'];
        }
        $user_rating_stmt->close();
    }
}

// ========== ПОДГОТОВКА БЛОКОВ ==========
$bio_text = $article['short_description'] ?: $article['subtitle'];
$quote = $article['tsitsta'] ?? '';
$masterpieces = $article['masterpieces'] ?? '';

// Факты для основного блока
$facts_main_list = '';
if (!empty($article['facts_main'])) {
    $facts_array = explode("\n", trim($article['facts_main']));
    $colors = ['#E9672B', '#E32636', '#565D33', '#E9672B'];
    $facts_main_list = '<div class="facts-main-container">';
    $fact_index = 0;
    foreach ($facts_array as $fact) {
        $fact = trim($fact);
        if (!empty($fact)) {
            $fact_index++;
            $color = $colors[($fact_index - 1) % count($colors)];
            $facts_main_list .= '<div class="fact-main-item">';
            $facts_main_list .= '<span class="fact-number" style="color: ' . $color . ';">' . $fact_index . '.</span>';
            $facts_main_list .= '<span class="fact-text">' . htmlspecialchars($fact) . '</span>';
            $facts_main_list .= '</div>';
        }
    }
    $facts_main_list .= '</div>';
}

// Факты для модального окна
$facts_list = '';
if (!empty($article['facts'])) {
    $facts_array = explode("\n", trim($article['facts']));
    $facts_list = '<div class="facts-list">';
    foreach ($facts_array as $fact) {
        $fact = trim($fact);
        if (!empty($fact)) {
            $facts_list .= '<div class="fact-item">' . htmlspecialchars($fact) . '</div>';
        }
    }
    $facts_list .= '</div>';
}

// Шедевры (автоматическая разметка из plain text)
$masterpieces_html = '';
if (!empty($article['masterpieces'])) {
    $blocks = preg_split('/\n\s*\n/', trim($article['masterpieces']));
    foreach ($blocks as $block) {
        $lines = explode("\n", trim($block));
        $title = trim($lines[0] ?? '');
        $description = trim(implode("\n", array_slice($lines, 1)));
        if (mb_strpos($title, 'Война и мир') !== false) {
            $color = '#E9672B';
        } elseif (mb_strpos($title, 'Анна Каренина') !== false) {
            $color = '#E32636';
        } else {
            $color = '#E9672B';
        }
        $masterpieces_html .= '<div class="masterpiece-item" style="border-color: ' . $color . ';">';
        $masterpieces_html .= '<h3 style="color: ' . $color . ';">' . htmlspecialchars($title) . '</h3>';
        $masterpieces_html .= '<p>' . nl2br(htmlspecialchars($description)) . '</p>';
        $masterpieces_html .= '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HistoRIZZ | <?= htmlspecialchars($article['title']) ?></title>
    
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/footer.css">
    <link rel="stylesheet" href="css/article.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&family=Prosto+One&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prosto+One&display=swap" rel="stylesheet">
    
    <link rel="icon" href="images/favicon.svg">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<?php include 'header.php'; ?>

<main>
    <div class="container">

        <!-- ===== КАРТОЧКА СТАТЬИ ===== -->
        <div class="article-wrapper">

            <!-- Верхняя часть: фото + заголовок + подзаголовок + хештеги + кнопка "Сохранить" -->
            <div class="article-header">
                <?php if (!empty($article['image_url'])): ?>
                    <div class="article-image">
                        <img src="<?= htmlspecialchars($article['image_url']) ?>" alt="<?= htmlspecialchars($article['title']) ?>">
                    </div>
                <?php endif; ?>
                <div class="article-title-group">
                    <h1 class="article-title"><?= htmlspecialchars($article['title']) ?></h1>
                    <p class="article-subtitle"><?= htmlspecialchars($article['subtitle']) ?></p>
                    
                    <!-- Хештеги (под подзаголовком, в одну строку с обводкой) -->
                    <?php if (!empty($article['hashtags'])): ?>
                        <div class="article-hashtags">
                            <?php 
                            $tags = explode(' ', trim($article['hashtags']));
                            foreach ($tags as $tag): 
                                if (!empty($tag)):
                                    $tag_clean = trim($tag);
                                    // Определяем цвет по ключевому слову
                                    if (mb_strpos($tag_clean, 'толстовство') !== false) {
                                        $color = '#E32636';
                                    } elseif (mb_strpos($tag_clean, 'пацифизм') !== false) {
                                        $color = '#565D33';
                                    } else {
                                        $color = '#E9672B'; // цвет по умолчанию
                                    }
                            ?>
                                    <span class="hashtag" style="color: <?= $color ?>; border-color: <?= $color ?>;">
                                        <?= htmlspecialchars($tag_clean) ?>
                                    </span>
                            <?php endif; endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="article-save">
                    <button class="btn-save">Сохранить</button>
                </div>
            </div>

            <!-- ===== ТЕЛО СТАТЬИ ===== -->
            <div class="article-body">

                <!-- БЛОК 1: "Кто он такой?" -->
                <div class="bio-row">
                    <div class="bio-content">
                        <h2 class="bio-title">Кто он такой?</h2>
                        <div class="bio-divider"></div>
                        <div class="bio-text">
                            <?= nl2br(htmlspecialchars($bio_text)) ?>
                        </div>
                    </div>
                    <div class="bio-action">
                        <button class="btn-detail" id="showFullContent">подробнее</button>
                    </div>
                </div>

                <!-- БЛОК 2: Цитата -->
                <?php if (!empty($quote)): ?>
                    <?php 
                    $quote_lines = explode("\n", trim($quote));
                    $quote_text = $quote_lines[0] ?? '';
                    $quote_author = $quote_lines[1] ?? '';
                    ?>
                    <div class="quote-block">
                        <div class="quote-text"><?= htmlspecialchars($quote_text) ?></div>
                        <?php if (!empty($quote_author)): ?>
                            <div class="quote-author"><?= htmlspecialchars($quote_author) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- БЛОК 3: Шедевры -->
                <?php if (!empty($masterpieces_html)): ?>
                    <div class="masterpieces-block">
                        <h2 class="masterpieces-title">Его главные шедевры (на зависть ТикТоку)</h2>
                        <div class="bio-divider"></div>
                        <div class="masterpieces-content">
                            <?= $masterpieces_html ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- БЛОК 4: Интересные факты -->
                <?php if (!empty($facts_main_list)): ?>
                    <div class="facts-main-block">
                        <h2 class="facts-main-title">Интересные факты (залутай для споров с друзьями)</h2>
                        <div class="bio-divider"></div>
                        <?= $facts_main_list ?>
                    </div>
                <?php endif; ?>

                <!-- БЛОК 5: Рейтинг и кнопки -->
                <div class="rating-actions">
                    <div class="bio-divider" style="margin-bottom: 8px;"></div>
                    <div class="rating-block-center">
                        <p class="rating-title">оцени наши старания</p>
                        <div class="stars-container" id="ratingStars">
                            <span class="star" data-rating="1">★</span>
                            <span class="star" data-rating="2">★</span>
                            <span class="star" data-rating="3">★</span>
                            <span class="star" data-rating="4">★</span>
                            <span class="star" data-rating="5">★</span>
                        </div>
                        <div class="rating-info">
                            <span id="avgRating"><?= $avg_rating ?></span> / 5 
                            (<?= $rating_count ?> оценок)
                        </div>
                    </div>

                    <div class="buttons-vertical">
                        <button class="btn-white" id="openChat">чатик по статье (тык)</button>
                        <button class="btn-white">сохранить</button>
                    </div>
                </div>

                <!-- БЛОК 6: Кнопка "Играть" -->
                <div class="play-wrapper">
                    <a href="victorins.php" class="btn-play-center">Играть →</a>
                </div>

            </div> <!-- /article-body -->
        </div> <!-- /article-wrapper -->
    </div> <!-- /container -->
</main>

<!-- ===== ФУТЕР ===== -->
<footer class="footer">
    <div class="container footer-inner">
        <img src="images/logo.png" alt="HistoRIZZ" class="footer-logo">
        <p class="copyright">© 2026 HistoRIZZ — история с характером</p>
        <div class="footer-contacts">
            <a href="mailto:hello@historizz.ru">📧 hello@historizz.ru</a> |
            <a href="#">📱 @historizz</a> |
            <a href="#">🎵 #историясхарактером</a>
        </div>
    </div>
</footer>

<!-- ===== МОДАЛЬНЫЕ ОКНА ===== -->
<?php include 'login_reg_modal.php'; ?>

<!-- Модальное окно "подробнее" -->
<div id="contentModal" class="modal-overlay">
    <div class="modal modal-article" style="width: 700px; max-width: 95%; max-height: 90vh; overflow-y: auto;">
        <span class="modal-close">&times;</span>
        <div id="modalContentBody">
            <h2 class="modal-title"><?= htmlspecialchars($article['title']) ?>: подробнее</h2>
            
            <div class="modal-section">
                <h3 class="modal-subtitle">Биография</h3>
                <div class="modal-text">
                    <?= nl2br(htmlspecialchars($article['content'])) ?>
                </div>
            </div>

            <?php if (!empty($facts_list)): ?>
            <div class="modal-section">
                <h3 class="modal-subtitle">Ещё больше фактов</h3>
                <?= $facts_list ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Модальное окно чата -->
<div id="chatModal" class="modal-overlay">
    <div class="modal modal-article" style="width: 600px; max-width: 95%; max-height: 80vh; display: flex; flex-direction: column; padding: 20px;">
        <span class="modal-close">&times;</span>
        <h3 class="modal-title" style="font-size: 24px; margin-bottom: 16px;">Обсуждение статьи</h3>
        <div id="chatMessages" style="flex: 1; overflow-y: auto; margin-bottom: 16px; padding-right: 8px;"></div>
        <div id="chatForm" style="display: flex; gap: 10px;">
            <input type="text" id="chatInput" placeholder="Ваш комментарий..." style="flex: 1; padding: 10px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(233,103,43,0.4); border-radius: 40px; color: white; outline: none;">
            <button id="chatSend" class="btn-save-small">Отправить</button>
        </div>
        <div id="chatAuthMessage" style="display: none; color: var(--text-gray); text-align: center; padding: 16px;">
            <a href="#" class="btn-login" id="chatLoginBtn">Войдите</a>, чтобы оставить комментарий
        </div>
    </div>
</div>

<script src="js/auth.js"></script>
<script>
$(document).ready(function() {
    $('#showFullContent').click(function() {
        $('#contentModal').addClass('active');
    });

    $('.modal-close, .modal-overlay').click(function() {
        $('.modal-overlay').removeClass('active');
    });

    // ===== РЕЙТИНГ =====
    var userRating = <?= $user_rating ?>;

    function highlightStars(rating) {
        $('#ratingStars .star').each(function() {
            if ($(this).data('rating') <= rating) {
                $(this).css('color', '#FFD700');
            } else {
                $(this).css('color', '#888');
            }
        });
    }

    function resetStars() {
        if (userRating > 0) {
            highlightStars(userRating);
        } else {
            $('#ratingStars .star').css('color', '#888');
        }
    }

    $('#ratingStars .star').hover(
        function() { highlightStars($(this).data('rating')); },
        function() { resetStars(); }
    );

    $('#ratingStars .star').click(function() {
        var rating = $(this).data('rating');
        var articleId = <?= $article_id ?>;
        <?php if (!isset($_SESSION['user_id'])): ?>
            alert('Пожалуйста, войдите, чтобы оценить статью.');
            return;
        <?php endif; ?>
        $.ajax({
            url: 'ajax_rating.php',
            type: 'POST',
            data: { article_id: articleId, rating: rating },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    userRating = rating;
                    $('#avgRating').text(response.avg_rating);
                    if (response.count !== undefined) {
                        $('.rating-info').html('<span id="avgRating">' + response.avg_rating + '</span> / 5 (' + response.count + ' оценок)');
                    }
                    highlightStars(rating);
                } else {
                    alert(response.error || 'Ошибка сохранения рейтинга');
                }
            },
            error: function() { alert('Ошибка соединения'); }
        });
    });

    resetStars();

    // ===== ЧАТ =====
    $('#openChat').click(function() {
        $('#chatModal').addClass('active');
        loadChatMessages();
    });

    function loadChatMessages() {
        var articleId = <?= $article_id ?>;
        $.ajax({
            url: 'ajax_comments.php',
            type: 'GET',
            data: { article_id: articleId },
            dataType: 'html',
            success: function(html) {
                $('#chatMessages').html(html);
                <?php if (!isset($_SESSION['user_id'])): ?>
                    $('#chatForm').hide();
                    $('#chatAuthMessage').show();
                <?php else: ?>
                    $('#chatForm').show();
                    $('#chatAuthMessage').hide();
                <?php endif; ?>
            },
            error: function() {
                $('#chatMessages').html('<p style="color: red;">Ошибка загрузки комментариев</p>');
            }
        });
    }

    $('#chatSend').click(function() {
        var comment = $('#chatInput').val().trim();
        if (!comment) {
            alert('Введите текст комментария');
            return;
        }
        var articleId = <?= $article_id ?>;
        $.ajax({
            url: 'ajax_comments.php',
            type: 'POST',
            data: { article_id: articleId, comment: comment },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#chatInput').val('');
                    loadChatMessages();
                } else {
                    alert(response.error || 'Ошибка отправки');
                }
            },
            error: function() { alert('Ошибка соединения'); }
        });
    });

    $('#chatInput').keypress(function(e) {
        if (e.which === 13) $('#chatSend').click();
    });

    $(document).on('click', '#chatLoginBtn', function(e) {
        e.preventDefault();
        $('#chatModal').removeClass('active');
        setTimeout(function() {
            $('#loginModalOverlay').addClass('active');
        }, 200);
    });
});
</script>

</body>
</html>