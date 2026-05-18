@extends('layouts.app', ['title' => 'New Purchase'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($suppliersPayload = $suppliers->map(fn ($supplier) => [
        'id' => $supplier->id,
        'name' => $supplier->name,
        'country' => $supplier->country,
        'credit' => (float) ($supplier->outstanding_credit ?? 0),
        'search' => strtolower(trim(implode(' ', array_filter([$supplier->name, $supplier->country])))),
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
        .workflow-shell { display:grid; grid-template-columns:minmax(0,1.35fr) minmax(320px,.9fr); gap:16px; align-items:start; }
        .workflow-stack { display:grid; gap:14px; }
        .picker-input { width:100%; min-height:48px; padding:12px 14px; border-radius:14px; border:1px solid var(--line); background:#fff; color:var(--ink); font-size:.96rem; }
        .result-list { display:grid; gap:8px; margin-top:12px; max-height:320px; overflow-y:auto; }
        .result-card { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:10px; align-items:center; padding:12px 14px; border-radius:14px; border:1px solid var(--line); background:var(--panel-soft); }
        .result-card strong { display:block; margin-bottom:4px; }
        .result-meta { color:var(--muted); font-size:.9rem; line-height:1.4; }
        .sale-add-button { border:0; border-radius:10px; padding:7px 11px; background:var(--brand); color:#fff; font-weight:700; cursor:pointer; font-size:.8rem; }
        .cart-remove { border:1px solid #ecd4c0; background:#fff7ef; color:#9a5821; border-radius:10px; padding:7px 10px; cursor:pointer; font-weight:700; font-size:.78rem; }
        .cart-list { display:grid; gap:10px; }
        .cart-item { border:1px solid var(--line); border-radius:16px; background:#fff; padding:12px 14px; }
        .cart-item-head { display:flex; justify-content:space-between; gap:10px; align-items:start; margin-bottom:10px; }
        .cart-grid, .compact-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .cart-grid.three { grid-template-columns:repeat(3,minmax(0,1fr)); }
        .summary-card { position:sticky; top:18px; }
        .summary-inline-stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; margin-top:10px; }
        .summary-inline-stat { border:1px solid var(--line); border-radius:12px; padding:7px 9px; background:var(--panel-soft); }
        .summary-inline-stat .label { color:var(--muted); font-size:.73rem; text-transform:uppercase; letter-spacing:.05em; }
        .summary-inline-stat .value { margin-top:4px; font-size:1rem; font-weight:700; }
        .party-results { display:grid; gap:6px; max-height:170px; overflow-y:auto; }
        .party-result { display:flex; justify-content:space-between; gap:10px; align-items:center; padding:9px 11px; border:1px solid var(--line); border-radius:12px; background:#fff; }
        .party-result strong { display:block; margin:0; font-size:.94rem; }
        .picker-empty { padding:12px; border:1px dashed var(--line); border-radius:12px; color:var(--muted); background:var(--panel-soft); font-size:.9rem; }
        .store-pill { display:inline-flex; align-items:center; min-height:46px; padding:0 12px; border-radius:13px; border:1px solid var(--line); background:var(--panel-soft); color:var(--ink); font-weight:600; }
        .summary-block { display:grid; gap:10px; }
        .summary-row { display:flex; justify-content:space-between; gap:10px; align-items:center; font-size:.92rem; }
        .summary-row.total { padding-top:12px; border-top:1px solid var(--line); font-size:1.12rem; font-weight:700; }
        .status-pill { display:inline-flex; align-items:center; justify-content:center; width:100%; padding:10px 12px; border-radius:13px; background:var(--brand-soft); color:var(--brand); font-weight:700; }
        .status-pill.credit { background:var(--accent-soft); color:var(--accent-ink); }
        .qty-box { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:8px; align-items:stretch; }
        .qty-box button { min-width:40px; height:40px; padding:0; border-radius:12px; }
        .qty-box input { text-align:left; font-weight:700; }
        .qty-step-stack { display:grid; gap:6px; }
        .qty-step-stack button { min-width:46px; }
        .line-total-field { background:var(--panel-soft); font-weight:700; }
        .empty-state { padding:22px 16px; border:1px dashed var(--line-strong); border-radius:16px; text-align:center; color:var(--muted); background:#fbfcfb; }
        .footer-actions { display:flex; gap:10px; flex-wrap:wrap; }
        .footer-actions .button-link, .footer-actions button { flex:1 1 180px; justify-content:center; }
        @media (max-width:980px) { .workflow-shell { grid-template-columns:1fr; } .summary-card { position:static; } }
        @media (max-width:760px) { .cart-grid, .cart-grid.three, .compact-grid, .summary-inline-stats { grid-template-columns:1fr; } .result-card { grid-template-columns:1fr; } .picker-input, .cart-item input, .cart-item select, .summary-block input, .summary-block select { min-height:46px; font-size:16px; } .footer-actions .button-link, .footer-actions button { flex-basis:100%; } }
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

    <form method="post" action="{{ route('purchases.store') }}" id="purchase-form" class="workflow-shell">
        @csrf
        <input type="hidden" name="return_to" value="{{ old('return_to', $returnTo) }}">
        <input type="hidden" name="corrected_from_purchase_id" value="{{ old('corrected_from_purchase_id', $sourcePurchase?->id) }}">

        <div class="workflow-stack">
            <section class="panel">
                <h3>1. Search And Add Items</h3>
                <p class="list-note">Type a product name, code, barcode, or part number. Add it once, then adjust quantity and buying cost in the list.</p>
                <input type="text" id="purchase-search" class="picker-input" placeholder="Search product, barcode, code, or part number">
                <div class="summary-inline-stats">
                    <div class="summary-inline-stat">
                        <div class="label">Lines</div>
                        <div class="value" id="purchase-lines-summary">0</div>
                    </div>
                    <div class="summary-inline-stat">
                        <div class="label">Units</div>
                        <div class="value" id="purchase-units-summary">0</div>
                    </div>
                    <div class="summary-inline-stat">
                        <div class="label">Total</div>
                        <div class="value" id="purchase-inline-total">{{ $currency }} 0</div>
                    </div>
                </div>
                <div id="purchase-search-results" class="result-list"></div>
            </section>

            <section class="panel">
                <div class="page-head" style="margin-bottom: 12px;">
                    <div>
                        <h3 style="margin: 0;">2. Incoming Items</h3>
                        <p style="margin: 6px 0 0;">Edit quantity and unit cost directly. Totals update as you work.</p>
                    </div>
                    <div class="actions">
                        <button type="button" id="clear-purchase-cart" class="button-link">Clear Purchase</button>
                    </div>
                </div>
                <div id="purchase-cart-empty" class="empty-state">No items added yet. Start with the search box above.</div>
                <div id="purchase-cart-list" class="cart-list"></div>
                <div id="purchase-items-hidden"></div>
            </section>
        </div>

        <div class="workflow-stack summary-card">
            <section class="panel">
                <h3>3. Purchase Details</h3>
                <input type="hidden" name="store_id" value="{{ $currentStore?->id }}">
                <div class="compact-grid" style="margin-top: 14px;">
                    <label class="form-field">
                        <span>Purchase Date</span>
                        <input type="date" name="purchase_date" id="purchase-date" value="{{ old('purchase_date', now()->toDateString()) }}" required>
                    </label>
                    <div class="form-field">
                        <span>Store</span>
                        <div class="store-pill">{{ $currentStore?->name ?? config('business.name', 'Apples Of Gold') }}</div>
                    </div>
                    <div class="form-field" style="grid-column: 1 / -1;">
                        <span>Supplier</span>
                        <input type="hidden" name="supplier_id" id="purchase-supplier" value="{{ old('supplier_id', $prefillPurchase['supplier_id']) }}">
                        <input type="text" id="supplier-search" class="picker-input" placeholder="Search supplier by name or country">
                        <div id="supplier-results" class="party-results" style="margin-top:10px;"></div>
                    </div>
                    <label class="form-field">
                        <span>Amount Paid Now</span>
                        <input type="number" step="0.01" min="0" name="amount_paid" id="purchase-amount-paid" value="{{ old('amount_paid', $prefillPurchase['amount_paid']) }}">
                    </label>
                    <label class="form-field" id="purchase-credit-period-wrap">
                        <span>Credit Period (days)</span>
                        <input type="number" min="1" name="credit_period_days" id="purchase-credit-period" value="{{ old('credit_period_days', $prefillPurchase['credit_period_days']) }}">
                    </label>
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
                        <span>Supplier Invoice No</span>
                        <input type="text" name="supplier_invoice_no" value="{{ old('supplier_invoice_no', $prefillPurchase['supplier_invoice_no']) }}">
                    </label>
                </div>
                <label class="form-field" style="margin-top:12px;">
                    <span>Remarks</span>
                    <textarea name="remarks" rows="3">{{ old('remarks', $prefillPurchase['remarks']) }}</textarea>
                </label>
            </section>

            <section class="panel">
                <h3>4. Payment Summary</h3>
                <div class="summary-block" style="margin-top:14px;">
                    <div id="purchase-kind-badge" class="status-pill">Cash Purchase</div>
                    <div class="summary-row"><span>Supplier</span><strong id="supplier-summary">Choose supplier</strong></div>
                    <div class="summary-row"><span>Items</span><strong id="purchase-items-summary">0</strong></div>
                    <div class="summary-row"><span>Subtotal</span><strong id="purchase-subtotal-summary">{{ $currency }} 0</strong></div>
                    <div class="summary-row"><span>Paid Now</span><strong id="purchase-paid-summary">{{ $currency }} 0</strong></div>
                    <div class="summary-row"><span>Balance</span><strong id="purchase-balance-summary">{{ $currency }} 0</strong></div>
                    <div class="summary-row"><span>Due Date</span><strong id="purchase-due-summary">Not needed</strong></div>
                    <div class="summary-row total"><span>Total Purchase</span><span id="purchase-total-summary">{{ $currency }} 0</span></div>
                </div>
            </section>

            <section class="panel">
                <h3>5. Save</h3>
                <p class="list-note">If today’s payment is less than the total, the remaining supplier balance will stay open for later payment.</p>
                <div class="footer-actions" style="margin-top:14px;">
                    <button type="button" id="purchase-fill-total" class="button-link">Use Full Payment</button>
                    <button type="submit">Record Purchase</button>
                </div>
            </section>
        </div>
    </form>

    <script>
        (() => {
            const currency = @json($currency);
            const units = @json($unitsPayload);
            const suppliers = @json($suppliersPayload);
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
                            <div class="result-meta">${item.code ? `Code: ${item.code} / ` : ''}${item.barcode ? `Barcode: ${item.barcode} / ` : ''}${item.part_number ? `Part: ${item.part_number} / ` : ''}Cost: ${money(item.price)}</div>
                        </div>
                        <button type="button" class="sale-add-button" data-add-unit="${item.id}">Add</button>
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
                            <div class="result-meta">${[supplier.country, supplier.credit > 0 ? `${money(supplier.credit)} credit` : null].filter(Boolean).join(' / ')}</div>
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

            function syncPurchaseSummary() {
                hiddenInputs.innerHTML = cart.map((item, index) => `
                    <input type="hidden" name="items[${index}][product_unit_id]" value="${item.id}">
                    <input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">
                    <input type="hidden" name="items[${index}][unit_cost]" value="${item.price}">
                `).join('');

                const selectedSupplier = suppliers.find((item) => String(item.id) === String(supplierInput.value));
                supplierSummary.textContent = selectedSupplier ? selectedSupplier.name : 'Choose supplier';
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
                } else {
                    badge.textContent = 'Cash Purchase';
                    badge.classList.remove('credit');
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
                cartList.innerHTML = cart.map((item, index) => `
                    <div class="cart-item">
                        <div class="cart-item-head">
                            <div>
                                <strong>${item.label}</strong>
                                <div class="result-meta">${item.code ? `Code: ${item.code}` : 'No code'}${item.barcode ? ` / Barcode: ${item.barcode}` : ''}</div>
                            </div>
                            <button type="button" class="cart-remove" data-remove="${index}">Remove</button>
                        </div>
                        <div class="cart-grid three">
                            <label class="form-field">
                                <span>Quantity</span>
                                <div class="qty-box">
                                    <input type="number" min="1" step="1" value="${item.quantity}" data-qty="${index}" inputmode="numeric" placeholder="Type quantity">
                                    <div class="qty-step-stack">
                                        <button type="button" data-plus="${index}" aria-label="Increase quantity">+</button>
                                        <button type="button" data-minus="${index}" aria-label="Decrease quantity">-</button>
                                    </div>
                                </div>
                            </label>
                            <label class="form-field">
                                <span>Unit Cost</span>
                                <input type="number" min="0" step="0.01" value="${item.price}" data-price="${index}">
                            </label>
                            <div class="form-field">
                                <span>Line Total</span>
                                <input type="text" value="${money(Number(item.quantity) * Number(item.price))}" data-line-total="${index}" class="line-total-field" readonly>
                            </div>
                        </div>
                    </div>
                `).join('');

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
                    alert('Add at least one item before saving the purchase.');
                    searchInput.focus();
                    return;
                }
                if (!supplierInput.value) {
                    event.preventDefault();
                    alert('Choose a supplier before saving the purchase.');
                    supplierSearch.focus();
                }
            });

            renderSearchResults();
            renderSuppliers();
            renderCart();
            if (supplierInput.value) selectSupplier(supplierInput.value);
            if (window.innerWidth > 760) searchInput.focus();
        })();
    </script>
@endsection
