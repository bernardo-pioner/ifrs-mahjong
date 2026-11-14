<?php

declare(strict_types=1);

final class Notification
{
    private const POR_PAGINA = 10;
    private const NO_DASHBOARD = 5;

    /**
     * @param array<string, mixed> $user
     * @return array{
     *   items: list<array{chave: string, tipo: string, text: string, href: string, at: string}>,
     *   total: int,
     *   page: int,
     *   pages: int
     * }
     */
    public static function recentFor(array $user, int $page = 1): array
    {
        $userId = (int) ($user['id'] ?? 0);
        self::sync($userId, self::sources($user), isAdmin($user));

        $where = 'usuario_id = :usuario_id AND dispensada_em IS NULL';
        $count = db()->prepare('SELECT COUNT(*) FROM notificacoes WHERE ' . $where);
        $count->execute([':usuario_id' => $userId]);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::NO_DASHBOARD));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * self::NO_DASHBOARD;

        $stmt = db()->prepare(
            'SELECT chave, tipo, texto AS text, href, ocorrido_em AS at
             FROM notificacoes
             WHERE ' . $where . '
             ORDER BY ocorrido_em DESC, id DESC
             LIMIT :limite OFFSET :offset'
        );
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', self::NO_DASHBOARD, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    public static function forgetApprovedPending(): void
    {
        db()->exec(
            "DELETE FROM notificacoes
             WHERE tipo = 'pendente'
               AND EXISTS (
                   SELECT 1
                   FROM evento_participantes ep
                   WHERE notificacoes.chave = 'pendente:' || ep.evento_id || ':' || ep.participante_id
                     AND ep.status = 'aprovado'
               )"
        );
    }

    /**
     * @param array<string, mixed> $user
     * @return array{
     *   items: list<array{chave: string, tipo: string, text: string, href: string, at: string, dispensada: bool}>,
     *   total: int,
     *   page: int,
     *   pages: int
     * }
     */
    public static function history(array $user, string $tipo, string $estado, int $page): array
    {
        $userId = (int) ($user['id'] ?? 0);
        self::sync($userId, self::sources($user), isAdmin($user));

        $tipos = array_keys(self::typeOptions($user));
        if (!in_array($tipo, $tipos, true)) {
            $tipo = '';
        }
        if (!in_array($estado, ['ativa', 'dispensada'], true)) {
            $estado = '';
        }

        $where = 'usuario_id = :usuario_id';
        $params = [':usuario_id' => $userId];
        if ($tipo !== '') {
            $where .= ' AND tipo = :tipo';
            $params[':tipo'] = $tipo;
        }
        if ($estado === 'ativa') {
            $where .= ' AND dispensada_em IS NULL';
        } elseif ($estado === 'dispensada') {
            $where .= ' AND dispensada_em IS NOT NULL';
        }

        $count = db()->prepare('SELECT COUNT(*) FROM notificacoes WHERE ' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::POR_PAGINA));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * self::POR_PAGINA;

        $stmt = db()->prepare(
            'SELECT chave, tipo, texto AS text, href, ocorrido_em AS at,
                    dispensada_em IS NOT NULL AS dispensada
             FROM notificacoes
             WHERE ' . $where . '
             ORDER BY ocorrido_em DESC, id DESC
             LIMIT :limite OFFSET :offset'
        );
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', self::POR_PAGINA, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['dispensada'] = (int) $row['dispensada'] === 1;
            $items[] = $row;
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /** @param array<string, mixed> $user */
    public static function dismiss(array $user, string $chave): void
    {
        $stmt = db()->prepare(
            "UPDATE notificacoes
             SET dispensada_em = datetime('now')
             WHERE usuario_id = :usuario_id
               AND chave = :chave
               AND dispensada_em IS NULL"
        );
        $stmt->execute([
            ':usuario_id' => (int) ($user['id'] ?? 0),
            ':chave' => $chave,
        ]);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, string>
     */
    public static function typeOptions(array $user): array
    {
        if (isAdmin($user)) {
            return [
                'pendente' => 'Inscrições pendentes',
                'usuario' => 'Novos usuários',
                'evento' => 'Eventos criados',
            ];
        }

        return [
            'partida' => 'Partidas',
            'inscricao' => 'Inscrições',
            'evento' => 'Novos eventos',
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array{chave: string, tipo: string, text: string, href: string, at: string}>
     */
    private static function sources(array $user): array
    {
        $userId = (int) ($user['id'] ?? 0);

        return isAdmin($user) ? self::forAdmin($userId) : self::forMember($userId);
    }

    /** @param list<array{chave: string, tipo: string, text: string, href: string, at: string}> $items */
    private static function sync(int $userId, array $items, bool $admin): void
    {
        if ($admin) {
            self::forgetApprovedPending();
        }

        if ($items === []) {
            return;
        }

        $stmt = db()->prepare(
            'INSERT OR IGNORE INTO notificacoes (usuario_id, chave, tipo, texto, href, ocorrido_em)
             VALUES (:usuario_id, :chave, :tipo, :texto, :href, :ocorrido_em)'
        );

        foreach ($items as $item) {
            if ($item['at'] === '' || $item['chave'] === '') {
                continue;
            }
            $stmt->execute([
                ':usuario_id' => $userId,
                ':chave' => $item['chave'],
                ':tipo' => $item['tipo'],
                ':texto' => $item['text'],
                ':href' => $item['href'],
                ':ocorrido_em' => $item['at'],
            ]);
        }
    }

    /** @return list<array{chave: string, tipo: string, text: string, href: string, at: string}> */
    private static function forMember(int $userId): array
    {
        $items = [];

        $partidas = db()->prepare(
            'SELECT p.id, p.criada_em, e.titulo
             FROM partidas p
             INNER JOIN partida_jogadores pj
                ON pj.partida_id = p.id
               AND pj.participante_id = :usuario_id
             LEFT JOIN eventos e ON e.id = p.evento_id
             ORDER BY p.criada_em DESC, p.id DESC'
        );
        $partidas->execute([':usuario_id' => $userId]);
        foreach ($partidas->fetchAll() as $row) {
            $evento = trim((string) ($row['titulo'] ?? ''));
            $items[] = [
                'chave' => 'partida:' . (int) $row['id'],
                'tipo' => 'partida',
                'text' => $evento !== ''
                    ? 'Você participou de uma partida em ' . $evento . '.'
                    : 'Você participou de uma partida.',
                'href' => '/partidas/' . (int) $row['id'],
                'at' => (string) ($row['criada_em'] ?? ''),
            ];
        }

        $inscricoes = db()->prepare(
            "SELECT ep.status, ep.inscrito_em, e.id, e.titulo, e.tipo
             FROM evento_participantes ep
             INNER JOIN eventos e ON e.id = ep.evento_id
             WHERE ep.participante_id = :usuario_id
               AND e.tipo IN ('oficina', 'torneio')
               AND ep.status IN ('aprovado', 'cancelado')
             ORDER BY ep.inscrito_em DESC, e.id DESC"
        );
        $inscricoes->execute([':usuario_id' => $userId]);
        foreach ($inscricoes->fetchAll() as $row) {
            $tipo = (string) $row['tipo'];
            $resultado = (string) $row['status'] === 'aprovado' ? 'aprovada' : 'reprovada';
            $items[] = [
                'chave' => 'inscricao:' . (int) $row['id'] . ':' . (string) $row['status'],
                'tipo' => 'inscricao',
                'text' => 'Sua inscrição ' . self::comArtigo($tipo) . ' ' . (string) $row['titulo'] . ' foi ' . $resultado . '.',
                'href' => '/eventos/' . (int) $row['id'],
                'at' => (string) ($row['inscrito_em'] ?? ''),
            ];
        }

        $eventos = db()->query(
            'SELECT id, titulo, tipo, criado_em
             FROM eventos
             ORDER BY criado_em DESC, id DESC'
        );
        foreach ($eventos->fetchAll() as $row) {
            $items[] = [
                'chave' => 'evento:' . (int) $row['id'],
                'tipo' => 'evento',
                'text' => self::rotuloNovo((string) $row['tipo']) . ': ' . (string) $row['titulo'] . '.',
                'href' => '/eventos/' . (int) $row['id'],
                'at' => (string) ($row['criado_em'] ?? ''),
            ];
        }

        return $items;
    }

    /** @return list<array{chave: string, tipo: string, text: string, href: string, at: string}> */
    private static function forAdmin(int $userId): array
    {
        $items = [];

        $pendentes = db()->query(
            "SELECT ep.participante_id, ep.inscrito_em, e.id, e.titulo, e.tipo, u.apelido, u.nome
             FROM evento_participantes ep
             INNER JOIN eventos e ON e.id = ep.evento_id
             INNER JOIN usuarios u ON u.id = ep.participante_id
             WHERE ep.status = 'pendente'
             ORDER BY ep.inscrito_em DESC, e.id DESC"
        );
        foreach ($pendentes->fetchAll() as $row) {
            $nome = self::nome($row);
            $items[] = [
                'chave' => 'pendente:' . (int) $row['id'] . ':' . (int) $row['participante_id'],
                'tipo' => 'pendente',
                'text' => $nome . ' aguarda aprovação ' . self::comArtigo((string) $row['tipo']) . ' ' . (string) $row['titulo'] . '.',
                'href' => '/eventos/' . (int) $row['id'],
                'at' => (string) ($row['inscrito_em'] ?? ''),
            ];
        }

        $usuarios = db()->prepare(
            'SELECT id, apelido, nome, criado_em
             FROM usuarios
             WHERE id != :id
             ORDER BY criado_em DESC, id DESC'
        );
        $usuarios->execute([':id' => $userId]);
        foreach ($usuarios->fetchAll() as $row) {
            $items[] = [
                'chave' => 'usuario:' . (int) $row['id'],
                'tipo' => 'usuario',
                'text' => self::nome($row) . ' entrou na plataforma.',
                'href' => '/usuarios',
                'at' => (string) ($row['criado_em'] ?? ''),
            ];
        }

        $eventos = db()->query(
            'SELECT id, titulo, criado_em
             FROM eventos
             ORDER BY criado_em DESC, id DESC'
        );
        foreach ($eventos->fetchAll() as $row) {
            $items[] = [
                'chave' => 'evento:' . (int) $row['id'],
                'tipo' => 'evento',
                'text' => 'Evento criado: ' . (string) $row['titulo'] . '.',
                'href' => '/eventos/' . (int) $row['id'],
                'at' => (string) ($row['criado_em'] ?? ''),
            ];
        }

        return $items;
    }

    /** @param array<string, mixed> $row */
    private static function nome(array $row): string
    {
        $apelido = trim((string) ($row['apelido'] ?? ''));
        if ($apelido !== '') {
            return $apelido;
        }

        $nome = trim((string) ($row['nome'] ?? ''));

        return $nome !== '' ? $nome : 'Participante';
    }

    private static function comArtigo(string $tipo): string
    {
        return match ($tipo) {
            'oficina' => 'na oficina',
            'torneio' => 'no torneio',
            'encontro' => 'no encontro',
            default => 'no evento',
        };
    }

    private static function rotuloNovo(string $tipo): string
    {
        return match ($tipo) {
            'oficina' => 'Nova oficina',
            'torneio' => 'Novo torneio',
            'encontro' => 'Novo encontro',
            default => 'Novo evento',
        };
    }
}
