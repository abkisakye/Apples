<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $userId = $request->integer('user_id');
        $event = trim((string) $request->string('event'));

        return view('activity_logs.index', [
            'logs' => ActivityLog::query()
                ->with('user:id,name')
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($inner) use ($search) {
                        $inner->where('event', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('subject_type', 'like', "%{$search}%");
                    });
                })
                ->when($userId > 0, fn ($query) => $query->where('user_id', $userId))
                ->when($event !== '', fn ($query) => $query->where('event', $event))
                ->latest()
                ->paginate(50)
                ->withQueryString(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'events' => ActivityLog::query()
                ->select('event')
                ->distinct()
                ->orderBy('event')
                ->pluck('event'),
            'search' => $search,
            'userId' => $userId,
            'eventFilter' => $event,
        ]);
    }
}
