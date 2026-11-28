<?php
$old = $old ?? [];
$tipoAtual = $old['tipo'] ?? 'encontro';
$modalidadeAtual = $old['modalidade'] ?? 'livre';
$temLimite = ($old['tem_limite'] ?? '0') === '1';
$temPagamento = ($old['tem_pagamento'] ?? '0') === '1';
$temPremiacao = $tipoAtual === 'torneio' && ($old['tem_premiacao'] ?? '0') === '1';
$premiacaoItens = is_array($old['premiacao'] ?? null) ? array_values($old['premiacao']) : [];
$premiacaoQtd = (int) ($old['premiacao_qtd'] ?? (count($premiacaoItens) > 0 ? count($premiacaoItens) : 3));
if ($premiacaoQtd < 1 || $premiacaoQtd > 10) {
    $premiacaoQtd = 3;
}
$recorrente = ($old['recorrente'] ?? '0') === '1';
$diasEvento = is_array($old['dias'] ?? null) ? array_values($old['dias']) : [];
if ($diasEvento === []) {
    $diasEvento = [[
        'data' => isset($old['data']) && is_string($old['data']) ? $old['data'] : '',
        'inicio' => isset($old['horario']) && is_string($old['horario']) ? $old['horario'] : '14:00',
        'fim' => '',
    ]];
}
$isEdit = isset($evento);
$dataMinima = (new DateTimeImmutable('today'))->format('Y-m-d');
$tituloPagina = $tituloPagina ?? 'Criar evento';
$descricaoPagina = $descricaoPagina ?? 'Preencha os dados para publicar um novo evento do clube.';
$modalidades = eventModalidadeOptions();

function eventFormValue(array $old, string $key, string $default = ''): string
{
    return e(isset($old[$key]) && is_string($old[$key]) ? $old[$key] : $default);
}

function eventDayValue(array $dia, string $key, string $default = ''): string
{
    $value = $dia[$key] ?? $default;

    return e(is_string($value) ? $value : $default);
}
?>

<section class="event-form-page event-form-page--composer">
    <header class="event-form-header">
        <div class="page-title-row">
            <a class="page-back" href="/eventos">← Voltar para eventos</a>
            <h1><?= e($tituloPagina) ?></h1>
        </div>
        <p><?= e($descricaoPagina) ?></p>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="flash flash-error" role="alert">
            <strong>Confira os dados informados:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form
        class="event-form-card"
        method="post"
        action="<?= e($acaoFormulario ?? '/eventos/novo') ?>"
    >
        <?= csrfField() ?>
        <?php if (!empty($perguntarRecorrenciasFuturas)): ?>
            <input type="hidden" name="aplicar_serie" id="aplicar_serie" value="atual">
        <?php endif; ?>

        <div class="form-field">
            <label for="titulo">Nome do evento</label>
            <input
                id="titulo"
                name="titulo"
                type="text"
                maxlength="160"
                required
                value="<?= eventFormValue($old, 'titulo') ?>"
                placeholder="Ex.: Encontro do Clube"
            >
        </div>

        <fieldset class="form-field">
            <legend>Tipo de evento</legend>

            <div class="event-type-options">
                <?php
                $tipos = [
                    'encontro' => ['Encontro', ''],
                    'oficina' => ['Oficina', ''],
                    'torneio' => ['Torneio', ''],
                ];
                ?>

                <?php foreach ($tipos as $valor => [$rotulo, $icone]): ?>
                    <label class="event-type-option">
                        <input
                            type="radio"
                            name="tipo"
                            value="<?= e($valor) ?>"
                            <?= $tipoAtual === $valor ? 'checked' : '' ?>
                        >
                        <span class="event-type-option-content">
                            <span class="event-type-symbol" aria-hidden="true"><?= e($icone) ?></span>
                            <span><?= e($rotulo) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="form-field" id="campo-modalidade" <?= $tipoAtual === 'torneio' ? '' : 'hidden' ?>>
            <label for="modalidade">Modalidade</label>
            <select id="modalidade" name="modalidade" <?= $tipoAtual === 'torneio' ? 'required' : 'disabled' ?>>
                <?php foreach ($modalidades as $valor => $rotulo): ?>
                    <option
                        value="<?= e($valor) ?>"
                        <?= $modalidadeAtual === $valor ? 'selected' : '' ?>
                    >
                        <?= e($rotulo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

       

        

        <div id="evento-dias" class="event-days" data-min="<?= e($dataMinima) ?>">
            <?php foreach ($diasEvento as $indice => $dia): ?>
                <?php $dia = is_array($dia) ? $dia : []; ?>
                <div class="event-day-row" data-day-row>
                    <div class="event-datetime-grid event-day-grid">
                        <div class="event-datetime-field">
                            <label for="dia_data_<?= (int) $indice ?>">Data</label>
                            <input
                                id="dia_data_<?= (int) $indice ?>"
                                name="dias[<?= (int) $indice ?>][data]"
                                type="date"
                                required
                                <?= $dataMinima !== '' ? 'min="' . e($dataMinima) . '"' : '' ?>
                                value="<?= eventDayValue($dia, 'data') ?>"
                            >
                        </div>
                        <div class="event-datetime-field">
                            <label for="dia_inicio_<?= (int) $indice ?>">Hora de início</label>
                            <input
                                id="dia_inicio_<?= (int) $indice ?>"
                                name="dias[<?= (int) $indice ?>][inicio]"
                                type="time"
                                required
                                value="<?= eventDayValue($dia, 'inicio', '14:00') ?>"
                            >
                        </div>
                        <div class="event-datetime-field">
                            <label for="dia_fim_<?= (int) $indice ?>">Hora de fim <span class="event-optional">(opcional)</span></label>
                            <input
                                id="dia_fim_<?= (int) $indice ?>"
                                name="dias[<?= (int) $indice ?>][fim]"
                                type="time"
                                value="<?= eventDayValue($dia, 'fim') ?>"
                            >
                        </div>
                    </div>
                    <button type="button" class="event-match-delete event-day-remove" aria-label="Remover dia">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9.2 5V3.4a1 1 0 0 1 1-1h3.6a1 1 0 0 1 1 1V5"></path>
                            <path d="M4.2 6.2h15.6"></path>
                            <path d="M6.4 6.2l.9 12.4a1.4 1.4 0 0 0 1.4 1.3h6.6a1.4 1.4 0 0 0 1.4-1.3l.9-12.4"></path>
                            <path d="M10 9.2v7.4"></path>
                            <path d="M12 9.2v7.4"></path>
                            <path d="M14 9.2v7.4"></path>
                        </svg>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="event-day-actions">
            <button type="button" class="button button-secondary" id="adicionar-dia">Adicionar dia</button>
        </div>
        <template id="event-day-template">
                <div class="event-day-row" data-day-row>
                    <div class="event-datetime-grid event-day-grid">
                        <div class="event-datetime-field">
                            <label>Data</label>
                            <input type="date" data-day-part="data" required <?= $dataMinima !== '' ? 'min="' . e($dataMinima) . '"' : '' ?>>
                        </div>
                        <div class="event-datetime-field">
                            <label>Hora de início</label>
                            <input type="time" data-day-part="inicio" value="14:00" required>
                        </div>
                        <div class="event-datetime-field">
                            <label>Hora de fim <span class="event-optional">(opcional)</span></label>
                            <input type="time" data-day-part="fim">
                        </div>
                    </div>
                    <button type="button" class="event-match-delete event-day-remove" aria-label="Remover dia">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9.2 5V3.4a1 1 0 0 1 1-1h3.6a1 1 0 0 1 1 1V5"></path>
                            <path d="M4.2 6.2h15.6"></path>
                            <path d="M6.4 6.2l.9 12.4a1.4 1.4 0 0 0 1.4 1.3h6.6a1.4 1.4 0 0 0 1.4-1.3l.9-12.4"></path>
                            <path d="M10 9.2v7.4"></path>
                            <path d="M12 9.2v7.4"></path>
                            <path d="M14 9.2v7.4"></path>
                        </svg>
                    </button>
                </div>
        </template>

        <?php if (!$isEdit): ?>

            <label class="event-registration-option">
                <input
                    type="checkbox"
                    id="recorrente"
                    name="recorrente"
                    value="1"
                    <?= $recorrente ? 'checked' : '' ?>
                >
                <span>
                    <strong>Evento recorrente</strong>
                </span>
            </label>

            <div
                class="event-datetime-grid"
                id="campo-recorrencia"
                <?= $recorrente ? '' : 'hidden' ?>
            >
                <div class="form-field">
                    <label for="recorrencia_dias">Repetir a cada (dias)</label>
                    <input
                        id="recorrencia_dias"
                        name="recorrencia_dias"
                        type="number"
                        inputmode="numeric"
                        min="1"
                        max="365"
                        value="<?= eventFormValue($old, 'recorrencia_dias', '7') ?>"
                        <?= $recorrente ? '' : 'disabled' ?>
                    >
                </div>

                <div class="form-field">
                    <label for="recorrencia_ocorrencias">Número de ocorrências</label>
                    <input
                        id="recorrencia_ocorrencias"
                        name="recorrencia_ocorrencias"
                        type="number"
                        inputmode="numeric"
                        min="2"
                        max="52"
                        value="<?= eventFormValue($old, 'recorrencia_ocorrencias', '4') ?>"
                        <?= $recorrente ? '' : 'disabled' ?>
                    >
                </div>
            </div>
        <?php endif; ?>


        <div class="form-field">
            <label for="local">Local</label>
            <input
                id="local"
                name="local"
                type="text"
                maxlength="200"
                value="<?= eventFormValue($old, 'local') ?>"
                placeholder="Ex.: Sede do clube"
            >
        </div>

        <div class="form-field">
            <label for="descricao-editor">Descrição</label>
            <div class="rich-text">
                <div class="rich-text-toolbar" role="toolbar" aria-label="Formatação da descrição">
                    <button type="button" data-format="bold" aria-label="Negrito" title="Negrito"><strong>N</strong></button>
                    <button type="button" data-format="italic" aria-label="Itálico" title="Itálico"><em>I</em></button>
                    <button type="button" data-format="underline" aria-label="Sublinhado" title="Sublinhado"><span class="rich-text-underline">S</span></button>
                    <span class="rich-text-separator" aria-hidden="true"></span>
                    <button type="button" data-format="h1" aria-label="Título 1" title="Título 1">Título 1</button>
                    <button type="button" data-format="h2" aria-label="Título 2" title="Título 2">Título 2</button>
                    <button type="button" data-format="h3" aria-label="Título 3" title="Título 3">Título 3</button>
                    <button type="button" data-format="p" aria-label="Texto" title="Texto">Texto</button>
                </div>
                <div
                    id="descricao-editor"
                    class="rich-text-body"
                    contenteditable="true"
                    role="textbox"
                    aria-multiline="true"
                    data-placeholder="Conte aos participantes como será o evento."
                ><?= formatEventDescription(isset($old['descricao']) && is_string($old['descricao']) ? $old['descricao'] : '') ?></div>
                <textarea id="descricao" name="descricao" hidden><?= eventFormValue($old, 'descricao') ?></textarea>
            </div>
        </div>

        

        <div class="event-form-divider"></div>
        <div
            class="event-option-block"
            id="campo-limite"
            <?= $tipoAtual === 'encontro' ? 'hidden' : '' ?>
        >
            <div class="form-field">
                <label for="inscricoes">Inscrições</label>
            </div>
            <label class="event-registration-option" for="tem_limite">
                <input
                    type="checkbox"
                    id="tem_limite"
                    name="tem_limite"
                    value="1"
                    <?= $temLimite ? 'checked' : '' ?>
                    <?= $tipoAtual === 'encontro' ? 'disabled' : '' ?>
                >
                <span>
                    <strong>Definir limite de participantes</strong>
                </span>
            </label>

            <div
                id="campo-limite-valor"
                class="form-field event-nested-field"
                <?= $temLimite ? '' : 'hidden' ?>
            >
                <input
                    id="limite"
                    name="limite"
                    type="number"
                    min="1"
                    max="10000"
                    value="<?= eventFormValue($old, 'limite') ?>"
                    placeholder="Ex.: 16"
                    <?= $temLimite ? '' : 'disabled' ?>
                >
            </div>
        </div>

        <div
            class="event-option-block"
            id="campo-pagamento"
            <?= $tipoAtual === 'encontro' ? 'hidden' : '' ?>
        >
            <label class="event-registration-option" for="tem_pagamento">
                <input
                    type="checkbox"
                    id="tem_pagamento"
                    name="tem_pagamento"
                    value="1"
                    <?= $temPagamento ? 'checked' : '' ?>
                    <?= $tipoAtual === 'encontro' ? 'disabled' : '' ?>
                >
                <span>
                    <strong>Cobrar taxa de participação</strong>
                </span>
            </label>

            <div id="campo-taxa" class="form-field event-nested-field" <?= $temPagamento ? '' : 'hidden' ?>>
                <label for="taxa">Valor (R$)</label>
                <input
                    id="taxa"
                    name="taxa"
                    type="number"
                    min="0.01"
                    step="0.01"
                    value="<?= eventFormValue($old, 'taxa') ?>"
                    placeholder="Ex.: 25.00"
                    <?= $temPagamento ? '' : 'disabled' ?>
                >
            </div>
        </div>

        <div
            class="event-option-block"
            id="campo-premiacao"
            <?= $tipoAtual === 'torneio' ? '' : 'hidden' ?>
        >
            <label class="event-registration-option" for="tem_premiacao">
                <input
                    type="checkbox"
                    id="tem_premiacao"
                    name="tem_premiacao"
                    value="1"
                    <?= $temPremiacao ? 'checked' : '' ?>
                    <?= $tipoAtual === 'torneio' ? '' : 'disabled' ?>
                >
                <span>
                    <strong>Definir premiação</strong>
                </span>
            </label>

            <div
                id="campo-premiacao-detalhes"
                class="event-nested-field event-premiacao-fields"
                <?= $temPremiacao ? '' : 'hidden' ?>
            >
                <div class="form-field">
                    <label for="premiacao_qtd">Colocações premiadas</label>
                    <select
                        id="premiacao_qtd"
                        name="premiacao_qtd"
                        <?= $temPremiacao ? '' : 'disabled' ?>
                    >
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>" <?= $premiacaoQtd === $i ? 'selected' : '' ?>>
                                <?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div id="campo-premiacao-itens" class="event-premiacao-itens">
                    <?php for ($i = 0; $i < $premiacaoQtd; $i++): ?>
                        <div class="form-field">
                            <label for="premiacao_<?= $i + 1 ?>"><?= $i + 1 ?>º lugar</label>
                            <input
                                id="premiacao_<?= $i + 1 ?>"
                                name="premiacao[]"
                                type="text"
                                maxlength="200"
                                value="<?= e((string) ($premiacaoItens[$i] ?? '')) ?>"
                                placeholder="Ex.: Troféu ou inscrição no próximo torneio"
                                <?= $temPremiacao ? '' : 'disabled' ?>
                            >
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div class="event-form-actions">
            <a class="button button-secondary" href="/eventos">Cancelar</a>

            <button class="button button-primary" type="submit">
                <?= e($textoBotao ?? 'Criar evento') ?>
                <span aria-hidden="true">→</span>
            </button>
        </div>
    </form>

    <?php if (!empty($perguntarRecorrenciasFuturas)): ?>
        <div
            id="series-choice-modal"
            class="series-choice-modal"
            hidden
            aria-hidden="true"
        >
            <div class="series-choice-backdrop" data-close-series-choice></div>
            <div
                class="series-choice-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="series-choice-title"
            >
                <button
                    type="button"
                    class="user-details-close"
                    data-close-series-choice
                    aria-label="Fechar"
                >×</button>
                <h2 id="series-choice-title">Editar recorrência</h2>
                <p>As ocorrências encerradas permanecem como estão. O que você deseja atualizar?</p>
                <div class="series-choice-actions is-stacked">
                    <button type="button" class="button button-secondary" id="series-choice-current">
                        Apenas esta ocorrência
                    </button>
                    <button type="button" class="button button-secondary" id="series-choice-future">
                        Esta e as seguintes
                    </button>
                    <button type="button" class="button button-primary" id="series-choice-active">
                        Todas as ocorrências ativas
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>

<script src="/assets/js/event-description-editor.js?v=<?= (int) @filemtime(ROOT_PATH . '/public/assets/js/event-description-editor.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tipos = document.querySelectorAll('input[name="tipo"]');
        const campoLimite = document.getElementById('campo-limite');
        const temLimite = document.getElementById('tem_limite');
        const campoLimiteValor = document.getElementById('campo-limite-valor');
        const inputLimite = document.getElementById('limite');
        const campoPagamento = document.getElementById('campo-pagamento');
        const temPagamento = document.getElementById('tem_pagamento');
        const campoTaxa = document.getElementById('campo-taxa');
        const inputTaxa = document.getElementById('taxa');
        const campoPremiacao = document.getElementById('campo-premiacao');
        const temPremiacao = document.getElementById('tem_premiacao');
        const campoPremiacaoDetalhes = document.getElementById('campo-premiacao-detalhes');
        const selectPremiacaoQtd = document.getElementById('premiacao_qtd');
        const campoPremiacaoItens = document.getElementById('campo-premiacao-itens');
        const listaDias = document.getElementById('evento-dias');
        const botaoAdicionarDia = document.getElementById('adicionar-dia');
        const modeloDia = document.getElementById('event-day-template');
        const recorrente = document.getElementById('recorrente');
        const campoRecorrencia = document.getElementById('campo-recorrencia');
        const recorrenciaDias = document.getElementById('recorrencia_dias');
        const recorrenciaOcorrencias = document.getElementById('recorrencia_ocorrencias');
        const campoModalidade = document.getElementById('campo-modalidade');
        const inputModalidade = document.getElementById('modalidade');

        function tipoComExtras() {
            const tipoSelecionado = document.querySelector('input[name="tipo"]:checked');
            return tipoSelecionado &&
                (tipoSelecionado.value === 'torneio' || tipoSelecionado.value === 'oficina');
        }

        function tipoComPremiacao() {
            const tipoSelecionado = document.querySelector('input[name="tipo"]:checked');
            return tipoSelecionado && tipoSelecionado.value === 'torneio';
        }

        function atualizarCampoModalidade() {
            const tipoSelecionado = document.querySelector('input[name="tipo"]:checked');
            const mostrar = tipoSelecionado && tipoSelecionado.value === 'torneio';
            campoModalidade.hidden = !mostrar;
            inputModalidade.disabled = !mostrar;
            inputModalidade.required = mostrar;
        }

        function atualizarCamposTipo() {
            const mostrar = tipoComExtras();
            const mostrarPremiacao = tipoComPremiacao();
            campoLimite.hidden = !mostrar;
            campoPagamento.hidden = !mostrar;
            if (campoPremiacao) {
                campoPremiacao.hidden = !mostrarPremiacao;
            }
            temLimite.disabled = !mostrar;
            temPagamento.disabled = !mostrar;
            if (temPremiacao) {
                temPremiacao.disabled = !mostrarPremiacao;
            }
            if (!mostrar) {
                temLimite.checked = false;
                temPagamento.checked = false;
            }
            if (!mostrarPremiacao && temPremiacao) {
                temPremiacao.checked = false;
            }
            atualizarCampoLimiteValor();
            atualizarCampoTaxa();
            atualizarCampoPremiacao();
        }

        function atualizarCampoLimiteValor() {
            const mostrar = tipoComExtras() && temLimite.checked;
            campoLimiteValor.hidden = !mostrar;
            inputLimite.disabled = !mostrar;
        }

        function atualizarCampoTaxa() {
            const mostrar = tipoComExtras() && temPagamento.checked;
            campoTaxa.hidden = !mostrar;
            inputTaxa.disabled = !mostrar;
        }

        function atualizarCampoPremiacao() {
            if (!temPremiacao || !campoPremiacaoDetalhes || !selectPremiacaoQtd || !campoPremiacaoItens) {
                return;
            }

            const mostrar = tipoComPremiacao() && temPremiacao.checked;
            campoPremiacaoDetalhes.hidden = !mostrar;
            selectPremiacaoQtd.disabled = !mostrar;
            atualizarItensPremiacao(mostrar);
        }

        function atualizarItensPremiacao(habilitar) {
            if (!selectPremiacaoQtd || !campoPremiacaoItens) {
                return;
            }

            const qtd = Math.min(10, Math.max(1, parseInt(selectPremiacaoQtd.value, 10) || 1));
            selectPremiacaoQtd.value = String(qtd);

            const valores = Array.from(
                campoPremiacaoItens.querySelectorAll('input[name="premiacao[]"]')
            ).map(function (input) {
                return input.value;
            });

            function escapeAttr(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;');
            }

            let html = '';
            for (let i = 0; i < qtd; i++) {
                const valor = valores[i] || '';
                html +=
                    '<div class="form-field">' +
                    '<label for="premiacao_' + (i + 1) + '">' + (i + 1) + 'º lugar</label>' +
                    '<input id="premiacao_' + (i + 1) + '" name="premiacao[]" type="text" maxlength="200" ' +
                    'value="' + escapeAttr(valor) + '" ' +
                    'placeholder="Ex.: Troféu ou inscrição no próximo torneio"' +
                    (habilitar ? '' : ' disabled') +
                    '>' +
                    '</div>';
            }

            campoPremiacaoItens.innerHTML = html;
        }

        function atualizarDias() {
            if (!listaDias) {
                return;
            }

            const linhas = listaDias.querySelectorAll('[data-day-row]');
            linhas.forEach(function (linha, indice) {
                linha.querySelectorAll('[data-day-part], input[name^="dias["]').forEach(function (campo) {
                    const parte = campo.getAttribute('data-day-part')
                        || (campo.name.match(/\[(data|inicio|fim)\]$/) || [])[1];
                    if (!parte) {
                        return;
                    }
                    campo.name = 'dias[' + indice + '][' + parte + ']';
                    campo.id = 'dia_' + parte + '_' + indice;
                    const campoDia = campo.closest('.event-datetime-field');
                    const rotulo = campoDia ? campoDia.querySelector('label') : null;
                    if (rotulo) {
                        rotulo.setAttribute('for', campo.id);
                    }
                });
            });

            const unico = linhas.length <= 1;
            listaDias.querySelectorAll('.event-day-remove').forEach(function (botao) {
                botao.disabled = unico;
            });

            if (botaoAdicionarDia) {
                botaoAdicionarDia.disabled = linhas.length >= 31;
            }

            let minimo = listaDias.getAttribute('data-min') || '';
            linhas.forEach(function (linha) {
                const campoData = linha.querySelector('input[type="date"]');
                if (!campoData) {
                    return;
                }
                if (minimo) {
                    campoData.min = minimo;
                    if (campoData.value && campoData.value < minimo) {
                        campoData.value = '';
                    }
                } else {
                    campoData.removeAttribute('min');
                }
                minimo = campoData.value ? somarUmDia(campoData.value) : minimo;
            });
        }

        function somarUmDia(valor) {
            const partes = /^(\d{4})-(\d{2})-(\d{2})$/.exec(valor);
            if (!partes) {
                return '';
            }
            const data = new Date(Date.UTC(Number(partes[1]), Number(partes[2]) - 1, Number(partes[3])));
            data.setUTCDate(data.getUTCDate() + 1);
            const mes = String(data.getUTCMonth() + 1).padStart(2, '0');
            const dia = String(data.getUTCDate()).padStart(2, '0');
            return data.getUTCFullYear() + '-' + mes + '-' + dia;
        }

        function atualizarCampoRecorrencia() {
            if (!recorrente || !campoRecorrencia) {
                return;
            }
            const mostrar = recorrente.checked;
            campoRecorrencia.hidden = !mostrar;
            if (recorrenciaDias) recorrenciaDias.disabled = !mostrar;
            if (recorrenciaOcorrencias) recorrenciaOcorrencias.disabled = !mostrar;
        }

        tipos.forEach(function (tipo) {
            tipo.addEventListener('change', function () {
                atualizarCamposTipo();
                atualizarCampoModalidade();
            });
        });
        temLimite.addEventListener('change', atualizarCampoLimiteValor);
        temPagamento.addEventListener('change', atualizarCampoTaxa);
        if (temPremiacao) {
            temPremiacao.addEventListener('change', atualizarCampoPremiacao);
        }
        if (selectPremiacaoQtd) {
            selectPremiacaoQtd.addEventListener('change', function () {
                atualizarItensPremiacao(tipoComExtras() && temPremiacao && temPremiacao.checked);
            });
        }
        if (recorrente) {
            recorrente.addEventListener('change', atualizarCampoRecorrencia);
        }
        if (botaoAdicionarDia && modeloDia && listaDias) {
            botaoAdicionarDia.addEventListener('click', function () {
                if (listaDias.querySelectorAll('[data-day-row]').length >= 31) {
                    return;
                }
                listaDias.appendChild(modeloDia.content.cloneNode(true));
                atualizarDias();
            });
            listaDias.addEventListener('change', function (event) {
                const campo = event.target;
                if (campo && campo.matches && campo.matches('input[type="date"]')) {
                    atualizarDias();
                }
            });
            listaDias.addEventListener('click', function (event) {
                const remover = event.target.closest('.event-day-remove');
                if (!remover) {
                    return;
                }
                const linha = remover.closest('[data-day-row]');
                if (linha && listaDias.querySelectorAll('[data-day-row]').length > 1) {
                    linha.remove();
                    atualizarDias();
                }
            });
        }

        atualizarCamposTipo();
        atualizarCampoModalidade();
        atualizarDias();
        atualizarCampoRecorrencia();

        const formEvento = document.querySelector('.event-form-card');
        const aplicarSerie = document.getElementById('aplicar_serie');
        const seriesModal = document.getElementById('series-choice-modal');
        if (formEvento && aplicarSerie && seriesModal) {
            const seriesDialog = seriesModal.querySelector('.series-choice-dialog');

            function abrirEscolhaSerie() {
                seriesModal.hidden = false;
                seriesModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('series-choice-open');
                const ativas = document.getElementById('series-choice-active');
                if (ativas) {
                    ativas.focus();
                }
            }

            function fecharEscolhaSerie() {
                seriesModal.hidden = true;
                seriesModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('series-choice-open');
            }

            function escolherSerie(escopo) {
                aplicarSerie.value = escopo;
                aplicarSerie.dataset.asked = '1';
                fecharEscolhaSerie();
                formEvento.requestSubmit();
            }

            formEvento.addEventListener('submit', function (event) {
                if (aplicarSerie.dataset.asked === '1') {
                    return;
                }
                event.preventDefault();
                abrirEscolhaSerie();
            });

            document.getElementById('series-choice-current').addEventListener('click', function () {
                escolherSerie('atual');
            });
            document.getElementById('series-choice-future').addEventListener('click', function () {
                escolherSerie('seguintes');
            });
            document.getElementById('series-choice-active').addEventListener('click', function () {
                escolherSerie('ativas');
            });
            seriesModal.querySelectorAll('[data-close-series-choice]').forEach(function (el) {
                el.addEventListener('click', fecharEscolhaSerie);
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !seriesModal.hidden) {
                    fecharEscolhaSerie();
                }
            });
            if (seriesDialog) {
                seriesDialog.addEventListener('click', function (event) {
                    event.stopPropagation();
                });
            }
        }

        if (formEvento) {
            let enviando = false;
            formEvento.addEventListener('submit', function (event) {
                if (event.defaultPrevented) {
                    return;
                }
                if (enviando) {
                    event.preventDefault();
                    return;
                }
                enviando = true;
                const botao = formEvento.querySelector('button[type="submit"]');
                window.setTimeout(function () {
                    if (botao) {
                        botao.disabled = true;
                    }
                }, 0);
            });
        }
    });
</script>
