@extends('layouts.app', ['title' => 'Stock Transfer Details'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Stock Transfer {{ $referenceNo }}</h2>
            <p>Review this transfer note before printing or handing over stock between stores.</p>
        </div>
        <div class="actions">
            <a href="{{ route('stock.transfers.print', $referenceNo) }}" target="_blank" class="button-link primary">Full Print</a>
            <a href="{{ route('stock.transfers.index') }}" class="button-link">Back to Transfer Log</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Transfer Date</div><div class="value">{{ $transferDate->format('d M Y') }}</div></div>
        <div class="card"><div class="label">From Store</div><div class="value">{{ $fromStore?->name ?? '-' }}</div></div>
        <div class="card"><div class="label">To Store</div><div class="value">{{ $toStore?->name ?? '-' }}</div></div>
        <div class="card"><div class="label">Lines</div><div class="value">{{ number_format($rows->count()) }}</div></div>
        <div class="card"><div class="label">Total Quantity</div><div class="value">{{ number_format((float) $rows->sum('quantity_out'), 0) }}</div></div>
    </section>

    <section class="grid-two" style="margin-bottom: 16px;">
        <div class="panel">
            <h3>Transfer Summary</h3>
            <p class="list-note">Use this area to confirm the route, quantity moved, and any note recorded during posting.</p>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Reference</th><td>{{ $referenceNo }}</td></tr>
                    <tr><th style="text-align:left;">Transfer Date</th><td>{{ $transferDate->format('d M Y') }}</td></tr>
                    <tr><th style="text-align:left;">From Store</th><td>{{ $fromStore?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">To Store</th><td>{{ $toStore?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Remarks</th><td>{{ $remarks ?: '-' }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Action Guide</h3>
            <p class="list-note">This helps the store team act on the document without hunting for the next step.</p>
            <div class="actions" style="margin-top: 14px;">
                <a href="{{ route('stock.transfers.print', $referenceNo) }}" target="_blank" class="button-link primary">Open Transfer Note</a>
                <a href="{{ route('stock.transfers.index') }}" class="button-link">View All Transfers</a>
            </div>
            <p class="list-note" style="margin-top: 14px;">Print this note when the source and destination stores need a signed handover record.</p>
        </div>
    </section>

    <section class="panel">
        <h3>Transfer Items</h3>
        <p class="list-note">These are the exact stock lines moved out of the source store into the destination store.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Unit</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row->product?->name ?? '-' }}</td>
                            <td>{{ $row->productUnit?->unit_name ?? '-' }}</td>
                            <td>{{ number_format((float) $row->quantity_out, 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

@endsection
