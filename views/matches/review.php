<?php
/** @var array<string, mixed> $evento */
/** @var array{errors: list<string>, formato: string, jogadores: list<array<string, mixed>>} $preview */

$eventoId = (int) $evento['id'];
$formato = (string) ($preview['formato'] ?? '');
$modalidade = isset(matchFormatOptions()[$formato])
    ? eventModalidadeLabel($formato)
    : 'Não informada';
$errors = $preview['errors'] ?? [];
$jogadores = $preview['jogadores'] ?? [];
?>

<a class="page-back" href="/eventos/<?= $eventoId ?>">← Voltar para o evento</a>

<section class="event-form-page">
    <header class="event-form-header">
        <h1>Partida</h1>
        <p><?= e((string) ($evento['titulo'] ?? '')) ?></p>
    </header>

    <?php if ($errors !== []): ?>
        <div class="flash flash-error" role="alert" data-static>
            <strong>Confira os dados informados:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <article class="event-form-card">
        <div class="event-details">
            <div class="event-detail">
                <span><strong>Modalidade:</strong> <?= e($modalidade) ?></span>
            </div>
        </div>

        <?php if ($jogadores !== []): ?>
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
                        <?php foreach ($jogadores as $jogador): ?>
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
        <?php endif; ?>

        <div class="event-form-actions match-scoreboard-actions">
            <form method="post" action="/eventos/<?= $eventoId ?>/partidas/cancelar">
                <?= csrfField() ?>
                <button class="button button-secondary" type="submit">Cancelar</button>
            </form>
            <a class="button button-secondary" href="/eventos/<?= $eventoId ?>/partidas/nova?editar=1">Editar</a>
            <?php if ($errors === []): ?>
                <form method="post" action="/eventos/<?= $eventoId ?>/partidas/salvar">
                    <?= csrfField() ?>
                    <button class="button button-primary" type="submit">Salvar</button>
                </form>
            <?php endif; ?>
        </div>
    </article>
</section>
