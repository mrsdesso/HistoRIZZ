// ========== МОДАЛЬНЫЕ ОКНА: ВХОД И РЕГИСТРАЦИЯ ==========
(function() {
    // создаём HTML для модальных окон, если их нет на странице
    function createModals() {
        // модальное окно ВХОДА
        if (!document.querySelector('.modal-login')) {
            const loginModal = document.createElement('div');
            loginModal.className = 'modal-overlay modal-login';
            loginModal.id = 'modalLogin';
            loginModal.innerHTML = `
                <div class="modal">
                    <span class="modal-close">&times;</span>
                    <h2>Добро пожаловать!</h2>
                    <div class="modal-subtitle">войди и продолжай своё историческое путешествие</div>
                    <div class="modal-field">
                        <label>Электронная почта</label>
                        <input type="email" placeholder="alex@historizz.ru" id="loginEmail">
                    </div>
                    <div class="modal-field">
                        <label>Пароль</label>
                        <input type="password" placeholder="••••••••" id="loginPassword">
                    </div>
                    <div class="modal-row">
                        <label class="checkbox-label">
                            <input type="checkbox"> Запомнить меня
                        </label>
                        <a href="#" class="forgot-link">Забыли пароль?</a>
                    </div>
                    <button class="modal-btn" id="loginBtn">ВОЙТИ</button>
                    <div class="modal-switch">
                        Нет аккаунта? <a href="#" id="switchToRegister">Зарегистрироваться →</a>
                    </div>
                </div>
            `;
            document.body.appendChild(loginModal);
        }

        // модальное окно РЕГИСТРАЦИИ
        if (!document.querySelector('.modal-register')) {
            const registerModal = document.createElement('div');
            registerModal.className = 'modal-overlay modal-register';
            registerModal.id = 'modalRegister';
            registerModal.innerHTML = `
                <div class="modal">
                    <span class="modal-close">&times;</span>
                    <h2>Создай аккаунт</h2>
                    <div class="modal-subtitle">сохраняй прогресс и становись лучшим в викторинах</div>
                    <div class="modal-field">
                        <label>Имя</label>
                        <input type="text" placeholder="Александр" id="regName">
                    </div>
                    <div class="modal-field">
                        <label>Фамилия</label>
                        <input type="text" placeholder="Македонский" id="regSurname">
                    </div>
                    <div class="modal-field">
                        <label>Электронная почта</label>
                        <input type="email" placeholder="alex@historizz.ru" id="regEmail">
                    </div>
                    <div class="modal-field">
                        <label>Пароль (мин. 5 символов, латиница + цифры)</label>
                        <input type="password" placeholder="********" id="regPassword">
                    </div>
                    <div class="modal-field">
                        <label>Подтвердите пароль</label>
                        <input type="password" placeholder="********" id="regConfirm">
                    </div>
                    <button class="modal-btn" id="registerBtn">Зарегистрироваться</button>
                    <div class="modal-switch">
                        Уже есть аккаунт? <a href="#" id="switchToLogin">Войти →</a>
                    </div>
                </div>
            `;
            document.body.appendChild(registerModal);
        }
    }

    // открыть модальное окно
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('active');
    }

    // закрыть все модальные окна
    function closeAllModals() {
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.classList.remove('active');
        });
    }

    // навешиваем обработчики
    function bindEvents() {
        // закрытие по крестику
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', closeAllModals);
        });

        // закрытие по клику на оверлей
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) closeAllModals();
            });
        });

        // переключение с входа на регистрацию
        const switchToRegister = document.getElementById('switchToRegister');
        if (switchToRegister) {
            switchToRegister.addEventListener('click', (e) => {
                e.preventDefault();
                closeAllModals();
                setTimeout(() => openModal('modalRegister'), 100);
            });
        }

        // переключение с регистрации на вход
        const switchToLogin = document.getElementById('switchToLogin');
        if (switchToLogin) {
            switchToLogin.addEventListener('click', (e) => {
                e.preventDefault();
                closeAllModals();
                setTimeout(() => openModal('modalLogin'), 100);
            });
        }

        // обработка входа (демо)
        const loginBtn = document.getElementById('loginBtn');
        if (loginBtn) {
            loginBtn.addEventListener('click', () => {
                const email = document.getElementById('loginEmail')?.value || '';
                const password = document.getElementById('loginPassword')?.value || '';
                if (!email || !password) {
                    alert('Пожалуйста, заполните все поля');
                    return;
                }
                alert(`Добро пожаловать, ${email}! (демо-вход)`);
                closeAllModals();
            });
        }

        // обработка регистрации (демо)
        const registerBtn = document.getElementById('registerBtn');
        if (registerBtn) {
            registerBtn.addEventListener('click', () => {
                const name = document.getElementById('regName')?.value || '';
                const email = document.getElementById('regEmail')?.value || '';
                const password = document.getElementById('regPassword')?.value || '';
                const confirm = document.getElementById('regConfirm')?.value || '';
                if (!name || !email || !password || !confirm) {
                    alert('Пожалуйста, заполните все поля');
                    return;
                }
                if (password.length < 5) {
                    alert('Пароль должен содержать минимум 5 символов');
                    return;
                }
                if (password !== confirm) {
                    alert('Пароли не совпадают');
                    return;
                }
                alert(`Регистрация успешна! Добро пожаловать, ${name}!`);
                closeAllModals();
            });
        }
    }

    // инициализация
    function initModals() {
        createModals();
        bindEvents();

        // привязываем к кнопкам в шапке (если они есть)
        const loginBtn = document.querySelector('.btn-login');
        const registerBtn = document.querySelector('.btn-reg');

        if (loginBtn) {
            loginBtn.addEventListener('click', (e) => {
                e.preventDefault();
                openModal('modalLogin');
            });
        }

        if (registerBtn) {
            registerBtn.addEventListener('click', (e) => {
                e.preventDefault();
                openModal('modalRegister');
            });
        }
    }

    // запускаем после загрузки DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModals);
    } else {
        initModals();
    }
})();