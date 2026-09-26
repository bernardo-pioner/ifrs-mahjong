<?php
/** @var list<array<string, mixed>> $users */
?>
<section class="page-head">
    <h1>Usuários</h1>
    <p class="lede">Contas e papéis de acesso do clube.</p>
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
                    <th>Papel</th>
                    <th>Status</th>
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
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
