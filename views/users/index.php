<?php
/** @var list<array<string, mixed>> $users */
?>
<section class="page-head">
    <h1> Usuários</h1>
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
                        <td><?= e((string) ($row['name'] ?? '')) ?></td>
                        <td><?= e((string) ($row['email'] ?? '')) ?></td>
                        <td><?= $isAdmin ? 'Administrador' : 'Participante' ?></td>
                        <td>
                            <span class="status <?= $isActive ? 'is-on' : 'is-off' ?>">
                                <?= $isActive ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>

                        <td class="actions-column">
                            <form
                                method="POST"
                                action="/users/delete"
                                onsubmit="return confirm('Tem certeza que deseja excluir este usuário? Esta ação não poderá ser desfeita.');"
                            >
                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $row['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['csrf_token'] ?? '') ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn-delete-user"
                                    title="Excluir usuário"
                                    aria-label="Excluir usuário"
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
                                        <path d="M3 6h18"/>
                                        <path d="M8 6V4h8v2"/>
                                        <path d="m19 6-1 14H6L5 6"/>
                                        <path d="M10 11v5"/>
                                        <path d="M14 11v5"/>
                                    </svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
