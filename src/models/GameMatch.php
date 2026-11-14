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
                    e.titulo AS evento, e.tipo AS evento_tipo
             FROM partidas p
             INNER JOIN partida_jogadores pj
                ON pj.partida_id = p.id
               AND pj.participante_id = :usuario_id
             LEFT JOIN eventos e ON e.id = p.evento_id
             ORDER BY p.id DESC'
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
     * @return array{errors: list<string>, id: int}
     */
    public static function register(int $eventoId, string $tipo, int $userId, string $formato, array $seats): array
    {
        $formats = matchFormatOptions();
        $errors = [];
        if (!isset($formats[$formato])) {
            $errors[] = 'Selecione a modalidade da mesa.';
            return ['errors' => $errors, 'id' => 0];
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
        $prepared = [];

        foreach ($seats as $index => $seat) {
            $label = 'Jogador ' . ($index + 1);
            $jogador = trim((string) ($seat['jogador'] ?? ''));
            $convidado = trim((string) ($seat['convidado'] ?? ''));
            $pontuacao = trim((string) ($seat['pontuacao'] ?? ''));
            $score = filter_var($pontuacao, FILTER_VALIDATE_INT);

            if ($score === false) {
                $errors[] = 'Informe a pontuação do ' . $label . ' com um número inteiro.';
            }

            if ($jogador === 'convidado') {
                if ($convidado === '') {
                    $errors[] = 'Informe o nome do convidado no ' . $label . '.';
                } elseif (mb_strlen($convidado) > 40) {
                    $errors[] = 'O nome do convidado no ' . $label . ' deve ter no máximo 40 caracteres.';
                }
                $prepared[] = [
                    'participante_id' => null,
                    'nome_convidado' => $convidado,
                    'pontuacao' => $score === false ? 0 : (int) $score,
                ];
                continue;
            }

            if ($jogador === '' || !ctype_digit($jogador)) {
                $errors[] = 'Selecione o ' . $label . '.';
                continue;
            }

            $playerId = (int) $jogador;
            if (!isset($choices[$playerId])) {
                $errors[] = 'O ' . $label . ' não está cadastrado.';
                continue;
            }
            if (isset($used[$playerId])) {
                $errors[] = 'O mesmo jogador não pode ocupar dois lugares.';
                continue;
            }

            $used[$playerId] = true;
            if ($playerId === $userId) {
                $includesSelf = true;
            }
            $prepared[] = [
                'participante_id' => $playerId,
                'nome_convidado' => null,
                'pontuacao' => $score === false ? 0 : (int) $score,
            ];
        }

        if ($errors === [] && !$includesSelf) {
            $errors[] = 'Inclua você mesmo entre os jogadores da mesa.';
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'id' => 0];
        }

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
                    (partida_id, participante_id, nome_convidado, colocacao, pontuacao)
                 VALUES
                    (:partida_id, :participante_id, :nome_convidado, :colocacao, :pontuacao)'
            );
            foreach ($prepared as $index => $row) {
                $player->execute([
                    ':partida_id' => $partidaId,
                    ':participante_id' => $row['participante_id'],
                    ':nome_convidado' => $row['nome_convidado'],
                    ':colocacao' => $ranks[$index],
                    ':pontuacao' => $row['pontuacao'],
                ]);
            }

            $pdo->commit();
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

        $delete = db()->prepare('DELETE FROM partidas WHERE id = :id');
        $delete->execute([':id' => $partidaId]);

        return $delete->rowCount() > 0;
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
                    pj.pontuacao, pj.colocacao, u.apelido AS username
             FROM partida_jogadores pj
             LEFT JOIN usuarios u ON u.id = pj.participante_id
             WHERE pj.partida_id IN ($placeholders)
             ORDER BY pj.partida_id ASC, pj.pontuacao DESC, pj.colocacao ASC, pj.id ASC"
        );
        $stmt->execute($ids);

        $byMatch = [];
        foreach ($stmt->fetchAll() as $row) {
            $guest = $row['participante_id'] === null;
            $byMatch[(int) $row['partida_id']][] = [
                'usuario_id' => $guest ? null : (int) $row['participante_id'],
                'nome' => $guest ? (string) $row['nome_convidado'] : (string) ($row['username'] ?? 'Participante'),
                'convidado' => $guest,
                'pontuacao' => $row['pontuacao'] === null ? null : (int) $row['pontuacao'],
                'colocacao' => $row['colocacao'] === null ? null : (int) $row['colocacao'],
            ];
        }

        foreach ($matches as &$match) {
            $match['jogadores'] = $byMatch[(int) $match['id']] ?? [];
        }
        unset($match);

        return $matches;
    }
}
