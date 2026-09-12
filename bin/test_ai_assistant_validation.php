<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\PatientAiAssistantService;

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

$empty = PatientAiAssistantService::reply(1, '   ');
expect_true(($empty['ok'] ?? true) === false, 'Empty message is rejected');
expect_true(($empty['http_code'] ?? 0) === 422, 'Empty message uses 422');

$short = PatientAiAssistantService::reply(1, 'A');
expect_true(($short['ok'] ?? true) === false, 'Single-character message is rejected');

$long = PatientAiAssistantService::reply(1, str_repeat('a', PatientAiAssistantService::MAX_MESSAGE_LENGTH + 1));
expect_true(($long['ok'] ?? true) === false, 'Overlong message is rejected');
expect_true(($long['http_code'] ?? 0) === 422, 'Overlong message uses 422');

$anon = PatientAiAssistantService::reply(0, 'What is hypertension?');
expect_true(($anon['ok'] ?? true) === false, 'Invalid user id is rejected');
expect_true(($anon['http_code'] ?? 0) === 401, 'Invalid user id uses 401');

$source = (string) file_get_contents(dirname(__DIR__) . '/public/js/ai-assistant.js');
expect_true(!str_contains($source, 'GEMINI_API_KEY'), 'Browser script does not mention GEMINI_API_KEY');
expect_true(!str_contains($source, 'generativelanguage.googleapis.com'), 'Browser script does not call Gemini directly');

$view = (string) file_get_contents(dirname(__DIR__) . '/app/Views/partials/dashboard/medimate_widget.php');
expect_true(!str_contains($view, 'GEMINI_API_KEY'), 'MediMate widget does not mention GEMINI_API_KEY');
expect_true(str_contains($view, 'MediMate AI'), 'Widget shows MediMate AI');
expect_true(str_contains($view, 'Open MediMate AI'), 'Floating button has an accessible label');
expect_true(str_contains($view, 'images/MediMate.png'), 'Widget uses the MediMate avatar image');
expect_true(str_contains($view, 'New chat'), 'Widget includes a New chat control');
expect_true(str_contains($view, 'Previous conversations'), 'Widget includes conversation history');
expect_true(!str_contains($view, 'AI-generated information'), 'Widget does not show the educational disclaimer');
expect_true(!str_contains($view, 'general educational purposes'), 'Widget does not include the educational-purpose disclaimer');
expect_true(!str_contains($source, 'AI-generated information'), 'Browser script does not render the educational disclaimer');
expect_true(str_contains($source, 'start_new'), 'Browser script can start a new conversation');
expect_true(str_contains($source, 'conversation_id'), 'Browser script sends conversation id');
expect_true(!str_contains($view, 'bi-stars'), 'Widget no longer uses a generic sparkle icon');
expect_true(!str_contains($source, 'bi-stars'), 'Browser script no longer uses a generic sparkle icon');
expect_true(str_contains(PatientAiAssistantService::SYSTEM_INSTRUCTION, 'Maintain continuity'), 'System prompt asks MediMate to keep conversation context');
expect_true(PatientAiAssistantService::titleFromFirstMessage('What is hypertension?') === 'Hypertension information', 'Title from what-is question');
expect_true(PatientAiAssistantService::titleFromFirstMessage('How do I prepare for my consultation?') === 'Consultation preparation', 'Title from preparation question');
expect_true(PatientAiAssistantService::CONTEXT_MESSAGE_LIMIT >= 20, 'Gemini context window is bounded');

$cleaned = PatientAiAssistantService::cleanReplyForDisplay(
    "**Hypertension** is high blood pressure.\n\n### What you should know\n\n* Reduce salt.\n* Exercise regularly.\n\nUse `this` example."
);
expect_true(!str_contains($cleaned, '**'), 'Cleaner removes bold markers');
expect_true(!str_contains($cleaned, '###'), 'Cleaner removes heading markers');
expect_true(!str_contains($cleaned, '*'), 'Cleaner removes asterisks');
expect_true(!str_contains($cleaned, '`'), 'Cleaner removes backticks');
expect_true(!str_contains($cleaned, '#'), 'Cleaner removes hash symbols');
expect_true(str_contains($cleaned, 'Hypertension is high blood pressure.'), 'Cleaner keeps the healthcare sentence');
expect_true(str_contains($cleaned, 'Reduce salt.'), 'Cleaner keeps bullet text as a sentence');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
