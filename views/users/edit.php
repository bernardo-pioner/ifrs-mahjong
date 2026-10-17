
<?php
/** @var array<string, mixed> $targetUser */
/** @var list<string> $errors */

$isActive = (int) ($targetUser['active'] ?? 0) === 1;
?>

<section class="page-head">
    <h1>Editar perfil</h1>
    <p class="lede">
        Consulte os dados atuais e altere as informações do usuário.
    </p>
</section>

<div class="table-wrap profile-edit-card">

    <div class="profile-section">
        <h2>Dados atuais</h2>
        <p class="lede">
            Informações cadastradas atualmente no sistema.
        </p>

        <div class="profile-current-data">
            <div>
                <span class="profile-label">Nome atual</span>
                <p><?= e((string) ($targetUser['name'] ?? '')) ?></p>
            </div>

            <div>
                <span class="profile-label">E-mail atual</span>
                <p><?= e((string) ($targetUser['email'] ?? '')) ?></p>
            </div>

            <div>
                <span class="profile-label">Permissão atual</span>
                <p>
                    <?= ($targetUser['role'] ?? '') === 'admin'
                        ? 'Administrador'
                        : 'Participante' ?>
                </p>
            </div>

            <div>
                <span class="profile-label">Status atual</span>
                <span class="status <?= $isActive ? 'is-on' : 'is-off' ?>">
                    <?= $isActive ? 'Ativo' : 'Inativo' ?>
                </span>
            </div>
        </div>

        <div class="profile-status-action">
            <form
                method="post"
                action="/usuarios/status"
                onsubmit="return confirm('Tem certeza de que deseja <?= $isActive ? 'inativar' : 'reativar' ?> este perfil?');"
            >
                <?= csrfField() ?>

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $targetUser['id'] ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="<?= $isActive ? 'deactivate' : 'activate' ?>"
                >

                <button
                    type="submit"
                    class="button <?= $isActive ? 'button-danger' : 'button-reactivate' ?>"
                >
                    <?= $isActive ? 'Inativar perfil' : 'Reativar perfil' ?>
                </button>
            </form>
        </div>
    </div>

    <div class="profile-section">
        <h2>Novos dados</h2>
        <p class="lede">
            Preencha os campos abaixo para atualizar o perfil.
        </p>

        <?php if ($errors !== []): ?>
            <ul class="form-errors">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="/usuarios/editar" class="form" novalidate>
            <?= csrfField() ?>

            <input
                type="hidden"
                name="id"
                value="<?= (int) $targetUser['id'] ?>"
            >

            <label>
                Novo nome
                <input
                    type="text"
                    name="name"
                    value="<?= e((string) ($targetUser['name'] ?? '')) ?>"
                    autocomplete="name"
                    required
                >
            </label>

            <label>
                Novo e-mail
                <input
                    type="email"
                    name="email"
                    value="<?= e((string) ($targetUser['email'] ?? '')) ?>"
                    autocomplete="email"
                    required
                >
            </label>

            <div class="profile-actions">
                <a href="/usuarios" class="button button-secondary">
                    Cancelar
                </a>

                <button type="submit" class="button">
                    Salvar alterações
                </button>
            </div>
        </form>
    </div>

</div>
