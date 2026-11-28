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

    public static function dismissApprovedPending(): void
    {
        db()->exec(
            "UPDATE notificacoes
             SET dispensada_em = datetime('now')
             WHERE tipo = 'pendente'
               AND dispensada_em IS NULL
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
                'partida' => 'Partidas',
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
            self::dismissApprovedPending();
        }

        self::collapseSeriesNotices();

        if ($items === []) {
            return;
        }

        $stmt = db()->prepare(
            'INSERT OR IGNORE INTO notificacoes (usuario_id, chave, tipo, texto, href, ocorrido_em, email_enviado)
             VALUES (:usuario_id, :chave, :tipo, :texto, :href, :ocorrido_em, 0)'
        );

        foreach ($items as $item) {
            self::deliver($userId, $item, $stmt);
        }
    }

    public static function publish(int $userId): void
    {
        $user = User::findById($userId);
        if ($user === null || (int) ($user['active'] ?? 0) !== 1) {
            return;
        }

        self::sync($userId, self::sources($user), isAdmin($user));
    }

    public static function publishActive(): void
    {
        $ids = db()->query('SELECT id FROM usuarios WHERE ativo = 1')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            self::publish((int) $id);
        }
    }

    public static function hasPendingEmails(): bool
    {
        $found = db()->query(
            'SELECT 1 FROM notificacoes WHERE email_enviado = 0 LIMIT 1'
        )->fetchColumn();

        return $found !== false;
    }

    public static function sendPendingEmails(): void
    {
        $rows = db()->query(
            'SELECT id, usuario_id, texto, href, ocorrido_em
             FROM notificacoes
             WHERE email_enviado = 0
             ORDER BY id ASC'
        )->fetchAll();

        $claim = db()->prepare(
            'UPDATE notificacoes SET email_enviado = 1 WHERE id = :id AND email_enviado = 0'
        );
        $release = db()->prepare(
            'UPDATE notificacoes SET email_enviado = 0 WHERE id = :id'
        );

        foreach ($rows as $row) {
            $claim->execute([':id' => (int) $row['id']]);
            if ($claim->rowCount() !== 1) {
                continue;
            }

            $sent = self::email((int) $row['usuario_id'], [
                'chave' => '',
                'tipo' => '',
                'text' => (string) $row['texto'],
                'href' => (string) $row['href'],
                'at' => (string) $row['ocorrido_em'],
            ]);
            if (!$sent) {
                $release->execute([':id' => (int) $row['id']]);
            }
        }
    }

    public static function publishAdmins(): void
    {
        $ids = db()->query(
            "SELECT a.usuario_id
             FROM admins a
             INNER JOIN usuarios u ON u.id = a.usuario_id
             WHERE u.ativo = 1
               AND a.cargo = 'administrador'"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            self::publish((int) $id);
        }
    }

    /**
     * @param array{chave: string, tipo: string, text: string, href: string, at: string} $item
     */
    private static function deliver(int $userId, array $item, ?PDOStatement $stmt = null): void
    {
        if ($item['at'] === '' || $item['chave'] === '') {
            return;
        }

        $stmt ??= db()->prepare(
            'INSERT OR IGNORE INTO notificacoes (usuario_id, chave, tipo, texto, href, ocorrido_em, email_enviado)
             VALUES (:usuario_id, :chave, :tipo, :texto, :href, :ocorrido_em, 0)'
        );
        $stmt->execute([
            ':usuario_id' => $userId,
            ':chave' => $item['chave'],
            ':tipo' => $item['tipo'],
            ':texto' => $item['text'],
            ':href' => $item['href'],
            ':ocorrido_em' => $item['at'],
        ]);
    }

    /**
     * @param array{chave: string, tipo: string, text: string, href: string, at: string} $item
     * @return bool true quando não precisa tentar de novo (enviado ou dispensado)
     */
    private static function email(int $userId, array $item): bool
    {
        if (!self::recente((string) $item['at'])) {
            return true;
        }

        $user = User::findById($userId);
        if ($user === null || (int) ($user['active'] ?? 0) !== 1) {
            return true;
        }

        $email = trim((string) ($user['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        if ((int) ($user['notify_emails'] ?? 1) !== 1) {
            return true;
        }

        $href = (string) $item['href'];
        if (!str_starts_with($href, '/')) {
            $href = '/' . ltrim($href, '/');
        }

        return EmailService::sendNotification(
            $email,
            userDisplayName($user),
            (string) $item['text'],
            appBaseUrl() . $href
        );
    }

    private static function recente(string $at): bool
    {
        $quando = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', substr($at, 0, 19));
        if ($quando === false) {
            try {
                $quando = new DateTimeImmutable($at);
            } catch (Exception) {
                return false;
            }
        }

        return $quando >= new DateTimeImmutable('-12 hours');
    }

    /** @return list<array{chave: string, tipo: string, text: string, href: string, at: string}> */
    private static function forMember(int $userId): array
    {
        $items = self::pendingMatchValidations($userId);

        $partidas = db()->prepare(
            "SELECT p.id, p.criada_em, e.titulo
             FROM partidas p
             INNER JOIN partida_jogadores pj
                ON pj.partida_id = p.id
               AND pj.participante_id = :usuario_id
               AND pj.validacao <> 'pendente'
             LEFT JOIN eventos e ON e.id = p.evento_id
             WHERE NOT EXISTS (
                 SELECT 1
                 FROM notificacoes n
                 WHERE n.usuario_id = :usuario_aviso
                   AND n.chave = 'validar-partida:' || p.id
             )
             ORDER BY p.criada_em DESC, p.id DESC"
        );
        $partidas->execute([
            ':usuario_id' => $userId,
            ':usuario_aviso' => $userId,
        ]);
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

        foreach (self::avisosDeEventos(false) as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /** @return list<array{chave: string, tipo: string, text: string, href: string, at: string}> */
    private static function forAdmin(int $userId): array
    {
        $items = self::pendingMatchValidations($userId);

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

        foreach (self::avisosDeEventos(true) as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param list<int> $userIds
     */
    public static function notifyMatchValidation(int $partidaId, array $userIds, string $evento, string $at): void
    {
        $userIds = array_values(array_unique(array_filter(
            $userIds,
            static fn (int $id): bool => $id > 0
        )));
        if ($partidaId <= 0 || $userIds === [] || $at === '') {
            return;
        }

        $texto = self::textoValidacaoPartida($evento);
        $item = [
            'chave' => 'validar-partida:' . $partidaId,
            'tipo' => 'partida',
            'text' => $texto,
            'href' => '/partidas',
            'at' => $at,
        ];
        foreach ($userIds as $userId) {
            self::deliver($userId, $item);
        }
    }

    public static function notifyRegistrationStatus(int $eventoId, int $participanteId, string $status): void
    {
        if (!in_array($status, ['aprovado', 'cancelado'], true) || $eventoId <= 0 || $participanteId <= 0) {
            return;
        }

        $stmt = db()->prepare('SELECT titulo, tipo FROM eventos WHERE id = :id');
        $stmt->execute([':id' => $eventoId]);
        $evento = $stmt->fetch();
        if (!$evento) {
            return;
        }

        $resultado = $status === 'aprovado' ? 'aprovada' : 'reprovada';
        self::deliver($participanteId, [
            'chave' => 'inscricao:' . $eventoId . ':' . $status,
            'tipo' => 'inscricao',
            'text' => 'Sua inscrição ' . self::comArtigo((string) $evento['tipo']) . ' ' . (string) $evento['titulo'] . ' foi ' . $resultado . '.',
            'href' => '/eventos/' . $eventoId,
            'at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array{chave: string, tipo: string, text: string, href: string, at: string}> */
    private static function pendingMatchValidations(int $userId): array
    {
        $stmt = db()->prepare(
            "SELECT p.id, p.criada_em, e.titulo
             FROM partidas p
             INNER JOIN partida_jogadores pj
                ON pj.partida_id = p.id
               AND pj.participante_id = :usuario_id
               AND pj.validacao = 'pendente'
             LEFT JOIN eventos e ON e.id = p.evento_id
             ORDER BY p.criada_em DESC, p.id DESC"
        );
        $stmt->execute([':usuario_id' => $userId]);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'chave' => 'validar-partida:' . (int) $row['id'],
                'tipo' => 'partida',
                'text' => self::textoValidacaoPartida((string) ($row['titulo'] ?? '')),
                'href' => '/partidas',
                'at' => (string) ($row['criada_em'] ?? ''),
            ];
        }

        return $items;
    }

    private static function textoValidacaoPartida(string $evento): string
    {
        $evento = trim($evento);

        return $evento !== ''
            ? 'Confirme se você participou da partida em ' . $evento . '.'
            : 'Confirme se você participou de uma partida.';
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

    /**
     * Uma série recorrente vira um único aviso, no primeiro encontro.
     * @return list<array{chave: string, tipo: string, text: string, href: string, at: string}>
     */
    private static function avisosDeEventos(bool $admin): array
    {
        $eventos = db()->query(
            'SELECT id, titulo, tipo, criado_em, serie_id
             FROM eventos
             WHERE serie_id IS NULL OR id = serie_id
             ORDER BY criado_em DESC, id DESC'
        );

        $items = [];
        foreach ($eventos->fetchAll() as $row) {
            $recorrente = $row['serie_id'] !== null && (int) $row['serie_id'] > 0;
            $titulo = (string) $row['titulo'];
            $items[] = [
                'chave' => 'evento:' . (int) $row['id'],
                'tipo' => 'evento',
                'text' => self::textoNovoEvento((string) $row['tipo'], $titulo, $recorrente, $admin),
                'href' => '/eventos/' . (int) $row['id'],
                'at' => (string) ($row['criado_em'] ?? ''),
            ];
        }

        return $items;
    }

    private static function textoNovoEvento(string $tipo, string $titulo, bool $recorrente, bool $admin): string
    {
        if ($admin) {
            return $recorrente
                ? 'Evento com recorrência criado: ' . $titulo . '.'
                : 'Evento criado: ' . $titulo . '.';
        }

        $rotulo = self::rotuloNovo($tipo);

        return $recorrente
            ? $rotulo . ' com recorrência: ' . $titulo . '.'
            : $rotulo . ': ' . $titulo . '.';
    }

    /** Remove avisos das datas seguintes e ajusta o texto do primeiro encontro. */
    private static function collapseSeriesNotices(): void
    {
        db()->exec(
            "DELETE FROM notificacoes
             WHERE chave IN (
                 SELECT 'evento:' || e.id
                 FROM eventos e
                 WHERE e.serie_id IS NOT NULL
                   AND e.id <> e.serie_id
             )"
        );

        $stmt = db()->query(
            "SELECT n.id, n.texto, e.titulo, e.tipo
             FROM notificacoes n
             INNER JOIN eventos e ON n.chave = 'evento:' || e.id
             WHERE e.serie_id IS NOT NULL
               AND e.id = e.serie_id
               AND n.texto NOT LIKE '%recorrência%'"
        );
        $update = db()->prepare('UPDATE notificacoes SET texto = :texto WHERE id = :id');
        foreach ($stmt->fetchAll() as $row) {
            $admin = str_starts_with((string) $row['texto'], 'Evento criado:');
            $update->execute([
                ':texto' => self::textoNovoEvento(
                    (string) $row['tipo'],
                    (string) $row['titulo'],
                    true,
                    $admin
                ),
                ':id' => (int) $row['id'],
            ]);
        }
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
