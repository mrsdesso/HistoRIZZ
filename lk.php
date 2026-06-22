<?php
// личный кабинет

require_once 'config.php';
session_start();

// проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: main.php');
    exit;
}

//  ID пользователя из сессии
$user_id = $_SESSION['user_id'];

// данные пользователя
$phone_field_exists = false;
$check_cols = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
if ($check_cols && $check_cols->num_rows > 0) {
    $phone_field_exists = true;
}

if ($phone_field_exists) {
    $stmt = $conn->prepare("SELECT name, surname, email, phone FROM users WHERE ID_user = ?");
} else {
    $stmt = $conn->prepare("SELECT name, surname, email FROM users WHERE ID_user = ?");
}

// обработка ошибки запроса
if (!$stmt) {
    die('Ошибка подготовки запроса: ' . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$phone_field_exists) {
    $user['phone'] = '';
}

// кол-во пройденных викторин
$stmt = $conn->prepare("SELECT COUNT(DISTINCT ID_quiz) as total FROM user_results WHERE ID_user = ?");
if (!$stmt) {
    die('Ошибка подготовки запроса: ' . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$quiz_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// результаты викторин
$results = [];
$stmt = $conn->prepare("
    SELECT q.title, ur.score, ur.total_possible, ur.date_taken, ur.ID_quiz 
    FROM user_results ur 
    JOIN quizzes q ON ur.ID_quiz = q.ID_quiz 
    WHERE ur.ID_user = ? 
    ORDER BY ur.date_taken DESC
");
if (!$stmt) {
    die('Ошибка подготовки запроса: ' . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

// Сохраняем все результаты в массив
while ($row = $res->fetch_assoc()) {
    $results[] = $row;
}
$stmt->close();

// закладки
$bookmarks = [];
$stmt = $conn->prepare("SELECT ID_item, item_type FROM bookmarks WHERE ID_user = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    // детальная ифна о закладке
    while ($row = $res->fetch_assoc()) {
        $id = $row['ID_item'];
        $type = $row['item_type'];
        
        // Если это статья — получаем название и время чтения
        if ($type === 'статья') {
            $s = $conn->prepare("SELECT title, reading_time FROM articles WHERE ID_article = ?");
            if ($s) {
                $s->bind_param("i", $id);
                $s->execute();
                $item = $s->get_result()->fetch_assoc();
                if ($item) {
                    $bookmarks[] = [
                        'id' => $id,
                        'title' => $item['title'],
                        'type' => 'Статья',
                        'meta' => $item['reading_time'] . ' мин чтения'
                    ];
                }
                $s->close();
            }
        } 
        // Если это викторина — получаем название и количество вопросов
        elseif ($type === 'викторина') {
            $s = $conn->prepare("SELECT title, total_questions FROM quizzes WHERE ID_quiz = ?");
            if ($s) {
                $s->bind_param("i", $id);
                $s->execute();
                $item = $s->get_result()->fetch_assoc();
                if ($item) {
                    $bookmarks[] = [
                        'id' => $id,
                        'title' => $item['title'],
                        'type' => 'Викторина',
                        'meta' => $item['total_questions'] . ' вопросов'
                    ];
                }
                $s->close();
            }
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HistoRIZZ | Личный кабинет</title>
    
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/footer.css">
    <link rel="stylesheet" href="css/lk.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&family=Prosto+One&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prosto+One&display=swap" rel="stylesheet">

    <link rel="icon" href="images/favicon.svg">
    
    <!-- подключение jQuery для AJAX-запросов и манипуляции с DOM -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<?php include 'header.php'; ?>

<main>
    <div class="container profile-container">
        <!-- инфа о пользователе -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php 
                $avatar_url = !empty($user['avatar']) ? $user['avatar'] : '';
                if (!empty($avatar_url)): ?>
                    <img src="<?= htmlspecialchars($avatar_url) ?>" alt="Аватар">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <?= mb_substr($user['name'], 0, 1) . mb_substr($user['surname'], 0, 1) ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-info">
                <h1 class="profile-name"><?= htmlspecialchars($user['name'] . ' ' . $user['surname']) ?></h1>
                <div class="profile-meta">
                    <?= htmlspecialchars($user['email']) ?> | <?= $quiz_count ?> викторины пройдено
                </div>
            </div>
        </div>

        <!-- рез-ты викторин -->
        <div class="profile-section">
            <h2 class="section-title">Мои результаты</h2>
            <div class="bio-divider orange"></div>
            
            <?php if (empty($results)): ?>
                <!-- если нет рез-ов -->
                <div class="no-results">
                    <p>Вы ещё не прошли ни одной викторины.</p>
                </div>
            <?php else: ?>
                <!-- Таблица с результатами -->
                <div class="table-wrapper">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Викторина</th>
                                <th>Дата</th>
                                <th>Результат</th>
                                <th>Действие</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $res): ?>
                            <tr>
                                <td><?= htmlspecialchars($res['title']) ?></td>
                                <td><?= date('d.m.Y', strtotime($res['date_taken'])) ?></td>
                                <td><span class="score-badge"><?= $res['score'] ?> / <?= $res['total_possible'] ?></span></td>
                                <td><a href="#" class="retry-quiz" data-quiz-id="<?= $res['ID_quiz'] ?>">Пройти снова →</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- закладки -->
        <div class="profile-section">
            <h2 class="section-title">Мои закладки</h2>
            <div class="bio-divider red"></div>
            <div class="bookmarks-list" id="bookmarksList">
                <?php if (empty($bookmarks)): ?>
                    <!-- Если закладок нет — показываем сообщение -->
                    <div class="bookmark-item" style="justify-content: center; border-left-color: transparent;">
                        <span style="color: var(--text-gray);">У вас пока нет закладок.</span>
                    </div>
                <?php else: ?>
                    <!-- Выводим все закладки -->
                    <?php foreach ($bookmarks as $index => $bm): 
                        // Цвет обводки зависит от индекса
                        $colors = ['var(--accent-orange)', 'var(--accent-red)', 'var(--green-dark)'];
                        $border_color = $colors[$index % 3];
                    ?>
                        <div class="bookmark-item" data-index="<?= $index ?>" style="border-left-color: <?= $border_color ?>;">
                            <div class="bookmark-info">
                                <div class="bookmark-title"><?= htmlspecialchars($bm['title']) ?></div>
                                <div class="bookmark-meta"><?= $bm['type'] ?> • <?= $bm['meta'] ?></div>
                            </div>
                            <!-- Кнопка удаления закладки -->
                            <button class="bookmark-delete" data-id="<?= $bm['id'] ?? '' ?>" data-type="<?= $bm['type'] ?>" title="Удалить закладку">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- редактирование профиля -->
        <div class="profile-section">
            <h2 class="section-title">Редактирование профиля</h2>
            <div class="bio-divider green"></div>
            <form id="profileForm" class="profile-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Имя:</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Фамилия:</label>
                        <input type="text" name="surname" value="<?= htmlspecialchars($user['surname']) ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Телефон:</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+7 (999) 123-45-67">
                    </div>
                </div>
                <button type="submit" class="save-profile-btn">Сохранить</button>
                <div id="profileMessage"></div>
            </form>
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

<!-- модальные окна регистрации и авторизации -->
<?php include 'login_reg_modal.php'; ?>

<!-- модальное окно викторин -->
<div id="quizModalOverlay" class="modal-overlay">
    <div class="modal" style="width: 700px; max-width: 95%;">
        <span class="modal-close">&times;</span>
        <div id="quizModalBody"></div>
    </div>
</div>


<script>
$(document).ready(function() {

    // обновление профиля
    $('#profileForm').submit(function(e) {
        e.preventDefault();
        
        //отправка данныз
        $.post('ajax_update_profile.php', $(this).serialize(), function(data) {
            if (data.success) {
                // Если успешно — показываем сообщение и перезагружаем страницу
                $('#profileMessage').html('<span style="color:#4caf50;">Профиль обновлён</span>');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                // Если ошибка — показываем сообщение об ошибке
                $('#profileMessage').html('<span style="color:#e32636;">' + data.message + '</span>');
            }
        }, 'json').fail(function() {
            // Если ошибка соединения
            $('#profileMessage').html('<span style="color:#e32636;">Ошибка соединения</span>');
        });
    });

    // закрытие модалок
    // Закрытие по крестику
    $('.modal-close').click(function(e) {
        e.stopPropagation();
        $(this).closest('.modal-overlay').removeClass('active');
    });

    // Закрытие по клику на фон (overlay)
    $('.modal-overlay').click(function(e) {
        if (e.target === this) {
            $(this).removeClass('active');
        }
    });

    // Остановка клика внутри модального окна
    $('.modal').click(function(e) {
        e.stopPropagation();
    });

    // повторное прохождение викторины
    $('.retry-quiz').click(function(e) {
        e.preventDefault();
        var quizId = $(this).data('quiz-id');
        if (quizId) {
            loadQuiz(quizId);
        }
    });

    //логика прохождения викторин
    let currentQuizData = null;      // Данные текущей викторины
    let currentQuestionIndex = 0;    // Индекс текущего вопроса
    let userAnswers = {};            // Ответы пользователя {ID_вопроса: ответ}

    // Загрузка викторины 
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
                // Сохраняем данные и начинаем с первого вопроса
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

    // Отображение текущего вопроса
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

        // Выводим варианты ответов
        q.answers.forEach(a => {
            const checked = (savedAnswer === a.answer) ? 'checked' : '';
            html += `<label class="quiz-answer-option">
                <input type="radio" name="question" value="${escapeHtml(a.answer)}" ${checked}>
                <span>${escapeHtml(a.answer)}</span>
            </label>`;
        });

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

        // Сохраняем ответ при выборе варианта
        $('input[name="question"]').change(function() {
            userAnswers[q.id] = $(this).val();
            updateProgressIndicator();
        });

        // Обработчик кнопки "Назад"
        $('#prevBtn').click(() => {
            if (currentQuestionIndex > 0) {
                currentQuestionIndex--;
                renderCurrentQuestion();
            }
        });

        // Обработчик кнопки "Далее"
        $('#nextBtn').click(() => {
            if (currentQuestionIndex < total-1) {
                currentQuestionIndex++;
                renderCurrentQuestion();
            }
        });

        // Обработчик кнопки "Завершить"
        $('#finishBtn').click(() => {
            if (Object.keys(userAnswers).length < total) {
                if (!confirm('Вы ответили не на все вопросы. Всё равно завершить?')) return;
            }
            submitQuiz();
        });
    }

    // Обновление индикатора прогресса
    function updateProgressIndicator() {
        const answeredCount = Object.keys(userAnswers).length;
        const total = currentQuizData.questions.length;
        $('.quiz-progress').text(`Вопрос ${currentQuestionIndex+1} из ${total} | Ответов: ${answeredCount} из ${total}`);
    }

    // Отправка ответов и получение результата
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
                    // Показываем результат
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

                    // Обработчик для оценки викторины
                    $('.star-rating').click(function() {
                        let rating = $(this).data('value');
                        $('.star-rating').each(function(idx, el) {
                            if ($(el).data('value') <= rating) {
                                $(el).text('★').css('color', '#FFD700');
                            } else {
                                $(el).text('☆').css('color', '#ccc');
                            }
                        });
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

    // удаление закладки
    $(document).on('click', '.bookmark-delete', function(e) {
        e.stopPropagation();
        
        var $btn = $(this);
        var itemId = $btn.data('id');
        var itemType = $btn.data('type');
        
        // Преобразуем тип для БД
        var typeMap = {
            'Статья': 'статья',
            'Викторина': 'викторина',
            'Личность': 'личность',
            'Событие': 'событие'
        };
        var dbType = typeMap[itemType] || itemType;
        
        // Запрос подтверждения
        if (!confirm('Удалить закладку?')) {
            return;
        }
        
        // Отправляем запрос на удаление
        $.ajax({
            url: 'ajax_bookmark.php',
            type: 'POST',
            data: {
                action: 'remove',
                item_type: dbType,
                item_id: itemId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Плавное удаление элемента
                    $btn.closest('.bookmark-item').fadeOut(300, function() {
                        $(this).remove();
                        // Если закладок не осталось — показываем сообщение
                        if ($('.bookmark-item').length === 0) {
                            $('#bookmarksList').html('<div class="bookmark-item" style="justify-content: center; border-left-color: transparent;"><span style="color: var(--text-gray);">У вас пока нет закладок.</span></div>');
                        }
                    });
                } else {
                    alert(response.error || 'Ошибка удаления');
                }
            },
            error: function(xhr) {
                alert('Ошибка соединения: ' + xhr.status);
            }
        });
    });

   
    // Экранирование HTML-символов для безопасности
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;' }[m]));
    }

    // Закрытие модального окна результата
    $(document).on('click', '.close-modal-btn', function() {
        $('.modal-overlay').removeClass('active');
    });

});
</script>

</body>
</html>
