<?php

$tiposEvento = [
    'encontro' => 'Encontro casual',
    'oficina' => 'Oficina',
    'torneio' => 'Torneio',
];

$statusLabels = [
    'pendente' => 'Pendente',
    'aprovado' => 'Aprovado',
    'cancelado' => 'Cancelado',
];

$formatarData = static function (?string $data): string {
    if (!$data) {
        return 'Data a definir';
    }

    try {
        return (new DateTimeImmutable($data))->format('d/m/Y · H:i');
    } catch (Exception) {
        return $data;
    }
};

$ehAdmin = ($user['role'] ?? '') === 'admin';
$eventoId = (int) $evento['id'];
$taxa = (float) ($details['taxa'] ?? 0);
$limite = $details['limite'] ?? null;
$inscricoesAbertas = (int) ($evento['inscricoes_abertas'] ?? 0) === 1;
$precisaPagamento = in_array($evento['tipo'], ['torneio', 'oficina'], true) && $taxa > 0;
$jaInscrito = $minhaParticipacao !== null && ($minhaParticipacao['status'] ?? '') !== 'cancelado';
$podeInscrever = !$ehAdmin && $inscricoesAbertas && !$jaInscrito && $temVaga;

?>

<section class="event-form-page">
    <header class="event-form-header">
        <div>
            <a class="event-back-link" href="/eventos">
                ← Voltar para eventos
            </a>

            <h1><?= e($evento['titulo']) ?></h1>
            <p>Detalhes do evento do clube.</p>
        </div>
    </header>

    <article class="event-form-card">
        <div class="event-card-top">
            <span class="event-type">
                <?= e($tiposEvento[$evento['tipo']] ?? 'Outro') ?>
        </div>

        <div class="event-details">
            <div class="event-detail">
                <span class="icon icon-calendar" aria-hidden="true"></span>
                <span>
                    <strong>Início:</strong>
                    <?= e($formatarData($evento['data_inicio'])) ?>

                    <br>

                    <strong>Término:</strong>
                    <?= e(!empty($evento['data_fim']) ? $formatarData($evento['data_fim']) : 'Não informado') ?>
                </span>
            </div>

            <?php if (!empty($evento['local'])): ?>
                <div class="event-detail">
                    <span class="icon icon-map-pin" aria-hidden="true"></span>
                    <span>
                        <strong>Local:</strong>
                        <?= e($evento['local']) ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="event-detail">
                <span>
                    <strong>Organizador:</strong>
                    <?= e($evento['organizador'] ?? 'Clube de Mahjong') ?>
                </span>
            </div>

            <?php if ($limite !== null): ?>
                <div class="event-detail">
                    <span>
                        <strong>Vagas:</strong>
                        <?= (int) $vagasOcupadas ?> / <?= (int) $limite ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($precisaPagamento): ?>
                <div class="event-detail">
                    <span>
                        <strong>Taxa:</strong>
                        R$ <?= e(number_format($taxa, 2, ',', '.')) ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($evento['descricao'])): ?>
            <div class="form-field">
                <h2>Descrição</h2>
                <p><?= nl2br(e($evento['descricao'])) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$ehAdmin): ?>
            <div class="event-form-divider"></div>

            <section class="event-registration-box">
                <h2>Sua inscrição</h2>

                <?php if ($jaInscrito): ?>
                    <p>
                        Status:
                        <strong class="event-participant-status is-<?= e($minhaParticipacao['status']) ?>">
                            <?= e($statusLabels[$minhaParticipacao['status']] ?? $minhaParticipacao['status']) ?>
                        </strong>
                    </p>
                <?php elseif (!$inscricoesAbertas): ?>
                    <p>As inscrições para este evento estão fechadas.</p>
                <?php elseif (!$temVaga): ?>
                    <p>Não há mais vagas disponíveis neste evento.</p>
                <?php elseif ($podeInscrever): ?>
                    <form
                        method="post"
                        action="/eventos/<?= $eventoId ?>/inscrever"
                        enctype="multipart/form-data"
                        class="event-register-form"
                    >
                        <?= csrfField() ?>

                        <?php if ($precisaPagamento): ?>
                            <p>
                                Este evento exige pagamento de
                                <strong>R$ <?= e(number_format($taxa, 2, ',', '.')) ?></strong>.
                                Anexe o comprovante para concluir a inscrição.
                            </p>
                            <div class="form-field">
                                <label for="comprovante">Comprovante (PDF, PNG ou JPEG)</label>
                                <input
                                    id="comprovante"
                                    name="comprovante"
                                    type="file"
                                    accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg"
                                    required
                                >
                            </div>
                        <?php else: ?>
                            <p>Confirme sua inscrição neste evento.</p>
                        <?php endif; ?>

                        <button class="button button-primary" type="submit">
                            Inscrever-se
                        </button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <div class="event-form-divider"></div>

        <section class="event-participants">
            <h2>Participantes inscritos (<?= count($participantes ?? []) ?>)</h2>

            <?php if (empty($participantes)): ?>
                <p class="event-participants-empty">
                    Nenhum participante inscrito neste evento.
                </p>
            <?php else: ?>
                <div class="event-participants-list">
                    <?php foreach ($participantes as $participante): ?>
                        <div class="event-participant">
                            <div>
                                <strong>
                                    <?= e($participante['nome'] ?: ($participante['apelido'] ?? 'Participante')) ?>
                                </strong>

                                <?php if (!empty($participante['apelido'])): ?>
                                    <small>
                                        (<?= e($participante['apelido']) ?>)
                                    </small>
                                <?php endif; ?>

                                <div class="event-participant-meta">
                                    <span class="event-participant-status is-<?= e($participante['status']) ?>">
                                        <?= e($statusLabels[$participante['status']] ?? $participante['status']) ?>
                                    </span>

                                    <?php if (!empty($participante['pode_ver_comprovante'])): ?>
                                        <a
                                            href="/eventos/<?= $eventoId ?>/comprovantes/<?= (int) $participante['usuario_id'] ?>"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            Ver comprovante
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($ehAdmin && $participante['status'] === 'pendente'): ?>
                                <form
                                    method="post"
                                    action="/eventos/<?= $eventoId ?>/participantes/<?= (int) $participante['usuario_id'] ?>/status"
                                    class="event-status-form"
                                >
                                    <?= csrfField() ?>
                                    <input type="hidden" name="status" value="aprovado">
                                    <button class="button button-primary" type="submit">
                                        Aprovar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($ehAdmin): ?>
            <div class="event-form-divider"></div>

            <div class="event-form-actions">
                <a
                    class="button button-secondary"
                    href="/eventos/<?= $eventoId ?>/editar"
                >
                    Editar evento
                </a>

                <form
                    method="post"
                    action="/eventos/<?= $eventoId ?>/excluir"
                    onsubmit="return confirm('Tem certeza que deseja excluir este evento? Esta ação não poderá ser desfeita.');"
                >
                    <?= csrfField() ?>

                    <button
                        class="button button-secondary"
                        type="submit"
                    >
                        Excluir evento
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </article>
</section>
