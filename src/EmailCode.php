<?php

declare(strict_types=1);

/** Códigos de uso único para confirmar e-mail e criar uma senha nova. */
final class EmailCode
{
    public const VERIFICACAO_MINUTOS = 1440;
    public const SENHA_MINUTOS = 30;
    private const MAX_TENTATIVAS = 5;

    public static function tooSoon(int $userId, string $finalidade): bool
    {
        $stmt = db()->prepare(
            "SELECT 1
             FROM codigos_email
             WHERE usuario_id = :usuario_id
               AND finalidade = :finalidade
               AND usado_em IS NULL
               AND criado_em >= datetime('now', '-60 seconds')
             LIMIT 1"
        );
        $stmt->execute([
            'usuario_id' => $userId,
            'finalidade' => $finalidade,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public static function issue(int $userId, string $finalidade): string
    {
        $plain = $finalidade === 'redefinir_senha'
            ? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)
            : bin2hex(random_bytes(32));
        $minutes = $finalidade === 'redefinir_senha' ? self::SENHA_MINUTOS : self::VERIFICACAO_MINUTOS;

        $pdo = db();
        $invalidate = $pdo->prepare(
            "UPDATE codigos_email
             SET usado_em = datetime('now')
             WHERE usuario_id = :usuario_id
               AND finalidade = :finalidade
               AND usado_em IS NULL"
        );
        $invalidate->execute([
            'usuario_id' => $userId,
            'finalidade' => $finalidade,
        ]);

        $insert = $pdo->prepare(
            "INSERT INTO codigos_email (usuario_id, finalidade, codigo_hash, expira_em)
             VALUES (:usuario_id, :finalidade, :codigo_hash, datetime('now', :modificador))"
        );
        $insert->execute([
            'usuario_id' => $userId,
            'finalidade' => $finalidade,
            'codigo_hash' => hash('sha256', $plain),
            'modificador' => '+' . $minutes . ' minutes',
        ]);

        return $plain;
    }

    public static function invalidate(int $userId, string $finalidade): void
    {
        $stmt = db()->prepare(
            "UPDATE codigos_email
             SET usado_em = datetime('now')
             WHERE usuario_id = :usuario_id
               AND finalidade = :finalidade
               AND usado_em IS NULL"
        );
        $stmt->execute([
            'usuario_id' => $userId,
            'finalidade' => $finalidade,
        ]);
    }

    /** @return 'ok'|'invalid' */
    public static function consumePasswordCode(int $userId, string $code): string
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        $stmt = db()->prepare(
            "SELECT id, codigo_hash, tentativas
             FROM codigos_email
             WHERE usuario_id = :usuario_id
               AND finalidade = 'redefinir_senha'
               AND usado_em IS NULL
               AND expira_em > datetime('now')
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute(['usuario_id' => $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return 'invalid';
        }

        if (!hash_equals((string) $row['codigo_hash'], hash('sha256', $code))) {
            $tentativas = (int) $row['tentativas'] + 1;
            $update = db()->prepare(
                "UPDATE codigos_email
                 SET tentativas = :tentativas,
                     usado_em = CASE WHEN :esgotou = 1 THEN datetime('now') ELSE usado_em END
                 WHERE id = :id"
            );
            $update->execute([
                'tentativas' => $tentativas,
                'esgotou' => $tentativas >= self::MAX_TENTATIVAS ? 1 : 0,
                'id' => (int) $row['id'],
            ]);

            return 'invalid';
        }

        self::markUsed((int) $row['id']);

        return 'ok';
    }

    /** @return 'ok'|'already'|'invalid' */
    public static function consumeVerificationToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return 'invalid';
        }

        $stmt = db()->prepare(
            "SELECT c.id, c.usuario_id, u.email_verificado, u.ativo
             FROM codigos_email c
             INNER JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.finalidade = 'verificar_email'
               AND c.codigo_hash = :codigo_hash
               AND c.usado_em IS NULL
               AND c.expira_em > datetime('now')
             LIMIT 1"
        );
        $stmt->execute(['codigo_hash' => hash('sha256', $token)]);
        $row = $stmt->fetch();
        if (!$row) {
            $usado = db()->prepare(
                "SELECT u.email_verificado, u.ativo
                 FROM codigos_email c
                 INNER JOIN usuarios u ON u.id = c.usuario_id
                 WHERE c.finalidade = 'verificar_email'
                   AND c.codigo_hash = :codigo_hash
                   AND c.usado_em IS NOT NULL
                 LIMIT 1"
            );
            $usado->execute(['codigo_hash' => hash('sha256', $token)]);
            $anterior = $usado->fetch();
            if ($anterior && (int) $anterior['ativo'] === 1 && (int) $anterior['email_verificado'] === 1) {
                return 'already';
            }

            return 'invalid';
        }
        if (!(int) $row['ativo']) {
            return 'invalid';
        }

        self::markUsed((int) $row['id']);
        if ((int) $row['email_verificado'] === 1) {
            return 'already';
        }

        User::markEmailVerified((int) $row['usuario_id']);

        return 'ok';
    }

    private static function markUsed(int $id): void
    {
        $stmt = db()->prepare("UPDATE codigos_email SET usado_em = datetime('now') WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }
}
