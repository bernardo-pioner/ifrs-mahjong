<?php
/** @var array<string, mixed> $evento */
/** @var list<array{id: int, username: string, name: string}> $choices */
/** @var list<string> $errors */
/** @var array{modalidade: string, seats: list<array{jogador: string, convidado: string, pontuacao: string}>} $old */
/** @var bool $selfListed */

$formats = matchFormatOptions();
$modalidade = (string) ($old['modalidade'] ?? '');
$seatCount = isset($formats[$modalidade]) ? matchFormatSeatCount($modalidade) : 0;
$seats = $old['seats'] ?? [];
$eventoId = (int) $evento['id'];
$userId = (int) ($user['id'] ?? 0);
$selfName = trim((string) ($user['username'] ?? ''));
$selfRealName = trim((string) ($user['name'] ?? ''));
$choiceById = [];
foreach ($choices as $choice) {
    if ($choice['id'] === $userId) {
        continue;
    }
    $choiceById[$choice['id']] = $choice;
}
?>

<section class="event-form-page">
    <header class="event-form-header">
        <a class="event-back-link" href="/eventos/<?= $eventoId ?>">← Voltar para o evento</a>
        <h1>Registrar partida</h1>
        <p><?= e((string) $evento['titulo']) ?></p>
    </header>

    <?php if (!$selfListed): ?>
        <div class="flash flash-error" role="alert">
            Sua conta não está habilitada como participante.
        </div>
    <?php else: ?>
        <?php if ($errors !== []): ?>
            <div class="flash flash-error" role="alert">
                <strong>Confira os dados informados:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="event-form-card" method="post" action="/eventos/<?= $eventoId ?>/partidas/nova" id="match-form">
            <?= csrfField() ?>

            <div class="form-field">
                <label for="modalidade">Modalidade</label>
                <select id="modalidade" name="modalidade" required>
                    <option value="" <?= $modalidade === '' ? 'selected' : '' ?>>Selecione</option>
                    <?php foreach ($formats as $valor => $rotulo): ?>
                        <option value="<?= e($valor) ?>" <?= $modalidade === $valor ? 'selected' : '' ?>>
                            <?= e($rotulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="match-seats" <?= $seatCount > 0 ? '' : 'hidden' ?>>
                <div class="match-seats">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <?php
                        $seat = $seats[$i] ?? ['jogador' => '', 'convidado' => '', 'pontuacao' => ''];
                        $jogador = $i === 0 ? (string) $userId : (string) ($seat['jogador'] ?? '');
                        $convidado = (string) ($seat['convidado'] ?? '');
                        $pontuacao = (string) ($seat['pontuacao'] ?? '');
                        $ativo = $i < $seatCount;
                        $isGuest = $i > 0 && $jogador === 'convidado';
                        $picked = null;
                        if ($i > 0 && ctype_digit($jogador) && isset($choiceById[(int) $jogador])) {
                            $picked = $choiceById[(int) $jogador];
                        }
                        $pickedLabel = $picked === null
                            ? ''
                            : $picked['username'] . ($picked['name'] !== '' ? ' (' . $picked['name'] . ')' : '');
                        ?>
                        <fieldset class="match-seat" data-seat <?= $ativo ? '' : 'hidden' ?>>
                            <legend><?= $i === 0 ? 'Você' : 'Jogador ' . ($i + 1) ?></legend>
                            <div class="match-seat-identity" <?= $i > 0 ? 'data-search' : '' ?>>
                                <?php if ($i === 0): ?>
                                    <div class="match-seat-self">
                                        <?= e($selfName) ?><?php if ($selfRealName !== ''): ?> (<?= e($selfRealName) ?>)<?php endif; ?>
                                        <input type="hidden" name="jogadores[0][jogador]" value="<?= $userId ?>" data-player>
                                    </div>
                                <?php else: ?>
                                    <div class="match-player-search">
                                        <label data-query-wrap <?= $picked !== null || $isGuest ? 'hidden' : '' ?>>
                                            Apelido ou nome
                                            <input
                                                type="text"
                                                data-query
                                                autocomplete="off"
                                                placeholder="Buscar por apelido ou nome"
                                                aria-autocomplete="list"
                                            >
                                        </label>
                                        <div class="match-player-picked" data-picked <?= $picked !== null || $isGuest ? '' : 'hidden' ?>>
                                            <span data-picked-label><?= e($isGuest ? 'Convidado' : $pickedLabel) ?></span>
                                            <button type="button" class="match-player-clear" data-clear>Trocar</button>
                                        </div>
                                        <input type="hidden" name="jogadores[<?= $i ?>][jogador]" value="<?= e($jogador) ?>" data-player>
                                        <ul class="match-player-results" data-results hidden></ul>
                                    </div>
                                    <label class="match-guest" data-guest <?= $isGuest ? '' : 'hidden' ?>>
                                        Nome do convidado
                                        <input
                                            type="text"
                                            name="jogadores[<?= $i ?>][convidado]"
                                            maxlength="40"
                                            value="<?= e($convidado) ?>"
                                            data-guest-name
                                            <?= $ativo && $isGuest ? 'required' : '' ?>
                                        >
                                    </label>
                                <?php endif; ?>
                            </div>
                            <label class="match-score">
                                <span>Pontuação</span>
                                <input
                                    type="number"
                                    name="jogadores[<?= $i ?>][pontuacao]"
                                    step="1"
                                    inputmode="numeric"
                                    value="<?= e($pontuacao) ?>"
                                    data-score
                                    <?= $ativo ? 'required' : '' ?>
                                >
                            </label>
                            <?php if ($i > 0): ?>
                                <button type="button" class="match-guest-toggle" data-use-guest <?= $picked !== null || $isGuest ? 'hidden' : '' ?>>
                                    Convidado
                                </button>
                            <?php endif; ?>
                        </fieldset>
                    <?php endfor; ?>
                </div>
                <p class="hint">O primeiro lugar é o seu. Busque os outros pelo apelido ou pelo nome. Use Convidado para quem ainda não tem cadastro.</p>
            </div>

            <div class="event-form-actions">
                <a class="button button-secondary" href="/eventos/<?= $eventoId ?>">Cancelar</a>
                <button class="button button-primary" type="submit">Registrar partida</button>
            </div>
        </form>
    <?php endif; ?>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('match-form');
        const modalidade = document.getElementById('modalidade');
        const seatsWrap = document.getElementById('match-seats');
        const players = <?= json_encode(array_values($choiceById), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        if (!form || !modalidade || !seatsWrap) {
            return;
        }

        const seats = Array.prototype.slice.call(form.querySelectorAll('[data-seat]'));

        function fold(value) {
            return (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        }

        function seatCount(value) {
            return value.indexOf('3_') === 0 ? 3 : 4;
        }

        function takenIds(except) {
            const ids = {};
            seats.forEach(function (seat) {
                if (seat === except || seat.hidden) {
                    return;
                }
                const player = seat.querySelector('[data-player]');
                if (player && player.value && player.value !== 'convidado') {
                    ids[player.value] = true;
                }
            });
            return ids;
        }

        function labelOf(player) {
            return player.name ? player.username + ' (' + player.name + ')' : player.username;
        }

        function closeResults(except) {
            form.querySelectorAll('[data-results]').forEach(function (list) {
                if (list !== except) {
                    list.hidden = true;
                    list.innerHTML = '';
                }
            });
        }

        function matchesFor(search) {
            const query = search.querySelector('[data-query]');
            const term = fold(query.value.trim());
            if (term === '') {
                return [];
            }
            const taken = takenIds(search.closest('[data-seat]'));
            return players.filter(function (player) {
                if (taken[String(player.id)]) {
                    return false;
                }
                return fold(player.username).indexOf(term) !== -1 || fold(player.name).indexOf(term) !== -1;
            }).sort(function (a, b) {
                return rank(a, term) - rank(b, term);
            });
        }

        function rank(player, term) {
            const username = fold(player.username);
            const name = fold(player.name);
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

        function paint(search) {
            const query = search.querySelector('[data-query]');
            const list = search.querySelector('[data-results]');
            const term = fold(query.value.trim());
            list.innerHTML = '';
            if (term === '') {
                list.hidden = true;
                return;
            }
            const matches = matchesFor(search);
            if (matches.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'match-player-empty';
                empty.textContent = 'Nenhum jogador encontrado.';
                list.appendChild(empty);
            } else {
                matches.forEach(function (player, index) {
                    const item = document.createElement('li');
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = labelOf(player);
                    button.dataset.playerId = String(player.id);
                    if (index === 0) {
                        button.className = 'is-active';
                    }
                    button.addEventListener('click', function () {
                        choose(search, String(player.id), labelOf(player), false);
                    });
                    item.appendChild(button);
                    list.appendChild(item);
                });
            }
            list.hidden = false;
        }

        function moveActive(search, direction) {
            const list = search.querySelector('[data-results]');
            const buttons = Array.prototype.slice.call(list.querySelectorAll('button'));
            if (buttons.length === 0) {
                return;
            }
            const current = list.querySelector('button.is-active');
            let index = buttons.indexOf(current);
            index = index + direction;
            if (index < 0) {
                index = buttons.length - 1;
            }
            if (index >= buttons.length) {
                index = 0;
            }
            buttons.forEach(function (button) {
                button.classList.remove('is-active');
            });
            buttons[index].classList.add('is-active');
            buttons[index].scrollIntoView({ block: 'nearest' });
        }

        function choose(search, value, label, guest) {
            const player = search.querySelector('[data-player]');
            const query = search.querySelector('[data-query]');
            const picked = search.querySelector('[data-picked]');
            const pickedLabel = search.querySelector('[data-picked-label]');
            const seat = search.closest('[data-seat]');
            const guestBox = seat.querySelector('[data-guest]');
            const guestName = seat.querySelector('[data-guest-name]');
            const guestToggle = seat.querySelector('[data-use-guest]');
            player.value = value;
            pickedLabel.textContent = label;
            picked.hidden = false;
            query.closest('[data-query-wrap]').hidden = true;
            query.value = '';
            guestToggle.hidden = true;
            guestBox.hidden = !guest;
            guestName.required = guest && !seat.hidden;
            if (!guest) {
                guestName.value = '';
                seat.querySelector('[data-score]').focus();
            }
            closeResults(null);
            sync();
        }

        function clearChoice(search) {
            const player = search.querySelector('[data-player]');
            const query = search.querySelector('[data-query]');
            const picked = search.querySelector('[data-picked]');
            const seat = search.closest('[data-seat]');
            const guestToggle = seat.querySelector('[data-use-guest]');
            const guestBox = seat.querySelector('[data-guest]');
            const guestName = seat.querySelector('[data-guest-name]');
            player.value = '';
            picked.hidden = true;
            query.hidden = false;
            query.closest('[data-query-wrap]').hidden = false;
            guestToggle.hidden = false;
            guestBox.hidden = true;
            guestName.required = false;
            guestName.value = '';
            query.focus();
            sync();
        }

        function sync() {
            const chosen = modalidade.value !== '';
            seatsWrap.hidden = !chosen;
            const count = chosen ? seatCount(modalidade.value) : 0;
            seats.forEach(function (seat, index) {
                const active = index < count;
                seat.hidden = !active;
                const score = seat.querySelector('[data-score]');
                const guestName = seat.querySelector('[data-guest-name]');
                const player = seat.querySelector('[data-player]');
                if (score) {
                    score.required = active;
                }
                if (guestName) {
                    guestName.required = active && player && player.value === 'convidado';
                }
            });
        }

        modalidade.addEventListener('change', function () {
            closeResults(null);
            sync();
        });

        form.querySelectorAll('[data-search]').forEach(function (search) {
            const query = search.querySelector('[data-query]');
            query.addEventListener('input', function () {
                paint(search);
            });
            query.addEventListener('focus', function () {
                closeResults(search.querySelector('[data-results]'));
                paint(search);
            });
            search.querySelector('[data-clear]').addEventListener('click', function () {
                clearChoice(search);
            });
            search.closest('[data-seat]').querySelector('[data-use-guest]').addEventListener('click', function () {
                choose(search, 'convidado', 'Convidado', true);
                search.closest('[data-seat]').querySelector('[data-guest-name]').focus();
            });
            query.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    moveActive(search, 1);
                    return;
                }
                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    moveActive(search, -1);
                    return;
                }
                if (event.key === 'Escape') {
                    closeResults(null);
                    return;
                }
                if (event.key !== 'Enter') {
                    return;
                }
                if (fold(query.value.trim()) === '') {
                    return;
                }
                event.preventDefault();
                const active = search.querySelector('[data-results] button.is-active');
                if (!active) {
                    return;
                }
                const player = players.filter(function (item) {
                    return String(item.id) === active.dataset.playerId;
                })[0];
                if (player) {
                    choose(search, String(player.id), labelOf(player), false);
                }
            });
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('[data-search]')) {
                closeResults(null);
            }
        });

        sync();
    });
</script>
