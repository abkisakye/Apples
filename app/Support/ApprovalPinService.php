<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

class ApprovalPinService
{
    public function isConfigured(): bool
    {
        return trim((string) config('business.admin_approval_pin', '')) !== '';
    }

    public function verify(?string $candidate): bool
    {
        $configuredPin = trim((string) config('business.admin_approval_pin', ''));

        if ($configuredPin === '' || $candidate === null || $candidate === '') {
            return false;
        }

        if ($this->looksHashed($configuredPin)) {
            return Hash::check($candidate, $configuredPin);
        }

        return hash_equals($configuredPin, $candidate);
    }

    public function makeHash(string $plainPin): string
    {
        return Hash::make($plainPin);
    }

    private function looksHashed(string $value): bool
    {
        return password_get_info($value)['algo'] !== null;
    }
}
