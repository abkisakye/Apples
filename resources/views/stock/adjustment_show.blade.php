@extends('layouts.app', ['title' => 'Stock Adjustment Details'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Stock Adjustment {{ $referenceNo }}</h2>
            <p>Review this adjustment document before printing or using it during stock reconciliation.</p>
        </div>
        <div class="actions">
            <a href="{{ route('stock.adjustments.print', $referenceNo) }}" target="_blank" class="button-link primary">Full Print</a>
            <a href="{{ route('stock.adjustments.index') }}" class="button-link">Back to Adjustment Log</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Adjustment Date</div><div class="value">{{ $adjustmentDate->format('d M Y') }}</div></div>
        <div class="card"><div class="label">Store</div><div class="value">{{ $store?->name ?? '-' }}</div></div>
        <div class="card"><div class="label">Type</div><div class="value">{{ \Illuminate\Support\Str::headline($movementType) }}</div></div>
        <div class="card"><div class="label">Lines</div><div class="value">{{ number_format($rows->count()) }}</div></div>
        <div class="card"><div class="label">Total Quantity</div><div class="value">{{ number_format((float) $rows->sum(fn ($row) => max($row->quantity_in, $row->quantity_out)), 0) }}</div></div>
    </section>

    <section class="grid-two" style="margin-bottom: 16px;">
        <div class="panel">
            <h3>Adjustment Summary</h3>
            <p class="list-note">Use this area to confirm what changed, where it happened, and why the manual stock movement was posted.</p>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Reference</th><td>{{ $referenceNo }}</td></tr>
                    <tr><th style="text-align:left;">Adjustment Date</th><td>{{ $adjustmentDate->format('d M Y') }}</td></tr>
                    <tr><th style="text-align:left;">Store</th><td>{{ $store?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Type</th><td>{{ \Illuminate\Support\Str::headline($movementType) }}</td></tr>
                    <tr><th style="text-align:left;">Remarks</th><td>{{ $remarks ?: '-' }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Action Guide</h3>
            <p class="list-note">Keep this note for stock investigations, approvals, and store reconciliation checks.</p>
            <div class="actions" style="margin-top: 14px;">
                <a href="{{ route('stock.adjustments.print', $referenceNo) }}" target="_blank" class="button-link primary">Open Adjustment Note</a>
                <a href="{{ route('stock.adjustments.index') }}" class="button-link">View All Adjustments</a>
            </div>
            <p class="list-note" style="margin-top: 14px;">Print the note when managers or store keepers need a physical approval copy.</p>
        </div>
    </section>

    <section class="panel">
        <h3>Adjustment Items</h3>
        <p class="list-note">Use these lines to confirm what was increased or reduced outside normal sales and purchases.</p>
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
                            <td>{{ number_format((float) max($row->quantity_in, $row->quantity_out), 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if (session('auto_print_document'))
        <script>
            (() => {
                const printUrl = @json(route('stock.adjustments.print', $referenceNo));
                const popup = window.open(printUrl, '_blank', 'noopener,noreferrer');
                if (popup) popup.focus();
            })();
        </script>
    @endif
@endsection
