@extends('layouts.app', ['title' => 'UAT Center'])

@section('content')
    <div class="page-head">
        <div>
            <h2>UAT Center</h2>
            <p>Use this page to run a real mini supermarket acceptance pass. It keeps the test tracks, expected outcomes, and sign-off points in one place.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.demo-center') }}" class="button-link">Demo Center</a>
            <a href="{{ route('admin.readiness') }}" class="button-link">Readiness</a>
            <a href="{{ route('dashboard') }}" class="button-link">Back to Dashboard</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Test Tracks</div><div class="value">{{ number_format(count($tracks)) }}</div></div>
        <div class="card"><div class="label">Guided Steps</div><div class="value">{{ number_format(collect($tracks)->sum(fn ($track) => count($track['steps']))) }}</div></div>
        <div class="card"><div class="label">Sign-Off Checks</div><div class="value">{{ number_format(count($signOff)) }}</div></div>
        <div class="card"><div class="label">Best Use</div><div class="value">Real User Rehearsal</div></div>
    </section>

    <section class="panel" style="margin-bottom: 16px;">
        <h3>How To Use This Page</h3>
        <p class="list-note">Pick one role at a time, run the steps in order, and note any confusion about wording, button placement, speed, or printed output. This should feel like a real store day, not a feature tour.</p>
        <div style="display:grid; gap:10px; margin-top: 14px;">
            <div class="card" style="box-shadow:none; margin:0;">
                <div class="label">1. Prepare Data</div>
                <div class="muted">Use realistic products, customers, suppliers, and balances so reports and statements tell a believable story.</div>
            </div>
            <div class="card" style="box-shadow:none; margin:0;">
                <div class="label">2. Test On Real Devices</div>
                <div class="muted">Run the same flow on desktop, tablet, and mobile so the cashier and manager experience feels consistent.</div>
            </div>
            <div class="card" style="box-shadow:none; margin:0;">
                <div class="label">3. Sign Off By Role</div>
                <div class="muted">Do not move on just because the page loaded. Confirm that staff understand what to do without extra explanation.</div>
            </div>
        </div>
    </section>

    <section style="display:grid; gap:16px; margin-bottom:16px;">
        @foreach ($tracks as $track)
            <div class="panel">
                <div style="display:flex; justify-content:space-between; gap:14px; align-items:start; flex-wrap:wrap;">
                    <div>
                        <h3 style="margin-bottom: 6px;">{{ $track['title'] }}</h3>
                        <p class="list-note">{{ $track['summary'] }}</p>
                    </div>
                    <div class="badge">{{ $track['owner'] }}</div>
                </div>

                <div class="card" style="box-shadow:none; margin:14px 0 0;">
                    <div class="label">Expected Success</div>
                    <div class="muted">{{ $track['success'] }}</div>
                </div>

                <div style="display:grid; gap:12px; margin-top: 14px;">
                    @foreach ($track['steps'] as $step)
                        <div style="padding: 14px; border: 1px solid var(--line); border-radius: 16px; background: var(--panel-soft);">
                            <div style="display:flex; justify-content:space-between; gap:12px; align-items:start; flex-wrap:wrap;">
                                <div>
                                    <strong>{{ $loop->iteration }}. {{ $step['title'] }}</strong>
                                    <div class="muted" style="margin-top:6px;">{{ $step['summary'] }}</div>
                                </div>
                                <a href="{{ route($step['route'], $step['route_params'] ?? []) }}" class="button-link primary">{{ $step['label'] }}</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Sign-Off Points</h3>
            <p class="list-note">Use these as the minimum acceptance points before calling the rehearsal successful.</p>
            <div style="display:grid; gap:10px; margin-top: 14px;">
                @foreach ($signOff as $item)
                    <div style="padding:12px; border:1px solid var(--line); border-radius:14px; background:var(--panel-soft);">
                        <div class="table-title">{{ $loop->iteration }}. {{ $item }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="panel">
            <h3>Support Commands</h3>
            <p class="list-note">Use these around the rehearsal when you want to check readiness, protect the database, or refresh compiled views.</p>
            <div style="display:grid; gap:10px; margin-top: 14px;">
                @foreach ($commands as $command)
                    <div style="padding:12px; border:1px solid var(--line); border-radius:14px; background:var(--panel-soft);">
                        <div class="table-title">{{ $command['label'] }}</div>
                        <code>{{ $command['command'] }}</code>
                    </div>
                @endforeach
            </div>

            <div class="actions" style="margin-top: 14px;">
                <a href="{{ route('roles.matrix') }}" class="button-link">Permissions Matrix</a>
                <a href="{{ route('reports.financial-summary') }}" class="button-link">Financial Summary</a>
                <a href="{{ route('follow-ups.index') }}" class="button-link">Follow-Ups</a>
            </div>
        </div>
    </section>
@endsection
