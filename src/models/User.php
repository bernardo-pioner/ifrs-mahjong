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

    
    public static function emailExistsForOtherUser(string $email, int $id): bool
    {
        $stmt = db()->prepare(
            'SELECT id
            FROM usuarios
            WHERE email = :email AND id <> :id
            LIMIT 1'
        );

        $stmt->execute([
            'email' => $email,
            'id' => $id,
        ]);

        return (bool) $stmt->fetch();
    }

    /** @return list<string> */
    public static function updateProfile(
        int $id,
        string $name,
        string $email
    ): array {
        $errors = [];

        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === '' || mb_strlen($name) < 2) {
            $errors[] = 'Informe um nome com pelo menos 2 caracteres.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail válido.';
        }

        if ($errors !== []) {
            return $errors;
        }

        if (self::emailExistsForOtherUser($email, $id)) {
            return ['Este e-mail já está cadastrado para outro usuário.'];
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'UPDATE usuarios
                SET nome = :nome, email = :email
                WHERE id = :id'
            );

            $stmt->execute([
                'nome' => $name,
                'email' => $email,
                'id' => $id,
            ]);

            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare(
                    'SELECT id FROM usuarios WHERE id = :id'
                );
                $check->execute(['id' => $id]);

                if (!$check->fetch()) {
                    $pdo->rollBack();
                    return ['Usuário não encontrado.'];
                }
            }

            $pdo->commit();

            return [];
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Erro ao atualizar usuário: ' . $e->getMessage());

            return ['Não foi possível salvar as alterações. Tente novamente.'];
        }
    }

    
    public static function changeStatus(
        int $id,
        int $currentUserId,
        bool $activate
    ): string {
        $pdo = db();

        try {
            $pdo->beginTransaction();

            // Verifica se o usuário existe e consulta seu status.
            $stmt = $pdo->prepare(
                'SELECT id, ativo
                FROM usuarios
                WHERE id = :id'
            );
            $stmt->execute(['id' => $id]);
            $targetUser = $stmt->fetch();

            if (!$targetUser) {
                $pdo->rollBack();
                return 'not_found';
            }

            // Impede alterar o próprio status.
            if ($id === $currentUserId) {
                $pdo->rollBack();
                return 'self';
            }

            $currentStatus = (int) $targetUser['ativo'] === 1;

            // Evita executar uma alteração desnecessária.
            if ($currentStatus === $activate) {
                $pdo->rollBack();
                return 'unchanged';
            }

            // Verifica se o usuário é administrador.
            $stmt = $pdo->prepare(
                'SELECT usuario_id
                FROM admins
                WHERE usuario_id = :id'
            );
            $stmt->execute(['id' => $id]);
            $isAdmin = (bool) $stmt->fetch();

            // Não permite inativar o último administrador ativo.
            if ($isAdmin && !$activate) {
                $totalActiveAdmins = (int) $pdo->query(
                    'SELECT COUNT(*)
                    FROM usuarios u
                    INNER JOIN admins a ON a.usuario_id = u.id
                    WHERE u.ativo = 1'
                )->fetchColumn();

                if ($totalActiveAdmins <= 1) {
                    $pdo->rollBack();
                    return 'last_admin';
                }
            }

            $stmt = $pdo->prepare(
                'UPDATE usuarios
                SET ativo = :ativo
                WHERE id = :id'
            );

            $stmt->execute([
                'ativo' => $activate ? 1 : 0,
                'id' => $id,
            ]);

            $pdo->commit();

            return 'success';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Erro ao alterar status do usuário: ' . $e->getMessage());

            return 'error';
        }
    }



    
    public static function deleteById(int $id, int $currentUserId): string
    {
        $pdo = db();

        try {
            $pdo->beginTransaction();

            // Verifica se o usuário existe.
            $stmt = $pdo->prepare(
                'SELECT id FROM usuarios WHERE id = :id'
            );
            $stmt->execute(['id' => $id]);

            if (!$stmt->fetch()) {
                $pdo->rollBack();
                return 'not_found';
            }

            // Impede a exclusão da própria conta.
            if ($id === $currentUserId) {
                $pdo->rollBack();
                return 'self';
            }

            // Verifica se o usuário é administrador.
            $stmt = $pdo->prepare(
                'SELECT usuario_id FROM admins WHERE usuario_id = :id'
            );
            $stmt->execute(['id' => $id]);
            $isAdmin = (bool) $stmt->fetch();

            // Impede excluir o último administrador.
            if ($isAdmin) {
                $totalAdmins = (int) $pdo
                    ->query('SELECT COUNT(*) FROM admins')
                    ->fetchColumn();

                if ($totalAdmins <= 1) {
                    $pdo->rollBack();
                    return 'last_admin';
                }
            }

            // As chaves estrangeiras impedem excluir usuários
            // que ainda possuem registros dependentes.
            $stmt = $pdo->prepare(
                'DELETE FROM usuarios WHERE id = :id'
            );
            $stmt->execute(['id' => $id]);

            $pdo->commit();

            return 'success';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Ex.: usuário relacionado a eventos, partidas,
            // inscrições, pagamentos ou rankings.
            if (str_contains(strtolower($e->getMessage()), 'foreign key')) {
                return 'related_records';
            }

            error_log('Erro ao excluir usuário: ' . $e->getMessage());
            return 'error';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Erro ao excluir usuário: ' . $e->getMessage());
            return 'error';
        }
    }





}
