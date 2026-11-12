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

    if (!isset($eventos['inscricoes_abertas'])) {
        $pdo->exec('ALTER TABLE eventos ADD COLUMN inscricoes_abertas INTEGER NOT NULL DEFAULT 1');
        $changed = true;
    }

    $usuarios = tableColumns($pdo, 'usuarios');
    if ($usuarios !== null && !isset($usuarios['apelido'])) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN apelido TEXT');
        backfillUsuarioApelidos($pdo);
        $changed = true;
    }

    if ($usuarios !== null && usuarioNomeObrigatorio($pdo)) {
        tornarNomeOpcional($pdo);
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

    return $changed || true;
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
