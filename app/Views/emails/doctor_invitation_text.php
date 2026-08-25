<?php

$fullName = (string) ($fullName ?? 'Clinician');
$setupUrl = (string) ($setupUrl ?? '');
$contactUrl = (string) ($contactUrl ?? '');
$ttlLabel = (string) ($ttlLabel ?? '');

echo "MBPHA TeleHealth – Set Up Your Doctor Account\n\n";
echo 'Dear ' . $fullName . ",\n\n";
echo "An MBPHA administrator has created a TeleHealth clinician account for you.\n";
echo "Before you can sign in, you need to create your password.\n\n";
echo "Set up your password:\n";
echo $setupUrl . "\n\n";
echo 'This link expires in ' . $ttlLabel . ".\n\n";
echo "If you were not expecting this email, do not click the link and contact MBPHA TeleHealth administration.\n\n";
echo "Support:\n";
echo $contactUrl . "\n\n";
echo "MBPHA TeleHealth\n";
echo "Milne Bay Provincial Health Authority\n";
