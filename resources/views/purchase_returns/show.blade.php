@extends('layouts.app', ['title' => 'Purchase Return'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Purchase Return {{ $purchaseReturn->return_no }}</h2>
            <p>Review the returned items, supplier settlement effect, and print the supplier return document when needed.</p>
        </div>
        <div class="actions">
            <a href="{{ route('purchase-returns.print', $purchaseReturn) }}" target="_blank" class="button-link primary">Print</a>
            <a href="{{ route('purchases.show', $purchaseReturn->purchase) }}" class="button-link">Back To Purchase</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Purchase</div><div class="value">{{ $purchaseReturn->purchase?->purchase_no ?? '-' }}</div></div>
        <div class="card"><div class="label">Return Type</div><div class="value">{{ ucwords(str_replace('_', ' ', $purchaseReturn->return_type)) }}</div></div>
        <div class="card"><div class="label">Returned Value</div><div class="value money">{{ $currency }} {{ number_format((float) $purchaseReturn->returned_total, 0) }}</div></div>
        <div class="card"><div class="label">Refund</div><div class="value money">{{ $currency }} {{ number_format((float) $purchaseReturn->refund_amount, 0) }}</div></div>
        <div class="card"><div class="label">Supplier Credit</div><div class="value money">{{ $currency }} {{ number_format((float) $purchaseReturn->supplier_credit_amount, 0) }}</div></div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Returned Items</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Unit</th>
                            <th>Qty</th>
                            <th>Unit Cost</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchaseReturn->items as $item)
                            <tr>
                                <td>{{ $item->product?->name ?? '-' }}</td>
                                <td>{{ $item->productUnit?->unit_name ?? '-' }}</td>
                                <td>{{ number_format((float) $item->quantity, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $item->unit_cost, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $item->line_total, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h3>Return Summary</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Supplier</th><td>{{ $purchaseReturn->supplier?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Return Date</th><td>{{ optional($purchaseReturn->return_date)->format('d M Y') }}</td></tr>
                    <tr><th style="text-align:left;">Store</th><td>{{ $purchaseReturn->store?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Refund Mode</th><td>{{ $purchaseReturn->paymentMode?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Remarks</th><td>{{ $purchaseReturn->remarks ?? '-' }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    @if (session('auto_print_document'))
        <script>
            (() => {
                const popup = window.open(@json(route('purchase-returns.print', $purchaseReturn)), '_blank', 'noopener,noreferrer');
                if (popup) popup.focus();
            })();
        </script>
    @endif
@endsection
