<?php

declare(strict_types=1);

final class MatchController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();

        $stmt = db()->query("
            SELECT
                p.id,
                p.modalidade,
                p.tipo,
                p.status,
                p.mesa,
                e.titulo AS encontro,
                pc.duracao_minutos
            FROM partidas p
            LEFT JOIN partidas_casuais pc
                ON pc.partida_id = p.id
            LEFT JOIN encontros en
                ON en.evento_id = pc.encontro_id
            LEFT JOIN eventos e
                ON e.id = en.evento_id
            WHERE p.tipo = 'casual'
            ORDER BY p.id DESC
        ");

        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->view('matches/index', [
            'title' => 'Partidas',
            'user' => $user,
            'currentPath' => '/partidas',
            'matches' => $matches,
        ]);
    }
}
