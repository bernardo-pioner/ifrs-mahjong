<?php
$old = $old ?? [];
$tipoAtual = $old['tipo'] ?? 'encontro';
$modalidadeAtual = $old['modalidade'] ?? 'livre';
$temTermino = ($old['tem_termino'] ?? '0') === '1';
$temLimite = ($old['tem_limite'] ?? '0') === '1';
$temPagamento = ($old['tem_pagamento'] ?? '0') === '1';
$temPremiacao = ($old['tem_premiacao'] ?? '0') === '1';
$premiacaoItens = is_array($old['premiacao'] ?? null) ? array_values($old['premiacao']) : [];
$premiacaoQtd = (int) ($old['premiacao_qtd'] ?? (count($premiacaoItens) > 0 ? count($premiacaoItens) : 3));
if ($premiacaoQtd < 1 || $premiacaoQtd > 10) {
    $premiacaoQtd = 3;
}
$recorrente = ($old['recorrente'] ?? '0') === '1';
$isEdit = isset($evento);
$dataMinima = (new DateTimeImmutable('today'))->format('Y-m-d');
$tituloPagina = $tituloPagina ?? 'Criar evento';
$descricaoPagina = $descricaoPagina ?? 'Preencha os dados para publicar um novo evento do clube.';
$modalidades = eventModalidadeOptions();

function eventFormValue(array $old, string $key, string $default = ''): string
{
    return e(isset($old[$key]) && is_string($old[$key]) ? $old[$key] : $default);
}
?>

<section class="event-form-page">
    <header class="event-form-header">
        <a class="event-back-link" href="/eventos">← Voltar para eventos</a>
        <h1><?= e($tituloPagina) ?></h1>
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

       

        

        <div class="event-datetime-grid">
            <div class="event-datetime-field">
                <label for="data">Data de início</label>
                <input
                    id="data"
                    name="data"
                    type="date"
                    required
                    <?= $dataMinima !== '' ? 'min="' . e($dataMinima) . '"' : '' ?>
                    value="<?= eventFormValue($old, 'data') ?>"
                >
            </div>

            <div class="event-datetime-field">
                <label for="horario">Horário de início</label>
                <input
                    id="horario"
                    name="horario"
                    type="time"
                    required
                    value="<?= eventFormValue($old, 'horario', '14:00') ?>"
                >
            </div>
        </div>

        <label class="event-registration-option">
            <input
                type="checkbox"
                id="tem_termino"
                name="tem_termino"
                value="1"
                <?= $temTermino ? 'checked' : '' ?>
            >
            <span>
                <strong>Informar data de término</strong>
            </span>
        </label>

        <div
            class="event-datetime-grid"
            id="campo-termino"
            <?= $temTermino ? '' : 'hidden' ?>
        >
            <div class="event-datetime-field">
                <label for="data_fim">Data de término</label>
                <input
                    id="data_fim"
                    name="data_fim"
                    type="date"
                    <?= $dataMinima !== '' ? 'min="' . e($dataMinima) . '"' : '' ?>
                    value="<?= eventFormValue($old, 'data_fim') ?>"
                    <?= $temTermino ? '' : 'disabled' ?>
                >
            </div>

            <div class="event-datetime-field">
                <label for="horario_fim">Horário de término</label>
                <input
                    id="horario_fim"
                    name="horario_fim"
                    type="time"
                    value="<?= eventFormValue($old, 'horario_fim', '16:00') ?>"
                    <?= $temTermino ? '' : 'disabled' ?>
                >
            </div>
        </div>

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
            <label for="descricao">Descrição</label>
            <textarea
                id="descricao"
                name="descricao"
                rows="4"
                maxlength="2500"
                placeholder="Conte aos participantes como será o evento."
            ><?= eventFormValue($old, 'descricao') ?></textarea>
        </div>

        

        <div class="event-form-divider"></div>
        <div class="form-field">
            <label for="inscricoes">Inscrições</label>
        </div>
        <label class="event-registration-option">
            <input
                type="checkbox"
                id="inscricoes"
                name="inscricoes"
                value="1"
                <?= !isset($old['inscricoes']) || $old['inscricoes'] === '1' ? 'checked' : '' ?>
            >
            <span>
                <strong>Permitir inscrições de participantes</strong>
            </span>
        </label>
        
        <div
            class="event-option-block"
            id="campo-limite"
            <?= $tipoAtual === 'encontro' ? 'hidden' : '' ?>
        >
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
            <?= $tipoAtual === 'encontro' ? 'hidden' : '' ?>
        >
            <label class="event-registration-option" for="tem_premiacao">
                <input
                    type="checkbox"
                    id="tem_premiacao"
                    name="tem_premiacao"
                    value="1"
                    <?= $temPremiacao ? 'checked' : '' ?>
                    <?= $tipoAtual === 'encontro' ? 'disabled' : '' ?>
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
        const temTermino = document.getElementById('tem_termino');
        const campoTermino = document.getElementById('campo-termino');
        const dataInicio = document.getElementById('data');
        const dataFim = document.getElementById('data_fim');
        const horarioFim = document.getElementById('horario_fim');
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

        function atualizarCampoModalidade() {
            const tipoSelecionado = document.querySelector('input[name="tipo"]:checked');
            const mostrar = tipoSelecionado && tipoSelecionado.value === 'torneio';
            campoModalidade.hidden = !mostrar;
            inputModalidade.disabled = !mostrar;
            inputModalidade.required = mostrar;
        }

        function atualizarCamposTipo() {
            const mostrar = tipoComExtras();
            campoLimite.hidden = !mostrar;
            campoPagamento.hidden = !mostrar;
            if (campoPremiacao) {
                campoPremiacao.hidden = !mostrar;
            }
            temLimite.disabled = !mostrar;
            temPagamento.disabled = !mostrar;
            if (temPremiacao) {
                temPremiacao.disabled = !mostrar;
            }
            if (!mostrar) {
                temLimite.checked = false;
                temPagamento.checked = false;
                if (temPremiacao) {
                    temPremiacao.checked = false;
                }
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

            const mostrar = tipoComExtras() && temPremiacao.checked;
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

        function atualizarCampoTermino() {
            const mostrar = temTermino.checked;
            campoTermino.hidden = !mostrar;
            dataFim.disabled = !mostrar;
            horarioFim.disabled = !mostrar;
            atualizarDataMinimaTermino();
        }

        function atualizarDataMinimaTermino() {
            if (!dataInicio || !dataFim || !dataInicio.min) {
                return;
            }

            const minima = dataInicio.value !== '' && dataInicio.value > dataInicio.min
                ? dataInicio.value
                : dataInicio.min;
            dataFim.min = minima;
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
        temTermino.addEventListener('change', atualizarCampoTermino);
        if (dataInicio) {
            dataInicio.addEventListener('change', atualizarDataMinimaTermino);
        }
        if (recorrente) {
            recorrente.addEventListener('change', atualizarCampoRecorrencia);
        }

        atualizarCamposTipo();
        atualizarCampoModalidade();
        atualizarCampoTermino();
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
    });
</script>
