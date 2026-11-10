<?php
/** @var list<array<string, mixed>> $matches */
?>

<section class="page-head">
    <h1>Partidas</h1>
    <p class="lede">
        Mesas avulsas ou ligadas a um evento serão lançadas aqui.
    </p>
</section>

<?php if (empty($matches)): ?>
    <div class="empty">
        <p>Nenhuma partida registrada.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Modalidade</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Mesa</th>
                    <th>Encontro</th>
                    <th>Duração</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matches as $match): ?>
                    <tr>
                        <td><?= e($match['id']) ?></td>
                        <td><?= e($match['modalidade']) ?></td>
                        <td>
                            <?= $match['tipo'] === 'casual'
                                ? 'Casual'
                                : 'Competitiva' ?>
                        </td>
                        <td><?= e($match['status']) ?></td>
                        <td><?= e($match['mesa'] ?? '—') ?></td>
                        <td><?= e($match['encontro'] ?? '—') ?></td>
                        <td>
                            <?= isset($match['duracao_minutos'])
                                ? e($match['duracao_minutos']) . ' min'
                                : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
