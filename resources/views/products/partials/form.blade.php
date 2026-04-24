<style>
    .profile-form {
        display: grid;
        gap: 16px;
    }
    .form-section-title {
        margin: 0 0 10px;
        font-size: .95rem;
    }
    .field-tip {
        color: var(--muted);
        font-size: .82rem;
        line-height: 1.45;
        margin-top: 2px;
    }
    .side-note {
        display: grid;
        gap: 10px;
    }
    .side-note-card {
        padding: 12px 14px;
        border: 1px solid var(--line);
        border-radius: 14px;
        background: var(--panel-soft);
    }
    .side-note-card strong {
        display: block;
        margin-bottom: 4px;
    }
</style>

<div class="page-head">
    <div>
        <h2>{{ $title }}</h2>
        <p>Maintain the product master and all selling packs or units from one guided screen.</p>
    </div>
    <div class="actions">
        <a href="{{ route('products.index') }}" class="button-link">Back to Products</a>
        @if ($product->exists)
            <a href="{{ route('products.show', $product) }}" class="button-link">Product Profile</a>
        @endif
    </div>
</div>

<section class="grid-two">
    <div class="panel">
        <form method="post" action="{{ $action }}" class="entry-form profile-form" id="product-form">
            @csrf
            @if ($method === 'put')
                @method('put')
            @endif

            <div>
                <h3 class="form-section-title">1. Product Basics</h3>
                <p class="list-note">Set the product name, code, grouping, default supplier, and stock control values here first.</p>
            </div>
            <div class="form-grid">
                <label class="form-field">
                    <span>Product Name</span>
                    <input type="text" name="name" value="{{ old('name', $product->name) }}" required>
                </label>
                <label class="form-field">
                    <span>Code</span>
                    <input type="text" name="code" value="{{ old('code', $product->code) }}">
                    <div class="field-tip">Use the shop code staff already know, if one exists.</div>
                </label>
                <label class="form-field">
                    <span>Category</span>
                    <select name="category_id">
                        <option value="">No category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-field">
                    <span>Supplier</span>
                    <select name="supplier_id">
                        <option value="">No supplier</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $product->supplier_id) === (string) $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-field">
                    <span>Item Group</span>
                    <input type="text" name="item_group" value="{{ old('item_group', $product->item_group) }}">
                </label>
                <label class="form-field">
                    <span>Base Cost Price</span>
                    <input type="number" step="0.01" min="0" name="base_cost_price" value="{{ old('base_cost_price', $product->base_cost_price ?? 0) }}">
                    <div class="field-tip">This is the fallback buying cost before unit-level costs are entered below.</div>
                </label>
                <label class="form-field">
                    <span>Reorder Level</span>
                    <input type="number" step="0.001" min="0" name="reorder_level" value="{{ old('reorder_level', $product->reorder_level ?? 0) }}">
                    <div class="field-tip">Use zero if this product should not appear in reorder alerts.</div>
                </label>
                <label class="form-field">
                    <span>VAT</span>
                    <select name="is_vat_applicable">
                        <option value="1" @selected(old('is_vat_applicable', $product->is_vat_applicable ?? false))>VAT applicable</option>
                        <option value="0" @selected((string) old('is_vat_applicable', $product->is_vat_applicable ?? false) === '0')>No VAT</option>
                    </select>
                </label>
                <label class="form-field">
                    <span>Status</span>
                    <select name="is_active">
                        <option value="1" @selected(old('is_active', $product->is_active ?? true))>Active</option>
                        <option value="0" @selected((string) old('is_active', $product->is_active ?? true) === '0')>Inactive</option>
                    </select>
                </label>
            </div>

            <label class="form-field">
                <span>Notes</span>
                <textarea name="notes" rows="3">{{ old('notes', $product->notes) }}</textarea>
            </label>

            <div>
                <h3 class="form-section-title">2. Units And Selling Packs</h3>
                <p class="list-note">Add every pack size staff can buy or sell, then choose the one cashiers should see first.</p>
            </div>
            <div class="units-head">
                <div>
                    <h3>Units And Pack Sizes</h3>
                    <p class="list-note">Add each selling pack here, like piece, box, carton, tray, or bale. Pick one default POS unit for the cashier flow.</p>
                </div>
                <button type="button" class="button-link" id="add-unit-row">Add Another Unit</button>
            </div>

            <input type="hidden" name="default_unit_index" id="default-unit-index" value="{{ old('default_unit_index', $defaultUnitIndex) }}">

            <div id="unit-rows" class="unit-stack">
                @foreach (old('units', $unitRows->toArray()) as $index => $unit)
                    <div class="unit-card" data-unit-row data-index="{{ $index }}">
                    <div class="unit-card-head">
                        <strong>Unit {{ $index + 1 }}</strong>
                        <button type="button" class="action-chip" data-remove-unit>{{ ! empty($unit['id']) ? 'Set Inactive' : 'Remove Row' }}</button>
                    </div>
                        <input type="hidden" name="units[{{ $index }}][id]" value="{{ $unit['id'] ?? '' }}">
                        <div class="form-grid">
                            <label class="form-field">
                                <span>Unit Name</span>
                                <input type="text" name="units[{{ $index }}][unit_name]" value="{{ $unit['unit_name'] ?? '' }}" required>
                            </label>
                            <label class="form-field">
                                <span>Conversion Factor</span>
                                <input type="number" step="0.001" min="0.001" name="units[{{ $index }}][conversion_factor]" value="{{ $unit['conversion_factor'] ?? 1 }}">
                            </label>
                            <label class="form-field">
                                <span>Selling Price</span>
                                <input type="number" step="0.01" min="0" name="units[{{ $index }}][selling_price]" value="{{ $unit['selling_price'] ?? 0 }}">
                            </label>
                            <label class="form-field">
                                <span>Cost Price</span>
                                <input type="number" step="0.01" min="0" name="units[{{ $index }}][cost_price]" value="{{ $unit['cost_price'] ?? 0 }}">
                            </label>
                            <label class="form-field">
                                <span>Barcode</span>
                                <input type="text" name="units[{{ $index }}][barcode]" value="{{ $unit['barcode'] ?? '' }}">
                            </label>
                            <label class="form-field">
                                <span>Part Number</span>
                                <input type="text" name="units[{{ $index }}][part_number]" value="{{ $unit['part_number'] ?? '' }}">
                            </label>
                            <label class="form-field">
                                <span>Unit Status</span>
                                <select name="units[{{ $index }}][is_active]">
                                    <option value="1" @selected((string) ($unit['is_active'] ?? true) !== '0')>Active</option>
                                    <option value="0" @selected((string) ($unit['is_active'] ?? true) === '0')>Inactive</option>
                                </select>
                            </label>
                            <label class="form-field">
                                <span>Default POS Unit</span>
                                <button type="button" class="action-chip {{ (int) old('default_unit_index', $defaultUnitIndex) === $index ? 'primary' : '' }}" data-default-unit>{{ (int) old('default_unit_index', $defaultUnitIndex) === $index ? 'Selected' : 'Make Default' }}</button>
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="actions">
                <button type="submit">Save Product</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h3>Product Setup Notes</h3>
        <div class="side-note">
            <div class="side-note-card">
                <strong>One product, many packs</strong>
                <div class="field-tip">Use one product record even if it sells as piece, pack, box, or carton. Add those under units instead of creating duplicate products.</div>
            </div>
            <div class="side-note-card">
                <strong>Default POS unit</strong>
                <div class="field-tip">Choose the pack size cashiers use most often so sales entry stays fast.</div>
            </div>
        </div>
        <table>
            <tbody>
                <tr><th style="text-align:left; width:38%;">Product Master</th><td>Use this for naming, supplier linking, grouping, and reorder settings.</td></tr>
                <tr><th style="text-align:left;">Multiple Units</th><td>Add all major selling packs here so the same product can be used as piece, pack, carton, or box without confusion.</td></tr>
                <tr><th style="text-align:left;">Default POS Unit</th><td>Pick the unit that cashiers should see first during normal selling.</td></tr>
                <tr><th style="text-align:left;">Inactive Units</th><td>If a unit should stay in history but stop being used in new work, mark it inactive instead of deleting it.</td></tr>
            </tbody>
        </table>
    </div>
</section>

<template id="unit-row-template">
    <div class="unit-card" data-unit-row data-index="__INDEX__">
        <div class="unit-card-head">
            <strong>Unit __LABEL__</strong>
            <button type="button" class="action-chip" data-remove-unit>Remove Row</button>
        </div>
        <input type="hidden" name="units[__INDEX__][id]" value="">
        <div class="form-grid">
            <label class="form-field">
                <span>Unit Name</span>
                <input type="text" name="units[__INDEX__][unit_name]" value="" required>
            </label>
            <label class="form-field">
                <span>Conversion Factor</span>
                <input type="number" step="0.001" min="0.001" name="units[__INDEX__][conversion_factor]" value="1">
            </label>
            <label class="form-field">
                <span>Selling Price</span>
                <input type="number" step="0.01" min="0" name="units[__INDEX__][selling_price]" value="0">
            </label>
            <label class="form-field">
                <span>Cost Price</span>
                <input type="number" step="0.01" min="0" name="units[__INDEX__][cost_price]" value="0">
            </label>
            <label class="form-field">
                <span>Barcode</span>
                <input type="text" name="units[__INDEX__][barcode]" value="">
            </label>
            <label class="form-field">
                <span>Part Number</span>
                <input type="text" name="units[__INDEX__][part_number]" value="">
            </label>
            <label class="form-field">
                <span>Unit Status</span>
                <select name="units[__INDEX__][is_active]">
                    <option value="1" selected>Active</option>
                    <option value="0">Inactive</option>
                </select>
            </label>
            <label class="form-field">
                <span>Default POS Unit</span>
                <button type="button" class="action-chip" data-default-unit>Make Default</button>
            </label>
        </div>
    </div>
</template>

<style>
    .units-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        margin-top: 6px;
    }
    .unit-stack {
        display: grid;
        gap: 14px;
    }
    .unit-card {
        padding: 16px;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--panel-soft);
    }
    .unit-card-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }
    @media (max-width: 760px) {
        .units-head,
        .unit-card-head {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>

<script>
    (() => {
        const rowsContainer = document.getElementById('unit-rows');
        const template = document.getElementById('unit-row-template');
        const addRowButton = document.getElementById('add-unit-row');
        const defaultInput = document.getElementById('default-unit-index');

        if (!rowsContainer || !template || !addRowButton || !defaultInput) {
            return;
        }

        let nextIndex = Array.from(rowsContainer.querySelectorAll('[data-unit-row]')).length;

        const refreshLabels = () => {
            Array.from(rowsContainer.querySelectorAll('[data-unit-row]')).forEach((row, visibleIndex) => {
                const label = row.querySelector('.unit-card-head strong');
                if (label) {
                    label.textContent = `Unit ${visibleIndex + 1}`;
                }
            });
        };

        const updateDefaultButtons = () => {
            const selectedIndex = Number(defaultInput.value || 0);
            rowsContainer.querySelectorAll('[data-unit-row]').forEach((row) => {
                const button = row.querySelector('[data-default-unit]');
                if (!button) {
                    return;
                }

                const index = Number(row.dataset.index);
                const isSelected = index === selectedIndex;
                button.classList.toggle('primary', isSelected);
                button.textContent = isSelected ? 'Selected' : 'Make Default';
            });
        };

        const ensureDefaultExists = () => {
            const rows = Array.from(rowsContainer.querySelectorAll('[data-unit-row]'));
            if (!rows.length) {
                defaultInput.value = 0;
                return;
            }

            const selectedIndex = Number(defaultInput.value || 0);
            const selectedRow = rows.find((row) => Number(row.dataset.index) === selectedIndex);
            if (!selectedRow) {
                defaultInput.value = rows[0].dataset.index;
            }
            updateDefaultButtons();
        };

        rowsContainer.addEventListener('click', (event) => {
            const defaultButton = event.target.closest('[data-default-unit]');
            if (defaultButton) {
                const row = defaultButton.closest('[data-unit-row]');
                if (row) {
                    defaultInput.value = row.dataset.index;
                    updateDefaultButtons();
                }
                return;
            }

            const removeButton = event.target.closest('[data-remove-unit]');
            if (!removeButton) {
                return;
            }

            const row = removeButton.closest('[data-unit-row]');
            const savedIdInput = row?.querySelector('input[name$=\"[id]\"]');
            const statusSelect = row?.querySelector('select[name$=\"[is_active]\"]');

            if (savedIdInput?.value) {
                if (statusSelect) {
                    statusSelect.value = '0';
                }
                row.style.opacity = '0.7';
                removeButton.textContent = 'Set Inactive';
                return;
            }

            const rows = rowsContainer.querySelectorAll('[data-unit-row]');
            if (rows.length <= 1) {
                return;
            }

            row?.remove();
            refreshLabels();
            ensureDefaultExists();
        });

        addRowButton.addEventListener('click', () => {
            const index = nextIndex++;
            const html = template.innerHTML
                .replaceAll('__INDEX__', index)
                .replaceAll('__LABEL__', rowsContainer.querySelectorAll('[data-unit-row]').length + 1);

            rowsContainer.insertAdjacentHTML('beforeend', html);
            ensureDefaultExists();
        });

        ensureDefaultExists();
    })();
</script>
