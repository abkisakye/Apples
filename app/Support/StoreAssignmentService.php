<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class StoreAssignmentService
{
    public function resolveStoreId(?int $requestedStoreId, ?User $user, AccessService $access, string $field = 'store_id'): int
    {
        $defaultStoreId = $user?->default_store_id ? (int) $user->default_store_id : null;
        $requestedStoreId = $requestedStoreId ?: $defaultStoreId;

        if (! $requestedStoreId) {
            throw ValidationException::withMessages([
                $field => 'Assign a default store to this user before posting transactions from this account.',
            ]);
        }

        if ($this->canChooseAnyStore($user, $access)) {
            return (int) $requestedStoreId;
        }

        if (! $defaultStoreId) {
            throw ValidationException::withMessages([
                $field => 'This user does not have an assigned store yet.',
            ]);
        }

        if ((int) $requestedStoreId !== $defaultStoreId) {
            throw ValidationException::withMessages([
                $field => 'You can only post transactions to your assigned store from this account.',
            ]);
        }

        return $defaultStoreId;
    }

    public function canChooseAnyStore(?User $user, AccessService $access): bool
    {
        return $access->hasRole('admin')
            || $access->hasRole('manager')
            || $access->can('sales.override');
    }
}
