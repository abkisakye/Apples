@extends('layouts.app', ['title' => 'Purchase Details'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($canCorrectPurchase = ($access->hasRole('admin') || $access->can('purchases.correct')) && $purchase->status === 'posted' && $purchase->payments->isEmpty() && $purchase->returns->isEmpty())
    <div class="page-head">
        <div>
            <h2>Purchase {{ $purchase->purchase_no }}</h2>
            <p>Purchase summary showing received items, supplier details, payment progress, and print-ready records.</p>
            @if ($canCorrectPurchase)
                <p class="list-note" style="margin-top:8px;">Saved too early or entered wrong? Use Correct / Edit Purchase to add, remove, or change items safely.</p>
            @endif
        </div>
        <div class="actions">
            @if ($access->can('supplier_payments.manage') && $purchase->balance_due > 0)
                <a href="{{ route('supplier-payments.create', ['supplier_id' => $purchase->supplier_id, 'purchase_id' => $purchase->id]) }}" class="button-link">Post Payment</a>
            @endif
            @if ($access->can('purchases.manage') && $purchase->status === 'posted')
                <a href="{{ route('purchases.returns.create', $purchase) }}" class="button-link">Supplier Return</a>
            @endif
            @if ($canCorrectPurchase)
                <a href="{{ route('purchases.correct', $purchase) }}" class="button-link" title="Use this when a purchase was saved too early or entered wrongly.">Correct / Edit Purchase</a>
            @endif
            @if ($access->can('purchases.manage') && $purchase->status === 'posted' && $purchase->payments->isEmpty() && $purchase->returns->isEmpty())
                <form method="post" action="{{ route('purchases.void', $purchase) }}">
                    @csrf
                    <input type="hidden" name="void_reason" value="Voided from purchase detail page">
                    <button type="submit" class="button-link" style="border-color:#efcfac; background:#f8ead7; color:#8b4513;">Void Purchase</button>
                </form>
            @endif
            <a href="{{ route('purchases.print', $purchase) }}" target="_blank" class="button-link primary">Full Print</a>
            <a href="{{ route('purchases.index') }}" class="button-link">Back to Purchases</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Supplier</div><div class="value">{{ $purchase->supplier?->name ?? 'Supplier not linked' }}</div></div>
        <div class="card"><div class="label">Purchase Type</div><div class="value">{{ ucfirst($purchase->purchase_type) }}</div></div>
        <div class="card"><div class="label">Store</div><div class="value">{{ $purchase->store?->name ?? '-' }}</div></div>
        <div class="card"><div class="label">Total</div><div class="value money">{{ $currency }} {{ number_format((float) $purchase->total_amount, 0) }}</div></div>
        <div class="card"><div class="label">Balance</div><div class="value money">{{ $currency }} {{ number_format((float) $purchase->balance_due, 0) }}</div></div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Purchase Items</h3>
            <p class="list-note">These are the item quantities and costs recorded against this purchase.</p>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Unit</th>
                        <th>Qty</th>
                        <th>Cost</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchase->items as $item)
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
            <h3>Purchase Summary</h3>
            <p class="list-note">Use this area to confirm which supplier delivered the goods, how the purchase was posted, and whether any balance is still due.</p>
            <table>
                <tbody>
                    <tr>
                        <th style="text-align:left; width: 38%;">Purchase Date</th>
                        <td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Supplier</th>
                        <td>{{ $purchase->supplier?->name ?? 'Supplier not linked' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Phone</th>
                        <td>{{ $purchase->supplier?->phone ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Payment Mode</th>
                        <td>{{ $purchase->paymentMode?->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Status</th>
                        <td>{{ ucfirst($purchase->status) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Due Date</th>
                        <td>{{ $purchase->credit_due_date?->format('d M Y') ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Corrected From</th>
                        <td>{{ $purchase->correctedFrom?->purchase_no ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Replaced By</th>
                        <td>{{ $purchase->replacedBy?->purchase_no ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
            <h3 style="margin-top: 18px;">Payments</h3>
            <p class="list-note">Supplier payments linked to this purchase appear here after posting.</p>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Mode</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchase->payments as $payment)
                        <tr>
                            <td>{{ optional($payment->payment_date)->format('d M Y') }}</td>
                            <td><a href="{{ route('supplier-payments.show', $payment) }}">{{ $payment->payment_no }}</a></td>
                            <td>{{ $payment->paymentMode?->name ?? '-' }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $payment->amount, 0) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">No supplier payments have been posted for this purchase yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @if ($purchase->balance_due > 0)
                <p class="list-note">There is still {{ $currency }} {{ number_format((float) $purchase->balance_due, 0) }} outstanding on this purchase.</p>
            @elseif ($purchase->status === 'void')
                <p class="list-note">This purchase was voided, and its stock movement was reversed out of inventory.</p>
            @endif
        </div>
    </section>

    @if ($purchase->returns->isNotEmpty())
        <section class="panel" style="margin-top: 16px;">
            <h3>Supplier Returns</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Type</th>
                            <th>Returned</th>
                            <th>Refund</th>
                            <th>Supplier Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchase->returns as $return)
                            <tr>
                                <td>{{ optional($return->return_date)->format('d M Y') }}</td>
                                <td><a href="{{ route('purchase-returns.show', $return) }}">{{ $return->return_no }}</a></td>
                                <td>{{ ucwords(str_replace('_', ' ', $return->return_type)) }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $return->returned_total, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $return->refund_amount, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $return->supplier_credit_amount, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if (session('auto_print_document'))
        <script>
            (() => {
                const printUrl = @json(route('purchases.print', $purchase));
                const popup = window.open(printUrl, '_blank', 'noopener,noreferrer');
                if (popup) {
                    popup.focus();
                }
            })();
        </script>
    @endif
@endsection
