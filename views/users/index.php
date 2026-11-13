<?php
/** @var list<array<string, mixed>> $users */
/** @var array<string, mixed> $user */

$currentUserId = (int) ($user['id'] ?? 0);
$levelLabels = levelLabels();
?>
<section class="page-head">
    <h1>Usuários</h1>
</section>

<?php if ($users === []): ?>
    <div class="empty">
        <p>Nenhum usuário cadastrado.</p>
    </div>
<?php else: ?>

    <div class="user-filters">
        <div class="user-search">
            <label for="user-search-input">Pesquisar</label>
            <input
                type="search"
                id="user-search-input"
                placeholder="Nome ou e-mail..."
                autocomplete="off"
            >
        </div>

        <div class="user-status-filter">
            <label for="user-status-select">Status</label>
            <select id="user-status-select">
                <option value="todos">Todos</option>
                <option value="ativo">Ativos</option>
                <option value="inativo">Inativos</option>
            </select>
        </div>

        <div class="user-role-filter">
            <label for="user-role-select">Permissão</label>
            <select id="user-role-select">
                <option value="todos">Todas</option>
                <option value="admin">Administradores</option>
                <option value="participante">Participantes</option>
            </select>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Apelido</th>
                    <th>E-mail</th>
                    <th>Permissão</th>
                    <th>Status</th>
                    <th class="actions-column">Ações</th>
                </tr>
            </thead>

            <tbody id="users-table-body">
                <?php foreach ($users as $row): ?>
                    <?php
                    $isAdmin = ($row['role'] ?? '') === 'admin';
                    $isActive = (int) ($row['active'] ?? 0) === 1;
                    $username = (string) ($row['username'] ?? '');
                    $email = (string) ($row['email'] ?? '');
                    $name = trim((string) ($row['name'] ?? ''));
                    $nickname = trim((string) ($row['nickname'] ?? ''));
                    if ($nickname === '') {
                        $nickname = $username;
                    }
                    $bio = trim((string) ($row['bio'] ?? ''));
                    $mahjongSoul = trim((string) ($row['mahjong_soul'] ?? ''));
                    $riichiCity = trim((string) ($row['riichi_city'] ?? ''));
                    $discord = trim((string) ($row['discord'] ?? ''));
                    $level = (string) ($row['level'] ?? '');
                    $levelLabel = $levelLabels[$level] ?? '';
                    $memberSince = formatMemberSince($row['created_at'] ?? null);
                    $photoUrl = userPhotoUrl($row);
                    $rowId = (int) $row['id'];
                    $isSelf = $rowId === $currentUserId;
                    ?>

                    <tr
                        data-name="<?= e($username) ?>"
                        data-email="<?= e($email) ?>"
                        data-status="<?= $isActive ? 'ativo' : 'inativo' ?>"
                        data-role="<?= $isAdmin ? 'admin' : 'participante' ?>"
                    >
                        <td>
                            <div class="user-table-identity">
                                <span class="user-table-avatar" aria-hidden="true">
                                    <?php if ($photoUrl): ?>
                                        <img src="<?= e($photoUrl) ?>" alt="">
                                    <?php else: ?>
                                        <span class="icon icon-user"></span>
                                    <?php endif; ?>
                                </span>
                                <button type="button" class="user-table-name">
                                    <?= e($username) ?>
                                </button>
                            </div>
                        </td>
                        <td><?= e($email) ?></td>
                        <td><?= $isAdmin ? 'Administrador' : 'Participante' ?></td>
                        <td>
                            <span class="status <?= $isActive ? 'is-on' : 'is-off' ?>">
                                <?= $isActive ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="actions-column">
                            <button
                                type="button"
                                class="user-actions-trigger"
                                aria-haspopup="menu"
                                aria-expanded="false"
                                aria-label="Ações de <?= e($username) ?>"
                                data-user-id="<?= $rowId ?>"
                                data-username="<?= e($username) ?>"
                                data-nickname="<?= e($nickname) ?>"
                                data-real-name="<?= e($name) ?>"
                                data-email="<?= e($email) ?>"
                                data-bio="<?= e($bio) ?>"
                                data-mahjong-soul="<?= e($mahjongSoul) ?>"
                                data-riichi-city="<?= e($riichiCity) ?>"
                                data-discord="<?= e($discord) ?>"
                                data-level="<?= e($levelLabel) ?>"
                                data-member-since="<?= e($memberSince) ?>"
                                data-photo="<?= e($photoUrl ?? '') ?>"
                                data-role="<?= $isAdmin ? 'admin' : 'participante' ?>"
                                data-status="<?= $isActive ? 'ativo' : 'inativo' ?>"
                                data-self="<?= $isSelf ? '1' : '0' ?>"
                            >
                                <span class="user-actions-dots" aria-hidden="true"></span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr id="users-no-results" hidden>
                    <td colspan="5">
                        Nenhum usuário encontrado com esses critérios.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div
        id="user-actions-menu"
        class="user-actions-menu"
        role="menu"
        hidden
    >
        <button type="button" class="user-actions-item" role="menuitem" data-action="details">
            Ver mais detalhes
        </button>
        <a
            href="#"
            class="user-actions-item"
            role="menuitem"
            id="user-edit-link"
            data-action="edit"
        >
            Editar perfil
        </a>
        <form
            method="post"
            action="/usuarios/permissao"
            class="user-actions-form"
            id="user-role-form"
        >
            <?= csrfField() ?>
            <input type="hidden" name="id" id="user-role-id" value="">
            <input type="hidden" name="action" id="user-role-action" value="">
            <button type="submit" class="user-actions-item" role="menuitem" id="user-role-button">
                Dar permissão de admin
            </button>
        </form>
        <form
            method="post"
            action="/usuarios/status"
            class="user-actions-form"
            id="user-status-form"
        >
            <?= csrfField() ?>
            <input type="hidden" name="id" id="user-status-id" value="">
            <input type="hidden" name="action" id="user-status-action" value="">
            <button type="submit" class="user-actions-item" role="menuitem" id="user-status-button">
                Desativar usuário
            </button>
        </form>
    </div>

    <div
        id="user-details-modal"
        class="user-details-modal"
        hidden
        aria-hidden="true"
    >
        <div class="user-details-backdrop" data-close-details></div>
        <div
            class="user-details-dialog profile-card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="user-details-title"
        >
            <button
                type="button"
                class="user-details-close"
                aria-label="Fechar detalhes"
                data-close-details
            >
                ×
            </button>

            <div class="profile-photo-field user-details-header">
                <span class="profile-photo-preview" aria-hidden="true">
                    <img id="user-details-photo" src="" alt="" hidden>
                    <span class="icon icon-user" id="user-details-photo-fallback"></span>
                </span>
                <div class="user-details-heading">
                    <h2 id="user-details-title"></h2>
                    <p id="user-details-username" class="lede"></p>
                    <p id="user-details-member-since" class="member-since"></p>
                </div>
            </div>

            <div class="profile-current-data">
                <div>
                    <span class="profile-label">Nome</span>
                    <p id="user-details-name"></p>
                </div>
                <div>
                    <span class="profile-label">E-mail</span>
                    <p id="user-details-email"></p>
                </div>
                <div>
                    <span class="profile-label">Permissão</span>
                    <p id="user-details-role"></p>
                </div>
                <div>
                    <span class="profile-label">Status</span>
                    <p>
                        <span id="user-details-status" class="status"></span>
                    </p>
                </div>
                <div>
                    <span class="profile-label">Nível</span>
                    <p id="user-details-level"></p>
                </div>
                <div>
                    <span class="profile-label">ID/Nome Mahjong Soul</span>
                    <p id="user-details-mahjong-soul"></p>
                </div>
                <div>
                    <span class="profile-label">ID/Nome Riichi City</span>
                    <p id="user-details-riichi-city"></p>
                </div>
                <div>
                    <span class="profile-label">Discord</span>
                    <p id="user-details-discord"></p>
                </div>
            </div>

            <div class="user-details-bio">
                <span class="profile-label">Bio</span>
                <p id="user-details-bio"></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('user-search-input');
            const statusSelect = document.getElementById('user-status-select');
            const roleSelect = document.getElementById('user-role-select');
            const tableBody = document.getElementById('users-table-body');
            const noResults = document.getElementById('users-no-results');
            const actionsMenu = document.getElementById('user-actions-menu');
            const detailsModal = document.getElementById('user-details-modal');
            const roleIdInput = document.getElementById('user-role-id');
            const roleActionInput = document.getElementById('user-role-action');
            const roleButton = document.getElementById('user-role-button');
            const statusIdInput = document.getElementById('user-status-id');
            const statusActionInput = document.getElementById('user-status-action');
            const statusButton = document.getElementById('user-status-button');
            const editLink = document.getElementById('user-edit-link');

            if (!searchInput || !statusSelect || !roleSelect || !tableBody || !noResults || !actionsMenu || !detailsModal) {
                return;
            }

            const userRows = Array.from(tableBody.querySelectorAll('tr[data-name]'));
            let activeTrigger = null;

            function filterUsers() {
                const search = searchInput.value.trim().toLocaleLowerCase('pt-BR');
                const selectedStatus = statusSelect.value;
                const selectedRole = roleSelect.value;
                let visibleCount = 0;

                userRows.forEach(function (row) {
                    const name = (row.dataset.name || '').toLocaleLowerCase('pt-BR');
                    const email = (row.dataset.email || '').toLocaleLowerCase('pt-BR');
                    const status = row.dataset.status || '';
                    const role = row.dataset.role || '';

                    const matchesSearch = name.includes(search) || email.includes(search);
                    const matchesStatus = selectedStatus === 'todos' || status === selectedStatus;
                    const matchesRole = selectedRole === 'todos' || role === selectedRole;
                    const visible = matchesSearch && matchesStatus && matchesRole;

                    row.hidden = !visible;
                    if (visible) {
                        visibleCount++;
                    }
                });

                noResults.hidden = visibleCount > 0;
            }

            function closeActionsMenu() {
                actionsMenu.hidden = true;
                if (activeTrigger) {
                    activeTrigger.setAttribute('aria-expanded', 'false');
                    activeTrigger = null;
                }
            }

            function openActionsMenu(trigger) {
                const isAdmin = trigger.dataset.role === 'admin';
                const isActive = trigger.dataset.status === 'ativo';
                const isSelf = trigger.dataset.self === '1';
                const userId = trigger.dataset.userId || '';

                roleIdInput.value = userId;
                statusIdInput.value = userId;
                if (editLink) {
                    editLink.href = '/usuarios/editar?id=' + encodeURIComponent(userId);
                }

                if (isAdmin) {
                    roleActionInput.value = 'demote';
                    roleButton.textContent = 'Remover permissão de admin';
                } else {
                    roleActionInput.value = 'promote';
                    roleButton.textContent = 'Dar permissão de admin';
                }

                if (isActive) {
                    statusActionInput.value = 'deactivate';
                    statusButton.textContent = 'Desativar usuário';
                } else {
                    statusActionInput.value = 'activate';
                    statusButton.textContent = 'Ativar usuário';
                }

                roleButton.disabled = isSelf;
                statusButton.disabled = isSelf;
                roleButton.hidden = isSelf;
                statusButton.hidden = isSelf;

                const rect = trigger.getBoundingClientRect();
                actionsMenu.hidden = false;

                const menuWidth = actionsMenu.offsetWidth;
                const menuHeight = actionsMenu.offsetHeight;
                let left = rect.right - menuWidth;
                let top = rect.bottom + 6;

                if (left < 8) {
                    left = 8;
                }
                if (left + menuWidth > window.innerWidth - 8) {
                    left = window.innerWidth - menuWidth - 8;
                }
                if (top + menuHeight > window.innerHeight - 8) {
                    top = rect.top - menuHeight - 6;
                }

                actionsMenu.style.left = left + 'px';
                actionsMenu.style.top = top + 'px';

                if (activeTrigger && activeTrigger !== trigger) {
                    activeTrigger.setAttribute('aria-expanded', 'false');
                }

                activeTrigger = trigger;
                trigger.setAttribute('aria-expanded', 'true');
            }

            function fillText(id, value, fallback) {
                const el = document.getElementById(id);
                if (!el) {
                    return;
                }
                const text = (value || '').trim();
                el.textContent = text !== '' ? text : fallback;
                el.classList.toggle('is-empty', text === '');
            }

            function openDetailsModal(trigger) {
                const nickname = (trigger.dataset.nickname || trigger.dataset.username || '').trim();
                const realName = (trigger.dataset.realName || '').trim();
                const photo = trigger.dataset.photo || '';
                const isActive = trigger.dataset.status === 'ativo';
                const photoImg = document.getElementById('user-details-photo');
                const photoFallback = document.getElementById('user-details-photo-fallback');
                const statusEl = document.getElementById('user-details-status');
                const memberSinceEl = document.getElementById('user-details-member-since');

                fillText('user-details-title', realName !== '' ? realName : nickname, 'Usuário');
                fillText(
                    'user-details-username',
                    nickname !== '' ? '@' + nickname : '',
                    ''
                );

                if (memberSinceEl) {
                    const since = (trigger.dataset.memberSince || '').trim();
                    memberSinceEl.textContent = since !== '' ? 'Membro desde ' + since : '';
                    memberSinceEl.hidden = since === '';
                }

                fillText('user-details-name', trigger.dataset.realName, 'Não informado');
                fillText('user-details-email', trigger.dataset.email, 'Não informado');
                fillText(
                    'user-details-role',
                    trigger.dataset.role === 'admin' ? 'Administrador' : 'Participante',
                    'Participante'
                );

                if (statusEl) {
                    statusEl.textContent = isActive ? 'Ativo' : 'Inativo';
                    statusEl.className = 'status ' + (isActive ? 'is-on' : 'is-off');
                }

                fillText('user-details-level', trigger.dataset.level, 'Não informado');
                fillText('user-details-mahjong-soul', trigger.dataset.mahjongSoul, 'Não informado');
                fillText('user-details-riichi-city', trigger.dataset.riichiCity, 'Não informado');
                fillText('user-details-discord', trigger.dataset.discord, 'Não informado');
                fillText('user-details-bio', trigger.dataset.bio, 'Este usuário ainda não escreveu uma bio.');

                if (photoImg && photoFallback) {
                    if (photo) {
                        photoImg.src = photo;
                        photoImg.hidden = false;
                        photoFallback.hidden = true;
                    } else {
                        photoImg.removeAttribute('src');
                        photoImg.hidden = true;
                        photoFallback.hidden = false;
                    }
                }

                closeActionsMenu();
                document.body.appendChild(detailsModal);
                detailsModal.hidden = false;
                detailsModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('user-details-open');
            }

            function closeDetailsModal() {
                detailsModal.hidden = true;
                detailsModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('user-details-open');
            }

            tableBody.addEventListener('click', function (event) {
                const nameButton = event.target.closest('.user-table-name');
                if (nameButton) {
                    event.stopPropagation();
                    const detailsTrigger = nameButton.closest('tr')?.querySelector('.user-actions-trigger');
                    if (detailsTrigger) {
                        openDetailsModal(detailsTrigger);
                    }
                    return;
                }

                const trigger = event.target.closest('.user-actions-trigger');
                if (!trigger) {
                    return;
                }

                event.stopPropagation();

                if (activeTrigger === trigger && !actionsMenu.hidden) {
                    closeActionsMenu();
                    return;
                }

                openActionsMenu(trigger);
            });

            actionsMenu.addEventListener('click', function (event) {
                const detailsButton = event.target.closest('[data-action="details"]');
                if (!detailsButton || !activeTrigger) {
                    return;
                }
                openDetailsModal(activeTrigger);
            });

            detailsModal.addEventListener('click', function (event) {
                if (event.target.closest('[data-close-details]')) {
                    closeDetailsModal();
                }
            });

            document.addEventListener('click', function (event) {
                if (
                    !actionsMenu.hidden &&
                    !event.target.closest('#user-actions-menu') &&
                    !event.target.closest('.user-actions-trigger')
                ) {
                    closeActionsMenu();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape') {
                    return;
                }
                if (!detailsModal.hidden) {
                    closeDetailsModal();
                    return;
                }
                closeActionsMenu();
            });

            window.addEventListener('resize', closeActionsMenu);
            window.addEventListener('scroll', closeActionsMenu, true);

            searchInput.addEventListener('input', filterUsers);
            statusSelect.addEventListener('change', filterUsers);
            roleSelect.addEventListener('change', filterUsers);
        });
    </script>

<?php endif; ?>
