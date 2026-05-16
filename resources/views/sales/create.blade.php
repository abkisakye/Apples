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
        .sale-workspace {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(360px, .9fr);
            gap: 12px;
            align-items: start;
        }
        .sale-lane {
            display: grid;
            gap: 12px;
        }
        .sale-workspace .panel {
            padding: 12px;
            border-radius: 18px;
        }
        .sale-hero {
            display: grid;
            gap: 10px;
        }
        .sale-hero-top {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: start;
        }
        .sale-hero-top h2,
        .sale-section-head h3,
        .sale-panel-title {
            margin: 0;
        }
        .sale-hero-note,
        .sale-section-head p,
        .sale-panel-subtitle,
        .sale-meta-note,
        .keypad-note {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: .8rem;
            line-height: 1.4;
        }
        .sale-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .sale-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 36px;
            padding: 0 11px;
            border-radius: 999px;
            border: 1px solid color-mix(in srgb, var(--line) 82%, var(--brand) 18%);
            background: linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(244, 248, 243, .96) 100%);
            color: var(--ink);
            font-size: .8rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .sale-hero-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .sale-hero-actions .button-link,
        .sale-hero-actions button {
            min-height: 38px;
        }
        .sale-search-panel {
            display: grid;
            gap: 10px;
        }
        .sale-search-row {
            display: grid;
            grid-template-columns: minmax(0, .82fr) minmax(0, 1.18fr);
            gap: 8px;
        }
        .sale-input {
            width: 100%;
            min-height: 42px;
            padding: 10px 12px;
            border-radius: 14px;
            border: 1px solid color-mix(in srgb, var(--line) 78%, var(--brand) 22%);
            background: #fff;
            color: var(--ink);
            font-size: .88rem;
        }
        .sale-input:focus,
        .sale-textarea:focus,
        .sale-select:focus {
            outline: none;
            border-color: color-mix(in srgb, var(--brand) 62%, white 38%);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.14);
        }
        .sale-textarea,
        .sale-select {
            width: 100%;
            border-radius: 14px;
            border: 1px solid color-mix(in srgb, var(--line) 78%, var(--brand) 22%);
            background: #fff;
            color: var(--ink);
            font-size: .86rem;
        }
        .sale-select {
            min-height: 42px;
            padding: 10px 12px;
        }
        .sale-textarea {
            min-height: 94px;
            padding: 10px 12px;
            resize: vertical;
        }
        .scan-status {
            min-height: 1.2em;
            color: var(--muted);
            font-size: .76rem;
        }
        .sale-kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }
        .sale-kpi {
            border: 1px solid color-mix(in srgb, var(--line) 84%, var(--brand) 16%);
            border-radius: 16px;
            padding: 10px 11px;
            background:
                radial-gradient(circle at top right, rgba(212, 175, 55, 0.16), transparent 44%),
                linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(246, 249, 246, .98) 100%);
        }
        .sale-kpi-label {
            font-size: .69rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
        }
        .sale-kpi-value {
            margin-top: 5px;
            font-size: 1rem;
            font-weight: 800;
            color: var(--ink);
        }
        .sale-product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(138px, 1fr));
            gap: 7px;
            max-height: calc(100vh - 320px);
            overflow-y: auto;
            padding-right: 2px;
        }
        .sale-results-meta {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            align-items: center;
            color: var(--muted);
            font-size: .76rem;
        }
        .sale-product-card {
            display: grid;
            gap: 8px;
            padding: 9px;
            border-radius: 15px;
            border: 1px solid color-mix(in srgb, var(--line) 82%, var(--brand) 18%);
            background:
                radial-gradient(circle at top right, rgba(212, 175, 55, 0.14), transparent 42%),
                linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(246, 249, 246, .98) 100%);
            min-height: 118px;
        }
        .sale-product-card strong {
            display: block;
            font-size: .84rem;
            line-height: 1.2;
        }
        .sale-product-meta {
            color: var(--muted);
            font-size: .7rem;
            line-height: 1.28;
        }
        .sale-product-footer {
            display: flex;
            justify-content: space-between;
            gap: 6px;
            align-items: end;
            margin-top: auto;
        }
        .sale-product-price {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 0 8px;
            border-radius: 999px;
            background: rgba(6, 104, 56, 0.1);
            color: var(--brand);
            font-size: .76rem;
            font-weight: 800;
            white-space: nowrap;
        }
        .sale-add-button {
            min-height: 31px;
            padding: 0 10px;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--brand) 0%, color-mix(in srgb, var(--brand) 70%, #0e4d2c 30%) 100%);
            color: #fff;
            font-weight: 800;
            cursor: pointer;
            font-size: .76rem;
        }
        .sale-bill-shell {
            position: sticky;
            top: 12px;
            display: grid;
            gap: 12px;
        }
        .sale-bill-top {
            display: grid;
            gap: 10px;
        }
        .sale-mini-grid {
            display: grid;
            grid-template-columns: minmax(170px, .68fr) minmax(0, 1.32fr);
            gap: 8px;
            align-items: start;
        }
        .sale-field {
            display: grid;
            gap: 5px;
            align-content: start;
            align-items: start;
        }
        .sale-field span {
            font-size: .72rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .sale-date-compact {
            min-height: 42px;
            max-width: 190px;
        }
        .customer-picker {
            position: relative;
            display: grid;
            gap: 6px;
            z-index: 18;
        }
        .customer-search-input {
            width: 100%;
            min-height: 42px;
            padding: 10px 12px;
            border-radius: 14px;
            border: 1px solid color-mix(in srgb, var(--line) 78%, var(--brand) 22%);
            background: #fff;
            color: var(--ink);
            font-size: .88rem;
        }
        .customer-search-input.is-selected {
            font-weight: 700;
        }
        .customer-dropdown {
            display: none;
            gap: 8px;
            padding: 10px;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            border-radius: 16px;
            border: 1px solid color-mix(in srgb, var(--line) 82%, var(--brand) 18%);
            background: #fff;
            box-shadow: 0 24px 44px rgba(16, 30, 23, 0.16);
        }
        .customer-dropdown.is-open {
            display: grid;
        }
        .customer-results {
            display: grid;
            gap: 6px;
            max-height: 220px;
            overflow-y: auto;
            padding-right: 2px;
        }
        .customer-result {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
            align-items: center;
            padding: 8px 10px;
            border-radius: 13px;
            border: 1px solid color-mix(in srgb, var(--line) 86%, var(--brand) 14%);
            background: rgba(249, 251, 249, 0.98);
        }
        .customer-result strong {
            display: block;
            margin: 0 0 2px;
            font-size: .82rem;
        }
        .customer-result .button-link {
            min-width: 76px;
            justify-content: center;
            padding-inline: 10px;
        }
        .quick-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .quick-actions .button-link {
            flex: 1 1 132px;
            justify-content: center;
        }
        .quick-customer-status {
            color: var(--muted);
            font-size: .78rem;
            line-height: 1.35;
        }
        .selected-customer-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 34px;
            padding: 0 10px;
            border-radius: 999px;
            background: rgba(6, 104, 56, 0.08);
            color: var(--brand);
            font-size: .78rem;
            font-weight: 700;
            width: fit-content;
            max-width: 100%;
        }
        .selected-customer-chip.empty {
            background: rgba(109, 101, 84, 0.08);
            color: var(--muted);
        }
        .sale-kind-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 0 12px;
            border-radius: 999px;
            background: rgba(6, 104, 56, 0.1);
            color: var(--brand);
            font-weight: 800;
            font-size: .8rem;
        }
        .sale-kind-pill.credit {
            background: rgba(102, 40, 40, 0.11);
            color: var(--apple);
        }
        .sale-cart-wrap {
            display: grid;
            gap: 8px;
        }
        .bill-list {
            display: grid;
            gap: 8px;
            max-height: 360px;
            overflow-y: auto;
            padding-right: 2px;
        }
        .bill-empty {
            padding: 20px 14px;
            border-radius: 16px;
            border: 1px dashed color-mix(in srgb, var(--line-strong) 76%, var(--brand) 24%);
            background: rgba(248, 250, 248, 0.98);
            text-align: center;
            color: var(--muted);
            font-size: .84rem;
        }
        .bill-row {
            display: grid;
            gap: 8px;
            padding: 10px;
            border-radius: 16px;
            border: 1px solid color-mix(in srgb, var(--line) 84%, var(--brand) 16%);
            background: #fff;
        }
        .bill-row-top {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            align-items: start;
        }
        .bill-row-title strong {
            display: block;
            font-size: .88rem;
            line-height: 1.25;
        }
        .bill-row-sub {
            margin-top: 2px;
            color: var(--muted);
            font-size: .75rem;
        }
        .bill-remove {
            min-width: 34px;
            height: 34px;
            border-radius: 10px;
            border: 1px solid rgba(102, 40, 40, 0.18);
            background: rgba(102, 40, 40, 0.08);
            color: var(--apple);
            font-weight: 900;
            cursor: pointer;
        }
        .bill-row-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, .92fr) minmax(0, .82fr);
            gap: 8px;
        }
        .bill-label {
            font-size: .68rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 5px;
        }
        .qty-box {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr) 34px;
            gap: 6px;
            align-items: center;
        }
        .qty-box button {
            min-width: 34px;
            height: 34px;
            border-radius: 11px;
            border: 1px solid color-mix(in srgb, var(--line) 76%, var(--brand) 24%);
            background: rgba(6, 104, 56, 0.08);
            color: var(--brand);
            font-size: .95rem;
            font-weight: 800;
            cursor: pointer;
        }
        .qty-box input,
        .bill-row-grid input,
        .sale-payment-grid input {
            width: 100%;
            min-height: 36px;
            padding: 8px 10px;
            border-radius: 12px;
            border: 1px solid color-mix(in srgb, var(--line) 78%, var(--brand) 22%);
            background: #fff;
            color: var(--ink);
            font-size: .86rem;
        }
        .qty-box input {
            text-align: center;
        }
        .bill-line-total {
            display: flex;
            align-items: center;
            min-height: 36px;
            padding: 0 10px;
            border-radius: 12px;
            background: rgba(6, 104, 56, 0.08);
            color: var(--brand);
            font-size: .83rem;
            font-weight: 800;
        }
        .sale-total-stack {
            display: grid;
            gap: 8px;
        }
        .sale-total-box {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
            padding: 11px 12px;
            border-radius: 16px;
            border: 1px solid color-mix(in srgb, var(--line) 84%, var(--brand) 16%);
            background: rgba(249, 251, 249, 0.98);
        }
        .sale-total-box span {
            color: var(--muted);
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .sale-total-box strong {
            font-size: .92rem;
        }
        .sale-total-box.grand {
            background: linear-gradient(135deg, rgba(6, 104, 56, 0.1) 0%, rgba(212, 175, 55, 0.12) 100%);
            border-color: color-mix(in srgb, var(--brand) 28%, var(--line) 72%);
        }
        .sale-total-box.grand strong {
            font-size: 1.16rem;
        }
        .sale-payment-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
        .sale-actions-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .sale-actions-row .button-link,
        .sale-actions-row button {
            flex: 1 1 140px;
            justify-content: center;
        }
        .sale-primary-button {
            min-height: 44px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--brand) 0%, color-mix(in srgb, var(--brand) 70%, #0e4d2c 30%) 100%);
            color: #fff;
            font-weight: 800;
            cursor: pointer;
            font-size: .92rem;
        }
        .sale-primary-button:hover,
        .sale-add-button:hover {
            filter: brightness(1.02);
        }
        .sale-keypad {
            display: grid;
            gap: 8px;
        }
        .sale-keypad-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }
        .sale-key {
            min-height: 48px;
            border-radius: 14px;
            border: 1px solid color-mix(in srgb, var(--line) 76%, var(--brand) 24%);
            background: linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(245, 248, 245, .98) 100%);
            color: var(--ink);
            font-size: .92rem;
            font-weight: 800;
            cursor: pointer;
        }
        .sale-key.action {
            background: rgba(6, 104, 56, 0.08);
            color: var(--brand);
        }
        .sale-key.wide {
            grid-column: span 2;
        }
        .sale-mobile-bar {
            display: none;
        }
        .sale-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(17, 27, 22, 0.44);
            z-index: 80;
        }
        .sale-modal.is-open {
            display: flex;
        }
        .sale-modal-card {
            width: min(520px, 100%);
            padding: 16px;
            border-radius: 20px;
            border: 1px solid color-mix(in srgb, var(--line) 84%, var(--brand) 16%);
            background: #fff;
            box-shadow: 0 28px 48px rgba(15, 28, 22, 0.2);
        }
        .sale-modal-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: start;
            margin-bottom: 14px;
        }
        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 11px;
            border: 1px solid color-mix(in srgb, var(--line) 80%, var(--brand) 20%);
            background: #fff;
            color: var(--ink);
            cursor: pointer;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .summary-callout {
            padding: 11px 12px;
            border-radius: 14px;
            border: 1px solid rgba(212, 175, 55, 0.28);
            background: rgba(212, 175, 55, 0.08);
            color: var(--ink);
            font-size: .83rem;
            line-height: 1.4;
        }
        @media (max-width: 1180px) {
            .sale-workspace {
                grid-template-columns: 1fr;
            }
            .sale-bill-shell {
                position: static;
            }
            .sale-product-grid {
                max-height: none;
            }
        }
        @media (max-width: 760px) {
            .sale-search-row,
            .sale-mini-grid,
            .sale-payment-grid,
            .bill-row-grid,
            .form-grid {
                grid-template-columns: 1fr;
            }
            .sale-kpis {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .sale-product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .sale-badges {
                justify-content: start;
            }
            .customer-dropdown {
                position: fixed;
                left: 12px;
                right: 12px;
                bottom: 12px;
                top: auto;
                max-height: min(72vh, 520px);
                z-index: 90;
            }
            .sale-keypad-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .sale-key.wide {
                grid-column: span 1;
            }
            .sale-bill-shell .sale-actions-row {
                display: none;
            }
            .sale-mobile-bar {
                position: sticky;
                bottom: 8px;
                display: grid;
                gap: 8px;
                padding: 8px;
                border-radius: 16px;
                border: 1px solid color-mix(in srgb, var(--line) 82%, var(--brand) 18%);
                background: rgba(255,255,255,.96);
                box-shadow: 0 14px 24px rgba(19, 30, 24, 0.14);
                backdrop-filter: blur(10px);
            }
            .sale-mobile-meta {
                display: flex;
                justify-content: space-between;
                gap: 10px;
                align-items: center;
            }
            .sale-mobile-sub {
                color: var(--muted);
                font-size: .75rem;
            }
            .sale-mobile-meta strong {
                font-size: 1rem;
            }
            .sale-mobile-actions {
                display: grid;
                grid-template-columns: 1fr 1.35fr;
                gap: 8px;
            }
            .sale-modal {
                align-items: end;
                padding: 12px;
            }
            .sale-modal-card {
                border-radius: 20px 20px 0 0;
            }
        }
    </style>

    <div class="sale-hero panel">
        <div class="sale-hero-top">
            <div>
                <h2>Sales Desk</h2>
                <p class="sale-hero-note">
                    Search or scan items, build the bill, capture payment, and post the sale from one cashier-focused screen.
                </p>
            </div>
            <div class="sale-badges">
                @if ($requiresShift)
                    @if ($activeShift)
                        <div class="sale-badge">Shift {{ $activeShift->shift_no }}</div>
                    @else
                        <a href="{{ route('cash-shifts.create') }}" class="button-link primary">Open Shift First</a>
                    @endif
                @endif
                @if ($currentStore)
                    <div class="sale-badge">{{ $currentStore->name }}</div>
                @endif
            </div>
        </div>
        <div class="sale-hero-actions">
            <a href="{{ route('sales.index') }}" class="button-link">Back to Sales</a>
            <a href="{{ route('customer-payments.create') }}" class="button-link">Customer Payment</a>
        </div>
    </div>

    <form method="post" action="{{ route('sales.store') }}" id="sale-form" class="sale-workspace">
        @csrf
        <input type="hidden" name="corrected_from_sale_id" value="{{ old('corrected_from_sale_id', $sourceSale?->id) }}">
        <input type="hidden" name="exchange_return_id" value="{{ old('exchange_return_id', $exchangeReturn?->id) }}">
        <input type="hidden" name="store_id" value="{{ $currentStore?->id }}">

        <div class="sale-lane">
            <section class="panel sale-search-panel">
                <div class="sale-section-head">
                    <div>
                        <h3>Find Products</h3>
                        <p>Use the scanner box for barcode input or type part of the product name, code, or part number.</p>
                    </div>
                </div>

                @if ($exchangeReturn && $exchangeReturn->return_type === 'exchange')
                    <div class="summary-callout">
                        Exchange return <strong>{{ $exchangeReturn->return_no }}</strong> is open for this customer. Returned items are already loaded as a starting point for the replacement sale.
                    </div>
                @endif

                <div class="sale-search-row">
                    <div>
                        <input type="text" id="scan-search" class="sale-input" placeholder="Scan barcode or code">
                        <div id="scan-status" class="scan-status">Use this box for scanner input. Press Enter after scan if your scanner does not auto-submit.</div>
                    </div>
                    <input type="text" id="product-search" class="sale-input" placeholder="Search product, barcode, code, or part number">
                </div>

                <div class="sale-kpis">
                    <div class="sale-kpi">
                        <div class="sale-kpi-label">Lines</div>
                        <div class="sale-kpi-value" id="items-inline-summary">0</div>
                    </div>
                    <div class="sale-kpi">
                        <div class="sale-kpi-label">Units</div>
                        <div class="sale-kpi-value" id="units-inline-summary">0</div>
                    </div>
                    <div class="sale-kpi">
                        <div class="sale-kpi-label">Customer</div>
                        <div class="sale-kpi-value" id="hero-customer-summary">Not set</div>
                    </div>
                    <div class="sale-kpi">
                        <div class="sale-kpi-label">Total</div>
                        <div class="sale-kpi-value" id="total-inline-summary">{{ $currency }} 0</div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="sale-section-head">
                    <div>
                        <h3>Product Tiles</h3>
                        <p>Tap an item once to add it. The bill updates immediately on the right.</p>
                    </div>
                </div>
                <div class="sale-results-meta">
                    <div id="product-results-note">Showing ready items for quick picking.</div>
                    <div id="product-results-count"></div>
                </div>
                <div style="height: 8px;"></div>
                <div id="product-search-results" class="sale-product-grid"></div>
            </section>
        </div>

        <div class="sale-bill-shell">
            <section class="panel sale-bill-top">
                <div class="sale-section-head">
                    <div>
                        <h3>Bill And Customer</h3>
                        <p>Choose the customer first, then capture payment after the basket is ready.</p>
                    </div>
                    <div id="sale-kind-badge" class="sale-kind-pill">Cash Sale</div>
                </div>

                @if ($requiresShift && ! $activeShift)
                    <div class="bill-empty" style="text-align:left;">
                        Open a cashier shift before posting sales from this account. This keeps day cash and cashier accountability accurate.
                    </div>
                @endif

                <div class="sale-mini-grid">
                    <label class="sale-field">
                        <span>Sale Date</span>
                        <input type="date" name="sale_date" id="sale-date" class="sale-input sale-date-compact" value="{{ old('sale_date', now()->toDateString()) }}" required data-keypad-input="date">
                    </label>
                    <div class="sale-field">
                        <span>Customer</span>
                        <input type="hidden" name="customer_id" id="sale-customer" value="{{ old('customer_id', $prefillSale['customer_id']) }}">
                        <div class="customer-picker">
                            <input type="text" id="customer-search" class="customer-search-input" placeholder="Search customer by name or place" autocomplete="off">
                            <div id="customer-dropdown" class="customer-dropdown" aria-hidden="true">
                                <div id="customer-results" class="customer-results"></div>
                                <div class="quick-actions">
                                    <button type="button" id="quick-customer-open" class="button-link">Add Customer</button>
                                    <button type="button" id="customer-use-walk-in" class="button-link">Use Walk-in</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="selected-customer-chip" class="selected-customer-chip empty">No customer selected yet</div>
                <div id="quick-customer-status" class="quick-customer-status">Pick a customer first. Use walk-in only when you do not need a named customer account.</div>
            </section>

            <section class="panel sale-cart-wrap">
                <div class="sale-section-head">
                    <div>
                        <h3>Selected Products</h3>
                        <p>Adjust quantity or price directly from the selected items list.</p>
                    </div>
                    <button type="button" id="clear-cart" class="button-link">Clear Sale</button>
                </div>

                <div id="cart-empty" class="bill-empty">
                    No items on the bill yet. Search or scan a product to begin.
                </div>
                <div id="cart-list" class="bill-list"></div>
                <div id="sale-items-hidden"></div>
            </section>

            <section class="panel">
                <div class="sale-section-head">
                    <div>
                        <h3>Checkout</h3>
                        <p>Totals update instantly. If payment is short, the balance remains as customer credit.</p>
                    </div>
                </div>

                <div class="sale-total-stack">
                    <div class="sale-total-box">
                        <span>Customer</span>
                        <strong id="customer-summary">Choose customer</strong>
                    </div>
                    <div class="sale-total-box">
                        <span>Items</span>
                        <strong id="items-summary">0</strong>
                    </div>
                    <div class="sale-total-box">
                        <span>Subtotal</span>
                        <strong id="subtotal-summary">{{ $currency }} 0</strong>
                    </div>
                    <div class="sale-total-box">
                        <span>Discount</span>
                        <strong id="discount-summary">{{ $currency }} 0</strong>
                    </div>
                    <div class="sale-total-box">
                        <span>Received</span>
                        <strong id="received-summary">{{ $currency }} 0</strong>
                    </div>
                    <div class="sale-total-box">
                        <span>Balance</span>
                        <strong id="balance-summary">{{ $currency }} 0</strong>
                    </div>
                    <div class="sale-total-box">
                        <span>Change</span>
                        <strong id="change-summary">{{ $currency }} 0</strong>
                    </div>
                    <div class="sale-total-box">
                        <span>Due Date</span>
                        <strong id="due-summary">Not needed</strong>
                    </div>
                    <div class="sale-total-box grand">
                        <span>Total Sale</span>
                        <strong id="total-summary">{{ $currency }} 0</strong>
                    </div>
                </div>

                <div style="height: 10px;"></div>

                <div class="sale-payment-grid">
                    <label class="sale-field">
                        <span>Payment Mode</span>
                        <select name="payment_mode_id" class="sale-select">
                            <option value="">Auto-select from balance</option>
                            @foreach ($paymentModes as $mode)
                                <option value="{{ $mode->id }}" @selected((string) old('payment_mode_id') === (string) $mode->id)>{{ $mode->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="sale-field">
                        <span>Amount Received</span>
                        <input type="number" step="0.01" min="0" name="amount_paid" id="amount-paid" value="{{ old('amount_paid', $prefillSale['amount_paid']) }}" data-keypad-input="decimal">
                    </label>
                    <label class="sale-field">
                        <span>Discount Amount</span>
                        <input type="number" step="0.01" min="0" name="discount_amount" id="discount-amount" value="{{ old('discount_amount', $prefillSale['discount_amount']) }}" data-keypad-input="decimal">
                    </label>
                    <label class="sale-field" id="credit-period-wrap">
                        <span>Credit Period (days)</span>
                        <input
                            type="number"
                            min="1"
                            name="credit_period_days"
                            id="credit-period"
                            value="{{ old('credit_period_days', $prefillSale['credit_period_days']) }}"
                            data-default-value="{{ old('credit_period_days', $prefillSale['credit_period_days']) ?: 30 }}"
                            data-keypad-input="integer"
                        >
                    </label>
                </div>

                <div style="height: 8px;"></div>

                <label class="sale-field" id="approval-pin-wrap" @if (! old('approval_pin')) hidden @endif>
                    <span>Admin Approval PIN</span>
                    <input type="password" name="approval_pin" id="approval-pin" class="sale-input" value="{{ old('approval_pin') }}" placeholder="Needed for large discount approvals">
                </label>

                <div style="height: 8px;"></div>

                <label class="sale-field">
                    <span>Remarks</span>
                    <textarea name="remarks" class="sale-textarea">{{ old('remarks', $prefillSale['remarks']) }}</textarea>
                </label>

                <div style="height: 12px;"></div>

                <div class="sale-actions-row">
                    <button type="button" id="fill-total" class="button-link">Use Full Payment</button>
                    <button type="submit" class="sale-primary-button">Save Sale</button>
                </div>
            </section>

            <section class="panel sale-keypad">
                <div>
                    <h3 class="sale-panel-title">Cashier Keypad</h3>
                    <p class="keypad-note">Tap a quantity, price, discount, amount received, or credit days field, then use the keypad for quick entry.</p>
                </div>
                <div class="sale-keypad-grid" id="sale-keypad">
                    <button type="button" class="sale-key" data-keypad="7">7</button>
                    <button type="button" class="sale-key" data-keypad="8">8</button>
                    <button type="button" class="sale-key" data-keypad="9">9</button>
                    <button type="button" class="sale-key action" data-keypad-action="backspace">⌫</button>
                    <button type="button" class="sale-key" data-keypad="4">4</button>
                    <button type="button" class="sale-key" data-keypad="5">5</button>
                    <button type="button" class="sale-key" data-keypad="6">6</button>
                    <button type="button" class="sale-key action" data-keypad-action="clear">C</button>
                    <button type="button" class="sale-key" data-keypad="1">1</button>
                    <button type="button" class="sale-key" data-keypad="2">2</button>
                    <button type="button" class="sale-key" data-keypad="3">3</button>
                    <button type="button" class="sale-key action" data-keypad-action="full">Full</button>
                    <button type="button" class="sale-key wide" data-keypad="0">0</button>
                    <button type="button" class="sale-key" data-keypad=".">.</button>
                    <button type="button" class="sale-key action" data-keypad-action="plus-one">+1</button>
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
                <button type="submit" class="sale-primary-button">Save Sale</button>
            </div>
        </div>
    </form>

    <div id="quick-customer-modal" class="sale-modal" aria-hidden="true">
        <div class="sale-modal-card">
            <div class="sale-modal-head">
                <div>
                    <h3 class="sale-panel-title">Add Customer</h3>
                    <p class="sale-panel-subtitle">Create a customer quickly and continue selling without leaving the sales desk.</p>
                </div>
                <button type="button" id="quick-customer-close" class="modal-close" aria-label="Close">×</button>
            </div>
            <div class="form-grid">
                <label class="sale-field">
                    <span>Name</span>
                    <input type="text" id="quick-customer-name" class="sale-input" placeholder="Customer name">
                </label>
                <label class="sale-field">
                    <span>Phone</span>
                    <input type="text" id="quick-customer-phone" class="sale-input" placeholder="Phone">
                </label>
                <label class="sale-field" style="grid-column: 1 / -1;">
                    <span>Location</span>
                    <input type="text" id="quick-customer-location" class="sale-input" placeholder="Location">
                </label>
            </div>
            <div class="sale-actions-row" style="margin-top: 16px;">
                <button type="button" id="quick-customer-save" class="sale-primary-button">Save Customer</button>
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
            const productResultsNote = document.getElementById('product-results-note');
            const productResultsCount = document.getElementById('product-results-count');
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
            const keypad = document.getElementById('sale-keypad');

            const saleKindBadge = document.getElementById('sale-kind-badge');
            const customerSummary = document.getElementById('customer-summary');
            const heroCustomerSummary = document.getElementById('hero-customer-summary');
            const selectedCustomerChip = document.getElementById('selected-customer-chip');
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
            let activeKeypadInput = null;

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
                        meta: 'Choose a customer first. Use the walk-in record only when you do not need a named account.',
                    };
                }

                return {
                    value: customer.name,
                    meta: customer.is_walk_in
                        ? 'Walk-in customer. Complete full payment unless you switch to a named customer.'
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
                const matching = needle.length < 1
                    ? allUnits
                    : allUnits.filter((item) => item.search.includes(needle));
                const displayLimit = needle.length < 1 ? 36 : 120;
                const results = matching.slice(0, displayLimit);

                if (productResultsNote) {
                    productResultsNote.textContent = needle.length < 1
                        ? 'Showing the first quick-pick items. Start typing to search the full product list.'
                        : `Search checks the full product list for "${searchInput.value.trim()}".`;
                }

                if (productResultsCount) {
                    productResultsCount.textContent = matching.length > displayLimit
                        ? `Showing ${results.length} of ${matching.length}`
                        : `${matching.length} match${matching.length === 1 ? '' : 'es'}`;
                }

                searchResults.innerHTML = results.length
                    ? results.map((item) => `
                        <div class="sale-product-card">
                            <div>
                                <strong>${item.label}</strong>
                                <div class="sale-product-meta">
                                    ${item.code ? `Code: ${item.code}<br>` : ''}${item.barcode ? `Barcode: ${item.barcode}<br>` : ''}${item.part_number ? `Part: ${item.part_number}` : 'Ready to sell'}
                                </div>
                            </div>
                            <div class="sale-product-footer">
                                <div class="sale-product-price">${money(item.price)}</div>
                                <button type="button" class="sale-add-button" data-add-unit="${item.id}">Add</button>
                            </div>
                        </div>
                    `).join('')
                    : `<div class="bill-empty" style="grid-column: 1 / -1;">No products matched that search.</div>`;
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
                    <div class="bill-row">
                        <div class="bill-row-top">
                            <div class="bill-row-title">
                                <strong>${item.label}</strong>
                                <div class="bill-row-sub">${item.code ? `Code: ${item.code}` : 'No code'}${item.barcode ? ` | Barcode: ${item.barcode}` : ''}</div>
                            </div>
                            <button type="button" class="bill-remove" data-remove-index="${index}" aria-label="Remove item">×</button>
                        </div>
                        <div class="bill-row-grid">
                            <div>
                                <div class="bill-label">Quantity</div>
                                <div class="qty-box">
                                    <button type="button" data-qty-minus="${index}">-</button>
                                    <input type="number" min="1" step="1" value="${item.quantity}" data-qty-input="${index}" data-keypad-input="integer">
                                    <button type="button" data-qty-plus="${index}">+</button>
                                </div>
                            </div>
                            <div>
                                <div class="bill-label">Unit Price</div>
                                <input type="number" min="0" step="0.01" value="${item.price}" data-price-input="${index}" data-keypad-input="decimal">
                            </div>
                            <div>
                                <div class="bill-label">Line Total</div>
                                <div class="bill-line-total">${money(Number(item.quantity) * Number(item.price))}</div>
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
                heroCustomerSummary.textContent = activeCustomer ? activeCustomer.name : 'Not set';
                if (selectedCustomerChip) {
                    selectedCustomerChip.textContent = activeCustomer
                        ? (activeCustomer.is_walk_in
                            ? `${activeCustomer.name} selected`
                            : `${activeCustomer.name}${activeCustomer.location ? ` / ${activeCustomer.location}` : ''}`)
                        : 'No customer selected yet';
                    selectedCustomerChip.classList.toggle('empty', !activeCustomer);
                }
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
                        <div>
                            <strong>${customer.name}${customer.is_walk_in ? ' (Walk-in)' : ''}</strong>
                            <div class="sale-product-meta">${[customer.location, customer.credit > 0 ? `${money(customer.credit)} credit` : null].filter(Boolean).join(' / ') || 'Saved customer account'}</div>
                        </div>
                        <button type="button" class="button-link" data-customer-pick="${customer.id}">${selectedId === String(customer.id) ? 'Selected' : 'Select'}</button>
                    </div>
                `).join('') || `<div class="bill-empty">No customer matched that search.</div>`;
            }

            function selectCustomer(customerId) {
                customerSelect.value = customerId ? String(customerId) : '';
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

            function applyKeypadValue(rawValue) {
                if (!activeKeypadInput || activeKeypadInput.disabled || activeKeypadInput.readOnly) {
                    return;
                }

                if (activeKeypadInput.type === 'date') {
                    return;
                }

                const mode = activeKeypadInput.dataset.keypadInput || 'decimal';
                const current = String(activeKeypadInput.value || '');
                let nextValue = current;

                if (rawValue === '.') {
                    if (mode === 'integer' || current.includes('.')) {
                        return;
                    }
                    nextValue = current === '' ? '0.' : `${current}.`;
                } else {
                    nextValue = `${current}${rawValue}`;
                }

                activeKeypadInput.value = nextValue;
                activeKeypadInput.dispatchEvent(new Event('input', { bubbles: true }));
                activeKeypadInput.focus();
            }

            function handleKeypadAction(action) {
                if (action === 'full') {
                    amountPaidInput.value = totalSale().toFixed(2);
                    amountPaidInput.dispatchEvent(new Event('input', { bubbles: true }));
                    amountPaidInput.focus();
                    activeKeypadInput = amountPaidInput;
                    return;
                }

                if (!activeKeypadInput || activeKeypadInput.disabled || activeKeypadInput.readOnly) {
                    return;
                }

                if (action === 'clear') {
                    activeKeypadInput.value = '';
                }

                if (action === 'backspace') {
                    activeKeypadInput.value = String(activeKeypadInput.value || '').slice(0, -1);
                }

                if (action === 'plus-one') {
                    const current = Number(activeKeypadInput.value || 0);
                    const increment = activeKeypadInput.dataset.keypadInput === 'integer' ? 1 : 1;
                    activeKeypadInput.value = String(current + increment);
                }

                activeKeypadInput.dispatchEvent(new Event('input', { bubbles: true }));
                activeKeypadInput.focus();
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

            form.addEventListener('focusin', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLInputElement)) {
                    return;
                }
                if (!target.matches('[data-keypad-input]')) {
                    return;
                }
                activeKeypadInput = target;
            });

            keypad?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-keypad],[data-keypad-action]');
                if (!button) return;

                if (button.dataset.keypadAction) {
                    handleKeypadAction(button.dataset.keypadAction);
                    return;
                }

                applyKeypadValue(button.dataset.keypad);
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
