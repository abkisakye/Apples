@extends('layouts.app', ['title' => 'Product Unit Setup / Price & Conversion Workbench'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($formatQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0')
    @php($formatMoneyInput = function ($value) {
        $raw = trim((string) $value);

        if ($raw === '') {
            return '0';
        }

        if (! preg_match('/^(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d+)?$/', $raw)) {
            return $raw;
        }

        return number_format((float) str_replace(',', '', $raw), 0);
    })
    <style>
        .page { width:100%; max-width:none; }
        .workspace { padding-left:8px; padding-right:8px; }
        .unit-workbench-card { max-height:calc(100vh - 310px); min-height:360px; overflow:auto; }
        .unit-workbench-card table { min-width:1540px; }
        .unit-workbench-card thead th { position:sticky; top:0; z-index:2; background:#fffaf0; }
        .unit-workbench-card td, .unit-workbench-card th { padding:7px 8px; vertical-align:middle; }
        .unit-workbench-card input[type="number"],
        .unit-workbench-card input[type="text"] { min-height:34px; padding:6px 8px; }
        .unit-save-cell { display:grid; gap:5px; min-width:92px; }
        .unit-row-status { min-height:16px; font-size:.74rem; font-weight:800; color:#64748b; }
        .unit-row-status.is-saving { color:#92400e; }
        .unit-row-status.is-saved { color:#047857; }
        .unit-row-status.is-failed { color:#b91c1c; }
    </style>

    <div class="page-head">
        <div>
            <h2>Product Unit Setup / Price & Conversion Workbench</h2>
            <p>Maintain product units, pack conversion, cost, selling price, fractional quantity, precision, and minimum wholesale quantity.</p>
        </div>
        <div class="actions">
            <a href="{{ route('reports.price-margins', request()->only(['q', 'category_id', 'status'])) }}" class="button-link">Back to Price Margins</a>
            <a href="{{ route('reports.financial-summary') }}" class="button-link">Financial Summary</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert danger">
            <strong>Could not save this product unit.</strong>
            <ul style="margin:8px 0 0; padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="panel" style="margin-bottom:16px;">
        <p class="list-note"><strong>Safe setup screen.</strong> Saving here updates product unit setup only. It does not change stock quantities, inventory transactions, sales, purchases, payments, or receipts.</p>
        <form method="get" class="filters" style="margin-top:12px;" data-unit-workbench-filter-form>
            <select name="category_id">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) $filters['category_id'] === (int) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search product, code, category, supplier, barcode, or unit" data-unit-workbench-live-filter>
            <input type="search" name="unit_name" value="{{ $filters['unit_name'] }}" placeholder="Unit name" data-unit-workbench-live-filter>
            <select name="status">
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="per_page">
                @foreach ([10, 25, 50, 100] as $option)
                    <option value="{{ $option }}" @selected((int) $filters['per_page'] === $option)>{{ $option }} rows</option>
                @endforeach
            </select>
            <button type="submit">Apply</button>
        </form>
    </section>

    <section class="cards">
        <div class="card"><div class="label">Rows Shown</div><div class="value">{{ number_format($summary['total_product_units']) }}</div></div>
        <div class="card"><div class="label">Missing Cost</div><div class="value">{{ number_format($summary['missing_cost']) }}</div></div>
        <div class="card"><div class="label">Conversion Review</div><div class="value">{{ number_format($summary['conversion_review_count']) }}</div></div>
        <div class="card"><div class="label">Zero Selling Price</div><div class="value">{{ number_format($summary['zero_selling_price']) }}</div></div>
        <div class="card"><div class="label">Selling Below Cost</div><div class="value">{{ number_format($summary['selling_below_cost']) }}</div></div>
        <div class="card"><div class="label">Low Margin</div><div class="value">{{ number_format($summary['low_margin']) }}</div></div>
    </section>

    <section class="panel">
        <h3>Product Unit Setup Rows</h3>
        <p class="list-note">Use this table to repair old imported product setup. Review conversion factors carefully before saving.</p>
        <div class="unit-workbench-card" style="margin-top:12px;">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Unit / Pack</th>
                        <th>Current conversion factor</th>
                        <th>Editable conversion factor</th>
                        <th>Current cost price</th>
                        <th>Editable cost price</th>
                        <th>Current selling price</th>
                        <th>Editable selling price</th>
                        <th>Allow fractional quantity</th>
                        <th>Quantity precision</th>
                        <th>Minimum wholesale quantity</th>
                        <th>Current warning/status</th>
                        <th>Suggested action</th>
                        <th>Save</th>
                        <th>Full edit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php($formId = 'unit-fix-form-'.$row->unit->id)
                        <tr data-unit-workbench-row="{{ $row->unit->id }}">
                            <td>
                                <strong>{{ $row->product->name }}</strong>
                                <div class="muted">{{ $row->product->code ?: 'No code' }}</div>
                            </td>
                            <td>{{ $row->product->category?->name ?? 'Uncategorised' }}</td>
                            <td>{{ $row->unit->unit_name }}</td>
                            <td>{{ $formatQty($row->conversion_factor) }}</td>
                            <td>
                                <input form="{{ $formId }}" type="number" name="conversion_factor" value="{{ $formatQty($row->conversion_factor) }}" min="0.001" step="0.001" required style="min-width:110px;">
                            </td>
                            <td>{{ $currency }} {{ number_format($row->cost_price, 0) }}</td>
                            <td>
                                <input form="{{ $formId }}" type="text" name="cost_price" value="{{ $formatMoneyInput($row->cost_price) }}" inputmode="numeric" autocomplete="off" data-money-input required style="min-width:120px;">
                            </td>
                            <td>{{ $currency }} {{ number_format($row->selling_price, 0) }}</td>
                            <td>
                                <input form="{{ $formId }}" type="text" name="selling_price" value="{{ $formatMoneyInput($row->selling_price) }}" inputmode="numeric" autocomplete="off" data-money-input required style="min-width:120px;">
                            </td>
                            <td>
                                <input form="{{ $formId }}" type="hidden" name="allow_fractional_quantity" value="0">
                                <label style="display:inline-flex; align-items:center; gap:6px; font-weight:800;">
                                    <input form="{{ $formId }}" type="checkbox" name="allow_fractional_quantity" value="1" @checked($row->unit->allow_fractional_quantity)>
                                    Allow
                                </label>
                            </td>
                            <td>
                                <input form="{{ $formId }}" type="number" name="quantity_precision" value="{{ (int) $row->unit->quantity_precision }}" min="0" max="4" step="1" required style="min-width:80px;">
                            </td>
                            <td>
                                <input form="{{ $formId }}" type="number" name="minimum_wholesale_quantity" value="{{ $row->unit->minimum_wholesale_quantity === null ? '' : $formatQty($row->unit->minimum_wholesale_quantity) }}" min="0.001" step="0.001" placeholder="0.25" style="min-width:110px;">
                            </td>
                            <td><span class="badge {{ $row->status_key === 'healthy_margin' && ! $row->conversion_review ? 'success' : 'credit' }}">{{ $row->warning_label }}</span></td>
                            <td>{{ $row->suggested_action }}</td>
                            <td>
                                <form id="{{ $formId }}" method="post" action="{{ route('reports.product-unit-fix-workbench.update') }}" data-unit-workbench-form>
                                    @csrf
                                    <input type="hidden" name="product_unit_id" value="{{ $row->unit->id }}">
                                    <input type="hidden" name="q" value="{{ $filters['q'] }}">
                                    <input type="hidden" name="category_id" value="{{ $filters['category_id'] ?: '' }}">
                                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                    <input type="hidden" name="unit_name" value="{{ $filters['unit_name'] }}">
                                    <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">
                                    <div class="unit-save-cell">
                                        <button type="submit" data-unit-workbench-save>Save</button>
                                        <span class="unit-row-status" data-unit-workbench-status></span>
                                    </div>
                                </form>
                            </td>
                            <td><a href="{{ route('products.edit', ['product' => $row->product->id, 'focus' => 'units']) }}">Full product edit</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="16" class="muted">No product unit setup rows matched the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:12px;">
            {{ $rows->links() }}
        </div>
    </section>

    @include('partials.developer_credit')
    <script>
        (() => {
            const filterForm = document.querySelector('[data-unit-workbench-filter-form]');
            let filterTimer = null;
            filterForm?.querySelectorAll('[data-unit-workbench-live-filter]').forEach((input) => {
                input.addEventListener('input', () => {
                    clearTimeout(filterTimer);
                    filterTimer = setTimeout(() => {
                        const pageInput = filterForm.querySelector('input[name="page"]');
                        if (pageInput) pageInput.value = '1';
                        filterForm.requestSubmit();
                    }, 500);
                });
            });

            document.querySelectorAll('[data-unit-workbench-form]').forEach((form) => {
                const status = form.querySelector('[data-unit-workbench-status]');
                const button = form.querySelector('[data-unit-workbench-save]');

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    status.textContent = 'Saving...';
                    status.className = 'unit-row-status is-saving';
                    button.disabled = true;

                    try {
                        const formData = new FormData(form);
                        document.querySelectorAll(`[form="${form.id}"]`).forEach((control) => {
                            if (form.contains(control) || !control.name) return;
                            if ((control.type === 'checkbox' || control.type === 'radio') && !control.checked) return;
                            formData.append(control.name, control.value);
                        });

                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: formData,
                        });
                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            const firstError = Object.values(payload.errors || {})?.[0]?.[0];
                            throw new Error(firstError || payload.message || 'Save failed');
                        }

                        status.textContent = 'Saved';
                        status.className = 'unit-row-status is-saved';
                    } catch (error) {
                        status.textContent = error.message || 'Failed';
                        status.className = 'unit-row-status is-failed';
                    } finally {
                        button.disabled = false;
                    }
                });
            });
        })();
    </script>
@endsection
