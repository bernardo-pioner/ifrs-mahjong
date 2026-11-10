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

    <form id="events-filters" class="events-filters" method="get" action="/eventos">
        <div class="events-filters-header">
            <h2>Filtrar eventos</h2>
            <p>Encontre eventos pelo nome, tipo ou situação.</p>
        </div>

        <div class="events-filters-grid">
            <div class="form-field">
                <label for="busca">Nome do evento</label>
                <input
                    type="search"
                    id="busca"
                    name="busca"
                    placeholder="Pesquisar pelo nome..."
                    value="<?= e($busca ?? '') ?>"
                >
            </div>

            <div class="form-field">
                <label for="tipo">Tipo de evento</label>
                <select id="tipo" name="tipo">
                    <option value="">Todos os tipos</option>
                    <option value="encontro" <?= ($tipoFiltro ?? '') === 'encontro' ? 'selected' : '' ?>>
                        Encontro casual
                    </option>
                    <option value="oficina" <?= ($tipoFiltro ?? '') === 'oficina' ? 'selected' : '' ?>>
                        Oficina
                    </option>
                    <option value="torneio" <?= ($tipoFiltro ?? '') === 'torneio' ? 'selected' : '' ?>>
                        Torneio
                    </option>
                </select>
            </div>

            <div class="form-field">
                <label for="situacao">Situação do evento</label>
                <select id="situacao" name="situacao">
                    <option value="">Todas as situações</option>
                    <option value="nao_iniciado" <?= ($situacaoFiltro ?? '') === 'nao_iniciado' ? 'selected' : '' ?>>
                        Não iniciado
                    </option>
                    <option value="em_andamento" <?= ($situacaoFiltro ?? '') === 'em_andamento' ? 'selected' : '' ?>>
                        Em andamento
                    </option>
                    <option value="encerrado" <?= ($situacaoFiltro ?? '') === 'encerrado' ? 'selected' : '' ?>>
                        Encerrado
                    </option>
                </select>
            </div>
        </div>

        <div class="events-filters-actions">
            <a class="button button-secondary" href="/eventos">
                Limpar filtros
            </a>
        </div>
    </form>

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
                                Início: <?= e($formatarData($evento['data_inicio'])) ?>
                                <?php if (!empty($evento['data_fim'])): ?>
                                    <br>
                                    Término: <?= e($formatarData($evento['data_fim'])) ?>
                                <?php endif; ?>
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


<script>
document.addEventListener('DOMContentLoaded', function () {
    const formulario = document.getElementById('events-filters');

    if (!formulario) return;

    const busca = document.getElementById('busca');
    const tipo = document.getElementById('tipo');
    const situacao = document.getElementById('situacao');

    let temporizador;

    function aplicarFiltros() {
        // Monta a URL com os filtros selecionados.
        const parametros = new URLSearchParams(
            new FormData(formulario)
        );

        // Remove filtros vazios da URL.
        for (const [chave, valor] of parametros.entries()) {
            if (!valor.trim()) {
                parametros.delete(chave);
            }
        }

        const url = '/eventos' +
            (parametros.toString() ? '?' + parametros.toString() : '');

        window.location.href = url;
    }

    // Pesquisa por nome após uma pequena pausa na digitação.
    busca.addEventListener('input', function () {
        clearTimeout(temporizador);

        temporizador = setTimeout(aplicarFiltros, 500);
    });

    // Atualização imediata ao selecionar tipo ou situação.
    tipo.addEventListener('change', aplicarFiltros);
    situacao.addEventListener('change', aplicarFiltros);
});
</script>
