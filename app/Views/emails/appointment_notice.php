<?php

use App\Helpers\Helper;

$title = trim((string) ($title ?? 'MBPHA TeleHealth notice'));
$message = trim((string) ($message ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Helper::escape($title) ?></title>
</head>
<body style="margin:0;padding:0;background-color:#F8F9FA;font-family:Arial,Helvetica,sans-serif;color:#212529;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F8F9FA;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#FFFFFF;border:1px solid #DEE2E6;border-radius:12px;overflow:hidden;">
          <tr>
            <td style="background-color:#0F4C81;color:#FFFFFF;padding:20px 24px;font-size:18px;font-weight:600;">
              MBPHA TeleHealth
            </td>
          </tr>
          <tr>
            <td style="padding:24px;">
              <h1 style="margin:0 0 12px;font-size:20px;font-weight:700;color:#0F4C81;"><?= Helper::escape($title) ?></h1>
              <p style="margin:0;font-size:15px;line-height:1.5;color:#212529;"><?= Helper::escape($message) ?></p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
