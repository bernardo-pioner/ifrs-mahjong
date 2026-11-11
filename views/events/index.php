<?php
$tiposEvento = [
    'encontro' => 'Encontro',
    'oficina' => 'Oficina',
    'torneio' => 'Torneio',
];

$gruposEvento = [
    'encontro' => 'Encontros',
    'oficina' => 'Oficinas',
    'torneio' => 'Torneios',
];

$meses = [
    1 => 'jan',
    2 => 'fev',
    3 => 'mar',
    4 => 'abr',
    5 => 'mai',
    6 => 'jun',
    7 => 'jul',
    8 => 'ago',
    9 => 'set',
    10 => 'out',
    11 => 'nov',
    12 => 'dez',
];

$partesData = static function (?string $data) use ($meses): array {
    if (!$data) {
        return ['dia' => '—', 'mes' => '', 'hora' => 'A definir', 'iso' => ''];
    }

    try {
        $quando = new DateTimeImmutable($data);
    } catch (Exception) {
        return ['dia' => '—', 'mes' => '', 'hora' => 'A definir', 'iso' => ''];
    }

    return [
        'dia' => $quando->format('d'),
        'mes' => $meses[(int) $quando->format('n')] ?? '',
        'hora' => $quando->format('H:i'),
        'iso' => $quando->format('c'),
    ];
};

$eventoEncerrado = static function (array $evento): bool {
    if (empty($evento['data_fim'])) {
        return false;
    }

    try {
        return new DateTimeImmutable((string) $evento['data_fim']) < new DateTimeImmutable('now');
    } catch (Exception) {
        return false;
    }
};

$inscricaoUsuario = static function (array $evento): array {
    $status = (string) ($evento['minha_inscricao'] ?? '');

    if ($status === 'aprovado') {
        return ['label' => 'Inscrito', 'class' => 'is-on'];
    }

    if ($status === 'pendente') {
        return ['label' => 'Pendente', 'class' => 'is-pending'];
    }

    return ['label' => 'Não Inscrito', 'class' => 'is-off'];
};

$montarUrl = static function (array $mudancas) use ($busca, $tipoFiltro, $inscricaoFiltro): string {
    $params = [
        'busca' => $busca ?? '',
        'tipo' => $tipoFiltro ?? '',
        'inscricao' => $inscricaoFiltro ?? '',
    ];

    foreach ($mudancas as $chave => $valor) {
        $params[$chave] = $valor;
    }

    $params = array_filter(
        $params,
        static fn ($valor): bool => $valor !== '' && $valor !== null
    );

    $query = http_build_query($params);

    return '/eventos' . ($query !== '' ? '?' . $query : '');
};

$proximos = [
    'encontro' => [],
    'oficina' => [],
    'torneio' => [],
];
$encerrados = [];

foreach ($eventos as $evento) {
    $tipo = (string) ($evento['tipo'] ?? '');
    if (!isset($proximos[$tipo])) {
        $tipo = 'encontro';
    }

    if ($eventoEncerrado($evento)) {
        $encerrados[] = $evento;
        continue;
    }

    $proximos[$tipo][] = $evento;
}

$encerrados = array_reverse($encerrados);
$mostrarTipo = ($tipoFiltro ?? '') === '';
$ehAdmin = ($user['role'] ?? '') === 'admin';

$renderLinha = static function (array $evento, bool $comTipo) use (
    $tiposEvento,
    $partesData,
    $inscricaoUsuario
): void {
    $quando = $partesData($evento['data_inicio'] ?? null);
    $inscricao = $inscricaoUsuario($evento);
    $tipo = (string) ($evento['tipo'] ?? '');
    $local = trim((string) ($evento['local'] ?? ''));
    $detalhes = array_filter([
        $quando['hora'],
        $local !== '' ? $local : null,
        eventModalidadeLabel($evento['modalidade'] ?? null),
    ]);
    ?>
    <a class="event-row" href="/eventos/<?= (int) $evento['id'] ?>">
        <time <?= $quando['iso'] !== '' ? 'datetime="' . e($quando['iso']) . '"' : '' ?>>
            <span class="event-date-day"><?= e($quando['dia']) ?></span>
            <?php if ($quando['mes'] !== ''): ?>
                <span class="event-date-mon"><?= e($quando['mes']) ?></span>
            <?php endif; ?>
        </time>
        <span class="event-copy">
            <span class="event-title"><?= e((string) $evento['titulo']) ?></span>
            <span class="event-meta">
                <?php if ($comTipo): ?>
                    <span class="event-kind is-<?= e($tipo) ?>"><?= e($tiposEvento[$tipo] ?? 'Outro') ?></span>
                <?php endif; ?>
                <?php foreach ($detalhes as $detalhe): ?>
                    <span><?= e($detalhe) ?></span>
                <?php endforeach; ?>
            </span>
        </span>
        <span class="status <?= e($inscricao['class']) ?>"><?= e($inscricao['label']) ?></span>
    </a>
    <?php
};

$renderGrupo = static function (string $titulo, string $tipo, array $itens, bool $comTipo) use ($renderLinha): void {
    if ($itens === []) {
        return;
    }
    ?>
    <section class="event-group">
        <h2>
            <?php if ($tipo !== ''): ?>
                <span class="event-mark is-<?= e($tipo) ?>" aria-hidden="true"></span>
            <?php endif; ?>
            <?= e($titulo) ?>
        </h2>
        <div class="event-panel">
            <?php foreach ($itens as $evento): ?>
                <?php $renderLinha($evento, $comTipo); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
};

$temEventos = false;
foreach ($proximos as $itens) {
    if ($itens !== []) {
        $temEventos = true;
        break;
    }
}
$temEventos = $temEventos || $encerrados !== [];
?>

<div class="events-browse">
    <section class="page-head events-list-head">
        <h1>Eventos</h1>
        <?php if ($ehAdmin): ?>
            <a class="button button-primary" href="/eventos/novo">
                <span aria-hidden="true">＋</span>
                Novo evento
            </a>
        <?php endif; ?>
    </section>

    <div class="events-toolbar">
        <nav class="event-type-tabs" aria-label="Tipo de evento">
            <?php foreach (['' => 'Todos'] + $gruposEvento as $valor => $rotulo): ?>
                <a
                    href="<?= e($montarUrl(['tipo' => $valor])) ?>"
                    <?= ($tipoFiltro ?? '') === $valor ? 'aria-current="page"' : '' ?>
                ><?= e($rotulo) ?></a>
            <?php endforeach; ?>
        </nav>

        <form id="events-filters" class="user-filters" method="get" action="/eventos">
            <input type="hidden" name="tipo" value="<?= e($tipoFiltro ?? '') ?>">

            <div class="user-search">
                <label for="busca">Pesquisar</label>
                <input
                    type="search"
                    id="busca"
                    name="busca"
                    placeholder="Nome do evento..."
                    value="<?= e($busca ?? '') ?>"
                    autocomplete="off"
                >
            </div>

            <div class="user-role-filter">
                <label for="inscricao">Inscrição</label>
                <select id="inscricao" name="inscricao">
                    <option value="">Todas</option>
                    <option value="inscrito" <?= ($inscricaoFiltro ?? '') === 'inscrito' ? 'selected' : '' ?>>
                        Inscrito
                    </option>
                    <option value="pendente" <?= ($inscricaoFiltro ?? '') === 'pendente' ? 'selected' : '' ?>>
                        Pendente
                    </option>
                    <option value="nao_inscrito" <?= ($inscricaoFiltro ?? '') === 'nao_inscrito' ? 'selected' : '' ?>>
                        Não Inscrito
                    </option>
                </select>
            </div>
        </form>
    </div>

    <?php if (!$temEventos): ?>
        <div class="empty">
            <p>
                Nenhum evento encontrado.
                <?php if ($ehAdmin && ($busca ?? '') === '' && ($tipoFiltro ?? '') === '' && ($inscricaoFiltro ?? '') === ''): ?>
                    <a href="/eventos/novo">Criar evento</a>
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="event-groups">
            <?php foreach ($gruposEvento as $tipo => $rotulo): ?>
                <?php $renderGrupo($rotulo, $tipo, $proximos[$tipo], false); ?>
            <?php endforeach; ?>
            <?php $renderGrupo('Encerrados', '', $encerrados, $mostrarTipo); ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const formulario = document.getElementById('events-filters');
    if (!formulario) {
        return;
    }

    const busca = document.getElementById('busca');
    const inscricao = document.getElementById('inscricao');
    let temporizador;

    function aplicarFiltros() {
        const parametros = new URLSearchParams(new FormData(formulario));

        for (const [chave, valor] of [...parametros.entries()]) {
            if (!String(valor).trim()) {
                parametros.delete(chave);
            }
        }

        const url = '/eventos' + (parametros.toString() ? '?' + parametros.toString() : '');
        window.location.href = url;
    }

    busca.addEventListener('input', function () {
        clearTimeout(temporizador);
        temporizador = setTimeout(aplicarFiltros, 500);
    });

    inscricao.addEventListener('change', aplicarFiltros);
});
</script>
