<?php

$eventoId = (int) $evento['id'];

$tituloPagina = 'Editar evento';
$descricaoPagina = 'Atualize as informações do evento do clube.';
$acaoFormulario = '/eventos/' . $eventoId . '/editar';
$textoBotao = 'Salvar alterações';
$perguntarRecorrenciasFuturas = !empty($evento['serie_id'])
    && Event::hasSeriesChoice(
        (int) $evento['serie_id'],
        isset($evento['data_inicio']) ? (string) $evento['data_inicio'] : null,
        (int) $evento['id']
    );

require __DIR__ . '/create.php';