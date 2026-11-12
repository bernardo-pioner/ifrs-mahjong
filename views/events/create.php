<?php
$old = $old ?? [];
$tipoAtual = $old['tipo'] ?? 'encontro';
$modalidadeAtual = $old['modalidade'] ?? 'livre';
$temTermino = ($old['tem_termino'] ?? '0') === '1';
$temLimite = ($old['tem_limite'] ?? '0') === '1';
$temPagamento = ($old['tem_pagamento'] ?? '0') === '1';
$recorrente = ($old['recorrente'] ?? '0') === '1';
$isEdit = isset($evento);
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
                    'encontro' => ['Encontro casual', ''],
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
                <div class="event-datetime-field">
                    <label for="recorrencia_dias">Repetir a cada (dias)</label>
                    <input
                        id="recorrencia_dias"
                        name="recorrencia_dias"
                        type="number"
                        min="1"
                        max="365"
                        value="<?= eventFormValue($old, 'recorrencia_dias', '7') ?>"
                        <?= $recorrente ? '' : 'disabled' ?>
                    >
                </div>

                <div class="event-datetime-field">
                    <label for="recorrencia_ocorrencias">Número de ocorrências</label>
                    <input
                        id="recorrencia_ocorrencias"
                        name="recorrencia_ocorrencias"
                        type="number"
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
                maxlength="5000"
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
        

        <div class="event-form-actions">
            <a class="button button-secondary" href="/eventos">Cancelar</a>

            <button class="button button-primary" type="submit">
                <?= e($textoBotao ?? 'Criar evento') ?>
                <span aria-hidden="true">→</span>
            </button>
        </div>
    </form>
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
        const temTermino = document.getElementById('tem_termino');
        const campoTermino = document.getElementById('campo-termino');
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
            temLimite.disabled = !mostrar;
            temPagamento.disabled = !mostrar;
            if (!mostrar) {
                temLimite.checked = false;
                temPagamento.checked = false;
            }
            atualizarCampoLimiteValor();
            atualizarCampoTaxa();
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

        function atualizarCampoTermino() {
            const mostrar = temTermino.checked;
            campoTermino.hidden = !mostrar;
            dataFim.disabled = !mostrar;
            horarioFim.disabled = !mostrar;
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
        temTermino.addEventListener('change', atualizarCampoTermino);
        if (recorrente) {
            recorrente.addEventListener('change', atualizarCampoRecorrencia);
        }

        atualizarCamposTipo();
        atualizarCampoModalidade();
        atualizarCampoTermino();
        atualizarCampoRecorrencia();
    });
</script>
