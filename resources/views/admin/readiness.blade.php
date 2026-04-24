@extends('layouts.app', ['title' => 'Production Readiness'])

@section('content')
    @php($readyCount = collect($checks)->where('ready', true)->count())
    <div class="page-head">
        <div>
            <h2>Production Readiness</h2>
            <p>Use this page to see what is already in good shape and what still needs attention before a real live deployment.</p>
        </div>
        <div class="actions">
            <a href="{{ route('settings.business.edit') }}" class="button-link">Business Settings</a>
            <a href="{{ route('admin.uat-center') }}" class="button-link">UAT Center</a>
            <a href="{{ route('admin.demo-center') }}" class="button-link">Demo Center</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Checks Passed</div><div class="value">{{ number_format($readyCount) }}</div></div>
        <div class="card"><div class="label">Checks Remaining</div><div class="value">{{ number_format(count($checks) - $readyCount) }}</div></div>
        <div class="card"><div class="label">Current Goal</div><div class="value">{{ $goal }}</div></div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Checklist by Area</h3>
            <p class="list-note">This is a fast summary of the current setup. Use it to see what is already safe and what still needs attention before a hosted rollout.</p>
            <div style="display:grid; gap:12px;">
                @foreach ($groupedChecks as $group)
                    <div style="padding: 12px; border: 1px solid var(--line); border-radius: 16px; background: var(--panel-soft);">
                        <div style="display:flex; justify-content:space-between; gap:10px; align-items:center; margin-bottom:10px; flex-wrap:wrap;">
                            <strong>{{ $group['section'] }}</strong>
                            <span class="badge {{ collect($group['checks'])->every(fn ($check) => $check['ready']) ? 'success' : 'credit' }}">
                                {{ collect($group['checks'])->every(fn ($check) => $check['ready']) ? 'Ready' : 'Needs Work' }}
                            </span>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Check</th>
                                        <th>Current Value</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['checks'] as $check)
                                        <tr>
                                            <td>
                                                <div class="table-title">{{ $check['label'] }}</div>
                                            </td>
                                            <td>{{ $check['value'] }}</td>
                                            <td>
                                                <span class="badge {{ $check['ready'] ? 'success' : 'credit' }}">
                                                    {{ $check['ready'] ? 'Ready' : 'Needs Work' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="panel">
            <h3>Recommended Next Actions</h3>
            <p class="list-note">These are the main steps to complete when you decide to move toward a real hosted deployment.</p>
            <div style="display:grid; gap:12px; margin-top: 14px;">
                @foreach ($checks as $check)
                    <div style="padding: 14px; border: 1px solid var(--line); border-radius: 16px; background: {{ $check['ready'] ? 'var(--panel-soft)' : '#fff7ef' }};">
                        <div style="display:flex; justify-content:space-between; gap:10px; align-items:center; flex-wrap:wrap;">
                            <strong>{{ $check['section'] }}: {{ $check['label'] }}</strong>
                            <span class="badge {{ $check['ready'] ? 'success' : 'credit' }}">{{ $check['ready'] ? 'Ready' : 'Action Needed' }}</span>
                        </div>
                        <div class="muted" style="margin-top:6px;">{{ $check['action'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="grid-two" style="margin-top: 14px;">
        <div class="panel">
            <h3>Operational Runbook</h3>
            <p class="list-note">Use these commands when preparing the hosted server, doing a go-live rehearsal, or taking a safe backup before risky changes.</p>
            <div style="display:grid; gap:10px;">
                <div style="padding:12px; border:1px solid var(--line); border-radius:14px; background:var(--panel-soft);">
                    <div class="table-title">Readiness Check</div>
                    <code>php artisan ops:go-live-check</code>
                </div>
                <div style="padding:12px; border:1px solid var(--line); border-radius:14px; background:var(--panel-soft);">
                    <div class="table-title">Database Backup</div>
                    <code>php artisan ops:backup-database</code>
                </div>
                <div style="padding:12px; border:1px solid var(--line); border-radius:14px; background:var(--panel-soft);">
                    <div class="table-title">Production Caches</div>
                    <code>php artisan config:cache</code><br>
                    <code>php artisan route:cache</code><br>
                    <code>php artisan view:cache</code>
                </div>
                <div style="padding:12px; border:1px solid var(--line); border-radius:14px; background:var(--panel-soft);">
                    <div class="table-title">Deployment Database Step</div>
                    <code>php artisan migrate --force</code>
                </div>
            </div>
        </div>

        <div class="panel">
            <h3>Deployment Focus</h3>
            <p class="list-note">These are the most important operating priorities for this supermarket system when you move it from internal use to a real client environment.</p>
            <div style="display:grid; gap:10px;">
                <div style="padding:12px; border:1px solid var(--line); border-radius:14px; background:var(--panel-soft);">
                    <div class="table-title">1. Data Safety</div>
                    <div class="muted">Move to MySQL, confirm backups, and prove restore steps before daily live use.</div>
                </div>
                <div style="padding:12px; border:1px solid var(--line); border-radius:14px; background:var(--panel-soft);">
                    <div class="table-title">2. Secure Access</div>
                    <div class="muted">Use production APP_KEY, HTTPS, secure cookies, and the real staff roles for day-to-day use.</div>
                </div>
                <div style="padding:12px; border:1px solid var(--line); border-radius:14px; background:var(--panel-soft);">
                    <div class="table-title">3. Background Operations</div>
                    <div class="muted">Use persistent sessions, cache, and queue settings so reminders, exports, and multi-user access stay stable.</div>
                </div>
                <div style="padding:12px; border:1px solid var(--line); border-radius:14px; background:var(--panel-soft);">
                    <div class="table-title">4. Business Identity</div>
                    <div class="muted">Complete business settings so receipts, invoices, statements, and follow-up messages show correct real-world details.</div>
                </div>
            </div>
        </div>
    </section>
@endsection
