<?php

namespace App\Support;

use App\Models\Store;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class StoreAssignmentService
{
    private const OPERATING_SHOP_NAME = 'Apples Of Gold';

    public function resolveStoreId(?int $requestedStoreId, ?User $user, AccessService $access, string $field = 'store_id'): int
    {
        $operatingStoreId = $this->operatingStoreId();
        $assignedStoreId = $user?->default_store_id ? (int) $user->default_store_id : $operatingStoreId;
        $requestedStoreId = $requestedStoreId ?: $assignedStoreId;

        if (! $requestedStoreId && ! $assignedStoreId) {
            throw ValidationException::withMessages([
                $field => 'Create the Apples Of Gold shop record before posting transactions.',
            ]);
        }

        if (! $this->isSingleShopMode() && $this->canChooseAnyStore($user, $access)) {
            return (int) $requestedStoreId;
        }

        if (! $assignedStoreId) {
            throw ValidationException::withMessages([
                $field => 'This user is not linked to the Apples Of Gold shop yet.',
            ]);
        }

        if ($requestedStoreId && (int) $requestedStoreId !== $assignedStoreId) {
            throw ValidationException::withMessages([
                $field => 'Transactions can only be posted to Apples Of Gold from this account.',
            ]);
        }

        return $assignedStoreId;
    }

    public function canChooseAnyStore(?User $user, AccessService $access): bool
    {
        return $access->hasRole('admin')
            || $access->hasRole('manager')
            || $access->can('sales.override');
    }

    public function operatingStoreId(): ?int
    {
        return Store::query()
            ->where('name', self::OPERATING_SHOP_NAME)
            ->value('id')
            ?: Store::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');
    }

    private function isSingleShopMode(): bool
    {
        return Store::query()->where('is_active', true)->count() <= 1;
    }
}
