<?php
/** @var string $email */
?>
<section class="auth-card">
    <p class="eyebrow">Acesso</p>
    <h1>Entrar no clube</h1>
    <p class="lede">Use sua conta para acompanhar membros, eventos e amistosos.</p>

    <form method="post" action="/login" class="form" novalidate>
        <?= csrfField() ?>
        <label>
            E-mail ou nome
            <input type="text" name="email" value="<?= e($email) ?>" autocomplete="username" required>
        </label>
        <label>
            Senha
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit" class="button">Entrar</button>
    </form>

    <p class="auth-switch">Ainda não tem conta? <a href="/cadastro">Cadastre-se</a></p>
</section>
