<?php
/** @var array{items: list<array{chave?: string, text: string, href: string, at: string}>, total: int, page: int, pages: int} $notificacoes */
$notificacoes = $notificacoes ?? ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$itens = $notificacoes['items'];
$pagina = (int) $notificacoes['page'];
$paginas = (int) $notificacoes['pages'];
$retorno = $pagina > 1 ? '/?page=' . $pagina : '/';
$montarPagina = static function (int $page): string {
    return $page <= 1 ? '/' : '/?page=' . $page;
};
?>
<section class="notice-board" aria-labelledby="notice-board-title">
    <div class="notice-board-head">
        <h2 id="notice-board-title">Notificações</h2>
        <a class="notice-history" href="/notificacoes" aria-label="Histórico de notificações" title="Histórico">
            <span class="icon icon-history" aria-hidden="true"></span>
        </a>
    </div>
    <?php if ($itens === []): ?>
        <p class="notice-empty">Nenhuma notificação recente.</p>
    <?php else: ?>
        <ul class="notice-list">
            <?php foreach ($itens as $item): ?>
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
                    <form method="post" action="/notificacoes/ok">
                        <?= csrfField() ?>
                        <input type="hidden" name="chave" value="<?= e((string) ($item['chave'] ?? '')) ?>">
                        <input type="hidden" name="retorno" value="<?= e($retorno) ?>">
                        <button class="button button-secondary notice-ok" type="submit">OK</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($paginas > 1): ?>
            <nav class="notice-pager" aria-label="Paginação das notificações">
                <?php if ($pagina > 1): ?>
                    <a href="<?= e($montarPagina($pagina - 1)) ?>">Anterior</a>
                <?php else: ?>
                    <span>Anterior</span>
                <?php endif; ?>
                <span>Página <?= $pagina ?> de <?= $paginas ?></span>
                <?php if ($pagina < $paginas): ?>
                    <a href="<?= e($montarPagina($pagina + 1)) ?>">Próxima</a>
                <?php else: ?>
                    <span>Próxima</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
