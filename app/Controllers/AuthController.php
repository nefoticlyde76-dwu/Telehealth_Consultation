<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\LoginAttemptService;
use App\Services\PasswordResetService;

class AuthController extends Controller
{
    public function login(): void
    {
        $errors = [];
        $oldInput = ['email' => ''];

        if (AuthService::isAuthenticated()) {
            $this->redirectAuthenticatedUser();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $oldInput['email'] = $email;
            $errors = $this->validateLoginRequest($email, $password, (string) ($_POST['_token'] ?? ''));

            if (empty($errors)) {
                try {
                    $attempt = LoginAttemptService::inspect($email);
                    if ($attempt['locked']) {
                        $errors[] = LoginAttemptService::LOCKOUT_MESSAGE;
                    } else {
                        LoginAttemptService::throttle($attempt['delay_seconds']);
                        $user = AuthService::authenticate($email, $password);
                        if ($user && $user->getRole()) {
                            LoginAttemptService::clear($email);
                            AuthService::login($user->id, $user->getRole());
                            AuditLogService::record(
                                'login_success',
                                'User authenticated successfully.',
                                AuditLogService::ENTITY_AUTH,
                                (int) $user->id
                            );
                            Helper::redirect(AuthService::getRoleRedirectUrl($user->getRole()));
                            return;
                        }

                        $failure = LoginAttemptService::recordFailure($email);
                        $failedAuditContext = [
                            'actor_name' => 'Guest',
                            'actor_role' => 'guest',
                            'subject_name' => $email,
                            'subject_role' => 'guest',
                        ];
                        AuditLogService::record(
                            'login_failed',
                            'Authentication failed for supplied credentials.',
                            AuditLogService::ENTITY_AUTH,
                            null,
                            'failed',
                            $failedAuditContext
                        );
                        if ($failure['just_locked']) {
                            AuditLogService::record(
                                'login_lockout',
                                'Sign-in temporarily locked after repeated failed attempts.',
                                AuditLogService::ENTITY_AUTH,
                                null,
                                'failed',
                                $failedAuditContext
                            );
                            $errors[] = LoginAttemptService::LOCKOUT_MESSAGE;
                        } else {
                            $errors[] = 'Invalid email, password, or account status.';
                        }
                    }
                } catch (\Throwable $exception) {
                    error_log('Authentication error: ' . $exception->getMessage());
                    $errors[] = 'Authentication is temporarily unavailable. Please try again later.';
                }
            }
        }

        $this->render('auth/login', [
            'title' => 'Login | MBPHA TeleHealth Consultation System',
            'robots' => 'noindex, nofollow',
            'csrfToken' => Csrf::generate(),
            'errors' => $errors,
            'oldInput' => $oldInput,
            'statusMessage' => Session::getFlash('status'),
            'bodyClass' => 'public-layout auth-login-layout',
        ]);
    }

    public function register(): void
    {
        $errors = [];
        $oldInput = [
            'full_name' => '',
            'email' => '',
            'dob' => '',
            'gender' => '',
            'address' => '',
            'terms' => false,
        ];

        if (AuthService::isAuthenticated()) {
            $this->redirectAuthenticatedUser();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = $this->getRegistrationFormData();
            $oldInput = array_merge($formData, [
                'terms' => isset($_POST['terms']),
            ]);
            $errors = $this->validateRegistrationRequest(
                $formData,
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['confirm_password'] ?? ''),
                (string) ($_POST['_token'] ?? ''),
                isset($_POST['terms'])
            );

            if (empty($errors)) {
                try {
                    $user = AuthService::register(array_merge($formData, [
                        'password' => (string) $_POST['password'],
                    ]));

                    if ($user && $user->getRole()) {
                        AuthService::login($user->id, $user->getRole());
                        Helper::redirect(AuthService::getRoleRedirectUrl($user->getRole()));
                        return;
                    }

                    $errors[] = 'Registration failed. Please review your information and try again.';
                } catch (\Throwable $exception) {
                    error_log('Registration error: ' . $exception->getMessage());
                    $errors[] = 'Registration is temporarily unavailable. Please try again later.';
                }
            }
        }

        $this->render('auth/register', [
            'title' => 'Register | MBPHA TeleHealth Consultation System',
            'robots' => 'noindex, nofollow',
            'csrfToken' => Csrf::generate(),
            'errors' => $errors,
            'oldInput' => $oldInput,
            'bodyClass' => 'public-layout auth-login-layout auth-register-layout',
        ]);
    }

    public function forgotPassword(): void
    {
        $errors = [];
        $fieldErrors = [];
        $oldInput = ['email' => ''];

        if (AuthService::isAuthenticated()) {
            $this->redirectAuthenticatedUser();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $oldInput['email'] = $email;

            if (!Csrf::verify((string) ($_POST['_token'] ?? ''))) {
                $errors[] = 'Your session security token is invalid. Please refresh the page and try again.';
            } else {
                try {
                    PasswordResetService::requestReset($email);
                } catch (\Throwable) {
                    error_log('Password reset request error.');
                }

                Session::flash('status', [
                    'type' => 'success',
                    'message' => PasswordResetService::REQUEST_MESSAGE,
                ]);
                Helper::redirect('/login');
                return;
            }
        }

        $this->render('auth/forgot_password', [
            'title' => 'Forgot Password | MBPHA TeleHealth Consultation System',
            'robots' => 'noindex, nofollow',
            'csrfToken' => Csrf::generate(),
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
            'oldInput' => $oldInput,
            'statusMessage' => Session::getFlash('status'),
            'bodyClass' => 'public-layout auth-login-layout',
        ]);
    }

    public function resetPassword(): void
    {
        $errors = [];
        $fieldErrors = [];
        $invalidLink = false;
        $resetToken = '';

        if (AuthService::isAuthenticated()) {
            $this->redirectAuthenticatedUser();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $resetToken = strtolower(trim((string) ($_POST['token'] ?? '')));

            if (!Csrf::verify((string) ($_POST['_token'] ?? ''))) {
                $errors[] = 'Your session security token is invalid. Please refresh the page and try again.';
            } else {
                $password = (string) ($_POST['password'] ?? '');
                $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? $_POST['confirm_password'] ?? '');

                try {
                    $result = PasswordResetService::completeReset($resetToken, $password, $passwordConfirmation);
                } catch (\Throwable) {
                    error_log('Password reset completion error.');
                    $result = [
                        'success' => false,
                        'invalidLink' => true,
                        'message' => PasswordResetService::INVALID_MESSAGE,
                        'errors' => [PasswordResetService::INVALID_MESSAGE],
                        'fieldErrors' => [],
                    ];
                }

                if (($result['success'] ?? false) === true) {
                    Session::flash('status', [
                        'type' => 'success',
                        'message' => $result['message'] ?? PasswordResetService::SUCCESS_MESSAGE,
                    ]);
                    Helper::redirect('/login');
                    return;
                }

                $invalidLink = (bool) ($result['invalidLink'] ?? false);
                $errors = $result['errors'] ?? [$result['message'] ?? PasswordResetService::INVALID_MESSAGE];
                $fieldErrors = $result['fieldErrors'] ?? [];
                if ($invalidLink) {
                    $resetToken = '';
                }
            }
        } else {
            $resetToken = strtolower(trim((string) ($_GET['token'] ?? '')));
        }

        $this->render('auth/reset_password', [
            'title' => 'Reset Password | MBPHA TeleHealth Consultation System',
            'robots' => 'noindex, nofollow',
            'csrfToken' => Csrf::generate(),
            'resetToken' => $resetToken,
            'invalidLink' => $invalidLink,
            'errors' => $errors,
            'fieldErrors' => $fieldErrors,
            'statusMessage' => Session::getFlash('status'),
            'bodyClass' => 'public-layout auth-login-layout',
            'invalidMessage' => PasswordResetService::INVALID_MESSAGE,
        ]);
    }

    public function logout(): void
    {
        if (!Csrf::verify((string) ($_POST['_token'] ?? ''))) {
            Session::flash('status', [
                'type' => 'danger',
                'message' => 'Unable to complete logout because the request could not be verified.',
            ]);
            Helper::redirect('/');
        }

        AuditLogService::record(
            'logout',
            'User logged out securely.',
            AuditLogService::ENTITY_AUTH
        );
        AuthService::logout();
        Session::flash('status', [
            'type' => 'success',
            'message' => 'You have been logged out securely.',
        ]);
        Helper::redirect('/');
    }

    private function redirectAuthenticatedUser(): void
    {
        $role = AuthService::getUserRole();
        Helper::redirect(AuthService::getRoleRedirectUrl((string) $role));
    }

    private function validateLoginRequest(string $email, string $password, string $csrfToken): array
    {
        $errors = [];

        if (!Csrf::verify($csrfToken)) {
            $errors[] = 'Your session security token is invalid. Please refresh the page and try again.';
        }

        if ($email === '') {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        return $errors;
    }

    private function getRegistrationFormData(): array
    {
        return [
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'dob' => trim((string) ($_POST['dob'] ?? '')),
            'gender' => trim((string) ($_POST['gender'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
        ];
    }

    private function validateRegistrationRequest(
        array $formData,
        string $password,
        string $confirmPassword,
        string $csrfToken,
        bool $acceptedTerms
    ): array {
        $errors = [];
        $allowedGenders = ['male', 'female', 'other'];

        if (!Csrf::verify($csrfToken)) {
            $errors[] = 'Your session security token is invalid. Please refresh the page and try again.';
        }

        if ($formData['full_name'] === '') {
            $errors[] = 'Full name is required.';
        } elseif (mb_strlen($formData['full_name']) > 255) {
            $errors[] = 'Full name must be 255 characters or fewer.';
        }

        if ($formData['email'] === '') {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address.';
        } else {
            try {
                if (User::findByEmail($formData['email'])) {
                    $errors[] = 'An account with this email address already exists.';
                }
            } catch (\Throwable $exception) {
                error_log('Registration validation error: ' . $exception->getMessage());
                $errors[] = 'Registration is temporarily unavailable. Please try again later.';
            }
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (!Helper::isStrongPassword($password)) {
            $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.';
        }

        if ($confirmPassword === '') {
            $errors[] = 'Please confirm your password.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if ($formData['dob'] !== '') {
            $dob = \DateTime::createFromFormat('Y-m-d', $formData['dob']);
            $isValidDate = $dob && $dob->format('Y-m-d') === $formData['dob'];

            if (!$isValidDate) {
                $errors[] = 'Date of birth must be a valid date.';
            } elseif ($dob > new \DateTime('today')) {
                $errors[] = 'Date of birth cannot be in the future.';
            }
        }

        if ($formData['gender'] !== '' && !in_array($formData['gender'], $allowedGenders, true)) {
            $errors[] = 'Please select a valid gender option.';
        }

        if (mb_strlen($formData['address']) > 1000) {
            $errors[] = 'Address must be 1000 characters or fewer.';
        }

        if (!$acceptedTerms) {
            $errors[] = 'You must accept the terms and privacy notice to continue.';
        }

        return $errors;
    }
}
