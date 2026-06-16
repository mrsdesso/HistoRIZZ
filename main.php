<!-- подключение к бд -->
<?php
require_once 'config.php';
// запуск сессии для проверки авторизованного пользователя
session_start();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>HistoRIZZ | Главная</title>
    
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/footer.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&family=Prosto+One&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prosto+One&display=swap" rel="stylesheet">

    <link rel="icon" href="images/favicon.svg">
    <!-- подключение jQuery для AJAX-запросов и манипуляции с DOM -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<!-- подключение навигации -->
<?php include 'header.php'; ?>

<main>
    <!-- главый блок на странице -->
    <div class="hero">
        <div class="container hero-content">
            <h1>История с <br><span>ХАРАКТЕРОМ</span></h1>
            <p class="hero-desc">Ленты времени, викторины, карты и тысячи статей —<br>погрузись в прошлое так, как никогда раньше</p>
            <a href="victorins.php" class="btn-start">Начать изучение →</a>
        </div>
    </div>

    <div class="container">
        <?php
        //  СТАТИСТИКА (кол-во записей в таблицах) 
        $stats = [];
        $tables = ['periods' => 'исторических эпох', 'lichnosti' => 'исторических личностей', 'articles' => 'увлекательных статей', 'quizzes' => 'увлекательных викторин'];
        foreach ($tables as $table => $label) {
            $res = $conn->query("SELECT COUNT(*) as cnt FROM $table");
            $row = $res->fetch_assoc();
            $stats[$label] = $row['cnt'];
        }
        ?>
        <!-- вывод статистики в виде нескольких блоков -->
        <div class="stats">
            <div class="stat-item"><div class="stat-number stat-50"><?= $stats['исторических эпох'] ?></div><div class="stat-label">исторических эпох</div></div>
            <div class="stat-item"><div class="stat-number stat-300"><?= $stats['исторических личностей'] ?></div><div class="stat-label">исторических личностей</div></div>
            <div class="stat-item"><div class="stat-number stat-1000"><?= $stats['увлекательных статей'] ?></div><div class="stat-label">увлекательных статей</div></div>
            <div class="stat-item"><div class="stat-number stat-infinity"><?= $stats['увлекательных викторин'] ?></div><div class="stat-label">увлекательных викторин</div></div>
        </div>

        <!-- ПОПУЛЯРНЫЕ СТАТЬИ -->
        <div class="section-title">Популярные статьи</div>
        <div class="section-line"></div>
        <div class="articles-grid">
            <?php
            // Запрос на получение трёх самых популярных статей (по лайкам).
            $sql_articles = "
                SELECT a.ID_article, a.title, a.subtitle, a.for_popular, a.reading_time,
                       (SELECT COUNT(*) FROM article_likes WHERE ID_article = a.ID_article) as likes,
                       (SELECT COUNT(*) FROM article_comments WHERE ID_article = a.ID_article) as comments,
                       a.image_url
                FROM articles a
                ORDER BY likes DESC
                LIMIT 3
            ";
            $res_articles = $conn->query($sql_articles);
            while ($art = $res_articles->fetch_assoc()):
                // Если картинка не задана, ставится дефолтное изображение
                $img = !empty($art['image_url']) ? $art['image_url'] : 'images/main/main.png';
            ?>
            <!-- наполнение статьи из таблицы -->
            <div class="article-card">
                <div class="article-top">
                    <div class="article-img"><img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($art['title']) ?>"></div>
                    <div class="article-info">
                        <div class="article-title"><?= htmlspecialchars($art['title']) ?></div>
                        <div class="article-desc"><?= nl2br(htmlspecialchars($art['subtitle'])) ?></div>
                        <div class="article-desc-secondary"><?= nl2br(htmlspecialchars($art['for_popular'])) ?></div>
                    </div>
                </div>
                <div class="article-bottom">
                    <a href="article.php?id=<?= $art['ID_article'] ?>" class="btn-read">читать →</a>
                    <div class="stats-column">
                        <div class="stat-info">📅 <?= $art['reading_time'] ?> мин чтения</div>
                        <div class="stat-info">🔥 <?= number_format($art['likes']) ?> лайков</div>
                        <div class="stat-info">💬 <?= $art['comments'] ?> комментариев</div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- ПОПУЛЯРНЫЕ ВИКТОРИНЫ -->
        <div class="section-title">Популярные викторины</div>
        <div class="section-line"></div>
        <div class="quiz-grid">
            <?php
            // получение среднего рейтинга, кол-ва отзывов и рекорд прохождения (если нет оценок, то 0)
            $sql_quizzes = "
                SELECT 
                    q.ID_quiz, q.title, q.total_questions, q.difficulty, q.description,
                    COALESCE(ROUND(AVG(qr.rating), 1), 0) as avg_rating,
                    COUNT(DISTINCT qr.ID_quiz_rating) as reviews,
                    COALESCE(MAX(ur.score), 0) as best_score,
                    q.total_questions as max_score
                FROM quizzes q
                LEFT JOIN quiz_ratings qr ON q.ID_quiz = qr.ID_quiz
                LEFT JOIN user_results ur ON q.ID_quiz = ur.ID_quiz
                GROUP BY q.ID_quiz
                ORDER BY q.ID_quiz ASC
                LIMIT 3
            ";
            $res_quizzes = $conn->query($sql_quizzes);
            $difficulty_map = ['легкий' => 'легкий уровень', 'средний' => 'средний уровень', 'сложный' => 'сложный уровень'];
            while ($quiz = $res_quizzes->fetch_assoc()):
                $difficulty_text = $difficulty_map[$quiz['difficulty']] ?? $quiz['difficulty'];
                $time_min = ceil($quiz['total_questions'] * 0.6); // примерное время прохождения
                // фоновые изображения
                $bg_image = 'images/main/main.png';
                $title_lower = mb_strtolower($quiz['title']);
                if (strpos($title_lower, 'греция') !== false) $bg_image = 'images/vict/dr_greece_vict.png';
                elseif (strpos($title_lower, 'русские гении') !== false) $bg_image = 'images/vict/genius_vict.png';
                elseif (strpos($title_lower, 'битвы') !== false) $bg_image = 'images/vict/wars_vict.png';
            ?>

            <!-- наполнение вкторины из таблицы -->
            <div class="quiz-card" data-quiz-id="<?= $quiz['ID_quiz'] ?>" style="background-image: url('<?= $bg_image ?>');">
                <div class="quiz-overlay">
                    <div class="quiz-title"><?= htmlspecialchars($quiz['title']) ?></div>
                    <div class="quiz-desc"><?= htmlspecialchars($quiz['description'] ?? '') ?></div>
                    <div class="quiz-meta"><?= $quiz['total_questions'] ?> вопросов • <?= $time_min ?> минут • <?= $difficulty_text ?></div>
                    <div class="quiz-stats-row">
                        <span class="quiz-rating">⭐ <?= $quiz['avg_rating'] ?> (<?= number_format($quiz['reviews']) ?> отзывов)</span>
                        <span class="separator">|</span>
                        <span class="quiz-record">🔥 Рекорд: <?= round($quiz['best_score']) ?>/<?= $quiz['max_score'] ?></span>
                    </div>
                    <a href="#" class="btn-play play-quiz">Играть →</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</main>

<!-- ФУТЕР -->
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

<!-- модальные окна регситрации и авторизации -->
<?php include 'login_reg_modal.php'; ?>

<!--модальное окно викторин -->
<div id="quizModalOverlay" class="modal-overlay">
    <div class="modal" style="width: 700px; max-width: 95%;">
        <span class="modal-close">&times;</span>
        <div id="quizModalBody"></div>
    </div>
</div>

<!-- скрипт логики авторизации и регситрации -->
<script src="js/auth.js"></script>

<!-- скрипт для виктоорин (на jQuery) -->
<script>
$(document).ready(function() {
    // переменные для хранения состояния текущей викторины
    let currentQuizData = null, currentQuestionIndex = 0, userAnswers = {};

    // обработчик события нажатия на кнопку "Играть" на карточке викторины
    $('.play-quiz').click(function(e) {
        e.preventDefault();
        let quizId = $(this).closest('.quiz-card').data('quiz-id');
        if (quizId) loadQuiz(quizId);
    });

    // загрузка викторины
    function loadQuiz(quizId) {
        $('#quizModalBody').html('<div style="text-align:center; padding:20px;">Загрузка...</div>');
        $('#quizModalOverlay').addClass('active');
        $.ajax({
            url: 'ajax_quiz_handler.php',
            type: 'GET',
            data: { id: quizId },
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    $('#quizModalBody').html('<p>Ошибка: ' + data.error + '</p>');
                    return;
                }
                currentQuizData = data;
                currentQuestionIndex = 0;
                userAnswers = {};
                renderCurrentQuestion();
            },
            error: function() {
                $('#quizModalBody').html('<p>Ошибка загрузки викторины.</p>');
            }
        });
    }

    // рендер текущего вопроса
    function renderCurrentQuestion() {
        const total = currentQuizData.questions.length;
        const q = currentQuizData.questions[currentQuestionIndex];
        const savedAnswer = userAnswers[q.id] || '';
        const isLast = (currentQuestionIndex === total - 1);
        const answeredCount = Object.keys(userAnswers).length;

        let html = `<div class="quiz-step">
            <div class="quiz-header">
                <h2>${escapeHtml(currentQuizData.title)}</h2>
                <p>${escapeHtml(currentQuizData.description)}</p>
            </div>
            <div class="quiz-progress">Вопрос ${currentQuestionIndex+1} из ${total} | Ответов: ${answeredCount} из ${total}</div>
            <div class="quiz-question-text">${escapeHtml(q.text)}</div>
            <div class="quiz-answers-list">`;

        // вывод варианты ответов
        q.answers.forEach(a => {
            const checked = (savedAnswer === a.answer) ? 'checked' : '';
            html += `<label class="quiz-answer-option">
                <input type="radio" name="question" value="${escapeHtml(a.answer)}" ${checked}>
                <span>${escapeHtml(a.answer)}</span>
            </label>`;
        });

        // кнопки
        html += `</div>
            <div class="quiz-navigation">
                <button type="button" class="quiz-nav-btn" id="prevBtn" ${currentQuestionIndex===0?'disabled':''}>← Назад</button>`;

        if (!isLast) {
            html += `<button type="button" class="quiz-nav-btn" id="nextBtn">Далее →</button>`;
        }
        html += `<button type="button" class="quiz-submit-btn" id="finishBtn">Завершить</button>
            </div>
        </div>`;

        $('#quizModalBody').html(html);

        // выбор ответов
        $('input[name="question"]').change(function() {
            userAnswers[q.id] = $(this).val();
            updateProgressIndicator();
        });

        // обработка кнопок
        $('#prevBtn').click(() => {
            if (currentQuestionIndex > 0) {
                currentQuestionIndex--;
                renderCurrentQuestion();
            }
        });

        $('#nextBtn').click(() => {
            if (currentQuestionIndex < total-1) {
                currentQuestionIndex++;
                renderCurrentQuestion();
            }
        });

        // завершение викторины
        $('#finishBtn').click(() => {
            if (Object.keys(userAnswers).length < total) {
                if (!confirm('Вы ответили не на все вопросы. Всё равно завершить?')) return;
            }
            submitQuiz();
        });
    }

    // обновление индикатора прогресса в заголовке
    function updateProgressIndicator() {
        const answeredCount = Object.keys(userAnswers).length;
        const total = currentQuizData.questions.length;
        $('.quiz-progress').text(`Вопрос ${currentQuestionIndex+1} из ${total} | Ответов: ${answeredCount} из ${total}`);
    }

    // отправка результатов викторины на сервер
    function submitQuiz() {
        $.ajax({
            url: 'ajax_quiz_handler.php',
            type: 'POST',
            data: JSON.stringify({
                quiz_id: currentQuizData.quiz_id,
                answers: userAnswers
            }),
            contentType: 'application/json',
            dataType: 'json',
            success: function(res) {
                if (res.error) {
                    alert(res.error);
                } else {
                    //  результат
                    let stars = '';
                    for (let i = 1; i <= 5; i++) {
                        stars += `<span class="star-rating" data-value="${i}">☆</span>`;
                    }
                    $('#quizModalBody').html(`
                        <div class="quiz-result">
                            <h3>Результат</h3>
                            <p>Вы набрали <strong>${res.score}</strong> из ${res.total} баллов.</p>
                            <p>Процент: <strong>${res.percentage}%</strong></p>
                            <div class="rating-section">
                                <p>Оцените викторину:</p>
                                <div class="stars-container">${stars}</div>
                                <div id="ratingMessage"></div>
                            </div>
                            <button class="close-modal-btn">Закрыть</button>
                        </div>
                    `);

                    // обработчик клика по звёздам для оценки
                    $('.star-rating').click(function() {
                        let rating = $(this).data('value');
                        // смена цвета звезды до выбранной
                        $('.star-rating').each(function(idx, el) {
                            if ($(el).data('value') <= rating) {
                                $(el).text('★').css('color', '#FFD700');
                            } else {
                                $(el).text('☆').css('color', '#ccc');
                            }
                        });
                        // отправка оценки на сервер
                        $.ajax({
                            url: 'ajax_quiz_handler.php',
                            type: 'POST',
                            data: JSON.stringify({
                                action: 'rate',
                                quiz_id: currentQuizData.quiz_id,
                                rating: rating
                            }),
                            contentType: 'application/json',
                            success: (r) => {
                                $('#ratingMessage').text(r.error || 'Спасибо за оценку!');
                            }
                        });
                    });
                }
            }
        });
    }

    //  функция для экранирования HTML, чтобы избежать XSS
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;' }[m]));
    }

    // закрытие модального окна по кнопке "Закрыть"
    $(document).on('click', '.close-modal-btn', function() {
        $('.modal-overlay').removeClass('active');
    });
});
</script>
</body>
</html>