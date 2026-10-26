<?php

declare(strict_types=1);

final class User
{
    public static function findActiveById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT u.id,
                    u.apelido AS username,
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
                    u.apelido AS username,
                    u.email,
                    u.ativo AS active,
                    CASE WHEN a.usuario_id IS NOT NULL THEN \'admin\' ELSE \'member\' END AS role
             FROM usuarios u
             LEFT JOIN admins a ON a.usuario_id = u.id
             ORDER BY u.apelido COLLATE NOCASE'
        );

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT u.id,
                    u.apelido AS username,
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
             WHERE apelido = :apelido COLLATE NOCASE
             LIMIT 2'
        );
        $stmt->execute(['apelido' => $login]);
        $rows = $stmt->fetchAll();

        return count($rows) === 1 ? $rows[0] : null;
    }

    public static function emailExists(string $email): bool
    {
        $stmt = db()->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        return (bool) $stmt->fetch();
    }

    public static function usernameExists(string $username): bool
    {
        $stmt = db()->prepare('SELECT id FROM usuarios WHERE apelido = :apelido LIMIT 1');
        $stmt->execute(['apelido' => $username]);

        return (bool) $stmt->fetch();
    }

    public static function create(string $username, string $email, string $password, string $role = 'member'): int
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (apelido, email, senha_hash, ativo)
                 VALUES (:apelido, :email, :senha_hash, 1)'
            );
            $stmt->execute([
                'apelido' => $username,
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
            $participante->execute(['id' => $id, 'apelido' => $username]);

            $pdo->commit();

            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return list<string> */
    public static function register(string $username, string $email, string $password): array
    {
        $errors = [];

        $username = trim($username);
        $email = strtolower(trim($email));
    
        if ($name === '' || mb_strlen($name) < 3 || str_contains($name, "")) {
            $errors[] = 'O apelido deve conter 3 caracteres e não possuir espaços';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'O e-mail informado deve estar em um formato válido';
        }
        
        if (mb_strlen($password) < 8 || // mínimo de 8 caracteres
            !preg_match('/[^a-zA-Z0-9]/', $password) || // verifica se NÃO tem caractere especial
            !preg_match('/(?=.*\p{Ll})(?=.*\p{Lu})/u', $password)) { // verifica se tem pelo menos UMA maiúscula e UMA minúscula
                $errors[] = 'A senha deve ter pelo menos 8 caracteres, incluindo um caractere especial, letras maiúsculas e minúsculas';
        }
         
        if ($errors !== []) {
            return $errors;
        }

        if (self::emailExists($email)) {
            return ['Este e-mail já está cadastrado. Faça login para acessar nossa plataforma!'];
        }

        if (self::usernameExists($username)) {
            return ['']; // verificar como retornará e como tratará esse retorno
        }

        $id = self::create($username, $email, $password);
        AuthService::login($id);

        return [];
    }
}
