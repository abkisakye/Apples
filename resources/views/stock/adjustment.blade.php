@extends('layouts.app', ['title' => 'Stock Adjustment'])

@section('content')
    @php($initialAdjustmentItems = collect(old('items', $prefillAdjustment['items'] ?? [])))
    @php($openingStockMode = $openingStockMode ?? false)
    @php($unitsPayload = $productUnits->map(fn ($unit) => [
        'id' => $unit->id,
        'label' => trim($unit->product->name.' - '.$unit->unit_name),
        'product_name' => $unit->product->name,
        'unit_name' => $unit->unit_name,
        'barcode' => $unit->barcode,
        'part_number' => $unit->part_number,
        'allow_fractional_quantity' => (bool) ($unit->allow_fractional_quantity ?? false),
        'quantity_precision' => (int) ($unit->quantity_precision ?? 0),
        'search' => strtolower(trim(implode(' ', array_filter([$unit->product->name, $unit->unit_name, $unit->barcode, $unit->part_number])))),
    ]))
    <style>
        .workflow-shell { display:grid; grid-template-columns:minmax(0,1.35fr) minmax(320px,.9fr); gap:16px; align-items:start; }
        .workflow-stack { display:grid; gap:14px; }
        .summary-card { position:sticky; top:18px; }
        .picker-input { width:100%; min-height:48px; padding:12px 14px; border-radius:14px; border:1px solid var(--line); background:#fff; color:var(--ink); }
        .result-list { display:grid; gap:8px; margin-top:12px; max-height:320px; overflow-y:auto; }
        .result-card { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:10px; align-items:center; padding:12px 14px; border-radius:14px; border:1px solid var(--line); background:var(--panel-soft); }
        .result-card strong { display:block; margin-bottom:4px; }
        .result-meta { color:var(--muted); font-size:.9rem; line-height:1.4; }
        .sale-add-button { border:0; border-radius:10px; padding:7px 11px; background:var(--brand); color:#fff; font-weight:700; cursor:pointer; font-size:.8rem; }
        .cart-list { display:grid; gap:10px; }
        .cart-item { border:1px solid var(--line); border-radius:16px; background:#fff; padding:12px 14px; }
        .cart-item-head { display:flex; justify-content:space-between; gap:10px; align-items:start; margin-bottom:10px; }
        .compact-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .store-pill { display:inline-flex; align-items:center; min-height:46px; padding:0 12px; border-radius:13px; border:1px solid var(--line); background:var(--panel-soft); font-weight:600; }
        .qty-box { display:flex; align-items:center; gap:8px; }
        .qty-box button { min-width:40px; height:40px; padding:0; border-radius:12px; }
        .cart-remove { border:1px solid #ecd4c0; background:#fff7ef; color:#9a5821; border-radius:10px; padding:7px 10px; cursor:pointer; font-weight:700; font-size:.78rem; }
        .status-pill { display:inline-flex; align-items:center; justify-content:center; width:100%; padding:10px 12px; border-radius:13px; background:var(--brand-soft); color:var(--brand); font-weight:700; }
        .status-pill.decrease { background:var(--accent-soft); color:var(--accent-ink); }
        .summary-block { display:grid; gap:10px; }
        .summary-row { display:flex; justify-content:space-between; gap:10px; align-items:center; font-size:.94rem; }
        .empty-state { padding:22px 16px; border:1px dashed var(--line-strong); border-radius:16px; text-align:center; color:var(--muted); background:#fbfcfb; }
        @media (max-width:980px) { .workflow-shell { grid-template-columns:1fr; } .summary-card { position:static; } }
        @media (max-width:760px) { .compact-grid { grid-template-columns:1fr; } .result-card { grid-template-columns:1fr; } .picker-input { font-size:16px; } }
    </style>
    <div class="page-head">
        <div>
            <h2>{{ $openingStockMode ? 'Opening Stock Entry' : 'Stock Adjustment' }}</h2>
            @if ($openingStockMode)
                <p>Use this when stock already existed in the shop before the system started. It increases stock without creating supplier debt.</p>
            @else
                <p>Search items, add them to the adjustment list, choose whether system stock is increasing or decreasing, and post the correction for your current store.</p>
                <p class="list-note">For existing shop stock that was already paid before system start, use Opening Stock Entry. This adds stock without creating supplier debt.</p>
            @endif
        </div>
        <div class="actions">
            <a href="{{ $returnTo ?: route('stock.balances') }}" class="button-link">Back to Stock</a>
        </div>
    </div>

    <form method="post" action="{{ $openingStockMode ? route('stock.opening-stock.store') : route('stock.adjustments.store') }}" id="adjustment-form" class="workflow-shell">
        @csrf
        <input type="hidden" name="store_id" value="{{ $currentStore?->id }}">
        <input type="hidden" name="return_to" value="{{ old('return_to', $returnTo) }}">
        @if ($openingStockMode)
            <input type="hidden" name="adjustment_type" value="increase">
        @endif
        <div class="workflow-stack summary-card">
            <section class="panel">
                <h3>1. Search And Add Items</h3>
                <p class="list-note">{{ $openingStockMode ? 'Search the product and unit/pack that already exists in the shop, then enter the physical quantity found.' : 'Search the selling unit, add it once, then enter the units that need correction in the system.' }}</p>
                <input type="text" id="adjustment-search" class="picker-input" placeholder="Search product, barcode, or part number">
                <div id="adjustment-search-results" class="result-list"></div>
            </section>
            <section class="panel">
                <div class="page-head" style="margin-bottom:12px;">
                    <div><h3 style="margin:0;">2. {{ $openingStockMode ? 'Existing Stock Items' : 'Adjustment Items' }}</h3><p style="margin:6px 0 0;">{{ $openingStockMode ? 'Add each product/unit and enter the quantity already in the shop.' : 'Add the stock units and set the unit difference to correct.' }}</p></div>
                    <div class="actions"><button type="button" id="clear-adjustment-cart" class="button-link">{{ $openingStockMode ? 'Clear Entry' : 'Clear Adjustment' }}</button></div>
                </div>
                <div id="adjustment-cart-empty" class="empty-state">No items added yet.</div>
                <div id="adjustment-cart-list" class="cart-list"></div>
                <div id="adjustment-items-hidden"></div>
            </section>
        </div>
        <div class="workflow-stack">
            <section class="panel">
                <h3>3. {{ $openingStockMode ? 'Opening Stock Details' : 'Adjustment Details' }}</h3>
                <div class="compact-grid" style="margin-top:14px;">
                    <label class="form-field"><span>{{ $openingStockMode ? 'Entry Date' : 'Adjustment Date' }}</span><input type="date" name="adjustment_date" value="{{ old('adjustment_date', now()->toDateString()) }}" required></label>
                    <div class="form-field"><span>Shop</span><div class="store-pill">{{ $currentStore?->name ?? config('business.name', 'Apples Of Gold') }}</div></div>
                    @if ($openingStockMode)
                        <label class="form-field" style="grid-column:1 / -1;"><span>Optional Reference</span><input type="text" name="opening_reference" value="{{ old('opening_reference', $openingStockReference ?? '') }}" placeholder="Example: Opening stock sheet, shelf count, notebook ref"></label>
                    @else
                        <label class="form-field" style="grid-column:1 / -1;">
                            <span>Adjustment Type</span>
                            <select name="adjustment_type" id="adjustment-type" required>
                                <option value="increase" @selected(old('adjustment_type', $prefillAdjustment['adjustment_type'] ?? 'increase') === 'increase')>Increase stock</option>
                                <option value="decrease" @selected(old('adjustment_type', $prefillAdjustment['adjustment_type'] ?? 'increase') === 'decrease')>Decrease stock</option>
                            </select>
                        </label>
                    @endif
                </div>
                <label class="form-field" style="margin-top:12px;"><span>Reason / Note</span><textarea name="remarks" rows="3">{{ old('remarks', $openingStockMode ? 'Existing stock before system start' : '') }}</textarea></label>
            </section>
            <section class="panel">
                <h3>4. Save</h3>
                <div id="adjustment-badge" class="status-pill {{ $openingStockMode ? '' : 'decrease' }}" style="margin-top:14px;">{{ $openingStockMode ? 'Opening Stock In' : 'Decrease Stock' }}</div>
                <div class="summary-block" style="margin-top:14px;">
                    <div class="summary-row"><span>Lines</span><strong id="adjustment-lines-summary">0</strong></div>
                    <div class="summary-row"><span>Total Quantity</span><strong id="adjustment-qty-summary">0</strong></div>
                </div>
                <p class="list-note">{{ $openingStockMode ? 'This will create stock-in records only. It will not create a supplier purchase, supplier payment, or supplier balance.' : 'Use this for stock take differences, damaged goods, corrections, or items found during recount. When a staff member does a physical stock count, compare the physical count to the system count and post only the difference here.' }}</p>
                <button type="submit" style="margin-top:14px; width:100%;">{{ $openingStockMode ? 'Post Opening Stock' : 'Record Adjustment' }}</button>
            </section>
        </div>
    </form>

    <script>
        (() => {
            const units = @json($unitsPayload);
            const openingStockMode = @json($openingStockMode);
            const form = document.getElementById('adjustment-form');
            if (!form) return;
            const searchInput = document.getElementById('adjustment-search');
            const results = document.getElementById('adjustment-search-results');
            const cartList = document.getElementById('adjustment-cart-list');
            const cartEmpty = document.getElementById('adjustment-cart-empty');
            const hidden = document.getElementById('adjustment-items-hidden');
            const typeSelect = document.getElementById('adjustment-type');
            const badge = document.getElementById('adjustment-badge');
            const linesSummary = document.getElementById('adjustment-lines-summary');
            const qtySummary = document.getElementById('adjustment-qty-summary');
            const initialItems = [];
            @foreach ($initialAdjustmentItems as $oldItem)
                @php($oldUnit = $productUnits->firstWhere('id', (int) ($oldItem['product_unit_id'] ?? 0)))
                @if ($oldUnit)
                    initialItems.push({
                        id: {{ $oldUnit->id }},
                        label: @json(trim($oldUnit->product->name.' - '.$oldUnit->unit_name)),
                        product_name: @json($oldUnit->product->name),
                        unit_name: @json($oldUnit->unit_name),
                        barcode: @json($oldUnit->barcode),
                        part_number: @json($oldUnit->part_number),
                        quantity: {{ (int) round((float) ($oldItem['quantity'] ?? 1)) }},
                    });
                @endif
            @endforeach
            let cart = initialItems;
            const normalizeQuantity = (value) => {
                const numeric = Number(value || 0);
                if (openingStockMode) {
                    return Math.max(Math.round(numeric * 1000) / 1000, 0.001);
                }

                return Math.max(Math.round(numeric), 1);
            };

            function renderResults() {
                const needle = String(searchInput.value || '').trim().toLowerCase();
                const rows = needle.length < 1 ? units.slice(0, 8) : units.filter((item) => item.search.includes(needle)).slice(0, 20);
                results.innerHTML = rows.length ? rows.map((item) => `<div class="result-card"><div><strong>${item.label}</strong><div class="result-meta">Ready for correction entry</div></div><button type="button" class="sale-add-button" data-add="${item.id}">Add</button></div>`).join('') : `<div class="empty-state">No items matched that search.</div>`;
            }

            function renderCart() {
                cartEmpty.style.display = cart.length ? 'none' : 'block';
                cartList.innerHTML = cart.map((item, index) => `<div class="cart-item"><div class="cart-item-head"><strong>${item.label}</strong><button type="button" class="cart-remove" data-remove="${index}">Remove</button></div><label class="form-field"><span>Quantity</span><div class="qty-box"><button type="button" data-minus="${index}">-</button><input type="number" min="${openingStockMode ? '0.001' : '1'}" step="${openingStockMode ? '0.001' : '1'}" value="${item.quantity}" data-qty="${index}"><button type="button" data-plus="${index}">+</button></div></label></div>`).join('');
                hidden.innerHTML = cart.map((item, index) => `<input type="hidden" name="items[${index}][product_unit_id]" value="${item.id}"><input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">`).join('');
                linesSummary.textContent = String(cart.length);
                qtySummary.textContent = String(cart.reduce((sum, item) => sum + normalizeQuantity(item.quantity), 0));
            }

            function updateBadge() {
                if (!typeSelect) {
                    badge.textContent = 'Opening Stock In';
                    badge.classList.remove('decrease');
                    return;
                }

                if (typeSelect.value === 'increase') {
                    badge.textContent = 'Increase Stock';
                    badge.classList.remove('decrease');
                } else {
                    badge.textContent = 'Decrease Stock';
                    badge.classList.add('decrease');
                }
            }

            function addItem(id) {
                const unit = units.find((item) => Number(item.id) === Number(id));
                if (!unit) return;
                const existing = cart.find((item) => Number(item.id) === Number(unit.id));
                if (existing) existing.quantity = normalizeQuantity(existing.quantity) + 1;
                else cart.push({ ...unit, quantity: 1 });
                renderCart();
                searchInput.value = '';
                renderResults();
                searchInput.focus();
            }

            searchInput.addEventListener('input', renderResults);
            results.addEventListener('click', (event) => {
                const button = event.target.closest('[data-add]');
                if (button) addItem(button.dataset.add);
            });
            cartList.addEventListener('click', (event) => {
                const remove = event.target.closest('[data-remove]');
                if (remove) { cart.splice(Number(remove.dataset.remove), 1); renderCart(); return; }
                const plus = event.target.closest('[data-plus]');
                if (plus) { const i = Number(plus.dataset.plus); cart[i].quantity = normalizeQuantity(cart[i].quantity) + 1; renderCart(); return; }
                const minus = event.target.closest('[data-minus]');
                if (minus) { const i = Number(minus.dataset.minus); cart[i].quantity = Math.max(normalizeQuantity(cart[i].quantity) - 1, 1); renderCart(); }
            });
            cartList.addEventListener('input', (event) => {
                const qty = event.target.closest('[data-qty]');
                if (qty) { const i = Number(qty.dataset.qty); cart[i].quantity = normalizeQuantity(qty.value); renderCart(); }
            });
            document.getElementById('clear-adjustment-cart').addEventListener('click', () => { cart = []; renderCart(); });
            typeSelect?.addEventListener('change', updateBadge);
            form.addEventListener('submit', (event) => {
                if (!cart.length) { event.preventDefault(); alert(openingStockMode ? 'Add at least one existing stock item before posting.' : 'Add at least one item before posting the adjustment.'); searchInput.focus(); }
            });
            renderResults();
            renderCart();
            updateBadge();
        })();
    </script>
@endsection
