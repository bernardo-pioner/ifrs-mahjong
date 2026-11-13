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
    <button type="button" class="bio-back" id="bio-voltar">← Voltar</button>
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
        <input type="hidden" name="return_to" id="bio-return-to" value="">

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
                <span class="hint">JPG, PNG, WEBP ou GIF. Máximo de 2 MB. Depois de escolher, você ajusta o recorte.</span>
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
        <button type="button" class="button button-secondary" id="bio-cancelar">Cancelar</button>
    </form>
</section>

<div
    id="image-crop-modal"
    class="image-crop-modal"
    hidden
    aria-hidden="true"
>
    <div class="image-crop-backdrop" data-close-crop></div>
    <div
        class="image-crop-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="image-crop-title"
    >
        <button type="button" class="user-details-close" data-close-crop aria-label="Fechar">×</button>
        <h2 id="image-crop-title">Ajustar foto</h2>
        <p class="image-crop-help">Arraste para posicionar. Use o zoom e a rotação se precisar.</p>

        <div class="image-crop-layout">
            <div class="image-crop-stage" data-crop-stage data-shape="circle">
                <img data-crop-image alt="">
                <div class="image-crop-mask" data-crop-mask data-shape="circle" aria-hidden="true"></div>
            </div>

            <div class="image-crop-side">
                <span class="image-crop-side-label">Prévia</span>
                <div class="image-crop-previews">
                    <div class="image-crop-preview-card">
                        <canvas data-crop-preview-square width="96" height="96" aria-label="Prévia em quadrado"></canvas>
                        <span>Quadrado</span>
                    </div>
                    <div class="image-crop-preview-card">
                        <canvas
                            class="is-circle"
                            data-crop-preview-circle
                            width="96"
                            height="96"
                            aria-label="Prévia em círculo"
                        ></canvas>
                        <span>Círculo</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="image-crop-toolbar">
            <button type="button" class="image-crop-tool" data-crop-rotate="-90" aria-label="Girar para a esquerda" title="Girar para a esquerda">↺</button>
            <button type="button" class="image-crop-tool" data-crop-rotate="90" aria-label="Girar para a direita" title="Girar para a direita">↻</button>
            <span class="image-crop-toolbar-sep" aria-hidden="true"></span>
            <button type="button" class="image-crop-tool" data-crop-zoom="-0.15" aria-label="Diminuir zoom" title="Diminuir zoom">−</button>
            <button type="button" class="image-crop-tool" data-crop-zoom="0.15" aria-label="Aumentar zoom" title="Aumentar zoom">+</button>
            <span class="image-crop-toolbar-sep" aria-hidden="true"></span>
            <button
                type="button"
                class="image-crop-tool"
                data-crop-shape="square"
                aria-label="Recorte quadrado"
                aria-pressed="false"
                title="Quadrado"
            >
                <span class="image-crop-shape-icon is-square" aria-hidden="true"></span>
            </button>
            <button
                type="button"
                class="image-crop-tool is-active"
                data-crop-shape="circle"
                aria-label="Recorte circular"
                aria-pressed="true"
                title="Círculo"
            >
                <span class="image-crop-shape-icon is-circle" aria-hidden="true"></span>
            </button>
        </div>

        <div class="image-crop-actions">
            <button type="button" class="button button-secondary" data-close-crop>Cancelar</button>
            <button type="button" class="button button-primary" data-crop-apply>Aplicar</button>
        </div>
    </div>
</div>

<div
    id="bio-leave-modal"
    class="series-choice-modal"
    hidden
    aria-hidden="true"
>
    <div class="series-choice-backdrop" data-close-bio-leave></div>
    <div
        class="series-choice-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="bio-leave-title"
    >
        <button type="button" class="user-details-close" data-close-bio-leave aria-label="Fechar">×</button>
        <h2 id="bio-leave-title">Alterações não salvas</h2>
        <p>Deseja salvar as alterações antes de sair?</p>
        <div class="series-choice-actions">
            <button type="button" class="button button-secondary" id="bio-leave-no">Não</button>
            <button type="button" class="button button-primary" id="bio-leave-yes">Sim</button>
        </div>
    </div>
</div>

<script src="/assets/js/image-crop.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('.profile-card form');
        const voltar = document.getElementById('bio-voltar');
        const modal = document.getElementById('bio-leave-modal');
        const returnTo = document.getElementById('bio-return-to');
        if (!form || !voltar || !modal || !returnTo) {
            return;
        }

        function destinoVolta() {
            try {
                if (document.referrer) {
                    const url = new URL(document.referrer);
                    if (url.origin === window.location.origin && url.pathname !== '/perfil') {
                        const destino = url.pathname + url.search + url.hash;
                        sessionStorage.setItem('bio-voltar', destino);
                        return destino;
                    }
                }
            } catch (error) {
                // O endereço anterior fica indisponível; usa o destino guardado.
            }

            return sessionStorage.getItem('bio-voltar') || '/';
        }

        const destino = destinoVolta();

        function estadoFormulario() {
            const data = new FormData(form);
            data.delete('_csrf');
            data.delete('return_to');
            const partes = [];
            data.forEach(function (valor, chave) {
                if (chave === 'photo') {
                    return;
                }
                partes.push(chave + '=' + String(valor));
            });
            const foto = form.querySelector('input[name="photo"]');
            const arquivo = foto && foto.files && foto.files[0];
            partes.push('photo=' + (arquivo ? arquivo.name + ':' + arquivo.size : ''));
            return partes.join('&');
        }

        const estadoInicial = estadoFormulario();

        function formularioAlterado() {
            return estadoFormulario() !== estadoInicial;
        }

        function sair() {
            window.location.assign(destino);
        }

        function abrirModal() {
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('series-choice-open');
            const sim = document.getElementById('bio-leave-yes');
            if (sim) {
                sim.focus();
            }
        }

        function fecharModal() {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('series-choice-open');
        }

        function pedirSaida() {
            if (formularioAlterado()) {
                abrirModal();
                return;
            }
            sair();
        }

        voltar.addEventListener('click', pedirSaida);
        document.getElementById('bio-cancelar').addEventListener('click', pedirSaida);

        document.getElementById('bio-leave-no').addEventListener('click', function () {
            fecharModal();
            sair();
        });
        document.getElementById('bio-leave-yes').addEventListener('click', function () {
            returnTo.value = destino;
            fecharModal();
            form.submit();
        });
        modal.querySelectorAll('[data-close-bio-leave]').forEach(function (el) {
            el.addEventListener('click', fecharModal);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                fecharModal();
            }
        });
    });
</script>
