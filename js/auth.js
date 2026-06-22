// логика авторизации и регистрации

$(document).ready(function() {

    // ============================================================
    // 1. МОДАЛЬНЫЕ ОКНА (открытие/закрытие)
    // ============================================================

    // Закрытие по клику на крестик
    $(document).on('click', '.modal-close', function(e) {
        e.stopPropagation();
        $(this).closest('.modal-overlay').removeClass('active');
    });

    // Закрытие по клику на фон (overlay)
    $(document).on('click', '.modal-overlay', function(e) {
        if (e.target === this) {
            $(this).removeClass('active');
        }
    });

    // Предотвращаем закрытие при клике на содержимое модалки
    $(document).on('click', '.modal', function(e) {
        e.stopPropagation();
    });

    // ============================================================
    // 2. ОТКРЫТИЕ МОДАЛОК ВХОДА/РЕГИСТРАЦИИ
    // ============================================================
    $(document).on('click', '#loginBtn', function(e) {
        e.preventDefault();
        $('#loginModalOverlay').addClass('active');
    });

    $(document).on('click', '#registerBtn', function(e) {
        e.preventDefault();
        $('#registerModalOverlay').addClass('active');
    });

    $(document).on('click', '#switchToRegister', function(e) {
        e.preventDefault();
        $('#loginModalOverlay').removeClass('active');
        $('#registerModalOverlay').addClass('active');
    });

    $(document).on('click', '#switchToLogin', function(e) {
        e.preventDefault();
        $('#registerModalOverlay').removeClass('active');
        $('#loginModalOverlay').addClass('active');
    });

    // ============================================================
    // 3. ПОКАЗ/СКРЫТИЕ ПАРОЛЯ
    // ============================================================
    $(document).on('click', '.toggle-password', function() {
        var target = $(this).data('target');
        var input = $('#' + target);
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            $(this).text('🙈');
        } else {
            input.attr('type', 'password');
            $(this).text('👁');
        }
    });

    // ============================================================
    // 4. РЕГИСТРАЦИЯ
    // ============================================================
    $(document).on('submit', '#registerForm', function(e) {
        e.preventDefault();
        
        var name = $(this).find('input[name="name"]').val().trim();
        var surname = $(this).find('input[name="surname"]').val().trim();
        var email = $(this).find('input[name="email"]').val().trim();
        var password = $(this).find('input[name="password"]').val();
        var password_confirm = $(this).find('input[name="password_confirm"]').val();
        
        if (!name || !surname || !email || !password) {
            alert('Заполните все поля');
            return;
        }
        if (password !== password_confirm) {
            alert('Пароли не совпадают');
            return;
        }
        if (password.length < 5) {
            alert('Пароль должен быть не менее 5 символов');
            return;
        }
        
        $.ajax({
            url: 'ajax_auth.php',
            type: 'POST',
            data: {
                action: 'register',
                name: name,
                surname: surname,
                email: email,
                password: password,
                password_confirm: password_confirm
            },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Ошибка регистрации');
                }
            },
            error: function(xhr) {
                alert('Ошибка соединения: ' + xhr.responseText);
            }
        });
    });

    // ============================================================
    // 5. ВХОД
    // ============================================================
    $(document).on('submit', '#loginForm', function(e) {
        e.preventDefault();
        
        var email = $(this).find('input[name="email"]').val().trim();
        var password = $(this).find('input[name="password"]').val();
        
        if (!email || !password) {
            alert('Заполните все поля');
            return;
        }
        
        $.ajax({
            url: 'ajax_auth.php',
            type: 'POST',
            data: {
                action: 'login',
                email: email,
                password: password
            },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Ошибка входа');
                }
            },
            error: function(xhr) {
                alert('Ошибка соединения: ' + xhr.responseText);
            }
        });
    });

});