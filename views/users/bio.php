<?php
/** @var array $user */
/** @var string $nickname */
/** @var string $realName */
/** @var string $bio */
/** @var list<string> $errors */
$photoUrl = userPhotoUrl($user);
?>
<section class="page-head">
    <p class="eyebrow">Perfil</p>
    <h1>Configurações de Bio</h1>
    <p class="lede">Atualize o apelido, o nome real, a bio e a foto que aparecem no clube.</p>
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
            <label>
                Foto de perfil
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif">
                <span class="hint">JPG, PNG, WEBP ou GIF. Máximo de 2 MB.</span>
            </label>
        </div>

        <label>
            Apelido
            <input type="text" name="nickname" value="<?= e($nickname) ?>" maxlength="40" autocomplete="nickname" required>
        </label>
        <label>
            Nome real
            <input type="text" name="real_name" value="<?= e($realName) ?>" maxlength="80" autocomplete="name" required>
        </label>
        <label>
            Bio
            <textarea name="bio" rows="5" maxlength="500" placeholder="Conte um pouco sobre você no clube."><?= e($bio) ?></textarea>
            <span class="hint">Até 500 caracteres.</span>
        </label>
        <button type="submit" class="button">Salvar perfil</button>
    </form>
</section>
