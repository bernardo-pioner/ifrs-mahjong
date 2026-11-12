<?php
/** @var array $user */
/** @var string $nickname */
/** @var string $realName */
/** @var string $bio */
/** @var string $mahjongSoul */
/** @var string $riichiCity */
/** @var string $discord */
/** @var string $memberSince */
/** @var list<string> $errors */
$photoUrl = userPhotoUrl($user);
?>
<section class="page-head">
    <p class="eyebrow">Perfil</p>
    <h1>Configurações de Bio</h1>
    </section>

<section class="profile-card">
    <?php if ($errors !== []): ?>
        <ul class="form-errors">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="/perfil" class="form" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>

        <div class="profile-photo-field">
            <span class="profile-photo-preview" aria-hidden="true">
                <?php if ($photoUrl): ?>
                    <img src="<?= e($photoUrl) ?>" alt="">
                <?php else: ?>
                    <span class="icon icon-user"></span>
                <?php endif; ?>
            </span>
            <div class="profile-photo-upload">
                <span class="file-field-label">Foto de perfil</span>
                <label class="file-picker" for="photo">
                    <input
                        id="photo"
                        type="file"
                        name="photo"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        data-file-picker
                    >
                    <span class="file-picker-button">Escolher arquivo</span>
                    <span class="file-picker-name" data-file-name>
                        Nenhum arquivo selecionado
                    </span>
                </label>
                <span class="hint">JPG, PNG, WEBP ou GIF. Máximo de 2 MB.</span>
            </div>
        </div>

        <?php if ($memberSince !== ''): ?>
            <p class="member-since">Membro desde <?= e($memberSince) ?></p>
        <?php endif; ?>

        <label>
            <span class="field-label">Apelido <span class="required-mark" aria-hidden="true">*</span></span>
            <input
                type="text"
                name="nickname"
                value="<?= e($nickname) ?>"
                minlength="3"
                maxlength="20"
                pattern="[A-Za-z0-9]{3,20}"
                autocomplete="username"
                required
            >
            <span class="hint">De 3 a 20 caracteres, apenas letras e números. Também é usado no login.</span>
        </label>
        <label>
            Nome
            <input
                type="text"
                name="real_name"
                value="<?= e($realName) ?>"
                maxlength="80"
                autocomplete="name"
            >
        </label>
        <label>
            Bio
            <textarea name="bio" rows="5" maxlength="500" placeholder="Conte um pouco sobre você no clube."><?= e($bio) ?></textarea>
            <span class="hint">Até 500 caracteres.</span>
        </label>
        <label>
            ID/Nome Mahjong Soul
            <input
                type="text"
                name="mahjong_soul"
                value="<?= e($mahjongSoul) ?>"
                maxlength="40"
                autocomplete="off"
            >
        </label>
        <label>
            ID/Nome Riichi City
            <input
                type="text"
                name="riichi_city"
                value="<?= e($riichiCity) ?>"
                maxlength="40"
                autocomplete="off"
            >
        </label>
        <label>
            Discord
            <input
                type="text"
                name="discord"
                value="<?= e($discord) ?>"
                maxlength="40"
                autocomplete="off"
            >
        </label>
        <button type="submit" class="button">Salvar perfil</button>
    </form>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-file-picker]').forEach(function (input) {
            const nameEl = input.closest('.file-picker')?.querySelector('[data-file-name]');
            if (!nameEl) {
                return;
            }

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                nameEl.textContent = file ? file.name : 'Nenhum arquivo selecionado';
            });
        });
    });
</script>
