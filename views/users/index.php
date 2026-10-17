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

            <tbody>
                <?php foreach ($users as $row): ?>
                    <?php
                    $isAdmin = ($row['role'] ?? '') === 'admin';
                    $isActive = (int) ($row['active'] ?? 0) === 1;
                    ?>

                    <tr>
                        <td>
                            <?= e((string) ($row['name'] ?? '')) ?>
                        </td>

                        <td>
                            <?= e((string) ($row['email'] ?? '')) ?>
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
                                aria-label="Editar perfil de <?= e((string) ($row['name'] ?? '')) ?>"
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
            </tbody>
        </table>
    </div>
<?php endif; ?>
