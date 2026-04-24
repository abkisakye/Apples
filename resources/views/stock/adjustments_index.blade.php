@extends('layouts.app', ['title' => 'Adjustment Log'])

@section('content')
    @php($adjustmentCollection = $rows instanceof \Illuminate\Support\Collection ? $rows : collect($rows))
    <div class="page-head">
        <div>
            <h2>Stock Adjustment Log</h2>
            <p>Review stock increase and decrease documents used to correct balances after checks or losses.</p>
        </div>
        <div class="actions">
            <a href="{{ route('stock.adjustments.create') }}" class="button-link primary">New Adjustment</a>
            <a href="{{ route('stock.balances') }}" class="button-link">Back to Stock</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Adjustments Logged</div><div class="value">{{ number_format($adjustmentCollection->count()) }}</div></div>
        <div class="card"><div class="label">Increase Entries</div><div class="value">{{ number_format($adjustmentCollection->where('movement_type', 'adjustment_in')->count()) }}</div></div>
        <div class="card"><div class="label">Decrease Entries</div><div class="value">{{ number_format($adjustmentCollection->where('movement_type', 'adjustment_out')->count()) }}</div></div>
        <div class="card"><div class="label">Total Lines</div><div class="value">{{ number_format((int) $adjustmentCollection->sum('line_count')) }}</div></div>
    </section>

    <section class="panel">
        <p class="list-note">Use these records when you need to explain why stock was increased or reduced outside the normal purchase and sales flow.</p>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Lines</th>
                    <th>Print</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td><a href="{{ route('stock.adjustments.show', $row->reference_no) }}">{{ $row->reference_no }}</a></td>
                        <td>{{ \Carbon\Carbon::parse($row->transaction_date)->format('d M Y') }}</td>
                        <td>{{ \Illuminate\Support\Str::headline($row->movement_type) }}</td>
                        <td>{{ $row->line_count }}</td>
                        <td><a href="{{ route('stock.adjustments.print', $row->reference_no) }}" target="_blank">Print</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">No stock adjustments recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </section>
@endsection
