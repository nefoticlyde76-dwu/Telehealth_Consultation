<?php

namespace App\Services;

use App\Config\Environment;

class MailService
{
    /**
     * In-memory outbox used by the `array` mailer during automated tests.
     *
     * @var list<array{to:string,subject:string,html:string,text:string,headers:array<string,string>}>
     */
    public static array $outbox = [];

    private static string $lastError = '';

    public static function lastError(): string
    {
        return self::$lastError;
    }

    /**
     * Short admin-facing hint derived from the last mail failure.
     * Never includes credentials, tokens, or raw message bodies.
     */
    public static function adminFailureHint(): string
    {
        $error = strtolower(self::$lastError);
        if ($error === '') {
            return '';
        }

        if (
            str_contains($error, '535')
            || str_contains($error, 'username and password not accepted')
            || str_contains($error, 'badcredentials')
            || str_contains($error, 'authentication rejected')
        ) {
            return ' The mail server rejected the SMTP login. Update the Gmail App Password in the application environment, then try again.';
        }

        if (str_contains($error, 'mail_host is not configured')) {
            return ' Outgoing mail is not configured.';
        }

        if (str_contains($error, 'unable to connect')) {
            return ' The application could not connect to the mail server.';
        }

        return '';
    }

    public static function resetTestState(): void
    {
        self::$outbox = [];
        self::$lastError = '';
    }

    /**
     * @return array{
     *   mailer:string,
     *   host:string,
     *   port:int,
     *   username:string,
     *   password:string,
     *   encryption:string,
     *   from_address:string,
     *   from_name:string,
     *   timeout:int
     * }
     */
    public static function config(): array
    {
        $mailer = strtolower(trim((string) Environment::get('MAIL_MAILER', 'log')));
        if ($mailer === '') {
            $mailer = 'log';
        }

        $portRaw = Environment::get('MAIL_PORT', 587);
        $port = (int) $portRaw;
        if ($portRaw === '' || $portRaw === null || $port < 1) {
            $port = 587;
        }

        $encryption = strtolower(trim((string) Environment::get('MAIL_ENCRYPTION', 'tls')));
        if ($encryption === '') {
            $encryption = 'tls';
        }

        $fromAddress = trim((string) Environment::get('MAIL_FROM_ADDRESS', 'telehealth76@gmail.com'));
        if ($fromAddress === '') {
            $fromAddress = 'telehealth76@gmail.com';
        }

        $fromName = trim((string) Environment::get('MAIL_FROM_NAME', 'TeleHealth PNG'));
        if ($fromName === '') {
            $fromName = 'TeleHealth PNG';
        }

        return [
            'mailer' => $mailer,
            'host' => trim((string) Environment::get('MAIL_HOST', '')),
            'port' => $port,
            'username' => (string) Environment::get('MAIL_USERNAME', ''),
            'password' => (string) Environment::get('MAIL_PASSWORD', ''),
            'encryption' => $encryption,
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'timeout' => max(3, (int) Environment::get('MAIL_TIMEOUT', 10)),
        ];
    }

    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        self::$lastError = '';
        $to = strtolower(trim($to));
        $subject = trim($subject);
        $htmlBody = trim($htmlBody);
        $textBody = trim($textBody);

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL) || $subject === '' || $htmlBody === '') {
            self::$lastError = 'The outgoing message was incomplete.';
            return false;
        }

        if ($textBody === '') {
            $textBody = trim(html_entity_decode(strip_tags($htmlBody), ENT_QUOTES, 'UTF-8'));
        }

        $config = self::config();

        try {
            return match ($config['mailer']) {
                'array' => self::sendToOutbox($to, $subject, $htmlBody, $textBody, $config),
                'log' => self::sendToLog($to, $subject, $htmlBody, $textBody, $config),
                'fail' => self::sendFailure('Mail delivery is disabled by the fail mailer.'),
                'smtp' => self::sendViaSmtp($to, $subject, $htmlBody, $textBody, $config),
                default => self::sendFailure('The configured mailer is not supported.'),
            };
        } catch (\Throwable $exception) {
            self::$lastError = self::sanitizeError($exception->getMessage(), $config);
            error_log('Mail delivery failed: ' . self::$lastError);
            return false;
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function sendToOutbox(string $to, string $subject, string $htmlBody, string $textBody, array $config): bool
    {
        self::$outbox[] = [
            'to' => $to,
            'subject' => $subject,
            'html' => $htmlBody,
            'text' => $textBody,
            'headers' => [
                'From' => self::formatAddress((string) $config['from_address'], (string) $config['from_name']),
            ],
        ];

        return true;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function sendToLog(string $to, string $subject, string $htmlBody, string $textBody, array $config): bool
    {
        $directory = dirname(__DIR__, 2) . '/tmp/mail';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return self::sendFailure('The local mail log directory could not be created.');
        }

        $filename = $directory . '/' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.eml';
        $contents = self::buildRfc822Message($to, $subject, $htmlBody, $textBody, $config);
        if (file_put_contents($filename, $contents) === false) {
            return self::sendFailure('The local mail log could not be written.');
        }

        return true;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function sendViaSmtp(string $to, string $subject, string $htmlBody, string $textBody, array $config): bool
    {
        $host = trim((string) $config['host']);
        if ($host === '') {
            return self::sendFailure('MAIL_HOST is not configured.');
        }

        $encryption = (string) $config['encryption'];
        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . (int) $config['port'];
        $timeout = (int) $config['timeout'];
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);

        if (!is_resource($socket)) {
            return self::sendFailure('Unable to connect to the mail server.');
        }

        stream_set_timeout($socket, $timeout);

        try {
            self::expectSmtp($socket, [220]);
            self::command($socket, 'EHLO ' . self::ehloHost(), [250]);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Unable to start a TLS session with the mail server.');
                }
                self::command($socket, 'EHLO ' . self::ehloHost(), [250]);
            }

            $username = (string) $config['username'];
            if ($username !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode($username), [334]);
                self::command($socket, base64_encode((string) $config['password']), [235]);
            }

            self::command($socket, 'MAIL FROM:<' . (string) $config['from_address'] . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);

            $message = self::buildRfc822Message($to, $subject, $htmlBody, $textBody, $config);
            $message = preg_replace("/\r\n|\n|\r/", "\r\n", $message) ?? $message;
            $message = str_replace("\r\n.", "\r\n..", $message);
            fwrite($socket, $message . "\r\n.\r\n");
            self::expectSmtp($socket, [250]);
            self::command($socket, 'QUIT', [221, 250]);

            return true;
        } finally {
            fclose($socket);
        }
    }

    /**
     * @param resource $socket
     * @param list<int> $accepted
     */
    private static function command($socket, string $command, array $accepted): void
    {
        fwrite($socket, $command . "\r\n");
        self::expectSmtp($socket, $accepted);
    }

    /**
     * @param resource $socket
     * @param list<int> $accepted
     */
    private static function expectSmtp($socket, array $accepted): void
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $accepted, true)) {
            $reply = trim(preg_replace('/\s+/', ' ', $response) ?? $response);
            if ($reply === '') {
                $reply = 'empty reply';
            }
            if (strlen($reply) > 300) {
                $reply = substr($reply, 0, 300) . '...';
            }

            throw new \RuntimeException('SMTP ' . $code . ': ' . $reply);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function buildRfc822Message(string $to, string $subject, string $htmlBody, string $textBody, array $config): string
    {
        $boundary = 'mbpha-' . bin2hex(random_bytes(12));
        $from = self::formatAddress((string) $config['from_address'], (string) $config['from_name']);
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $headers = [
            'From: ' . $from,
            'To: ' . $to,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@telehealth.local>',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        return implode("\r\n", $headers)
            . "\r\n\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $textBody . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $htmlBody . "\r\n"
            . '--' . $boundary . "--\r\n";
    }

    private static function formatAddress(string $address, string $name): string
    {
        if ($name === '') {
            return $address;
        }

        return sprintf('"%s" <%s>', addcslashes($name, '"\\'), $address);
    }

    private static function ehloHost(): string
    {
        $appUrl = trim((string) Environment::get('APP_URL', ''));
        $host = is_string($appUrl) && $appUrl !== '' ? parse_url($appUrl, PHP_URL_HOST) : null;
        if (is_string($host) && $host !== '' && !in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
            return $host;
        }

        return 'localhost';
    }

    private static function sendFailure(string $message): bool
    {
        self::$lastError = $message;
        error_log('Mail delivery failed: ' . $message);
        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function sanitizeError(string $message, array $config): string
    {
        $secrets = array_filter([
            (string) $config['password'],
            (string) $config['username'],
        ], static fn (string $value): bool => $value !== '');

        foreach ($secrets as $secret) {
            $message = str_replace($secret, '[redacted]', $message);
        }

        return $message;
    }
}
