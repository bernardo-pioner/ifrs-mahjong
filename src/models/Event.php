<?php

declare(strict_types=1);

final class Event
{
    public static function count(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM eventos')->fetchColumn();
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

    /** @return array{limite: ?int, taxa: float} */
    public static function typeDetails(array $evento): array
    {
        $id = (int) $evento['id'];
        $tipo = (string) $evento['tipo'];

        if ($tipo === 'torneio') {
            $stmt = db()->prepare(
                'SELECT maximo_participantes AS limite, taxa_participacao AS taxa
                 FROM torneios WHERE evento_id = :id'
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch() ?: [];

            return [
                'limite' => isset($row['limite']) ? (int) $row['limite'] : null,
                'taxa' => (float) ($row['taxa'] ?? 0),
            ];
        }

        if ($tipo === 'oficina') {
            $stmt = db()->prepare(
                'SELECT vagas AS limite, taxa_inscricao AS taxa
                 FROM oficinas WHERE evento_id = :id'
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch() ?: [];

            return [
                'limite' => isset($row['limite']) ? (int) $row['limite'] : null,
                'taxa' => (float) ($row['taxa'] ?? 0),
            ];
        }

        return ['limite' => null, 'taxa' => 0.0];
    }

    public static function countActiveParticipants(int $eventoId): int
    {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM evento_participantes
             WHERE evento_id = :id AND status IN ('pendente', 'aprovado')"
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
    public static function listParticipants(int $eventoId): array
    {
        $stmt = db()->prepare(
            'SELECT
                u.id AS usuario_id,
                u.nome,
                p.apelido,
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
             ORDER BY ep.inscrito_em ASC'
        );
        $stmt->execute([':evento_id' => $eventoId]);

        return $stmt->fetchAll();
    }

    /**
     * @param array{
     *   admin_id: int,
     *   tipo: string,
     *   titulo: string,
     *   descricao: ?string,
     *   data_inicio: string,
     *   data_fim: ?string,
     *   local: ?string,
     *   inscricoes_abertas: int,
     *   limite: ?int,
     *   taxa: float
     * } $data
     */
    public static function createOne(array $data): int
    {
        $pdo = db();

        $stmt = $pdo->prepare(
            'INSERT INTO eventos
                (admin_id, tipo, titulo, descricao, data_inicio, data_fim, local, inscricoes_abertas)
             VALUES
                (:admin_id, :tipo, :titulo, :descricao, :data_inicio, :data_fim, :local, :inscricoes_abertas)'
        );
        $stmt->execute([
            ':admin_id' => $data['admin_id'],
            ':tipo' => $data['tipo'],
            ':titulo' => $data['titulo'],
            ':descricao' => $data['descricao'],
            ':data_inicio' => $data['data_inicio'],
            ':data_fim' => $data['data_fim'],
            ':local' => $data['local'],
            ':inscricoes_abertas' => $data['inscricoes_abertas'],
        ]);

        $eventoId = (int) $pdo->lastInsertId();
        self::insertTypeRow($eventoId, $data['tipo'], $data['limite'], $data['taxa'], $data['inscricoes_abertas']);

        return $eventoId;
    }

    public static function insertTypeRow(
        int $eventoId,
        string $tipo,
        ?int $limite,
        float $taxa,
        int $inscricoesAbertas
    ): void {
        $pdo = db();

        if ($tipo === 'torneio') {
            $stmt = $pdo->prepare(
                'INSERT INTO torneios (evento_id, maximo_participantes, taxa_participacao)
                 VALUES (:evento_id, :limite, :taxa)'
            );
            $stmt->execute([
                ':evento_id' => $eventoId,
                ':limite' => $limite,
                ':taxa' => $taxa,
            ]);
            return;
        }

        if ($tipo === 'oficina') {
            $stmt = $pdo->prepare(
                'INSERT INTO oficinas (evento_id, vagas, taxa_inscricao)
                 VALUES (:evento_id, :limite, :taxa)'
            );
            $stmt->execute([
                ':evento_id' => $eventoId,
                ':limite' => $limite,
                ':taxa' => $taxa,
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
     *   titulo: string,
     *   descricao: ?string,
     *   data_inicio: string,
     *   data_fim: ?string,
     *   local: ?string,
     *   inscricoes_abertas: int,
     *   limite: ?int,
     *   taxa: float
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
                $payload['data_inicio'] = $inicioAtual->format('Y-m-d H:i:s');
                $payload['data_fim'] = $fimAtual?->format('Y-m-d H:i:s');
                $ids[] = self::createOne($payload);
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
    public static function register(
        array $evento,
        int $participanteId,
        ?string $comprovantePath = null
    ): array {
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

        if ($precisaPagamento && ($comprovantePath === null || $comprovantePath === '')) {
            return ['ok' => false, 'error' => 'Anexe o comprovante de pagamento (PDF, PNG ou JPEG).'];
        }

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

            return ['ok' => true, 'error' => null];
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
