@extends('layouts.app', ['title' => 'Sale Return'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))

    <style>
        .return-status-banner {
            margin-bottom: 14px;
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid rgba(212, 175, 55, .28);
            background: linear-gradient(180deg, rgba(255, 247, 219, .95), rgba(248, 238, 207, .98));
        }
        .return-status-banner strong {
            display: block;
            margin-bottom: 4px;
            color: var(--brand);
        }
        .return-status-banner p {
            margin: 0;
            color: var(--ink);
            line-height: 1.5;
        }
        .return-summary-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }
        .return-summary-card {
            padding: 12px 14px;
            border-radius: 16px;
            border: 1px solid var(--line);
            background: linear-gradient(180deg, rgba(255,255,255,.97), rgba(247,245,237,.94));
        }
        .return-summary-card .label {
            color: var(--muted);
            font-size: .73rem;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .return-summary-card .value {
            margin-top: 6px;
            font-size: 1rem;
            font-weight: 700;
        }
        .return-outcome-grid {
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 14px;
        }
        .return-outcome-box {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #fff;
            padding: 14px;
        }
        .return-outcome-box h3 {
            margin: 0 0 8px;
        }
        .return-outcome-box p {
            margin: 0;
            color: var(--muted);
            line-height: 1.55;
        }
        .return-outcome-list {
            display: grid;
            gap: 8px;
            margin-top: 12px;
        }
        .return-outcome-list div {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 14px;
            background: var(--panel-soft);
            border: 1px solid var(--line);
        }
        .return-outcome-list span {
            color: var(--muted);
            font-size: .82rem;
        }
        .return-outcome-list strong {
            color: var(--ink);
        }
        @media (max-width: 1100px) {
            .return-summary-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .return-outcome-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 720px) {
            .return-summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-head">
        <div>
            <h2>Sale Return {{ $saleReturn->return_no }}</h2>
            <p>Review the items returned, see the exact settlement effect, and continue with the next step if this return is an exchange.</p>
        </div>
        <div class="actions">
            @if ($saleReturn->return_type === 'exchange')
                @if ($saleReturn->replacementSale)
                    <a href="{{ route('sales.show', $saleReturn->replacementSale) }}" class="button-link">Open Replacement Sale</a>
                @else
                    <a href="{{ route('sales.create', ['exchange_return_id' => $saleReturn->id]) }}" class="button-link">Start Replacement Sale</a>
                @endif
            @endif
            <a href="{{ route('sale-returns.print', $saleReturn) }}" target="_blank" class="button-link primary">Print</a>
            <a href="{{ route('sales.show', $saleReturn->sale) }}" class="button-link">Back To Sale</a>
        </div>
    </div>

    @if ($saleReturn->return_type === 'exchange' && ! $saleReturn->replacementSale)
        <div class="return-status-banner">
            <strong>Replacement Sale Still Needed</strong>
            <p>This exchange note has already restored stock and settled the original sale. The next step is to start the replacement sale from this page so the new items are linked back to the exchange.</p>
        </div>
    @endif

    <section class="return-summary-grid">
        <div class="return-summary-card">
            <div class="label">Linked Sale</div>
            <div class="value">{{ $saleReturn->sale?->sale_no ?? '-' }}</div>
        </div>
        <div class="return-summary-card">
            <div class="label">Type</div>
            <div class="value">{{ ucwords(str_replace('_', ' ', $saleReturn->return_type)) }}</div>
        </div>
        <div class="return-summary-card">
            <div class="label">Returned Value</div>
            <div class="value money">{{ $currency }} {{ number_format((float) $saleReturn->returned_total, 0) }}</div>
        </div>
        <div class="return-summary-card">
            <div class="label">Outstanding Reduced</div>
            <div class="value money">{{ $currency }} {{ number_format((float) $settlement['balance_reduction'], 0) }}</div>
        </div>
        <div class="return-summary-card">
            <div class="label">Refund Paid</div>
            <div class="value money">{{ $currency }} {{ number_format((float) $saleReturn->refund_amount, 0) }}</div>
        </div>
        <div class="return-summary-card">
            <div class="label">Store Credit / Exchange Value</div>
            <div class="value money">{{ $currency }} {{ number_format((float) $saleReturn->store_credit_amount, 0) }}</div>
        </div>
    </section>

    <section class="return-outcome-grid">
        <div class="panel">
            <h3>Returned Items</h3>
            <p class="list-note">These are the exact lines restored into stock by this return document.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Unit</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($saleReturn->items as $item)
                            <tr>
                                <td>{{ $item->product?->name ?? '-' }}</td>
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

        <div style="display: grid; gap: 14px;">
            <div class="return-outcome-box">
                <h3>Settlement Outcome</h3>
                <p>{{ $settlement['next_step'] }}</p>
                <div class="return-outcome-list">
                    <div>
                        <span>Customer</span>
                        <strong>{{ $saleReturn->customer?->name ?? 'Walk-in customer' }}</strong>
                    </div>
                    <div>
                        <span>Return Date</span>
                        <strong>{{ optional($saleReturn->return_date)->format('d M Y') }}</strong>
                    </div>
                    <div>
                        <span>Store</span>
                        <strong>{{ $saleReturn->store?->name ?? '-' }}</strong>
                    </div>
                    <div>
                        <span>Refund Mode</span>
                        <strong>{{ $saleReturn->paymentMode?->name ?? 'Not used' }}</strong>
                    </div>
                    <div>
                        <span>Sale Balance After</span>
                        <strong>{{ $currency }} {{ number_format((float) ($saleReturn->sale?->balance_due ?? 0), 0) }}</strong>
                    </div>
                    <div>
                        <span>Replacement Sale</span>
                        <strong>{{ $saleReturn->replacementSale?->sale_no ?? 'Not created yet' }}</strong>
                    </div>
                </div>
            </div>

            <div class="return-outcome-box">
                <h3>Remarks</h3>
                <p>{{ $saleReturn->remarks ?: 'No extra remarks were added to this return.' }}</p>
            </div>
        </div>
    </section>

    @if (session('auto_print_document'))
        <script>
            (() => {
                const popup = window.open(@json(route('sale-returns.print', $saleReturn)), '_blank', 'noopener,noreferrer');
                if (popup) popup.focus();
            })();
        </script>
    @endif
@endsection
