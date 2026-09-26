<?php

declare(strict_types=1);

final class User
{
    public static function findActiveById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT u.id,
                    u.nome AS name,
                    u.email,
                    u.ativo AS active,
                    CASE WHEN a.usuario_id IS NOT NULL THEN \'admin\' ELSE \'member\' END AS role
             FROM usuarios u
             LEFT JOIN admins a ON a.usuario_id = u.id
             WHERE u.id = :id AND u.ativo = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        $stmt = db()->query(
            'SELECT u.id,
                    u.nome AS name,
                    u.email,
                    u.ativo AS active,
                    CASE WHEN a.usuario_id IS NOT NULL THEN \'admin\' ELSE \'member\' END AS role
             FROM usuarios u
             LEFT JOIN admins a ON a.usuario_id = u.id
             ORDER BY u.nome COLLATE NOCASE'
        );

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT u.id,
                    u.nome AS name,
                    u.email,
                    u.ativo AS active,
                    CASE WHEN a.usuario_id IS NOT NULL THEN \'admin\' ELSE \'member\' END AS role
             FROM usuarios u
             LEFT JOIN admins a ON a.usuario_id = u.id
             WHERE u.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }

        $stmt = db()->prepare(
            'SELECT id, senha_hash AS password_hash, ativo AS active
             FROM usuarios
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByLogin(string $login): ?array
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        $byEmail = self::findByEmail($login);
        if ($byEmail !== null) {
            return $byEmail;
        }

        $stmt = db()->prepare(
            'SELECT id, senha_hash AS password_hash, ativo AS active
             FROM usuarios
             WHERE nome = :nome COLLATE NOCASE
             LIMIT 2'
        );
        $stmt->execute(['nome' => $login]);
        $rows = $stmt->fetchAll();

        return count($rows) === 1 ? $rows[0] : null;
    }

    public static function emailExists(string $email): bool
    {
        $stmt = db()->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        return (bool) $stmt->fetch();
    }

    public static function create(string $name, string $email, string $password, string $role = 'member'): int
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (nome, email, senha_hash, ativo)
                 VALUES (:nome, :email, :senha_hash, 1)'
            );
            $stmt->execute([
                'nome' => $name,
                'email' => $email,
                'senha_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            $id = (int) $pdo->lastInsertId();

            if ($role === 'admin') {
                $admin = $pdo->prepare(
                    'INSERT INTO admins (usuario_id, cargo) VALUES (:id, :cargo)'
                );
                $admin->execute(['id' => $id, 'cargo' => 'administrador']);
            }

            $participante = $pdo->prepare(
                'INSERT INTO participantes (usuario_id, apelido) VALUES (:id, :apelido)'
            );
            $participante->execute(['id' => $id, 'apelido' => $name]);

            $pdo->commit();

            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return list<string> */
    public static function register(string $name, string $email, string $password): array
    {
        $errors = [];

        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === '' || mb_strlen($name) < 2) {
            $errors[] = 'Informe um nome com pelo menos 2 caracteres.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail válido.';
        }

        if (mb_strlen($password) < 8) {
            $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
        }

        if ($errors !== []) {
            return $errors;
        }

        if (self::emailExists($email)) {
            return ['Este e-mail já está cadastrado.'];
        }

        $id = self::create($name, $email, $password);
        AuthService::login($id);

        return [];
    }
}
