@extends('layouts.app', ['title' => 'Demo Center'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Demo Center</h2>
            <p>Use this page when preparing a client presentation. It keeps the suggested flow, demo accounts, and quick links in one place.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.uat-center') }}" class="button-link">UAT Center</a>
            <a href="{{ route('dashboard') }}" class="button-link">Back to Dashboard</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Demo Accounts</div><div class="value">{{ number_format($demoUsers->count()) }}</div></div>
        <div class="card"><div class="label">Suggested Stops</div><div class="value">{{ number_format(count($demoFlow)) }}</div></div>
        <div class="card"><div class="label">Best Starting Role</div><div class="value">Admin</div></div>
    </section>

    <section class="grid-two" style="margin-bottom: 16px;">
        <div class="panel">
            <h3>Presentation Flow</h3>
            <p class="list-note">Follow this order for a smooth walk-through that starts broad, shows operations, and ends with management value.</p>
            <div style="display:grid; gap:12px; margin-top: 14px;">
                @foreach ($demoFlow as $step)
                    <div style="padding: 14px; border: 1px solid var(--line); border-radius: 16px; background: var(--panel-soft);">
                        <div style="display:flex; justify-content:space-between; gap:12px; align-items:start; flex-wrap:wrap;">
                            <div>
                                <strong>{{ $loop->iteration }}. {{ $step['title'] }}</strong>
                                <div class="muted" style="margin-top:6px;">{{ $step['summary'] }}</div>
                            </div>
                            <a href="{{ route($step['route']) }}" class="button-link primary">{{ $step['label'] }}</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="panel">
            <h3>Demo Accounts</h3>
            <p class="list-note">Use the role that matches the story you want to tell. Keep temporary passwords in the private handover sheet only.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($demoUsers as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->username }}</td>
                                <td><span class="badge">{{ \Illuminate\Support\Str::headline($user->role?->name ?? 'No role') }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="muted">No demo users are seeded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h3 style="margin-top: 16px;">Presentation Notes</h3>
            <div style="display:grid; gap:10px;">
                <div class="card" style="box-shadow:none; margin:0;">
                    <div class="label">Open Strong</div>
                    <div class="muted">Start with the dashboard so the client immediately sees sales, stock, and overdue control in one place.</div>
                </div>
                <div class="card" style="box-shadow:none; margin:0;">
                    <div class="label">Keep It Real</div>
                    <div class="muted">Use one cash sale, one customer statement, and one stock page instead of trying to show every feature in one sitting.</div>
                </div>
                <div class="card" style="box-shadow:none; margin:0;">
                    <div class="label">End With Value</div>
                    <div class="muted">Close on reports, permissions, and printouts so the client sees control, accountability, and decision support.</div>
                </div>
            </div>
            <div class="actions" style="margin-top: 14px;">
                <a href="{{ route('admin.uat-center') }}" class="button-link">UAT Center</a>
                <a href="{{ route('roles.matrix') }}" class="button-link">Permissions Matrix</a>
                <a href="{{ route('admin.readiness') }}" class="button-link">Readiness</a>
            </div>
        </div>
    </section>
@endsection
