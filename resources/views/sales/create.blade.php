@extends('layouts.app', ['title' => 'New Sale'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($customersPayload = $customers->map(fn ($customer) => [
        'id' => $customer->id,
        'name' => $customer->name,
        'is_walk_in' => (bool) $customer->is_walk_in,
        'location' => $customer->location,
        'credit' => (float) ($customer->outstanding_credit ?? 0),
        'search' => strtolower(trim(implode(' ', array_filter([
            $customer->name,
            $customer->location,
        ])))),
    ]))
    @php($unitsPayload = $productUnits->map(fn ($unit) => [
        'id' => $unit->id,
        'label' => trim($unit->product->name.' - '.$unit->unit_name),
        'product_name' => $unit->product->name,
        'unit_name' => $unit->unit_name,
        'price' => (float) $unit->selling_price,
        'barcode' => $unit->barcode,
        'code' => $unit->product->code,
        'part_number' => $unit->part_number,
        'search' => strtolower(trim(implode(' ', array_filter([
            $unit->product->name,
            $unit->unit_name,
            $unit->product->code,
            $unit->barcode,
            $unit->part_number,
        ])))),
    ]))
    <style>
        .sale-shell {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(300px, .82fr);
            gap: 10px;
            align-items: start;
        }
        .sale-stack {
            display: grid;
            gap: 10px;
        }
        .sale-shell .panel {
            padding: 10px;
            border-radius: 16px;
        }
        .sale-section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .sale-section-head h3 {
            margin: 0;
        }
        .sale-section-head p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: .82rem;
            line-height: 1.35;
        }
        .sale-search-input {
            width: 100%;
            min-height: 42px;
            padding: 9px 11px;
            border-radius: 13px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            font-size: .88rem;
        }
        .sale-search-grid {
            display: grid;
            grid-template-columns: minmax(0, .82fr) minmax(0, 1.18fr);
            gap: 8px;
        }
        .scan-status {
            margin-top: 8px;
            color: var(--muted);
            font-size: .8rem;
            min-height: 1.2em;
        }
        .sale-inline-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-top: 10px;
        }
        .sale-inline-stat {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 7px 9px;
            background: var(--panel-soft);
        }
        .sale-inline-stat .label {
            color: var(--muted);
            font-size: .73rem;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .sale-inline-stat .value {
            margin-top: 4px;
            font-size: .98rem;
            font-weight: 700;
        }
        .sale-search-results {
            display: grid;
            gap: 8px;
            margin-top: 10px;
            max-height: 300px;
            overflow-y: auto;
            padding-right: 2px;
        }
        .sale-search-card {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 10px;
            align-items: center;
            padding: 8px 10px;
            border-radius: 13px;
            border: 1px solid var(--line);
            background: #fff;
        }
        .sale-search-card strong {
            display: block;
            margin-bottom: 2px;
            font-size: .86rem;
        }
        .sale-search-meta {
            color: var(--muted);
            font-size: .76rem;
            line-height: 1.3;
        }
        .sale-price-tag {
            padding: 6px 8px;
            border-radius: 999px;
            background: var(--panel-soft);
            color: var(--ink);
            font-size: .8rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .sale-add-button {
            border: 0;
            border-radius: 11px;
            padding: 8px 12px;
            background: var(--brand);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            font-size: .84rem;
        }
        .cart-list {
            display: grid;
            gap: 8px;
        }
        .cart-item {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
            padding: 8px 10px;
        }
        .cart-item-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: start;
            margin-bottom: 8px;
        }
        .cart-item-head strong {
            display: block;
            font-size: .92rem;
        }
        .cart-item-sub {
            color: var(--muted);
            font-size: .8rem;
            margin-top: 2px;
        }
        .cart-remove {
            border: 1px solid #ecd4c0;
            background: #fff7ef;
            color: #9a5821;
            border-radius: 10px;
            padding: 7px 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: .78rem;
        }
        .cart-grid {
            display: grid;
            grid-template-columns: 1.15fr .95fr .95fr;
            gap: 8px;
        }
        .cart-grid .form-field {
            gap: 4px;
        }
        .cart-grid .form-field span {
            font-size: .73rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .qty-box {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .qty-box button {
            min-width: 32px;
            height: 32px;
            padding: 0;
            border-radius: 10px;
            font-size: .86rem;
        }
        .qty-box input {
            text-align: center;
            min-height: 34px;
            padding: 6px 8px;
        }
        .sale-summary-card {
            position: sticky;
            top: 12px;
        }
        .compact-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
        .sale-date-row {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            max-width: 100%;
        }
        .sale-date-row span {
            margin: 0;
            white-space: nowrap;
        }
        .sale-date-row input {
            width: 170px;
            min-width: 0;
        }
        .customer-picker {
            position: relative;
            display: grid;
            gap: 8px;
        }
        .customer-dropdown {
            display: none;
            gap: 8px;
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 16px 30px rgba(30, 48, 40, 0.12);
        }
        .customer-dropdown.is-open {
            display: grid;
        }
        .customer-search-input {
            width: 100%;
            min-height: 40px;
            padding: 9px 11px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            font-size: .86rem;
        }
        .customer-search-input.is-selected {
            font-weight: 700;
        }
        .customer-results {
            display: grid;
            gap: 5px;
            max-height: 180px;
            overflow-y: auto;
            padding-right: 2px;
        }
        .customer-result {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            align-items: center;
            padding: 8px 10px;
            border: 1px solid var(--line);
            border-radius: 11px;
            background: #fff;
        }
        .customer-result strong {
            display: block;
            margin: 0;
            font-size: .88rem;
        }
        .customer-result button {
            white-space: nowrap;
            min-width: 72px;
            padding: 6px 10px;
            font-size: .8rem;
        }
        .quick-customer-status {
            color: var(--muted);
            font-size: .8rem;
        }
        .quick-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .quick-actions .button-link {
            flex: 1 1 120px;
            justify-content: center;
        }
        .store-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 0 10px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: var(--panel-soft);
            color: var(--ink);
            font-weight: 600;
            font-size: .84rem;
        }
        .sale-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(23, 31, 27, 0.42);
            z-index: 80;
        }
        .sale-modal.is-open {
            display: flex;
        }
        .sale-modal-card {
            width: min(520px, 100%);
            background: #fff;
            border-radius: 18px;
            border: 1px solid var(--line);
            box-shadow: 0 24px 48px rgba(21, 32, 28, 0.2);
            padding: 16px;
        }
        .sale-modal-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: start;
            margin-bottom: 14px;
        }
        .sale-modal-head h3 {
            margin: 0;
        }
        .modal-close {
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            border-radius: 10px;
            width: 36px;
            height: 36px;
            padding: 0;
            cursor: pointer;
        }
        .summary-block {
            display: grid;
            gap: 9px;
        }
        .payment-entry-block {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--line);
            display: grid;
            gap: 10px;
        }
        .payment-entry-block h4 {
            margin: 0;
            font-size: .9rem;
        }
        .payment-entry-block p {
            margin: 0;
            color: var(--muted);
            font-size: .8rem;
            line-height: 1.4;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
            font-size: .88rem;
        }
        .summary-row.total {
            padding-top: 10px;
            border-top: 1px solid var(--line);
            font-size: 1rem;
            font-weight: 700;
        }
        .sale-status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 9px 12px;
            border-radius: 12px;
            background: var(--brand-soft);
            color: var(--brand);
            font-weight: 700;
            font-size: .84rem;
        }
        .sale-status-pill.credit {
            background: var(--accent-soft);
            color: var(--accent-ink);
        }
        .empty-cart {
            padding: 16px 14px;
            border: 1px dashed var(--line-strong);
            border-radius: 14px;
            text-align: center;
            color: var(--muted);
            background: #fbfcfb;
            font-size: .88rem;
        }
        .sale-footer-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .sale-footer-actions .button-link,
        .sale-footer-actions button {
            flex: 1 1 150px;
            justify-content: center;
        }
        .sale-mobile-bar {
            display: none;
        }
        @media (max-width: 980px) {
            .sale-shell {
                grid-template-columns: 1fr;
            }
            .sale-summary-card {
                position: static;
            }
        }
        @media (max-width: 760px) {
            .sale-search-grid,
            .compact-grid,
            .cart-grid {
                grid-template-columns: 1fr;
            }
            .sale-search-card {
                grid-template-columns: minmax(0, 1fr) auto;
            }
            .sale-price-tag {
                display: none;
            }
            .sale-search-input,
            .cart-item input,
            .cart-item select,
            .summary-block input,
            .summary-block select {
                min-height: 40px;
                font-size: 16px;
            }
            .sale-inline-stats {
                grid-template-columns: 1fr;
            }
            .customer-dropdown {
                position: fixed;
                left: 12px;
                right: 12px;
                bottom: 12px;
                z-index: 90;
                max-height: min(70vh, 520px);
            }
            .sale-summary-card .sale-footer-actions {
                display: none;
            }
            .sale-modal {
                padding: 12px;
                align-items: end;
            }
            .sale-modal-card {
                border-radius: 18px 18px 0 0;
            }
            .sale-mobile-bar {
                position: sticky;
                bottom: 8px;
                display: grid;
                gap: 8px;
                margin-top: 8px;
                padding: 8px;
                border: 1px solid var(--line);
                border-radius: 16px;
                background: rgba(255,255,255,.96);
                box-shadow: 0 10px 24px rgba(30, 48, 40, 0.14);
                backdrop-filter: blur(10px);
            }
            .sale-mobile-meta {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 10px;
                font-size: .86rem;
            }
            .sale-mobile-meta strong {
                font-size: 1rem;
            }
            .sale-mobile-sub {
                color: var(--muted);
                font-size: .8rem;
            }
            .sale-mobile-actions {
                display: grid;
                grid-template-columns: 1fr 1.2fr;
                gap: 8px;
            }
        }
    </style>

    <div class="page-head">
        <div>
            <h2>New Sale</h2>
           <!-- <p>
                {{ $sourceSale
                    ? 'You are correcting '.$sourceSale->sale_no.'. Review the copied items and save the corrected version. The original sale will be voided automatically after the new one posts.'
                    : 'Search a product, add it to the sale, adjust quantities, choose the customer, enter the money received, and save. The system will treat any unpaid balance as credit automatically.' }}
            </p>-->
        </div>
        <div class="actions">
            @if ($requiresShift)
                @if ($activeShift)
                    <div class="store-pill">Shift: {{ $activeShift->shift_no }}</div>
                @else
                    <a href="{{ route('cash-shifts.create') }}" class="button-link primary">Open Shift First</a>
                @endif
            @endif
            @if ($currentStore)
                <div class="store-pill">Store: {{ $currentStore->name }}</div>
            @endif
            <a href="{{ route('sales.index') }}" class="button-link">Back to Sales</a>
        </div>
    </div>

    <form method="post" action="{{ route('sales.store') }}" id="sale-form" class="sale-shell">
        @csrf
        <input type="hidden" name="corrected_from_sale_id" value="{{ old('corrected_from_sale_id', $sourceSale?->id) }}">
        <input type="hidden" name="exchange_return_id" value="{{ old('exchange_return_id', $exchangeReturn?->id) }}">

        <div class="sale-stack">
            <section class="panel">
                <div class="sale-section-head">
                    <div>
                        <h3>1. Search And Add Products</h3>
                        <p>Type a product name, code, barcode, or part number. Tap once to add it.</p>
                    </div>
                </div>
                @if ($exchangeReturn && $exchangeReturn->return_type === 'exchange')
                    <div class="summary-callout" style="margin-bottom: 10px;">
                        Exchange return <strong>{{ $exchangeReturn->return_no }}</strong> is open for this customer. The returned items are already loaded as a starting point, and you can adjust them before saving the replacement sale.
                    </div>
                @endif
                <div class="sale-search-grid">
                    <div>
                        <input type="text" id="scan-search" class="sale-search-input" placeholder="Scan barcode or code">
                        <div id="scan-status" class="scan-status">Use this box for scanner input. Press Enter after scan if your scanner does not auto-submit.</div>
                    </div>
                    <input type="text" id="product-search" class="sale-search-input" placeholder="Search product, barcode, code, or part number">
                </div>
                <div class="sale-inline-stats">
                    <div class="sale-inline-stat">
                        <div class="label">Lines</div>
                        <div class="value" id="items-inline-summary">0</div>
                    </div>
                    <div class="sale-inline-stat">
                        <div class="label">Units</div>
                        <div class="value" id="units-inline-summary">0</div>
                    </div>
                    <div class="sale-inline-stat">
                        <div class="label">Total</div>
                        <div class="value" id="total-inline-summary">{{ $currency }} 0</div>
                    </div>
                </div>
                <div id="product-search-results" class="sale-search-results"></div>
            </section>

            <section class="panel">
                <div class="sale-section-head">
                    <div>
                        <h3>2. Sale Items</h3>
                        <p>Change quantity or price directly. Totals update instantly.</p>
                    </div>
                    <div class="actions">
                        <button type="button" id="clear-cart" class="button-link">Clear Sale</button>
                    </div>
                </div>

                <div id="cart-empty" class="empty-cart">
                    No products added yet. Start with the search box above.
                </div>
                <div id="cart-list" class="cart-list"></div>
                <div id="sale-items-hidden"></div>
            </section>
        </div>

        <div class="sale-stack sale-summary-card">
            <section class="panel">
                <h3>3. Sale Details</h3>
                <input type="hidden" name="store_id" value="{{ $currentStore?->id }}">
                @if ($requiresShift && ! $activeShift)
                    <div class="empty-cart" style="margin-top: 10px; text-align:left;">
                        Open a cashier shift before posting sales from this account. This helps the admin review daily cash properly.
                    </div>
                @endif
                <div class="compact-grid" style="margin-top: 10px;">
                    <label class="form-field sale-date-row">
                        <span>Sale Date:</span>
                        <input type="date" name="sale_date" id="sale-date" value="{{ old('sale_date', now()->toDateString()) }}" required>
                    </label>
                    <div class="form-field" style="grid-column: 1 / -1;">
                        <span>Add/Select Customer</span>
                        <input type="hidden" name="customer_id" id="sale-customer" value="{{ old('customer_id', $prefillSale['customer_id']) }}">
                        <div class="customer-picker">
                            <input type="text" id="customer-search" class="customer-search-input" placeholder="Search and choose customer" autocomplete="off">
                            <div id="customer-dropdown" class="customer-dropdown" aria-hidden="true">
                                <div id="customer-results" class="customer-results"></div>
                                <div class="quick-actions">
                                    <button type="button" id="quick-customer-open" class="button-link">Add Customer</button>
                                    <button type="button" id="customer-use-walk-in" class="button-link">Use Walk-in</button>
                                </div>
                            </div>
						</div>
                        <div id="quick-customer-status" class="quick-customer-status">Choose a customer first.</div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <h3>4. Payment Summary</h3>
                <div class="summary-block" style="margin-top: 10px;">
                    <div id="sale-kind-badge" class="sale-status-pill">Cash Sale</div>
                    <div class="summary-row">
                        <span>Customer</span>
                        <strong id="customer-summary">Choose customer</strong>
                    </div>
                    <div class="summary-row">
                        <span>Items</span>
                        <strong id="items-summary">0</strong>
                    </div>
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <strong id="subtotal-summary">{{ $currency }} 0</strong>
                    </div>
                    <div class="summary-row">
                        <span>Discount</span>
                        <strong id="discount-summary">{{ $currency }} 0</strong>
                    </div>
                    <div class="summary-row">
                        <span>Received</span>
                        <strong id="received-summary">{{ $currency }} 0</strong>
                    </div>
                    <div class="summary-row">
                        <span>Balance</span>
                        <strong id="balance-summary">{{ $currency }} 0</strong>
                    </div>
                    <div class="summary-row">
                        <span>Change</span>
                        <strong id="change-summary">{{ $currency }} 0</strong>
                    </div>
                    <div class="summary-row">
                        <span>Due Date</span>
                        <strong id="due-summary">Not needed</strong>
                    </div>
                    <div class="summary-row total">
                        <span>Total Sale</span>
                        <span id="total-summary">{{ $currency }} 0</span>
                    </div>
                </div>
                <div class="payment-entry-block">
                    <div>
                        <h4>Payment Entry</h4>
                        <p>Capture the amount received here. The balance will stay on the customer account only when payment is short.</p>
                    </div>
                    <div class="compact-grid">
                        <label class="form-field">
                            <span>Payment Mode</span>
                            <select name="payment_mode_id">
                                <option value="">Auto-select from balance</option>
                                @foreach ($paymentModes as $mode)
                                    <option value="{{ $mode->id }}" @selected((string) old('payment_mode_id') === (string) $mode->id)>{{ $mode->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="form-field">
                            <span>Discount Amount</span>
                            <input type="number" step="0.01" min="0" name="discount_amount" id="discount-amount" value="{{ old('discount_amount', $prefillSale['discount_amount']) }}">
                        </label>
                        <label class="form-field">
                            <span>Amount Received Now</span>
                            <input type="number" step="0.01" min="0" name="amount_paid" id="amount-paid" value="{{ old('amount_paid', $prefillSale['amount_paid']) }}">
                        </label>
                        <label class="form-field" id="credit-period-wrap">
                            <span>Credit Period (days)</span>
                            <input
                                type="number"
                                min="1"
                                name="credit_period_days"
                                id="credit-period"
                                value="{{ old('credit_period_days', $prefillSale['credit_period_days']) }}"
                                data-default-value="{{ old('credit_period_days', $prefillSale['credit_period_days']) ?: 30 }}"
                            >
                        </label>
                    </div>
                    <label class="form-field" id="approval-pin-wrap" @if (! old('approval_pin')) hidden @endif>
                        <span>Admin Approval PIN</span>
                        <input type="password" name="approval_pin" id="approval-pin" value="{{ old('approval_pin') }}" placeholder="Needed for large discount approvals">
                    </label>
                    <label class="form-field">
                        <span>Remarks</span>
                        <textarea name="remarks" rows="3">{{ old('remarks', $prefillSale['remarks']) }}</textarea>
                    </label>
                </div>
            </section>

            <section class="panel">
                <h3>5. Save</h3>
                <p class="list-note">If the received amount is less than the total, this sale will be saved as credit and the remaining balance will stay on the customer account.</p>
                <div class="sale-footer-actions" style="margin-top: 10px;">
                    <button type="button" id="fill-total" class="button-link">Use Full Payment</button>
                    <button type="submit">Save Sale</button>
                </div>
            </section>
        </div>

        <div class="sale-mobile-bar">
            <div class="sale-mobile-meta">
                <div>
                    <div class="sale-mobile-sub">Total Sale</div>
                    <strong id="mobile-total-summary">{{ $currency }} 0</strong>
                </div>
                <div style="text-align:right;">
                    <div class="sale-mobile-sub">Balance</div>
                    <strong id="mobile-balance-summary">{{ $currency }} 0</strong>
                </div>
            </div>
            <div class="sale-mobile-actions">
                <button type="button" id="mobile-fill-total" class="button-link">Full</button>
                <button type="submit">Save Sale</button>
            </div>
        </div>
    </form>

    <div id="quick-customer-modal" class="sale-modal" aria-hidden="true">
        <div class="sale-modal-card">
            <div class="sale-modal-head">
                <div>
                    <h3>Add Customer</h3>
                    <p class="list-note" style="margin: 6px 0 0;">Create the customer quickly and continue the sale without leaving this page.</p>
                </div>
                <button type="button" id="quick-customer-close" class="modal-close" aria-label="Close">×</button>
            </div>
            <div class="form-grid">
                <label class="form-field">
                    <span>Name</span>
                    <input type="text" id="quick-customer-name" placeholder="Customer name">
                </label>
                <label class="form-field">
                    <span>Phone</span>
                    <input type="text" id="quick-customer-phone" placeholder="Phone">
                </label>
                <label class="form-field" style="grid-column: 1 / -1;">
                    <span>Location</span>
                    <input type="text" id="quick-customer-location" placeholder="Location">
                </label>
            </div>
            <div class="sale-footer-actions" style="margin-top: 16px;">
                <button type="button" id="quick-customer-save">Save Customer</button>
                <button type="button" id="quick-customer-cancel" class="button-link">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const currency = @json($currency);
            const allUnits = @json($unitsPayload);
            const allCustomers = @json($customersPayload);
            const form = document.getElementById('sale-form');
            if (!form) return;

            const cartList = document.getElementById('cart-list');
            const cartEmpty = document.getElementById('cart-empty');
            const hiddenInputs = document.getElementById('sale-items-hidden');
            const scanInput = document.getElementById('scan-search');
            const scanStatus = document.getElementById('scan-status');
            const searchInput = document.getElementById('product-search');
            const searchResults = document.getElementById('product-search-results');
            const amountPaidInput = document.getElementById('amount-paid');
            const discountAmountInput = document.getElementById('discount-amount');
            const creditPeriodInput = document.getElementById('credit-period');
            const creditPeriodWrap = document.getElementById('credit-period-wrap');
            const approvalPinWrap = document.getElementById('approval-pin-wrap');
            const saleDateInput = document.getElementById('sale-date');
            const customerSelect = document.getElementById('sale-customer');
            const customerDropdown = document.getElementById('customer-dropdown');
            const customerSearch = document.getElementById('customer-search');
            const customerResults = document.getElementById('customer-results');
            const quickCustomerOpen = document.getElementById('quick-customer-open');
            const quickCustomerName = document.getElementById('quick-customer-name');
            const quickCustomerPhone = document.getElementById('quick-customer-phone');
            const quickCustomerLocation = document.getElementById('quick-customer-location');
            const quickCustomerSave = document.getElementById('quick-customer-save');
            const quickCustomerModal = document.getElementById('quick-customer-modal');
            const quickCustomerClose = document.getElementById('quick-customer-close');
            const quickCustomerCancel = document.getElementById('quick-customer-cancel');
            const quickCustomerStatus = document.getElementById('quick-customer-status');
            const customerUseWalkIn = document.getElementById('customer-use-walk-in');
            const fillTotalButton = document.getElementById('fill-total');
            const clearCartButton = document.getElementById('clear-cart');
            const csrfToken = form.querySelector('input[name="_token"]').value;

            const saleKindBadge = document.getElementById('sale-kind-badge');
            const customerSummary = document.getElementById('customer-summary');
            const itemsSummary = document.getElementById('items-summary');
            const subtotalSummary = document.getElementById('subtotal-summary');
            const discountSummary = document.getElementById('discount-summary');
            const receivedSummary = document.getElementById('received-summary');
            const balanceSummary = document.getElementById('balance-summary');
            const changeSummary = document.getElementById('change-summary');
            const dueSummary = document.getElementById('due-summary');
            const totalSummary = document.getElementById('total-summary');
            const itemsInlineSummary = document.getElementById('items-inline-summary');
            const unitsInlineSummary = document.getElementById('units-inline-summary');
            const totalInlineSummary = document.getElementById('total-inline-summary');
            const mobileTotalSummary = document.getElementById('mobile-total-summary');
            const mobileBalanceSummary = document.getElementById('mobile-balance-summary');
            const mobileFillTotalButton = document.getElementById('mobile-fill-total');
            const cashierDiscountLimit = Number(@json($cashierDiscountLimit));
            const requiresApprovalPin = @json($requiresApprovalPin);

            const initialItems = [];
            @foreach (old('items', $prefillSale['items']) as $oldItem)
                @php($oldUnit = $productUnits->firstWhere('id', (int) ($oldItem['product_unit_id'] ?? 0)))
                @if ($oldUnit)
                    initialItems.push({
                        id: {{ $oldUnit->id }},
                        label: @json(trim($oldUnit->product->name.' - '.$oldUnit->unit_name)),
                        product_name: @json($oldUnit->product->name),
                        unit_name: @json($oldUnit->unit_name),
                        price: {{ (float) ($oldItem['unit_price'] ?? $oldUnit->selling_price) }},
                        quantity: {{ (int) round((float) ($oldItem['quantity'] ?? 1)) }},
                        barcode: @json($oldUnit->barcode),
                        code: @json($oldUnit->product->code),
                    });
                @endif
            @endforeach

            let cart = initialItems;
            let customers = allCustomers;
            let customerDropdownOpen = false;

            function money(value) {
                return `${currency} ${Number(value || 0).toLocaleString()}`;
            }

            function subtotal() {
                return cart.reduce((carry, item) => carry + (Number(item.quantity || 0) * Number(item.price || 0)), 0);
            }

            function normalizeQuantity(value) {
                return Math.max(Math.round(Number(value || 0)), 1);
            }

            function discountAmount() {
                return Math.min(Number(discountAmountInput.value || 0), subtotal());
            }

            function totalSale() {
                return Math.max(subtotal() - discountAmount(), 0);
            }

            function amountReceived() {
                return Number(amountPaidInput.value || 0);
            }

            function saleBalance() {
                return Math.max(totalSale() - Math.min(amountReceived(), totalSale()), 0);
            }

            function saleChange() {
                return Math.max(amountReceived() - totalSale(), 0);
            }

            function dueDatePreview() {
                const days = Number(creditPeriodInput.value || 0);
                if (!saleDateInput.value || !days || saleBalance() <= 0) {
                    return 'Not needed';
                }

                const base = new Date(`${saleDateInput.value}T00:00:00`);
                base.setDate(base.getDate() + days);
                return base.toLocaleDateString();
            }

            function walkInCustomer() {
                return customers.find((customer) => Boolean(customer.is_walk_in)) || null;
            }

            function selectedCustomer() {
                return customers.find((customer) => String(customer.id) === String(customerSelect.value)) || null;
            }

            function customerDisplay(customer) {
                if (!customer) {
                    return {
                        value: '',
                        meta: 'Choose a customer first. Use the walk-in record only when you do not want a named account.',
                    };
                }

                return {
                    value: customer.name,
                    meta: customer.is_walk_in
                        ? 'Walk-in customer. Use full payment unless you switch to a named customer.'
                        : ([customer.location, customer.credit > 0 ? `${money(customer.credit)} credit` : null].filter(Boolean).join(' / ') || 'Saved customer account'),
                };
            }

            function creditPeriodIsNeeded(customer = selectedCustomer()) {
                return Boolean(customer) && !customer.is_walk_in && saleBalance() > 0;
            }

            function syncCreditPeriodVisibility(customer = selectedCustomer()) {
                const shouldShow = creditPeriodIsNeeded(customer);

                if (!shouldShow && creditPeriodInput.value) {
                    creditPeriodInput.dataset.lastValue = creditPeriodInput.value;
                }

                if (creditPeriodWrap) {
                    creditPeriodWrap.hidden = !shouldShow;
                }

                if (shouldShow) {
                    if (!String(creditPeriodInput.value || '').trim()) {
                        creditPeriodInput.value = creditPeriodInput.dataset.lastValue || creditPeriodInput.dataset.defaultValue || '30';
                    }
                } else {
                    creditPeriodInput.value = '';
                }

                return shouldShow;
            }

            function setCustomerDropdown(open) {
                customerDropdownOpen = open;
                customerDropdown.classList.toggle('is-open', open);
                customerDropdown.setAttribute('aria-hidden', open ? 'false' : 'true');

                if (open) {
                    requestAnimationFrame(() => customerSearch.focus());
                } else {
                    renderCart();
                }
            }

            function renderSearchResults() {
                const needle = String(searchInput.value || '').trim().toLowerCase();
                const results = needle.length < 1
                    ? allUnits.slice(0, 8)
                    : allUnits.filter((item) => item.search.includes(needle)).slice(0, 20);

                searchResults.innerHTML = results.length
                    ? results.map((item) => `
                        <div class="sale-search-card">
                            <div>
                                <strong>${item.label}</strong>
                                <div class="sale-search-meta">
                                    ${item.code ? `Code: ${item.code} | ` : ''}${item.barcode ? `Barcode: ${item.barcode} | ` : ''}${item.part_number ? `Part: ${item.part_number}` : 'Ready to sell'}
                                </div>
                            </div>
                            <div class="sale-price-tag">${money(item.price)}</div>
                            <button type="button" class="sale-add-button" data-add-unit="${item.id}">Add</button>
                        </div>
                    `).join('')
                    : `<div class="empty-cart">No products matched that search.</div>`;
            }

            function findScanMatch(value) {
                const needle = String(value || '').trim().toLowerCase();
                if (!needle) {
                    return null;
                }

                const exactMatches = allUnits.filter((item) => [item.barcode, item.code, item.part_number]
                    .filter(Boolean)
                    .some((candidate) => String(candidate).trim().toLowerCase() === needle));

                if (exactMatches.length === 1) {
                    return exactMatches[0];
                }

                const exactSearchMatches = allUnits.filter((item) => String(item.search || '').trim() === needle);

                return exactSearchMatches.length === 1 ? exactSearchMatches[0] : null;
            }

            function setScanStatus(message, tone = 'muted') {
                if (!scanStatus) return;

                scanStatus.textContent = message;
                scanStatus.style.color = tone === 'success'
                    ? 'var(--brand)'
                    : (tone === 'error' ? 'var(--apple)' : 'var(--muted)');
            }

            function renderCart() {
                cartEmpty.style.display = cart.length ? 'none' : 'block';
                cartList.innerHTML = cart.map((item, index) => `
                    <div class="cart-item">
                        <div class="cart-item-head">
                            <div>
                                <strong>${item.label}</strong>
                                <div class="cart-item-sub">${item.code ? `Code: ${item.code}` : 'No code'}${item.barcode ? ` | Barcode: ${item.barcode}` : ''}</div>
                            </div>
                            <button type="button" class="cart-remove" data-remove-index="${index}">Remove</button>
                        </div>
                        <div class="cart-grid">
                            <label class="form-field">
                                <span>Quantity</span>
                                <div class="qty-box">
                                    <button type="button" data-qty-minus="${index}">-</button>
                                    <input type="number" min="1" step="1" value="${item.quantity}" data-qty-input="${index}">
                                    <button type="button" data-qty-plus="${index}">+</button>
                                </div>
                            </label>
                            <label class="form-field">
                                <span>Unit Price</span>
                                <input type="number" min="0" step="0.01" value="${item.price}" data-price-input="${index}">
                            </label>
                            <div class="form-field">
                                <span>Line Total</span>
                                <input type="text" value="${money(Number(item.quantity) * Number(item.price))}" readonly>
                            </div>
                        </div>
                    </div>
                `).join('');

                hiddenInputs.innerHTML = cart.map((item, index) => `
                    <input type="hidden" name="items[${index}][product_unit_id]" value="${item.id}">
                    <input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">
                    <input type="hidden" name="items[${index}][unit_price]" value="${item.price}">
                `).join('');

                itemsSummary.textContent = String(cart.length);
                itemsInlineSummary.textContent = String(cart.length);
                unitsInlineSummary.textContent = String(cart.reduce((carry, item) => carry + normalizeQuantity(item.quantity), 0));
                subtotalSummary.textContent = money(subtotal());
                discountSummary.textContent = money(discountAmount());
                totalInlineSummary.textContent = money(totalSale());
                totalSummary.textContent = money(totalSale());
                receivedSummary.textContent = money(Math.min(amountReceived(), totalSale()));
                balanceSummary.textContent = money(saleBalance());
                mobileTotalSummary.textContent = money(totalSale());
                mobileBalanceSummary.textContent = money(saleBalance());
                const activeCustomer = selectedCustomer();
                syncCreditPeriodVisibility(activeCustomer);
                changeSummary.textContent = money(saleChange());
                dueSummary.textContent = dueDatePreview();
                if (approvalPinWrap) {
                    const needsApproval = requiresApprovalPin && discountAmount() > cashierDiscountLimit;
                    approvalPinWrap.hidden = !needsApproval;
                }

                customerSummary.textContent = activeCustomer ? activeCustomer.name : 'Choose customer';
                const triggerCopy = customerDisplay(activeCustomer);
                customerSearch.classList.toggle('is-selected', Boolean(activeCustomer));
                if (!customerDropdownOpen) {
                    customerSearch.value = triggerCopy.value;
                }
                quickCustomerStatus.textContent = activeCustomer?.is_walk_in && saleBalance() > 0
                    ? 'Walk-in customer selected. Complete full payment or choose a named customer to continue on credit.'
                    : triggerCopy.meta;

                if (saleBalance() > 0) {
                    saleKindBadge.textContent = 'Credit Sale';
                    saleKindBadge.classList.add('credit');
                } else {
                    saleKindBadge.textContent = 'Cash Sale';
                    saleKindBadge.classList.remove('credit');
                }
            }

            function renderCustomerResults() {
                const selectedId = String(customerSelect.value || '');
                const needle = String(customerSearch.value || '').trim().toLowerCase();
                const filtered = needle.length < 1
                    ? customers.slice(0, 8)
                    : customers.filter((customer) => customer.search.includes(needle)).slice(0, 12);

                customerResults.innerHTML = filtered.map((customer) => `
                    <div class="customer-result">
                        <strong>${customer.name}${customer.is_walk_in ? ' (Walk-in)' : ''}</strong>
                        <div class="sale-search-meta">${[customer.location, customer.credit > 0 ? `${money(customer.credit)} credit` : null].filter(Boolean).join(' / ')}</div>
                        <button type="button" class="button-link" data-customer-pick="${customer.id}">${selectedId === String(customer.id) ? 'Selected' : 'Select'}</button>
                    </div>
                `).join('') || `<div class="empty-cart">No customer matched that search.</div>`;
            }

            function selectCustomer(customerId) {
                customerSelect.value = customerId ? String(customerId) : '';
                const selectedCustomer = customers.find((customer) => String(customer.id) === String(customerId));
                renderCustomerResults();
                renderCart();
                setCustomerDropdown(false);
            }

            function openQuickCustomerModal() {
                quickCustomerModal.classList.add('is-open');
                quickCustomerModal.setAttribute('aria-hidden', 'false');
                quickCustomerName.focus();
            }

            function closeQuickCustomerModal() {
                quickCustomerModal.classList.remove('is-open');
                quickCustomerModal.setAttribute('aria-hidden', 'true');
                quickCustomerOpen.focus();
            }

            function addUnit(unitId) {
                const unit = allUnits.find((item) => Number(item.id) === Number(unitId));
                if (!unit) return;

                const existing = cart.find((item) => Number(item.id) === Number(unit.id));
                if (existing) {
                    existing.quantity = normalizeQuantity(existing.quantity) + 1;
                } else {
                    cart.push({
                        ...unit,
                        quantity: 1,
                    });
                }

                renderCart();
                searchInput.value = '';
                renderSearchResults();
                if (scanInput && document.activeElement === scanInput) {
                    scanInput.focus();
                } else {
                    searchInput.focus();
                }
            }

            searchInput.addEventListener('input', renderSearchResults);
            searchInput.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                const firstButton = searchResults.querySelector('[data-add-unit]');
                if (firstButton) {
                    addUnit(firstButton.dataset.addUnit);
                }
            });

            scanInput?.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== 'Tab') return;
                event.preventDefault();

                const match = findScanMatch(scanInput.value);

                if (!match) {
                    setScanStatus('No exact barcode or code match was found. Use the search box to browse the item.', 'error');
                    searchInput.value = scanInput.value;
                    renderSearchResults();
                    searchInput.focus();
                    searchInput.select();
                    return;
                }

                addUnit(match.id);
                setScanStatus(`${match.label} added to sale.`, 'success');
                scanInput.value = '';
            });

            scanInput?.addEventListener('input', () => {
                if (String(scanInput.value || '').trim() === '') {
                    setScanStatus('Use this box for scanner input. Press Enter after scan if your scanner does not auto-submit.');
                }
            });

            searchResults.addEventListener('click', (event) => {
                const button = event.target.closest('[data-add-unit]');
                if (!button) return;
                addUnit(button.dataset.addUnit);
            });

            customerSearch.addEventListener('focus', () => {
                setCustomerDropdown(true);
                customerSearch.select();
                renderCustomerResults();
            });
            customerSearch.addEventListener('input', () => {
                setCustomerDropdown(true);
                renderCustomerResults();
            });
            customerResults.addEventListener('click', (event) => {
                const button = event.target.closest('[data-customer-pick]');
                if (!button) return;
                selectCustomer(button.dataset.customerPick);
            });

            cartList.addEventListener('click', (event) => {
                const removeButton = event.target.closest('[data-remove-index]');
                if (removeButton) {
                    cart.splice(Number(removeButton.dataset.removeIndex), 1);
                    renderCart();
                    return;
                }

                const plusButton = event.target.closest('[data-qty-plus]');
                if (plusButton) {
                    const index = Number(plusButton.dataset.qtyPlus);
                    cart[index].quantity = normalizeQuantity(cart[index].quantity) + 1;
                    renderCart();
                    return;
                }

                const minusButton = event.target.closest('[data-qty-minus]');
                if (minusButton) {
                    const index = Number(minusButton.dataset.qtyMinus);
                    cart[index].quantity = Math.max(normalizeQuantity(cart[index].quantity) - 1, 1);
                    renderCart();
                }
            });

            cartList.addEventListener('input', (event) => {
                const qtyInput = event.target.closest('[data-qty-input]');
                if (qtyInput) {
                    const index = Number(qtyInput.dataset.qtyInput);
                    cart[index].quantity = normalizeQuantity(qtyInput.value);
                    renderCart();
                    return;
                }

                const priceInput = event.target.closest('[data-price-input]');
                if (priceInput) {
                    const index = Number(priceInput.dataset.priceInput);
                    cart[index].price = Math.max(Number(priceInput.value || 0), 0);
                    renderCart();
                }
            });

            amountPaidInput.addEventListener('input', renderCart);
            discountAmountInput.addEventListener('input', renderCart);
            creditPeriodInput.addEventListener('input', () => {
                if (String(creditPeriodInput.value || '').trim()) {
                    creditPeriodInput.dataset.lastValue = creditPeriodInput.value;
                }
                renderCart();
            });
            saleDateInput.addEventListener('input', renderCart);

            fillTotalButton.addEventListener('click', () => {
                amountPaidInput.value = totalSale().toFixed(2);
                renderCart();
            });
            mobileFillTotalButton?.addEventListener('click', () => {
                amountPaidInput.value = totalSale().toFixed(2);
                renderCart();
            });

            clearCartButton.addEventListener('click', () => {
                cart = [];
                renderCart();
            });

            form.addEventListener('submit', (event) => {
                if (!cart.length) {
                    event.preventDefault();
                    alert('Add at least one product before saving the sale.');
                    searchInput.focus();
                    return;
                }

                if (!customerSelect.value) {
                    event.preventDefault();
                    alert('Choose a customer before saving the sale.');
                    customerSearch.focus();
                }
            });

            customerUseWalkIn.addEventListener('click', () => {
                const customer = walkInCustomer();
                if (!customer) {
                    quickCustomerStatus.textContent = 'No walk-in customer record is available yet.';
                    return;
                }

                selectCustomer(customer.id);
            });

            quickCustomerOpen.addEventListener('click', openQuickCustomerModal);
            quickCustomerClose.addEventListener('click', closeQuickCustomerModal);
            quickCustomerCancel.addEventListener('click', closeQuickCustomerModal);
            quickCustomerModal.addEventListener('click', (event) => {
                if (event.target === quickCustomerModal) {
                    closeQuickCustomerModal();
                }
            });

            quickCustomerSave.addEventListener('click', async () => {
                const name = String(quickCustomerName.value || '').trim();
                if (!name) {
                    quickCustomerStatus.textContent = 'Enter the customer name before saving.';
                    quickCustomerName.focus();
                    return;
                }

                quickCustomerSave.disabled = true;
                quickCustomerStatus.textContent = 'Saving customer...';

                try {
                    const response = await fetch(@json(route('customers.quick-store')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            name,
                            phone: String(quickCustomerPhone.value || '').trim(),
                            location: String(quickCustomerLocation.value || '').trim(),
                        }),
                    });

                    if (!response.ok) {
                        const payload = await response.json().catch(() => ({}));
                        const message = payload.message || 'Could not save the customer right now.';
                        throw new Error(message);
                    }

                    const customer = await response.json();
                    customers.unshift({
                        id: customer.id,
                        name: customer.name,
                        is_walk_in: false,
                        location: customer.location || '',
                        credit: 0,
                        search: String([customer.name || '', customer.location || ''].join(' ')).toLowerCase(),
                    });

                    quickCustomerName.value = '';
                    quickCustomerPhone.value = '';
                    quickCustomerLocation.value = '';
                    quickCustomerStatus.textContent = `${customer.name} added and selected for this sale.`;
                    selectCustomer(customer.id);
                    closeQuickCustomerModal();
                } catch (error) {
                    quickCustomerStatus.textContent = error.message || 'Could not save the customer right now.';
                } finally {
                    quickCustomerSave.disabled = false;
                }
            });

            renderSearchResults();
            renderCustomerResults();
            renderCart();
            if (customerSelect.value) {
                selectCustomer(customerSelect.value);
            }
            document.addEventListener('click', (event) => {
                if (!customerDropdownOpen) return;
                if (event.target.closest('.customer-picker')) return;
                setCustomerDropdown(false);
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && customerDropdownOpen) {
                    setCustomerDropdown(false);
                    customerSearch.blur();
                }
            });
            if (window.innerWidth > 760) {
                (scanInput || searchInput).focus();
            }
        })();
    </script>
@endsection
