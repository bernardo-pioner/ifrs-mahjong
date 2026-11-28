<?php

declare(strict_types=1);

/**
 * Envia e-mail pelo driver configurado em config.php (log, smtp ou mail).
 * O corpo não fica no banco: a tabela emails guarda só destinatário, assunto e resultado.
 */
final class Mailer
{
    /** @var list<array{to: string, subject: string, text: string}> */
    private static array $devLog = [];

    public static function usesLog(): bool
    {
        return self::driver() === 'log';
    }

    /** @return list<array{to: string, subject: string, text: string}> */
    public static function devMessages(): array
    {
        return self::$devLog;
    }

    public static function send(
        string $to,
        string $subject,
        string $text,
        ?string $link = null,
        ?string $linkLabel = null,
        string $tipo = 'aviso',
        ?int $usuarioId = null
    ): bool {
        $to = trim($to);
        $subject = trim(str_replace(["\r", "\n"], '', $subject));
        $text = trim($text);
        if ($link !== null && $link !== '') {
            $text .= "\n\n" . ($linkLabel !== null && $linkLabel !== '' ? $linkLabel . ': ' : '') . $link;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || $subject === '' || $text === '') {
            self::record($usuarioId, $to, $subject !== '' ? $subject : '(sem assunto)', $tipo, false, 'Destinatário ou mensagem inválidos.');

            return false;
        }

        $html = self::html($text);
        $error = null;

        try {
            match (self::driver()) {
                'smtp' => self::sendSmtp($to, $subject, $text, $html),
                'mail' => self::sendPhpMail($to, $subject, $html),
                default => self::sendLog($to, $subject, $text, $html),
            };
            $ok = true;
        } catch (Throwable $exception) {
            $ok = false;
            $error = $exception->getMessage();
        }

        self::record($usuarioId, $to, $subject, $tipo, $ok, $error);

        return $ok;
    }

    private static function driver(): string
    {
        $mail = config('mail');
        $driver = is_array($mail) ? (string) ($mail['driver'] ?? 'log') : 'log';

        return in_array($driver, ['log', 'smtp', 'mail'], true) ? $driver : 'log';
    }

    /** @return array<string, mixed> */
    private static function settings(): array
    {
        $mail = config('mail');

        return is_array($mail) ? $mail : [];
    }

    private static function sendLog(string $to, string $subject, string $text, string $html): void
    {
        $directory = ROOT_PATH . '/data/emails';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível guardar o e-mail local.');
        }

        $stamp = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $path = $directory . '/' . $stamp . '.html';
        $payload = "Para: {$to}\nAssunto: {$subject}\n\n{$text}";
        if (file_put_contents($path, $html) === false) {
            throw new RuntimeException('Não foi possível guardar o e-mail local.');
        }
        file_put_contents($directory . '/' . $stamp . '.txt', $payload);

        self::$devLog[] = [
            'to' => $to,
            'subject' => $subject,
            'text' => $text,
        ];
    }

    private static function sendPhpMail(string $to, string $subject, string $html): void
    {
        $from = self::fromHeader();
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from,
        ];
        if (!mail($to, $subject, $html, implode("\r\n", $headers))) {
            throw new RuntimeException('O servidor recusou o e-mail.');
        }
    }

    private static function sendSmtp(string $to, string $subject, string $text, string $html): void
    {
        $settings = self::settings();
        $host = trim((string) ($settings['smtp_host'] ?? ''));
        if ($host === '') {
            throw new RuntimeException('Servidor SMTP não configurado.');
        }

        $client = new SmtpClient(
            $host,
            (int) ($settings['smtp_port'] ?? 587),
            (string) ($settings['smtp_username'] ?? ''),
            (string) ($settings['smtp_password'] ?? ''),
            (string) ($settings['smtp_encryption'] ?? 'tls')
        );
        $client->send(self::fromAddress(), self::fromName(), $to, $subject, self::mime($text, $html));
    }

    private static function fromAddress(): string
    {
        $address = trim((string) (self::settings()['from_address'] ?? ''));
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return 'nao-responda@localhost';
        }

        return $address;
    }

    private static function fromName(): string
    {
        $name = trim(str_replace(["\r", "\n"], '', (string) (self::settings()['from_name'] ?? 'Clube de Mahjong')));

        return $name !== '' ? $name : 'Clube de Mahjong';
    }

    private static function fromHeader(): string
    {
        return self::encodeHeader(self::fromName()) . ' <' . self::fromAddress() . '>';
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function html(string $text): string
    {
        $body = '';
        foreach (preg_split("/\n{2,}/", trim($text)) ?: [] as $block) {
            $body .= '<p style="margin:0 0 12px;line-height:1.5">'
                . nl2br(htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
                . '</p>';
        }

        return '<!DOCTYPE html><html lang="pt-BR"><body style="font-family:Georgia,serif;color:#1c2430">'
            . $body
            . '<p style="color:#667;font-size:12px">Clube de Mahjong</p></body></html>';
    }

    private static function mime(string $text, string $html): string
    {
        $boundary = 'mahjong_' . bin2hex(random_bytes(8));

        return implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            '',
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($text)),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($html)),
            '--' . $boundary . '--',
            '',
        ]);
    }

    private static function record(
        ?int $usuarioId,
        string $to,
        string $subject,
        string $tipo,
        bool $ok,
        ?string $error
    ): void {
        try {
            $stmt = db()->prepare(
                'INSERT INTO emails (usuario_id, destinatario, assunto, tipo, status, erro)
                 VALUES (:usuario_id, :destinatario, :assunto, :tipo, :status, :erro)'
            );
            $stmt->execute([
                'usuario_id' => $usuarioId,
                'destinatario' => $to,
                'assunto' => $subject,
                'tipo' => $tipo,
                'status' => $ok ? 'enviado' : 'falhou',
                'erro' => $error,
            ]);
        } catch (Throwable) {
        }
    }
}

final class SmtpClient
{
    public function __construct(
        private string $host,
        private int $port,
        private string $username,
        private string $password,
        private string $encryption
    ) {
    }

    public function send(string $from, string $fromName, string $to, string $subject, string $mimeHeadAndBody): void
    {
        $encryption = in_array($this->encryption, ['tls', 'ssl', 'none'], true) ? $this->encryption : 'tls';
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $socket = stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]));
        if ($socket === false) {
            throw new RuntimeException('Não foi possível conectar ao servidor de e-mail.');
        }

        stream_set_timeout($socket, 15);
        $this->expect($socket, [220]);
        $this->command($socket, 'EHLO clube-mahjong', [250]);

        if ($encryption === 'tls') {
            $this->command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Não foi possível iniciar a conexão segura.');
            }
            $this->command($socket, 'EHLO clube-mahjong', [250]);
        }

        if ($this->username !== '') {
            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($this->username), [334]);
            $this->command($socket, base64_encode($this->password), [235]);
        }

        $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
        $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        $this->command($socket, 'DATA', [354]);

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $message = 'Date: ' . gmdate('D, d M Y H:i:s') . " +0000\r\n"
            . 'From: ' . $encodedName . ' <' . $from . ">\r\n"
            . 'To: ' . $to . "\r\n"
            . 'Subject: ' . $encodedSubject . "\r\n"
            . $mimeHeadAndBody;
        $message = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $message)) ?? $message;
        $message = str_replace("\n", "\r\n", $message);
        fwrite($socket, $message . "\r\n.\r\n");
        $this->expect($socket, [250]);
        $this->command($socket, 'QUIT', [221]);
        fclose($socket);
    }

    /** @param resource $socket */
    private function command($socket, string $command, array $ok): string
    {
        fwrite($socket, $command . "\r\n");

        return $this->expect($socket, $ok);
    }

    /** @param resource $socket @param list<int> $ok */
    private function expect($socket, array $ok): string
    {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($data, 0, 3);
        if ($data === '' || !in_array($code, $ok, true)) {
            throw new RuntimeException('O servidor de e-mail recusou a mensagem.');
        }

        return $data;
    }
}
