<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Helpers\Helper;
use App\Services\AuthService;
use App\Services\PatientAiAssistantService;

class PatientAiAssistantController extends Controller
{
    public function index(): void
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'patient') {
            Helper::redirect('/login');
            return;
        }

        Helper::redirect('/patient/dashboard');
    }

    public function conversation(): void
    {
        $user = $this->requirePatientJson();
        if ($user === null) {
            return;
        }

        $requestedId = (int) ($_GET['id'] ?? 0);
        $result = PatientAiAssistantService::getConversation(
            (int) $user->id,
            $requestedId > 0 ? $requestedId : null
        );

        $this->sendServiceResult($result, [
            'conversation' => $result['conversation'] ?? null,
            'messages' => $result['messages'] ?? [],
        ]);
    }

    public function conversations(): void
    {
        $user = $this->requirePatientJson();
        if ($user === null) {
            return;
        }

        $result = PatientAiAssistantService::listConversations((int) $user->id);
        $this->sendServiceResult($result, [
            'conversations' => $result['conversations'] ?? [],
        ]);
    }

    public function deleteConversation(): void
    {
        $user = $this->requirePatientPost();
        if ($user === null) {
            return;
        }

        $conversationId = (int) ($_POST['conversation_id'] ?? 0);
        $result = PatientAiAssistantService::deleteConversation((int) $user->id, $conversationId);
        $this->sendServiceResult($result, [
            'deleted' => (bool) ($result['deleted'] ?? false),
        ]);
    }

    public function chat(): void
    {
        $user = $this->requirePatientPost();
        if ($user === null) {
            return;
        }

        $message = (string) ($_POST['message'] ?? '');
        $conversationId = (int) ($_POST['conversation_id'] ?? 0);
        $startNew = (string) ($_POST['start_new'] ?? '') === '1';

        try {
            $result = PatientAiAssistantService::reply(
                (int) $user->id,
                $message,
                $conversationId > 0 ? $conversationId : null,
                $startNew
            );
        } catch (\Throwable $e) {
            error_log('[PatientAiAssistantController::chat] Unexpected assistant error.');
            $this->jsonResponse([
                'success' => false,
                'message' => PatientAiAssistantService::SAFE_UNAVAILABLE,
                'csrf_token' => Csrf::generate(),
            ], 500);
            return;
        }

        $this->sendServiceResult($result, [
            'reply' => (string) ($result['reply'] ?? ''),
            'conversation_id' => (int) ($result['conversation_id'] ?? 0),
            'title' => (string) ($result['title'] ?? ''),
        ]);
    }

    private function requirePatientPost(): ?object
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'This action must be submitted as a POST request.',
            ], 405);
            return null;
        }

        if (!Csrf::verify((string) ($_POST['_token'] ?? ''))) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Security token expired or is invalid. Please refresh the page and try again.',
            ], 419);
            return null;
        }

        return $this->requirePatientJson();
    }

    private function requirePatientJson(): ?object
    {
        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'patient') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Please sign in as a patient to use MediMate AI.',
            ], 401);
            return null;
        }

        $user = AuthService::getUser();
        if ($user === null || $user->id === null) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Please sign in as a patient to use MediMate AI.',
            ], 401);
            return null;
        }

        return $user;
    }

    /**
     * @param array{ok?:bool,message?:string,http_code?:int} $result
     * @param array<string, mixed> $extra
     */
    private function sendServiceResult(array $result, array $extra = []): void
    {
        if (!($result['ok'] ?? false)) {
            $this->jsonResponse(array_merge([
                'success' => false,
                'message' => (string) ($result['message'] ?? PatientAiAssistantService::SAFE_UNAVAILABLE),
                'csrf_token' => Csrf::generate(),
            ], $extra), (int) ($result['http_code'] ?? 502));
            return;
        }

        $this->jsonResponse(array_merge([
            'success' => true,
            'csrf_token' => Csrf::generate(),
        ], $extra));
    }
}
