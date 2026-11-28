<?php
/** @var string $email */
/** @var string $error */
/** @var bool $codeSent */
/** @var list<array{to: string, subject: string, text: string}> $devMails */
?>

<section class="auth-card">
    <p class="eyebrow">Acesso</p>
    <h1>Nova senha</h1>
    <p class="lede">
        <?php if ($codeSent && $error === ''): ?>
            Se o e-mail estiver cadastrado, enviamos um código de 6 dígitos. Ele vale por 30 minutos.
        <?php else: ?>
            Digite o código recebido por e-mail e escolha uma senha nova.
        <?php endif; ?>
    </p>

    <?php if ($error !== ''): ?>
        <p class="form-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/redefinir-senha" class="form" novalidate>
        <?= csrfField() ?>
        <label>
            E-mail
            <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required>
        </label>
        <label>
            Código
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                minlength="6" maxlength="6" pattern="[0-9]{6}" required>
        </label>
        <label>
            Nova senha
            <div id="passwordInputDiv">
                <input id="passwordInput" type="password" name="password" minlength="8" maxlength="128" required>
                <img id="password-visibility-icon" src="/assets/img/login-eye-open.png" alt="Mostrar senha">
            </div>
            <span class="hint">Mínimo de 8 caracteres, com pelo menos um número</span>
        </label>
        <label>
            Confirmar senha
            <input type="password" name="password_confirmation" minlength="8" maxlength="128" required>
        </label>
        <button type="submit" class="button">Salvar senha</button>
    </form>

    <?php require ROOT_PATH . '/views/partials/dev-mail.php'; ?>

    <p class="auth-switch"><a href="/esqueci-senha">Pedir outro código</a></p>
</section>

<script type="module" src="/assets/js/toggle-password-visibility.js"></script>
