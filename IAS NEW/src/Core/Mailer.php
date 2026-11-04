<?php

namespace App\Core;

/**
 * Sends plain-text e-mail over SMTP (for example Gmail) using PHP's own TLS sockets.
 * The connection must be encrypted and the server's certificate is verified, unless
 * encryption is explicitly set to 'none' (only used by the local test mail server).
 */
final class Mailer
{
    private const TIMEOUT = 15;

    /** @var resource|null */
    private $socket = null;

    public static function isConfigured(): bool
    {
        return Config::get('mail.host') !== '' && Config::get('mail.from') !== '';
    }

    public static function send(string $to, string $subject, string $body): bool
    {
        if (!self::isConfigured() || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        try {
            (new self())->deliver($to, $subject, $body);
            return true;
        } catch (\RuntimeException $e) {
            // The message holds SMTP replies only, never the password.
            error_log('Mail delivery failed: ' . $e->getMessage());
            return false;
        }
    }

    private function deliver(string $to, string $subject, string $body): void
    {
        $c = Config::get('mail');
        $from = (string) $c['from'];
        $this->connect($c);

        try {
            $this->expect(220);
            $this->command('EHLO localhost', 250);

            if ($c['encryption'] === 'tls') {
                $this->command('STARTTLS', 220);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new \RuntimeException('TLS could not be started');
                }
                $this->command('EHLO localhost', 250);
            }
            if ($c['username'] !== '') {
                $this->command('AUTH LOGIN', 334);
                $this->command(base64_encode((string) $c['username']), 334);
                $this->command(base64_encode((string) $c['password']), 235, 'password');
            }

            $this->command('MAIL FROM:<' . $from . '>', 250);
            $this->command('RCPT TO:<' . $to . '>', 250);
            $this->command('DATA', 354);
            $this->write($this->message($from, (string) $c['from_name'], $to, $subject, $body) . "\r\n.");
            $this->expect(250);
            $this->command('QUIT', 221);
        } finally {
            fclose($this->socket);
        }
    }

    private function connect(array $c): void
    {
        $scheme = $c['encryption'] === 'ssl' ? 'ssl' : 'tcp';
        $context = stream_context_create(['ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
            'peer_name'         => $c['host'],
        ]]);
        $this->socket = @stream_socket_client("{$scheme}://{$c['host']}:{$c['port']}", $errno, $error, self::TIMEOUT, STREAM_CLIENT_CONNECT, $context);
        if (!$this->socket) {
            throw new \RuntimeException("Could not connect to {$c['host']}:{$c['port']}: {$error}");
        }
        stream_set_timeout($this->socket, self::TIMEOUT);
    }

    private function message(string $from, string $fromName, string $to, string $subject, string $body): string
    {
        // Line breaks are removed from header values, so input can never add extra headers.
        $clean = static fn (string $value): string => str_replace(["\r", "\n"], '', $value);
        $domain = substr(strrchr($from, '@') ?: '@localhost', 1);

        $headers = [
            'From: ' . mb_encode_mimeheader($clean($fromName), 'UTF-8') . ' <' . $clean($from) . '>',
            'To: <' . $clean($to) . '>',
            'Subject: ' . mb_encode_mimeheader($clean($subject), 'UTF-8'),
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $text = str_replace(["\r\n", "\r"], "\n", $body);
        // SMTP ends a message with a line holding one dot, so a leading dot in the text is doubled.
        $text = preg_replace('/^\./m', '..', $text);

        return implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $text);
    }

    private function command(string $line, int $expected, string $label = ''): void
    {
        $this->write($line);
        $this->expect($expected, $label);
    }

    private function write(string $line): void
    {
        if (fwrite($this->socket, $line . "\r\n") === false) {
            throw new \RuntimeException('Could not write to the mail server');
        }
    }

    private function expect(int $code, string $label = ''): void
    {
        $reply = '';
        do {
            $line = fgets($this->socket, 1024);
            if ($line === false) {
                throw new \RuntimeException('The mail server closed the connection or timed out');
            }
            $reply .= $line;
        } while (isset($line[3]) && $line[3] === '-');   // "250-..." means more lines follow

        if ((int) substr($reply, 0, 3) !== $code) {
            throw new \RuntimeException("Expected {$code}" . ($label !== '' ? " after {$label}" : '') . ', got: ' . trim($line));
        }
    }
}
