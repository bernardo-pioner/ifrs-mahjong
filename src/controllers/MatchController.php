<?php

declare(strict_types=1);

final class MatchController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();

        $this->view('matches/index', [
            'title' => 'Partidas',
            'user' => $user,
            'currentPath' => '/partidas',
            'matches' => GameMatch::forPlayer((int) $user['id']),
        ]);
    }

    public function show(string $id): void
    {
        $user = $this->requireAuth();
        $match = GameMatch::find((int) $id);
        if ($match === null) {
            http_response_code(404);
            echo 'Partida não encontrada.';
            return;
        }

        $this->view('matches/show', [
            'title' => 'Partida',
            'user' => $user,
            'currentPath' => '/partidas',
            'match' => $match,
        ]);
    }

    public function create(string $eventoId): void
    {
        $user = $this->requireAuth();
        $evento = $this->casualEvent($eventoId);
        $choices = GameMatch::playerChoices();

        $this->view('matches/create', [
            'title' => 'Registrar partida',
            'user' => $user,
            'currentPath' => '/eventos',
            'evento' => $evento,
            'choices' => $choices,
            'errors' => [],
            'old' => [
                'modalidade' => $this->suggestedFormat($evento),
                'seats' => $this->blankSeats((int) $user['id']),
            ],
            'selfListed' => $this->listsUser($choices, (int) $user['id']),
        ]);
    }

    public function store(string $eventoId): void
    {
        verifyCsrf();
        $user = $this->requireAuth();
        $evento = $this->casualEvent($eventoId);
        $choices = GameMatch::playerChoices();
        $userId = (int) $user['id'];
        $formato = request('modalidade');
        $seats = $this->postedSeats();

        if (!$this->listsUser($choices, $userId)) {
            $this->view('matches/create', [
                'title' => 'Registrar partida',
                'user' => $user,
                'currentPath' => '/eventos',
                'evento' => $evento,
                'choices' => $choices,
                'errors' => ['Sua conta não está habilitada como participante.'],
                'old' => ['modalidade' => $formato, 'seats' => $seats],
                'selfListed' => false,
            ]);
            return;
        }

        $result = GameMatch::register(
            (int) $evento['id'],
            (string) $evento['tipo'],
            $userId,
            $formato,
            $seats
        );

        if ($result['errors'] !== []) {
            $this->view('matches/create', [
                'title' => 'Registrar partida',
                'user' => $user,
                'currentPath' => '/eventos',
                'evento' => $evento,
                'choices' => $choices,
                'errors' => $result['errors'],
                'old' => ['modalidade' => $formato, 'seats' => $seats],
                'selfListed' => true,
            ]);
            return;
        }

        flash('success', 'Partida registrada.');
        redirect('/partidas/' . $result['id']);
    }

    public function destroy(string $eventoId, string $partidaId): void
    {
        verifyCsrf();
        $user = $this->requireAuth();
        $evento = $this->casualEvent($eventoId);
        $deleted = GameMatch::deleteOwned((int) $partidaId, (int) $evento['id'], (int) $user['id']);

        if ($deleted) {
            flash('success', 'Partida excluída.');
        } else {
            flash('error', 'Você só pode excluir uma partida que você registrou.');
        }

        redirect('/eventos/' . (int) $evento['id'] . '?aba=partidas');
    }

    /** @return array<string, mixed> */
    private function casualEvent(string $eventoId): array
    {
        if (!ctype_digit($eventoId) || (int) $eventoId <= 0) {
            http_response_code(404);
            echo 'Evento não encontrado.';
            exit;
        }

        $evento = Event::findById((int) $eventoId);
        if ($evento === null || !in_array($evento['tipo'], ['encontro', 'oficina'], true)) {
            http_response_code(404);
            echo 'Evento não encontrado.';
            exit;
        }

        return $evento;
    }

    /** @param array<string, mixed> $evento */
    private function suggestedFormat(array $evento): string
    {
        $formato = (string) ($evento['modalidade'] ?? '');

        return isset(matchFormatOptions()[$formato]) ? $formato : '';
    }

    /** @return list<array{jogador: string, convidado: string, pontuacao: string}> */
    private function blankSeats(int $userId): array
    {
        $seats = [];
        for ($i = 0; $i < 4; $i++) {
            $seats[] = [
                'jogador' => $i === 0 ? (string) $userId : '',
                'convidado' => '',
                'pontuacao' => '',
            ];
        }

        return $seats;
    }

    /** @return list<array{jogador: string, convidado: string, pontuacao: string}> */
    private function postedSeats(): array
    {
        $posted = $_POST['jogadores'] ?? [];
        if (!is_array($posted)) {
            return $this->blankSeats(0);
        }

        $seats = [];
        foreach (array_values($posted) as $seat) {
            if (!is_array($seat)) {
                continue;
            }
            $seats[] = [
                'jogador' => trim((string) ($seat['jogador'] ?? '')),
                'convidado' => trim((string) ($seat['convidado'] ?? '')),
                'pontuacao' => trim((string) ($seat['pontuacao'] ?? '')),
            ];
        }

        while (count($seats) < 4) {
            $seats[] = ['jogador' => '', 'convidado' => '', 'pontuacao' => ''];
        }

        return array_slice($seats, 0, 4);
    }

    /** @param list<array{id: int, username: string, name: string}> $choices */
    private function listsUser(array $choices, int $userId): bool
    {
        foreach ($choices as $choice) {
            if ($choice['id'] === $userId) {
                return true;
            }
        }

        return false;
    }
}
