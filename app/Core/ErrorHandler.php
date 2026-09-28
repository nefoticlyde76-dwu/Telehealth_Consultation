<?php

namespace App\Core;

use App\Config\App as AppConfig;

class ErrorHandler
{
    public static function register(): void
    {
        $debug = self::isDebug();

        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('display_startup_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        $logFile = self::logFilePath();
        if ($logFile !== null) {
            ini_set('error_log', $logFile);
        }

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        // PHP upgrade deprecations must not abort business operations
        // (e.g. Daily room creation during consultation approval).
        if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
            error_log(sprintf('Deprecated: %s in %s:%d', $errstr, $errfile, $errline));
            return true;
        }

        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public static function handleException(\Throwable $exception): void
    {
        if (self::isDebug()) {
            echo '<h1>Uncaught Exception</h1>';
            echo '<p>' . htmlspecialchars($exception->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
        } else {
            http_response_code(500);
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
            }
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex, nofollow"><meta name="googlebot" content="noindex, nofollow"><title>Unable to load page</title></head><body style="font-family: system-ui, sans-serif; background:' . \App\Helpers\Palette::LIGHT_GRAY . '; color:' . \App\Helpers\Palette::DARK_NAVY . '; padding:48px 24px;">';
            echo '<h1 style="font-size:1.25rem;">Unable to load this page</h1>';
            echo '<p>Please refresh the page or try again. If the problem continues, contact MBPHA TeleHealth administration.</p>';
            echo '</body></html>';
        }

        self::logError($exception);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            self::handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    private static function isDebug(): bool
    {
        try {
            return !empty(AppConfig::getConfig()['debug']);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function logFilePath(): ?string
    {
        $logDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logDir) && !@mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            return null;
        }

        return $logDir . DIRECTORY_SEPARATOR . 'error.log';
    }

    private static function logError(\Throwable $exception): void
    {
        $logPath = self::logFilePath();
        if ($logPath === null) {
            error_log($exception->getMessage());
            return;
        }

        $message = sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s\n",
            date('Y-m-d H:i:s'),
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        file_put_contents($logPath, $message, FILE_APPEND);
    }
}
