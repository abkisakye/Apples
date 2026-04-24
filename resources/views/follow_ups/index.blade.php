@extends('layouts.app', ['title' => 'Follow-ups'])

@section('content')
    @php($followUpCollection = $followUps instanceof \Illuminate\Support\Collection ? $followUps : collect($followUps))
    @php($openItems = $followUpCollection->where('status', '!=', 'completed'))
    @php($overdueItems = $openItems->filter(fn ($followUp) => optional($followUp->reminder_date)?->isPast()))
    @php($todayItems = $openItems->filter(fn ($followUp) => optional($followUp->reminder_date)?->isToday()))
    <div class="page-head">
        <div>
            <h2>Follow-ups</h2>
            <p>Track overdue accounts, see who owns the next step, and close each follow-up once the issue is resolved.</p>
        </div>
        <div class="actions">
            <a href="{{ route('follow-ups.create') }}" class="button-link primary">New Follow-up</a>
            @if ($access->can('reports.view'))
                <a href="{{ route('reports.customer-aging') }}" class="button-link">Customer Aging</a>
                <a href="{{ route('reports.supplier-aging') }}" class="button-link">Supplier Aging</a>
            @endif
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Open Items</div><div class="value">{{ number_format($openItems->count()) }}</div></div>
        <div class="card"><div class="label">Due Today</div><div class="value">{{ number_format($todayItems->count()) }}</div></div>
        <div class="card"><div class="label">Overdue</div><div class="value">{{ number_format($overdueItems->count()) }}</div></div>
        <div class="card"><div class="label">Completed</div><div class="value">{{ number_format($followUpCollection->where('status', 'completed')->count()) }}</div></div>
        <div class="card"><div class="label">Email Channel</div><div class="value">{{ number_format($followUpCollection->filter(fn ($followUp) => str_contains(strtolower((string) $followUp->channel), 'email'))->count()) }}</div></div>
        <div class="card"><div class="label">SMS Channel</div><div class="value">{{ number_format($followUpCollection->filter(fn ($followUp) => str_contains(strtolower((string) $followUp->channel), 'sms'))->count()) }}</div></div>
    </section>

    <section class="panel">
        <p class="list-note">Use this page like a small action queue: review the account, send a reminder, post the related payment, then mark the follow-up complete.</p>
        <div class="table-wrap table-mobile-friendly">
            <table>
                <thead>
                    <tr>
                        <th>Follow-up</th>
                        <th>Ownership</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($followUps as $followUp)
                        <tr>
                            <td>
                                <div class="cell-stack">
                                    <div class="table-title">{{ optional($followUp->reminder_date)->format('d M Y') ?: '-' }}</div>
                                    @if ($followUp->sale)
                                        <div class="table-title"><a href="{{ route('sales.show', $followUp->sale) }}">{{ $followUp->sale->sale_no }}</a></div>
                                        <div class="table-meta">{{ $followUp->customer?->name ?? '-' }} / Customer account</div>
                                    @elseif ($followUp->purchase)
                                        <div class="table-title"><a href="{{ route('purchases.show', $followUp->purchase) }}">{{ $followUp->purchase->purchase_no }}</a></div>
                                        <div class="table-meta">{{ $followUp->supplier?->name ?? '-' }} / Supplier account</div>
                                    @else
                                        <div class="table-meta">Reference not linked.</div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="cell-stack">
                                    <div class="table-title">{{ $followUp->assignedUser?->name ?? 'Not assigned' }}</div>
                                    <div class="status-inline">
                                        <span class="badge soft">{{ $followUp->channel ?: 'No channel' }}</span>
                                        <span class="badge soft">Last sent: {{ $followUp->last_sent_at?->format('d M Y H:i') ?? 'Not yet sent' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="cell-stack">
                                    <div class="status-inline">
                                        <span class="badge {{ $followUp->status === 'completed' ? 'success' : (optional($followUp->reminder_date)?->isPast() ? 'credit' : '') }}">
                                            {{ ucfirst($followUp->status) }}
                                        </span>
                                        @if ($followUp->status === 'completed')
                                            <span class="badge success">Closed {{ optional($followUp->follow_up_date)->format('d M Y') ?: 'recently' }}</span>
                                        @elseif (optional($followUp->reminder_date)?->isPast())
                                            <span class="badge credit">Overdue</span>
                                        @elseif (optional($followUp->reminder_date)?->isToday())
                                            <span class="badge">Due today</span>
                                        @else
                                            <span class="badge">Upcoming</span>
                                        @endif
                                    </div>
                                    <div class="table-meta">
                                        {{ $followUp->notes ?: ($followUp->status === 'completed' ? 'This follow-up has already been closed.' : 'Waiting for the next action on this account.') }}
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($followUp->status !== 'completed')
                                    <div class="action-stack">
                                        @if ($followUp->sale && $access->can('customers.statement') && $followUp->customer)
                                            <a href="{{ route('customers.statement', $followUp->customer) }}" class="action-chip">Statement</a>
                                        @elseif ($followUp->purchase && $access->can('suppliers.statement') && $followUp->supplier)
                                            <a href="{{ route('suppliers.statement', $followUp->supplier) }}" class="action-chip">Statement</a>
                                        @endif

                                        @if ($followUp->sale && $access->can('customer_payments.manage') && $followUp->customer)
                                            <a href="{{ route('customer-payments.create', ['customer_id' => $followUp->customer->id]) }}" class="action-chip primary">Record Payment</a>
                                        @elseif ($followUp->purchase && $access->can('supplier_payments.manage') && $followUp->supplier)
                                            <a href="{{ route('supplier-payments.create', ['supplier_id' => $followUp->supplier->id]) }}" class="action-chip primary">Record Payment</a>
                                        @endif

                                        <form method="post" action="{{ route('follow-ups.send', $followUp) }}">
                                            @csrf
                                            <input type="hidden" name="channel" value="email">
                                            <button type="submit" class="action-chip soft">Email</button>
                                        </form>
                                        <form method="post" action="{{ route('follow-ups.send', $followUp) }}">
                                            @csrf
                                            <input type="hidden" name="channel" value="sms">
                                            <button type="submit" class="action-chip soft">SMS</button>
                                        </form>
                                        <form method="post" action="{{ route('follow-ups.complete', $followUp) }}">
                                            @csrf
                                            <button type="submit" class="action-chip good">Complete</button>
                                        </form>
                                    </div>
                                @else
                                    <div class="action-stack">
                                        <span class="action-chip good">Completed</span>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
