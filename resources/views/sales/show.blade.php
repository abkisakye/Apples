@extends('layouts.app', ['title' => 'Sale Details'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Sale {{ $sale->sale_no }}</h2>
            <p>{{ $sale->sale_type === 'cash' ? 'Cash sale summary' : 'Credit sale summary' }} showing the customer, items sold, payment progress, and print-ready details.</p>
        </div>
        <div class="actions">
            @if ($access->can('customer_payments.manage') && $sale->balance_due > 0)
                <a href="{{ route('customer-payments.create', ['customer_id' => $sale->customer_id]) }}" class="button-link">Post Payment</a>
            @endif
            @if ($access->can('sales.manage') && $sale->status === 'posted')
                <a href="{{ route('sales.returns.create', $sale) }}" class="button-link">Start Return / Refund / Exchange</a>
            @endif
            @if ($access->can('sales.manage') && $sale->status === 'posted' && $sale->payments->isEmpty() && $sale->returns->isEmpty())
                <a href="{{ route('sales.correct', $sale) }}" class="button-link">Correct And Repost</a>
            @endif
            @if ($access->can('sales.manage') && $sale->status === 'posted' && $sale->payments->isEmpty() && $sale->returns->isEmpty())
                <form method="post" action="{{ route('sales.void', $sale) }}">
                    @csrf
                    <input type="hidden" name="void_reason" value="Voided from sale detail page">
                    @if (! $access->can('sales.override'))
                        <input type="password" name="approval_pin" placeholder="Admin PIN" style="max-width: 130px;">
                    @endif
                    <button type="submit" class="button-link" style="border-color:#efcfac; background:#f8ead7; color:#8b4513;">Void Sale</button>
                </form>
            @endif
            <a href="{{ route('sales.print', ['sale' => $sale, 'theme' => 'full']) }}" target="_blank" class="button-link">Full A4 Document</a>
            <a href="{{ route('sales.print', $sale) }}" target="_blank" class="button-link primary">Print Receipt</a>
            <a href="{{ route('sales.index') }}" class="button-link">Back to Sales</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Customer</div><div class="value">{{ $sale->customer?->name ?? 'Walk-in customer' }}</div></div>
        <div class="card"><div class="label">Sale Type</div><div class="value">{{ ucfirst($sale->sale_type) }}</div></div>
        <div class="card"><div class="label">Store</div><div class="value">{{ $sale->store?->name ?? '-' }}</div></div>
        <div class="card"><div class="label">Total</div><div class="value money">{{ $currency }} {{ number_format((float) $sale->total_amount, 0) }}</div></div>
        <div class="card"><div class="label">Discount</div><div class="value money">{{ $currency }} {{ number_format((float) $sale->discount_amount, 0) }}</div></div>
        <div class="card"><div class="label">Received</div><div class="value money">{{ $currency }} {{ number_format((float) ($sale->cash_tendered ?: $sale->amount_paid), 0) }}</div></div>
        <div class="card"><div class="label">Balance</div><div class="value money">{{ $currency }} {{ number_format((float) $sale->balance_due, 0) }}</div></div>
    </section>

    <section class="panel" style="margin-bottom: 16px;">
        <h3>Cashier Quick View</h3>
        <p class="list-note">Use this summary when the customer is standing at the counter and you need the next action immediately.</p>
        <div class="actions" style="margin-top: 14px;">
            @if ($sale->balance_due > 0 && $access->can('customer_payments.manage'))
                <a href="{{ route('customer-payments.create', ['customer_id' => $sale->customer_id]) }}" class="button-link primary">Post Payment For This Sale</a>
            @endif
            <a href="{{ route('sales.print', $sale) }}" target="_blank" class="button-link primary">Print Cashier Receipt</a>
            <a href="{{ route('sales.print', ['sale' => $sale, 'theme' => 'full']) }}" target="_blank" class="button-link">Open Full A4 Document</a>
            @if ($access->can('sales.manage'))
                <a href="{{ route('sales.create') }}" class="button-link">Start Another Sale</a>
            @endif
        </div>
    </section>

    @if ($sale->status === 'posted')
        <section class="panel" style="margin-bottom: 16px;">
            <h3>After-Sale Actions</h3>
            <p class="list-note">
                Use a return document when stock is coming back, a payment when the customer is settling credit, and an exchange return when you still need to open a replacement sale.
            </p>
            <div class="actions" style="margin-top: 14px;">
                @if ($sale->balance_due > 0 && $access->can('customer_payments.manage'))
                    <a href="{{ route('customer-payments.create', ['customer_id' => $sale->customer_id]) }}" class="button-link">Collect Balance</a>
                @endif
                @if ($access->can('sales.manage'))
                    <a href="{{ route('sales.returns.create', $sale) }}" class="button-link">Create Return Document</a>
                @endif
                @php($pendingExchange = $sale->returns->first(fn ($return) => $return->return_type === 'exchange' && ! $return->replacementSale))
                @if ($pendingExchange)
                    <a href="{{ route('sales.create', ['exchange_return_id' => $pendingExchange->id]) }}" class="button-link primary">Continue Pending Exchange</a>
                @endif
            </div>
        </section>
    @endif

    <section class="grid-two">
        <div class="panel">
            <h3>Sale Items</h3>
            <p class="list-note">These are the exact units and prices recorded for this sale.</p>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Unit</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sale->items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->product?->name ?? '-' }}</strong>
                                @if ($item->base_stock_impact_label)
                                    <div class="table-meta">{{ $item->base_stock_impact_label }}</div>
                                @endif
                            </td>
                            <td>{{ $item->productUnit?->unit_name ?? '-' }}</td>
                            <td>{{ number_format((float) $item->quantity, 0) }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $item->unit_price, 0) }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $item->line_total, 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>

        <div class="panel">
            <h3>Sale Summary</h3>
            <p class="list-note">Use this area to confirm who bought the goods, how the sale was posted, and whether payment follow-up is still needed.</p>
            <table>
                <tbody>
                    <tr>
                        <th style="text-align:left; width: 38%;">Sale Date</th>
                        <td>{{ optional($sale->sale_date)->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Customer</th>
                        <td>{{ $sale->customer?->name ?? 'Walk-in customer' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Phone</th>
                        <td>{{ $sale->customer?->phone ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Payment Mode</th>
                        <td>{{ $sale->paymentMode?->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Subtotal</th>
                        <td>{{ $currency }} {{ number_format((float) $sale->subtotal, 0) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Discount</th>
                        <td>{{ $currency }} {{ number_format((float) $sale->discount_amount, 0) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Status</th>
                        <td>{{ ucfirst($sale->status) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Due Date</th>
                        <td>{{ $sale->credit_due_date?->format('d M Y') ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Corrected From</th>
                        <td>{{ $sale->correctedFrom?->sale_no ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Replaced By</th>
                        <td>{{ $sale->replacedBy?->sale_no ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
            <h3 style="margin-top: 18px;">Payments</h3>
            <p class="list-note">For cash sales, the sale itself usually covers payment. For credit sales, posted payments appear here.</p>
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
                    @forelse ($sale->payments as $payment)
                        <tr>
                            <td>{{ optional($payment->payment_date)->format('d M Y') }}</td>
                            <td><a href="{{ route('customer-payments.show', $payment) }}">{{ $payment->payment_no }}</a></td>
                            <td>{{ $payment->paymentMode?->name ?? '-' }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $payment->amount, 0) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">No separate payments have been posted for this sale yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @if ($sale->balance_due > 0)
                <p class="list-note">There is still {{ $currency }} {{ number_format((float) $sale->balance_due, 0) }} outstanding on this sale.</p>
            @elseif ($sale->status === 'void')
                <p class="list-note">This sale was voided, and its stock movement was reversed back into inventory.</p>
            @endif
        </div>
    </section>

    @if ($sale->returns->isNotEmpty())
        <section class="panel" style="margin-top: 16px;">
            <h3>Returns / Refunds</h3>
            <p class="list-note">Each return note shows whether the value reduced the sale balance, became a cash refund, or is waiting to be used on a replacement sale.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Type</th>
                            <th>Returned</th>
                            <th>Refund</th>
                            <th>Store Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sale->returns as $return)
                            <tr>
                                <td>{{ optional($return->return_date)->format('d M Y') }}</td>
                                <td><a href="{{ route('sale-returns.show', $return) }}">{{ $return->return_no }}</a></td>
                                <td>{{ ucwords(str_replace('_', ' ', $return->return_type)) }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $return->returned_total, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $return->refund_amount, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $return->store_credit_amount, 0) }}</td>
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
                const printUrl = @json(route('sales.print', ['sale' => $sale, 'theme' => 'thermal']));
                const popup = window.open(printUrl, '_blank', 'noopener,noreferrer');
                if (popup) {
                    popup.focus();
                }
            })();
        </script>
    @endif
@endsection
