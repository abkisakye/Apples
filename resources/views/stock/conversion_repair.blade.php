@extends('layouts.app', ['title' => 'Stock Conversion Repair'])

@section('content')
    @php($formatQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0')

    <style>
        .repair-grid { display:grid; grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr); gap:16px; align-items:start; }
        .repair-warning { border:1px solid #f59e0b; background:#fffbeb; color:#78350f; border-radius:12px; padding:12px 14px; margin-bottom:14px; }
        .repair-warning strong { display:block; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px; }
        .unit-repair-table input { width:100%; min-height:38px; }
        .unit-repair-table th { white-space:nowrap; }
        .repair-total { border:1px solid var(--line); border-radius:12px; background:var(--panel-soft); padding:12px; display:grid; gap:8px; }
        .repair-total-row { display:flex; justify-content:space-between; gap:12px; align-items:center; }
        .repair-total-row strong { text-align:right; }
        @media (max-width:980px) { .repair-grid { grid-template-columns:1fr; } }
    </style>

    <div class="page-head">
        <div>
            <h2>Stock Conversion Repair</h2>
            <p>Align one product's current base stock to the physical stock counted in boxes, packets, pieces, or any configured unit.</p>
        </div>
        <div class="actions">
            <a href="{{ route('stock.balances', request()->only('store_id')) }}" class="button-link">Back to Stock</a>
            <a href="{{ route('stock.adjustments.index') }}" class="button-link">Adjustment Log</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert danger">
            <strong>Could not post conversion repair.</strong>
            <ul style="margin:8px 0 0; padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="repair-warning">
        <strong>Selected product and store only</strong>
        <p>This does not change purchases, supplier balances, product unit conversion factors, sales, or old inventory rows. It posts one stock adjustment for the variance between system base stock and the physical stock entered here.</p>
    </div>

    <section class="panel" style="margin-bottom:16px;">
        <form method="get" class="filters">
            <select name="store_id" required>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}" @selected((int) $selectedStoreId === (int) $store->id)>{{ $store->name }}</option>
                @endforeach
            </select>
            <select name="product_id" required>
                <option value="">Choose product</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected((int) request('product_id') === (int) $product->id)>{{ $product->name }}{{ $product->code ? ' - '.$product->code : '' }}</option>
                @endforeach
            </select>
            <button type="submit">Load Product</button>
        </form>
    </section>

    @if ($selectedProduct && $summary)
        <form method="post" action="{{ route('stock.conversion-repair.store') }}" class="repair-grid" id="conversion-repair-form">
            @csrf
            <input type="hidden" name="product_id" value="{{ $selectedProduct->id }}">
            <input type="hidden" name="store_id" value="{{ $selectedStoreId }}">

            <section class="panel">
                <h3>Physical Stock Count</h3>
                <p class="list-note">Enter what is physically in the shop using each configured unit. Leave empty units as zero.</p>

                <div style="overflow:auto; margin-top:12px;">
                    <table class="unit-repair-table">
                        <thead>
                            <tr>
                                <th>Unit / Pack</th>
                                <th>Conversion Factor</th>
                                <th>Physical Qty</th>
                                <th>Base Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($selectedProduct->units as $unit)
                                @php($oldQty = old('physical_quantities.'.$unit->id, ''))
                                <tr data-repair-unit-row data-factor="{{ (float) $unit->conversion_factor }}">
                                    <td>
                                        <strong>{{ $unit->unit_name }}</strong>
                                        @if ($unit->is_base_unit || (float) $unit->conversion_factor === 1.0)
                                            <div class="table-meta">Base unit</div>
                                        @endif
                                    </td>
                                    <td>{{ $formatQty($unit->conversion_factor ?: 1) }} {{ $summary->base_unit_label }}</td>
                                    <td>
                                        <input
                                            type="number"
                                            name="physical_quantities[{{ $unit->id }}]"
                                            value="{{ $oldQty }}"
                                            min="0"
                                            step="{{ $unit->allow_fractional_quantity ? '0.001' : '1' }}"
                                            placeholder="0"
                                            data-repair-qty>
                                    </td>
                                    <td><strong data-repair-base>0</strong> {{ $summary->base_unit_label }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="panel">
                <h3>Repair Summary</h3>
                <div class="cards" style="grid-template-columns:1fr; margin-top:10px;">
                    <div class="card"><div class="label">Current Base Stock</div><div class="value">{{ $summary->base_stock_label }}</div></div>
                    <div class="card"><div class="label">Current Equivalent Stock</div><div class="value" style="font-size:1rem;">{{ $summary->equivalent_breakdown }}</div></div>
                    <div class="card"><div class="label">Configured Units</div><div class="value" style="font-size:1rem;">{{ $summary->configured_units }}</div></div>
                </div>

                <div class="repair-total" style="margin-top:12px;">
                    <div class="repair-total-row"><span>Actual Base Stock</span><strong><span id="repair-actual-base">0</span> {{ $summary->base_unit_label }}</strong></div>
                    <div class="repair-total-row"><span>Current System Stock</span><strong>{{ $summary->base_stock_label }}</strong></div>
                    <div class="repair-total-row"><span>Variance</span><strong><span id="repair-variance">0</span> {{ $summary->base_unit_label }}</strong></div>
                </div>

                <label class="form-field" style="margin-top:12px;">
                    <span>Repair Date</span>
                    <input type="date" name="repair_date" value="{{ old('repair_date', now()->toDateString()) }}" required>
                </label>

                <label class="form-field" style="margin-top:12px;">
                    <span>Reason / Note</span>
                    <textarea name="remarks" rows="4" placeholder="Required if this repair reduces stock.">{{ old('remarks') }}</textarea>
                </label>

                <div class="actions" style="margin-top:12px;">
                    <button type="submit" class="primary">Post Conversion Repair</button>
                    <a href="{{ route('stock.balances', ['store_id' => $selectedStoreId, 'q' => $selectedProduct->name]) }}" class="button-link">View Stock Balance</a>
                </div>
            </aside>
        </form>

        <script>
            (() => {
                const currentBase = Number(@json((float) $summary->base_balance));
                const rows = Array.from(document.querySelectorAll('[data-repair-unit-row]'));
                const actual = document.getElementById('repair-actual-base');
                const variance = document.getElementById('repair-variance');
                const format = (value) => {
                    const rounded = Math.round(Number(value || 0) * 1000) / 1000;
                    return rounded.toLocaleString(undefined, { maximumFractionDigits: 3 });
                };
                const recalc = () => {
                    let total = 0;
                    rows.forEach((row) => {
                        const factor = Number(row.dataset.factor || 1) || 1;
                        const input = row.querySelector('[data-repair-qty]');
                        const base = Math.round((Number(input?.value || 0) * factor) * 1000) / 1000;
                        total += base;
                        row.querySelector('[data-repair-base]').textContent = format(base);
                    });
                    total = Math.round(total * 1000) / 1000;
                    actual.textContent = format(total);
                    variance.textContent = `${total - currentBase >= 0 ? '+' : ''}${format(total - currentBase)}`;
                };

                rows.forEach((row) => row.querySelector('[data-repair-qty]')?.addEventListener('input', recalc));
                recalc();
            })();
        </script>
    @else
        <section class="panel">
            <p class="muted">Choose a product and store to calculate a safe conversion repair.</p>
        </section>
    @endif
@endsection
