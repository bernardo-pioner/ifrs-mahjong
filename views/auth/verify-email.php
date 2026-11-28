<?php
/** @var string $email */
/** @var string $error */
/** @var bool $resent */
/** @var list<array{to: string, subject: string, text: string}> $devMails */
?>

<section class="auth-card">
    <p class="eyebrow">Cadastro</p>
    <h1>Confirme seu e-mail</h1>
    <p class="lede">
        <?php if ($resent && $error === ''): ?>
            Se ainda faltar confirmar, enviamos um novo link. Ele vale por 24 horas.
        <?php else: ?>
            Abra o link que enviamos para o seu e-mail. Ele vale por 24 horas.
        <?php endif; ?>
    </p>

    <?php if ($error !== ''): ?>
        <p class="form-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/confirmar-email" class="form">
        <?= csrfField() ?>
        <label>
            E-mail
            <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required>
        </label>
        <button type="submit" class="button">Reenviar link</button>
    </form>

    <?php require ROOT_PATH . '/views/partials/dev-mail.php'; ?>

    <p class="auth-switch">Já confirmou? <a href="/login">Entrar</a></p>
</section>
