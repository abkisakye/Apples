@extends('layouts.app', ['title' => 'Transfer Log'])

@section('content')
    @php($transferCollection = $rows instanceof \Illuminate\Support\Collection ? $rows : collect($rows))
    <div class="page-head">
        <div>
            <h2>Stock Transfer Log</h2>
            <p>Review the transfer documents that moved stock from one store to another.</p>
        </div>
        <div class="actions">
            <a href="{{ route('stock.transfers.create') }}" class="button-link primary">New Transfer</a>
            <a href="{{ route('stock.balances') }}" class="button-link">Back to Stock</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Transfers Logged</div><div class="value">{{ number_format($transferCollection->count()) }}</div></div>
        <div class="card"><div class="label">Total Lines</div><div class="value">{{ number_format((int) $transferCollection->sum('line_count')) }}</div></div>
        <div class="card"><div class="label">Latest Transfer</div><div class="value">{{ optional($transferCollection->first()?->transaction_date ? \Carbon\Carbon::parse($transferCollection->first()->transaction_date) : null)->format('d M Y') ?? '-' }}</div></div>
    </section>

    <section class="panel">
        <p class="list-note">Open the print view whenever you need a transfer note for store handover or internal checking.</p>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Date</th>
                    <th>Lines</th>
                    <th>Print</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td><a href="{{ route('stock.transfers.show', $row->reference_no) }}">{{ $row->reference_no }}</a></td>
                        <td>{{ \Carbon\Carbon::parse($row->transaction_date)->format('d M Y') }}</td>
                        <td>{{ $row->line_count }}</td>
                        <td><a href="{{ route('stock.transfers.print', $row->reference_no) }}" target="_blank">Print</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="muted">No stock transfers recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </section>
@endsection
