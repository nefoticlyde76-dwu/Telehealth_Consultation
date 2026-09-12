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

    public function chat(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'This action must be submitted as a POST request.',
            ], 405);
            return;
        }

        if (!AuthService::isAuthenticated() || AuthService::getUserRole() !== 'patient') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Please sign in as a patient to use MediMate AI.',
            ], 401);
            return;
        }

        if (!Csrf::verify((string) ($_POST['_token'] ?? ''))) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Security token expired or is invalid. Please refresh the page and try again.',
            ], 419);
            return;
        }

        $user = AuthService::getUser();
        if ($user === null || $user->id === null) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Please sign in as a patient to use MediMate AI.',
            ], 401);
            return;
        }

        $message = (string) ($_POST['message'] ?? '');

        try {
            $result = PatientAiAssistantService::reply((int) $user->id, $message);
        } catch (\Throwable $e) {
            error_log('[PatientAiAssistantController::chat] Unexpected assistant error.');
            $this->jsonResponse([
                'success' => false,
                'message' => PatientAiAssistantService::SAFE_UNAVAILABLE,
            ], 500);
            return;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonResponse([
                'success' => false,
                'message' => (string) ($result['message'] ?? PatientAiAssistantService::SAFE_UNAVAILABLE),
            ], (int) ($result['http_code'] ?? 502));
            return;
        }

        $this->jsonResponse([
            'success' => true,
            'reply' => (string) ($result['reply'] ?? ''),
        ]);
    }
}
