<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function generateTemporaryPassword(int $length = 12): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
    $maxIndex = strlen($alphabet) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $maxIndex)];
    }

    return $password;
}

$legacyUsers = User::query()
    ->with('role')
    ->where('is_legacy_user', true)
    ->orderBy('username')
    ->get();

$rows = [];

foreach ($legacyUsers as $user) {
    $temporaryPassword = generateTemporaryPassword();

    $user->forceFill([
        'password' => Hash::make($temporaryPassword),
    ])->save();

    $rows[] = [
        'username' => $user->username ?: '-',
        'name' => $user->name ?: '-',
        'role' => $user->role?->name ?? 'no-role',
        'department' => $user->legacyDepartmentName() ?? '-',
        'status' => $user->is_active ? 'active' : 'inactive',
        'temporary_password' => $temporaryPassword,
    ];
}

$lines = [];
$lines[] = '# Private User Access Sheet';
$lines[] = '';
$lines[] = 'Generated on '.now()->format('d M Y H:i');
$lines[] = '';
$lines[] = '> This file contains temporary passwords for imported legacy users. Remove or secure it after handover.';
$lines[] = '';
$lines[] = '| Username | Full Name | Role | Department | Status | Temporary Password |';
$lines[] = '|---|---|---|---|---|---|';

foreach ($rows as $row) {
    $lines[] = sprintf(
        '| %s | %s | %s | %s | %s | %s |',
        $row['username'],
        $row['name'],
        $row['role'],
        $row['department'],
        $row['status'],
        $row['temporary_password']
    );
}

$outputPath = __DIR__.'/../docs/PRIVATE_USER_ACCESS_SHEET.md';
file_put_contents($outputPath, implode(PHP_EOL, $lines).PHP_EOL);

echo 'Reset '.count($rows).' legacy user passwords.'.PHP_EOL;
echo 'Wrote '.realpath($outputPath).PHP_EOL;
