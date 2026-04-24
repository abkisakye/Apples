<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public function __construct(
        private readonly Request $request
    ) {
    }

    public function record(string $event, ?Model $subject = null, ?string $description = null, array $properties = []): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => $this->request->ip(),
            'description' => $description,
            'properties' => $properties,
        ]);
    }
}
