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
        rebuildSqliteFile($path);
    }

    $pdo = openSqlite($path);
    $pdo->exec($schemaSql);
    saveSchemaFingerprint($pdo, $fingerprint);

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
