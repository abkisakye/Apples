@extends('layouts.app', ['title' => 'Activity Log'])

@section('content')
    @php($logCollection = $logs->getCollection())
    <div class="page-head">
        <div>
            <h2>Activity Log</h2>
            <p>Review recent actions across logins, postings, follow-ups, reminders, and admin changes.</p>
        </div>
        <div class="actions">
            <a href="{{ route('users.index') }}" class="button-link">Users</a>
            <a href="{{ route('roles.matrix') }}" class="button-link">Permissions Matrix</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Entries</div><div class="value">{{ number_format($logs->total()) }}</div></div>
        <div class="card"><div class="label">Shown</div><div class="value">{{ number_format($logCollection->count()) }}</div></div>
        <div class="card"><div class="label">By Staff</div><div class="value">{{ number_format($logCollection->whereNotNull('user_id')->count()) }}</div></div>
        <div class="card"><div class="label">System Events</div><div class="value">{{ number_format($logCollection->whereNull('user_id')->count()) }}</div></div>
        <div class="card"><div class="label">Latest Event</div><div class="value">{{ optional($logCollection->first()?->created_at)->format('d M Y H:i') ?? '-' }}</div></div>
    </section>

    <section class="panel">
        <form method="get" class="filters">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search event, description, or subject">
            <select name="user_id">
                <option value="">All users</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected($userId === $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
            <select name="event">
                <option value="">All events</option>
                @foreach ($events as $event)
                    <option value="{{ $event }}" @selected($eventFilter === $event)>{{ \Illuminate\Support\Str::headline(str_replace('.', ' ', $event)) }}</option>
                @endforeach
            </select>
            <button type="submit">Filter</button>
        </form>
        <p class="list-note">Use this page when you need to confirm who posted a transaction, sent a reminder, changed a user, or logged into the system.</p>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Event</th>
                    <th>Description</th>
                    <th>Subject</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('d M Y H:i') }}</td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td>
                            <span class="badge {{ str_contains((string) $log->event, 'deleted') || str_contains((string) $log->event, 'failed') ? 'credit' : (str_contains((string) $log->event, 'created') || str_contains((string) $log->event, 'posted') ? 'success' : '') }}">
                                {{ \Illuminate\Support\Str::headline(str_replace('.', ' ', (string) $log->event)) }}
                            </span>
                        </td>
                        <td>
                            <div class="table-title">{{ $log->description ?? '-' }}</div>
                            @if (! empty($log->properties))
                                <div class="table-meta">{{ count($log->properties) }} detail{{ count($log->properties) === 1 ? '' : 's' }} recorded</div>
                            @endif
                        </td>
                        <td>{{ class_basename($log->subject_type ?? 'System') }} {{ $log->subject_id ? '#'.$log->subject_id : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <div class="pagination">{{ $logs->links() }}</div>
    </section>
@endsection
