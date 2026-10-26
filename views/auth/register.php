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
    <script src="/assets/js/check-username.js" defer></script>
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

    <form method="post" action="/cadastro" class="form" novalidate autocomplete="off">
        <?= csrfField() ?>
        <label>
            Apelido
            <input type="text" name="name" value="<?= e($name) ?>" oninput="debounceCheckUsername($name)" minlength="3" maxlength="20" required>
        </label>
        <label>
            E-mail
            <input type="email" name="email" value="<?= e($email) ?>" required>
        </label>
        <label>
            Senha
            <input type="password" name="password" autocomplete="new-password" minlength="8" maxlength="128" required>
            <span class="hint">Mínimo de 8 caracteres.</span>
        </label>
        <button type="submit" class="button">Cadastrar</button>
    </form>

    <p class="auth-switch">Já tem conta? <a href="/login">Entrar</a></p>
</section>
</body>
</html>