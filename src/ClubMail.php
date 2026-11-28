<?php

declare(strict_types=1);

/**
 * Mensagens do clube.
 * Avisos novos (do admin ou de outra tela) passam por aviso() ou avisoParaVarios().
 */
final class ClubMail
{
    public static function confirmacaoCadastro(int $userId): bool
    {
        $user = User::findById($userId);
        if ($user === null || !(int) $user['active']) {
            return false;
        }

        $email = (string) $user['email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (EmailCode::tooSoon($userId, 'verificar_email')) {
            return true;
        }

        $token = EmailCode::issue($userId, 'verificar_email');
        $link = appBaseUrl() . '/confirmar-email?token=' . rawurlencode($token);
        $nome = userDisplayName($user);
        $ok = Mailer::send(
            $email,
            'Confirme seu e-mail',
            "Olá, {$nome}.\n\nConfirme seu e-mail para entrar no clube. O link vale por 24 horas.\n\nSe você não criou esta conta, ignore esta mensagem.",
            $link,
            'Confirmar e-mail',
            'verificar_email',
            $userId
        );
        if (!$ok) {
            EmailCode::invalidate($userId, 'verificar_email');
        }

        return $ok;
    }

    public static function codigoNovaSenha(int $userId): bool
    {
        $user = User::findById($userId);
        if ($user === null || !(int) $user['active']) {
            return false;
        }

        $email = (string) $user['email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (EmailCode::tooSoon($userId, 'redefinir_senha')) {
            return true;
        }

        $codigo = EmailCode::issue($userId, 'redefinir_senha');
        $nome = userDisplayName($user);
        $ok = Mailer::send(
            $email,
            'Código para criar uma nova senha',
            "Olá, {$nome}.\n\nUse este código para criar uma nova senha. Ele vale por 30 minutos:\n\n{$codigo}\n\nSe você não pediu esta troca, ignore esta mensagem.",
            null,
            null,
            'redefinir_senha',
            $userId
        );
        if (!$ok) {
            EmailCode::invalidate($userId, 'redefinir_senha');
        }

        return $ok;
    }

    /** @param array<string, mixed> $evento */
    public static function inscricaoRealizada(array $evento, int $participanteId): void
    {
        $titulo = trim((string) ($evento['titulo'] ?? 'evento'));
        $tipo = (string) ($evento['tipo'] ?? '');
        $onde = self::noEvento($tipo);
        $link = appBaseUrl() . '/eventos/' . (int) ($evento['id'] ?? 0);
        $pendente = in_array($tipo, ['torneio', 'oficina'], true);
        $texto = $pendente
            ? "Sua inscrição {$onde} {$titulo} foi registrada e aguarda aprovação."
            : "Sua inscrição {$onde} {$titulo} foi confirmada.";

        self::aviso($participanteId, 'Inscrição registrada', $texto, $link, 'Ver evento', 'inscricao');

        if (!$pendente) {
            return;
        }

        $participante = User::findById($participanteId);
        $nome = $participante !== null ? userDisplayName($participante) : 'Um participante';
        foreach (self::adminIds() as $adminId) {
            if ($adminId === $participanteId) {
                continue;
            }
            self::aviso(
                $adminId,
                'Nova inscrição',
                "{$nome} se inscreveu {$onde} {$titulo}.",
                $link,
                'Ver evento',
                'inscricao_admin'
            );
        }
    }

    /** @param array<string, mixed> $evento */
    public static function inscricaoAtualizada(array $evento, int $participanteId, string $status): void
    {
        if (!in_array($status, ['aprovado', 'cancelado'], true)) {
            return;
        }

        $titulo = trim((string) ($evento['titulo'] ?? 'evento'));
        $onde = self::noEvento((string) ($evento['tipo'] ?? ''));
        $resultado = $status === 'aprovado' ? 'aprovada' : 'recusada';
        $link = appBaseUrl() . '/eventos/' . (int) ($evento['id'] ?? 0);

        self::aviso(
            $participanteId,
            'Inscrição ' . $resultado,
            "Sua inscrição {$onde} {$titulo} foi {$resultado}.",
            $link,
            'Ver evento',
            'inscricao'
        );
    }

    /**
     * Envia um aviso para um usuário. Ponto de extensão para mensagens futuras do admin.
     */
    public static function aviso(
        int $usuarioId,
        string $assunto,
        string $texto,
        ?string $link = null,
        ?string $linkLabel = null,
        string $tipo = 'aviso'
    ): bool {
        $user = User::findById($usuarioId);
        if ($user === null || !(int) $user['active'] || !(int) $user['email_verified']) {
            return false;
        }

        $email = (string) $user['email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return Mailer::send($email, $assunto, $texto, $link, $linkLabel, $tipo, $usuarioId);
    }

    /**
     * @param list<int> $usuarioIds
     */
    public static function avisoParaVarios(
        array $usuarioIds,
        string $assunto,
        string $texto,
        ?string $link = null,
        ?string $linkLabel = null,
        string $tipo = 'aviso'
    ): void {
        foreach ($usuarioIds as $usuarioId) {
            self::aviso((int) $usuarioId, $assunto, $texto, $link, $linkLabel, $tipo);
        }
    }

    /** @return list<int> */
    private static function adminIds(): array
    {
        $rows = db()->query(
            'SELECT a.usuario_id
             FROM admins a
             INNER JOIN usuarios u ON u.id = a.usuario_id
             WHERE u.ativo = 1'
        )->fetchAll();

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['usuario_id'];
        }

        return $ids;
    }

    private static function noEvento(string $tipo): string
    {
        return match ($tipo) {
            'oficina' => 'na oficina',
            'torneio' => 'no torneio',
            'encontro' => 'no encontro',
            default => 'no evento',
        };
    }
}
