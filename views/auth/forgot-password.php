<?php
/** @var string $step */
/** @var string $email */
/** @var string $error */
/** @var int $resendWait */
/** @var string $passwordError */
/** @var string $passwordConfirmError */
$step = $step ?? 'email';
$email = $email ?? '';
$error = $error ?? '';
$resendWait = (int) ($resendWait ?? 0);
$passwordError = $passwordError ?? '';
$passwordConfirmError = $passwordConfirmError ?? '';
?>

<section class="auth-card auth-card--forgot">
    <?php if ($step === 'password'): ?>
        <p class="eyebrow">Nova senha</p>
        <h1>Criar nova senha</h1>
        <p class="lede">Escolha uma senha nova e confirme. Vale a mesma regra do cadastro.</p>
    <?php elseif ($step === 'pin'): ?>
        <p class="eyebrow">PIN</p>
        <h1>Digite o PIN</h1>
        <p class="lede">Enviamos um PIN de 6 números para <?= e($email) ?>.</p>
    <?php else: ?>
        <p class="eyebrow">Acesso</p>
        <h1>Digite seu e&#8209;mail</h1>
        <p class="lede">Enviaremos um PIN de 6 números para você criar uma senha nova.</p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="flash flash-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 'email'): ?>
        <form method="post" action="/esqueci-senha" class="form" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="acao" value="enviar">
            <label>
                E-mail
                <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required>
            </label>
            <button type="submit" class="button">OK</button>
        </form>
    <?php elseif ($step === 'pin'): ?>
        <form id="pinForm" method="post" action="/esqueci-senha" class="form" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="acao" value="verificar">
            <label>
                PIN
                <span class="pin-boxes">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                        <input
                            class="pin-digit"
                            type="text"
                            name="pin[]"
                            inputmode="numeric"
                            autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                            maxlength="1"
                            pattern="[0-9]"
                            aria-label="Dígito <?= $i + 1 ?> do PIN"
                            required
                        >
                    <?php endfor; ?>
                </span>
            </label>
            <button type="submit" class="button">OK</button>
        </form>

        <form method="post" action="/esqueci-senha" class="form">
            <?= csrfField() ?>
            <input type="hidden" name="acao" value="reenviar">
            <button
                id="resendEmailButton"
                type="submit"
                class="button button-resend"
                data-wait="<?= $resendWait ?>"
                <?= $resendWait > 0 ? 'disabled' : '' ?>
            >Reenviar o e-mail<?= $resendWait > 0 ? ' (' . $resendWait . 's)' : '' ?></button>
        </form>

        <form method="post" action="/esqueci-senha">
            <?= csrfField() ?>
            <input type="hidden" name="acao" value="reiniciar">
            <p class="auth-switch"><button type="submit" class="link-button">Usar outro e-mail</button></p>
        </form>
    <?php else: ?>
        <form id="resetPasswordForm" method="post" action="/esqueci-senha" class="form" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="acao" value="salvar">
            <label>
                Nova senha
                <div id="passwordInputDiv">
                    <input id="passwordInput" type="password" name="password" minlength="8" maxlength="128" autocomplete="new-password" required>
                    <img id="password-visibility-icon" src="/assets/img/login-eye-open.png" alt="Mostrar senha">
                </div>
                <span class="hint">Mínimo de 8 caracteres, com pelo menos um número</span>
                <div id="passwordErrorMessage" class="<?= $passwordError !== '' ? '' : 'hidden' ?>">
                    <img class="message-icon-img" src="/assets/img/yellow_attention_icon.png" alt="">
                    <span class="hint register-input-msg register-input-error" id="passwordErrorTxt"><?= e($passwordError) ?></span>
                </div>
            </label>
            <label>
                Confirmar senha
                <div id="passwordConfirmInputDiv" class="password-field">
                    <input id="passwordConfirmInput" type="password" name="password_confirmation" minlength="8" maxlength="128" autocomplete="new-password" required>
                    <img id="password-confirm-visibility-icon" class="password-visibility-icon" src="/assets/img/login-eye-open.png" alt="Mostrar senha">
                </div>
                <div id="passwordConfirmErrorMessage" class="<?= $passwordConfirmError !== '' ? '' : 'hidden' ?>">
                    <img class="message-icon-img" src="/assets/img/yellow_attention_icon.png" alt="">
                    <span class="hint register-input-msg register-input-error" id="passwordConfirmErrorTxt"><?= e($passwordConfirmError !== '' ? $passwordConfirmError : 'As senhas não combinam.') ?></span>
                </div>
            </label>
            <button id="submitResetPasswordButton" type="button" class="button">Salvar senha</button>
        </form>
    <?php endif; ?>

    <p class="auth-switch"><a href="/login">Voltar para entrar</a></p>
</section>

<?php if ($step === 'pin'): ?>
    <script type="module" src="/assets/js/forgot-password.js"></script>
<?php elseif ($step === 'password'): ?>
    <script type="module" src="/assets/js/forgot-password.js"></script>
    <script type="module" src="/assets/js/toggle-password-visibility.js?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/js/toggle-password-visibility.js') ?>"></script>
<?php endif; ?>
