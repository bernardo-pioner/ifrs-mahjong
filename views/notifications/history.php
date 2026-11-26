<?php
/** @var array<string, mixed> $user */
/** @var array<string, string> $tipos */
/** @var string $tipoFiltro */
/** @var string $estadoFiltro */
/** @var array{items: list<array{chave: string, tipo: string, text: string, href: string, at: string, dispensada: bool}>, total: int, page: int, pages: int} $historico */

$montarUrl = static function (array $mudancas) use ($tipoFiltro, $estadoFiltro): string {
    $params = [
        'tipo' => $tipoFiltro,
        'estado' => $estadoFiltro,
    ];
    foreach ($mudancas as $chave => $valor) {
        $params[$chave] = (string) $valor;
    }

    $query = [];
    foreach ($params as $chave => $valor) {
        if ($valor === '' || ($chave === 'page' && $valor === '1')) {
            continue;
        }
        $query[$chave] = $valor;
    }

    return '/notificacoes' . ($query === [] ? '' : '?' . http_build_query($query));
};

$retorno = (string) ($_SERVER['REQUEST_URI'] ?? '/notificacoes');
if (!str_starts_with($retorno, '/notificacoes')) {
    $retorno = '/notificacoes';
}
?>
<a class="page-back" href="/">← Voltar</a>

<section class="page-head">
    <h1>Histórico de notificações</h1>
    <p class="lede">Todas as notificações, inclusive as que você já dispensou no dashboard.</p>
</section>

<div class="notice-toolbar">
    <nav class="event-type-tabs" aria-label="Tipo de notificação">
        <a
            href="<?= e($montarUrl(['tipo' => '', 'page' => '1'])) ?>"
            <?= $tipoFiltro === '' ? 'aria-current="page"' : '' ?>
        >Todos</a>
        <?php foreach ($tipos as $valor => $rotulo): ?>
            <a
                href="<?= e($montarUrl(['tipo' => $valor, 'page' => '1'])) ?>"
                <?= $tipoFiltro === $valor ? 'aria-current="page"' : '' ?>
            ><?= e($rotulo) ?></a>
        <?php endforeach; ?>
    </nav>

    <form id="notice-filters" class="user-filters" method="get" action="/notificacoes">
        <input type="hidden" name="tipo" value="<?= e($tipoFiltro) ?>">
        <div class="user-role-filter">
            <label for="estado">Situação</label>
            <select id="estado" name="estado">
                <option value="">Todas</option>
                <option value="ativa" <?= $estadoFiltro === 'ativa' ? 'selected' : '' ?>>Não dispensadas</option>
                <option value="dispensada" <?= $estadoFiltro === 'dispensada' ? 'selected' : '' ?>>Dispensadas</option>
            </select>
        </div>
    </form>
</div>

<section class="notice-board notice-history-board">
    <?php if ($historico['items'] === []): ?>
        <p class="notice-empty">Nenhuma notificação encontrada.</p>
    <?php else: ?>
        <ul class="notice-list">
            <?php foreach ($historico['items'] as $item): ?>
                <?php
                $quando = '';
                if ($item['at'] !== '') {
                    try {
                        $quando = (new DateTimeImmutable($item['at']))->format('d/m/Y · H:i');
                    } catch (Exception) {
                        $quando = $item['at'];
                    }
                }
                ?>
                <li class="notice-item">
                    <a class="notice-link" href="<?= e($item['href']) ?>">
                        <span><?= e($item['text']) ?></span>
                        <?php if ($quando !== ''): ?>
                            <time datetime="<?= e($item['at']) ?>"><?= e($quando) ?></time>
                        <?php endif; ?>
                    </a>
                    <?php if (!$item['dispensada']): ?>
                        <form method="post" action="/notificacoes/ok">
                            <?= csrfField() ?>
                            <input type="hidden" name="chave" value="<?= e($item['chave']) ?>">
                            <input type="hidden" name="retorno" value="<?= e($retorno) ?>">
                            <button class="button button-secondary notice-ok" type="submit">OK</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($historico['pages'] > 1): ?>
            <nav class="notice-pager" aria-label="Paginação do histórico">
                <?php if ($historico['page'] > 1): ?>
                    <a href="<?= e($montarUrl(['page' => (string) ($historico['page'] - 1)])) ?>">Anterior</a>
                <?php else: ?>
                    <span>Anterior</span>
                <?php endif; ?>
                <span>Página <?= (int) $historico['page'] ?> de <?= (int) $historico['pages'] ?></span>
                <?php if ($historico['page'] < $historico['pages']): ?>
                    <a href="<?= e($montarUrl(['page' => (string) ($historico['page'] + 1)])) ?>">Próxima</a>
                <?php else: ?>
                    <span>Próxima</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const formulario = document.getElementById('notice-filters');
        const situacao = document.getElementById('estado');
        if (!formulario || !situacao) {
            return;
        }
        situacao.addEventListener('change', function () {
            const parametros = new URLSearchParams(new FormData(formulario));
            for (const [chave, valor] of [...parametros.entries()]) {
                if (!String(valor).trim()) {
                    parametros.delete(chave);
                }
            }
            const url = '/notificacoes' + (parametros.toString() ? '?' + parametros.toString() : '');
            window.location.href = url;
        });
    });
</script>
