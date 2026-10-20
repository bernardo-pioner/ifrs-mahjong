
<?php
/** @var list<array<string, mixed>> $users */
?>
<section class="page-head">
    <h1>Usuários</h1>
    <p class="lede">Contas e permissões de acesso do clube.</p>
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
                    <th>Nome</th>
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
                    $name = (string) ($row['name'] ?? '');
                    $email = (string) ($row['email'] ?? '');
                    ?>

                    <tr
                        data-name="<?= e($name) ?>"
                        data-email="<?= e($email) ?>"
                        data-status="<?= $isActive ? 'ativo' : 'inativo' ?>"
                        data-role="<?= $isAdmin ? 'admin' : 'participante' ?>"
                    >
                        <td>
                            <?= e($name) ?>
                        </td>

                        <td>
                            <?= e($email) ?>
                        </td>

                        <td>
                            <?= $isAdmin ? 'Administrador' : 'Participante' ?>
                        </td>

                        <td>
                            <span class="status <?= $isActive ? 'is-on' : 'is-off' ?>">
                                <?= $isActive ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>

                        <td class="actions-column">
                            <a
                                href="/usuarios/editar?id=<?= (int) $row['id'] ?>"
                                class="btn-view-user"
                                title="Editar perfil"
                                aria-label="Editar perfil de <?= e($name) ?>"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M12 20h9"/>
                                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/>
                                </svg>
                            </a>
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('user-search-input');
            const statusSelect = document.getElementById('user-status-select');
            const roleSelect = document.getElementById('user-role-select');
            const tableBody = document.getElementById('users-table-body');
            const noResults = document.getElementById('users-no-results');

            if (!searchInput || !statusSelect || !roleSelect || !tableBody || !noResults) {
                return;
            }

            const userRows = Array.from(
                tableBody.querySelectorAll('tr[data-name]')
            );

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

                    const matchesSearch =
                        name.includes(search) || email.includes(search);

                    const matchesStatus =
                        selectedStatus === 'todos' || status === selectedStatus;

                    const matchesRole =
                        selectedRole === 'todos' || role === selectedRole;

                    const visible = matchesSearch && matchesStatus && matchesRole;

                    row.hidden = !visible;

                    if (visible) {
                        visibleCount++;
                    }
                });

                noResults.hidden = visibleCount > 0;
            }

            searchInput.addEventListener('input', filterUsers);
            statusSelect.addEventListener('change', filterUsers);
            roleSelect.addEventListener('change', filterUsers);
        });

    </script>

<?php endif; ?>
