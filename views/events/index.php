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
?>

<section class="events-page">
    <header class="events-header">
        <div>
            <h1>Eventos</h1>
            <p class="events-subtitle">
                Organize encontros, oficinas e torneios do clube.
            </p>
        </div>

        <a class="button button-primary" href="/eventos/novo">
            <span aria-hidden="true">＋</span>
            Novo evento
        </a>
    </header>

    <div class="events-summary">
        <div>
            <span class="events-summary-label">Eventos cadastrados</span>
            <strong><?= count($eventos) ?></strong>
        </div>
        <span class="icon icon-calendar" aria-hidden="true"></span>
    </div>

    <?php if (empty($eventos)): ?>
        <section class="events-empty">
            <div class="events-empty-icon" aria-hidden="true">
                <span class="icon icon-calendar"></span>
            </div>

            <h2>Nenhum evento por enquanto</h2>
            <p>
                Crie o primeiro evento do clube e reúna a comunidade
                para jogar Mahjong.
            </p>

            <a class="button button-primary" href="/eventos/novo">
                Criar primeiro evento
            </a>
        </section>
    <?php else: ?>
        <div class="events-grid">
            <?php foreach ($eventos as $evento): ?>
                <article class="event-card">
                    <div class="event-card-top">
                        <span class="event-type">
                            <?= e($tiposEvento[$evento['tipo']] ?? 'Outro') ?>
                        </span>

                        <span class="event-card-id">
                            #<?= (int) $evento['id'] ?>
                        </span>
                    </div>

                    <h2><?= e($evento['titulo']) ?></h2>

                    <?php if (!empty($evento['descricao'])): ?>
                        <p class="event-description">
                            <?= e($evento['descricao']) ?>
                        </p>
                    <?php endif; ?>

                    <div class="event-details">
                        <div class="event-detail">
                            <span class="icon icon-calendar" aria-hidden="true"></span>
                            <span>
                                <?= e($formatarData($evento['data_inicio'])) ?>
                            </span>
                        </div>

                        <?php if (!empty($evento['local'])): ?>
                            <div class="event-detail">
                                <span class="icon icon-map-pin" aria-hidden="true"></span>
                                <span><?= e($evento['local']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <footer class="event-card-footer">
                        <span class="event-organizer">
                            <?= e($evento['organizador'] ?? 'Clube de Mahjong') ?>
                        </span>

                        <a
                            class="event-action"
                            href="/eventos/<?= (int) $evento['id'] ?>"
                            aria-label="Ver evento <?= e($evento['titulo']) ?>"
                        >
                            Ver detalhes
                            <span aria-hidden="true">→</span>
                        </a>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>