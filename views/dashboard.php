<?php
/** @var array $user */
/** @var array{members:int,events:int,matches:int,progress:int} $stats */
?>
<section class="page-head">
    <h1>Dashboard</h1>
    <p class="lede">Olá, <?= e(explode(' ', $user['name'])[0]) ?>. Este é o ponto de partida da gestão do clube.</p>
</section>

<div class="cards">
    <a class="card" href="/eventos">
        <span class="card-kicker">Agenda</span>
        <strong><?= (int) $stats['events'] ?></strong>
        <h2>Eventos</h2>
        <p>Encontros, workshops e demais atividades do clube.</p>
    </a>
    <a class="card" href="/partidas">
        <span class="card-kicker">Mesas</span>
        <strong><?= (int) $stats['matches'] ?></strong>
        <h2>Partidas</h2>
        <p>Resultados de mesas, com ou sem vínculo a um evento.</p>
    </a>
    <a class="card" href="/participantes">
        <span class="card-kicker">Pessoas</span>
        <strong><?= (int) $stats['members'] ?></strong>
        <h2>Participantes</h2>
        <p>Quem faz parte do clube e das atividades.</p>
    </a>
    <a class="card" href="/rankings">
        <span class="card-kicker">Acompanhamento</span>
        <strong><?= (int) $stats['progress'] ?></strong>
        <h2>Rankings</h2>
        <p>Evolução e classificação dos jogadores.</p>
    </a>
</div>
