<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Pressupõe que o Composer já foi carregado
// pelo bootstrap da aplicação.

final class EmailService
{
    public static function emailExists(string $email): bool
    {
        $stmt = db()->prepare(
            'SELECT id FROM usuarios WHERE email = :email LIMIT 1'
        );

        $stmt->execute(['email' => $email]);

        return (bool) $stmt->fetch();
    }

    public static function sendTestEmail(
        string $email,
        string $name
    ): bool {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;

            $mail->Username = 'bepioner16@gmail.com'; //getenv('SMTP_USERNAME');
            $mail->Password = 'qtggwsuvcthranwq'; //getenv('SMTP_PASSWORD');

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            // Use o endereço autenticado como remetente.
            $mail->setFrom(
                'bepioner16@gmail.com' , //getenv('SMTP_USERNAME');
                'Minha Plataforma'
            );

            $mail->addAddress($email, $name);

            $mail->isHTML(true);
            $mail->Subject = 'Teste de envio de e-mail';
            $mail->Body = '<h1>Olá!</h1><p>O envio funcionou.</p>';
            $mail->AltBody = 'Olá! O envio de e-mail funcionou.';

            $mail->send();

            return true;
        } catch (Exception $e) {
            error_log('Falha no envio de e-mail: ' . $mail->ErrorInfo);

            return false;
        }
    }
}
