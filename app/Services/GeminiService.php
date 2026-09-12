<?php

namespace App\Services;

use App\Config\App;

/**
 * Server-side Gemini generateContent client.
 *
 * Reads GEMINI_API_KEY and GEMINI_MODEL from App config (environment).
 * The API key is sent only as the x-goog-api-key header and is never
 * returned, logged, or included in exception messages.
 */
class GeminiService
{
    public const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    public const DEFAULT_MODEL = 'gemini-3.5-flash';

    public const TEST_PROMPT = 'Respond with exactly: Gemini connection successful.';

    /**
     * Send a single-turn text prompt to Gemini and return a sanitized result.
     *
     * Optional $options (all omitted values keep the original test request):
     *   - system_instruction: string
     *   - max_output_tokens: int (default 64)
     *   - temperature: float (default 0)
     *   - timeout: int seconds (default 20)
     *
     * @param array<string, mixed> $options
     * @return array{
     *     ok: bool,
     *     message: string,
     *     text?: string,
     *     http_code?: int
     * }
     */
    public static function generateText(string $prompt, array $options = []): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return self::failResult('A prompt is required.', 400);
        }

        $config = self::resolveConfig();
        if (!($config['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($config['message'] ?? 'Gemini is not configured.'),
                'http_code' => (int) ($config['http_code'] ?? 503),
            ];
        }

        return self::generateContent($prompt, [
            'api_key' => (string) $config['api_key'],
            'model' => (string) $config['model'],
        ], $options);
    }

    /**
     * Multi-turn generateContent using the same Gemini client as generateText().
     *
     * @param list<array{role?:string,text?:string,message?:string}> $turns
     * @param array<string, mixed> $options
     * @return array{ok: bool, message: string, text?: string, http_code?: int}
     */
    public static function generateConversation(array $turns, array $options = []): array
    {
        $config = self::resolveConfig();
        if (!($config['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($config['message'] ?? 'Gemini is not configured.'),
                'http_code' => (int) ($config['http_code'] ?? 503),
            ];
        }

        return self::generateContent($turns, [
            'api_key' => (string) $config['api_key'],
            'model' => (string) $config['model'],
        ], $options);
    }

    /**
     * Connection check used by the temporary /ai/test route.
     *
     * @return array{ok: bool, message: string, text?: string, http_code?: int}
     */
    public static function testConnection(): array
    {
        return self::generateText(self::TEST_PROMPT);
    }

    /**
     * @param string|list<array{role?:string,text?:string,message?:string}> $input
     * @param array{api_key: non-empty-string, model: non-empty-string} $config
     * @param array<string, mixed> $options
     * @return array{ok: bool, message: string, text?: string, http_code?: int}
     */
    private static function generateContent(string|array $input, array $config, array $options = []): array
    {
        if (!extension_loaded('curl')) {
            error_log('[GeminiService] PHP ext-curl is not installed.');
            return self::failResult('Server missing cURL extension.', 500);
        }

        $contents = self::normalizeContents($input);
        if ($contents === []) {
            return self::failResult('A prompt is required.', 400);
        }

        $url = self::API_BASE . '/models/' . rawurlencode($config['model']) . ':generateContent';

        $ch = curl_init($url);
        if ($ch === false) {
            return self::failResult('Unable to initialise the Gemini request.', 500);
        }

        $maxTokens = (int) ($options['max_output_tokens'] ?? 64);
        $maxTokens = max(16, min(2048, $maxTokens));
        $temperature = (float) ($options['temperature'] ?? 0);
        $temperature = max(0.0, min(1.0, $temperature));
        $timeout = (int) ($options['timeout'] ?? 20);
        $timeout = max(10, min(60, $timeout));

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        $systemInstruction = trim((string) ($options['system_instruction'] ?? ''));
        if ($systemInstruction !== '') {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ];
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return self::failResult('Unable to prepare the Gemini request.', 500);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'MBPHA-TeleHealth/1.0',
            CURLOPT_VERBOSE => false,
            CURLOPT_HTTPHEADER => [
                'x-goog-api-key: ' . $config['api_key'],
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($raw === false || $errno !== 0) {
            error_log(sprintf('[GeminiService] cURL error (%d) on generateContent HTTP %d', $errno, $http));
            return self::failResult('Unable to reach the Gemini service.', 502);
        }

        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            error_log(sprintf('[GeminiService] Non-JSON response (HTTP %d)', $http));
            return self::failResult('Gemini returned an invalid response.', 502);
        }

        if ($http < 200 || $http >= 300) {
            $safeStatus = self::safeErrorStatus($decoded);
            error_log(sprintf(
                '[GeminiService] HTTP %d on generateContent%s | body_len=%d',
                $http,
                $safeStatus !== '' ? ' | status=' . $safeStatus : '',
                strlen((string) $raw)
            ));

            return self::failResult(self::userMessageForHttp($http), 502);
        }

        if (self::isBlocked($decoded)) {
            error_log('[GeminiService] Gemini blocked the prompt or response.');
            return self::failResult('Gemini blocked the response.', 502);
        }

        $text = self::extractText($decoded);
        if ($text === null || $text === '') {
            error_log('[GeminiService] Gemini returned no text candidates.');
            return self::failResult('Gemini returned an empty response.', 502);
        }

        return [
            'ok' => true,
            'message' => 'OK',
            'text' => $text,
            'http_code' => 200,
        ];
    }

    /**
     * @param string|list<array{role?:string,text?:string,message?:string}> $input
     * @return list<array{role: string, parts: list<array{text: string}>}>
     */
    private static function normalizeContents(string|array $input): array
    {
        if (is_string($input)) {
            $text = trim($input);
            if ($text === '') {
                return [];
            }

            return [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $text],
                    ],
                ],
            ];
        }

        $contents = [];
        foreach ($input as $turn) {
            if (!is_array($turn)) {
                continue;
            }
            $text = trim((string) ($turn['text'] ?? $turn['message'] ?? ''));
            if ($text === '') {
                continue;
            }
            $role = strtolower(trim((string) ($turn['role'] ?? 'user')));
            $contents[] = [
                'role' => in_array($role, ['assistant', 'model'], true) ? 'model' : 'user',
                'parts' => [
                    ['text' => $text],
                ],
            ];
        }

        return $contents;
    }

    /**
     * @return array{ok: true, api_key: non-empty-string, model: non-empty-string}|array{ok: false, message: string, http_code: int}
     */
    private static function resolveConfig(): array
    {
        $gemini = App::getConfig()['gemini'] ?? [];
        if (!is_array($gemini)) {
            error_log('[GeminiService] Gemini config section is missing.');
            return self::failResult('Gemini is not configured.', 503);
        }

        $apiKey = trim((string) ($gemini['api_key'] ?? ''));
        $model = trim((string) ($gemini['model'] ?? self::DEFAULT_MODEL));

        if ($apiKey === '') {
            error_log('[GeminiService] GEMINI_API_KEY is missing or empty.');
            return self::failResult('Gemini is not configured.', 503);
        }

        if ($model === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $model)) {
            error_log('[GeminiService] GEMINI_MODEL is missing or contains invalid characters.');
            return self::failResult('The configured Gemini model is invalid.', 503);
        }

        return [
            'ok' => true,
            'api_key' => $apiKey,
            'model' => $model,
        ];
    }

    /**
     * @param array<array-key, mixed> $decoded
     */
    private static function extractText(array $decoded): ?string
    {
        $candidates = $decoded['candidates'] ?? [];
        if (!is_array($candidates) || $candidates === []) {
            return null;
        }

        $first = is_array($candidates[0] ?? null) ? $candidates[0] : [];
        $parts = $first['content']['parts'] ?? [];
        if (!is_array($parts)) {
            return null;
        }

        $chunks = [];
        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }
            $text = trim((string) ($part['text'] ?? ''));
            if ($text !== '') {
                $chunks[] = $text;
            }
        }

        if ($chunks === []) {
            return null;
        }

        return implode('', $chunks);
    }

    /**
     * @param array<array-key, mixed> $decoded
     */
    private static function isBlocked(array $decoded): bool
    {
        $promptFeedback = is_array($decoded['promptFeedback'] ?? null) ? $decoded['promptFeedback'] : [];
        if (trim((string) ($promptFeedback['blockReason'] ?? '')) !== '') {
            return true;
        }

        $candidates = $decoded['candidates'] ?? [];
        if (!is_array($candidates) || $candidates === []) {
            return false;
        }

        $first = is_array($candidates[0] ?? null) ? $candidates[0] : [];
        $finish = strtoupper(trim((string) ($first['finishReason'] ?? '')));

        return in_array($finish, ['SAFETY', 'BLOCKLIST', 'PROHIBITED_CONTENT'], true);
    }

    /**
     * @param array<array-key, mixed> $decoded
     */
    private static function safeErrorStatus(array $decoded): string
    {
        $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
        $status = preg_replace('/[^A-Z0-9_]/', '', strtoupper((string) ($error['status'] ?? ''))) ?? '';

        return $status !== '' ? substr($status, 0, 64) : '';
    }

    private static function userMessageForHttp(int $http): string
    {
        return match (true) {
            $http === 400 => 'Gemini rejected the request.',
            $http === 401, $http === 403 => 'Gemini rejected the credentials.',
            $http === 404 => 'The configured Gemini model was not found.',
            $http === 429 => 'Gemini rate limit reached. Try again later.',
            $http >= 500 => 'Gemini is temporarily unavailable.',
            default => 'Gemini request failed.',
        };
    }

    /**
     * @return array{ok: false, message: string, http_code: int}
     */
    private static function failResult(string $message, int $httpCode): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'http_code' => $httpCode,
        ];
    }
}
