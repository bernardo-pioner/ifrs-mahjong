<?php
/** @var string $email */
/** @var string $error */
?>

<section class="auth-card">
    <p class="eyebrow">Acesso</p>
    <h1>Esqueci minha senha</h1>
    <p class="lede">Informe o e-mail da conta. Se ele estiver cadastrado, enviamos um código para criar uma senha nova.</p>

    <?php if ($error !== ''): ?>
        <p class="form-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/esqueci-senha" class="form">
        <?= csrfField() ?>
        <label>
            E-mail
            <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required>
        </label>
        <button type="submit" class="button">Enviar código</button>
    </form>

    <p class="auth-switch"><a href="/login">Voltar para entrar</a></p>
</section>
