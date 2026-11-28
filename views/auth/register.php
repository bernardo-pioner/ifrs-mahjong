<?php
/** @var string $username */
/** @var string $email */
/** @var bool $notifyEmails */
/** @var bool $passwordMismatch */
/** @var bool|null $registerResponse */
?>

<section class="auth-card auth-card--register">
    <p class="eyebrow">Novo usuário</p>
    <h1>Criar cadastro</h1>
    <p class="lede">O cadastro libera o acesso ao painel do clube. O perfil de membro pode ser completado depois.
    </p>

    <?php if ($registerResponse === false): ?>
        <div id="registerError" class="flash flash-error" role="alert">
            Não foi possível concluir o cadastro. Tente novamente.
        </div>
    <?php endif; ?>

    <form id="registerForm" action="/cadastro" method="POST" class="form" novalidate autocomplete="off">
        <?= csrfField() ?>
        <label>
            Apelido
            <input id="usernameInput" type="text" name="username" value="<?= e($username) ?>"
                minlength="3" maxlength="12" pattern="[A-Za-z0-9]{3,12}" required>
            <div id="usernameAvailabilityMessage" class="hidden">
                <img id="usernameAvailabilityMessageImg" class="message-icon-img" src="" alt="">
                <span class="hint register-input-msg" id="usernameAvailabilityMessageTxt"></span>
            </div>
            <div id="usernameErrorMessage" class="hidden">
                <img class="message-icon-img" src="/assets/img/yellow_attention_icon.png" alt="">
                <span class="hint register-input-msg register-input-error" id="usernameErrorTxt"></span>
            </div>
        </label>
        <label>
            E-mail
            <input id="emailInput" type="email" name="email" value="<?= e($email) ?>" required>
            <div id="emailAvailabilityMessage" class="hidden">
                <img id="emailAvailabilityMessageImg" class="message-icon-img" src="" alt="">
                <span class="hint register-input-msg" id="emailAvailabilityMessageTxt"></span>
            </div>
            <div id="emailErrorMessage" class="hidden">
                <img class="message-icon-img" src="/assets/img/yellow_attention_icon.png" alt="">
                <span class="hint register-input-msg register-input-error" id="emailErrorTxt"></span>
            </div>
        </label>
        <label>
            Senha
            <div id="passwordInputDiv">
                <input id="passwordInput" type="password" name="password" minlength="8" maxlength="128" required>
                <img id="password-visibility-icon" src="/assets/img/login-eye-open.png" alt="Mostrar senha">
            </div>
            <span class="hint">Mínimo de 8 caracteres, com pelo menos um número</span>
            <div id="passwordErrorMessage" class="hidden">
                <img class="message-icon-img" src="/assets/img/yellow_attention_icon.png" alt="">
                <span class="hint register-input-msg register-input-error" id="passwordErrorTxt"></span>
            </div>
        </label>
        <label>
            Confirmar senha
            <div id="passwordConfirmInputDiv" class="password-field">
                <input id="passwordConfirmInput" type="password" name="password_confirmation" minlength="8" maxlength="128" autocomplete="new-password" required>
                <img id="password-confirm-visibility-icon" class="password-visibility-icon" src="/assets/img/login-eye-open.png" alt="Mostrar senha">
            </div>
            <div id="passwordConfirmErrorMessage" class="<?= $passwordMismatch ? '' : 'hidden' ?>">
                <img class="message-icon-img" src="/assets/img/yellow_attention_icon.png" alt="">
                <span class="hint register-input-msg register-input-error" id="passwordConfirmErrorTxt">As senhas não combinam.</span>
            </div>
        </label>
        <label class="mail-opt">
            <input type="checkbox" name="emails_notificacao" value="1" <?= $notifyEmails ? 'checked' : '' ?>>
            <span>Quero receber por e-mail as notificações da plataforma.</span>
        </label>
        <button id="submitRegisterButton" type="button" class="button">Cadastrar</button>
    </form>

    <p class="auth-switch">Já tem conta? <a href="/login">Entrar</a></p>
</section>

<script type="module" src="/assets/js/check-user-register-input.js"></script>
<script type="module" src="/assets/js/toggle-password-visibility.js?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/js/toggle-password-visibility.js') ?>"></script>
