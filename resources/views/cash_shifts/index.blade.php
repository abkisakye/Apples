@extends('layouts.app', ['title' => 'Cash Shifts'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <style>
        .desk-cards {
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }
        .desk-cards .card {
            padding: 10px;
        }
        .desk-cards .card .value {
            font-size: 1.08rem;
        }
        .desk-filters {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr)) auto auto;
            gap: 8px;
            align-items: center;
            margin-bottom: 10px;
        }
        .desk-filters select {
            min-width: 0;
        }
        .desk-panel {
            padding: 10px;
        }
        .desk-panel th,
        .desk-panel td {
            padding: 7px 6px;
        }
        @media (max-width: 980px) {
            .desk-filters {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <div class="page-head">
        <div>
            <h2>Cash Shifts</h2>
            <p>Open and close cashier sessions so the owner can compare expected cash with actual counted cash at the end of the shift.</p>
        </div>
        <div class="actions">
            @if ($activeShift)
                <a href="{{ route('cash-shifts.show', $activeShift) }}" class="button-link primary">My Open Shift</a>
            @else
                <a href="{{ route('cash-shifts.create') }}" class="button-link primary">Open Shift</a>
            @endif
            <a href="{{ route('cash-shifts.index', ['status' => 'open']) }}" class="button-link {{ $status === 'open' ? 'primary' : '' }}">Open</a>
            <a href="{{ route('cash-shifts.index', ['status' => 'closed']) }}" class="button-link {{ $status === 'closed' ? 'primary' : '' }}">Closed</a>
        </div>
    </div>

    <section class="cards desk-cards">
        <div class="card"><div class="label">Open Shifts</div><div class="value">{{ number_format($summary['open_count']) }}</div></div>
        <div class="card"><div class="label">Today Expected Cash</div><div class="value money">{{ $currency }} {{ number_format($summary['today_cash'], 0) }}</div></div>
        <div class="card"><div class="label">Today Difference</div><div class="value money">{{ $currency }} {{ number_format($summary['today_difference'], 0) }}</div></div>
    </section>

    <section class="panel desk-panel">
        <form method="get" class="filters desk-filters">
            <select name="status">
                <option value="">Any status</option>
                <option value="open" @selected($status === 'open')>Open</option>
                <option value="closed" @selected($status === 'closed')>Closed</option>
            </select>
            <select name="user_id">
                <option value="">All users</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((string) $userId === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
            <select name="period">
                <option value="">Any date</option>
                <option value="today" @selected($period === 'today')>Today</option>
                <option value="week" @selected($period === 'week')>This week</option>
            </select>
            <button type="submit">Filter</button>
            @if ($status !== '' || $userId > 0 || $period !== '')
                <a href="{{ route('cash-shifts.index') }}" class="button-link">Clear</a>
            @endif
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Shift</th>
                        <th>User</th>
                        <th>Store</th>
                        <th>Opened</th>
                        <th>Closed</th>
                        <th>Expected</th>
                        <th>Difference</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shifts as $shift)
                        <tr>
                            <td><a href="{{ route('cash-shifts.show', $shift) }}">{{ $shift->shift_no }}</a></td>
                            <td>{{ $shift->user?->name ?? '-' }}</td>
                            <td>{{ $shift->store?->name ?? '-' }}</td>
                            <td>{{ optional($shift->opened_at)->format('d M Y H:i') }}</td>
                            <td>{{ $shift->closed_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $shift->expected_cash, 0) }}</td>
                            <td class="money">{{ $shift->shortage_overage === null ? '-' : $currency.' '.number_format((float) $shift->shortage_overage, 0) }}</td>
                            <td><span class="badge {{ $shift->status === 'open' ? 'credit' : '' }}">{{ ucfirst($shift->status) }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">No cash shifts match this filter yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $shifts->links() }}
        </div>
    </section>
@endsection
