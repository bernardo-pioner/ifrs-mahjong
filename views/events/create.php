
<?php
$old = $old ?? [];
$tipoAtual = $old['tipo'] ?? 'encontro';

function eventFormValue(array $old, string $key, string $default = ''): string
{
    return e(isset($old[$key]) && is_string($old[$key]) ? $old[$key] : $default);
}
?>

<section class="event-form-page">
    <header class="event-form-header">
        <div>
            <a class="event-back-link" href="/eventos">← Voltar para eventos</a>
            <h1><?= e($tituloPagina ?? 'Criar novo evento') ?></h1>
            <p><?= e($descricaoPagina ?? 'Informações básicas do evento do clube.') ?></p>
        </div>
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
                placeholder="Ex.: Encontro de Riichi"
            >
        </div>

        <fieldset class="form-field">
            <legend>Tipo de evento</legend>

            <div class="event-type-options">
                <?php
                $tipos = [
                    'encontro' => ['Encontro casual', '♧'],
                    'oficina' => ['Oficina', '▤'],
                    'torneio' => ['Torneio', '♜'],
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

            <div class="event-datetime-field">
                <label for="data_fim">Data de término</label>
                <input
                    id="data_fim"
                    name="data_fim"
                    type="date"
                    required
                    value="<?= eventFormValue($old, 'data_fim') ?>"
                >
            </div>

            <div class="event-datetime-field">
                <label for="horario_fim">Horário de término</label>
                <input
                    id="horario_fim"
                    name="horario_fim"
                    type="time"
                    required
                    value="<?= eventFormValue($old, 'horario_fim', '16:00') ?>"
                >
            </div>
        </div>

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
            <label for="limite">Limite de participantes</label>
            <input
                id="limite"
                name="limite"
                type="number"
                min="1"
                max="10000"
                value="<?= eventFormValue($old, 'limite', '16') ?>"
                placeholder="Ex.: 16"
            >
            <small>Opcional. Aplicável a torneios e oficinas nesta versão.</small>
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

        <label class="event-registration-option">
            <input
                type="checkbox"
                name="inscricoes"
                value="1"
                <?= !isset($old['inscricoes']) || $old['inscricoes'] === '1' ? 'checked' : '' ?>
            >
            <span>
                <strong>Permitir inscrições de participantes</strong>
                <small>Indica se o evento aceitará inscrições pelo sistema.</small>
            </span>
        </label>

        <div class="event-form-actions">
            <a class="button button-secondary" href="/eventos">Cancelar</a>

            <button class="button button-primary" type="submit">
                <?= e($textoBotao ?? 'Criar evento') ?>
                <span aria-hidden="true">→</span>
            </button>
        </div>
    </form>
</section>
