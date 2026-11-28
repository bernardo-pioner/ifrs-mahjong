<?php
/** @var list<array<string, mixed>> $matches */
$detalhes = [];
?>

<section class="page-head">
    <h1>Partidas</h1>
    <p class="lede">
        Partidas em que você foi incluído. As que ainda precisam da sua confirmação aparecem primeiro.
    </p>
</section>

<?php if ($matches === []): ?>
    <div class="empty">
        <p>Você ainda não está em nenhuma partida registrada.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Evento</th>
                    <th>Modalidade</th>
                    <th>Mesa</th>
                    <th>Situação</th>
                    <th class="actions-column"></th>
                </tr>
            </thead>
            <tbody id="matches-table-body">
                <?php foreach ($matches as $match): ?>
                    <?php
                    $formato = (string) ($match['formato'] ?? '');
                    $modalidade = $formato !== ''
                        ? eventModalidadeLabel($formato)
                        : (string) ($match['modalidade'] ?? '');
                    $quando = '—';
                    if (!empty($match['criada_em'])) {
                        try {
                            $quando = (new DateTimeImmutable((string) $match['criada_em']))->format('d/m/Y · H:i');
                        } catch (Exception) {
                            $quando = (string) $match['criada_em'];
                        }
                    }
                    $jogadoresMesa = $match['jogadores'];
                    usort(
                        $jogadoresMesa,
                        static function (array $a, array $b): int {
                            $colocacaoA = $a['colocacao'] ?? PHP_INT_MAX;
                            $colocacaoB = $b['colocacao'] ?? PHP_INT_MAX;

                            return ((int) $colocacaoA) <=> ((int) $colocacaoB);
                        }
                    );
                    $nomes = [];
                    foreach ($jogadoresMesa as $jogador) {
                        $nomes[] = (string) $jogador['nome'];
                    }
                    $pendente = ($match['validacao'] ?? '') === 'pendente';
                    $matchId = (int) $match['id'];
                    $jogadoresDetalhe = [];
                    foreach ($match['jogadores'] as $jogador) {
                        $jogadoresDetalhe[] = [
                            'nome' => (string) $jogador['nome'],
                            'photo' => (string) ($jogador['photo_url'] ?? ''),
                            'convidado' => !empty($jogador['convidado']),
                            'pontuacao' => formatarPontuacao($jogador['pontuacao']),
                            'colocacao' => $jogador['colocacao'] === null
                                ? '—'
                                : (string) $jogador['colocacao'] . 'º',
                        ];
                    }
                    $detalhes[(string) $matchId] = [
                        'evento' => (string) ($match['evento'] ?? 'Mesa avulsa'),
                        'modalidade' => $modalidade,
                        'quando' => $quando,
                        'jogadores' => $jogadoresDetalhe,
                    ];
                    ?>
                    <tr>
                        <td><?= e($quando) ?></td>
                        <td>
                            <?php if (!empty($match['evento_id'])): ?>
                                <a href="/eventos/<?= (int) $match['evento_id'] ?>"><?= e((string) ($match['evento'] ?? 'Evento')) ?></a>
                            <?php else: ?>
                                <?= e((string) ($match['evento'] ?? '—')) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e($modalidade) ?></td>
                        <td><?= e(implode(' · ', $nomes)) ?></td>
                        <td>
                            <?php if ($pendente): ?>
                                <span class="status is-pending">Pendente</span>
                            <?php else: ?>
                                <span class="status is-on">Confirmada</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-column">
                            <button
                                type="button"
                                class="user-actions-trigger"
                                aria-haspopup="menu"
                                aria-expanded="false"
                                aria-label="Ações da partida de <?= e($quando) ?>"
                                data-match-id="<?= $matchId ?>"
                                data-pending="<?= $pendente ? '1' : '0' ?>"
                            >
                                <span class="user-actions-dots" aria-hidden="true"></span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="user-list-footer">
        <select id="matches-page-size" class="users-page-size" aria-label="Partidas por página">
            <option value="10" selected>Partidas por página 10</option>
            <option value="20">Partidas por página 20</option>
            <option value="50">Partidas por página 50</option>
        </select>
        <nav class="notice-pager" id="matches-pager" aria-label="Paginação de partidas" hidden></nav>
    </div>

    <div
        id="match-actions-menu"
        class="user-actions-menu"
        role="menu"
        hidden
    >
        <button type="button" class="user-actions-item" role="menuitem" data-action="details">
            Ver partida
        </button>
        <form
            method="post"
            action="/partidas/0/validar"
            class="user-actions-form"
            id="match-validate-form"
        >
            <?= csrfField() ?>
            <input type="hidden" name="pagina" value="1">
            <input type="hidden" name="por_pagina" value="10">
            <button type="submit" class="user-actions-item" role="menuitem">
                Validar participação
            </button>
        </form>
    </div>

    <div
        id="match-details-modal"
        class="user-details-modal"
        hidden
        aria-hidden="true"
    >
        <div class="user-details-backdrop" data-close-match></div>
        <div
            class="user-details-dialog profile-card match-details-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="match-details-title"
        >
            <button
                type="button"
                class="user-details-close"
                aria-label="Fechar detalhes"
                data-close-match
            >
                ×
            </button>
            <div class="user-details-heading">
                <h2 id="match-details-title">Partida</h2>
                <p id="match-details-event" class="lede"></p>
            </div>
            <div class="event-details">
                <div class="event-detail">
                    <span><strong>Modalidade:</strong> <span id="match-details-format"></span></span>
                </div>
                <div class="event-detail">
                    <span><strong>Registrada em:</strong> <span id="match-details-when"></span></span>
                </div>
            </div>
            <div class="table-wrap match-scoreboard">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Colocação</th>
                            <th>Jogador</th>
                            <th>Pontuação</th>
                        </tr>
                    </thead>
                    <tbody id="match-details-players"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tableBody = document.getElementById('matches-table-body');
            const pageSizeSelect = document.getElementById('matches-page-size');
            const matchesPager = document.getElementById('matches-pager');
            const actionsMenu = document.getElementById('match-actions-menu');
            const validateForm = document.getElementById('match-validate-form');
            const detailsModal = document.getElementById('match-details-modal');
            const details = <?= json_encode($detalhes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
            if (!tableBody || !pageSizeSelect || !matchesPager || !actionsMenu || !validateForm || !detailsModal) {
                return;
            }

            const matchRows = Array.from(tableBody.querySelectorAll('tr'));
            const listParams = new URLSearchParams(window.location.search);
            let activeTrigger = null;
            let currentPage = 1;

            const savedSize = listParams.get('por_pagina');
            if (savedSize === '10' || savedSize === '20' || savedSize === '50') {
                pageSizeSelect.value = savedSize;
            }
            const savedPage = Number(listParams.get('pagina'));
            if (Number.isInteger(savedPage) && savedPage > 0) {
                currentPage = savedPage;
            }

            function pageSize() {
                const value = Number(pageSizeSelect.value);
                return value === 20 || value === 50 ? value : 10;
            }

            function paintPager(page, pages) {
                matchesPager.hidden = pages < 2;
                matchesPager.replaceChildren();
                if (pages < 2) {
                    return;
                }

                function addStep(label, picked, disabled) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = label;
                    button.disabled = disabled;
                    if (!disabled) {
                        button.addEventListener('click', function () {
                            currentPage = picked;
                            closeActionsMenu();
                            showMatches(false);
                        });
                    }
                    matchesPager.appendChild(button);
                }

                addStep('Anterior', page - 1, page <= 1);

                const status = document.createElement('span');
                status.className = 'notice-pager-status';
                status.append('Página');

                const select = document.createElement('select');
                select.setAttribute('aria-label', 'Página');
                for (let number = 1; number <= pages; number += 1) {
                    const option = document.createElement('option');
                    option.value = String(number);
                    option.textContent = String(number);
                    option.selected = number === page;
                    select.appendChild(option);
                }
                select.addEventListener('change', function () {
                    currentPage = Number(select.value);
                    closeActionsMenu();
                    showMatches(false);
                });
                status.appendChild(select);
                status.append('de ' + pages);
                matchesPager.appendChild(status);

                addStep('Próxima', page + 1, page >= pages);
            }

            function showMatches(resetPage) {
                if (resetPage) {
                    currentPage = 1;
                }

                const size = pageSize();
                const pages = Math.max(1, Math.ceil(matchRows.length / size));
                if (currentPage > pages) {
                    currentPage = pages;
                }
                if (currentPage < 1) {
                    currentPage = 1;
                }
                const start = (currentPage - 1) * size;

                matchRows.forEach(function (row, index) {
                    row.hidden = index < start || index >= start + size;
                });

                paintPager(currentPage, pages);
            }

            function closeActionsMenu() {
                actionsMenu.hidden = true;
                if (activeTrigger) {
                    activeTrigger.setAttribute('aria-expanded', 'false');
                    activeTrigger = null;
                }
            }

            function openActionsMenu(trigger) {
                const matchId = trigger.dataset.matchId || '';
                const pending = trigger.dataset.pending === '1';

                validateForm.action = '/partidas/' + encodeURIComponent(matchId) + '/validar';
                validateForm.hidden = !pending;

                const rect = trigger.getBoundingClientRect();
                actionsMenu.hidden = false;

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

                if (activeTrigger && activeTrigger !== trigger) {
                    activeTrigger.setAttribute('aria-expanded', 'false');
                }

                activeTrigger = trigger;
                trigger.setAttribute('aria-expanded', 'true');
            }

            function playerCell(jogador) {
                const cell = document.createElement('span');
                cell.className = 'match-player';

                const avatar = document.createElement('span');
                avatar.className = 'user-table-avatar';
                avatar.setAttribute('aria-hidden', 'true');
                if (jogador.photo) {
                    const image = document.createElement('img');
                    image.src = jogador.photo;
                    image.alt = '';
                    avatar.appendChild(image);
                } else {
                    const icon = document.createElement('span');
                    icon.className = 'icon icon-user';
                    avatar.appendChild(icon);
                }
                cell.appendChild(avatar);

                const name = document.createElement('span');
                name.className = 'match-player-label';
                name.textContent = jogador.nome || 'Participante';
                cell.appendChild(name);

                if (jogador.convidado) {
                    const guest = document.createElement('span');
                    guest.className = 'event-participant-status is-suplente';
                    guest.textContent = 'Convidado';
                    cell.appendChild(guest);
                }

                return cell;
            }

            function openDetails(trigger) {
                const match = details[trigger.dataset.matchId || ''];
                if (!match) {
                    return;
                }

                const eventName = document.getElementById('match-details-event');
                const format = document.getElementById('match-details-format');
                const when = document.getElementById('match-details-when');
                const players = document.getElementById('match-details-players');
                if (!eventName || !format || !when || !players) {
                    return;
                }

                eventName.textContent = match.evento || 'Mesa avulsa';
                format.textContent = match.modalidade || '—';
                when.textContent = match.quando || '—';
                players.replaceChildren();
                (match.jogadores || []).forEach(function (jogador) {
                    const row = document.createElement('tr');
                    const nameCell = document.createElement('td');
                    const scoreCell = document.createElement('td');
                    const placeCell = document.createElement('td');
                    nameCell.appendChild(playerCell(jogador));
                    scoreCell.textContent = jogador.pontuacao === '' || jogador.pontuacao == null ? '—' : String(jogador.pontuacao);
                    placeCell.textContent = jogador.colocacao || '—';
                    row.append(placeCell, nameCell, scoreCell);
                    players.appendChild(row);
                });

                closeActionsMenu();
                document.body.appendChild(detailsModal);
                detailsModal.hidden = false;
                detailsModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('match-details-open');
            }

            function closeDetails() {
                detailsModal.hidden = true;
                detailsModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('match-details-open');
            }

            tableBody.addEventListener('click', function (event) {
                const trigger = event.target.closest('.user-actions-trigger');
                if (!trigger) {
                    return;
                }

                event.stopPropagation();
                if (activeTrigger === trigger && !actionsMenu.hidden) {
                    closeActionsMenu();
                    return;
                }

                openActionsMenu(trigger);
            });

            actionsMenu.addEventListener('click', function (event) {
                const viewButton = event.target.closest('[data-action="details"]');
                if (!viewButton || !activeTrigger) {
                    return;
                }
                openDetails(activeTrigger);
            });

            detailsModal.addEventListener('click', function (event) {
                if (event.target.closest('[data-close-match]')) {
                    closeDetails();
                }
            });

            document.addEventListener('click', function (event) {
                if (
                    !actionsMenu.hidden &&
                    !event.target.closest('#match-actions-menu') &&
                    !event.target.closest('.user-actions-trigger')
                ) {
                    closeActionsMenu();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape') {
                    return;
                }
                if (!detailsModal.hidden) {
                    closeDetails();
                    return;
                }
                closeActionsMenu();
            });

            window.addEventListener('resize', closeActionsMenu);
            window.addEventListener('scroll', closeActionsMenu, true);

            pageSizeSelect.addEventListener('change', function () {
                closeActionsMenu();
                showMatches(true);
            });
            validateForm.addEventListener('submit', function () {
                validateForm.querySelector('[name="pagina"]').value = String(currentPage);
                validateForm.querySelector('[name="por_pagina"]').value = String(pageSize());
            });
            showMatches(false);
        });
    </script>
<?php endif; ?>
