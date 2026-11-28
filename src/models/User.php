<?php

declare(strict_types=1);

final class User
{
    public static function findActiveById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT u.id,
                    u.apelido AS username,
                    u.nome AS name,
                    u.email,
                    u.ativo AS active,
                    u.emails_notificacao AS notify_emails,
                    u.criado_em AS created_at,
                    p.apelido AS nickname,
                    p.bio,
                    p.mahjong_soul,
                    p.riichi_city,
                    p.discord,
                    p.foto AS photo,
                    CASE a.cargo WHEN \'administrador\' THEN \'admin\' WHEN \'moderador\' THEN \'moderator\' ELSE \'member\' END AS role
             FROM usuarios u
             LEFT JOIN admins a ON a.usuario_id = u.id
             LEFT JOIN participantes p ON p.usuario_id = u.id
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
                    u.nome AS name,
                    u.email,
                    u.ativo AS active,
                    u.email_verificado AS email_verified,
                    u.criado_em AS created_at,
                    p.apelido AS nickname,
                    p.bio,
                    p.mahjong_soul,
                    p.riichi_city,
                    p.discord,
                    p.foto AS photo,
                    p.nivel AS level,
                    CASE a.cargo WHEN \'administrador\' THEN \'admin\' WHEN \'moderador\' THEN \'moderator\' ELSE \'member\' END AS role
             FROM usuarios u
             LEFT JOIN admins a ON a.usuario_id = u.id
             LEFT JOIN participantes p ON p.usuario_id = u.id
             ORDER BY u.apelido COLLATE NOCASE'
        );

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT u.id,
                    u.apelido AS username,
                    u.nome AS name,
                    u.email,
                    u.ativo AS active,
                    u.emails_notificacao AS notify_emails,
                    u.criado_em AS created_at,
                    p.apelido AS nickname,
                    p.bio,
                    p.mahjong_soul,
                    p.riichi_city,
                    p.discord,
                    p.foto AS photo,
                    p.nivel AS level,
                    CASE a.cargo WHEN \'administrador\' THEN \'admin\' WHEN \'moderador\' THEN \'moderator\' ELSE \'member\' END AS role
             FROM usuarios u
             LEFT JOIN admins a ON a.usuario_id = u.id
             LEFT JOIN participantes p ON p.usuario_id = u.id
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
            'SELECT id, senha_hash AS password_hash, ativo AS active,
                    email_verificado AS email_verified
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
            'SELECT id, senha_hash AS password_hash, ativo AS active,
                    email_verificado AS email_verified
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
        $stmt = db()->prepare(
            'SELECT id FROM usuarios WHERE apelido = :apelido COLLATE NOCASE LIMIT 1'
        );
        $stmt->execute(['apelido' => $username]);

        return (bool) $stmt->fetch();
    }

    public static function usernameExistsForOtherUser(string $username, int $id): bool
    {
        $stmt = db()->prepare(
            'SELECT id
             FROM usuarios
             WHERE apelido = :apelido COLLATE NOCASE
               AND id <> :id
             LIMIT 1'
        );
        $stmt->execute([
            'apelido' => $username,
            'id' => $id,
        ]);

        return (bool) $stmt->fetch();
    }

    public static function create(
        string $username,
        string $email,
        string $password,
        string $role = 'member',
        bool $notifyEmails = true
    ): int
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (apelido, email, senha_hash, ativo, email_verificado, emails_notificacao)
                 VALUES (:apelido, :email, :senha_hash, 1, 0, :emails)'
            );
            $stmt->execute([
                'apelido' => $username,
                'email' => $email,
                'senha_hash' => password_hash($password, PASSWORD_DEFAULT),
                'emails' => $notifyEmails ? 1 : 0,
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

    /** @return true|'mail'|false */
    public static function register(
        string $username,
        string $email,
        string $password,
        bool $notifyEmails = true
    ): bool|string
    {
        $username = trim($username);
        $email = strtolower(trim($email));

        if (!preg_match('/^[a-zA-Z0-9]{3,12}$/', $username)) {
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (mb_strlen($password) < 8 || !preg_match('/\d/', $password)) {
            return false;
        }

        if (self::emailExists($email)) {
            return false;
        }

        if (self::usernameExists($username)) {
            return false;
        }

        $id = self::create($username, $email, $password, 'member', $notifyEmails);
        $token = EmailCode::issue($id, 'verificar_email');
        $link = appBaseUrl() . '/confirmar-email?token=' . rawurlencode($token);
        if (!EmailService::sendEmailConfirmationLink($email, $username, $link)) {
            EmailCode::invalidate($id, 'verificar_email');
            self::deleteAccount($id);

            return 'mail';
        }

        Notification::publishAdmins();

        return true;
    }

    public static function updatePassword(int $id, string $password): bool
    {
        if (mb_strlen($password) < 8 || mb_strlen($password) > 128 || preg_match('/\d/', $password) !== 1) {
            return false;
        }

        $stmt = db()->prepare(
            "UPDATE usuarios
             SET senha_hash = :senha_hash, atualizado_em = datetime('now')
             WHERE id = :id AND ativo = 1"
        );
        $stmt->execute([
            'senha_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $id,
        ]);

        return $stmt->rowCount() === 1;
    }

    public static function markEmailVerified(int $id): void
    {
        $stmt = db()->prepare(
            "UPDATE usuarios
             SET email_verificado = 1, atualizado_em = datetime('now')
             WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    private static function deleteAccount(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @return list<string>
     */
    public static function bioErrors(
        string $nickname,
        string $realName,
        string $bio,
        ?int $userId = null,
        string $mahjongSoul = '',
        string $riichiCity = '',
        string $discord = ''
    ): array {
        $errors = [];
        $nickname = trim($nickname);
        $realName = trim($realName);
        $bio = trim($bio);
        $mahjongSoul = trim($mahjongSoul);
        $riichiCity = trim($riichiCity);
        $discord = trim($discord);

        if (!preg_match('/^[a-zA-Z0-9]{3,12}$/', $nickname)) {
            $errors[] = 'O apelido deve ter de 3 a 12 caracteres, apenas letras e números.';
        } elseif (
            $userId !== null
            && self::usernameExistsForOtherUser($nickname, $userId)
        ) {
            $errors[] = 'Este apelido já está em uso.';
        }

        $realNameLength = mb_strlen($realName);
        if ($realName !== '' && ($realNameLength < 2 || $realNameLength > 20)) {
            $errors[] = 'O nome deve ter de 2 a 20 caracteres.';
        }

        if (mb_strlen($bio) > 500) {
            $errors[] = 'A bio deve ter no máximo 500 caracteres.';
        }

        foreach (
            [
                'ID/Nome Mahjong Soul' => $mahjongSoul,
                'ID/Nome Riichi City' => $riichiCity,
                'Discord' => $discord,
            ] as $label => $value
        ) {
            if (mb_strlen($value) > 40) {
                $errors[] = $label . ' deve ter no máximo 40 caracteres.';
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    public static function updateBio(
        int $id,
        string $nickname,
        string $realName,
        string $bio,
        ?string $photo,
        string $mahjongSoul,
        string $riichiCity,
        string $discord,
        bool $notifyEmails
    ): array {
        $nickname = trim($nickname);
        $realName = trim($realName);
        $bio = trim($bio);
        $mahjongSoul = trim($mahjongSoul);
        $riichiCity = trim($riichiCity);
        $discord = trim($discord);
        $errors = self::bioErrors($nickname, $realName, $bio, $id, $mahjongSoul, $riichiCity, $discord);

        if ($errors !== []) {
            return $errors;
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $userStmt = $pdo->prepare(
                'UPDATE usuarios
                 SET apelido = :apelido,
                     nome = :nome,
                     emails_notificacao = :emails,
                     atualizado_em = datetime(\'now\')
                 WHERE id = :id'
            );
            $userStmt->execute([
                'apelido' => $nickname,
                'nome' => $realName !== '' ? $realName : null,
                'emails' => $notifyEmails ? 1 : 0,
                'id' => $id,
            ]);

            $memberStmt = $pdo->prepare(
                'INSERT INTO participantes (usuario_id, apelido, bio, foto, mahjong_soul, riichi_city, discord)
                 VALUES (:id, :apelido, :bio, :foto, :mahjong_soul, :riichi_city, :discord)
                 ON CONFLICT(usuario_id) DO UPDATE SET
                    apelido = excluded.apelido,
                    bio = excluded.bio,
                    mahjong_soul = excluded.mahjong_soul,
                    riichi_city = excluded.riichi_city,
                    discord = excluded.discord,
                    foto = CASE
                        WHEN excluded.foto IS NOT NULL THEN excluded.foto
                        ELSE participantes.foto
                    END'
            );
            $memberStmt->execute([
                'id' => $id,
                'apelido' => $nickname,
                'bio' => $bio === '' ? null : $bio,
                'foto' => $photo,
                'mahjong_soul' => $mahjongSoul === '' ? null : $mahjongSoul,
                'riichi_city' => $riichiCity === '' ? null : $riichiCity,
                'discord' => $discord === '' ? null : $discord,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

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
        string $email,
        string $roleAction = 'keep',
        string $statusAction = 'keep',
        string $level = 'iniciante'
    ): array {
        $errors = [];

        $email = strtolower(trim($email));
        $level = trim($level);
        $niveisValidos = ['iniciante', 'intermediario', 'avancado'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail válido.';
        }

        if (!in_array($roleAction, ['keep', 'promote', 'demote'], true)) {
            $errors[] = 'Ação de permissão inválida.';
        }

        if (!in_array($statusAction, ['keep', 'activate', 'deactivate'], true)) {
            $errors[] = 'Ação de status inválida.';
        }

        if (!in_array($level, $niveisValidos, true)) {
            $errors[] = 'Selecione um nível válido.';
        }

        if ($errors !== []) {
            return $errors;
        }

        if (self::emailExistsForOtherUser($email, $id)) {
            return ['Este e-mail já está cadastrado para outro usuário.'];
        }

        $pdo = db();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT u.id, u.ativo, a.cargo
                FROM usuarios u
                LEFT JOIN admins a ON a.usuario_id = u.id
                WHERE u.id = :id'
            );
            $stmt->execute(['id' => $id]);
            $target = $stmt->fetch();

            if (!$target) {
                $pdo->rollBack();
                return ['Usuário não encontrado.'];
            }

            $cargo = (string) ($target['cargo'] ?? '');
            $isAdmin = $cargo === 'administrador';
            $isModerator = $cargo === 'moderador';
            $isActive = (int) $target['ativo'] === 1;

            if ($isAdmin && $roleAction !== 'keep') {
                $pdo->rollBack();

                return ['A permissão de administrador não pode ser alterada por esta tela.'];
            }

            $makeModerator = match ($roleAction) {
                'promote' => true,
                'demote' => false,
                default => $isModerator,
            };

            $activate = match ($statusAction) {
                'activate' => true,
                'deactivate' => false,
                default => $isActive,
            };

            if ($isAdmin && $isActive && !$activate) {
                $totalActiveAdmins = (int) $pdo->query(
                    "SELECT COUNT(*)
                    FROM usuarios u
                    INNER JOIN admins a ON a.usuario_id = u.id
                    WHERE u.ativo = 1
                      AND a.cargo = 'administrador'"
                )->fetchColumn();

                if ($totalActiveAdmins <= 1) {
                    $pdo->rollBack();

                    return [
                        'Não é possível inativar o último administrador ativo.'
                    ];
                }
            }

            // Atualiza os dados pessoais e o status na mesma transação.
            $stmt = $pdo->prepare(
                'UPDATE usuarios
                SET email = :email,
                    ativo = :ativo
                WHERE id = :id'
            );

            $stmt->execute([
                'email' => $email,
                'ativo' => $activate ? 1 : 0,
                'id' => $id,
            ]);

            if (!$isAdmin && $makeModerator !== $isModerator) {
                if ($makeModerator) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO admins (usuario_id, cargo)
                        VALUES (:id, 'moderador')"
                    );
                } else {
                    $stmt = $pdo->prepare(
                        "DELETE FROM admins
                        WHERE usuario_id = :id
                          AND cargo = 'moderador'"
                    );
                }

                $stmt->execute(['id' => $id]);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO participantes (usuario_id, nivel)
                 VALUES (:id, :nivel)
                 ON CONFLICT(usuario_id) DO UPDATE SET
                    nivel = excluded.nivel'
            );
            $stmt->execute([
                'id' => $id,
                'nivel' => $level,
            ]);

            $pdo->commit();

            return [];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
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
                "SELECT usuario_id
                FROM admins
                WHERE usuario_id = :id
                  AND cargo = 'administrador'"
            );
            $stmt->execute(['id' => $id]);
            $isAdmin = (bool) $stmt->fetch();

            // Não permite inativar o último administrador ativo.
            if ($isAdmin && !$activate) {
                $totalActiveAdmins = (int) $pdo->query(
                    "SELECT COUNT(*)
                    FROM usuarios u
                    INNER JOIN admins a ON a.usuario_id = u.id
                    WHERE u.ativo = 1
                      AND a.cargo = 'administrador'"
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

    
    public static function changeRole(
        int $id,
        int $currentUserId,
        bool $makeModerator
    ): string {
        $pdo = db();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT id
                FROM usuarios
                WHERE id = :id'
            );
            $stmt->execute(['id' => $id]);
            $targetUser = $stmt->fetch();

            if (!$targetUser) {
                $pdo->rollBack();
                return 'not_found';
            }

            if ($id === $currentUserId) {
                $pdo->rollBack();
                return 'self';
            }

            $stmt = $pdo->prepare(
                'SELECT cargo
                FROM admins
                WHERE usuario_id = :id'
            );
            $stmt->execute(['id' => $id]);
            $cargo = $stmt->fetchColumn();
            $cargo = $cargo === false ? '' : (string) $cargo;

            if ($cargo === 'administrador') {
                $pdo->rollBack();
                return 'is_admin';
            }

            $isModerator = $cargo === 'moderador';
            if ($isModerator === $makeModerator) {
                $pdo->rollBack();
                return 'unchanged';
            }

            if ($makeModerator) {
                $stmt = $pdo->prepare(
                    "INSERT INTO admins (usuario_id, cargo)
                    VALUES (:id, 'moderador')"
                );
            } else {
                $stmt = $pdo->prepare(
                    "DELETE FROM admins
                    WHERE usuario_id = :id
                      AND cargo = 'moderador'"
                );
            }

            $stmt->execute(['id' => $id]);

            $pdo->commit();

            return 'success';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if (str_contains(strtolower($e->getMessage()), 'foreign key')) {
                return 'has_events';
            }

            error_log('Erro ao alterar permissão do usuário: ' . $e->getMessage());

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
                "SELECT usuario_id FROM admins
                 WHERE usuario_id = :id AND cargo = 'administrador'"
            );
            $stmt->execute(['id' => $id]);
            $isAdmin = (bool) $stmt->fetch();

            // Impede excluir o último administrador.
            if ($isAdmin) {
                $totalAdmins = (int) $pdo
                    ->query("SELECT COUNT(*) FROM admins WHERE cargo = 'administrador'")
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
