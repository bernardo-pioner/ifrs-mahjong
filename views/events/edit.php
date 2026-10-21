<?php

$eventoId = (int) $evento['id'];

$tituloPagina = 'Editar evento';
$descricaoPagina = 'Atualize as informações do evento do clube.';
$acaoFormulario = '/eventos/' . $eventoId . '/editar';
$textoBotao = 'Salvar alterações';

require __DIR__ . '/create.php';