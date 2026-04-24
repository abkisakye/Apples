@extends('layouts.app', ['title' => 'Physical Stock Count'])

@section('content')
    @php($savedItemsCollection = collect(old('items', []))->isNotEmpty() ? collect(old('items', [])) : $savedItems->map(fn ($item) => [
        'product_unit_id' => $item->product_unit_id,
        'physical_count' => $item->physical_qty,
        'is_counted' => true,
    ])->values())
    @php($defaultCountDate = old('count_date', optional($draftCount?->count_date)->toDateString() ?: now()->toDateString()))
    @php($rowCollection = method_exists($rows, 'items') ? collect($rows->items()) : collect($rows))
    @php($matchingLineCount = method_exists($rows, 'total') ? $rows->total() : $rowCollection->count())
    @php($visibleLineCount = $rowCollection->count())
    @php($firstVisibleLine = method_exists($rows, 'firstItem') ? ($rows->firstItem() ?? 0) : ($visibleLineCount ? 1 : 0))
    @php($lastVisibleLine = method_exists($rows, 'lastItem') ? ($rows->lastItem() ?? 0) : $visibleLineCount)
    <style>
        .count-shell { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(300px,.86fr); gap:18px; align-items:start; }
        .count-stack { display:grid; gap:16px; }
        .count-filter-grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:10px; }
        .count-filter-grid .span-two { grid-column: span 2; }
        .count-list { display:grid; gap:10px; }
        .count-row { display:grid; grid-template-columns:minmax(0,1.2fr) 140px 160px 120px 120px; gap:10px; align-items:center; padding:12px; border:1px solid var(--line); border-radius:16px; background:#fff; }
        .count-row.counted { border-color:#b8d7c4; background:linear-gradient(135deg, #f8fffb 0%, #f1faf5 100%); }
        .count-row strong { display:block; margin-bottom:3px; }
        .count-input { width:100%; border:1px solid var(--line); border-radius:12px; padding:10px 12px; min-height:44px; }
        .count-variance { display:inline-flex; justify-content:center; align-items:center; min-height:44px; padding:0 10px; border-radius:12px; border:1px solid var(--line); background:var(--panel-soft); font-weight:700; }
        .count-variance.plus { background:var(--brand-soft); color:var(--brand); border-color:#b8d7c4; }
        .count-variance.minus { background:var(--apple-soft); color:var(--apple); border-color:#d7b3b3; }
        .count-toggle { display:flex; align-items:center; gap:8px; min-height:44px; padding:0 10px; border:1px solid var(--line); border-radius:12px; background:var(--panel-soft); }
        .count-toggle input { width:18px; height:18px; accent-color:var(--brand); }
        .count-toggle.active { border-color:#b8d7c4; background:var(--brand-soft); color:var(--brand); font-weight:700; }
        .summary-grid { display:grid; gap:12px; }
        .summary-row { display:flex; justify-content:space-between; gap:12px; align-items:center; }
        .summary-callout { padding:12px 14px; border-radius:14px; background:var(--accent-soft); border:1px solid #ead79f; color:var(--accent-ink); line-height:1.45; }
        .count-head { display:grid; grid-template-columns:minmax(0,1.2fr) 140px 160px 120px 120px; gap:10px; padding:0 12px 8px; color:var(--muted); font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
        .store-pill { display:inline-flex; align-items:center; min-height:50px; padding:0 14px; border-radius:14px; border:1px solid var(--line); background:var(--panel-soft); font-weight:600; }
        .empty-state { padding:26px 18px; border:1px dashed var(--line-strong); border-radius:18px; text-align:center; color:var(--muted); background:#fbfcfb; }
        .count-batch-note { display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; margin:10px 0 14px; padding:10px 12px; border-radius:14px; background:var(--panel-soft); color:var(--muted); }
        .count-pager { display:flex; justify-content:space-between; gap:10px; align-items:center; flex-wrap:wrap; margin-top:14px; }
        .count-pager-meta { color:var(--muted); font-size:.92rem; }
        .count-pager-actions { display:flex; gap:8px; flex-wrap:wrap; }
        @media (max-width:1100px) { .count-shell { grid-template-columns:1fr; } }
        @media (max-width:760px) {
            .count-filter-grid { grid-template-columns:1fr; }
            .count-filter-grid .span-two { grid-column:auto; }
            .count-head { display:none; }
            .count-row { grid-template-columns:1fr; }
        }
    </style>

    <div class="page-head">
        <div>
            <h2>Physical Stock Count</h2>
            <p>Count what is physically on the shelf, compare it to the system count, and post only the variance for the selected store.</p>
        </div>
        <div class="actions">
            @if ($draftCount)
                <span class="badge">Draft {{ $draftCount->count_no }}</span>
            @endif
            <a href="{{ route('stock.counts.index') }}" class="button-link">Count Log</a>
            <a href="{{ route('stock.balances', request()->only('store_id', 'category_id', 'q')) }}" class="button-link">Back to Stock</a>
        </div>
    </div>

    <form method="get" class="panel" style="margin-bottom:16px;">
        @if ($draftCount)
            <input type="hidden" name="draft_id" value="{{ $draftCount->id }}">
        @endif
        <div class="count-filter-grid">
            <label class="form-field">
                <span>Store</span>
                <select name="store_id">
                    @foreach ($stores as $store)
                        <option value="{{ $store->id }}" @selected($filters['store_id'] === $store->id)>{{ $store->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-field">
                <span>Category</span>
                <select name="category_id">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($filters['category_id'] === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-field span-two">
                <span>Search</span>
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search product, code, or unit">
            </label>
            <label class="form-field">
                <span>Show Lines</span>
                <select name="show_status">
                    <option value="pending" @selected($showStatus === 'pending')>Pending only</option>
                    <option value="counted" @selected($showStatus === 'counted')>Counted only</option>
                    <option value="all" @selected($showStatus === 'all')>All lines</option>
                </select>
            </label>
            <label class="form-field">
                <span>Count Priority</span>
                <select name="count_focus">
                    <option value="all" @selected($countFocus === 'all')>All stock lines</option>
                    <option value="low_stock" @selected($countFocus === 'low_stock')>Low stock first</option>
                    <option value="zero_or_negative" @selected($countFocus === 'zero_or_negative')>Zero / negative first</option>
                </select>
            </label>
            <label class="form-field">
                <span>Batch Size</span>
                <select name="per_page">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}" @selected($perPage === $option)>{{ number_format($option) }} lines</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="actions" style="margin-top:12px;">
            <button type="submit">Load Count Sheet</button>
            <a href="{{ route('stock.counts.create', ['store_id' => $selectedStore?->id ?? $filters['store_id'], 'draft_id' => $draftCount?->id]) }}" class="button-link">Reset Filters</a>
        </div>
        <p class="list-note">Count one shelf, aisle, or category at a time if that is easier for staff. By default the sheet shows pending lines only, so counted items move out of the way and make room for the next products.</p>
    </form>

    <form method="post" action="{{ route('stock.counts.store') }}" class="count-shell" id="stock-count-form">
        @csrf
        <input type="hidden" name="action" id="stock-count-action" value="{{ old('action') }}">
        <input type="hidden" name="count_date" id="stock-count-date" value="{{ $defaultCountDate }}">
        <input type="hidden" name="stock_count_id" value="{{ old('stock_count_id', $draftCount?->id) }}">
        <input type="hidden" name="store_id" value="{{ $selectedStore?->id ?? $filters['store_id'] }}">
        <input type="hidden" name="q" value="{{ $filters['q'] }}">
        <input type="hidden" name="category_id" value="{{ $filters['category_id'] }}">
        <input type="hidden" name="count_focus" value="{{ $countFocus }}">
        <input type="hidden" name="show_status" value="{{ $showStatus }}">
        <input type="hidden" name="page" value="{{ method_exists($rows, 'currentPage') ? $rows->currentPage() : 1 }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <div class="count-stack">
            <section class="panel">
                <div class="page-head" style="margin-bottom:12px;">
                    <div>
                        <h3 style="margin:0;">Count Sheet</h3>
                        <p style="margin:6px 0 0;">Tick a line when it has been physically counted. Save each batch, then move to the next one without losing progress.</p>
                    </div>
                    <div class="actions">
                        <span class="badge soft">{{ $selectedStore?->name ?? 'Store not selected' }}</span>
                        <span class="badge">{{ number_format($matchingLineCount) }} matching lines</span>
                        @if ($draftCount)
                            <span class="badge success">{{ number_format($draftCount->line_count) }} already counted</span>
                        @endif
                    </div>
                </div>

                @if ($rowCollection->isEmpty())
                    <div class="empty-state">No stock lines matched this filter. Change the store, category, or search and load the sheet again.</div>
                @else
                    <div class="count-batch-note">
                        <span>Showing {{ number_format($firstVisibleLine) }}-{{ number_format($lastVisibleLine) }} of {{ number_format($matchingLineCount) }} matching lines.</span>
                        <span>{{ $countFocus === 'all' ? 'Batch size: '.number_format($perPage).' lines' : ($countFocus === 'low_stock' ? 'Priority: low stock lines' : 'Priority: zero / negative lines') }}</span>
                    </div>
                    <div class="count-head">
                        <div>Product</div>
                        <div>System Count</div>
                        <div>Physical Count</div>
                        <div>Counted</div>
                        <div>Variance</div>
                    </div>
                    <div class="count-list">
                        @foreach ($rowCollection as $index => $row)
                            @php($systemCount = max((int) round((float) $row->balance_qty), 0))
                            @php($savedItem = $savedItemsCollection->firstWhere('product_unit_id', $row->id))
                            @php($isCounted = (bool) data_get($savedItem, 'is_counted', false))
                            @php($physicalCount = data_get($savedItem, 'physical_count', $systemCount))
                            <div class="count-row {{ $isCounted ? 'counted' : '' }}">
                                <div>
                                    <strong>{{ $row->product_name }}</strong>
                                    <div class="table-meta">{{ $row->product_code ?: 'No code' }}</div>
                                    <div class="table-meta">{{ $row->unit_name }} / {{ $row->category_name ?? 'Uncategorized' }}</div>
                                </div>
                                <div>
                                    <span class="badge soft">System {{ number_format($systemCount, 0) }}</span>
                                </div>
                                <div>
                                    <input type="hidden" name="items[{{ $index }}][product_unit_id]" value="{{ $row->id }}">
                                    <input
                                        type="number"
                                        min="0"
                                        step="1"
                                        name="items[{{ $index }}][physical_count]"
                                        value="{{ old("items.{$index}.physical_count", $physicalCount) }}"
                                        class="count-input"
                                        data-count-input
                                        data-system-count="{{ $systemCount }}"
                                    >
                                </div>
                                <div>
                                    <label class="count-toggle {{ old("items.{$index}.is_counted", $isCounted ? 1 : 0) ? 'active' : '' }}">
                                        <input type="checkbox" name="items[{{ $index }}][is_counted]" value="1" @checked(old("items.{$index}.is_counted", $isCounted ? 1 : 0)) data-counted-toggle>
                                        <span>{{ old("items.{$index}.is_counted", $isCounted ? 1 : 0) ? 'Counted' : 'Pending' }}</span>
                                    </label>
                                </div>
                                <div>
                                    <span class="count-variance" data-variance-chip>Match</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if (method_exists($rows, 'hasPages') && $rows->hasPages())
                        <div class="count-pager">
                            <div class="count-pager-meta">Use batches to keep the count sheet responsive on large stores.</div>
                            <div class="count-pager-actions">
                                @if ($rows->onFirstPage())
                                    <span class="button-link" style="opacity:.55; pointer-events:none;">Previous Batch</span>
                                @else
                                    <a href="{{ $rows->previousPageUrl() }}" class="button-link">Previous Batch</a>
                                @endif
                                @if ($rows->hasMorePages())
                                    <a href="{{ $rows->nextPageUrl() }}" class="button-link primary">Next Batch</a>
                                @else
                                    <span class="button-link" style="opacity:.55; pointer-events:none;">Last Batch</span>
                                @endif
                            </div>
                        </div>
                    @endif
                @endif
            </section>
        </div>

        <div class="count-stack">
            <section class="panel">
                <h3>Count Details</h3>
                <div class="summary-grid" style="margin-top:14px;">
                    <label class="form-field">
                        <span>Count Date</span>
                        <input type="date" id="stock-count-date-display" value="{{ $defaultCountDate }}" required>
                    </label>
                    <label class="form-field">
                        <span>Assigned Staff</span>
                        <select name="assigned_user_id">
                            <option value="">Current user</option>
                            @foreach ($countStaff as $staffUser)
                                <option value="{{ $staffUser->id }}" @selected((string) old('assigned_user_id', $selectedAssignedUserId) === (string) $staffUser->id)>{{ $staffUser->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="form-field">
                        <span>Store</span>
                        <div class="store-pill">{{ $selectedStore?->name ?? 'Choose a store above' }}</div>
                    </div>
                    <label class="form-field">
                        <span>Aisle / Section</span>
                        <input type="text" name="section_name" value="{{ $defaultSectionName }}" placeholder="Example: Front shelf, Drinks aisle, Freezer bay">
                    </label>
                    <label class="form-field">
                        <span>Remarks</span>
                        <textarea name="remarks" rows="4" placeholder="Example: April floor count, damaged units removed, shelf recount after delivery.">{{ old('remarks') }}</textarea>
                    </label>
                </div>
            </section>

            <section class="panel">
                <h3>Save</h3>
                <div class="summary-grid" style="margin-top:14px;">
                    <div class="summary-row"><span>Visible Lines</span><strong>{{ number_format($visibleLineCount) }}</strong></div>
                    <div class="summary-row"><span>Total Matching Lines</span><strong>{{ number_format($matchingLineCount) }}</strong></div>
                    <div class="summary-row"><span>Counted In This Batch</span><strong id="count-counted-lines">0</strong></div>
                    <div class="summary-row"><span>Saved In Draft Overall</span><strong>{{ number_format($draftCount?->line_count ?? 0) }}</strong></div>
                    <div class="summary-row"><span>Changed Lines</span><strong id="count-changed-lines">0</strong></div>
                    <div class="summary-row"><span>Total Variance Units</span><strong id="count-total-variance">0</strong></div>
                    <div class="summary-row"><span>Visible Filter</span><strong>{{ $showStatus === 'all' ? 'All lines' : ($showStatus === 'pending' ? 'Pending only' : 'Counted only') }}</strong></div>
                    <div class="summary-row"><span>Count Priority</span><strong>{{ $countFocus === 'all' ? 'All stock lines' : ($countFocus === 'low_stock' ? 'Low stock first' : 'Zero / negative first') }}</strong></div>
                </div>
                <div class="summary-callout" style="margin-top:14px;">
                    Count and save in batches. After each save, the working sheet stays focused on pending lines so already-counted items stop occupying space. Use <strong>Counted only</strong> any time you want to review what has already been done.
                </div>
                <div class="actions" style="margin-top:14px;">
                    <button type="button" data-submit-action="draft" class="button-link" style="flex:1 1 180px;" @disabled($rowCollection->isEmpty())>Save Progress</button>
                    <button type="button" data-submit-action="post" style="flex:1 1 180px;" @disabled($rowCollection->isEmpty())>Post Final Count</button>
                </div>
            </section>
        </div>
    </form>

    <script>
        (() => {
            const form = document.getElementById('stock-count-form');
            if (!form) return;

            const actionInput = document.getElementById('stock-count-action');
            const countDateInput = document.getElementById('stock-count-date');
            const countDateDisplay = document.getElementById('stock-count-date-display');
            const inputs = Array.from(form.querySelectorAll('[data-count-input]'));
            const toggles = Array.from(form.querySelectorAll('[data-counted-toggle]'));
            const submitButtons = Array.from(form.querySelectorAll('[data-submit-action]'));
            const countedLines = document.getElementById('count-counted-lines');
            const changedLines = document.getElementById('count-changed-lines');
            const totalVariance = document.getElementById('count-total-variance');

            const normalizeCount = (value) => Math.max(Math.round(Number(value || 0)), 0);

            function syncCountDate() {
                const fallbackDate = new Date().toISOString().slice(0, 10);
                const value = countDateDisplay?.value || countDateInput?.value || fallbackDate;

                if (countDateDisplay && countDateDisplay.value !== value) {
                    countDateDisplay.value = value;
                }
                if (countDateInput) {
                    countDateInput.value = value;
                }
            }

            function updateRow(input) {
                const row = input.closest('.count-row');
                const chip = row?.querySelector('[data-variance-chip]');
                const toggle = row?.querySelector('[data-counted-toggle]');
                const toggleWrap = row?.querySelector('.count-toggle');
                const toggleLabel = toggleWrap?.querySelector('span:last-child');
                const systemCount = normalizeCount(input.dataset.systemCount);
                const physicalCount = normalizeCount(input.value);
                const variance = physicalCount - systemCount;
                const isCounted = !!toggle?.checked;

                input.value = String(physicalCount);
                if (toggleWrap && toggleLabel) {
                    toggleWrap.classList.toggle('active', isCounted);
                    toggleLabel.textContent = isCounted ? 'Counted' : 'Pending';
                }
                if (row) {
                    row.classList.toggle('counted', isCounted);
                }
                if (!chip) return { variance, isCounted };

                chip.classList.remove('plus', 'minus');
                if (variance === 0) {
                    chip.textContent = 'Match';
                } else if (variance > 0) {
                    chip.classList.add('plus');
                    chip.textContent = `+${variance}`;
                } else {
                    chip.classList.add('minus');
                    chip.textContent = String(variance);
                }

                return { variance, isCounted };
            }

            function updateSummary() {
                let counted = 0;
                let changed = 0;
                let varianceUnits = 0;

                inputs.forEach((input) => {
                    const state = updateRow(input);
                    if (state.isCounted) {
                        counted += 1;
                    }
                    if (state.isCounted && state.variance !== 0) {
                        changed += 1;
                        varianceUnits += Math.abs(state.variance);
                    }
                });

                if (countedLines) countedLines.textContent = String(counted);
                if (changedLines) changedLines.textContent = String(changed);
                if (totalVariance) totalVariance.textContent = String(varianceUnits);
            }

            inputs.forEach((input) => {
                input.addEventListener('input', updateSummary);
                input.addEventListener('blur', updateSummary);
            });
            toggles.forEach((toggle) => {
                toggle.addEventListener('change', updateSummary);
            });
            if (countDateDisplay) {
                countDateDisplay.addEventListener('input', syncCountDate);
                countDateDisplay.addEventListener('change', syncCountDate);
            }
            submitButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    syncCountDate();
                    if (actionInput) {
                        actionInput.value = button.dataset.submitAction || '';
                    }
                    form.requestSubmit();
                });
            });

            form.addEventListener('submit', (event) => {
                syncCountDate();
                updateSummary();
                if (!inputs.length) {
                    event.preventDefault();
                    alert('Load a stock sheet before posting a physical count.');
                    return;
                }

                if (!toggles.some((toggle) => toggle.checked)) {
                    event.preventDefault();
                    alert('Tick at least one line as counted before saving progress or posting the final count.');
                    return;
                }

                if (!actionInput?.value) {
                    event.preventDefault();
                    alert('Choose whether you want to save progress or post the final count.');
                    return;
                }

                if (!countDateInput?.value) {
                    event.preventDefault();
                    alert('Choose a count date before saving progress or posting the final count.');
                }
            });

            syncCountDate();
            updateSummary();
        })();
    </script>
@endsection
