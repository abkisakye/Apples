@extends('layouts.app', ['title' => 'New Purchase'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($suppliersPayload = $suppliers->map(fn ($supplier) => [
        'id' => $supplier->id,
        'name' => $supplier->name,
        'phone' => $supplier->phone,
        'country' => $supplier->country,
        'credit' => (float) ($supplier->outstanding_credit ?? 0),
        'search' => strtolower(trim(implode(' ', array_filter([$supplier->name, $supplier->phone, $supplier->country])))),
    ]))
    @php($unitsPayload = $productUnits->map(fn ($unit) => [
        'id' => $unit->id,
        'label' => trim($unit->product->name.' - '.$unit->unit_name),
        'product_name' => $unit->product->name,
        'unit_name' => $unit->unit_name,
        'price' => (float) $unit->cost_price,
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
        :root {
            --purchase-main: #92400E;
            --purchase-soft: #FFF7ED;
            --purchase-border: #FDBA74;
            --purchase-button: #B45309;
            --purchase-blue: #1D4ED8;
            --purchase-ink: #1F2937;
        }
        .receive-shell { display:grid; grid-template-columns:300px minmax(420px,1fr) 300px; grid-template-areas:"details finder summary" "items items summary"; gap:12px; align-items:start; }
        .receive-panel { border:1px solid #fed7aa; border-radius:10px; background:#fffdfa; padding:12px; box-shadow:0 10px 24px rgba(146,64,14,.06); }
        .receive-panel h3 { margin:0; color:var(--purchase-main); font-size:1rem; }
        .receive-panel .list-note { margin:4px 0 0; font-size:.82rem; }
        .details-panel { grid-area:details; }
        .finder-panel { grid-area:finder; }
        .items-panel { grid-area:items; }
        .summary-panel { grid-area:summary; position:sticky; top:14px; }
        .receive-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; margin-top:10px; }
        .receive-grid .span-full { grid-column:1 / -1; }
        .picker-input { width:100%; min-height:40px; padding:9px 11px; border-radius:9px; border:1px solid #d6d3d1; background:#fff; color:var(--ink); font-size:.92rem; }
        .receive-panel input, .receive-panel select, .receive-panel textarea { border-color:#d6d3d1; border-radius:9px; }
        .receive-panel button:not(.button-link), .receive-primary-button { border:0; border-radius:9px; padding:9px 12px; background:var(--purchase-button); color:#fff; font-weight:800; cursor:pointer; }
        .receive-panel .button-link { border-color:#fed7aa; color:var(--purchase-main); background:#fffaf5; }
        .find-row { display:grid; grid-template-columns:minmax(0,1fr) auto auto auto; gap:8px; align-items:center; margin-top:10px; }
        .mini-stat { border:1px solid #fed7aa; border-radius:9px; padding:6px 8px; background:var(--purchase-soft); min-width:74px; }
        .mini-stat .label { color:#78716c; font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; }
        .mini-stat .value { margin-top:2px; font-size:.9rem; font-weight:800; color:var(--purchase-main); }
        .result-list { display:grid; gap:7px; margin-top:10px; max-height:190px; overflow-y:auto; }
        .result-card { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:10px; align-items:center; padding:8px 10px; border-radius:9px; border:1px solid #e7e5e4; background:#fff; }
        .result-card strong { display:block; margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .result-meta { color:#6b7280; font-size:.78rem; line-height:1.35; }
        .receive-add-button { border:0; border-radius:8px; padding:7px 10px; background:#1D4ED8; color:#fff; font-weight:800; cursor:pointer; font-size:.78rem; }
        .party-results { display:grid; gap:6px; max-height:120px; overflow-y:auto; }
        .party-result { display:flex; justify-content:space-between; gap:8px; align-items:center; padding:7px 8px; border:1px solid #e7e5e4; border-radius:9px; background:#fff; }
        .party-result strong { display:block; margin:0; font-size:.88rem; }
        .quick-supplier-box { display:none; gap:8px; margin-top:8px; padding:9px; border:1px dashed #fed7aa; border-radius:9px; background:var(--purchase-soft); }
        .quick-supplier-box.is-visible { display:grid; }
        .quick-supplier-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,.8fr); gap:8px; }
        .picker-empty { padding:10px; border:1px dashed #fed7aa; border-radius:9px; color:#78716c; background:var(--purchase-soft); font-size:.84rem; }
        .store-pill { display:inline-flex; align-items:center; min-height:38px; width:100%; padding:0 10px; border-radius:9px; border:1px solid #fed7aa; background:var(--purchase-soft); color:var(--purchase-main); font-weight:700; }
        .receive-warning { display:none; margin-top:10px; padding:8px 10px; border:1px solid var(--purchase-border); border-radius:9px; background:var(--purchase-soft); color:var(--purchase-main); font-weight:700; font-size:.82rem; }
        .receive-warning.is-visible { display:block; }
        .summary-block { display:grid; gap:9px; margin-top:10px; }
        .summary-row { display:flex; justify-content:space-between; gap:10px; align-items:center; font-size:.9rem; }
        .summary-row.total { padding-top:10px; border-top:1px solid #fed7aa; font-size:1.08rem; font-weight:800; color:var(--purchase-main); }
        .status-pill { display:inline-flex; align-items:center; justify-content:center; width:100%; padding:9px 10px; border-radius:9px; background:#DBEAFE; color:#1D4ED8; font-weight:800; }
        .status-pill.credit { background:var(--purchase-soft); color:var(--purchase-main); }
        .incoming-table-wrap { margin-top:10px; max-height:285px; overflow:auto; border:1px solid #e7e5e4; border-radius:10px; background:#fff; }
        .incoming-table { width:100%; border-collapse:collapse; min-width:760px; }
        .incoming-table th, .incoming-table td { padding:7px 8px; border-bottom:1px solid #f1f5f9; text-align:left; vertical-align:top; }
        .incoming-table th { position:sticky; top:0; z-index:1; background:var(--purchase-soft); color:var(--purchase-main); font-size:.76rem; text-transform:uppercase; letter-spacing:.04em; }
        .incoming-table input { min-height:34px; padding:6px 8px; }
        .incoming-product { font-weight:800; color:var(--purchase-ink); }
        .incoming-unit { color:#6b7280; font-size:.78rem; margin-top:2px; }
        .line-total-field { background:#f8fafc; font-weight:800; }
        .cart-remove { border:1px solid #fed7aa; background:#fff7ed; color:var(--purchase-main); border-radius:8px; padding:6px 9px; cursor:pointer; font-weight:800; font-size:.76rem; }
        .empty-state { padding:18px 14px; border:1px dashed #fed7aa; border-radius:10px; text-align:center; color:#78716c; background:var(--purchase-soft); }
        .footer-actions { display:grid; gap:8px; margin-top:12px; }
        @media (max-width:1180px) { .receive-shell { grid-template-columns:1fr 1fr; grid-template-areas:"details summary" "finder finder" "items items"; } .summary-panel { position:static; } }
        @media (max-width:760px) { .receive-shell, .receive-grid, .find-row { grid-template-columns:1fr; grid-template-areas:none; } .details-panel, .finder-panel, .items-panel, .summary-panel { grid-area:auto; } .incoming-table { min-width:680px; } .picker-input, .receive-panel input, .receive-panel select { min-height:46px; font-size:16px; } }
    </style>

    <div class="page-head">
        <div>
            <h2>New Purchase</h2>
            <p>
                {{ $sourcePurchase
                    ? 'You are correcting '.$sourcePurchase->purchase_no.'. Review the copied items and save the corrected version. The original purchase will be voided automatically after the new one posts.'
                    : 'Search items, add them to the incoming list, choose the supplier, enter how much is being paid now, and save. Any unpaid balance becomes supplier credit automatically.' }}
            </p>
        </div>
        <div class="actions">
            <a href="{{ $returnTo ?: route('purchases.index') }}" class="button-link">Back to Purchases</a>
        </div>
    </div>

    <form method="post" action="{{ route('purchases.store') }}" id="purchase-form" class="receive-shell">
        @csrf
        <input type="hidden" name="return_to" value="{{ old('return_to', $returnTo) }}">
        <input type="hidden" name="corrected_from_purchase_id" value="{{ old('corrected_from_purchase_id', $sourcePurchase?->id) }}">

        <section class="receive-panel finder-panel">
            <h3>Find Items</h3>
            <p class="list-note">Search product, barcode, code, or part number, then receive it into stock.</p>
            <div class="find-row">
                <input type="text" id="purchase-search" class="picker-input" placeholder="Find Item">
                <div class="mini-stat"><div class="label">Lines</div><div class="value" id="purchase-lines-summary">0</div></div>
                <div class="mini-stat"><div class="label">Units</div><div class="value" id="purchase-units-summary">0</div></div>
                <div class="mini-stat"><div class="label">Subtotal</div><div class="value" id="purchase-inline-total">{{ $currency }} 0</div></div>
            </div>
            <div id="purchase-search-results" class="result-list"></div>
        </section>

        <section class="receive-panel items-panel">
            <div class="section-heading" style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
                <div>
                    <h3>Incoming Items</h3>
                    <p class="list-note">Confirm Qty Received and Buying Cost before recording stock.</p>
                </div>
                <button type="button" id="clear-purchase-cart" class="button-link">Clear Items</button>
            </div>
            <div id="purchase-cart-empty" class="empty-state" style="margin-top:10px;">No incoming items yet. Use Find Items above.</div>
            <div id="purchase-cart-list" class="incoming-table-wrap"></div>
            <div id="purchase-items-hidden"></div>
        </section>

        <section class="receive-panel details-panel">
                <h3>Supplier & Invoice</h3>
                <p class="list-note">Choose who delivered the stock and capture the supplier document.</p>
                <input type="hidden" name="store_id" value="{{ $currentStore?->id }}">
                <div class="receive-grid">
                    <label class="form-field">
                        <span>Purchase Date</span>
                        <input type="date" name="purchase_date" id="purchase-date" value="{{ old('purchase_date', now()->toDateString()) }}" required>
                    </label>
                    <div class="form-field">
                        <span>Store</span>
                        <div class="store-pill">{{ $currentStore?->name ?? config('business.name', 'Apples Of Gold') }}</div>
                    </div>
                    <div class="form-field span-full">
                        <span>Supplier</span>
                        <input type="hidden" name="supplier_id" id="purchase-supplier" value="{{ old('supplier_id', $prefillPurchase['supplier_id']) }}">
                        <input type="text" id="supplier-search" class="picker-input" placeholder="Search supplier">
                        <div id="supplier-results" class="party-results" style="margin-top:8px;"></div>
                        <button type="button" id="toggle-quick-supplier" class="button-link" style="margin-top:8px;">Quick Add Supplier</button>
                        <div id="quick-supplier-box" class="quick-supplier-box">
                            <div class="quick-supplier-grid">
                                <label class="form-field">
                                    <span>Supplier Name</span>
                                    <input type="text" id="quick-supplier-name" placeholder="Supplier name">
                                </label>
                                <label class="form-field">
                                    <span>Phone</span>
                                    <input type="text" id="quick-supplier-phone" placeholder="Optional phone">
                                </label>
                            </div>
                            <div class="actions">
                                <button type="button" id="save-quick-supplier">Save Supplier</button>
                                <button type="button" id="cancel-quick-supplier" class="button-link">Cancel</button>
                            </div>
                            <div id="quick-supplier-error" class="receive-warning"></div>
                        </div>
                        <div id="supplier-warning" class="receive-warning">Choose supplier before recording purchase.</div>
                    </div>
                    <label class="form-field">
                        <span>Paid Now</span>
                        <input type="number" step="0.01" min="0" name="amount_paid" id="purchase-amount-paid" value="{{ old('amount_paid', $prefillPurchase['amount_paid']) }}">
                    </label>
                    <label class="form-field" id="purchase-credit-period-wrap">
                        <span>Credit Period</span>
                        <input type="number" min="1" name="credit_period_days" id="purchase-credit-period" value="{{ old('credit_period_days', $prefillPurchase['credit_period_days']) }}">
                    </label>
                    <label class="form-field">
                        <span>Payment Mode</span>
                        <select name="payment_mode_id">
                            <option value="">Auto-select</option>
                            @foreach ($paymentModes as $mode)
                                <option value="{{ $mode->id }}" @selected((string) old('payment_mode_id') === (string) $mode->id)>{{ $mode->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-field">
                        <span>Supplier Invoice</span>
                        <input type="text" name="supplier_invoice_no" value="{{ old('supplier_invoice_no', $prefillPurchase['supplier_invoice_no']) }}">
                    </label>
                </div>
                <label class="form-field" style="margin-top:12px;">
                    <span>Remarks</span>
                    <textarea name="remarks" rows="2">{{ old('remarks', $prefillPurchase['remarks']) }}</textarea>
                </label>
            </section>

            <section class="receive-panel summary-panel">
                <h3>Purchase Summary</h3>
                <div class="summary-block">
                    <div id="purchase-kind-badge" class="status-pill">Cash Purchase</div>
                    <div class="summary-row"><span>Supplier</span><strong id="supplier-summary">Choose supplier</strong></div>
                    <div class="summary-row"><span>Items</span><strong id="purchase-items-summary">0</strong></div>
                    <div class="summary-row"><span>Subtotal</span><strong id="purchase-subtotal-summary">{{ $currency }} 0</strong></div>
                    <div class="summary-row"><span>Paid Now</span><strong id="purchase-paid-summary">{{ $currency }} 0</strong></div>
                    <div class="summary-row"><span>Supplier Balance</span><strong id="purchase-balance-summary">{{ $currency }} 0</strong></div>
                    <div class="summary-row"><span>Due Date</span><strong id="purchase-due-summary">Not needed</strong></div>
                    <div class="summary-row total"><span>Total</span><span id="purchase-total-summary">{{ $currency }} 0</span></div>
                </div>
                <div id="balance-warning" class="receive-warning">Supplier balance will remain open.</div>
                <div class="footer-actions">
                    <button type="button" id="purchase-fill-total" class="button-link">Pay Full Amount</button>
                    <button type="submit" class="receive-primary-button">Record Purchase</button>
                </div>
            </section>

    </form>

    <script>
        (() => {
            const currency = @json($currency);
            const units = @json($unitsPayload);
            let suppliers = @json($suppliersPayload);
            const quickSupplierUrl = @json(route('suppliers.quick-store'));
            const csrfToken = @json(csrf_token());
            const form = document.getElementById('purchase-form');
            if (!form) return;

            const searchInput = document.getElementById('purchase-search');
            const searchResults = document.getElementById('purchase-search-results');
            const cartList = document.getElementById('purchase-cart-list');
            const cartEmpty = document.getElementById('purchase-cart-empty');
            const hiddenInputs = document.getElementById('purchase-items-hidden');
            const supplierSearch = document.getElementById('supplier-search');
            const supplierResults = document.getElementById('supplier-results');
            const supplierInput = document.getElementById('purchase-supplier');
            const supplierWarning = document.getElementById('supplier-warning');
            const quickSupplierBox = document.getElementById('quick-supplier-box');
            const quickSupplierName = document.getElementById('quick-supplier-name');
            const quickSupplierPhone = document.getElementById('quick-supplier-phone');
            const quickSupplierError = document.getElementById('quick-supplier-error');
            const amountPaidInput = document.getElementById('purchase-amount-paid');
            const creditPeriodInput = document.getElementById('purchase-credit-period');
            const creditPeriodWrap = document.getElementById('purchase-credit-period-wrap');
            const purchaseDateInput = document.getElementById('purchase-date');
            const linesInlineSummary = document.getElementById('purchase-lines-summary');
            const unitsInlineSummary = document.getElementById('purchase-units-summary');
            const totalInlineSummary = document.getElementById('purchase-inline-total');

            const badge = document.getElementById('purchase-kind-badge');
            const supplierSummary = document.getElementById('supplier-summary');
            const itemsSummary = document.getElementById('purchase-items-summary');
            const subtotalSummary = document.getElementById('purchase-subtotal-summary');
            const paidSummary = document.getElementById('purchase-paid-summary');
            const balanceSummary = document.getElementById('purchase-balance-summary');
            const dueSummary = document.getElementById('purchase-due-summary');
            const totalSummary = document.getElementById('purchase-total-summary');
            const balanceWarning = document.getElementById('balance-warning');

            const initialItems = [];
            @foreach (old('items', $prefillPurchase['items']) as $oldItem)
                @php($oldUnit = $productUnits->firstWhere('id', (int) ($oldItem['product_unit_id'] ?? 0)))
                @if ($oldUnit)
                    initialItems.push({
                        id: {{ $oldUnit->id }},
                        label: @json(trim($oldUnit->product->name.' - '.$oldUnit->unit_name)),
                        product_name: @json($oldUnit->product->name),
                        unit_name: @json($oldUnit->unit_name),
                        price: {{ (float) ($oldItem['unit_cost'] ?? $oldUnit->cost_price) }},
                        quantity: {{ (int) round((float) ($oldItem['quantity'] ?? 1)) }},
                        barcode: @json($oldUnit->barcode),
                        code: @json($oldUnit->product->code),
                    });
                @endif
            @endforeach

            let cart = initialItems;

            function money(value) { return `${currency} ${Number(value || 0).toLocaleString()}`; }
            function normalizeQuantity(value) { return Math.max(Math.round(Number(value || 0)), 1); }
            function total() { return cart.reduce((sum, item) => sum + (Number(item.quantity || 0) * Number(item.price || 0)), 0); }
            function paidNow() { return Number(amountPaidInput.value || 0); }
            function balance() { return Math.max(total() - Math.min(paidNow(), total()), 0); }
            function duePreview() {
                const days = Number(creditPeriodInput.value || 0);
                if (!purchaseDateInput.value || !days || balance() <= 0) return 'Not needed';
                const date = new Date(`${purchaseDateInput.value}T00:00:00`);
                date.setDate(date.getDate() + days);
                return date.toLocaleDateString();
            }

            function syncCreditPeriodVisibility() {
                const shouldShow = balance() > 0;
                if (creditPeriodWrap) {
                    creditPeriodWrap.hidden = !shouldShow;
                }
                if (!shouldShow) {
                    creditPeriodInput.value = '';
                } else if (!String(creditPeriodInput.value || '').trim()) {
                    creditPeriodInput.value = '30';
                }
            }

            function renderSearchResults() {
                const needle = String(searchInput.value || '').trim().toLowerCase();
                const results = needle.length < 1 ? units.slice(0, 8) : units.filter((item) => item.search.includes(needle)).slice(0, 20);
                searchResults.innerHTML = results.length ? results.map((item) => `
                    <div class="result-card">
                        <div>
                            <strong>${item.label}</strong>
                            <div class="result-meta">${item.code ? `Code: ${item.code} / ` : ''}${item.barcode ? `Barcode: ${item.barcode} / ` : ''}${item.part_number ? `Part: ${item.part_number} / ` : ''}Buying: ${money(item.price)}</div>
                        </div>
                        <button type="button" class="receive-add-button" data-add-unit="${item.id}">Receive</button>
                    </div>
                `).join('') : `<div class="picker-empty">No products matched that search.</div>`;
            }

            function renderSuppliers() {
                const selectedId = String(supplierInput.value || '');
                const needle = String(supplierSearch.value || '').trim().toLowerCase();
                const results = needle.length < 1 ? suppliers.slice(0, 8) : suppliers.filter((item) => item.search.includes(needle)).slice(0, 12);
                supplierResults.innerHTML = results.length ? results.map((supplier) => `
                    <div class="party-result">
                        <div>
                            <strong>${supplier.name}</strong>
                            <div class="result-meta">${[supplier.phone, supplier.country, supplier.credit > 0 ? `${money(supplier.credit)} credit` : null].filter(Boolean).join(' / ')}</div>
                        </div>
                        <button type="button" class="button-link" data-pick-supplier="${supplier.id}">${selectedId === String(supplier.id) ? 'Selected' : 'Select'}</button>
                    </div>
                `).join('') : `<div class="picker-empty">No supplier matched that search.</div>`;
            }

            function selectSupplier(id) {
                supplierInput.value = String(id || '');
                const selected = suppliers.find((item) => String(item.id) === String(id));
                supplierSearch.value = selected ? selected.name : '';
                renderSuppliers();
                renderCart();
            }

            function setQuickSupplierOpen(open) {
                quickSupplierBox?.classList.toggle('is-visible', open);
                quickSupplierError?.classList.remove('is-visible');
                if (open) {
                    quickSupplierName.value = supplierSearch.value || '';
                    quickSupplierName.focus();
                }
            }

            async function saveQuickSupplier() {
                const name = String(quickSupplierName.value || '').trim();
                const phone = String(quickSupplierPhone.value || '').trim();
                if (!name) {
                    quickSupplierError.textContent = 'Enter supplier name.';
                    quickSupplierError.classList.add('is-visible');
                    quickSupplierName.focus();
                    return;
                }

                const response = await fetch(quickSupplierUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ name, phone }),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const message = data?.message || Object.values(data?.errors || {})?.[0]?.[0] || 'Supplier could not be saved.';
                    quickSupplierError.textContent = message;
                    quickSupplierError.classList.add('is-visible');
                    return;
                }

                suppliers.push(data.supplier);
                suppliers.sort((a, b) => String(a.name).localeCompare(String(b.name)));
                selectSupplier(data.supplier.id);
                setQuickSupplierOpen(false);
                quickSupplierName.value = '';
                quickSupplierPhone.value = '';
            }

            function syncPurchaseSummary() {
                hiddenInputs.innerHTML = cart.map((item, index) => `
                    <input type="hidden" name="items[${index}][product_unit_id]" value="${item.id}">
                    <input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">
                    <input type="hidden" name="items[${index}][unit_cost]" value="${item.price}">
                `).join('');

                const selectedSupplier = suppliers.find((item) => String(item.id) === String(supplierInput.value));
                supplierSummary.textContent = selectedSupplier ? selectedSupplier.name : 'Choose supplier';
                supplierWarning?.classList.toggle('is-visible', !selectedSupplier);
                itemsSummary.textContent = String(cart.length);
                linesInlineSummary.textContent = String(cart.length);
                unitsInlineSummary.textContent = String(cart.reduce((sum, item) => sum + normalizeQuantity(item.quantity), 0));
                totalInlineSummary.textContent = money(total());
                subtotalSummary.textContent = money(total());
                paidSummary.textContent = money(Math.min(paidNow(), total()));
                balanceSummary.textContent = money(balance());
                dueSummary.textContent = duePreview();
                totalSummary.textContent = money(total());
                syncCreditPeriodVisibility();

                if (balance() > 0) {
                    badge.textContent = 'Credit Purchase';
                    badge.classList.add('credit');
                    balanceWarning?.classList.add('is-visible');
                } else {
                    badge.textContent = 'Cash Purchase';
                    badge.classList.remove('credit');
                    balanceWarning?.classList.remove('is-visible');
                }
            }

            function updateCartRow(index) {
                const lineTotalField = cartList.querySelector(`[data-line-total="${index}"]`);
                if (lineTotalField) {
                    lineTotalField.value = money(Number(cart[index]?.quantity || 0) * Number(cart[index]?.price || 0));
                }
            }

            function renderCart() {
                cartEmpty.style.display = cart.length ? 'none' : 'block';
                cartList.style.display = cart.length ? 'block' : 'none';
                cartList.innerHTML = cart.length ? `
                    <table class="incoming-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Unit</th>
                                <th>Qty Received</th>
                                <th>Buying Cost</th>
                                <th>Total</th>
                                <th>Remove</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${cart.map((item, index) => `
                                <tr>
                                    <td>
                                        <div class="incoming-product">${item.product_name}</div>
                                        <div class="incoming-unit">${item.code ? `Code: ${item.code}` : 'No code'}${item.barcode ? ` / Barcode: ${item.barcode}` : ''}</div>
                                    </td>
                                    <td>${item.unit_name}</td>
                                    <td><input type="number" min="1" step="1" value="${item.quantity}" data-qty="${index}" inputmode="numeric"></td>
                                    <td><input type="number" min="0" step="0.01" value="${item.price}" data-price="${index}"></td>
                                    <td><input type="text" value="${money(Number(item.quantity) * Number(item.price))}" data-line-total="${index}" class="line-total-field" readonly></td>
                                    <td><button type="button" class="cart-remove" data-remove="${index}">Remove</button></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                ` : '';

                syncPurchaseSummary();
            }

            function addUnit(unitId) {
                const unit = units.find((item) => Number(item.id) === Number(unitId));
                if (!unit) return;
                const existing = cart.find((item) => Number(item.id) === Number(unit.id));
                if (existing) existing.quantity = normalizeQuantity(existing.quantity) + 1;
                else cart.push({ ...unit, quantity: 1 });
                renderCart();
                searchInput.value = '';
                renderSearchResults();
                searchInput.focus();
            }

            searchInput.addEventListener('input', renderSearchResults);
            searchInput.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                const first = searchResults.querySelector('[data-add-unit]');
                if (first) addUnit(first.dataset.addUnit);
            });
            searchResults.addEventListener('click', (event) => {
                const button = event.target.closest('[data-add-unit]');
                if (button) addUnit(button.dataset.addUnit);
            });

            supplierSearch.addEventListener('input', renderSuppliers);
            supplierResults.addEventListener('click', (event) => {
                const button = event.target.closest('[data-pick-supplier]');
                if (button) selectSupplier(button.dataset.pickSupplier);
            });
            document.getElementById('toggle-quick-supplier').addEventListener('click', () => setQuickSupplierOpen(!quickSupplierBox.classList.contains('is-visible')));
            document.getElementById('cancel-quick-supplier').addEventListener('click', () => setQuickSupplierOpen(false));
            document.getElementById('save-quick-supplier').addEventListener('click', saveQuickSupplier);

            cartList.addEventListener('click', (event) => {
                const remove = event.target.closest('[data-remove]');
                if (remove) {
                    cart.splice(Number(remove.dataset.remove), 1);
                    renderCart();
                    return;
                }
                const plus = event.target.closest('[data-plus]');
                if (plus) {
                    const index = Number(plus.dataset.plus);
                    cart[index].quantity = normalizeQuantity(cart[index].quantity) + 1;
                    renderCart();
                    return;
                }
                const minus = event.target.closest('[data-minus]');
                if (minus) {
                    const index = Number(minus.dataset.minus);
                    cart[index].quantity = Math.max(normalizeQuantity(cart[index].quantity) - 1, 1);
                    renderCart();
                }
            });

            cartList.addEventListener('input', (event) => {
                const qty = event.target.closest('[data-qty]');
                if (qty) {
                    const index = Number(qty.dataset.qty);
                    cart[index].quantity = normalizeQuantity(qty.value);
                    updateCartRow(index);
                    syncPurchaseSummary();
                    return;
                }
                const price = event.target.closest('[data-price]');
                if (price) {
                    const index = Number(price.dataset.price);
                    cart[index].price = Math.max(Number(price.value || 0), 0);
                    updateCartRow(index);
                    syncPurchaseSummary();
                }
            });

            cartList.addEventListener('change', (event) => {
                const qty = event.target.closest('[data-qty]');
                if (qty) {
                    const index = Number(qty.dataset.qty);
                    qty.value = String(cart[index].quantity);
                    updateCartRow(index);
                    syncPurchaseSummary();
                    return;
                }

                const price = event.target.closest('[data-price]');
                if (price) {
                    const index = Number(price.dataset.price);
                    price.value = String(Math.max(Number(cart[index].price || 0), 0));
                    updateCartRow(index);
                    syncPurchaseSummary();
                }
            });

            amountPaidInput.addEventListener('input', renderCart);
            creditPeriodInput.addEventListener('input', renderCart);
            purchaseDateInput.addEventListener('input', renderCart);

            document.getElementById('purchase-fill-total').addEventListener('click', () => {
                amountPaidInput.value = total().toFixed(2);
                renderCart();
            });

            document.getElementById('clear-purchase-cart').addEventListener('click', () => {
                cart = [];
                renderCart();
            });

            form.addEventListener('submit', (event) => {
                if (!cart.length) {
                    event.preventDefault();
                    alert('Add at least one incoming item before recording the purchase.');
                    searchInput.focus();
                    return;
                }
                if (!supplierInput.value) {
                    event.preventDefault();
                    alert('Choose supplier before recording purchase.');
                    supplierSearch.focus();
                }
            });

            renderSearchResults();
            renderSuppliers();
            renderCart();
            if (supplierInput.value) selectSupplier(supplierInput.value);
            if (window.innerWidth > 760) supplierSearch.focus();
        })();
    </script>
@endsection
