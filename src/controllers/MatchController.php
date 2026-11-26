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
        $this->denyPendingWorkshop($evento, $user);
        $choices = GameMatch::playerChoices();
        $draft = $this->draftFor((int) $evento['id']);
        $old = [
            'modalidade' => $this->suggestedFormat($evento),
            'seats' => $this->blankSeats((int) $user['id']),
        ];
        if (request('editar') === '1' && $draft !== null) {
            $old = [
                'modalidade' => $draft['formato'],
                'seats' => $draft['seats'],
            ];
        }

        $this->view('matches/create', [
            'title' => 'Registrar partida',
            'user' => $user,
            'currentPath' => '/eventos',
            'evento' => $evento,
            'choices' => $choices,
            'errors' => [],
            'old' => $old,
            'selfListed' => $this->listsUser($choices, (int) $user['id']),
        ]);
    }

    public function store(string $eventoId): void
    {
        verifyCsrf();
        $user = $this->requireAuth();
        $evento = $this->casualEvent($eventoId);
        $this->denyPendingWorkshop($evento, $user);
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

        $_SESSION['match_draft'] = [
            'evento_id' => (int) $evento['id'],
            'formato' => $formato,
            'seats' => $seats,
        ];
        redirect('/eventos/' . (int) $evento['id'] . '/partidas/revisao');
    }

    public function review(string $eventoId): void
    {
        $user = $this->requireAuth();
        $evento = $this->casualEvent($eventoId);
        $this->denyPendingWorkshop($evento, $user);
        $draft = $this->draftFor((int) $evento['id']);
        if ($draft === null) {
            redirect('/eventos/' . (int) $evento['id'] . '/partidas/nova');
        }

        $preview = GameMatch::preview((int) $user['id'], $draft['formato'], $draft['seats']);

        $this->view('matches/review', [
            'title' => 'Revisar partida',
            'user' => $user,
            'currentPath' => '/eventos',
            'evento' => $evento,
            'preview' => $preview,
        ]);
    }

    public function confirm(string $eventoId): void
    {
        verifyCsrf();
        $user = $this->requireAuth();
        $evento = $this->casualEvent($eventoId);
        $this->denyPendingWorkshop($evento, $user);
        $draft = $this->draftFor((int) $evento['id']);
        if ($draft === null) {
            flash('error', 'Não há uma partida para salvar.');
            redirect('/eventos/' . (int) $evento['id'] . '/partidas/nova');
        }

        $result = GameMatch::register(
            (int) $evento['id'],
            (string) $evento['tipo'],
            (int) $user['id'],
            $draft['formato'],
            $draft['seats']
        );
        if ($result['errors'] !== []) {
            redirect('/eventos/' . (int) $evento['id'] . '/partidas/revisao');
        }

        unset($_SESSION['match_draft']);
        flash('success', 'Partida registrada.');
        redirect('/eventos/' . (int) $evento['id'] . '?aba=partidas');
    }

    public function validate(string $id): void
    {
        verifyCsrf();
        $user = $this->requireAuth();
        if (!ctype_digit($id) || (int) $id <= 0) {
            flash('error', 'Partida não encontrada.');
            redirect('/partidas');
        }

        $confirmed = GameMatch::confirmParticipation((int) $id, (int) $user['id']);
        if ($confirmed) {
            flash('success', 'Participação confirmada.');
        } else {
            flash('error', 'Não foi possível validar esta partida.');
        }

        redirect('/partidas');
    }

    public function discard(string $eventoId): void
    {
        verifyCsrf();
        $this->requireAuth();
        $evento = $this->casualEvent($eventoId);
        unset($_SESSION['match_draft']);
        redirect('/eventos/' . (int) $evento['id']);
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

    /**
     * Jogador com inscrição pendente em oficina não registra partidas.
     *
     * @param array<string, mixed> $evento
     * @param array<string, mixed> $user
     */
    private function denyPendingWorkshop(array $evento, array $user): void
    {
        if (($evento['tipo'] ?? '') !== 'oficina') {
            return;
        }

        $participation = Event::findParticipation((int) $evento['id'], (int) $user['id']);
        if (($participation['status'] ?? '') !== 'pendente') {
            return;
        }

        $draft = $_SESSION['match_draft'] ?? null;
        if (is_array($draft) && (int) ($draft['evento_id'] ?? 0) === (int) $evento['id']) {
            unset($_SESSION['match_draft']);
        }

        flash('error', 'Não é possível registrar partidas enquanto sua inscrição na oficina estiver pendente.');
        redirect('/eventos/' . (int) $evento['id'] . '?aba=partidas');
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
                'pontuacao' => GameMatch::canonicalScore((string) ($seat['pontuacao'] ?? '')),
            ];
        }

        while (count($seats) < 4) {
            $seats[] = ['jogador' => '', 'convidado' => '', 'pontuacao' => ''];
        }

        return array_slice($seats, 0, 4);
    }

    /** @return array{formato: string, seats: list<array{jogador: string, convidado: string, pontuacao: string}>}|null */
    private function draftFor(int $eventoId): ?array
    {
        $draft = $_SESSION['match_draft'] ?? null;
        if (!is_array($draft) || (int) ($draft['evento_id'] ?? 0) !== $eventoId) {
            return null;
        }

        $formato = (string) ($draft['formato'] ?? '');
        $seats = $draft['seats'] ?? null;
        if (!is_array($seats)) {
            return null;
        }

        return [
            'formato' => $formato,
            'seats' => array_values($seats),
        ];
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
