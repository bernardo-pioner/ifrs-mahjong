
<?php

$tiposEvento = [
    'encontro' => 'Encontro casual',
    'oficina' => 'Oficina',
    'torneio' => 'Torneio',
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
            </span>

            <span class="event-card-id">
                #<?= $eventoId ?>
            </span>
        </div>

        <div class="event-details">
            <div class="event-detail">
                <span class="icon icon-calendar" aria-hidden="true"></span>
                <span>
                    <strong>Início:</strong>
                    <?= e($formatarData($evento['data_inicio'])) ?>

                    <br>

                    <strong>Término:</strong>
                    <?= e($formatarData($evento['data_fim'] ?? null)) ?>
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
        </div>

        <?php if (!empty($evento['descricao'])): ?>
            <div class="form-field">
                <h2>Descrição</h2>
                <p><?= nl2br(e($evento['descricao'])) ?></p>
            </div>
        <?php endif; ?>

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
