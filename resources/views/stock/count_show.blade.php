@extends('layouts.app', ['title' => 'Physical Stock Count Details'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Physical Stock Count {{ $referenceNo }}</h2>
            <p>Review the shelf count, the original system quantities, and the exact variance that was posted for this store.</p>
        </div>
        <div class="actions">
            <a href="{{ route('stock.counts.print', $referenceNo) }}" target="_blank" class="button-link primary">Full Print</a>
            <a href="{{ route('stock.counts.index') }}" class="button-link">Back to Count Log</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Count Date</div><div class="value">{{ $countDate->format('d M Y') }}</div></div>
        <div class="card"><div class="label">Store</div><div class="value">{{ $store?->name ?? '-' }}</div></div>
        <div class="card"><div class="label">Status</div><div class="value">{{ ucfirst($stockCount->status) }}</div></div>
        <div class="card"><div class="label">Counted By</div><div class="value">{{ $countedBy?->name ?? 'System User' }}</div></div>
        <div class="card"><div class="label">Assigned Staff</div><div class="value">{{ $assignedTo?->name ?? ($countedBy?->name ?? 'System User') }}</div></div>
        <div class="card"><div class="label">Lines</div><div class="value">{{ number_format($rows->count()) }}</div></div>
        <div class="card"><div class="label">Total Base Variance</div><div class="value">{{ number_format((float) $stockCount->total_variance_base_qty, 3) }}</div></div>
    </section>

    <section class="grid-two" style="margin-bottom:16px;">
        <div class="panel">
            <h3>Count Summary</h3>
            <p class="list-note">Use this summary when checking who counted, which store was involved, and what was posted into stock movement history.</p>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Reference</th><td>{{ $referenceNo }}</td></tr>
                    <tr><th style="text-align:left;">Count Date</th><td>{{ $countDate->format('d M Y') }}</td></tr>
                    <tr><th style="text-align:left;">Store</th><td>{{ $store?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Counted By</th><td>{{ $countedBy?->name ?? 'System User' }}</td></tr>
                    <tr><th style="text-align:left;">Assigned Staff</th><td>{{ $assignedTo?->name ?? ($countedBy?->name ?? 'System User') }}</td></tr>
                    <tr><th style="text-align:left;">Section</th><td>{{ $stockCount->section_name ?: '-' }}</td></tr>
                    <tr><th style="text-align:left;">Remarks</th><td>{{ $remarks ?: '-' }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Action Guide</h3>
            <p class="list-note">Keep this note for reconciliations, supervisor checks, and explaining differences between physical shelf stock and the previous system count.</p>
            <div class="actions" style="margin-top:14px;">
                @if ($stockCount->status === 'draft')
                    <a href="{{ route('stock.counts.create', ['draft_id' => $stockCount->id]) }}" class="button-link primary">Resume Draft</a>
                @else
                    <a href="{{ route('stock.counts.print', $referenceNo) }}" target="_blank" class="button-link primary">Open Count Note</a>
                @endif
                <a href="{{ route('stock.balances', ['store_id' => $store?->id]) }}" class="button-link">Store Stock Balance</a>
            </div>
        </div>
    </section>

    <section class="panel">
        <h3>Count Items</h3>
        <p class="list-note">These lines show the base-unit system stock, the physical count from entered packs/units, and the movement created by posting.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>System Base Stock</th>
                        <th>Physical Base Count</th>
                        <th>Entered Unit Breakdown</th>
                        <th>Friendly Breakdown</th>
                        <th>Variance Base Qty</th>
                        <th>Movement Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row->product?->name ?? '-' }}</td>
                            <td>{{ $row->system_base_label }}</td>
                            <td>{{ $row->physical_base_label }}</td>
                            <td>{{ $row->entered_unit_breakdown }}</td>
                            <td>{{ $row->friendly_breakdown }}</td>
                            <td>
                                <span class="badge {{ $row->variance_base_qty < 0 ? 'credit' : ($row->variance_base_qty > 0 ? 'success' : 'soft') }}">
                                    {{ $row->variance_base_label }}
                                </span>
                            </td>
                            <td><span class="badge soft">{{ $row->movement_created }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if (session('auto_print_document'))
        <script>
            (() => {
                const printUrl = @json(route('stock.counts.print', $referenceNo));
                const popup = window.open(printUrl, '_blank', 'noopener,noreferrer');
                if (popup) popup.focus();
            })();
        </script>
    @endif
@endsection
