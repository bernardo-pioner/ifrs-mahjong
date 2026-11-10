<?php
/** @var string $email */
?>

<html>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script type="module" src="/assets/js/toggle-password-visibility.js" defer></script>
</html>
<section class="auth-card">
    <p class="eyebrow"></p>
    <h1>Entrar no clube</h1>
    <p class="lede">Use sua conta para acompanhar membros, eventos e amistosos.</p>
    <p ></p>
    <form method="post" action="/login" class="form" novalidate>
        <?= csrfField() ?>
        <label>
            Apelido ou E-mail
            <input type="text" name="email" value="<?= e($email) ?>" autocomplete="username" required>
            E-mail ou nome
            <input type="text" name="email" value="<?= e($email) ?>" autocomplete="off" required>
        </label>
        <label>
            Senha
            <div id="passwordInputDiv">
                <input id="passwordInput" type="password" name="password" minlength="8" maxlength="128" required>
                <img id="password-visibility-icon" src="/assets/img/login-eye-open.png" alt="">
            </div>
        </label>
        <button type="submit" class="button">Entrar</button>
        
    </form>
    
    <p ></p>
    <p class="auth-switch">Ainda não tem conta? <a href="/cadastro">Cadastre-se</a></p>
</section>