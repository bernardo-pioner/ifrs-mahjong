<?php

declare(strict_types=1);

final class Event
{
    public static function count(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM eventos')->fetchColumn();
    }

    public static function countFutureInSeries(?int $serieId, ?string $dataInicio): int
    {
        return count(self::futureInSeries($serieId, $dataInicio));
    }

    /** @return list<array<string, mixed>> */
    public static function futureInSeries(?int $serieId, ?string $dataInicio): array
    {
        if ($serieId === null || $serieId <= 0 || $dataInicio === null || trim($dataInicio) === '') {
            return [];
        }

        $stmt = db()->prepare(
            'SELECT *
             FROM eventos
             WHERE serie_id = :serie
               AND data_inicio > :inicio
             ORDER BY data_inicio ASC, id ASC'
        );
        $stmt->execute([
            ':serie' => $serieId,
            ':inicio' => $dataInicio,
        ]);

        return $stmt->fetchAll();
    }

    public static function isEmAndamento(array $evento): bool
    {
        $data = trim((string) ($evento['data_fim'] ?? ''));
        if ($data === '') {
            $data = trim((string) ($evento['data_inicio'] ?? ''));
        }

        if ($data === '' || !preg_match('/^(\d{4}-\d{2}-\d{2})/', $data, $dia)) {
            return true;
        }

        $ontem = (new DateTimeImmutable('today'))->modify('-1 day')->format('Y-m-d');

        return $dia[1] > $ontem;
    }

    /** @return list<array<string, mixed>> */
    public static function activeInSeries(?int $serieId): array
    {
        if ($serieId === null || $serieId <= 0) {
            return [];
        }

        $referencia = "COALESCE(NULLIF(data_fim, ''), NULLIF(data_inicio, ''))";
        $stmt = db()->prepare(
            "SELECT *
             FROM eventos
             WHERE serie_id = :serie
               AND ({$referencia} IS NULL OR date({$referencia}) > date('now', 'localtime', '-1 day'))
             ORDER BY data_inicio ASC, id ASC"
        );
        $stmt->execute([':serie' => $serieId]);

        return $stmt->fetchAll();
    }

    public static function hasSeriesChoice(?int $serieId, ?string $dataInicio, int $eventoId): bool
    {
        if (self::countFutureInSeries($serieId, $dataInicio) > 0) {
            return true;
        }

        foreach (self::activeInSeries($serieId) as $ocorrencia) {
            if ((int) $ocorrencia['id'] !== $eventoId) {
                return true;
            }
        }

        return false;
    }

    public static function hasLinkedRecords(int $eventoId): bool
    {
        $stmt = db()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM partidas_competitivas WHERE torneio_id = :torneio_partidas)
                +
                (SELECT COUNT(*) FROM partidas_casuais WHERE encontro_id = :encontro_partidas)
                +
                (SELECT COUNT(*) FROM rankings_competitivos WHERE torneio_id = :torneio_rankings)
                +
                (SELECT COUNT(*) FROM rankings_casuais WHERE encontro_id = :encontro_rankings)'
        );
        $stmt->execute([
            ':torneio_partidas' => $eventoId,
            ':encontro_partidas' => $eventoId,
            ':torneio_rankings' => $eventoId,
            ':encontro_rankings' => $eventoId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT e.*, u.nome AS organizador
             FROM eventos e
             LEFT JOIN usuarios u ON u.id = e.admin_id
             WHERE e.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $evento = $stmt->fetch();

        return $evento ?: null;
    }

    /** @return array{limite: ?int, taxa: float, premiacao: list<string>} */
    public static function typeDetails(array $evento): array
    {
        $id = (int) $evento['id'];
        $tipo = (string) $evento['tipo'];

        if ($tipo === 'torneio') {
            $stmt = db()->prepare(
                'SELECT maximo_participantes AS limite, taxa_participacao AS taxa, premiacao
                 FROM torneios WHERE evento_id = :id'
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch() ?: [];

            return [
                'limite' => isset($row['limite']) ? (int) $row['limite'] : null,
                'taxa' => (float) ($row['taxa'] ?? 0),
                'premiacao' => self::decodePremiacao($row['premiacao'] ?? null),
            ];
        }

        if ($tipo === 'oficina') {
            $stmt = db()->prepare(
                'SELECT vagas AS limite, taxa_inscricao AS taxa, premiacao
                 FROM oficinas WHERE evento_id = :id'
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch() ?: [];

            return [
                'limite' => isset($row['limite']) ? (int) $row['limite'] : null,
                'taxa' => (float) ($row['taxa'] ?? 0),
                'premiacao' => self::decodePremiacao($row['premiacao'] ?? null),
            ];
        }

        return ['limite' => null, 'taxa' => 0.0, 'premiacao' => []];
    }

    /** @return list<string> */
    public static function decodePremiacao(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        $itens = [];
        foreach ($decoded as $item) {
            if (!is_string($item)) {
                continue;
            }
            $texto = trim($item);
            if ($texto !== '') {
                $itens[] = $texto;
            }
        }

        return array_slice($itens, 0, 10);
    }

    /** @param list<string> $premiacao */
    public static function encodePremiacao(array $premiacao): ?string
    {
        $itens = [];
        foreach ($premiacao as $item) {
            $texto = trim((string) $item);
            if ($texto !== '') {
                $itens[] = $texto;
            }
        }

        if ($itens === []) {
            return null;
        }

        return json_encode(array_values(array_slice($itens, 0, 10)), JSON_UNESCAPED_UNICODE);
    }

    public static function countActiveParticipants(int $eventoId): int
    {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM evento_participantes
             WHERE evento_id = :id AND status = 'aprovado'"
        );
        $stmt->execute([':id' => $eventoId]);

        return (int) $stmt->fetchColumn();
    }

    public static function hasCapacity(array $evento): bool
    {
        $details = self::typeDetails($evento);
        if ($details['limite'] === null || $details['limite'] < 1) {
            return true;
        }

        return self::countActiveParticipants((int) $evento['id']) < $details['limite'];
    }

    public static function findParticipation(int $eventoId, int $participanteId): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM evento_participantes
             WHERE evento_id = :evento_id AND participante_id = :participante_id'
        );
        $stmt->execute([
            ':evento_id' => $eventoId,
            ':participante_id' => $participanteId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public static function listParticipants(int $eventoId, ?int $currentUserId = null): array
    {
        $stmt = db()->prepare(
            'SELECT
                u.id AS usuario_id,
                u.apelido AS username,
                u.nome,
                u.criado_em AS created_at,
                p.apelido,
                p.bio,
                p.mahjong_soul,
                p.riichi_city,
                p.discord,
                p.foto AS photo,
                p.nivel AS level,
                ep.status,
                ep.inscrito_em,
                pg.id AS pagamento_id,
                pg.valor AS pagamento_valor,
                pg.status AS pagamento_status,
                pg.comprovante
             FROM evento_participantes ep
             INNER JOIN participantes p ON p.usuario_id = ep.participante_id
             INNER JOIN usuarios u ON u.id = p.usuario_id
             LEFT JOIN pagamentos pg
                ON pg.evento_id = ep.evento_id
               AND pg.participante_id = ep.participante_id
             WHERE ep.evento_id = :evento_id
             ORDER BY
                CASE WHEN u.id = :current_user_id THEN 0 ELSE 1 END ASC,
                COALESCE(
                    NULLIF(TRIM(p.apelido), \'\'),
                    NULLIF(TRIM(u.nome), \'\'),
                    u.apelido
                ) COLLATE NOCASE ASC'
        );
        $stmt->execute([
            ':evento_id' => $eventoId,
            ':current_user_id' => $currentUserId ?? 0,
        ]);

        return $stmt->fetchAll();
    }

    public static function findPayment(int $eventoId, int $participanteId): ?array
    {
        $stmt = db()->prepare(
            'SELECT *
             FROM pagamentos
             WHERE evento_id = :evento_id
               AND participante_id = :participante_id
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            ':evento_id' => $eventoId,
            ':participante_id' => $participanteId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function resolveComprovantePath(?string $storedPath): ?string
    {
        if ($storedPath === null || trim($storedPath) === '') {
            return null;
        }

        $storedPath = str_replace('\\', '/', trim($storedPath));
        if (str_contains($storedPath, '..')) {
            return null;
        }

        $candidates = [];

        if (str_starts_with($storedPath, '/uploads/comprovantes/')) {
            $filename = basename($storedPath);
            $candidates[] = ROOT_PATH . '/storage/comprovantes/' . $filename;
            $candidates[] = ROOT_PATH . '/public/uploads/comprovantes/' . $filename;
        } elseif (str_starts_with($storedPath, 'storage/comprovantes/')) {
            $candidates[] = ROOT_PATH . '/' . $storedPath;
        } else {
            $filename = basename($storedPath);
            $candidates[] = ROOT_PATH . '/storage/comprovantes/' . $filename;
            $candidates[] = ROOT_PATH . '/public/uploads/comprovantes/' . $filename;
        }

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param array{
     *   admin_id: int,
     *   tipo: string,
     *   modalidade: string,
     *   titulo: string,
     *   descricao: ?string,
     *   data_inicio: string,
     *   data_fim: ?string,
     *   local: ?string,
     *   inscricoes_abertas: int,
     *   limite: ?int,
     *   taxa: float,
     *   premiacao: list<string>
     * } $data
     */
    public static function createOne(array $data): int
    {
        $pdo = db();

        $stmt = $pdo->prepare(
            'INSERT INTO eventos
                (admin_id, tipo, modalidade, titulo, descricao, data_inicio, data_fim, local, serie_id, inscricoes_abertas)
             VALUES
                (:admin_id, :tipo, :modalidade, :titulo, :descricao, :data_inicio, :data_fim, :local, :serie_id, :inscricoes_abertas)'
        );
        $stmt->execute([
            ':admin_id' => $data['admin_id'],
            ':tipo' => $data['tipo'],
            ':modalidade' => $data['modalidade'],
            ':titulo' => $data['titulo'],
            ':descricao' => $data['descricao'],
            ':data_inicio' => $data['data_inicio'],
            ':data_fim' => $data['data_fim'],
            ':local' => $data['local'],
            ':serie_id' => $data['serie_id'] ?? null,
            ':inscricoes_abertas' => $data['inscricoes_abertas'],
        ]);

        $eventoId = (int) $pdo->lastInsertId();
        self::insertTypeRow(
            $eventoId,
            $data['tipo'],
            $data['limite'],
            $data['taxa'],
            $data['inscricoes_abertas'],
            $data['premiacao'] ?? []
        );

        return $eventoId;
    }

    /** @param list<string> $premiacao */
    public static function insertTypeRow(
        int $eventoId,
        string $tipo,
        ?int $limite,
        float $taxa,
        int $inscricoesAbertas,
        array $premiacao = []
    ): void {
        $pdo = db();
        $premiacaoJson = self::encodePremiacao($premiacao);

        if ($tipo === 'torneio') {
            $stmt = $pdo->prepare(
                'INSERT INTO torneios (evento_id, maximo_participantes, taxa_participacao, premiacao)
                 VALUES (:evento_id, :limite, :taxa, :premiacao)'
            );
            $stmt->execute([
                ':evento_id' => $eventoId,
                ':limite' => $limite,
                ':taxa' => $taxa,
                ':premiacao' => $premiacaoJson,
            ]);
            return;
        }

        if ($tipo === 'oficina') {
            $stmt = $pdo->prepare(
                'INSERT INTO oficinas (evento_id, vagas, taxa_inscricao, premiacao)
                 VALUES (:evento_id, :limite, :taxa, :premiacao)'
            );
            $stmt->execute([
                ':evento_id' => $eventoId,
                ':limite' => $limite,
                ':taxa' => $taxa,
                ':premiacao' => $premiacaoJson,
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO encontros (evento_id, aberto_publico, observacoes)
             VALUES (:evento_id, 1, :observacoes)'
        );
        $stmt->execute([
            ':evento_id' => $eventoId,
            ':observacoes' => $inscricoesAbertas === 1
                ? 'Inscrições habilitadas'
                : 'Inscrições não habilitadas',
        ]);
    }

    /**
     * @param array{
     *   admin_id: int,
     *   tipo: string,
     *   modalidade: string,
     *   titulo: string,
     *   descricao: ?string,
     *   data_inicio: string,
     *   data_fim: ?string,
     *   local: ?string,
     *   inscricoes_abertas: int,
     *   limite: ?int,
     *   taxa: float,
     *   premiacao: list<string>
     * } $base
     * @return list<int>
     */
    public static function createWithRecurrence(
        array $base,
        int $intervaloDias,
        int $ocorrencias
    ): array {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $ids = [];
            $serieId = null;
            $inicio = new DateTimeImmutable($base['data_inicio']);
            $fim = $base['data_fim'] !== null
                ? new DateTimeImmutable($base['data_fim'])
                : null;
            $duracao = $fim !== null ? $inicio->diff($fim) : null;

            for ($i = 0; $i < $ocorrencias; $i++) {
                $offset = $intervaloDias * $i;
                $inicioAtual = $inicio->modify('+' . $offset . ' days');
                $fimAtual = null;
                if ($fim !== null && $duracao !== null) {
                    $fimAtual = $inicioAtual->add($duracao);
                }

                $payload = $base;
                $payload['serie_id'] = $serieId;
                $payload['data_inicio'] = $inicioAtual->format('Y-m-d H:i:s');
                $payload['data_fim'] = $fimAtual?->format('Y-m-d H:i:s');
                $eventoId = self::createOne($payload);
                if ($serieId === null) {
                    $serieId = $eventoId;
                    $pdo->prepare('UPDATE eventos SET serie_id = :serie WHERE id = :id')->execute([
                        ':serie' => $serieId,
                        ':id' => $eventoId,
                    ]);
                }
                $ids[] = $eventoId;
            }

            $pdo->commit();

            return $ids;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    public static function register(array $evento, int $participanteId): array
    {
        if (!(int) ($evento['inscricoes_abertas'] ?? 0)) {
            return ['ok' => false, 'error' => 'As inscrições para este evento estão fechadas.'];
        }

        $existing = self::findParticipation((int) $evento['id'], $participanteId);
        if ($existing !== null && $existing['status'] !== 'cancelado') {
            return ['ok' => false, 'error' => 'Você já está inscrito neste evento.'];
        }

        if (!self::hasCapacity($evento)) {
            return ['ok' => false, 'error' => 'Não há mais vagas disponíveis neste evento.'];
        }

        $details = self::typeDetails($evento);
        $taxa = $details['taxa'];
        $tipo = (string) $evento['tipo'];
        $precisaPagamento = in_array($tipo, ['torneio', 'oficina'], true) && $taxa > 0;
        $status = in_array($tipo, ['torneio', 'oficina'], true) ? 'pendente' : 'aprovado';

        $pdo = db();
        $pdo->beginTransaction();

        try {
            if ($existing !== null) {
                $stmt = $pdo->prepare(
                    "UPDATE evento_participantes
                     SET status = :status, inscrito_em = datetime('now')
                     WHERE evento_id = :evento_id AND participante_id = :participante_id"
                );
                $stmt->execute([
                    ':status' => $status,
                    ':evento_id' => (int) $evento['id'],
                    ':participante_id' => $participanteId,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO evento_participantes (evento_id, participante_id, status)
                     VALUES (:evento_id, :participante_id, :status)'
                );
                $stmt->execute([
                    ':evento_id' => (int) $evento['id'],
                    ':participante_id' => $participanteId,
                    ':status' => $status,
                ]);
            }

            if ($precisaPagamento) {
                $stmt = $pdo->prepare(
                    'DELETE FROM pagamentos
                     WHERE evento_id = :evento_id AND participante_id = :participante_id'
                );
                $stmt->execute([
                    ':evento_id' => (int) $evento['id'],
                    ':participante_id' => $participanteId,
                ]);

                $stmt = $pdo->prepare(
                    'INSERT INTO pagamentos
                        (evento_id, participante_id, valor, status, comprovante)
                     VALUES
                        (:evento_id, :participante_id, :valor, :status, NULL)'
                );
                $stmt->execute([
                    ':evento_id' => (int) $evento['id'],
                    ':participante_id' => $participanteId,
                    ':valor' => $taxa,
                    ':status' => 'pendente',
                ]);
            }

            $pdo->commit();

            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array{ok: bool, error: ?string, previous_path: ?string}
     */
    public static function upsertPaymentProof(
        array $evento,
        int $participanteId,
        string $comprovantePath
    ): array {
        $participation = self::findParticipation((int) $evento['id'], $participanteId);
        if ($participation === null || ($participation['status'] ?? '') === 'cancelado') {
            return [
                'ok' => false,
                'error' => 'Você precisa estar inscrito neste evento para enviar o comprovante.',
                'previous_path' => null,
            ];
        }

        if (($participation['status'] ?? '') === 'aprovado') {
            return [
                'ok' => false,
                'error' => 'A inscrição já foi aprovada. O comprovante não pode mais ser alterado.',
                'previous_path' => null,
            ];
        }

        $details = self::typeDetails($evento);
        $taxa = $details['taxa'];
        $tipo = (string) $evento['tipo'];
        $precisaPagamento = in_array($tipo, ['torneio', 'oficina'], true) && $taxa > 0;

        if (!$precisaPagamento) {
            return [
                'ok' => false,
                'error' => 'Este evento não exige comprovante de pagamento.',
                'previous_path' => null,
            ];
        }

        $existing = self::findPayment((int) $evento['id'], $participanteId);
        $previousPath = $existing !== null ? ($existing['comprovante'] ?? null) : null;

        $pdo = db();
        $pdo->beginTransaction();

        try {
            if ($existing !== null) {
                $stmt = $pdo->prepare(
                    "UPDATE pagamentos
                     SET comprovante = :comprovante,
                         status = 'pendente',
                         pago_em = NULL
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':comprovante' => $comprovantePath,
                    ':id' => (int) $existing['id'],
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO pagamentos
                        (evento_id, participante_id, valor, status, comprovante)
                     VALUES
                        (:evento_id, :participante_id, :valor, :status, :comprovante)'
                );
                $stmt->execute([
                    ':evento_id' => (int) $evento['id'],
                    ':participante_id' => $participanteId,
                    ':valor' => $taxa,
                    ':status' => 'pendente',
                    ':comprovante' => $comprovantePath,
                ]);
            }

            $pdo->commit();

            return [
                'ok' => true,
                'error' => null,
                'previous_path' => is_string($previousPath) ? $previousPath : null,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    public static function updateParticipantStatus(
        int $eventoId,
        int $participanteId,
        string $status
    ): array {
        if (!in_array($status, ['pendente', 'aprovado', 'cancelado'], true)) {
            return ['ok' => false, 'error' => 'Status de participação inválido.'];
        }

        $participation = self::findParticipation($eventoId, $participanteId);
        if ($participation === null) {
            return ['ok' => false, 'error' => 'Participação não encontrada.'];
        }

        $evento = self::findById($eventoId);
        if ($evento === null) {
            return ['ok' => false, 'error' => 'Evento não encontrado.'];
        }

        if ($status === 'aprovado' && ($participation['status'] ?? '') !== 'aprovado') {
            if (!self::hasCapacity($evento)) {
                return ['ok' => false, 'error' => 'Não há mais vagas disponíveis para aprovar esta inscrição.'];
            }
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'UPDATE evento_participantes
                 SET status = :status
                 WHERE evento_id = :evento_id AND participante_id = :participante_id'
            );
            $stmt->execute([
                ':status' => $status,
                ':evento_id' => $eventoId,
                ':participante_id' => $participanteId,
            ]);

            if ($status === 'aprovado') {
                $stmt = $pdo->prepare(
                    "UPDATE pagamentos
                     SET status = 'pago', pago_em = datetime('now')
                     WHERE evento_id = :evento_id
                       AND participante_id = :participante_id
                       AND status = 'pendente'"
                );
                $stmt->execute([
                    ':evento_id' => $eventoId,
                    ':participante_id' => $participanteId,
                ]);
            }

            $pdo->commit();

            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
