<?php

declare(strict_types=1);

namespace Tests\Support;

use OpenSSLAsymmetricKey;
use RuntimeException;

final class GoogleJwtFactory
{
    /**
     * @return array{private: OpenSSLAsymmetricKey, jwks: array{keys: list<array<string, string>>}, kid: string}
     */
    public static function rsaJwks(string $kid = 'phpunit-gis-kid'): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $opensslConfig = self::opensslConfigPath();
        if ($opensslConfig !== '') {
            $config['config'] = $opensslConfig;
        }

        $private = openssl_pkey_new($config);
        if ($private === false) {
            throw new RuntimeException('Unable to generate a test RSA key.');
        }

        $details = openssl_pkey_get_details($private);
        if ($details === false || !isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException('Unable to read the test RSA key.');
        }

        return [
            'private' => $private,
            'kid' => $kid,
            'jwks' => [
                'keys' => [[
                    'kty' => 'RSA',
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'kid' => $kid,
                    'n' => self::b64url($details['rsa']['n']),
                    'e' => self::b64url($details['rsa']['e']),
                ]],
            ],
        ];
    }

    /**
     * @param array{private: OpenSSLAsymmetricKey, kid: string} $rsa
     * @param array<string, mixed> $claims
     */
    public static function idToken(array $rsa, string $clientId, array $claims): string
    {
        $now = time();
        $payload = array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => $clientId,
            'azp' => $clientId,
            'exp' => $now + 3600,
            'iat' => $now,
            'email_verified' => true,
            'name' => 'Google Patient',
        ], $claims);

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $rsa['kid'],
        ];

        $headerPart = self::b64url(json_encode($header, JSON_UNESCAPED_SLASHES) ?: '{}');
        $payloadPart = self::b64url(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}');
        $signingInput = $headerPart . '.' . $payloadPart;
        $signature = '';
        if (!openssl_sign($signingInput, $signature, $rsa['private'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign the test JWT.');
        }

        return $signingInput . '.' . self::b64url($signature);
    }

    public static function configureOpenSsl(): void
    {
        $existing = getenv('OPENSSL_CONF');
        $needs = !is_string($existing) || $existing === '' || !is_file($existing);
        if (!$needs) {
            return;
        }

        $path = self::opensslConfigPath();
        if ($path !== '') {
            putenv('OPENSSL_CONF=' . $path);
        }
    }

    private static function opensslConfigPath(): string
    {
        foreach ([
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'openssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
        ] as $opensslConfig) {
            if (is_file($opensslConfig)) {
                return $opensslConfig;
            }
        }

        return '';
    }

    private static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
