<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

final class EmailService
{
    public static function emailExists(string $email): bool
    {
        return User::emailExists($email);
    }

    public static function sendEmailConfirmationLink(string $email, string $name, string $link): bool
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return self::send(
            $email,
            $name,
            'Confirme seu e-mail',
            '<p>Olá, ' . $safeName . '.</p>'
                . '<p>Confirme seu e-mail para entrar no clube. O link vale por 24 horas.</p>'
                . '<p><a href="' . $safeLink . '">Confirmar e-mail</a></p>'
                . '<p>Se você não criou esta conta, ignore esta mensagem.</p>',
            "Olá, {$name}.\n\nConfirme seu e-mail para entrar no clube. O link vale por 24 horas.\n\n{$link}\n\nSe você não criou esta conta, ignore esta mensagem."
        );
    }

    public static function sendPasswordResetPin(string $email, string $name, string $pin): bool
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safePin = htmlspecialchars($pin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return self::send(
            $email,
            $name,
            'PIN para criar uma nova senha',
            '<p>Olá, ' . $safeName . '.</p>'
                . '<p>Seu PIN para criar uma nova senha é:</p>'
                . '<p style="font-size:24px;letter-spacing:0.28em;font-weight:700;">' . $safePin . '</p>'
                . '<p>Ele vale por 30 minutos. Se você não pediu esta troca, ignore esta mensagem.</p>',
            "Olá, {$name}.\n\nSeu PIN para criar uma nova senha é: {$pin}\n\nEle vale por 30 minutos. Se você não pediu esta troca, ignore esta mensagem."
        );
    }

    public static function sendNotification(string $email, string $name, string $text, string $link): bool
    {
        $text = trim($text);
        $subject = $text;
        if (mb_strlen($subject) > 120) {
            $subject = mb_substr($subject, 0, 117) . '...';
        }

        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeText = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return self::send(
            $email,
            $name,
            $subject,
            '<p>Olá, ' . $safeName . '.</p><p>' . $safeText . '</p><p><a href="' . $safeLink . '">Abrir na plataforma</a></p>',
            "Olá, {$name}.\n\n{$text}\n\nAbrir na plataforma: {$link}"
        );
    }

    private static function send(string $email, string $name, string $subject, string $html, string $plain): bool
    {
        if (!class_exists(PHPMailer::class)) {
            error_log('Falha no envio de e-mail: PHPMailer não está instalado.');

            return false;
        }

        $fromAddress = self::env('MAIL_FROM_ADDRESS');
        $fromName = self::env('MAIL_FROM_NAME');
        $username = self::env('SMTP_USERNAME');
        $password = self::env('SMTP_PASSWORD');
        $host = self::env('SMTP_HOST');
        $port = (int) self::env('SMTP_PORT');
        $encryption = strtolower(self::env('SMTP_ENCRYPTION'));

        if ($fromAddress === '') {
            $fromAddress = $username;
        }
        if ($fromName === '') {
            $fromName = 'Clube de Mahjong POA';
        }
        if ($host === '' || $username === '' || $password === '' || $fromAddress === '' || $port <= 0) {
            error_log('Falha no envio de e-mail: preencha o remetente do clube no arquivo .env.');

            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = $encryption === 'tls'
                ? PHPMailer::ENCRYPTION_STARTTLS
                : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $port;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $plain;
            $mail->send();

            return true;
        } catch (Exception $e) {
            error_log('Falha no envio de e-mail: ' . $mail->ErrorInfo);

            return false;
        }
    }

    private static function env(string $name): string
    {
        $value = getenv($name);

        return is_string($value) ? trim($value) : '';
    }
}
