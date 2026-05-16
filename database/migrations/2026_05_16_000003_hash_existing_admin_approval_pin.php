<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $currentPin = DB::table('business_settings')
            ->where('key', 'admin_approval_pin')
            ->value('value');

        if (! is_string($currentPin) || trim($currentPin) === '') {
            return;
        }

        if ($this->looksHashed($currentPin)) {
            return;
        }

        DB::table('business_settings')
            ->where('key', 'admin_approval_pin')
            ->update([
                'value' => Hash::make($currentPin),
            ]);
    }

    public function down(): void
    {
        // Existing PIN hashes are intentionally not reverted to plain text.
    }

    private function looksHashed(string $value): bool
    {
        return password_get_info($value)['algo'] !== null;
    }
};
