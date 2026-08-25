<?php

/**
 * Development-only Gmail SMTP probe using the existing MailService config.
 *
 * Usage: php bin/test_gmail_smtp.php
 *
 * Does not print usernames, passwords, or app secrets.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Services\MailService;

$root = dirname(__DIR__);
Environment::load($root . '/.env');

function redact(string $text): string
{
    $config = MailService::config();
    foreach ([$config['password'], $config['username'], $config['from_address']] as $secret) {
        $secret = trim((string) $secret);
        if ($secret !== '') {
            $text = str_replace($secret, '[redacted]', $text);
        }
    }

    return trim($text);
}

function smtp_read($socket): array
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return [
        'code' => (int) substr($response, 0, 3),
        'text' => redact($response),
    ];
}

function smtp_cmd($socket, string $command, bool $hideCommand = false): array
{
    fwrite($socket, $command . "\r\n");
    $result = smtp_read($socket);
    $result['command'] = $hideCommand ? '[redacted command]' : $command;

    return $result;
}

$config = MailService::config();
$mailer = strtolower((string) $config['mailer']);
$host = (string) $config['host'];
$port = (int) $config['port'];
$encryption = strtolower((string) $config['encryption']);
$usernamePresent = trim((string) $config['username']) !== '';
$passwordPresent = trim((string) $config['password']) !== '';
$fromPresent = trim((string) $config['from_address']) !== ''
    && filter_var((string) $config['from_address'], FILTER_VALIDATE_EMAIL);
$recipient = strtolower(trim((string) $config['from_address']));

$report = [
    'mailer_smtp' => $mailer === 'smtp',
    'host_gmail' => $host === 'smtp.gmail.com',
    'port_587' => $port === 587,
    'tls' => $encryption === 'tls',
    'credentials' => $usernamePresent && $passwordPresent,
    'connection' => false,
    'starttls' => false,
    'authentication' => false,
    'submission' => false,
    'accepted' => false,
    'error' => '',
    'stage' => 'init',
];

echo "MAIL_MAILER configured as smtp: " . ($report['mailer_smtp'] ? 'yes' : 'no') . PHP_EOL;
echo "SMTP host is smtp.gmail.com: " . ($report['host_gmail'] ? 'yes' : 'no') . PHP_EOL;
echo "SMTP port is 587: " . ($report['port_587'] ? 'yes' : 'no') . PHP_EOL;
echo "Encryption is tls: " . ($report['tls'] ? 'yes' : 'no') . PHP_EOL;
echo "Username present: " . ($usernamePresent ? 'yes' : 'no') . PHP_EOL;
echo "Password present: " . ($passwordPresent ? 'yes' : 'no') . PHP_EOL;
echo "From address present: " . ($fromPresent ? 'yes' : 'no') . PHP_EOL;

if (!$report['mailer_smtp'] || !$report['host_gmail'] || !$report['port_587']) {
    $report['error'] = 'SMTP host configuration is incomplete, so the existing driver was not used.';
    echo "Probe skipped: " . $report['error'] . PHP_EOL;
    exit(1);
}

$timeout = (int) $config['timeout'];
$errno = 0;
$errstr = '';
$socket = @stream_socket_client($host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);

if (!is_resource($socket)) {
    $report['error'] = 'Connection failed: ' . redact($errstr !== '' ? $errstr : 'unable to open smtp.gmail.com:587');
    echo $report['error'] . PHP_EOL;
} else {
    stream_set_timeout($socket, $timeout);
    $banner = smtp_read($socket);
    if ($banner['code'] === 220) {
        $report['connection'] = true;
        $report['stage'] = 'connected';
        echo "Connection banner code: " . $banner['code'] . PHP_EOL;

        $ehlo = smtp_cmd($socket, 'EHLO telehealth.local');
        if ($ehlo['code'] !== 250) {
            $report['error'] = 'EHLO rejected with SMTP ' . $ehlo['code'];
        } else {
            $starttls = smtp_cmd($socket, 'STARTTLS');
            if ($starttls['code'] !== 220) {
                $report['error'] = 'STARTTLS rejected with SMTP ' . $starttls['code'] . ': ' . $starttls['text'];
            } elseif (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $report['error'] = 'TLS negotiation failed after STARTTLS.';
            } else {
                $report['starttls'] = true;
                $report['stage'] = 'tls';
                echo "TLS negotiation: pass" . PHP_EOL;

                $ehlo2 = smtp_cmd($socket, 'EHLO telehealth.local');
                if ($ehlo2['code'] !== 250) {
                    $report['error'] = 'Post-TLS EHLO rejected with SMTP ' . $ehlo2['code'];
                } elseif (!$report['credentials'] || !$fromPresent) {
                    $report['error'] = 'MAIL_USERNAME, MAIL_PASSWORD, and MAIL_FROM_ADDRESS are empty in .env, so authentication and send were not attempted.';
                } else {
                    $auth = smtp_cmd($socket, 'AUTH LOGIN');
                    if ($auth['code'] !== 334) {
                        $report['error'] = 'AUTH LOGIN rejected with SMTP ' . $auth['code'] . ': ' . $auth['text'];
                    } else {
                        $userReply = smtp_cmd($socket, base64_encode((string) $config['username']), true);
                        $passReply = smtp_cmd($socket, base64_encode((string) $config['password']), true);
                        if ($passReply['code'] === 235) {
                            $report['authentication'] = true;
                            $report['stage'] = 'authenticated';
                            echo "Authentication: pass" . PHP_EOL;
                        } else {
                            $report['error'] = 'Authentication rejected with SMTP ' . $passReply['code'] . ': ' . $passReply['text'];
                            echo "Authentication: fail" . PHP_EOL;
                        }
                    }
                }
            }
        }
    } else {
        $report['error'] = 'SMTP banner rejected with SMTP ' . $banner['code'] . ': ' . $banner['text'];
    }

    fclose($socket);
}

if ($report['authentication']) {
    $sent = MailService::send(
        $recipient,
        'MBPHA TeleHealth SMTP Test',
        '<p>This is a controlled SMTP delivery test from the MBPHA TeleHealth application.</p>',
        'This is a controlled SMTP delivery test from the MBPHA TeleHealth application.'
    );
    $report['submission'] = $sent;
    $report['accepted'] = $sent;
    if (!$sent) {
        $report['error'] = MailService::lastError() !== ''
            ? MailService::lastError()
            : 'MailService::send() returned false without a stored error.';
        echo "MailService send: fail" . PHP_EOL;
    } else {
        echo "MailService send: pass" . PHP_EOL;
    }
}

echo PHP_EOL;
echo 'MAIL_MAILER: ' . ($report['mailer_smtp'] ? 'PASS' : 'FAIL') . PHP_EOL;
echo 'SMTP host: ' . ($report['host_gmail'] ? 'PASS' : 'FAIL') . PHP_EOL;
echo 'SMTP port: ' . ($report['port_587'] ? 'PASS' : 'FAIL') . PHP_EOL;
echo 'Credentials configured: ' . ($report['credentials'] ? 'YES' : 'NO') . PHP_EOL;
echo 'Connection: ' . ($report['connection'] ? 'PASS' : 'FAIL') . PHP_EOL;
echo 'TLS: ' . ($report['starttls'] ? 'PASS' : 'FAIL') . PHP_EOL;
echo 'Authentication: ' . ($report['authentication'] ? 'PASS' : 'FAIL') . PHP_EOL;
echo 'Message submission: ' . ($report['submission'] ? 'PASS' : 'FAIL') . PHP_EOL;
echo 'Test email accepted: ' . ($report['accepted'] ? 'YES' : 'NO') . PHP_EOL;
if ($report['error'] !== '') {
    echo 'Error: ' . $report['error'] . PHP_EOL;
}

exit($report['accepted'] ? 0 : 1);
