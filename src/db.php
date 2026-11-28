<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = config();
    $path = $config['db_path'];
    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível criar a pasta de dados.');
    }

    $schemaSql = (string) file_get_contents($config['schema_path']);
    $fingerprint = hash('sha256', str_replace(["\r\n", "\r"], "\n", $schemaSql));

    if (is_file($path) && schemaIsOutdated($path, $fingerprint)) {
        $existing = openSqlite($path);
        if (applyCompatibleSchemaUpdates($existing)) {
            $existing->exec($schemaSql);
            saveSchemaFingerprint($existing, $fingerprint);
            $pdo = $existing;
        } else {
            rebuildSqliteFile($path);
        }
    }

    if (!$pdo instanceof PDO) {
        $pdo = openSqlite($path);
        $pdo->exec($schemaSql);
        saveSchemaFingerprint($pdo, $fingerprint);
    }

    applyCompatibleSchemaUpdates($pdo);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ($count === 0) {
        $pdo->exec((string) file_get_contents($config['seed_path']));
    }

    return $pdo;
}

function openSqlite(string $path): PDO
{
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function schemaIsOutdated(string $path, string $fingerprint): bool
{
    try {
        $pdo = openSqlite($path);
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS _schema_meta (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                fingerprint TEXT NOT NULL
            )'
        );
        $stored = $pdo->query('SELECT fingerprint FROM _schema_meta WHERE id = 1')->fetchColumn();
        $pdo = null;

        return !is_string($stored) || $stored !== $fingerprint;
    } catch (PDOException) {
        return true;
    }
}

function saveSchemaFingerprint(PDO $pdo, string $fingerprint): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS _schema_meta (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            fingerprint TEXT NOT NULL
        )'
    );
    $stmt = $pdo->prepare(
        'INSERT INTO _schema_meta (id, fingerprint) VALUES (1, :fingerprint)
         ON CONFLICT(id) DO UPDATE SET fingerprint = excluded.fingerprint'
    );
    $stmt->execute(['fingerprint' => $fingerprint]);
}

function tableColumns(PDO $pdo, string $table): ?array
{
    $exists = $pdo->query(
        "SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $pdo->quote($table)
    )->fetchColumn();
    if ($exists !== $table) {
        return null;
    }

    $present = [];
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')') as $column) {
        $present[(string) $column['name']] = true;
    }

    return $present;
}

function applyCompatibleSchemaUpdates(PDO $pdo): bool
{
    $participantes = tableColumns($pdo, 'participantes');
    $oficinas = tableColumns($pdo, 'oficinas');
    $torneios = tableColumns($pdo, 'torneios');
    $eventos = tableColumns($pdo, 'eventos');
    if ($participantes === null || $oficinas === null || $torneios === null || $eventos === null) {
        return false;
    }

    $changed = false;

    if (isset($participantes['tenhou']) && !isset($participantes['discord'])) {
        $pdo->exec('ALTER TABLE participantes RENAME COLUMN tenhou TO discord');
        unset($participantes['tenhou']);
        $participantes['discord'] = true;
        $changed = true;
    }

    foreach (['mahjong_soul', 'riichi_city', 'discord'] as $name) {
        if (!isset($participantes[$name])) {
            $pdo->exec('ALTER TABLE participantes ADD COLUMN ' . $name . ' TEXT');
            $participantes[$name] = true;
            $changed = true;
        }
    }

    if (!isset($oficinas['premiacao'])) {
        $pdo->exec('ALTER TABLE oficinas ADD COLUMN premiacao TEXT');
        $changed = true;
    }

    if (!isset($torneios['premiacao'])) {
        $pdo->exec('ALTER TABLE torneios ADD COLUMN premiacao TEXT');
        $changed = true;
    }

    if (!isset($eventos['serie_id'])) {
        $pdo->exec('ALTER TABLE eventos ADD COLUMN serie_id INTEGER');
        $changed = true;
    }

    if (!isset($eventos['modalidade'])) {
        $pdo->exec("ALTER TABLE eventos ADD COLUMN modalidade TEXT NOT NULL DEFAULT 'livre'");
        $changed = true;
    }

    if (tableColumns($pdo, 'evento_dias') === null) {
        $pdo->exec(
            'CREATE TABLE evento_dias (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                evento_id INTEGER NOT NULL,
                ordem INTEGER NOT NULL,
                data_inicio TEXT NOT NULL,
                data_fim TEXT,
                FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
                UNIQUE (evento_id, ordem)
            )'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_evento_dias_evento
             ON evento_dias(evento_id, data_inicio)'
        );
        $changed = true;
    }

    $diasFaltando = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM eventos e
         WHERE e.data_inicio IS NOT NULL
           AND trim(e.data_inicio) <> ''
           AND NOT EXISTS (
               SELECT 1 FROM evento_dias d WHERE d.evento_id = e.id
           )"
    )->fetchColumn();
    if ($diasFaltando > 0) {
        $pdo->exec(
            "INSERT INTO evento_dias (evento_id, ordem, data_inicio, data_fim)
             SELECT e.id, 1, e.data_inicio, NULLIF(trim(e.data_fim), '')
             FROM eventos e
             WHERE e.data_inicio IS NOT NULL
               AND trim(e.data_inicio) <> ''
               AND NOT EXISTS (
                   SELECT 1 FROM evento_dias d WHERE d.evento_id = e.id
               )"
        );
        $changed = true;
    }

    if (isset($eventos['inscricoes_abertas'])) {
        $pdo->exec(
            "UPDATE encontros
             SET observacoes = NULL
             WHERE observacoes IN ('Inscrições habilitadas', 'Inscrições não habilitadas')"
        );
        $pdo->exec('ALTER TABLE eventos DROP COLUMN inscricoes_abertas');
        unset($eventos['inscricoes_abertas']);
        $changed = true;
    }

    if (tableColumns($pdo, 'notificacoes') === null) {
        $pdo->exec(
            'CREATE TABLE notificacoes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id INTEGER NOT NULL,
                chave TEXT NOT NULL,
                tipo TEXT NOT NULL,
                texto TEXT NOT NULL,
                href TEXT NOT NULL,
                ocorrido_em TEXT NOT NULL,
                dispensada_em TEXT,
                email_enviado INTEGER NOT NULL DEFAULT 0 CHECK (email_enviado IN (0, 1)),
                UNIQUE (usuario_id, chave),
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
            )'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_notificacoes_usuario
             ON notificacoes(usuario_id, ocorrido_em)'
        );
        $changed = true;
    }

    $notificacoes = tableColumns($pdo, 'notificacoes');
    if ($notificacoes !== null && !isset($notificacoes['email_enviado'])) {
        $pdo->exec(
            'ALTER TABLE notificacoes
             ADD COLUMN email_enviado INTEGER NOT NULL DEFAULT 1
             CHECK (email_enviado IN (0, 1))'
        );
        $changed = true;
    }

    $usuarios = tableColumns($pdo, 'usuarios');
    if ($usuarios !== null && !isset($usuarios['email_verificado'])) {
        $pdo->exec(
            'ALTER TABLE usuarios
             ADD COLUMN email_verificado INTEGER NOT NULL DEFAULT 0
             CHECK (email_verificado IN (0, 1))'
        );
        $usuarios['email_verificado'] = true;
        $changed = true;
    }

    if ($usuarios !== null && !isset($usuarios['emails_notificacao'])) {
        $pdo->exec(
            'ALTER TABLE usuarios
             ADD COLUMN emails_notificacao INTEGER NOT NULL DEFAULT 1
             CHECK (emails_notificacao IN (0, 1))'
        );
        $usuarios['emails_notificacao'] = true;
        $changed = true;
    }

    if (tableColumns($pdo, 'codigos_email') === null) {
        $pdo->exec(
            "CREATE TABLE codigos_email (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id INTEGER NOT NULL,
                finalidade TEXT NOT NULL CHECK (finalidade IN ('verificar_email', 'redefinir_senha')),
                codigo_hash TEXT NOT NULL,
                tentativas INTEGER NOT NULL DEFAULT 0,
                expira_em TEXT NOT NULL,
                usado_em TEXT,
                criado_em TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
            )"
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_codigos_email_usuario
             ON codigos_email(usuario_id, finalidade)'
        );
        $pdo->exec('UPDATE usuarios SET email_verificado = 1');
        $changed = true;
    }

    if ($usuarios !== null && !isset($usuarios['apelido'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN apelido TEXT');
        backfillUsuarioApelidos($pdo);
        $changed = true;
    }

    if ($usuarios !== null && usuarioNomeObrigatorio($pdo)) {
        tornarNomeOpcional($pdo);
        $changed = true;
    }

    if ($usuarios !== null && usuarioNomeSemLimite($pdo)) {
        limitarNomeUsuario($pdo);
        $changed = true;
    }

    $pagamentos = tableColumns($pdo, 'pagamentos');
    if ($pagamentos !== null && !isset($pagamentos['comprovante'])) {
        $pdo->exec('ALTER TABLE pagamentos ADD COLUMN comprovante TEXT');
        $changed = true;
    }

    if (inscricaoStatusLegado($pdo)) {
        atualizarStatusInscricao($pdo);
        $changed = true;
    }

    $inscricoes = tableColumns($pdo, 'evento_participantes');
    if ($inscricoes !== null && !isset($inscricoes['suplente'])) {
        $pdo->exec(
            'ALTER TABLE evento_participantes
             ADD COLUMN suplente INTEGER NOT NULL DEFAULT 0
             CHECK (suplente IN (0, 1))'
        );
        $changed = true;
    }

    if (atualizarPartidasEvento($pdo)) {
        $changed = true;
    }

    $admins = tableColumns($pdo, 'admins');
    if ($admins !== null && isset($admins['cargo'])) {
        $pdo->exec(
            "UPDATE admins
             SET cargo = 'administrador'
             WHERE cargo IS NULL OR trim(cargo) = ''"
        );
    }

    if (tableColumns($pdo, 'participantes') !== null && tableColumns($pdo, 'usuarios') !== null) {
        $pdo->exec(
            "INSERT INTO participantes (usuario_id, apelido, nivel)
             SELECT u.id, u.apelido, 'iniciante'
             FROM usuarios u
             WHERE NOT EXISTS (
                 SELECT 1 FROM participantes p WHERE p.usuario_id = u.id
             )"
        );
    }

    return $changed || true;
}

function atualizarPartidasEvento(PDO $pdo): bool
{
    $partidas = tableColumns($pdo, 'partidas');
    $jogadores = tableColumns($pdo, 'partida_jogadores');
    if ($partidas === null || $jogadores === null) {
        return false;
    }

    $changed = false;

    if (!isset($partidas['evento_id'])) {
        $pdo->exec('ALTER TABLE partidas ADD COLUMN evento_id INTEGER REFERENCES eventos(id)');
        $changed = true;
    }

    if (!isset($partidas['registrado_por'])) {
        $pdo->exec('ALTER TABLE partidas ADD COLUMN registrado_por INTEGER REFERENCES usuarios(id)');
        $pdo->exec(
            'UPDATE partidas
             SET registrado_por = (
                SELECT pj.participante_id
                FROM partida_jogadores pj
                WHERE pj.partida_id = partidas.id
                  AND pj.participante_id IS NOT NULL
                ORDER BY pj.id ASC
                LIMIT 1
             )
             WHERE registrado_por IS NULL'
        );
        $changed = true;
    }

    if (!isset($partidas['formato'])) {
        $pdo->exec(
            "ALTER TABLE partidas ADD COLUMN formato TEXT
             CHECK (formato IS NULL OR formato IN (
                '4_jogadores_leste',
                '4_jogadores_sul',
                '3_jogadores_leste',
                '3_jogadores_sul'
             ))"
        );
        $changed = true;
    }

    $pdo->exec(
        'UPDATE partidas
         SET evento_id = (
            SELECT pc.encontro_id
            FROM partidas_casuais pc
            WHERE pc.partida_id = partidas.id
         )
         WHERE evento_id IS NULL
           AND EXISTS (
            SELECT 1 FROM partidas_casuais pc WHERE pc.partida_id = partidas.id
           )'
    );

    if (!isset($jogadores['id']) || !isset($jogadores['nome_convidado'])) {
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->beginTransaction();

        try {
            $pdo->exec(
                'CREATE TABLE partida_jogadores_nova (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    partida_id INTEGER NOT NULL,
                    participante_id INTEGER,
                    nome_convidado TEXT,
                    colocacao INTEGER,
                    pontuacao INTEGER,
                    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,
                    FOREIGN KEY (participante_id) REFERENCES participantes(usuario_id),
                    CHECK (
                        (participante_id IS NOT NULL AND nome_convidado IS NULL)
                        OR (
                            participante_id IS NULL
                            AND nome_convidado IS NOT NULL
                            AND length(trim(nome_convidado)) > 0
                        )
                    )
                )'
            );
            $pdo->exec(
                'INSERT INTO partida_jogadores_nova
                    (partida_id, participante_id, colocacao, pontuacao)
                 SELECT partida_id, participante_id, colocacao, pontuacao
                 FROM partida_jogadores'
            );
            $pdo->exec('DROP TABLE partida_jogadores');
            $pdo->exec('ALTER TABLE partida_jogadores_nova RENAME TO partida_jogadores');
            $pdo->exec(
                'CREATE INDEX IF NOT EXISTS idx_partidas_evento ON partidas(evento_id)'
            );
            $pdo->exec(
                'CREATE INDEX IF NOT EXISTS idx_partida_jogadores_participante
                 ON partida_jogadores(participante_id)'
            );
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->exec('PRAGMA foreign_keys = ON');
            throw $exception;
        }

        $pdo->exec('PRAGMA foreign_keys = ON');
        $changed = true;
        $jogadores = tableColumns($pdo, 'partida_jogadores') ?? [];
    }

    if (!isset($jogadores['validacao'])) {
        $pdo->exec(
            "ALTER TABLE partida_jogadores
             ADD COLUMN validacao TEXT NOT NULL DEFAULT 'confirmada'
             CHECK (validacao IN ('pendente', 'confirmada'))"
        );
        $changed = true;
    }

    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_partidas_evento ON partidas(evento_id)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_partida_jogadores_participante
         ON partida_jogadores(participante_id)'
    );

    return $changed;
}

function inscricaoStatusLegado(PDO $pdo): bool
{
    $sql = $pdo->query(
        "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'evento_participantes'"
    )->fetchColumn();

    return is_string($sql) && str_contains($sql, 'lista_espera');
}

function atualizarStatusInscricao(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->beginTransaction();

    try {
        $pdo->exec(
            'CREATE TABLE evento_participantes_status (
                evento_id INTEGER NOT NULL,
                participante_id INTEGER NOT NULL,
                inscrito_em TEXT NOT NULL DEFAULT (datetime(\'now\')),
                status TEXT NOT NULL DEFAULT \'pendente\'
                    CHECK (status IN (\'pendente\', \'aprovado\', \'cancelado\')),
                suplente INTEGER NOT NULL DEFAULT 0 CHECK (suplente IN (0, 1)),
                PRIMARY KEY (evento_id, participante_id),
                FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
                FOREIGN KEY (participante_id) REFERENCES participantes(usuario_id)
            )'
        );
        $pdo->exec(
            'INSERT INTO evento_participantes_status (evento_id, participante_id, inscrito_em, status)
             SELECT evento_id, participante_id, inscrito_em,
                    CASE status
                        WHEN \'confirmado\' THEN \'aprovado\'
                        WHEN \'aprovado\' THEN \'aprovado\'
                        WHEN \'cancelado\' THEN \'cancelado\'
                        ELSE \'pendente\'
                    END
             FROM evento_participantes'
        );
        $pdo->exec('DROP TABLE evento_participantes');
        $pdo->exec('ALTER TABLE evento_participantes_status RENAME TO evento_participantes');
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_evento_participantes_participante
             ON evento_participantes(participante_id)'
        );
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
        throw $exception;
    }

    $pdo->exec('PRAGMA foreign_keys = ON');
}

function usuarioNomeSemLimite(PDO $pdo): bool
{
    $sql = $pdo->query(
        "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'usuarios'"
    )->fetchColumn();

    return !is_string($sql) || !str_contains($sql, 'length(trim(nome))');
}

function limitarNomeUsuario(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->beginTransaction();

    try {
        $pdo->exec(
            'CREATE TABLE usuarios_nome_limitado (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT CHECK (
                    nome IS NULL
                    OR (
                        length(trim(nome)) BETWEEN 2 AND 20
                        AND nome = trim(nome)
                    )
                ),
                apelido TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                senha_hash TEXT NOT NULL,
                ativo INTEGER NOT NULL DEFAULT 1 CHECK (ativo IN (0, 1)),
                criado_em TEXT NOT NULL DEFAULT (datetime(\'now\')),
                atualizado_em TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );
        $pdo->exec(
            'INSERT INTO usuarios_nome_limitado
                (id, nome, apelido, email, senha_hash, ativo, criado_em, atualizado_em)
             SELECT id,
                    CASE
                        WHEN nome IS NULL OR trim(nome) = \'\' THEN NULL
                        ELSE trim(nome)
                    END,
                    apelido, email, senha_hash, ativo, criado_em, atualizado_em
             FROM usuarios'
        );
        $pdo->exec('DROP TABLE usuarios');
        $pdo->exec('ALTER TABLE usuarios_nome_limitado RENAME TO usuarios');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
        throw $exception;
    }

    $pdo->exec('PRAGMA foreign_keys = ON');
}

function usuarioNomeObrigatorio(PDO $pdo): bool
{
    foreach ($pdo->query('PRAGMA table_info(usuarios)') as $column) {
        if ((string) $column['name'] === 'nome') {
            return (int) $column['notnull'] === 1;
        }
    }

    return false;
}

function tornarNomeOpcional(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->beginTransaction();

    try {
        $pdo->exec(
            'CREATE TABLE usuarios_nome_opcional (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT,
                apelido TEXT,
                email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                senha_hash TEXT NOT NULL,
                ativo INTEGER NOT NULL DEFAULT 1 CHECK (ativo IN (0, 1)),
                criado_em TEXT NOT NULL DEFAULT (datetime(\'now\')),
                atualizado_em TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );
        $pdo->exec(
            'INSERT INTO usuarios_nome_opcional
                (id, nome, apelido, email, senha_hash, ativo, criado_em, atualizado_em)
             SELECT id, NULLIF(TRIM(nome), \'\'), apelido, email, senha_hash, ativo, criado_em, atualizado_em
             FROM usuarios'
        );
        $pdo->exec('DROP TABLE usuarios');
        $pdo->exec('ALTER TABLE usuarios_nome_opcional RENAME TO usuarios');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
        throw $exception;
    }

    $pdo->exec('PRAGMA foreign_keys = ON');
}

function backfillUsuarioApelidos(PDO $pdo): void
{
    $rows = $pdo->query(
        'SELECT u.id, u.email, p.apelido AS nickname
         FROM usuarios u
         LEFT JOIN participantes p ON p.usuario_id = u.id'
    )->fetchAll();

    $update = $pdo->prepare('UPDATE usuarios SET apelido = :apelido WHERE id = :id');
    $used = [];

    foreach ($rows as $row) {
        $nickname = trim((string) ($row['nickname'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));
        if (preg_match('/^[A-Za-z0-9]{3,20}$/', $nickname) === 1) {
            $candidate = $nickname;
        } elseif (preg_match('/^[A-Za-z0-9]{3,20}$/', $email) === 1) {
            $candidate = $email;
        } else {
            $candidate = 'usuario' . $row['id'];
        }

        $base = $candidate;
        $suffix = 2;
        while (isset($used[strtolower($candidate)])) {
            $candidate = substr($base, 0, 18) . $suffix;
            $suffix++;
        }
        $used[strtolower($candidate)] = true;
        $update->execute([
            ':apelido' => $candidate,
            ':id' => $row['id'],
        ]);
    }
}

function rebuildSqliteFile(string $path): void
{
    if (is_file($path)) {
        copy($path, $path . '.bak');
    }

    foreach ([$path, $path . '-journal', $path . '-wal', $path . '-shm'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}
