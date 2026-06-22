<?php
require_once 'config.php';
session_start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HistoRIZZ | Викторины</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/header.css">   
    <link rel="stylesheet" href="css/footer.css">
    <link rel="stylesheet" href="css/victorins.css">

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
    <div class="hero-text">
        <h1 class="hero-title">Викторины</h1>
        <p class="hero-subtitle">Соревнуйся с друзьями и становись лучшим</p>
    </div>

    <?php
    $sql = "SELECT 
                q.ID_quiz,
                q.title,
                q.total_questions,
                q.description,
                COALESCE(ROUND(AVG(qr.rating), 1), 0) AS avg_rating,
                COUNT(DISTINCT qr.ID_quiz_rating) AS reviews_count,
                COUNT(DISTINCT ur.ID_user_result) AS attempts_count,
                COALESCE(MAX(ur.score), 0) AS best_score,
                q.total_questions AS max_score
            FROM quizzes q
            LEFT JOIN quiz_ratings qr ON q.ID_quiz = qr.ID_quiz
            LEFT JOIN user_results ur ON q.ID_quiz = ur.ID_quiz
            GROUP BY q.ID_quiz, q.title, q.total_questions, q.description
            ORDER BY q.ID_quiz";
    $res = $conn->query($sql);
    $all = [];
    while ($row = $res->fetch_assoc()) $all[$row['title']] = $row;

    $order = ['Древняя Греция', 'Войны XX века', 'Эпоха Возрождения', 'Русские гении'];
    $quizzes = [];
    foreach ($order as $title) {
        if (isset($all[$title])) $quizzes[] = $all[$title];
        else $quizzes[] = ['ID_quiz' => 0, 'title' => $title, 'total_questions' => 0, 'description' => 'Викторина готовится', 'avg_rating' => 0, 'reviews_count' => 0, 'attempts_count' => 0, 'best_score' => 0, 'max_score' => 0];
    }
    ?>

    <div class="quiz-grid">
        <?php foreach ($quizzes as $quiz):
            $time_min = ceil($quiz['total_questions'] * 0.6);
            $title_lower = mb_strtolower($quiz['title']);
            
            $bg = 'images/vict/default_quiz.jpg';
            $bg_map = [
                'древняя греция' => 'dr_greece_vict.png',
                'войны xx века'   => 'wars_vict.png',
                'эпоха возрождения' => 'renaissance_vict.png',
                'русские гении'   => 'genius_vict.png',
            ];
            foreach ($bg_map as $keyword => $file) {
                if (strpos($title_lower, $keyword) !== false) {
                    $bg = 'images/vict/' . $file;
                    break;
                }
            }
        ?>
        <div class="quiz-card" data-quiz-id="<?= $quiz['ID_quiz'] ?>" style="background-image: url('<?= $bg ?>');">
            <div class="quiz-card-grid">
                <div class="quiz-info">
                    <h3 class="quiz-title"><?= htmlspecialchars($quiz['title']) ?></h3>
                    <div class="quiz-meta"><?= $quiz['total_questions'] ?> вопросов • <?= $time_min ?> мин</div>
                    <p class="quiz-description"><?= htmlspecialchars($quiz['description']) ?></p>
                </div>
                <div class="quiz-sidebar">
                    <div class="quiz-rating">★ <?= $quiz['avg_rating'] ?> (<?= number_format($quiz['reviews_count']) ?> оценок)</div>
                    <div class="quiz-rating">🏆 Рекорд: <?= round($quiz['best_score']) ?>/<?= $quiz['max_score'] ?></div>
                    <div class="quiz-rating">👥 Прошло: <?= number_format($quiz['attempts_count']) ?> чел.</div>
                </div>
            </div>
            <div class="quiz-btn-wrapper">
                <a href="#" class="quiz-btn play-quiz">Играть →</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <section class="coming-soon">
        <div class="waiting-block">
            <p class="waiting-sub">скоро появится еще больше викторин</p>
            <h2 class="waiting-title">Прояви терпение и сделай нас лучше</h2>
        </div>
    </section>
</main>

<!-- Футер (встроен, не отдельный файл) -->
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

<!-- Модальные окна -->
<?php include 'login_reg_modal.php'; ?>

<div id="quizModalOverlay" class="modal-overlay">
    <div class="modal" style="width: 700px; max-width: 95%;">
        <span class="modal-close">&times;</span>
        <div id="quizModalBody"></div>
    </div>
</div>

<script src="js/auth.js"></script>
<script>
$(document).ready(function() {
    let currentQuizData = null, currentQuestionIndex = 0, userAnswers = {};

    $('.play-quiz').click(function(e) {
        e.preventDefault();
        let quizId = $(this).closest('.quiz-card').data('quiz-id');
        if (!quizId || quizId == 0) { alert('Викторина готовится'); return; }
        loadQuiz(quizId);
    });

    function loadQuiz(quizId) {
        $('#quizModalBody').html('<div style="text-align:center; padding:20px;">Загрузка...</div>');
        $('#quizModalOverlay').addClass('active');
        $.ajax({
            url: 'ajax_quiz_handler.php', type: 'GET', data: { id: quizId }, dataType: 'json',
            success: function(data) {
                if (data.error) { $('#quizModalBody').html('<p>Ошибка: ' + data.error + '</p>'); return; }
                currentQuizData = data; currentQuestionIndex = 0; userAnswers = {};
                renderCurrentQuestion();
            },
            error: function() { $('#quizModalBody').html('<p>Ошибка загрузки викторины</p>'); }
        });
    }

    function renderCurrentQuestion() {
        const total = currentQuizData.questions.length, q = currentQuizData.questions[currentQuestionIndex];
        const savedAnswer = userAnswers[q.id] || '', isLast = (currentQuestionIndex === total - 1);
        const answeredCount = Object.keys(userAnswers).length;
        let html = `<div class="quiz-step"><div class="quiz-header"><h2>${escapeHtml(currentQuizData.title)}</h2><p>${escapeHtml(currentQuizData.description)}</p></div>
            <div class="quiz-progress">Вопрос ${currentQuestionIndex+1} из ${total} | Ответов: ${answeredCount} из ${total}</div>
            <div class="quiz-question-text">${escapeHtml(q.text)}</div><div class="quiz-answers-list">`;
        q.answers.forEach(a => {
            const checked = (savedAnswer === a.answer) ? 'checked' : '';
            html += `<label class="quiz-answer-option"><input type="radio" name="question" value="${escapeHtml(a.answer)}" ${checked}><span>${escapeHtml(a.answer)}</span></label>`;
        });
        html += `</div><div class="quiz-navigation"><button type="button" class="quiz-nav-btn" id="prevBtn" ${currentQuestionIndex===0?'disabled':''}>← Назад</button>`;
        if (!isLast) html += `<button type="button" class="quiz-nav-btn" id="nextBtn">Далее →</button>`;
        html += `<button type="button" class="quiz-submit-btn" id="finishBtn">Завершить</button></div></div>`;
        $('#quizModalBody').html(html);
        $('input[name="question"]').change(function() { userAnswers[q.id] = $(this).val(); updateProgressIndicator(); });
        $('#prevBtn').click(() => { if (currentQuestionIndex > 0) { currentQuestionIndex--; renderCurrentQuestion(); } });
        $('#nextBtn').click(() => { if (currentQuestionIndex < total-1) { currentQuestionIndex++; renderCurrentQuestion(); } });
        $('#finishBtn').click(() => { if (Object.keys(userAnswers).length < total && !confirm('Вы ответили не на все вопросы. Всё равно завершить?')) return; submitQuiz(); });
    }

    function updateProgressIndicator() {
        const answeredCount = Object.keys(userAnswers).length, total = currentQuizData.questions.length;
        $('.quiz-progress').text(`Вопрос ${currentQuestionIndex+1} из ${total} | Ответов: ${answeredCount} из ${total}`);
    }

    function submitQuiz() {
        $.ajax({
            url: 'ajax_quiz_handler.php', type: 'POST', data: JSON.stringify({ quiz_id: currentQuizData.quiz_id, answers: userAnswers }),
            contentType: 'application/json', dataType: 'json',
            success: function(res) {
                if (res.error) alert(res.error);
                else {
                    let stars = ''; for (let i=1;i<=5;i++) stars += `<span class="star-rating" data-value="${i}">☆</span>`;
                    $('#quizModalBody').html(`<div class="quiz-result"><h3>Результат</h3><p>Вы набрали <strong>${res.score}</strong> из ${res.total} баллов.</p><p>Процент: <strong>${res.percentage}%</strong></p><div class="rating-section"><p>Оцените викторину:</p><div class="stars-container">${stars}</div><div id="ratingMessage"></div></div><button class="close-modal-btn">Закрыть</button></div>`);
                    $('.star-rating').click(function() {
                        let rating = $(this).data('value');
                        $('.star-rating').each(function(idx,el) { if($(el).data('value')<=rating) $(el).text('★').css('color','#FFD700'); else $(el).text('☆').css('color','#ccc'); });
                        $.ajax({ url: 'ajax_quiz_handler.php', type: 'POST', data: JSON.stringify({ action: 'rate', quiz_id: currentQuizData.quiz_id, rating: rating }), contentType: 'application/json', dataType: 'json', success: (r) => $('#ratingMessage').text(r.error || 'Спасибо за оценку!'), error: () => $('#ratingMessage').text('Ошибка') });
                    });
                }
            }
        });
    }

    function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, m => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;' }[m])); }

    function closeModals() { $('.modal-overlay').removeClass('active'); }
    $('.modal-close').click(closeModals);
    $(window).click(function(e) { if ($(e.target).hasClass('modal-overlay')) closeModals(); });
    $(document).on('click', '.close-modal-btn', closeModals);

    $('#loginBtn').click(function(e) { e.preventDefault(); closeModals(); $('#loginModalOverlay').addClass('active'); });
    $('#registerBtn').click(function(e) { e.preventDefault(); closeModals(); $('#registerModalOverlay').addClass('active'); });
    $('#switchToRegister').click(function(e) { e.preventDefault(); closeModals(); $('#registerModalOverlay').addClass('active'); });
    $('#switchToLogin').click(function(e) { e.preventDefault(); closeModals(); $('#loginModalOverlay').addClass('active'); });
});
</script>
</body>
</html>
