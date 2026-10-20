
<?php
/** @var array<string, mixed> $targetUser */
/** @var list<string> $errors */

$isActive = (int) ($targetUser['active'] ?? 0) === 1;
$isAdmin = ($targetUser['role'] ?? '') === 'admin';

$pendingRole = $targetUser['pending_role'] ?? 'keep';
$pendingStatus = $targetUser['pending_status'] ?? 'keep';

$roleWillBeAdmin = $pendingRole === 'keep'
    ? $isAdmin
    : $pendingRole === 'promote';

$statusWillBeActive = $pendingStatus === 'keep'
    ? $isActive
    : $pendingStatus === 'activate';
?>

<section class="page-head">
    <h1>Editar perfil</h1>
    <p class="lede">
        Consulte os dados atuais e altere as informações do usuário.
    </p>
</section>

<div class="table-wrap profile-edit-card">

    <?php if ($errors !== []): ?>
        <ul class="form-errors">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form
        method="post"
        action="/usuarios/editar"
        class="form"
        id="edit-user-form"
        novalidate
    >
        <?= csrfField() ?>

        <input
            type="hidden"
            name="id"
            value="<?= (int) $targetUser['id'] ?>"
        >

        <input
            type="hidden"
            name="role_action"
            id="role-action"
            value="<?= e($pendingRole) ?>"
        >

        <input
            type="hidden"
            name="status_action"
            id="status-action"
            value="<?= e($pendingStatus) ?>"
        >

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
                    <span class="profile-label">Permissão</span>

                    <div class="profile-inline-info">
                        <p id="role-display">
                            <?= $roleWillBeAdmin
                                ? 'Administrador'
                                : 'Participante' ?>
                            <?php if ($pendingRole !== 'keep'): ?>
                                (alteração pendente)
                            <?php endif; ?>
                        </p>

                        <?php if ((int) $targetUser['id'] !== (int) $user['id']): ?>
                            <button
                                type="button"
                                id="role-toggle"
                                class="button <?= $isAdmin
                                    ? 'button-danger'
                                    : 'button-promote' ?>"
                            >
                                <?= $pendingRole !== 'keep'
                                    ? 'Desfazer'
                                    : ($isAdmin
                                        ? 'Remover privilégios de administrador'
                                        : 'Tornar administrador') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <span class="profile-label">Status</span>

                    <div class="profile-inline-info">
                        <span
                            id="status-display"
                            class="status <?= $statusWillBeActive
                                ? 'is-on'
                                : 'is-off' ?>"
                        >
                            <?= $statusWillBeActive ? 'Ativo' : 'Inativo' ?>
                            <?php if ($pendingStatus !== 'keep'): ?>
                                (alteração pendente)
                            <?php endif; ?>
                        </span>

                        <?php if ((int) $targetUser['id'] !== (int) $user['id']): ?>
                            <button
                                type="button"
                                id="status-toggle"
                                class="button <?= $isActive
                                    ? 'button-danger'
                                    : 'button-reactivate' ?>"
                            >
                                <?= $pendingStatus !== 'keep'
                                    ? 'Desfazer'
                                    : ($isActive
                                        ? 'Inativar perfil'
                                        : 'Reativar perfil') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-section">
            <h2>Novos dados</h2>
            <p class="lede">
                Preencha os campos abaixo para atualizar o perfil.
                As alterações só serão aplicadas ao salvar.
            </p>

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
        </div>
    </form>
</div>

<script>
(function () {
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
    const isActive = <?= $isActive ? 'true' : 'false' ?>;

    const roleAction = document.getElementById('role-action');
    const statusAction = document.getElementById('status-action');

    const roleButton = document.getElementById('role-toggle');
    const statusButton = document.getElementById('status-toggle');

    const roleDisplay = document.getElementById('role-display');
    const statusDisplay = document.getElementById('status-display');

    function updateRoleDisplay() {
        const makeAdmin = roleAction.value === 'promote'
            ? true
            : roleAction.value === 'demote'
                ? false
                : isAdmin;

        roleDisplay.textContent =
            (makeAdmin ? 'Administrador' : 'Participante') +
            (roleAction.value !== 'keep'
                ? ' (alteração pendente)'
                : '');

        if (roleButton) {
            roleButton.textContent = roleAction.value !== 'keep'
                ? 'Desfazer'
                : (isAdmin
                    ? 'Remover privilégios de administrador'
                    : 'Tornar administrador');

            roleButton.className = 'button ' +
                (roleAction.value !== 'keep'
                    ? 'button-undo'
                    : (isAdmin ? 'button-danger' : 'button-promote'));
        }
    }

    function updateStatusDisplay() {
        const active = statusAction.value === 'activate'
            ? true
            : statusAction.value === 'deactivate'
                ? false
                : isActive;

        statusDisplay.textContent =
            (active ? 'Ativo' : 'Inativo') +
            (statusAction.value !== 'keep'
                ? ' (alteração pendente)'
                : '');

        statusDisplay.className = 'status ' +
            (active ? 'is-on' : 'is-off');

        if (statusButton) {
            statusButton.textContent = statusAction.value !== 'keep'
                ? 'Desfazer'
                : (isActive ? 'Inativar perfil' : 'Reativar perfil');

            statusButton.className = 'button ' +
                (statusAction.value !== 'keep'
                    ? 'button-undo'
                    : (isActive ? 'button-danger' : 'button-reactivate'));
        }
    }

    if (roleButton) {
        roleButton.addEventListener('click', function () {
            if (roleAction.value !== 'keep') {
                roleAction.value = 'keep';
            } else {
                roleAction.value = isAdmin ? 'demote' : 'promote';
            }

            updateRoleDisplay();
        });
    }

    if (statusButton) {
        statusButton.addEventListener('click', function () {
            if (statusAction.value !== 'keep') {
                statusAction.value = 'keep';
            } else {
                statusAction.value = isActive
                    ? 'deactivate'
                    : 'activate';
            }

            updateStatusDisplay();
        });
    }

    updateRoleDisplay();
    updateStatusDisplay();
})();
</script>
