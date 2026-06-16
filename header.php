<?php
//  шапка сайта (навигация, авторизация, адаптив)
if (session_status() === PHP_SESSION_NONE) session_start();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HistoRIZZ</title>
    
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/style.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&family=Prosto+One&display=swap" rel="stylesheet">

    <!-- jQuery (для модалок и бургера) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<header class="header">
    <div class="container header-inner">
        <!-- Логотип -->
        <a href="main.php" class="logo">
            <img src="images/logo.png" alt="HistoRIZZ" class="logo-img">
        </a>

        <!-- Бургер-кнопка -->
        <button class="menu-toggle" id="menuToggle" aria-label="Открыть меню">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <!-- Меню -->
        <ul class="nav" id="mainNav">
            <li><a href="main.php" <?= ($current_page == 'main.php' || $current_page == 'index.php') ? 'class="active"' : '' ?>>Главная</a></li>
            <li><a href="periods.php" <?= ($current_page == 'periods.php') ? 'class="active"' : '' ?>>Периоды</a></li>
            <li><a href="lichnosti.php" <?= ($current_page == 'lichnosti.php') ? 'class="active"' : '' ?>>Личности</a></li>
            <li><a href="carts.php" <?= ($current_page == 'carts.php') ? 'class="active"' : '' ?>>Карты</a></li>
            <li><a href="victorins.php" <?= ($current_page == 'victorins.php') ? 'class="active"' : '' ?>>Викторины</a></li>
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <li><a href="admin/main.php" <?= (strpos($current_page, 'admin') !== false) ? 'class="active"' : '' ?>>Админ-панель</a></li>
            <?php endif; ?>
        </ul>

        <!-- Авторизация -->
        <div class="auth-buttons">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="lk.php" class="btn-profile">Личный кабинет</a>
                <a href="logout.php" class="btn-logout">Выйти</a>
            <?php else: ?>
                <a href="#" class="btn-login" id="loginBtn">войти</a>
                <a href="#" class="btn-reg" id="registerBtn">регистрация</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!--  СКРИПТ ДЛЯ БУРГЕР-МЕНЮ  -->
<script>
$(document).ready(function() {
    // Обработчик клика по бургеру
    $('#menuToggle').on('click', function(e) {
        e.preventDefault();
        $('#mainNav').toggleClass('open');
        $(this).toggleClass('active'); // анимация бургера -> крестик
    });
});
</script>
