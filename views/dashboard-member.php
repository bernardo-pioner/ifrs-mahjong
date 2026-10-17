<?php
/** @var array $user */
/** @var array{events:int,matches:int,progress:int} $stats */
?>
<section class="page-head">
    <h1>Olá, <?= e(userFirstName($user)) ?></h1>
    <p class="lede">Acompanhe torneios, eventos e o seu progresso no clube.</p>
</section>

<div class="cards">
    <a class="card" href="/torneios">
        <span class="card-kicker">Competição</span>
        <h2>Torneios</h2>
        <p>Veja as competições oficiais e acompanhe as próximas mesas.</p>
    </a>
    <a class="card" href="/eventos">
        <span class="card-kicker">Agenda</span>
        <strong><?= (int) $stats['events'] ?></strong>
        <h2>Eventos</h2>
        <p>Encontros, oficinas e atividades abertas aos participantes.</p>
    </a>
    <a class="card" href="/partidas">
        <span class="card-kicker">Mesas</span>
        <strong><?= (int) $stats['matches'] ?></strong>
        <h2>Partidas</h2>
        <p>Resultados recentes e partidas em que você pode acompanhar o placar.</p>
    </a>
    <a class="card" href="/rankings">
        <span class="card-kicker">Progresso</span>
        <strong><?= (int) $stats['progress'] ?></strong>
        <h2>Rankings</h2>
        <p>Classificação dos jogadores e a sua evolução no clube.</p>
    </a>
</div>
