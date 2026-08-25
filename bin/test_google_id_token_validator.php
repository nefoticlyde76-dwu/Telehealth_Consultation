<?php

/**
 * Google ID-token validator checks.
 *
 * Usage: php bin/test_google_id_token_validator.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\App;
use App\Config\Environment;
use App\Services\GoogleIdTokenException;
use App\Services\GoogleIdTokenValidator;

$root = dirname(__DIR__);
Environment::load($root . '/.env');

$opensslConfigPath = '';
$existingOpenSslConf = getenv('OPENSSL_CONF');
$needsOpenSslConf = !is_string($existingOpenSslConf) || $existingOpenSslConf === '' || !is_file($existingOpenSslConf);
if ($needsOpenSslConf) {
    foreach ([
        dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'openssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
        dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
    ] as $opensslConfig) {
        if (is_file($opensslConfig)) {
            $opensslConfigPath = $opensslConfig;
            putenv('OPENSSL_CONF=' . $opensslConfig);
            break;
        }
    }
} else {
    $opensslConfigPath = $existingOpenSslConf;
}

$failed = 0;
$passed = 0;

function expect_true(bool $condition, string $label): void
{
    global $failed, $passed;
    if ($condition) {
        $passed++;
        echo "PASS  {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL  {$label}\n";
}

function b64url(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/**
 * @return array{private: OpenSSLAsymmetricKey, jwks: array{keys: list<array<string, string>>}, kid: string}
 */
function make_test_rsa_jwks(string $kid = 'test-kid-1'): array
{
    $config = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];
    global $opensslConfigPath;
    if (is_string($opensslConfigPath) && $opensslConfigPath !== '' && is_file($opensslConfigPath)) {
        $config['config'] = $opensslConfigPath;
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
                'n' => b64url($details['rsa']['n']),
                'e' => b64url($details['rsa']['e']),
            ]],
        ],
    ];
}

/**
 * @param array<string, mixed> $header
 * @param array<string, mixed> $payload
 */
function sign_jwt(array $header, array $payload, OpenSSLAsymmetricKey $private): string
{
    $headerPart = b64url(json_encode($header, JSON_UNESCAPED_SLASHES));
    $payloadPart = b64url(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $signingInput = $headerPart . '.' . $payloadPart;
    $signature = '';
    if (!openssl_sign($signingInput, $signature, $private, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Unable to sign the test JWT.');
    }

    return $signingInput . '.' . b64url($signature);
}

function expect_reason(string $token, ?array $jwks, string $expectedReason, string $label): void
{
    try {
        GoogleIdTokenValidator::validate($token, $jwks);
        expect_true(false, $label);
    } catch (GoogleIdTokenException $exception) {
        expect_true($exception->getReason() === $expectedReason, $label);
        expect_true(
            $exception->getMessage() === 'The Google identity token could not be verified.',
            $label . ' uses a generic message'
        );
        expect_true(!str_contains($exception->getMessage(), 'eyJ'), $label . ' does not leak a JWT');
        expect_true($token === '' || !str_contains($exception->getMessage(), $token), $label . ' does not include the raw token');
    }
}

$clientId = trim((string) (App::getConfig()['google']['client_id'] ?? ''));
expect_true($clientId !== '', 'GOOGLE_CLIENT_ID is configured for audience tests');
expect_true(extension_loaded('openssl'), 'OpenSSL is available');
expect_true(extension_loaded('curl'), 'cURL is available');
expect_true(!str_contains(strtolower(file_get_contents(dirname(__DIR__) . '/app/Services/GoogleIdTokenValidator.php')), 'tokeninfo'), 'Validator source does not call tokeninfo');

$rsa = make_test_rsa_jwks();
$now = time();
$validPayload = [
    'iss' => 'https://accounts.google.com',
    'aud' => $clientId,
    'azp' => $clientId,
    'exp' => $now + 3600,
    'iat' => $now,
    'sub' => 'google-subject-123',
    'email' => 'Patient.User@Gmail.com',
    'email_verified' => true,
    'name' => 'Patient User',
];
$validHeader = [
    'alg' => 'RS256',
    'typ' => 'JWT',
    'kid' => $rsa['kid'],
];
$validToken = sign_jwt($validHeader, $validPayload, $rsa['private']);

expect_reason('', $rsa['jwks'], GoogleIdTokenException::MALFORMED_TOKEN, 'Empty token is malformed');
expect_reason('not-a-jwt', $rsa['jwks'], GoogleIdTokenException::MALFORMED_TOKEN, 'Non-JWT string is malformed');
expect_reason('only.two', $rsa['jwks'], GoogleIdTokenException::MALFORMED_TOKEN, 'Two-segment value is malformed');
expect_reason('one.two.three.four', $rsa['jwks'], GoogleIdTokenException::MALFORMED_TOKEN, 'Four-segment value is malformed');
expect_reason('%%% .bad.sig', $rsa['jwks'], GoogleIdTokenException::MALFORMED_TOKEN, 'Undecodable segments are malformed');

$noneToken = b64url(json_encode(['alg' => 'none', 'typ' => 'JWT', 'kid' => $rsa['kid']]))
    . '.' . b64url(json_encode($validPayload))
    . '.';
expect_reason(rtrim($noneToken, '.'), $rsa['jwks'], GoogleIdTokenException::MALFORMED_TOKEN, 'Unsigned none token with two populated segments is malformed');

$noneThree = b64url(json_encode(['alg' => 'none', 'kid' => $rsa['kid']]))
    . '.' . b64url(json_encode($validPayload))
    . '.' . b64url('x');
expect_reason($noneThree, $rsa['jwks'], GoogleIdTokenException::INVALID_ALGORITHM, 'alg=none is rejected');

$hsHeader = ['alg' => 'HS256', 'typ' => 'JWT', 'kid' => $rsa['kid']];
$hsToken = sign_jwt($hsHeader, $validPayload, $rsa['private']);
expect_reason($hsToken, $rsa['jwks'], GoogleIdTokenException::INVALID_ALGORITHM, 'HS256 is rejected');

$missingKidHeader = ['alg' => 'RS256', 'typ' => 'JWT'];
$missingKidToken = sign_jwt($missingKidHeader, $validPayload, $rsa['private']);
expect_reason($missingKidToken, $rsa['jwks'], GoogleIdTokenException::UNKNOWN_KID, 'Missing kid is rejected');

$unknownKidHeader = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'unknown-kid'];
$unknownKidToken = sign_jwt($unknownKidHeader, $validPayload, $rsa['private']);
expect_reason($unknownKidToken, $rsa['jwks'], GoogleIdTokenException::UNKNOWN_KID, 'Unknown kid is rejected');

$tampered = $validToken;
$parts = explode('.', $tampered);
$payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
$payload['email'] = 'attacker@example.com';
$parts[1] = b64url(json_encode($payload));
$tampered = $parts[0] . '.' . $parts[1] . '.' . $parts[2];
expect_reason($tampered, $rsa['jwks'], GoogleIdTokenException::INVALID_SIGNATURE, 'Tampered payload fails signature verification');

$otherKey = make_test_rsa_jwks('other-kid');
$wrongKeyToken = sign_jwt($validHeader, $validPayload, $otherKey['private']);
expect_reason($wrongKeyToken, $rsa['jwks'], GoogleIdTokenException::INVALID_SIGNATURE, 'Signature from a different key is rejected');

$wrongIssuer = $validPayload;
$wrongIssuer['iss'] = 'https://evil.example';
expect_reason(sign_jwt($validHeader, $wrongIssuer, $rsa['private']), $rsa['jwks'], GoogleIdTokenException::INVALID_ISSUER, 'Wrong issuer is rejected');

$wrongAud = $validPayload;
$wrongAud['aud'] = 'other-client-id.apps.googleusercontent.com';
unset($wrongAud['azp']);
expect_reason(sign_jwt($validHeader, $wrongAud, $rsa['private']), $rsa['jwks'], GoogleIdTokenException::INVALID_AUDIENCE, 'Wrong audience is rejected');

$wrongFirstAud = $validPayload;
$wrongFirstAud['aud'] = ['other-client-id.apps.googleusercontent.com', $clientId];
unset($wrongFirstAud['azp']);
$wrongFirstAudToken = sign_jwt($validHeader, $wrongFirstAud, $rsa['private']);

$expired = $validPayload;
$expired['exp'] = $now - 120;
$expired['iat'] = $now - 3600;
expect_reason(sign_jwt($validHeader, $expired, $rsa['private']), $rsa['jwks'], GoogleIdTokenException::EXPIRED_TOKEN, 'Expired token is rejected');

$futureIat = $validPayload;
$futureIat['iat'] = $now + 3600;
$futureIat['exp'] = $now + 7200;
expect_reason(sign_jwt($validHeader, $futureIat, $rsa['private']), $rsa['jwks'], GoogleIdTokenException::INVALID_IAT, 'Far-future iat is rejected');

$missingSub = $validPayload;
unset($missingSub['sub']);
expect_reason(sign_jwt($validHeader, $missingSub, $rsa['private']), $rsa['jwks'], GoogleIdTokenException::MISSING_SUB, 'Missing sub is rejected');

$emptySub = $validPayload;
$emptySub['sub'] = '   ';
expect_reason(sign_jwt($validHeader, $emptySub, $rsa['private']), $rsa['jwks'], GoogleIdTokenException::MISSING_SUB, 'Blank sub is rejected');

$missingEmail = $validPayload;
unset($missingEmail['email']);
expect_reason(sign_jwt($validHeader, $missingEmail, $rsa['private']), $rsa['jwks'], GoogleIdTokenException::MISSING_EMAIL, 'Missing email is rejected');

$unverified = $validPayload;
$unverified['email_verified'] = false;
expect_reason(sign_jwt($validHeader, $unverified, $rsa['private']), $rsa['jwks'], GoogleIdTokenException::EMAIL_UNVERIFIED, 'Unverified email is rejected');

$wrongAzp = $validPayload;
$wrongAzp['azp'] = 'other-client-id.apps.googleusercontent.com';
expect_reason(sign_jwt($validHeader, $wrongAzp, $rsa['private']), $rsa['jwks'], GoogleIdTokenException::INVALID_AUDIENCE, 'Mismatched azp is rejected');

try {
    $identity = GoogleIdTokenValidator::validate($validToken, $rsa['jwks']);
    expect_true(is_array($identity), 'Valid signed token returns identity');
    expect_true(($identity['sub'] ?? null) === 'google-subject-123', 'Valid token preserves sub as the Google identity key');
    expect_true(($identity['email'] ?? null) === 'patient.user@gmail.com', 'Email is trimmed and lowercased like registration');
    expect_true(($identity['email_verified'] ?? null) === true, 'email_verified is true');
    expect_true(($identity['name'] ?? null) === 'Patient User', 'Name is returned as display data only');
    expect_true(array_keys($identity) === ['sub', 'email', 'email_verified', 'name'], 'Identity contains only trusted fields');
    expect_true(!isset($identity['aud'], $identity['iss'], $identity['exp']), 'Unrelated claims are not returned');
} catch (GoogleIdTokenException $exception) {
    expect_true(false, 'Valid signed token returns identity (' . $exception->getReason() . ')');
}

try {
    $identity = GoogleIdTokenValidator::validate($wrongFirstAudToken, $rsa['jwks']);
    expect_true(($identity['sub'] ?? null) === 'google-subject-123', 'Audience array is accepted when it contains the client ID even if it is not first');
} catch (GoogleIdTokenException $exception) {
    expect_true(false, 'Audience array containing the client ID is accepted (' . $exception->getReason() . ')');
}

$noAzp = $validPayload;
unset($noAzp['azp']);
try {
    $identity = GoogleIdTokenValidator::validate(sign_jwt($validHeader, $noAzp, $rsa['private']), $rsa['jwks']);
    expect_true(($identity['sub'] ?? null) === 'google-subject-123', 'Missing azp is allowed');
} catch (GoogleIdTokenException $exception) {
    expect_true(false, 'Missing azp is allowed (' . $exception->getReason() . ')');
}

$altIssuer = $validPayload;
$altIssuer['iss'] = 'accounts.google.com';
unset($altIssuer['azp']);
try {
    $identity = GoogleIdTokenValidator::validate(sign_jwt($validHeader, $altIssuer, $rsa['private']), $rsa['jwks']);
    expect_true(($identity['sub'] ?? null) === 'google-subject-123', 'Issuer accounts.google.com is accepted');
} catch (GoogleIdTokenException $exception) {
    expect_true(false, 'Issuer accounts.google.com is accepted (' . $exception->getReason() . ')');
}

expect_true(GoogleIdTokenValidator::JWKS_URL === 'https://www.googleapis.com/oauth2/v3/certs', 'JWKS URL is fixed to Google certs');

$rotated = make_test_rsa_jwks('rotated-kid');
$rotatedHeader = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'rotated-kid'];
$rotatedToken = sign_jwt($rotatedHeader, $validPayload, $rotated['private']);
$fetchCount = 0;

GoogleIdTokenValidator::resetJwksCache();
GoogleIdTokenValidator::seedJwksCache($rsa['jwks'], 3600);
GoogleIdTokenValidator::$testFetchJwks = static function () use (&$fetchCount, $rotated) {
    $fetchCount++;
    return [
        'jwks' => $rotated['jwks'],
        'expires_at' => time() + 3600,
    ];
};

try {
    $rotatedIdentity = GoogleIdTokenValidator::validate($rotatedToken);
    expect_true(($rotatedIdentity['sub'] ?? null) === 'google-subject-123', 'Unknown cached kid triggers JWKS refresh and then validates');
    expect_true($fetchCount === 1, 'JWKS is refreshed once after a rotated kid');
} catch (GoogleIdTokenException $exception) {
    expect_true(false, 'Rotated kid validates after JWKS refresh (' . $exception->getReason() . ')');
}

$stillUnknownHeader = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'never-published-kid'];
$stillUnknownToken = sign_jwt($stillUnknownHeader, $validPayload, $rsa['private']);
$fetchCount = 0;
GoogleIdTokenValidator::resetJwksCache();
GoogleIdTokenValidator::seedJwksCache($rsa['jwks'], 3600);
GoogleIdTokenValidator::$testFetchJwks = static function () use (&$fetchCount, $rsa) {
    $fetchCount++;
    return [
        'jwks' => $rsa['jwks'],
        'expires_at' => time() + 3600,
    ];
};
expect_reason($stillUnknownToken, null, GoogleIdTokenException::UNKNOWN_KID, 'Unknown kid still fails after a JWKS refresh');
expect_true($fetchCount === 1, 'Unknown kid after refresh does not retry JWKS indefinitely');

GoogleIdTokenValidator::$testFetchJwks = static function () {
    return ['jwks' => ['keys' => 'bad'], 'expires_at' => time() + 3600];
};
GoogleIdTokenValidator::resetJwksCache();
expect_reason($validToken, null, GoogleIdTokenException::JWKS_UNAVAILABLE, 'Malformed JWKS is rejected');

GoogleIdTokenValidator::$testFetchJwks = static function () {
    throw new RuntimeException('network down');
};
GoogleIdTokenValidator::resetJwksCache();
expect_reason($validToken, null, GoogleIdTokenException::JWKS_UNAVAILABLE, 'JWKS unavailable is rejected');

$expiredFetchCount = 0;
GoogleIdTokenValidator::$testFetchJwks = static function () use (&$expiredFetchCount, $rsa) {
    $expiredFetchCount++;
    return [
        'jwks' => $rsa['jwks'],
        'expires_at' => time() + 3600,
    ];
};
GoogleIdTokenValidator::resetJwksCache();
$jwksCachePath = dirname(__DIR__) . '/tmp/google_jwks.json';
if (!is_dir(dirname($jwksCachePath))) {
    mkdir(dirname($jwksCachePath), 0775, true);
}
file_put_contents($jwksCachePath, json_encode([
    'expires_at' => time() - 30,
    'jwks' => $rsa['jwks'],
]), LOCK_EX);
try {
    $fromExpiredCache = GoogleIdTokenValidator::validate($validToken);
    expect_true(($fromExpiredCache['sub'] ?? null) === 'google-subject-123', 'Expired JWKS cache is refreshed before validation');
    expect_true($expiredFetchCount === 1, 'Expired JWKS cache causes a single refresh fetch');
} catch (GoogleIdTokenException $exception) {
    expect_true(false, 'Expired JWKS cache is refreshed before validation (' . $exception->getReason() . ')');
}

GoogleIdTokenValidator::$testFetchJwks = null;
GoogleIdTokenValidator::resetJwksCache();

echo PHP_EOL . "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
