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

$inscricaoUsuario = static function (array $evento): array {
    $status = (string) ($evento['minha_inscricao'] ?? '');

    if ($status === 'aprovado') {
        return ['key' => 'inscrito', 'label' => 'Inscrito', 'class' => 'is-on'];
    }

    if ($status === 'pendente') {
        return ['key' => 'pendente', 'label' => 'Pendente', 'class' => 'is-pending'];
    }

    return ['key' => 'nao_inscrito', 'label' => 'Não Inscrito', 'class' => 'is-off'];
};

$ehAdmin = ($user['role'] ?? '') === 'admin';
?>

<section class="page-head events-list-head">
    <h1>Eventos</h1>
    <?php if ($ehAdmin): ?>
        <a class="button button-primary" href="/eventos/novo">
            <span aria-hidden="true">＋</span>
            Novo evento
        </a>
    <?php endif; ?>
</section>

<form id="events-filters" class="user-filters" method="get" action="/eventos">
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

    <div class="user-status-filter">
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
            <option value="">Todos</option>
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

<?php if (empty($eventos)): ?>
    <div class="empty">
        <p>
            <?php if ($ehAdmin): ?>
                Nenhum evento encontrado.
                <a href="/eventos/novo">Criar evento</a>
            <?php else: ?>
                Nenhum evento encontrado.
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Evento</th>
                    <th>Tipo</th>
                    <th>Modalidade</th>
                    <th>Início</th>
                    <th>Local</th>
                    <th>Inscrição</th>
                    <th class="actions-column">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eventos as $evento): ?>
                    <?php $inscricao = $inscricaoUsuario($evento); ?>
                    <tr>
                        <td><?= e((string) $evento['titulo']) ?></td>
                        <td><?= e($tiposEvento[$evento['tipo']] ?? 'Outro') ?></td>
                        <td><?= e(eventModalidadeLabel($evento['modalidade'] ?? null)) ?></td>
                        <td><?= e($formatarData($evento['data_inicio'] ?? null)) ?></td>
                        <td><?= e(trim((string) ($evento['local'] ?? '')) !== '' ? (string) $evento['local'] : '—') ?></td>
                        <td>
                            <span class="status <?= e($inscricao['class']) ?>">
                                <?= e($inscricao['label']) ?>
                            </span>
                        </td>
                        <td class="actions-column">
                            <a
                                class="button button-secondary"
                                href="/eventos/<?= (int) $evento['id'] ?>"
                                aria-label="Ver detalhes de <?= e((string) $evento['titulo']) ?>"
                            >
                                Ver
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const formulario = document.getElementById('events-filters');
    if (!formulario) {
        return;
    }

    const busca = document.getElementById('busca');
    const tipo = document.getElementById('tipo');
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

    tipo.addEventListener('change', aplicarFiltros);
    inscricao.addEventListener('change', aplicarFiltros);
});
</script>
