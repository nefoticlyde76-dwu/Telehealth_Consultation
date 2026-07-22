<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;

class HomeController extends Controller
{
    public function index(): void
    {
        $this->render('home/index', [
            'title' => 'Home | MBPHA TeleHealth Consultation System',
            'csrfToken' => Csrf::generate(),
            'contactErrors' => Session::getFlash('contact_errors', []),
            'contactStatus' => Session::getFlash('contact_status'),
            'contactOldInput' => Session::getFlash('contact_old_input', [
                'name' => '',
                'email' => '',
                'phone' => '',
                'subject' => '',
                'message' => '',
            ]),
        ]);
    }

    public function contact(): void
    {
        $formData = $this->getContactFormData();
        $errors = $this->validateContactForm($formData, (string) ($_POST['_token'] ?? ''));

        if (!empty($errors)) {
            Session::flash('contact_errors', $errors);
            Session::flash('contact_old_input', $formData);
            Helper::redirect('/#contact');
        }

        $logDirectory = dirname(__DIR__, 2) . '/logs';
        $logFile = $logDirectory . '/contact.log';
        $entry = json_encode([
            'submitted_at' => date('c'),
            'name' => $formData['name'],
            'email' => $formData['email'],
            'phone' => $formData['phone'],
            'subject' => $formData['subject'],
            'message' => $formData['message'],
        ], JSON_UNESCAPED_SLASHES);

        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0775, true);
        }

        $written = file_put_contents($logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            Session::flash('contact_status', [
                'type' => 'danger',
                'message' => 'Your inquiry could not be submitted right now. Please use the listed contact details.',
            ]);
            Session::flash('contact_old_input', $formData);
            Helper::redirect('/#contact');
        }

        Session::flash('contact_status', [
            'type' => 'success',
            'message' => 'Your inquiry has been received. Our team will review it through the configured contact channel.',
        ]);
        Helper::redirect('/#contact');
    }

    private function getContactFormData(): array
    {
        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'subject' => trim((string) ($_POST['subject'] ?? '')),
            'message' => trim((string) ($_POST['message'] ?? '')),
        ];
    }

    private function validateContactForm(array $formData, string $csrfToken): array
    {
        $errors = [];

        if (!Csrf::verify($csrfToken)) {
            $errors[] = 'Your session security token is invalid. Please refresh the page and try again.';
        }

        if ($formData['name'] === '') {
            $errors[] = 'Full name is required for contact inquiries.';
        }

        if ($formData['email'] === '') {
            $errors[] = 'Email address is required for contact inquiries.';
        } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid contact email address.';
        }

        if ($formData['subject'] === '') {
            $errors[] = 'Subject is required.';
        }

        if ($formData['message'] === '') {
            $errors[] = 'Message is required.';
        }

        if (mb_strlen($formData['name']) > 255 || mb_strlen($formData['subject']) > 255) {
            $errors[] = 'Name and subject must be 255 characters or fewer.';
        }

        if (mb_strlen($formData['phone']) > 30) {
            $errors[] = 'Phone number must be 30 characters or fewer.';
        }

        if (mb_strlen($formData['message']) > 2000) {
            $errors[] = 'Message must be 2000 characters or fewer.';
        }

        return $errors;
    }
}
