<?php
/** @var string $name */
/** @var string $email */
/** @var list<string> $errors */
?>
<section class="auth-card">
    <p class="eyebrow">Novo usuário</p>
    <h1>Criar cadastro</h1>
    <p class="lede">O cadastro libera o acesso ao painel do clube. O perfil de membro pode ser completado depois.</p>

    <?php if ($errors !== []): ?>
        <ul class="form-errors">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="/cadastro" class="form" novalidate>
        <?= csrfField() ?>
        <label>
            Nome
            <input type="text" name="name" value="<?= e($name) ?>" autocomplete="name" required>
        </label>
        <label>
            E-mail
            <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required>
        </label>
        <label>
            Senha
            <input type="password" name="password" autocomplete="new-password" minlength="8" required>
            <span class="hint">Mínimo de 8 caracteres.</span>
        </label>
        <button type="submit" class="button">Cadastrar</button>
    </form>

    <p class="auth-switch">Já tem conta? <a href="/login">Entrar</a></p>
</section>
