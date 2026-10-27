<?php
/** @var string $username */
/** @var string $email */
/** @var list<string> $errors */
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script type="module" src="/assets/js/check-username-availability.js" defer></script>
    <script type="module" src="/assets/js/check-username-register-input.js" defer></script>
</head>
<body>
    <section class="auth-card">
    <p class="eyebrow">Novo usuário</p>
    <h1>Criar cadastro</h1>
    <p class="lede">O cadastro libera o acesso ao painel do clube. O perfil de membro pode ser completado depois.</p>

    <?php if ($errors !== []): ?>
        <div class="form-errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="form" novalidate autocomplete="off">
        <?= csrfField() ?>
        <label>
            Apelido
            <input id="usernameInput" type="text" name="username" value="<?= e($username) ?>" minlength="3" maxlength="20" required>
            <div id="availabilityMessage" class="hidden">
                <img id="availabilityMessageImg" class="message-icon-img" src="" alt="">
                <span class="hint register-input-msg" id="availabilityMessageTxt"></span>
            </div>
            <div id="usernameErrorMessage" class="hidden">
                <img id="" class="message-icon-img" src="/assets/img/yellow_attention_icon.png" alt="">
                <span class="hint register-input-msg register-input-error" id="usernameErrorTxt"></span>
            </div>
        </label>
        <label>
            E-mail
            <input id="emailInput" type="email" name="email" value="<?= e($email) ?>" required>
            <div id="emailErrorMessage" class="hidden">
                <img id="" class="message-icon-img" src="/assets/img/yellow_attention_icon.png" alt="">
                <span class="hint register-input-msg register-input-error" id="emailErrorTxt"></span>
            </div>
        </label>
        <label>
            Senha
            <input id="passwordInput" type="password" name="password" minlength="8" maxlength="128" required>
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