<!-- Модальное окно регистрации -->
<div id="registerModalOverlay" class="modal-overlay">
    <div class="modal">
        <span class="modal-close">&times;</span>
        <h2>Создай аккаунт</h2>
        <p class="modal-subtitle">сохраняй прогресс и становись лучшим в викторинах</p>
        <form id="registerForm">
            <input type="hidden" name="action" value="register">
            <div class="modal-field">
                <label>Имя</label>
                <input type="text" name="name" placeholder="Александр" required>
            </div>
            <div class="modal-field">
                <label>Фамилия</label>
                <input type="text" name="surname" placeholder="Македонский" required>
            </div>
            <div class="modal-field">
                <label>Электронная почта</label>
                <input type="email" name="email" placeholder="alex@historizz.ru" required>
            </div>
            <div class="modal-field">
                <label>Пароль <span class="password-hint">(мин. 5 символов)</span></label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="regPassword" required>
                    <span class="toggle-password" data-target="regPassword">👁</span>
                </div>
            </div>
            <div class="modal-field">
                <label>Подтвердите пароль</label>
                <div class="password-wrapper">
                    <input type="password" name="password_confirm" id="regPasswordConfirm" required>
                    <span class="toggle-password" data-target="regPasswordConfirm">👁</span>
                </div>
            </div>
            <button type="submit" class="modal-btn">Зарегистрироваться</button>
            <div class="modal-switch">Уже есть аккаунт? <a href="#" id="switchToLogin">Войти →</a></div>
        </form>
    </div>
</div>



<!-- Модальное окно входа -->
<div id="loginModalOverlay" class="modal-overlay">
    <div class="modal">
        <span class="modal-close">&times;</span>
        <h2>Добро пожаловать!</h2>
        <p class="modal-subtitle">войди и продолжай своё историческое путешествие</p>
        <form id="loginForm">
            <input type="hidden" name="action" value="login">
            <div class="modal-field">
                <label>Электронная почта</label>
                <input type="email" name="email" placeholder="alex@historizz.ru" required>
            </div>
            <div class="modal-field">
                <label>Пароль</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="loginPassword" placeholder="**********" required>
                    <span class="toggle-password" data-target="loginPassword">👁</span>
                </div>
            </div>
            <div class="modal-row">
                <label class="checkbox-label"><input type="checkbox" name="remember"> Запомнить меня</label>
                <a href="#" class="forgot-link">Забыли пароль?</a>
            </div>
            <button type="submit" class="modal-btn">ВОЙТИ</button>
            <div class="modal-switch">Нет аккаунта? <a href="#" id="switchToRegister">Зарегистрироваться →</a></div>
        </form>
    </div>
</div>
