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
    .profile-form {
        display: grid;
        gap: 20px;
    }
    .form-section-title {
        margin: 0;
        font-size: 1.08rem;
    }
    .field-tip {
        color: var(--muted);
        font-size: .82rem;
        line-height: 1.45;
        margin-top: 2px;
    }
    .product-form-shell {
        display: grid;
        gap: 16px;
        max-width: 1180px;
    }
    .product-panel {
        max-width: none;
    }
    .product-section {
        display: grid;
        gap: 14px;
        padding-bottom: 4px;
    }
    .section-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
    }
    .section-heading p {
        margin: 4px 0 0;
    }
    .product-basics-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }
    .product-basics-grid .span-two {
        grid-column: span 2;
    }
    .product-basics-grid .span-full {
        grid-column: 1 / -1;
    }
    .help-panel {
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--panel-soft);
        padding: 0;
    }
    .help-panel summary {
        cursor: pointer;
        padding: 12px 14px;
        font-weight: 800;
    }
    .help-panel-body {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        padding: 0 14px 14px;
    }
    .help-note strong {
        display: block;
        margin-bottom: 3px;
    }
    .save-row {
        display: flex;
        justify-content: flex-end;
        padding-top: 4px;
    }
    @media (max-width: 1100px) {
        .product-basics-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 760px) {
        .section-heading,
        .save-row {
            align-items: stretch;
            flex-direction: column;
        }
        .product-basics-grid,
        .help-panel-body {
            grid-template-columns: 1fr;
        }
        .product-basics-grid .span-two {
            grid-column: 1 / -1;
        }
    }
</style>

<div class="page-head">
    <div>
        <h2>{{ $title }}</h2>
        <p>Maintain one wholesale + retail product record, then add every selling pack or unit from the same screen.</p>
    </div>
    <div class="actions">
        <a href="{{ route('products.index') }}" class="button-link">Back to Products</a>
        @if ($product->exists)
            <a href="{{ route('products.show', $product) }}" class="button-link">Product Profile</a>
        @endif
        @if ($access->can('reports.view'))
            <a href="{{ route('reports.product-unit-fix-workbench', $product->exists ? ['q' => $product->code ?: $product->name] : []) }}" class="button-link">Product Unit Setup Workbench</a>
        @endif
    </div>
</div>

<section class="product-form-shell">
    <div class="panel product-panel">
        <form method="post" action="{{ $action }}" class="entry-form profile-form" id="product-form">
            @csrf
            @if ($method === 'put')
                @method('put')
            @endif

            <input type="hidden" name="item_group" value="{{ old('item_group', $product->item_group) }}">
            <input type="hidden" name="base_cost_price" value="{{ old('base_cost_price', $product->base_cost_price ?? 0) }}">

            <section class="product-section">
                <div class="section-heading">
                    <div>
                        <h3 class="form-section-title">Product Basics</h3>
                        <p class="list-note">Name the item and choose the core settings staff need every day.</p>
                    </div>
                </div>

                <div class="product-basics-grid">
                    <label class="form-field span-two">
                        <span>Product Name</span>
                        <input type="text" name="name" value="{{ old('name', $product->name) }}" required>
                    </label>
                    <label class="form-field">
                        <span>Code / Barcode</span>
                        <input type="text" name="code" value="{{ old('code', $product->code) }}">
                    </label>
                    @php($canQuickAddCategory = $access->can('business.manage') || $access->can('master_data.manage'))
                    <div class="form-field quick-category-field">
                        <span class="quick-category-label">Category</span>
                        <div class="quick-category-row">
                            <select name="category_id" id="product-category-select">
                                <option value="">No category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @if ($canQuickAddCategory)
                                <button type="button" class="button-link" data-quick-category-toggle="product-category-panel">+ New</button>
                            @endif
                        </div>
                        @if ($canQuickAddCategory)
                            @include('partials.quick-category-panel', [
                                'panelId' => 'product-category-panel',
                                'selectId' => 'product-category-select',
                                'endpoint' => route('products.categories.quick-store'),
                            ])
                        @endif
                    </div>
                    <label class="form-field span-two">
                        <span>Supplier</span>
                        <select name="supplier_id">
                            <option value="">No supplier</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $product->supplier_id) === (string) $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-field">
                        <span>Reorder Level</span>
                        <input type="number" step="0.001" min="0" name="reorder_level" value="{{ old('reorder_level', $product->reorder_level ?? 0) }}">
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
                    <label class="form-field span-full">
                        <span>Notes</span>
                        <textarea name="notes" rows="2">{{ old('notes', $product->notes) }}</textarea>
                    </label>
                </div>
            </section>

            <section class="product-section">
                <div class="section-heading units-head">
                    <div>
                        <h3 class="form-section-title">Units & Selling Packs</h3>
                        <p class="list-note">Create one product, then add all selling packs/units such as piece, dozen, box, carton, sack, bundle, bottle, tin, half carton where applicable.</p>
                        <p class="list-note">Allow fractional quantities for wholesale packs such as 0.25, 0.5, or 0.75 carton. Use minimum wholesale quantity to prevent selling very small fractions at wholesale price.</p>
                        <p class="list-note">To apply fractional wholesale to all pack units, run <code>php artisan product-units:enable-fractional-wholesale --dry-run</code>, review the rows, then run with <code>--commit</code>.</p>
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
                                <span>Base Unit</span>
                                <select name="units[{{ $index }}][is_base_unit]">
                                    <option value="0" @selected(empty($unit['is_base_unit']))>No</option>
                                    <option value="1" @selected(! empty($unit['is_base_unit']))>Yes, base unit</option>
                                </select>
                                <span class="field-tip">Usually the smallest stock unit, such as piece or kg.</span>
                            </label>
                            <label class="form-field">
                                <span>Allow Fractional Qty</span>
                                <select name="units[{{ $index }}][allow_fractional_quantity]">
                                    <option value="0" @selected(empty($unit['allow_fractional_quantity']))>Whole numbers only</option>
                                    <option value="1" @selected(! empty($unit['allow_fractional_quantity']))>Allow decimals</option>
                                </select>
                            </label>
                            <label class="form-field">
                                <span>Quantity Precision</span>
                                <select name="units[{{ $index }}][quantity_precision]">
                                    @for ($precision = 0; $precision <= 3; $precision++)
                                        <option value="{{ $precision }}" @selected((int) ($unit['quantity_precision'] ?? 0) === $precision)>{{ $precision }} decimal place{{ $precision === 1 ? '' : 's' }}</option>
                                    @endfor
                                </select>
                            </label>
                            <label class="form-field">
                                <span>Minimum Wholesale Qty</span>
                                <input type="number" step="0.001" min="0" name="units[{{ $index }}][minimum_wholesale_quantity]" value="{{ $unit['minimum_wholesale_quantity'] ?? '' }}" placeholder="Example: 0.25">
                                <span class="field-tip">Leave blank unless this wholesale pack can be sold in fractions.</span>
                            </label>
                            <label class="form-field">
                                <span>Cost Price</span>
                                <input type="text" name="units[{{ $index }}][cost_price]" value="{{ $formatMoneyInput($unit['cost_price'] ?? 0) }}" inputmode="numeric" autocomplete="off" data-money-input>
                            </label>
                            <label class="form-field">
                                <span>Selling Price</span>
                                <input type="text" name="units[{{ $index }}][selling_price]" value="{{ $formatMoneyInput($unit['selling_price'] ?? 0) }}" inputmode="numeric" autocomplete="off" data-money-input>
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
            </section>

            <details class="help-panel">
                <summary>Help / Setup Notes</summary>
                <div class="help-panel-body">
                    <div class="help-note">
                        <strong>One product, many packs</strong>
                        <div class="field-tip">Use one product record for piece, dozen, box, carton, sack, bundle, bottle, tin, and half carton sales where applicable.</div>
                    </div>
                    <div class="help-note">
                        <strong>Avoid duplicate products</strong>
                        <div class="field-tip">Do not create separate products for carton and piece versions of the same item; add them here as units.</div>
                    </div>
                    <div class="help-note">
                        <strong>Default POS Unit</strong>
                        <div class="field-tip">Choose the pack size cashiers should see first. Conversion behavior has not changed in this phase.</div>
                    </div>
                </div>
            </details>

            <div class="actions save-row">
                <button type="submit">Save Product</button>
            </div>
        </form>
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
                <span>Base Unit</span>
                <select name="units[__INDEX__][is_base_unit]">
                    <option value="0" selected>No</option>
                    <option value="1">Yes, base unit</option>
                </select>
                <span class="field-tip">Usually the smallest stock unit, such as piece or kg.</span>
            </label>
            <label class="form-field">
                <span>Allow Fractional Qty</span>
                <select name="units[__INDEX__][allow_fractional_quantity]">
                    <option value="0" selected>Whole numbers only</option>
                    <option value="1">Allow decimals</option>
                </select>
            </label>
            <label class="form-field">
                <span>Quantity Precision</span>
                <select name="units[__INDEX__][quantity_precision]">
                    <option value="0" selected>0 decimal places</option>
                    <option value="1">1 decimal place</option>
                    <option value="2">2 decimal places</option>
                    <option value="3">3 decimal places</option>
                </select>
            </label>
            <label class="form-field">
                <span>Minimum Wholesale Qty</span>
                <input type="number" step="0.001" min="0" name="units[__INDEX__][minimum_wholesale_quantity]" value="" placeholder="Example: 0.25">
                <span class="field-tip">Leave blank unless this wholesale pack can be sold in fractions.</span>
            </label>
            <label class="form-field">
                <span>Cost Price</span>
                <input type="text" name="units[__INDEX__][cost_price]" value="0" inputmode="numeric" autocomplete="off" data-money-input>
            </label>
            <label class="form-field">
                <span>Selling Price</span>
                <input type="text" name="units[__INDEX__][selling_price]" value="0" inputmode="numeric" autocomplete="off" data-money-input>
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
        padding: 12px;
        border: 1px solid var(--line);
        border-radius: 10px;
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
            window.AppMoneyInput?.prepare(rowsContainer);
            ensureDefaultExists();
        });

        ensureDefaultExists();
    })();
</script>
