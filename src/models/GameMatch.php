<?php

declare(strict_types=1);

final class GameMatch
{
    public static function count(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM partidas')->fetchColumn();
    }

    /** @return list<array{id: int, username: string, name: string}> */
    public static function playerChoices(): array
    {
        $rows = db()->query(
            'SELECT u.id, u.apelido AS username, u.nome AS name
             FROM usuarios u
             INNER JOIN participantes p ON p.usuario_id = u.id
             WHERE u.ativo = 1
             ORDER BY u.apelido COLLATE NOCASE'
        )->fetchAll();

        $choices = [];
        foreach ($rows as $row) {
            $choices[] = [
                'id' => (int) $row['id'],
                'username' => (string) $row['username'],
                'name' => trim((string) ($row['name'] ?? '')),
            ];
        }

        return $choices;
    }

    /** @return list<array<string, mixed>> */
    public static function forEvent(int $eventoId): array
    {
        $stmt = db()->prepare(
            'SELECT id, formato, modalidade, criada_em, registrado_por
             FROM partidas
             WHERE evento_id = :evento_id
             ORDER BY id DESC'
        );
        $stmt->execute([':evento_id' => $eventoId]);

        return self::withPlayers($stmt->fetchAll());
    }

    /** @return list<array<string, mixed>> */
    public static function forPlayer(int $usuarioId): array
    {
        $stmt = db()->prepare(
            'SELECT p.id, p.formato, p.modalidade, p.criada_em, p.evento_id,
                    e.titulo AS evento, e.tipo AS evento_tipo, pj.validacao
             FROM partidas p
             INNER JOIN partida_jogadores pj
                ON pj.partida_id = p.id
               AND pj.participante_id = :usuario_id
             LEFT JOIN eventos e ON e.id = p.evento_id
             ORDER BY CASE WHEN pj.validacao = \'pendente\' THEN 0 ELSE 1 END, p.id DESC'
        );
        $stmt->execute([':usuario_id' => $usuarioId]);

        return self::withPlayers($stmt->fetchAll());
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT p.id, p.formato, p.modalidade, p.criada_em, p.evento_id,
                    e.titulo AS evento, e.tipo AS evento_tipo
             FROM partidas p
             LEFT JOIN eventos e ON e.id = p.evento_id
             WHERE p.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $matches = self::withPlayers([$row]);

        return $matches[0] ?? null;
    }

    /**
     * @param list<array{jogador: string, convidado: string, pontuacao: string}> $seats
     * @return array{
     *   errors: list<string>,
     *   formato: string,
     *   jogadores: list<array<string, mixed>>,
     *   prepared: list<array{participante_id: ?int, nome_convidado: ?string, pontuacao: int}>
     * }
     */
    public static function preview(int $userId, string $formato, array $seats): array
    {
        $built = self::buildRows($userId, $formato, $seats);
        $profiles = self::playerProfiles(array_values(array_filter(
            array_column($built['prepared'], 'participante_id')
        )));
        $ranks = $built['errors'] === []
            ? self::ranks(array_column($built['prepared'], 'pontuacao'))
            : [];
        $jogadores = [];

        foreach ($built['prepared'] as $index => $row) {
            $playerId = $row['participante_id'];
            $profile = $playerId !== null ? ($profiles[$playerId] ?? null) : null;
            $jogadores[] = [
                'nome' => $row['nome_convidado'] !== null
                    ? $row['nome_convidado']
                    : (string) ($profile['username'] ?? 'Participante'),
                'convidado' => $row['eh_convidado'],
                'pontuacao' => $row['pontuacao_valida'] ? (int) $row['pontuacao'] : null,
                'colocacao' => $ranks[$index] ?? null,
                'photo_url' => (string) ($profile['photo_url'] ?? ''),
            ];
        }

        if ($ranks !== []) {
            usort(
                $jogadores,
                static fn (array $a, array $b): int => ((int) $a['colocacao']) <=> ((int) $b['colocacao'])
            );
        }

        return [
            'errors' => $built['errors'],
            'formato' => $formato,
            'jogadores' => $jogadores,
            'prepared' => $built['errors'] === [] ? $built['prepared'] : [],
        ];
    }

    /**
     * @param list<array{jogador: string, convidado: string, pontuacao: string}> $seats
     * @return array{errors: list<string>, id: int}
     */
    public static function register(int $eventoId, string $tipo, int $userId, string $formato, array $seats): array
    {
        $preview = self::preview($userId, $formato, $seats);
        if ($preview['errors'] !== []) {
            return ['errors' => $preview['errors'], 'id' => 0];
        }

        $prepared = $preview['prepared'];
        $ranks = self::ranks(array_column($prepared, 'pontuacao'));
        $now = date('Y-m-d H:i:s');
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO partidas
                    (modalidade, tipo, status, evento_id, formato, registrado_por, criada_em, atualizada_em)
                 VALUES
                    (\'riichi\', \'casual\', \'encerrada\', :evento_id, :formato, :registrado_por, :agora, :agora)'
            );
            $stmt->execute([
                ':evento_id' => $eventoId,
                ':formato' => $formato,
                ':registrado_por' => $userId,
                ':agora' => $now,
            ]);
            $partidaId = (int) $pdo->lastInsertId();

            if ($tipo === 'encontro') {
                $link = $pdo->prepare(
                    'INSERT INTO partidas_casuais (partida_id, encontro_id)
                     VALUES (:partida_id, :encontro_id)'
                );
                $link->execute([
                    ':partida_id' => $partidaId,
                    ':encontro_id' => $eventoId,
                ]);
            }

            $player = $pdo->prepare(
                'INSERT INTO partida_jogadores
                    (partida_id, participante_id, nome_convidado, colocacao, pontuacao, validacao)
                 VALUES
                    (:partida_id, :participante_id, :nome_convidado, :colocacao, :pontuacao, :validacao)'
            );
            $selecionados = [];
            foreach ($prepared as $index => $row) {
                $participanteId = $row['participante_id'];
                $player->execute([
                    ':partida_id' => $partidaId,
                    ':participante_id' => $participanteId,
                    ':nome_convidado' => $row['nome_convidado'],
                    ':colocacao' => $ranks[$index],
                    ':pontuacao' => $row['pontuacao'],
                    ':validacao' => $participanteId !== null ? 'pendente' : 'confirmada',
                ]);
                if ($participanteId !== null) {
                    $selecionados[] = (int) $participanteId;
                }
            }

            $tituloStmt = $pdo->prepare('SELECT titulo FROM eventos WHERE id = :id');
            $tituloStmt->execute([':id' => $eventoId]);
            $titulo = (string) ($tituloStmt->fetchColumn() ?: '');

            $pdo->commit();

            Notification::notifyMatchValidation(
                $partidaId,
                $selecionados,
                $titulo,
                $now
            );
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return ['errors' => [], 'id' => $partidaId];
    }

    public static function deleteOwned(int $partidaId, int $eventoId, int $userId): bool
    {
        $stmt = db()->prepare(
            'SELECT id
             FROM partidas
             WHERE id = :id
               AND evento_id = :evento_id
               AND registrado_por = :usuario_id'
        );
        $stmt->execute([
            ':id' => $partidaId,
            ':evento_id' => $eventoId,
            ':usuario_id' => $userId,
        ]);
        if (!$stmt->fetch()) {
            return false;
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $clear = $pdo->prepare(
                'DELETE FROM notificacoes
                 WHERE chave = :validar OR chave = :partida'
            );
            $clear->execute([
                ':validar' => 'validar-partida:' . $partidaId,
                ':partida' => 'partida:' . $partidaId,
            ]);

            $delete = $pdo->prepare('DELETE FROM partidas WHERE id = :id');
            $delete->execute([':id' => $partidaId]);
            $removed = $delete->rowCount() > 0;
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return $removed;
    }

    public static function confirmParticipation(int $partidaId, int $userId): bool
    {
        $stmt = db()->prepare(
            "UPDATE partida_jogadores
             SET validacao = 'confirmada'
             WHERE partida_id = :partida_id
               AND participante_id = :usuario_id
               AND validacao = 'pendente'"
        );
        $stmt->execute([
            ':partida_id' => $partidaId,
            ':usuario_id' => $userId,
        ]);
        if ($stmt->rowCount() < 1) {
            return false;
        }

        Notification::dismiss(['id' => $userId], 'validar-partida:' . $partidaId);

        return true;
    }

    /** Aceita zero, negativos e zeros à esquerda ("001" vale 1). */
    public static function canonicalScore(string $value): string
    {
        $value = trim($value);
        $score = self::parseScore($value);

        return $score === null ? $value : (string) $score;
    }

    private static function parseScore(string $value): ?int
    {
        if (preg_match('/^[+-]?\d+$/', $value) !== 1) {
            return null;
        }

        $negative = str_starts_with($value, '-');
        $digits = ltrim($value, '+-');
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }

        $number = (int) ($negative ? '-' . $digits : $digits);
        if ((string) abs($number) !== $digits) {
            return null;
        }

        return $number;
    }

    /**
     * @param list<array{jogador: string, convidado: string, pontuacao: string}> $seats
     * @return array{
     *   errors: list<string>,
     *   prepared: list<array{participante_id: ?int, nome_convidado: ?string, pontuacao: int, pontuacao_valida: bool}>
     * }
     */
    private static function buildRows(int $userId, string $formato, array $seats): array
    {
        $formats = matchFormatOptions();
        $errors = [];
        $prepared = [];
        if (!isset($formats[$formato])) {
            return [
                'errors' => ['Selecione a modalidade da mesa.'],
                'prepared' => [],
            ];
        }

        $count = matchFormatSeatCount($formato);
        $seats = array_slice(array_values($seats), 0, $count);
        if (count($seats) < $count) {
            $errors[] = 'Informe todos os jogadores da mesa.';
        }

        $choices = [];
        foreach (self::playerChoices() as $choice) {
            $choices[$choice['id']] = $choice;
        }

        $used = [];
        $includesSelf = false;

        foreach ($seats as $index => $seat) {
            $label = 'Jogador ' . ($index + 1);
            $jogador = trim((string) ($seat['jogador'] ?? ''));
            $convidado = trim((string) ($seat['convidado'] ?? ''));
            $pontuacao = trim((string) ($seat['pontuacao'] ?? ''));
            $score = self::parseScore($pontuacao);
            $valida = $score !== null;

            if (!$valida) {
                $errors[] = 'Informe a pontuação do ' . $label . ' com um número inteiro.';
            }

            if ($jogador === 'convidado') {
                if ($convidado === '') {
                    $errors[] = 'Informe o nome do convidado no ' . $label . '.';
                    $convidado = 'Convidado';
                } elseif (mb_strlen($convidado) > 40) {
                    $errors[] = 'O nome do convidado no ' . $label . ' deve ter no máximo 40 caracteres.';
                }
                $prepared[] = [
                    'participante_id' => null,
                    'nome_convidado' => $convidado,
                    'eh_convidado' => true,
                    'pontuacao' => $valida ? (int) $score : 0,
                    'pontuacao_valida' => $valida,
                ];
                continue;
            }

            if ($jogador === '' || !ctype_digit($jogador)) {
                $errors[] = 'Selecione o ' . $label . '.';
                $prepared[] = [
                    'participante_id' => null,
                    'nome_convidado' => 'Não selecionado',
                    'eh_convidado' => false,
                    'pontuacao' => $valida ? (int) $score : 0,
                    'pontuacao_valida' => $valida,
                ];
                continue;
            }

            $playerId = (int) $jogador;
            if (!isset($choices[$playerId])) {
                $errors[] = 'O ' . $label . ' não está cadastrado.';
                $prepared[] = [
                    'participante_id' => null,
                    'nome_convidado' => 'Não cadastrado',
                    'eh_convidado' => false,
                    'pontuacao' => $valida ? (int) $score : 0,
                    'pontuacao_valida' => $valida,
                ];
                continue;
            }
            if (isset($used[$playerId])) {
                $errors[] = 'O mesmo jogador não pode ocupar dois lugares.';
            }

            $used[$playerId] = true;
            if ($playerId === $userId) {
                $includesSelf = true;
            }
            $prepared[] = [
                'participante_id' => $playerId,
                'nome_convidado' => null,
                'eh_convidado' => false,
                'pontuacao' => $valida ? (int) $score : 0,
                'pontuacao_valida' => $valida,
            ];
        }

        if ($errors === [] && !$includesSelf) {
            $errors[] = 'Inclua você mesmo entre os jogadores da mesa.';
        }

        return ['errors' => $errors, 'prepared' => $prepared];
    }

    /**
     * @param list<int> $ids
     * @return array<int, array{username: string, photo_url: string}>
     */
    private static function playerProfiles(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            $ids,
            static fn (mixed $id): bool => is_int($id) && $id > 0
        )));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT u.id, u.apelido AS username, p.foto AS photo
             FROM usuarios u
             LEFT JOIN participantes p ON p.usuario_id = u.id
             WHERE u.id IN ($placeholders)"
        );
        $stmt->execute($ids);

        $profiles = [];
        foreach ($stmt->fetchAll() as $row) {
            $profiles[(int) $row['id']] = [
                'username' => (string) $row['username'],
                'photo_url' => (string) (userPhotoUrl($row) ?? ''),
            ];
        }

        return $profiles;
    }

    /**
     * @param list<int> $scores
     * @return list<int>
     */
    private static function ranks(array $scores): array
    {
        $order = $scores;
        arsort($order);
        $ranks = [];
        $seen = 0;
        $place = 0;
        $previous = null;

        foreach ($order as $index => $score) {
            $seen++;
            if ($previous === null || $score !== $previous) {
                $place = $seen;
                $previous = $score;
            }
            $ranks[(int) $index] = $place;
        }

        ksort($ranks);

        return array_values($ranks);
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @return list<array<string, mixed>>
     */
    private static function withPlayers(array $matches): array
    {
        if ($matches === []) {
            return [];
        }

        $ids = array_map(static fn (array $match): int => (int) $match['id'], $matches);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT pj.partida_id, pj.participante_id, pj.nome_convidado,
                    pj.pontuacao, pj.colocacao,
                    u.apelido AS username, u.nome AS real_name, u.criado_em AS created_at,
                    p.apelido AS nickname, p.bio, p.mahjong_soul, p.riichi_city,
                    p.discord, p.foto AS photo, p.nivel AS level
             FROM partida_jogadores pj
             LEFT JOIN usuarios u ON u.id = pj.participante_id
             LEFT JOIN participantes p ON p.usuario_id = pj.participante_id
             WHERE pj.partida_id IN ($placeholders)
             ORDER BY pj.partida_id ASC, pj.pontuacao DESC, pj.colocacao ASC, pj.id ASC"
        );
        $stmt->execute($ids);

        $levels = levelLabels();
        $byMatch = [];
        foreach ($stmt->fetchAll() as $row) {
            $guest = $row['participante_id'] === null;
            $username = trim((string) ($row['username'] ?? ''));
            $nickname = trim((string) ($row['nickname'] ?? ''));
            if ($nickname === '') {
                $nickname = $username !== '' ? $username : 'Participante';
            }
            $level = (string) ($row['level'] ?? '');
            $byMatch[(int) $row['partida_id']][] = [
                'usuario_id' => $guest ? null : (int) $row['participante_id'],
                'nome' => $guest ? (string) $row['nome_convidado'] : ($username !== '' ? $username : 'Participante'),
                'convidado' => $guest,
                'pontuacao' => $row['pontuacao'] === null ? null : (int) $row['pontuacao'],
                'colocacao' => $row['colocacao'] === null ? null : (int) $row['colocacao'],
                'username' => $username,
                'nickname' => $nickname,
                'real_name' => trim((string) ($row['real_name'] ?? '')),
                'bio' => trim((string) ($row['bio'] ?? '')),
                'mahjong_soul' => trim((string) ($row['mahjong_soul'] ?? '')),
                'riichi_city' => trim((string) ($row['riichi_city'] ?? '')),
                'discord' => trim((string) ($row['discord'] ?? '')),
                'photo_url' => $guest ? '' : (string) (userPhotoUrl($row) ?? ''),
                'member_since' => $guest ? '' : formatMemberSince(isset($row['created_at']) ? (string) $row['created_at'] : null),
                'level_label' => $guest ? '' : ($levels[$level] ?? ''),
            ];
        }

        foreach ($matches as &$match) {
            $match['jogadores'] = $byMatch[(int) $match['id']] ?? [];
        }
        unset($match);

        return $matches;
    }
}
