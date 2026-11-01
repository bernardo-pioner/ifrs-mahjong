<?php
/** @var string $username */
/** @var string $email */
/** @var bool $registerResponse */
?>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script type="module" src="/assets/js/check-username-availability.js" defer></script>
    <script type="module" src="/assets/js/check-user-register-input.js" defer></script>
    <script type="module" src="/assets/js/toggle-password-visibility.js" defer></script>
</head>

<body>
    <section class="auth-card">
        <p class="eyebrow">Novo usuário</p>
        <h1>Criar cadastro</h1>
        <p class="lede">O cadastro libera o acesso ao painel do clube. O perfil de membro pode ser completado depois.
        </p>

        <?php if ($registerResponse === false): ?>
            <div id="registerError" class="form-errors">
                <span id="registerErrorTxt" class="register-error">Não foi possível concluir o cadastro. Tente
                    novamente</span>
            </div>
        <?php endif; ?>

        <form id="registerForm" action="/cadastro" method="POST" class="form" novalidate autocomplete="off">
            <?= csrfField() ?>
            <label>
                Apelido
                <input id="usernameInput" type="text" name="username" value="<?= e($username) ?>" minlength="3"
                    maxlength="20" required>
                <div id="usernameAvailabilityMessage" class="hidden">
                    <img id="usernameAvailabilityMessageImg" class="message-icon-img" src="" alt="">
                    <span class="hint register-input-msg" id="usernameAvailabilityMessageTxt"></span>
                </div>
                <div id="usernameErrorMessage" class="hidden">
                    <img id="" class="message-icon-img" src="/assets/img/yellow_attention_icon.png" alt="">
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
                    <img id="" class="message-icon-img" src="/assets/img/yellow_attention_icon.png" alt="">
                    <span class="hint register-input-msg register-input-error" id="emailErrorTxt"></span>
                </div>
            </label>
            <label>
                Senha
                <div id="passwordInputDiv">
                    <input id="passwordInput" type="password" name="password" minlength="8" maxlength="128" required>
                    <img id="password-visibility-icon" src="/assets/img/login-eye-open.png" alt="">
                </div>
                <span class="hint">Mínimo de 8 caracteres</span>
                <div id="passwordErrorMessage" class="hidden">
                    <img id="" class="message-icon-img" src="/assets/img/yellow_attention_icon.png" alt="">
                    <span class="hint register-input-msg register-input-error" id="passwordErrorTxt"></span>
                </div>
            </label>
            <button id="submitRegisterButton" class="button">Cadastrar</button>
        </form>

        <p class="auth-switch">Já tem conta? <a href="/login">Entrar</a></p>
    </section>
</body>

</html>