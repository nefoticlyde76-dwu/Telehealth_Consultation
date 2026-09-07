<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Services\DoctorPasswordSetupService;

class DoctorPasswordSetupController extends Controller
{
    public function show(): void
    {
        $rawToken = strtolower(trim((string) ($_GET['token'] ?? '')));
        $inspect = DoctorPasswordSetupService::inspect($rawToken);

        $this->renderSetupView(
            (bool) ($inspect['valid'] ?? false),
            false,
            (string) ($inspect['doctorName'] ?? ''),
            $inspect['valid'] ?? false ? $rawToken : '',
            [],
            []
        );
    }

    public function store(): void
    {
        $result = DoctorPasswordSetupService::complete($_POST);

        if (($result['success'] ?? false) === true) {
            Session::flash('status', [
                'type' => 'success',
                'message' => $result['message'] ?? DoctorPasswordSetupService::SUCCESS_MESSAGE,
            ]);
            Helper::redirect('/login');
            return;
        }

        $rawToken = strtolower(trim((string) ($_POST['token'] ?? '')));
        $invalidLink = (bool) ($result['invalidLink'] ?? false);
        $csrfFailed = (bool) ($result['csrfFailed'] ?? false);
        $linkValid = !$invalidLink && !$csrfFailed;

        $this->renderSetupView(
            $linkValid,
            $csrfFailed,
            (string) ($result['doctorName'] ?? ''),
            $linkValid ? $rawToken : '',
            $result['errors'] ?? [],
            $result['fieldErrors'] ?? []
        );
    }

    /**
     * @param list<string> $errors
     * @param array<string, string> $fieldErrors
     */
    private function renderSetupView(
        bool $linkValid,
        bool $csrfFailed,
        string $doctorName,
        string $setupToken,
        array $errors,
        array $fieldErrors
    ): void {
        $this->render('doctor/setup_password', [
            'title' => 'Set Up Your Password | MBPHA TeleHealth Consultation System',
            'robots' => 'noindex, nofollow',
            'bodyClass' => 'public-layout auth-login-layout',
            'linkValid' => $linkValid,
            'csrfFailed' => $csrfFailed,
            'doctorName' => $doctorName,
            'setupToken' => $setupToken,
            'csrfToken' => Csrf::generate(),
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
            'invalidMessage' => DoctorPasswordSetupService::INVALID_MESSAGE,
            'csrfMessage' => DoctorPasswordSetupService::CSRF_MESSAGE,
        ]);
    }
}
