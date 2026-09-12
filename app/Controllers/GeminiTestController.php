<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\GeminiService;

/**
 * Temporary authenticated Gemini connection check.
 * Not a chatbot. No UI, history, or clinical integration.
 */
class GeminiTestController extends Controller
{
    public function test(): void
    {
        $result = GeminiService::testConnection();

        if (!($result['ok'] ?? false)) {
            $this->jsonResponse([
                'success' => false,
                'message' => (string) ($result['message'] ?? 'Unable to connect to Gemini.'),
            ], (int) ($result['http_code'] ?? 502));
            return;
        }

        $this->jsonResponse([
            'success' => true,
            'message' => self::successMessage((string) ($result['text'] ?? '')),
        ]);
    }

    /**
     * Prefer the expected confirmation phrase when Gemini follows the prompt.
     * Otherwise return a short sanitized excerpt of the model text.
     */
    private static function successMessage(string $text): string
    {
        $expected = 'Gemini connection successful.';
        $text = trim($text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? $text;

        if ($text === '' || stripos($text, 'Gemini connection successful') !== false) {
            return $expected;
        }

        if (strlen($text) > 200) {
            $text = substr($text, 0, 200);
        }

        return $text;
    }
}
