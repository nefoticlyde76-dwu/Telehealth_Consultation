<?php

use App\Helpers\Helper;

$emailData = is_array($emailData ?? null) ? $emailData : [];

$userName = trim((string) ($emailData['userName'] ?? $fullName ?? 'there'));
if ($userName === '') {
    $userName = 'there';
}

$resetUrl = trim((string) ($emailData['resetUrl'] ?? $resetUrl ?? ''));
$contactUrl = trim((string) ($contactUrl ?? $emailData['contactUrl'] ?? ''));

$expiresMinutes = (int) ($emailData['expiresMinutes'] ?? 0);
if ($expiresMinutes < 1) {
    $ttlFromService = trim((string) ($ttlLabel ?? ''));
    if (preg_match('/(\d+)/', $ttlFromService, $ttlMatch) === 1) {
        $expiresMinutes = (int) $ttlMatch[1];
    } else {
        $expiresMinutes = 30;
    }
}

$expiresLabel = $expiresMinutes === 1 ? '1 minute' : $expiresMinutes . ' minutes';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset your MBPHA TeleHealth password</title>
</head>
<body style="margin:0;padding:0;background-color:#F8F9FA;font-family:Arial,Helvetica,sans-serif;color:#212529;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F8F9FA;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#FFFFFF;border:1px solid #DEE2E6;border-radius:12px;overflow:hidden;">
          <tr>
            <td style="background-color:#0F4C81;padding:24px 28px;">
              <p style="margin:0;font-size:20px;font-weight:700;color:#FFFFFF;">MBPHA TeleHealth</p>
              <p style="margin:6px 0 0;font-size:13px;color:#FFFFFF;">Milne Bay Provincial Health Authority</p>
            </td>
          </tr>
          <tr>
            <td style="padding:28px;">
              <p style="margin:0 0 16px;font-size:16px;">Dear <?= Helper::escape($userName) ?>,</p>
              <p style="margin:0 0 16px;font-size:15px;line-height:1.55;">
                We received a request to reset the password for your MBPHA TeleHealth account.
                Use the button below to choose a new password.
              </p>
              <p style="margin:24px 0;text-align:center;">
                <a href="<?= Helper::escape($resetUrl) ?>" style="display:inline-block;background-color:#0F4C81;color:#FFFFFF;text-decoration:none;font-weight:600;font-size:15px;padding:12px 24px;border-radius:8px;">
                  Reset My Password
                </a>
              </p>
              <p style="margin:0 0 16px;font-size:15px;line-height:1.55;">
                This link expires in <?= Helper::escape($expiresLabel) ?>.
              </p>
              <p style="margin:0 0 16px;font-size:14px;line-height:1.55;color:#6C757D;">
                If you did not request this password reset, you can safely ignore this email.
              </p>
              <?php if ($contactUrl !== ''): ?>
              <p style="margin:0;font-size:14px;line-height:1.55;">
                Support:
                <a href="<?= Helper::escape($contactUrl) ?>" style="color:#0F4C81;"><?= Helper::escape($contactUrl) ?></a>
              </p>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <td style="background-color:#F8F9FA;padding:18px 28px;border-top:1px solid #DEE2E6;">
              <p style="margin:0;font-size:13px;font-weight:600;color:#0F4C81;">MBPHA TeleHealth</p>
              <p style="margin:4px 0 0;font-size:12px;color:#6C757D;">Milne Bay Provincial Health Authority</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
