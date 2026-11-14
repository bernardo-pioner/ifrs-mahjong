<?php
/** @var list<array<string, mixed>> $matches */
?>

<section class="page-head">
    <h1>Partidas</h1>
    <p class="lede">
        Partidas em que você jogou. Quem estava na mesa também vê o mesmo registro.
    </p>
</section>

<?php if ($matches === []): ?>
    <div class="empty">
        <p>Você ainda não está em nenhuma partida registrada.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Evento</th>
                    <th>Modalidade</th>
                    <th>Mesa</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matches as $match): ?>
                    <?php
                    $formato = (string) ($match['formato'] ?? '');
                    $modalidade = $formato !== ''
                        ? eventModalidadeLabel($formato)
                        : (string) ($match['modalidade'] ?? '');
                    $quando = '—';
                    if (!empty($match['criada_em'])) {
                        try {
                            $quando = (new DateTimeImmutable((string) $match['criada_em']))->format('d/m/Y · H:i');
                        } catch (Exception) {
                            $quando = (string) $match['criada_em'];
                        }
                    }
                    $nomes = [];
                    foreach ($match['jogadores'] as $jogador) {
                        $nomes[] = (string) $jogador['nome']
                            . ($jogador['pontuacao'] === null ? '' : ' ' . formatarPontuacao($jogador['pontuacao']));
                    }
                    ?>
                    <tr>
                        <td><?= e($quando) ?></td>
                        <td>
                            <?php if (!empty($match['evento_id'])): ?>
                                <a href="/eventos/<?= (int) $match['evento_id'] ?>"><?= e((string) ($match['evento'] ?? 'Evento')) ?></a>
                            <?php else: ?>
                                <?= e((string) ($match['evento'] ?? '—')) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e($modalidade) ?></td>
                        <td><?= e(implode(' · ', $nomes)) ?></td>
                        <td><a href="/partidas/<?= (int) $match['id'] ?>">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
