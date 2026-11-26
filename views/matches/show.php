<?php
/** @var array<string, mixed> $match */

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
$eventoId = (int) ($match['evento_id'] ?? 0);
?>

<?php if ($eventoId > 0): ?>
    <a class="page-back" href="/eventos/<?= $eventoId ?>">← Voltar para o evento</a>
<?php else: ?>
    <a class="page-back" href="/partidas">← Voltar para partidas</a>
<?php endif; ?>

<section class="event-form-page">
    <header class="event-form-header">
        <h1>Partida</h1>
        <p><?= e((string) ($match['evento'] ?? 'Mesa avulsa')) ?></p>
    </header>

    <article class="event-form-card">
        <div class="event-details">
            <div class="event-detail">
                <span><strong>Modalidade:</strong> <?= e($modalidade) ?></span>
            </div>
            <div class="event-detail">
                <span><strong>Registrada em:</strong> <?= e($quando) ?></span>
            </div>
        </div>

        <div class="table-wrap match-scoreboard">
            <table class="data-table">
                <thead>
                        <tr>
                            <th>Colocação</th>
                            <th>Jogador</th>
                            <th>Pontuação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($match['jogadores'] as $jogador): ?>
                            <tr>
                                <td><?= $jogador['colocacao'] === null ? '—' : e((string) $jogador['colocacao']) . 'º' ?></td>
                                <td>
                                    <span class="match-player">
                                        <span class="user-table-avatar" aria-hidden="true">
                                            <?php if (!empty($jogador['photo_url'])): ?>
                                                <img src="<?= e((string) $jogador['photo_url']) ?>" alt="">
                                            <?php else: ?>
                                                <span class="icon icon-user"></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="match-player-label"><?= e((string) $jogador['nome']) ?></span>
                                        <?php if (!empty($jogador['convidado'])): ?>
                                            <span class="event-participant-status is-suplente">Convidado</span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td><?= e(formatarPontuacao($jogador['pontuacao'])) ?></td>
                            </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
