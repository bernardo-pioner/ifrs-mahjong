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

<section class="event-form-page">
    <header class="event-form-header">
        <?php if ($eventoId > 0): ?>
            <a class="event-back-link" href="/eventos/<?= $eventoId ?>">← Voltar para o evento</a>
        <?php else: ?>
            <a class="event-back-link" href="/partidas">← Voltar para partidas</a>
        <?php endif; ?>
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

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Jogador</th>
                        <th>Pontuação</th>
                        <th>Colocação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($match['jogadores'] as $jogador): ?>
                        <tr>
                            <td>
                                <?= e((string) $jogador['nome']) ?>
                                <?php if (!empty($jogador['convidado'])): ?>
                                    <span class="event-participant-status is-suplente">Convidado</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(formatarPontuacao($jogador['pontuacao'])) ?></td>
                            <td><?= $jogador['colocacao'] === null ? '—' : e((string) $jogador['colocacao']) . 'º' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
