@extends('layouts.app', ['title' => 'Sale Return'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))

    <style>
        .return-shell {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(300px, .9fr);
            gap: 14px;
            align-items: start;
        }
        .return-stack {
            display: grid;
            gap: 12px;
        }
        .return-shell .panel {
            padding: 14px;
            border-radius: 18px;
        }
        .return-cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }
        .return-card {
            padding: 12px 14px;
            border-radius: 16px;
            border: 1px solid var(--line);
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248, 247, 239, .9));
        }
        .return-card .label {
            color: var(--muted);
            font-size: .74rem;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .return-card .value {
            margin-top: 6px;
            font-size: 1rem;
            font-weight: 700;
        }
        .return-flow {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }
        .return-flow-card {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--panel-soft);
            padding: 10px 12px;
        }
        .return-flow-card strong {
            display: block;
            margin-bottom: 4px;
            font-size: .86rem;
        }
        .return-flow-card span {
            color: var(--muted);
            font-size: .79rem;
            line-height: 1.4;
        }
        .return-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .return-form-grid .form-field {
            gap: 5px;
        }
        .return-form-grid .form-field span,
        .return-notes .form-field span {
            font-size: .74rem;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .return-field-wrap.is-hidden {
            display: none;
        }
        .return-hint {
            margin-top: 4px;
            color: var(--muted);
            font-size: .77rem;
            line-height: 1.35;
        }
        .return-items-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .return-items-head p {
            margin: 0;
            color: var(--muted);
            font-size: .82rem;
        }
        .return-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        .return-table th {
            padding: 0 10px 6px;
            text-align: left;
            color: var(--muted);
            font-size: .74rem;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .return-table td {
            background: #fff;
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            padding: 10px;
            vertical-align: middle;
        }
        .return-table td:first-child {
            border-left: 1px solid var(--line);
            border-top-left-radius: 14px;
            border-bottom-left-radius: 14px;
        }
        .return-table td:last-child {
            border-right: 1px solid var(--line);
            border-top-right-radius: 14px;
            border-bottom-right-radius: 14px;
        }
        .return-table tr.is-selected td {
            border-color: rgba(6, 104, 56, .25);
            background: rgba(255, 255, 255, .98);
            box-shadow: inset 0 0 0 1px rgba(6, 104, 56, .12);
        }
        .return-product strong {
            display: block;
            font-size: .9rem;
        }
        .return-product span {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: .79rem;
        }
        .return-qty {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .return-qty input {
            width: 76px;
            min-height: 36px;
            padding: 8px 10px;
            text-align: center;
        }
        .return-qty button {
            border: 1px solid var(--line);
            background: #fff;
            color: var(--brand);
            border-radius: 10px;
            padding: 7px 9px;
            font-weight: 700;
            cursor: pointer;
            font-size: .78rem;
        }
        .return-summary-card {
            position: sticky;
            top: 12px;
            display: grid;
            gap: 10px;
        }
        .return-summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
        .return-summary-stat {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 10px 12px;
            background: #fff;
        }
        .return-summary-stat .label {
            color: var(--muted);
            font-size: .73rem;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .return-summary-stat .value {
            margin-top: 5px;
            font-size: 1rem;
            font-weight: 700;
        }
        .return-note-box {
            border-radius: 16px;
            padding: 12px 14px;
            background: linear-gradient(180deg, rgba(255, 247, 219, .9), rgba(248, 238, 207, .98));
            border: 1px solid rgba(212, 175, 55, .3);
        }
        .return-note-box strong {
            display: block;
            margin-bottom: 4px;
            color: var(--brand);
        }
        .return-note-box p {
            margin: 0;
            color: var(--ink);
            font-size: .84rem;
            line-height: 1.5;
        }
        .return-submit {
            display: grid;
            gap: 8px;
        }
        .return-submit button {
            width: 100%;
        }
        .return-submit .button-link {
            justify-content: center;
        }
        .return-next-step {
            font-size: .8rem;
            color: var(--muted);
            line-height: 1.45;
        }
        @media (max-width: 1100px) {
            .return-shell {
                grid-template-columns: 1fr;
            }
            .return-summary-card {
                position: static;
            }
        }
        @media (max-width: 800px) {
            .return-cards,
            .return-flow,
            .return-form-grid,
            .return-summary-grid {
                grid-template-columns: 1fr;
            }
            .return-table,
            .return-table thead,
            .return-table tbody,
            .return-table tr,
            .return-table th,
            .return-table td {
                display: block;
                width: 100%;
            }
            .return-table thead {
                display: none;
            }
            .return-table tr {
                margin-bottom: 10px;
            }
            .return-table td {
                border-left: 1px solid var(--line);
                border-right: 1px solid var(--line);
                border-radius: 0;
            }
            .return-table td:first-child {
                border-top-right-radius: 14px;
            }
            .return-table td:last-child {
                border-bottom-left-radius: 14px;
            }
            .return-qty {
                justify-content: flex-start;
            }
        }
    </style>

    <div class="page-head">
        <div>
            <h2>Return / Refund / Exchange For {{ $sale->sale_no }}</h2>
            <p>Choose the items coming back, then confirm whether the customer should get a refund, a credit note, or an exchange follow-up.</p>
        </div>
        <div class="actions">
            <a href="{{ route('sales.show', $sale) }}" class="button-link">Back to Sale</a>
        </div>
    </div>

    <section class="return-cards">
        <div class="return-card">
            <div class="label">Customer</div>
            <div class="value">{{ $sale->customer?->name ?? 'Walk-in customer' }}</div>
        </div>
        <div class="return-card">
            <div class="label">Sale Total</div>
            <div class="value money">{{ $currency }} {{ number_format((float) $sale->total_amount, 0) }}</div>
        </div>
        <div class="return-card">
            <div class="label">Outstanding Balance</div>
            <div class="value money">{{ $currency }} {{ number_format((float) $sale->balance_due, 0) }}</div>
        </div>
        <div class="return-card">
            <div class="label">Lines Ready For Return</div>
            <div class="value">{{ number_format($returnRows->count()) }}</div>
        </div>
    </section>

    <form method="post" action="{{ route('sales.returns.store', $sale) }}" class="return-shell" id="sale-return-form" data-outstanding="{{ (float) $sale->balance_due }}" data-currency="{{ $currency }}" data-requires-approval="{{ $requiresApprovalPin ? 1 : 0 }}">
        @csrf

        <div class="return-stack">
            <section class="panel">
                <div class="return-flow">
                    <div class="return-flow-card">
                        <strong>Refund</strong>
                        <span>Use when money must go back to the customer after the outstanding balance is cleared.</span>
                    </div>
                    <div class="return-flow-card">
                        <strong>Credit Note</strong>
                        <span>Use when the return should reduce debt first, then leave any extra value on the customer account.</span>
                    </div>
                    <div class="return-flow-card">
                        <strong>Exchange</strong>
                        <span>Use when the customer is bringing items back and you will open a replacement sale from this return.</span>
                    </div>
                </div>

                <div class="return-form-grid">
                    <label class="form-field">
                        <span>Return Date</span>
                        <input type="date" name="return_date" value="{{ old('return_date', now()->toDateString()) }}" required>
                    </label>
                    <label class="form-field">
                        <span>Settlement Type</span>
                        <select name="return_type" id="return_type" required>
                            <option value="refund" @selected(old('return_type', 'refund') === 'refund')>Refund</option>
                            <option value="credit_note" @selected(old('return_type') === 'credit_note')>Credit Note</option>
                            <option value="exchange" @selected(old('return_type') === 'exchange')>Exchange</option>
                        </select>
                        <div class="return-hint" id="return-type-hint">Refund only pays cash out when the return is worth more than the current outstanding balance.</div>
                    </label>
                    <label class="form-field return-field-wrap" id="refund-mode-wrap">
                        <span>Refund Paid Through</span>
                        <select name="payment_mode_id" id="payment_mode_id">
                            <option value="">Choose payout mode</option>
                            @foreach ($paymentModes as $mode)
                                <option value="{{ $mode->id }}" @selected((string) old('payment_mode_id') === (string) $mode->id)>{{ $mode->name }}</option>
                            @endforeach
                        </select>
                        <div class="return-hint">Only needed when the return actually sends money out to the customer.</div>
                    </label>
                    <label class="form-field return-field-wrap" id="approval-pin-wrap">
                        <span>Admin Approval PIN</span>
                        <input type="password" name="approval_pin" id="approval_pin" value="{{ old('approval_pin') }}" placeholder="Enter admin PIN">
                        <div class="return-hint">Cash refunds from cashier accounts need approval for control and audit.</div>
                    </label>
                </div>

                <div class="return-notes" style="margin-top: 10px;">
                    <label class="form-field">
                        <span>Remarks</span>
                        <textarea name="remarks" rows="3" placeholder="Reason for return, damaged item, wrong item, or exchange notes">{{ old('remarks') }}</textarea>
                    </label>
                </div>
            </section>

            <section class="panel">
                <div class="return-items-head">
                    <div>
                        <h3 style="margin: 0;">Return Items</h3>
                        <p>Enter only the quantities coming back now. Use `All` to pull the full remaining sold quantity onto this return.</p>
                    </div>
                    <div class="return-next-step" id="selected-lines-text">No items selected yet.</div>
                </div>

                <div class="table-wrap">
                    <table class="return-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Remaining Qty</th>
                                <th>Unit Price</th>
                                <th>Return Qty</th>
                                <th>Return Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($returnRows as $index => $row)
                                @php($oldQty = (int) old('items.'.$index.'.quantity', 0))
                                <tr data-return-row data-max="{{ $row['available_qty'] }}" data-price="{{ (float) $row['item']->unit_price }}">
                                    <td>
                                        <div class="return-product">
                                            <strong>{{ $row['item']->product?->name ?? '-' }}</strong>
                                            <span>{{ $row['item']->productUnit?->unit_name ?? '-' }} | Sold {{ number_format((float) $row['item']->quantity, 0) }} | Already returned {{ number_format((float) $row['already_returned'], 0) }}</span>
                                        </div>
                                    </td>
                                    <td>{{ number_format((float) $row['available_qty'], 0) }}</td>
                                    <td class="money">{{ $currency }} {{ number_format((float) $row['item']->unit_price, 0) }}</td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][sale_item_id]" value="{{ $row['item']->id }}">
                                        <div class="return-qty">
                                            <input type="number" step="1" min="0" max="{{ $row['available_qty'] }}" name="items[{{ $index }}][quantity]" value="{{ $oldQty }}" data-return-qty>
                                            <button type="button" data-fill-max>All</button>
                                            <button type="button" data-clear-row>0</button>
                                        </div>
                                    </td>
                                    <td class="money" data-line-total>{{ $currency }} {{ number_format((float) $row['item']->unit_price * $oldQty, 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="return-summary-card">
            <section class="panel">
                <div class="sale-section-head" style="margin-bottom: 10px;">
                    <div>
                        <h3 style="margin: 0;">Settlement Preview</h3>
                        <p style="margin: 4px 0 0; color: var(--muted); font-size: .82rem;">This shows the exact effect before you post the return.</p>
                    </div>
                </div>

                <div class="return-summary-grid">
                    <div class="return-summary-stat">
                        <div class="label">Returned Value</div>
                        <div class="value money" id="summary-returned">{{ $currency }} 0</div>
                    </div>
                    <div class="return-summary-stat">
                        <div class="label">Outstanding Reduced</div>
                        <div class="value money" id="summary-balance-reduction">{{ $currency }} 0</div>
                    </div>
                    <div class="return-summary-stat">
                        <div class="label">Refund To Customer</div>
                        <div class="value money" id="summary-refund">{{ $currency }} 0</div>
                    </div>
                    <div class="return-summary-stat">
                        <div class="label">Store Credit / Exchange Value</div>
                        <div class="value money" id="summary-credit">{{ $currency }} 0</div>
                    </div>
                    <div class="return-summary-stat">
                        <div class="label">Sale Balance After</div>
                        <div class="value money" id="summary-balance-after">{{ $currency }} {{ number_format((float) $sale->balance_due, 0) }}</div>
                    </div>
                    <div class="return-summary-stat">
                        <div class="label">Selected Lines</div>
                        <div class="value" id="summary-lines">0 items</div>
                    </div>
                </div>

                <div class="return-note-box">
                    <strong id="summary-title">Choose items to start</strong>
                    <p id="summary-text">Once you enter return quantities, the system will show whether the value reduces the customer balance, becomes a refund, or stays as exchange credit.</p>
                </div>

                <div class="return-submit">
                    <button type="submit">Post Sale Return</button>
                    <a href="{{ route('sales.show', $sale) }}" class="button-link">Cancel And Go Back</a>
                    <div class="return-next-step" id="summary-next-step">If this is an exchange, the replacement sale can be started from the return detail page after posting.</div>
                </div>
            </section>
        </aside>
    </form>

    <script>
        (() => {
            const form = document.getElementById('sale-return-form');
            if (!form) {
                return;
            }

            const currency = form.dataset.currency || 'UGX';
            const outstanding = Number.parseFloat(form.dataset.outstanding || '0');
            const requiresApprovalPin = form.dataset.requiresApproval === '1';
            const returnTypeInput = document.getElementById('return_type');
            const refundModeWrap = document.getElementById('refund-mode-wrap');
            const approvalPinWrap = document.getElementById('approval-pin-wrap');
            const returnTypeHint = document.getElementById('return-type-hint');
            const selectedLinesText = document.getElementById('selected-lines-text');
            const rows = Array.from(form.querySelectorAll('[data-return-row]'));

            const money = (value) => `${currency} ${Math.round(value).toLocaleString()}`;
            const summaryReturned = document.getElementById('summary-returned');
            const summaryBalanceReduction = document.getElementById('summary-balance-reduction');
            const summaryRefund = document.getElementById('summary-refund');
            const summaryCredit = document.getElementById('summary-credit');
            const summaryBalanceAfter = document.getElementById('summary-balance-after');
            const summaryLines = document.getElementById('summary-lines');
            const summaryTitle = document.getElementById('summary-title');
            const summaryText = document.getElementById('summary-text');
            const summaryNextStep = document.getElementById('summary-next-step');

            const typeCopy = {
                refund: {
                    hint: 'Refund only pays cash out when the return is worth more than the current outstanding balance.',
                    title: 'Refund settlement preview',
                    text: 'The return first clears any remaining debt on the original sale. Any extra value becomes a cash refund.',
                    next: 'Use this when the customer should receive money back after the sale balance has been cleared.',
                },
                credit_note: {
                    hint: 'Credit note keeps any extra value on the customer account instead of paying it out.',
                    title: 'Credit note settlement preview',
                    text: 'The return first reduces the sale balance. Any extra value stays on the customer account as store credit.',
                    next: 'Use this when the customer will buy again later instead of taking cash back now.',
                },
                exchange: {
                    hint: 'Exchange sets aside any extra value for the replacement sale instead of paying it out.',
                    title: 'Exchange settlement preview',
                    text: 'The return first reduces the original sale balance. Any extra value stays available while you open the replacement sale.',
                    next: 'After posting, open the replacement sale directly from the return detail page.',
                },
            };

            const updateSummary = () => {
                const returnType = returnTypeInput.value || 'refund';
                let selectedLines = 0;
                let returnedTotal = 0;

                rows.forEach((row) => {
                    const input = row.querySelector('[data-return-qty]');
                    const lineTotal = row.querySelector('[data-line-total]');
                    const max = Number.parseInt(row.dataset.max || '0', 10);
                    const price = Number.parseFloat(row.dataset.price || '0');
                    let qty = Number.parseInt(input.value || '0', 10);

                    if (!Number.isFinite(qty) || qty < 0) {
                        qty = 0;
                    }

                    if (qty > max) {
                        qty = max;
                        input.value = qty;
                    }

                    const value = qty * price;
                    if (qty > 0) {
                        selectedLines += 1;
                        returnedTotal += value;
                        row.classList.add('is-selected');
                    } else {
                        row.classList.remove('is-selected');
                    }

                    if (lineTotal) {
                        lineTotal.textContent = money(value);
                    }
                });

                const balanceReduction = Math.min(outstanding, returnedTotal);
                const refund = returnType === 'refund' ? Math.max(returnedTotal - outstanding, 0) : 0;
                const credit = (returnType === 'credit_note' || returnType === 'exchange') ? Math.max(returnedTotal - outstanding, 0) : 0;
                const balanceAfter = Math.max(outstanding - returnedTotal, 0);
                const copy = typeCopy[returnType] || typeCopy.refund;

                summaryReturned.textContent = money(returnedTotal);
                summaryBalanceReduction.textContent = money(balanceReduction);
                summaryRefund.textContent = money(refund);
                summaryCredit.textContent = money(credit);
                summaryBalanceAfter.textContent = money(balanceAfter);
                summaryLines.textContent = `${selectedLines} ${selectedLines === 1 ? 'item' : 'items'}`;
                summaryTitle.textContent = copy.title;
                summaryText.textContent = copy.text;
                summaryNextStep.textContent = copy.next;
                returnTypeHint.textContent = copy.hint;
                selectedLinesText.textContent = selectedLines > 0
                    ? `${selectedLines} line${selectedLines === 1 ? '' : 's'} selected.`
                    : 'No items selected yet.';

                const showRefundMode = returnType === 'refund' && refund > 0;
                refundModeWrap.classList.toggle('is-hidden', !showRefundMode);

                const showApprovalPin = requiresApprovalPin && returnType === 'refund' && refund > 0;
                approvalPinWrap.classList.toggle('is-hidden', !showApprovalPin);
            };

            rows.forEach((row) => {
                const input = row.querySelector('[data-return-qty]');
                const fillMaxButton = row.querySelector('[data-fill-max]');
                const clearButton = row.querySelector('[data-clear-row]');
                const max = row.dataset.max || '0';

                input.addEventListener('input', updateSummary);

                if (fillMaxButton) {
                    fillMaxButton.addEventListener('click', () => {
                        input.value = max;
                        updateSummary();
                    });
                }

                if (clearButton) {
                    clearButton.addEventListener('click', () => {
                        input.value = 0;
                        updateSummary();
                    });
                }
            });

            returnTypeInput.addEventListener('change', updateSummary);
            updateSummary();
        })();
    </script>
@endsection
