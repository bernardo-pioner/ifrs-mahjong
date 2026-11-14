<?php

$tiposEvento = [
    'encontro' => 'Encontro',
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
$perguntarSerie = $ehAdmin
    && !empty($evento['serie_id'])
    && Event::hasSeriesChoice(
        (int) $evento['serie_id'],
        isset($evento['data_inicio']) ? (string) $evento['data_inicio'] : null,
        $eventoId
    );
$userId = (int) ($user['id'] ?? 0);
$taxa = (float) ($details['taxa'] ?? 0);
$limite = $details['limite'] ?? null;
$premiacao = is_array($details['premiacao'] ?? null) ? $details['premiacao'] : [];
$inscricoesAbertas = (int) ($evento['inscricoes_abertas'] ?? 0) === 1;
$precisaPagamento = in_array($evento['tipo'], ['torneio', 'oficina'], true) && $taxa > 0;
$jaInscrito = $minhaParticipacao !== null && ($minhaParticipacao['status'] ?? '') !== 'cancelado';
$statusInscricao = (string) ($minhaParticipacao['status'] ?? '');
$podeInscrever = !$ehAdmin && $inscricoesAbertas && !$jaInscrito && $temVaga;
$meuPagamento = $meuPagamento ?? null;
$temComprovante = is_array($meuPagamento) && !empty($meuPagamento['comprovante']);
$podeEnviarComprovante = !$ehAdmin
    && $jaInscrito
    && $precisaPagamento
    && $statusInscricao === 'pendente';

?>

<section class="event-form-page event-show-page">
    <header class="event-form-header">
        <a class="event-back-link" href="/eventos">
            ← Voltar para eventos
        </a>
        <h1><?= e($evento['titulo']) ?></h1>
    </header>

    <article class="event-form-card">
        <div class="event-card-top">
            <span class="event-type">
                <?= e($tiposEvento[$evento['tipo']] ?? 'Outro') ?>
            </span>
            <span class="event-modality">
                <?= e(eventModalidadeLabel($evento['modalidade'] ?? null)) ?>
            </span>
        </div>

        <div class="event-details">
            <div class="event-detail">
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
                    <span>
                        <strong>Local:</strong>
                        <?= e($evento['local']) ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="event-detail">
                <span>
                    <strong>Organizador:</strong>
                    <?php if (is_array($organizador ?? null)): ?>
                        <button
                            type="button"
                            class="event-participant-name"
                            data-open-bio
                            data-nickname="<?= e($organizador['nickname']) ?>"
                            data-username="<?= e($organizador['username']) ?>"
                            data-real-name="<?= e($organizador['name']) ?>"
                            data-bio="<?= e($organizador['bio']) ?>"
                            data-mahjong-soul="<?= e($organizador['mahjong_soul']) ?>"
                            data-riichi-city="<?= e($organizador['riichi_city']) ?>"
                            data-discord="<?= e($organizador['discord']) ?>"
                            data-photo="<?= e((string) ($organizador['photo_url'] ?? '')) ?>"
                            data-member-since="<?= e($organizador['member_since']) ?>"
                            data-level="<?= e($organizador['level_label']) ?>"
                        >
                            <?= e($organizador['label']) ?>
                        </button>
                    <?php else: ?>
                        <?= e($evento['organizador'] ?? 'Clube de Mahjong') ?>
                    <?php endif; ?>
                </span>
            </div>

            <?php if ($limite !== null): ?>
                <div class="event-detail">
                    <span>
                        <strong>Vagas aprovadas:</strong>
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

        <?php if ($premiacao !== []): ?>
            <div class="form-field event-premiacao-show">
                <h2>Premiação</h2>
                <ul class="event-premiacao-list">
                    <?php foreach ($premiacao as $indice => $premio): ?>
                        <li>
                            <strong><?= (int) $indice + 1 ?>º lugar:</strong>
                            <?= e($premio) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$ehAdmin): ?>
            <div class="event-form-divider"></div>

            <section class="event-registration-box">
                <h2>Sua inscrição</h2>

                <?php if ($jaInscrito): ?>
                    <p>
                        Status:
                        <strong class="event-participant-status is-<?= e($statusInscricao) ?>">
                            <?= e($statusLabels[$statusInscricao] ?? $statusInscricao) ?>
                        </strong>
                        <?php if ((int) ($minhaParticipacao['suplente'] ?? 0) === 1): ?>
                            <strong class="event-participant-status is-suplente">Suplente</strong>
                        <?php endif; ?>
                    </p>

                    <?php if ($precisaPagamento): ?>
                        <?php if ($temComprovante): ?>
                            <p>
                                Comprovante enviado.
                                <a
                                    href="/eventos/<?= $eventoId ?>/comprovantes/<?= $userId ?>"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    Ver comprovante
                                </a>
                            </p>
                        <?php elseif ($statusInscricao === 'pendente'): ?>
                            <p>
                                Sua inscrição está pendente. Envie o comprovante de
                                <strong>R$ <?= e(number_format($taxa, 2, ',', '.')) ?></strong>
                                para o administrador analisar.
                            </p>
                        <?php endif; ?>

                        <?php if ($podeEnviarComprovante): ?>
                            <form
                                method="post"
                                action="/eventos/<?= $eventoId ?>/comprovante"
                                enctype="multipart/form-data"
                                class="event-register-form"
                            >
                                <?= csrfField() ?>

                                <div class="form-field">
                                    <span class="file-field-label">
                                        <?= $temComprovante
                                            ? 'Substituir comprovante (PDF, PNG ou JPEG)'
                                            : 'Enviar comprovante (PDF, PNG ou JPEG)' ?>
                                    </span>
                                    <label class="file-picker" for="comprovante">
                                        <input
                                            id="comprovante"
                                            name="comprovante"
                                            type="file"
                                            accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg"
                                            required
                                            data-file-picker
                                        >
                                        <span class="file-picker-button">Escolher arquivo</span>
                                        <span class="file-picker-name" data-file-name>
                                            Nenhum arquivo selecionado
                                        </span>
                                    </label>
                                </div>

                                <button class="button button-primary" type="submit">
                                    <?= $temComprovante ? 'Atualizar comprovante' : 'Enviar comprovante' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($statusInscricao === 'pendente'): ?>
                        <p>Aguarde a aprovação do administrador.</p>
                    <?php endif; ?>
                <?php elseif (!$inscricoesAbertas): ?>
                    <p>As inscrições para este evento estão fechadas.</p>
                <?php elseif (!$temVaga): ?>
                    <p>Não há mais vagas disponíveis neste evento.</p>
                <?php elseif ($podeInscrever): ?>
                    <form
                        method="post"
                        action="/eventos/<?= $eventoId ?>/inscrever"
                        class="event-register-form"
                    >
                        <?= csrfField() ?>

                        <?php if ($precisaPagamento): ?>
                            <p>
                                Este evento exige pagamento de
                                <strong>R$ <?= e(number_format($taxa, 2, ',', '.')) ?></strong>.
                                Ao se inscrever, o status ficará pendente e você poderá
                                enviar o comprovante nesta página.
                            </p>
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

        <?php
        $mostraPartidas = in_array($evento['tipo'], ['encontro', 'oficina'], true);
        $abaPartidas = $mostraPartidas && request('aba') === 'partidas';
        $totalInscritos = 0;
        foreach ($participantes ?? [] as $inscrito) {
            if ((int) ($inscrito['suplente'] ?? 0) !== 1) {
                $totalInscritos++;
            }
        }
        ?>

        <?php if ($mostraPartidas): ?>
            <div class="event-form-divider"></div>
            <div class="event-show-tabs" role="tablist" aria-label="Conteúdo do evento">
                <button
                    type="button"
                    class="event-show-tab"
                    id="tab-inscritos"
                    role="tab"
                    aria-controls="panel-inscritos"
                    aria-selected="<?= $abaPartidas ? 'false' : 'true' ?>"
                    tabindex="<?= $abaPartidas ? '-1' : '0' ?>"
                >
                    Inscritos
                </button>
                <button
                    type="button"
                    class="event-show-tab"
                    id="tab-partidas"
                    role="tab"
                    aria-controls="panel-partidas"
                    aria-selected="<?= $abaPartidas ? 'true' : 'false' ?>"
                    tabindex="<?= $abaPartidas ? '0' : '-1' ?>"
                >
                    Partidas
                </button>
            </div>
        <?php else: ?>
            <div class="event-form-divider"></div>
        <?php endif; ?>

        <section
            class="event-participants"
            id="panel-inscritos"
            <?= $mostraPartidas ? 'role="tabpanel" aria-labelledby="tab-inscritos"' : '' ?>
            <?= $abaPartidas ? 'hidden' : '' ?>
        >
            <?php if (!$mostraPartidas): ?>
                <h2>Participantes inscritos (<?= $totalInscritos ?>)</h2>
            <?php endif; ?>

            <?php if (empty($participantes)): ?>
                <p class="event-participants-empty">
                    Nenhum participante inscrito neste evento.
                </p>
            <?php else: ?>
                <?php if ($mostraPartidas): ?>
                    <div class="event-participant-search-wrap">
                        <label class="event-participant-search">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="11" cy="11" r="6.25"></circle>
                                <path d="M16 16.25 20 20.25"></path>
                            </svg>
                            <input
                                type="search"
                                id="participant-search"
                                aria-label="Buscar por apelido ou nome"
                                placeholder="Buscar por apelido ou nome"
                                autocomplete="off"
                                spellcheck="false"
                                enterkeyhint="search"
                            >
                        </label>
                <?php endif; ?>
                <div class="event-participants-list">
                    <?php $ordemInscrito = 0; ?>
                    <?php foreach ($participantes as $participante): ?>
                        <?php
                        $username = trim((string) ($participante['username'] ?? ''));
                        $nickname = trim((string) ($participante['apelido'] ?? ''));
                        if ($nickname === '') {
                            $nickname = $username !== '' ? $username : 'Participante';
                        }
                        $displayName = trim((string) ($participante['nome'] ?? ''));
                        if ($displayName === '') {
                            $displayName = $nickname;
                        }
                        $bio = trim((string) ($participante['bio'] ?? ''));
                        $mahjongSoul = trim((string) ($participante['mahjong_soul'] ?? ''));
                        $riichiCity = trim((string) ($participante['riichi_city'] ?? ''));
                        $discord = trim((string) ($participante['discord'] ?? ''));
                        $photoUrl = (string) ($participante['photo_url'] ?? '');
                        $memberSince = (string) ($participante['member_since'] ?? '');
                        $levelLabel = (string) ($participante['level_label'] ?? '');
                        ?>
                        <div
                            class="event-participant"
                            data-order="<?= $ordemInscrito++ ?>"
                            data-username="<?= e($username) ?>"
                            data-name="<?= e((string) ($participante['nome'] ?? '')) ?>"
                        >
                            <div class="event-participant-main">
                                <span class="user-table-avatar" aria-hidden="true">
                                    <?php if ($photoUrl !== ''): ?>
                                        <img src="<?= e($photoUrl) ?>" alt="">
                                    <?php else: ?>
                                        <span class="icon icon-user"></span>
                                    <?php endif; ?>
                                </span>
                                <div>
                                    <button
                                        type="button"
                                        class="event-participant-name"
                                        data-open-bio
                                        data-nickname="<?= e($nickname) ?>"
                                        data-username="<?= e($username) ?>"
                                        data-real-name="<?= e((string) ($participante['nome'] ?? '')) ?>"
                                        data-bio="<?= e($bio) ?>"
                                        data-mahjong-soul="<?= e($mahjongSoul) ?>"
                                        data-riichi-city="<?= e($riichiCity) ?>"
                                        data-discord="<?= e($discord) ?>"
                                        data-photo="<?= e($photoUrl) ?>"
                                        data-member-since="<?= e($memberSince) ?>"
                                        data-level="<?= e($levelLabel) ?>"
                                    >
                                    <?= e($displayName) ?>
                                    <?php if ($nickname !== '' && $nickname !== $displayName): ?>
                                        <small>(<?= e($nickname) ?>)</small>
                                    <?php endif; ?>
                                </button>

                                <div class="event-participant-meta">
                                    <span class="event-participant-status is-<?= e($participante['status']) ?>">
                                        <?= e($statusLabels[$participante['status']] ?? $participante['status']) ?>
                                    </span>
                                    <?php if ((int) ($participante['suplente'] ?? 0) === 1): ?>
                                        <span class="event-participant-status is-suplente">Suplente</span>
                                    <?php endif; ?>

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
                            </div>

                            <?php if ($ehAdmin): ?>
                                <button
                                    type="button"
                                    class="user-actions-trigger event-actions-trigger"
                                    aria-haspopup="menu"
                                    aria-expanded="false"
                                    aria-label="Ações de <?= e($displayName) ?>"
                                    data-event-id="<?= $eventoId ?>"
                                    data-participant-id="<?= (int) $participante['usuario_id'] ?>"
                                    data-status="<?= e((string) $participante['status']) ?>"
                                    data-suplente="<?= (int) ($participante['suplente'] ?? 0) === 1 ? '1' : '0' ?>"
                                >
                                    <span class="user-actions-dots" aria-hidden="true"></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($mostraPartidas): ?>
                        <p class="event-participants-empty" id="participant-search-empty" hidden>
                            Nenhum inscrito encontrado.
                        </p>
                    <?php endif; ?>
                </div>
                <?php if ($mostraPartidas): ?>
                        <nav class="event-pager" id="participant-pager" aria-label="Paginação de inscritos" hidden></nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <?php if ($mostraPartidas): ?>
            <section
                class="event-participants"
                id="panel-partidas"
                role="tabpanel"
                aria-labelledby="tab-partidas"
                <?= $abaPartidas ? '' : 'hidden' ?>
            >
                <div class="event-match-list">
                    <a class="event-match-add" href="/eventos/<?= $eventoId ?>/partidas/nova">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 5v14M5 12h14"></path>
                        </svg>
                        Nova partida
                    </a>

                <?php if (($partidas ?? []) === []): ?>
                    <p class="event-participants-empty">Nenhuma partida registrada neste evento.</p>
                <?php else: ?>
                        <?php foreach ($partidas as $partida): ?>
                            <?php
                            $formatoPartida = (string) ($partida['formato'] ?? '');
                            $rotuloPartida = $formatoPartida !== ''
                                ? eventModalidadeLabel($formatoPartida)
                                : (string) ($partida['modalidade'] ?? '');
                            $quandoPartida = $formatarData(
                                isset($partida['criada_em']) ? (string) $partida['criada_em'] : null
                            );
                            $partidaPropria = (int) ($partida['registrado_por'] ?? 0) === $userId;
                            ?>
                            <article class="event-match-card<?= $partidaPropria ? ' is-owned' : '' ?>">
                                <?php if ($partidaPropria): ?>
                                    <button
                                        type="button"
                                        class="event-match-delete"
                                        data-delete-match="<?= (int) $partida['id'] ?>"
                                        aria-label="Excluir partida"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M9.2 5V3.4a1 1 0 0 1 1-1h3.6a1 1 0 0 1 1 1V5"></path>
                                            <path d="M4.2 6.2h15.6"></path>
                                            <path d="M6.4 6.2l.9 12.4a1.4 1.4 0 0 0 1.4 1.3h6.6a1.4 1.4 0 0 0 1.4-1.3l.9-12.4"></path>
                                            <path d="M10 9.2v7.4"></path>
                                            <path d="M12 9.2v7.4"></path>
                                            <path d="M14 9.2v7.4"></path>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                                <header class="event-match-head">
                                    <span><?= e($rotuloPartida) ?></span>
                                    <time datetime="<?= e((string) ($partida['criada_em'] ?? '')) ?>"><?= e($quandoPartida) ?></time>
                                </header>
                                <div class="event-match-table">
                                    <?php foreach ($partida['jogadores'] as $jogadorPartida): ?>
                                        <?php $jogadorAtual = (int) ($jogadorPartida['usuario_id'] ?? 0) === $userId; ?>
                                        <span class="event-match-place">
                                            <?= $jogadorPartida['colocacao'] === null ? '—' : e((string) $jogadorPartida['colocacao']) . '°' ?>
                                        </span>
                                        <span class="event-match-name<?= $jogadorAtual ? ' is-self' : '' ?>">
                                            <?= e((string) $jogadorPartida['nome']) ?>
                                            <?php if (!empty($jogadorPartida['convidado'])): ?>
                                                <span class="event-participant-status is-suplente">Convidado</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="event-match-score">
                                            <?= e(formatarPontuacao($jogadorPartida['pontuacao'])) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <nav class="event-pager" id="match-pager" aria-label="Paginação de partidas" hidden></nav>
                <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($ehAdmin && !empty($participantes)): ?>
            <div
                id="participant-actions-menu"
                class="user-actions-menu"
                role="menu"
                hidden
            >
                <form
                    method="post"
                    action="#"
                    class="user-actions-form"
                    id="participant-approve-form"
                >
                    <?= csrfField() ?>
                    <input type="hidden" name="status" value="aprovado">
                    <button type="submit" class="user-actions-item" role="menuitem">
                        Aprovar
                    </button>
                </form>
                <form
                    method="post"
                    action="#"
                    class="user-actions-form"
                    id="participant-pending-form"
                >
                    <?= csrfField() ?>
                    <input type="hidden" name="status" value="pendente">
                    <button type="submit" class="user-actions-item" role="menuitem">
                        Tornar pendente
                    </button>
                </form>
                <form
                    method="post"
                    action="#"
                    class="user-actions-form"
                    id="participant-suplente-form"
                >
                    <?= csrfField() ?>
                    <input type="hidden" name="suplente" id="participant-suplente-value" value="1">
                    <button type="submit" class="user-actions-item" role="menuitem" id="participant-suplente-button">
                        Marcar como suplente
                    </button>
                </form>
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
                    id="form-excluir-evento"
                    method="post"
                    action="/eventos/<?= $eventoId ?>/excluir"
                >
                    <?= csrfField() ?>
                    <?php if ($perguntarSerie): ?>
                        <input type="hidden" name="excluir_serie" id="excluir_serie" value="atual">
                    <?php endif; ?>

                    <button
                        class="button button-secondary"
                        type="submit"
                    >
                        Excluir evento
                    </button>
                </form>
            </div>

            <div
                id="delete-choice-modal"
                class="series-choice-modal"
                hidden
                aria-hidden="true"
            >
                <div class="series-choice-backdrop" data-close-delete-choice></div>
                <div
                    class="series-choice-dialog"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="delete-choice-title"
                >
                    <button
                        type="button"
                        class="user-details-close"
                        data-close-delete-choice
                        aria-label="Fechar"
                    >×</button>
                    <?php if ($perguntarSerie): ?>
                        <h2 id="delete-choice-title">Excluir recorrência</h2>
                        <p>As ocorrências encerradas permanecem. O que você deseja excluir?</p>
                        <div class="series-choice-actions is-stacked">
                            <button type="button" class="button button-secondary" id="delete-choice-current">
                                Apenas esta ocorrência
                            </button>
                            <button type="button" class="button button-secondary" id="delete-choice-future">
                                Esta e as seguintes
                            </button>
                            <button type="button" class="button button-primary" id="delete-choice-active">
                                Todas as ocorrências ativas
                            </button>
                        </div>
                    <?php else: ?>
                        <h2 id="delete-choice-title">Excluir evento</h2>
                        <p>Esta ação não poderá ser desfeita.</p>
                        <div class="series-choice-actions">
                            <button type="button" class="button button-secondary" data-close-delete-choice>
                                Cancelar
                            </button>
                            <button type="button" class="button button-primary" id="delete-choice-confirm">
                                Excluir
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </article>

    <div
        id="participant-bio-modal"
        class="user-details-modal"
        hidden
        aria-hidden="true"
    >
        <div class="user-details-backdrop" data-close-bio></div>
        <div
            class="user-details-dialog profile-card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="participant-bio-title"
        >
            <button
                type="button"
                class="user-details-close"
                aria-label="Fechar bio"
                data-close-bio
            >
                ×
            </button>

            <div class="profile-photo-field user-details-header">
                <span class="profile-photo-preview" aria-hidden="true">
                    <img id="participant-bio-photo" src="" alt="" hidden>
                    <span class="icon icon-user" id="participant-bio-photo-fallback"></span>
                </span>
                <div class="user-details-heading">
                    <h2 id="participant-bio-title"></h2>
                    <p id="participant-bio-username" class="lede"></p>
                    <p id="participant-bio-member-since" class="member-since"></p>
                </div>
            </div>

            <div class="profile-current-data">
                <div>
                    <span class="profile-label">Nome</span>
                    <p id="participant-bio-name"></p>
                </div>
                <div>
                    <span class="profile-label">Nível</span>
                    <p id="participant-bio-level"></p>
                </div>
                <div>
                    <span class="profile-label">ID/Nome Mahjong Soul</span>
                    <p id="participant-bio-mahjong-soul"></p>
                </div>
                <div>
                    <span class="profile-label">ID/Nome Riichi City</span>
                    <p id="participant-bio-riichi-city"></p>
                </div>
                <div>
                    <span class="profile-label">Discord</span>
                    <p id="participant-bio-discord"></p>
                </div>
            </div>

            <div class="user-details-bio">
                <span class="profile-label">Bio</span>
                <p id="participant-bio-text"></p>
            </div>
        </div>
    </div>

    <?php if ($mostraPartidas): ?>
        <div
            id="match-delete-modal"
            class="series-choice-modal"
            hidden
            aria-hidden="true"
        >
            <div class="series-choice-backdrop" data-close-match-delete></div>
            <div
                class="series-choice-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="match-delete-title"
            >
                <button type="button" class="user-details-close" data-close-match-delete aria-label="Fechar">×</button>
                <h2 id="match-delete-title">Excluir partida</h2>
                <p>Esta ação não poderá ser desfeita.</p>
                <form
                    id="match-delete-form"
                    method="post"
                    action="#"
                    class="series-choice-actions"
                >
                    <?= csrfField() ?>
                    <button type="button" class="button button-secondary" data-close-match-delete>Cancelar</button>
                    <button type="submit" class="button button-primary">Excluir</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const formExcluir = document.getElementById('form-excluir-evento');
        const deleteModal = document.getElementById('delete-choice-modal');
        if (!formExcluir || !deleteModal) {
            return;
        }

        function abrirExclusao() {
            deleteModal.hidden = false;
            deleteModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('series-choice-open');
            const foco = document.getElementById('delete-choice-active')
                || document.getElementById('delete-choice-confirm');
            if (foco) {
                foco.focus();
            }
        }

        function fecharExclusao() {
            deleteModal.hidden = true;
            deleteModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('series-choice-open');
        }

        function enviarExclusao(escopo) {
            const input = document.getElementById('excluir_serie');
            if (input) {
                input.value = escopo;
            }
            fecharExclusao();
            formExcluir.submit();
        }

        formExcluir.addEventListener('submit', function (event) {
            event.preventDefault();
            abrirExclusao();
        });

        const apenasAtual = document.getElementById('delete-choice-current');
        const seguintes = document.getElementById('delete-choice-future');
        const ativas = document.getElementById('delete-choice-active');
        const confirmar = document.getElementById('delete-choice-confirm');
        if (apenasAtual) {
            apenasAtual.addEventListener('click', function () {
                enviarExclusao('atual');
            });
        }
        if (seguintes) {
            seguintes.addEventListener('click', function () {
                enviarExclusao('seguintes');
            });
        }
        if (ativas) {
            ativas.addEventListener('click', function () {
                enviarExclusao('ativas');
            });
        }
        if (confirmar) {
            confirmar.addEventListener('click', function () {
                enviarExclusao('atual');
            });
        }
        deleteModal.querySelectorAll('[data-close-delete-choice]').forEach(function (el) {
            el.addEventListener('click', fecharExclusao);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !deleteModal.hidden) {
                fecharExclusao();
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        function paintPager(nav, page, pages, onPick) {
            if (!nav) {
                return;
            }
            nav.hidden = pages < 2;
            nav.innerHTML = '';
            if (pages < 2) {
                return;
            }

            function addButton(label, picked, current, disabled) {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = label;
                if (current) {
                    button.setAttribute('aria-current', 'page');
                }
                if (disabled) {
                    button.disabled = true;
                } else {
                    button.addEventListener('click', function () {
                        onPick(picked);
                    });
                }
                nav.appendChild(button);
            }

            addButton('Anterior', page - 1, false, page <= 1);
            for (let number = 1; number <= pages; number += 1) {
                addButton(String(number), number, number === page, false);
            }
            addButton('Próxima', page + 1, false, page >= pages);
        }

        function showPage(items, matched, pageSize, page) {
            const pages = Math.max(1, Math.ceil(matched.length / pageSize));
            const safePage = Math.min(Math.max(page, 1), pages);
            const start = (safePage - 1) * pageSize;
            items.forEach(function (item) {
                const index = matched.indexOf(item);
                item.hidden = index < 0 || index < start || index >= start + pageSize;
            });
            return { page: safePage, pages: pages };
        }

        function closeRowActions() {
            const actionsMenu = document.getElementById('participant-actions-menu');
            if (actionsMenu) {
                actionsMenu.hidden = true;
            }
            document.querySelectorAll('.event-actions-trigger[aria-expanded="true"]').forEach(function (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            });
        }

        const participantSearch = document.getElementById('participant-search');
        const participantSearchEmpty = document.getElementById('participant-search-empty');
        const participantPager = document.getElementById('participant-pager');
        if (participantSearch && participantPager) {
            const participantList = document.querySelector('#panel-inscritos .event-participants-list');
            const participantRows = Array.prototype.slice.call(
                document.querySelectorAll('#panel-inscritos .event-participant')
            );
            let participantPage = 1;

            function fold(value) {
                return (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
            }

            function rank(row, term) {
                const username = fold(row.dataset.username);
                const name = fold(row.dataset.name);
                if (username === term) {
                    return 0;
                }
                if (name === term) {
                    return 1;
                }
                if (username.indexOf(term) === 0) {
                    return 2;
                }
                if (name.indexOf(term) === 0) {
                    return 3;
                }
                return 4;
            }

            function matchedParticipants() {
                return participantRows.filter(function (row) {
                    return row.dataset.matched !== '0';
                });
            }

            function applyParticipants(page, scroll) {
                const state = showPage(participantRows, matchedParticipants(), 8, page);
                participantPage = state.page;
                paintPager(participantPager, state.page, state.pages, function (picked) {
                    applyParticipants(picked, true);
                    closeRowActions();
                });
                if (scroll) {
                    participantSearch.scrollIntoView({ block: 'start', behavior: 'smooth' });
                }
            }

            function filterParticipants() {
                const term = fold(participantSearch.value.trim());
                let visible = 0;
                participantRows.forEach(function (row) {
                    const username = fold(row.dataset.username);
                    const name = fold(row.dataset.name);
                    const match = term === ''
                        || username.indexOf(term) !== -1
                        || name.indexOf(term) !== -1;
                    row.dataset.matched = match ? '1' : '0';
                    if (match) {
                        visible += 1;
                    }
                });
                participantRows.sort(function (a, b) {
                    if (term === '') {
                        return Number(a.dataset.order) - Number(b.dataset.order);
                    }
                    const matchDiff = Number(a.dataset.matched) - Number(b.dataset.matched);
                    if (matchDiff !== 0) {
                        return matchDiff * -1;
                    }
                    return rank(a, term) - rank(b, term);
                });
                if (participantList) {
                    participantRows.forEach(function (row) {
                        participantList.insertBefore(row, participantSearchEmpty);
                    });
                }
                if (participantSearchEmpty) {
                    participantSearchEmpty.hidden = term === '' || visible > 0;
                }
                applyParticipants(1, false);
                closeRowActions();
            }

            participantSearch.addEventListener('input', filterParticipants);
            filterParticipants();
        }

        const matchPager = document.getElementById('match-pager');
        if (matchPager) {
            const matchCards = Array.prototype.slice.call(
                document.querySelectorAll('#panel-partidas .event-match-card')
            );
            let matchPage = 1;

            function applyMatches(page, scroll) {
                const state = showPage(matchCards, matchCards, 5, page);
                matchPage = state.page;
                paintPager(matchPager, state.page, state.pages, function (picked) {
                    applyMatches(picked, true);
                });
                if (scroll) {
                    const anchor = document.querySelector('.event-match-add');
                    if (anchor) {
                        anchor.scrollIntoView({ block: 'start', behavior: 'smooth' });
                    }
                }
            }

            applyMatches(1, false);
        }

        const modal = document.getElementById('participant-bio-modal');
        if (!modal) {
            return;
        }

        function fillText(id, value, fallback) {
            const el = document.getElementById(id);
            if (!el) {
                return;
            }
            const text = (value || '').trim();
            el.textContent = text !== '' ? text : fallback;
            el.classList.toggle('is-empty', text === '');
        }

        function openBio(trigger) {
            const nickname = (trigger.dataset.nickname || trigger.dataset.username || '').trim();
            const realName = (trigger.dataset.realName || '').trim();
            const photo = (trigger.dataset.photo || '').trim();
            const since = (trigger.dataset.memberSince || '').trim();
            const photoImg = document.getElementById('participant-bio-photo');
            const photoFallback = document.getElementById('participant-bio-photo-fallback');
            const memberSinceEl = document.getElementById('participant-bio-member-since');

            fillText('participant-bio-title', realName !== '' ? realName : nickname, 'Participante');
            fillText('participant-bio-username', nickname !== '' ? '@' + nickname : '', '');
            fillText('participant-bio-name', realName, 'Não informado');
            fillText('participant-bio-level', trigger.dataset.level, 'Não informado');
            fillText('participant-bio-mahjong-soul', trigger.dataset.mahjongSoul, 'Não informado');
            fillText('participant-bio-riichi-city', trigger.dataset.riichiCity, 'Não informado');
            fillText('participant-bio-discord', trigger.dataset.discord, 'Não informado');
            fillText(
                'participant-bio-text',
                trigger.dataset.bio,
                'Este usuário ainda não escreveu uma bio.'
            );

            if (memberSinceEl) {
                memberSinceEl.textContent = since !== '' ? 'Membro desde ' + since : '';
                memberSinceEl.hidden = since === '';
            }

            if (photoImg && photoFallback) {
                if (photo) {
                    photoImg.src = photo;
                    photoImg.hidden = false;
                    photoFallback.hidden = true;
                } else {
                    photoImg.removeAttribute('src');
                    photoImg.hidden = true;
                    photoFallback.hidden = false;
                }
            }

            document.body.appendChild(modal);
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('user-details-open');
        }

        function closeBio() {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('user-details-open');
        }

        document.querySelectorAll('[data-open-bio]').forEach(function (button) {
            button.addEventListener('click', function () {
                openBio(button);
            });
        });

        modal.addEventListener('click', function (event) {
            if (event.target.closest('[data-close-bio]')) {
                closeBio();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                closeBio();
            }
        });

        const actionsMenu = document.getElementById('participant-actions-menu');
        const approveForm = document.getElementById('participant-approve-form');
        const pendingForm = document.getElementById('participant-pending-form');
        const suplenteForm = document.getElementById('participant-suplente-form');
        const suplenteInput = document.getElementById('participant-suplente-value');
        const suplenteButton = document.getElementById('participant-suplente-button');
        let activeActionsTrigger = null;

        function closeParticipantActions() {
            if (!actionsMenu) {
                return;
            }
            actionsMenu.hidden = true;
            if (activeActionsTrigger) {
                activeActionsTrigger.setAttribute('aria-expanded', 'false');
                activeActionsTrigger = null;
            }
        }

        function openParticipantActions(trigger) {
            if (!actionsMenu || !approveForm || !pendingForm || !suplenteForm || !suplenteInput || !suplenteButton) {
                return;
            }
            const action = '/eventos/' + trigger.dataset.eventId
                + '/participantes/' + trigger.dataset.participantId
                + '/status';
            const status = trigger.dataset.status || '';
            const suplente = trigger.dataset.suplente === '1';

            approveForm.action = action;
            pendingForm.action = action;
            suplenteForm.action = action;
            approveForm.hidden = status === 'aprovado';
            pendingForm.hidden = status === 'pendente';
            suplenteInput.value = suplente ? '0' : '1';
            suplenteButton.textContent = suplente ? 'Remover suplente' : 'Marcar como suplente';

            if (activeActionsTrigger && activeActionsTrigger !== trigger) {
                activeActionsTrigger.setAttribute('aria-expanded', 'false');
            }

            actionsMenu.hidden = false;
            const rect = trigger.getBoundingClientRect();
            const menuWidth = actionsMenu.offsetWidth;
            const menuHeight = actionsMenu.offsetHeight;
            let left = rect.right - menuWidth;
            let top = rect.bottom + 6;

            if (left < 8) {
                left = 8;
            }
            if (left + menuWidth > window.innerWidth - 8) {
                left = window.innerWidth - menuWidth - 8;
            }
            if (top + menuHeight > window.innerHeight - 8) {
                top = rect.top - menuHeight - 6;
            }

            actionsMenu.style.left = left + 'px';
            actionsMenu.style.top = top + 'px';
            activeActionsTrigger = trigger;
            trigger.setAttribute('aria-expanded', 'true');
        }

        document.querySelectorAll('.event-actions-trigger').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.stopPropagation();
                if (activeActionsTrigger === trigger && actionsMenu && !actionsMenu.hidden) {
                    closeParticipantActions();
                    return;
                }
                openParticipantActions(trigger);
            });
        });

        document.addEventListener('click', function (event) {
            if (
                !actionsMenu ||
                actionsMenu.hidden ||
                event.target.closest('#participant-actions-menu') ||
                event.target.closest('.event-actions-trigger')
            ) {
                return;
            }
            closeParticipantActions();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeParticipantActions();
            }
        });

        window.addEventListener('resize', closeParticipantActions);
        window.addEventListener('scroll', closeParticipantActions, true);

        const inscritosTab = document.getElementById('tab-inscritos');
        const partidasTab = document.getElementById('tab-partidas');
        const inscritosPanel = document.getElementById('panel-inscritos');
        const partidasPanel = document.getElementById('panel-partidas');

        function selecionarAba(partidas) {
            if (!inscritosTab || !partidasTab || !inscritosPanel || !partidasPanel) {
                return;
            }
            inscritosTab.setAttribute('aria-selected', partidas ? 'false' : 'true');
            partidasTab.setAttribute('aria-selected', partidas ? 'true' : 'false');
            inscritosTab.tabIndex = partidas ? -1 : 0;
            partidasTab.tabIndex = partidas ? 0 : -1;
            inscritosPanel.hidden = partidas;
            partidasPanel.hidden = !partidas;
        }

        if (inscritosTab && partidasTab) {
            inscritosTab.addEventListener('click', function () {
                selecionarAba(false);
            });
            partidasTab.addEventListener('click', function () {
                selecionarAba(true);
            });
        }

        const matchDeleteModal = document.getElementById('match-delete-modal');
        const matchDeleteForm = document.getElementById('match-delete-form');

        function openMatchDelete(id) {
            if (!matchDeleteModal || !matchDeleteForm) {
                return;
            }
            matchDeleteForm.action = '/eventos/<?= $eventoId ?>/partidas/' + id + '/excluir';
            matchDeleteModal.hidden = false;
            matchDeleteModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('series-choice-open');
        }

        function closeMatchDelete() {
            if (!matchDeleteModal) {
                return;
            }
            matchDeleteModal.hidden = true;
            matchDeleteModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('series-choice-open');
        }

        document.querySelectorAll('[data-delete-match]').forEach(function (button) {
            button.addEventListener('click', function () {
                openMatchDelete(button.dataset.deleteMatch || '');
            });
        });

        if (matchDeleteModal) {
            matchDeleteModal.addEventListener('click', function (event) {
                if (event.target.closest('[data-close-match-delete]')) {
                    closeMatchDelete();
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMatchDelete();
            }
        });

        document.querySelectorAll('[data-file-picker]').forEach(function (input) {
            const nameEl = input.closest('.file-picker')?.querySelector('[data-file-name]');
            if (!nameEl) {
                return;
            }

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                nameEl.textContent = file ? file.name : 'Nenhum arquivo selecionado';
            });
        });
    });
</script>
