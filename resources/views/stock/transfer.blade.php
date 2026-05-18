@extends('layouts.app', ['title' => 'Stock Transfer'])

@section('content')
    @php($unitsPayload = $productUnits->map(fn ($unit) => [
        'id' => $unit->id,
        'label' => trim($unit->product->name.' - '.$unit->unit_name),
        'product_name' => $unit->product->name,
        'unit_name' => $unit->unit_name,
        'barcode' => $unit->barcode,
        'part_number' => $unit->part_number,
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
        .summary-block { display:grid; gap:10px; }
        .summary-row { display:flex; justify-content:space-between; gap:10px; align-items:center; font-size:.94rem; }
        .empty-state { padding:22px 16px; border:1px dashed var(--line-strong); border-radius:16px; text-align:center; color:var(--muted); background:#fbfcfb; }
        @media (max-width:980px) { .workflow-shell { grid-template-columns:1fr; } .summary-card { position:static; } }
        @media (max-width:760px) { .compact-grid { grid-template-columns:1fr; } .result-card { grid-template-columns:1fr; } .picker-input { font-size:16px; } }
    </style>
    <div class="page-head">
        <div>
            <h2>Stock Transfer</h2>
            <p>Search items, add them to the transfer list, choose the destination store, and post the movement from your current store.</p>
        </div>
        <div class="actions">
            <a href="{{ route('stock.balances') }}" class="button-link">Back to Stock</a>
        </div>
    </div>

    <form method="post" action="{{ route('stock.transfers.store') }}" id="transfer-form" class="workflow-shell">
        @csrf
        <input type="hidden" name="from_store_id" value="{{ $currentStore?->id }}">
        <div class="workflow-stack summary-card">
            <section class="panel">
                <h3>1. Search And Add Items</h3>
                <p class="list-note">Search the selling unit, add it once, then adjust the quantity to move between stores.</p>
                <input type="text" id="transfer-search" class="picker-input" placeholder="Search product, barcode, or part number">
                <div id="transfer-search-results" class="result-list"></div>
            </section>
            <section class="panel">
                <div class="page-head" style="margin-bottom:12px;">
                    <div><h3 style="margin:0;">2. Transfer Items</h3><p style="margin:6px 0 0;">Add the stock units and set the quantities to move out.</p></div>
                    <div class="actions"><button type="button" id="clear-transfer-cart" class="button-link">Clear Transfer</button></div>
                </div>
                <div id="transfer-cart-empty" class="empty-state">No items added yet.</div>
                <div id="transfer-cart-list" class="cart-list"></div>
                <div id="transfer-items-hidden"></div>
            </section>
        </div>
        <div class="workflow-stack">
            <section class="panel">
                <h3>3. Transfer Details</h3>
                <div class="compact-grid" style="margin-top:14px;">
                    <label class="form-field"><span>Transfer Date</span><input type="date" name="transfer_date" value="{{ old('transfer_date', now()->toDateString()) }}" required></label>
                    <div class="form-field"><span>From Shop</span><div class="store-pill">{{ $currentStore?->name ?? config('business.name', 'Apples Of Gold') }}</div></div>
                    <label class="form-field" style="grid-column:1 / -1;">
                        <span>To Store</span>
                        <select name="to_store_id" required>
                            <option value="">Select destination store</option>
                            @foreach ($stores as $store)
                                @continue($currentStore && $store->id === $currentStore->id)
                                <option value="{{ $store->id }}" @selected((string) old('to_store_id') === (string) $store->id)>{{ $store->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label class="form-field" style="margin-top:12px;"><span>Remarks</span><textarea name="remarks" rows="3">{{ old('remarks') }}</textarea></label>
            </section>
            <section class="panel">
                <h3>4. Save</h3>
                <div class="summary-block" style="margin-top:14px;">
                    <div class="summary-row"><span>Lines</span><strong id="transfer-lines-summary">0</strong></div>
                    <div class="summary-row"><span>Total Quantity</span><strong id="transfer-qty-summary">0</strong></div>
                </div>
                <p class="list-note">This transfer will reduce stock from your current store and increase it at the destination store with one document number.</p>
                <button type="submit" style="margin-top:14px; width:100%;">Record Transfer</button>
            </section>
        </div>
    </form>

    <script>
        (() => {
            const units = @json($unitsPayload);
            const form = document.getElementById('transfer-form');
            if (!form) return;
            const searchInput = document.getElementById('transfer-search');
            const results = document.getElementById('transfer-search-results');
            const cartList = document.getElementById('transfer-cart-list');
            const cartEmpty = document.getElementById('transfer-cart-empty');
            const hidden = document.getElementById('transfer-items-hidden');
            const linesSummary = document.getElementById('transfer-lines-summary');
            const qtySummary = document.getElementById('transfer-qty-summary');
            let cart = [];
            const normalizeQuantity = (value) => Math.max(Math.round(Number(value || 0)), 1);

            function renderResults() {
                const needle = String(searchInput.value || '').trim().toLowerCase();
                const rows = needle.length < 1 ? units.slice(0, 8) : units.filter((item) => item.search.includes(needle)).slice(0, 20);
                results.innerHTML = rows.length ? rows.map((item) => `<div class="result-card"><div><strong>${item.label}</strong><div class="result-meta">${item.barcode ? `Barcode: ${item.barcode} / ` : ''}${item.part_number ? `Part: ${item.part_number}` : 'Ready to transfer'}</div></div><button type="button" class="sale-add-button" data-add="${item.id}">Add</button></div>`).join('') : `<div class="empty-state">No items matched that search.</div>`;
            }

            function renderCart() {
                cartEmpty.style.display = cart.length ? 'none' : 'block';
                cartList.innerHTML = cart.map((item, index) => `<div class="cart-item"><div class="cart-item-head"><strong>${item.label}</strong><button type="button" class="cart-remove" data-remove="${index}">Remove</button></div><label class="form-field"><span>Quantity</span><div class="qty-box"><button type="button" data-minus="${index}">-</button><input type="number" min="1" step="1" value="${item.quantity}" data-qty="${index}"><button type="button" data-plus="${index}">+</button></div></label></div>`).join('');
                hidden.innerHTML = cart.map((item, index) => `<input type="hidden" name="items[${index}][product_unit_id]" value="${item.id}"><input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">`).join('');
                linesSummary.textContent = String(cart.length);
                qtySummary.textContent = String(cart.reduce((sum, item) => sum + normalizeQuantity(item.quantity), 0));
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
            document.getElementById('clear-transfer-cart').addEventListener('click', () => { cart = []; renderCart(); });
            form.addEventListener('submit', (event) => {
                if (!cart.length) { event.preventDefault(); alert('Add at least one item before posting the transfer.'); searchInput.focus(); }
            });
            renderResults();
            renderCart();
        })();
    </script>
@endsection
