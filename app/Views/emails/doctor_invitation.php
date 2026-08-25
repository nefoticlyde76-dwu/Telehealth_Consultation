<?php

use App\Helpers\Helper;

$fullName = (string) ($fullName ?? 'Clinician');
$setupUrl = (string) ($setupUrl ?? '');
$contactUrl = (string) ($contactUrl ?? '');
$ttlLabel = (string) ($ttlLabel ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MBPHA TeleHealth – Set Up Your Doctor Account</title>
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
              <p style="margin:0 0 16px;font-size:16px;">Dear <?= Helper::escape($fullName) ?>,</p>
              <p style="margin:0 0 16px;font-size:15px;line-height:1.55;">
                An MBPHA administrator has created a TeleHealth clinician account for you.
                Before you can sign in, you need to create your own password.
              </p>
              <p style="margin:24px 0;text-align:center;">
                <a href="<?= Helper::escape($setupUrl) ?>" style="display:inline-block;background-color:#0F4C81;color:#FFFFFF;text-decoration:none;font-weight:600;font-size:15px;padding:12px 24px;border-radius:8px;">
                  Set Up My Password
                </a>
              </p>
              <p style="margin:0 0 16px;font-size:15px;line-height:1.55;">
                This link expires in <?= Helper::escape($ttlLabel) ?>.
              </p>
              <p style="margin:0 0 16px;font-size:14px;line-height:1.55;color:#6C757D;">
                If you were not expecting this email, please do not click the link and contact MBPHA TeleHealth administration.
              </p>
              <p style="margin:0;font-size:14px;line-height:1.55;">
                Support:
                <a href="<?= Helper::escape($contactUrl) ?>" style="color:#0F4C81;"><?= Helper::escape($contactUrl) ?></a>
              </p>
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
