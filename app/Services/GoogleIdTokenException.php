<?php

namespace App\Services;

/**
 * Controlled Google ID-token failure.
 *
 * The exception message is always generic so it can be shown later without
 * leaking cryptographic detail. Callers that need to audit or log should use
 * getReason() only.
 */
class GoogleIdTokenException extends \RuntimeException
{
    public const MALFORMED_TOKEN = 'malformed_token';
    public const INVALID_SIGNATURE = 'invalid_signature';
    public const UNKNOWN_KID = 'unknown_kid';
    public const INVALID_ALGORITHM = 'invalid_algorithm';
    public const INVALID_ISSUER = 'invalid_issuer';
    public const INVALID_AUDIENCE = 'invalid_audience';
    public const EXPIRED_TOKEN = 'expired_token';
    public const INVALID_IAT = 'invalid_iat';
    public const MISSING_SUB = 'missing_sub';
    public const MISSING_EMAIL = 'missing_email';
    public const EMAIL_UNVERIFIED = 'email_unverified';
    public const INVALID_CLAIMS = 'invalid_claims';
    public const JWKS_UNAVAILABLE = 'jwks_unavailable';

    public function __construct(private string $reason)
    {
        parent::__construct('The Google identity token could not be verified.');
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
