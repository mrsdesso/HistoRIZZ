<?php
// АДМИНИСТРАТИВНАЯ ПАНЕЛЬ

// подключение к бд
require_once 'config.php';
//запуск сессии для проверки авторизованного пользователя
session_start();

// проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: main.php');
    exit;
}

// проверка на роль админа
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: lk.php');
    exit;
}

// СТАТИСТИКА ДЛЯ ДАШБОРДА

$stats = [];
// все пользователи
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$stats['users'] = $result ? $result->fetch_assoc()['count'] : 0;

// все статьи
$result = $conn->query("SELECT COUNT(*) as count FROM articles");
$stats['articles'] = $result ? $result->fetch_assoc()['count'] : 0;

// все викторины
$result = $conn->query("SELECT COUNT(*) as count FROM quizzes");
$stats['quizzes'] = $result ? $result->fetch_assoc()['count'] : 0;

// средний рейтинг викторин
$result = $conn->query("SELECT COALESCE(ROUND(AVG(rating), 1), 0) as avg FROM quiz_ratings");
$stats['avg_rating'] = $result ? $result->fetch_assoc()['avg'] : 0;

// 5 последних зарегистрированных пользователей
$users = [];
$result = $conn->query("SELECT ID_user, name, surname, email, phone, created_at FROM users ORDER BY created_at DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | HistoRIZZ</title>
    
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/footer.css">
    <link rel="stylesheet" href="css/admin.css">
    
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
    <div class="admin-wrapper">

        <!-- ===== БОКОВОЕ МЕНЮ ===== -->
        <aside class="admin-sidebar">
            <h2>Admin Panel</h2>
            <div class="sidebar-header">
                <h3>МЕНЮ</h3>
            </div>
            <nav class="sidebar-nav">
                <a href="admin.php" class="active">Дашборд</a>
                <a href="#" class="menu-item" data-page="users">Пользователи</a>
                <a href="#" class="menu-item" data-page="periods">Периоды</a>
                <a href="#" class="menu-item" data-page="personalities">Личности</a>
                <a href="#" class="menu-item" data-page="quizzes">Викторины</a>
            </nav>
        </aside>
            
        <!-- ===== ОСНОВНОЙ КОНТЕНТ ===== -->
        <div class="admin-content">
        <div class="admin-header">
            <div class="admin-header-left">
                <h1>Дашборд</h1>
                <div class="bio-divider"></div>
            </div>
            <span class="admin-date"><?= date('d.m.Y') ?></span>
        </div>
            
            <!-- ===== СТАТИСТИКА ===== -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['users']) ?></div>
                    <div class="stat-label">Всего пользователей</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['articles']) ?></div>
                    <div class="stat-label">Статей опубликовано</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['quizzes']) ?></div>
                    <div class="stat-label">Пройденных викторин</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['avg_rating'] ?></div>
                    <div class="stat-label">Средний рейтинг</div>
                </div>
            </div>
            
            <!-- ===== ПОСЛЕДНИЕ ПОЛЬЗОВАТЕЛИ ===== -->
            <div class="table-wrapper">
                <h2 class="table-title">Последние зарегистрированные пользователи</h2>
                <table class="admin-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Имя</th>
                            <th>Email</th>
                            <th>Телефон</th>
                            <th>Дата регистрации</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php if (!empty($users)): ?>
                            <!-- вывод пользователя в отдельную строку -->
                            <?php foreach ($users as $user): ?>
                                <tr data-user-id="<?= $user['ID_user'] ?>">
                                    <td>#<?= $user['ID_user'] ?></td>
                                    <td class="user-name"><?= htmlspecialchars($user['name'] . ' ' . $user['surname']) ?></td>
                                    <td class="user-email"><?= htmlspecialchars($user['email']) ?></td>
                                    <td class="user-phone"><?= htmlspecialchars($user['phone'] ?? '—') ?></td>
                                    <td><?= date('d.m.Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <!-- Кнопки для редактирования и удаления -->
                                        <a href="#" class="action-btn edit-btn" data-id="<?= $user['ID_user'] ?>">редактировать</a>
                                        <a href="#" class="action-btn delete-btn" data-id="<?= $user['ID_user'] ?>">удалить</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; color:var(--text-muted); padding:30px;">
                                    Пользователи не найдены
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
                        
            <!-- ===== БЫСТРЫЕ ДЕЙСТВИЯ ===== -->
            <div class="quick-actions">
                <h2 class="quick-title">Быстрые действия</h2>
                <div class="quick-grid">
                    <a href="#" class="quick-card">
                        <div class="quick-label">Добавить статью</div>
                        <div class="quick-desc">Новый контент</div>
                    </a>
                    <a href="#" class="quick-card">
                        <div class="quick-label">Создать викторину</div>
                        <div class="quick-desc">Новые вопросы</div>
                    </a>
                    <a href="#" class="quick-card">
                        <div class="quick-label">Управление ролями</div>
                        <div class="quick-desc">Назначить администратора</div>
                    </a>
                    <a href="#" class="quick-card">
                        <div class="quick-label">Экспорт данных</div>
                        <div class="quick-desc">CSV / JSON</div>
                    </a>
                </div>
            </div>
            
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
        <button class="modal-save" id="saveQuizBtn" title="Сохранить викторину">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
            </svg>
        </button>
        <div id="quizModalBody"></div>
    </div>
</div>


<!--  МОДАЛЬНОЕ ОКНО ДЛЯ РЕДАКТИРОВАНИЯ ПОЛЬЗОВАТЕЛЯ  -->
<div id="editUserModal" class="modal-overlay">
    <div class="modal" style="max-width: 500px;">
        <span class="modal-close">&times;</span>
        <h2>Редактирование пользователя</h2>
        <form id="editUserForm">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="modal-field">
                <label>Имя</label>
                <input type="text" name="name" id="editName" required>
            </div>
            <div class="modal-field">
                <label>Фамилия</label>
                <input type="text" name="surname" id="editSurname" required>
            </div>
            <div class="modal-field">
                <label>Email</label>
                <input type="email" name="email" id="editEmail" required>
            </div>
            <div class="modal-field">
                <label>Телефон</label>
                <input type="tel" name="phone" id="editPhone" placeholder="+7 (999) 123-45-67">
            </div>
            <div class="modal-field">
                <label>Роль</label>
                <select name="role" id="editRole">
                    <option value="user">Пользователь</option>
                    <option value="admin">Администратор</option>
                </select>
            </div>
            <button type="submit" class="modal-btn">Сохранить</button>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // ОБРАБОТЧИК КЛИКОВ ПО ПУНКТАМ МЕНЮ
    $('.menu-item').on('click', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        var pageNames = {
            'users': 'Пользователи',
            'periods': 'Периоды',
            'personalities': 'Личности',
            'quizzes': 'Викторины'
        };
        var pageName = pageNames[page] || page;
        alert('Страница "' + pageName + '" будет добавлена позже, погоди');
    });

    // РЕДАКТИРОВАНИЕ ПОЛЬЗОВАТЕЛЯ
    $(document).on('click', '.edit-btn', function(e) {
        e.preventDefault();
        var userId = $(this).data('id');
        var $row = $(this).closest('tr');
        var fullName = $row.find('.user-name').text().trim().split(' ');
        var userEmail = $row.find('.user-email').text().trim();
        var userPhone = $row.find('.user-phone').text().trim() || '';
        
        // поля формы
        $('#editUserId').val(userId);
        $('#editName').val(fullName[0] || '');
        var surnameParts = fullName.slice(1).join(' ');
        $('#editSurname').val(surnameParts || '');
        $('#editEmail').val(userEmail);
        $('#editPhone').val(userPhone);
        
        // модальное окно
        $('#editUserModal').addClass('active');
    });

    // СОХРАНЕНИЕ ИЗМЕНЕНИЙ ПОЛЬЗОВАТЕЛЯ
    $('#editUserForm').on('submit', function(e) {
        e.preventDefault();
        
        var userId = $('#editUserId').val();
        var name = $('#editName').val().trim();
        var surname = $('#editSurname').val().trim();
        var email = $('#editEmail').val().trim();
        var phone = $('#editPhone').val().trim();
        var role = $('#editRole').val();
        
        if (!name || !surname || !email) {
            alert('Заполните все поля');
            return;
        }
        
        $.ajax({
            url: 'ajax_update_user.php',
            type: 'POST',
            data: {
                user_id: userId,
                name: name,
                surname: surname,
                email: email,
                phone: phone,
                role: role
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // обновленние данных в таблице
                    var $row = $('tr[data-user-id="' + userId + '"]');
                    $row.find('.user-name').text(name + ' ' + surname);
                    $row.find('.user-email').text(email);
                    if ($row.find('.user-phone').length) {
                        $row.find('.user-phone').text(phone || '—');
                    }
                    
                    $('#editUserModal').removeClass('active');
                    alert('Пользователь обновлён!');
                } else {
                    alert(response.error || 'Ошибка обновления');
                }
            },
            error: function(xhr) {
                alert('Ошибка соединения: ' + xhr.responseText);
            }
        });
    });

    // УДАЛЕНИЕ ПОЛЬЗОВАТЕЛЯ
    $(document).on('click', '.delete-btn', function(e) {
        e.preventDefault();
        var userId = $(this).data('id');
        var userName = $(this).closest('tr').find('.user-name').text();
        
        // Проверка, чтобы случайно не удалить
        if (!confirm('Вы уверены, что хотите удалить пользователя "' + userName + '"? Это действие нельзя отменить.')) {
            return;
        }
        
        $.ajax({
            url: 'ajax_delete_user.php',
            type: 'POST',
            data: { user_id: userId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('tr[data-user-id="' + userId + '"]').fadeOut(300, function() {
                        $(this).remove();
                        // Если таблица пуста, сообщение
                        if ($('#usersTableBody tr').length === 0) {
                            $('#usersTableBody').html('<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:30px;">Пользователи не найдены</td></tr>');
                        }
                    });
                    alert('Пользователь удалён!');
                } else {
                    alert(response.error || 'Ошибка удаления');
                }
            },
            error: function(xhr) {
                alert('Ошибка соединения: ' + xhr.responseText);
            }
        });
    });

    // ЗАКРЫТИЕ МОДАЛЬНЫХ ОКОН    
    // Закрытие по клику на крестик
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

    // Предотвращаю закрытие при клике на содержимое модалки
    $('.modal').click(function(e) {
        e.stopPropagation();
    });
});
</script>

</body>
</html>