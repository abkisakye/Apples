@extends('layouts.app', ['title' => 'Product Profile'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($stockSummary = $productSummary['stock_summary'])
    <div class="page-head">
        <div>
            <h2>{{ $product->name }}</h2>
            <p>Review selling units, stock position, and recent movement for this product before buying, adjusting, or checking history.</p>
        </div>
        <div class="actions">
            @if ($access->can('stock.view'))
                <a href="{{ route('stock.balances', ['q' => $product->code ?: $product->name]) }}" class="button-link">Stock Balance</a>
                <a href="{{ route('stock.reorder', ['q' => $product->code ?: $product->name]) }}" class="button-link">Reorder View</a>
                <a href="{{ route('stock.product-history', $product) }}" class="button-link primary">Recent Movements</a>
            @endif
            @if ($access->can('purchases.manage'))
                <a href="{{ route('purchases.create', ['product_id' => $product->id, 'return_to' => url()->full()]) }}" class="button-link primary">Add Stock</a>
                <a href="{{ route('products.edit', $product) }}" class="button-link">Edit Product</a>
            @endif
            @if ($access->can('stock.manage'))
                <a href="{{ route('stock.adjustments.create', ['product_id' => $product->id, 'return_to' => url()->full()]) }}" class="button-link">Adjust Stock</a>
            @endif
            <a href="{{ route('products.index') }}" class="button-link">Back to Products</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Units</div><div class="value">{{ number_format($productSummary['units']) }}</div></div>
        <div class="card"><div class="label">Current Base Stock</div><div class="value">{{ $stockSummary->base_stock_label }}</div></div>
        <div class="card"><div class="label">Friendly Breakdown</div><div class="value" style="font-size:1rem;">Breakdown: {{ $stockSummary->friendly_breakdown }}</div></div>
        <div class="card"><div class="label">Base Unit</div><div class="value">{{ $stockSummary->base_unit_label }}</div></div>
    </section>

    <section class="cards">
        <div class="card"><div class="label">Active Units</div><div class="value">{{ number_format($productSummary['active_units']) }}</div></div>
        <div class="card"><div class="label">Configured Units</div><div class="value" style="font-size:1rem;">{{ $stockSummary->configured_units ?: 'No active units' }}</div></div>
        <div class="card"><div class="label">Estimated Stock Value</div><div class="value money">{{ $currency }} {{ number_format((float) $productSummary['stock_value'], 0) }}</div></div>
    </section>

    <section class="grid-two" style="margin-bottom: 16px;">
        <div class="panel">
            <h3>Product Details</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Code</th><td>{{ $product->code ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Category</th><td>{{ $product->category?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Supplier</th><td>{{ $product->supplier?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Item Group</th><td>{{ $product->item_group ?? 'General item' }}</td></tr>
                    <tr><th style="text-align:left;">Reorder Level</th><td>{{ number_format((float) $product->reorder_level, 0) }}</td></tr>
                    <tr><th style="text-align:left;">VAT</th><td>{{ $product->is_vat_applicable ? 'Applicable' : 'Not applicable' }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Supplier Snapshot</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Supplier</th><td>{{ $product->supplier?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Phone</th><td>{{ $product->supplier?->phone ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Country</th><td>{{ $product->supplier?->country ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Status</th><td><span class="badge {{ $product->is_active ? 'success' : 'credit' }}">{{ $product->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                </tbody>
            </table>
            <p class="list-note">This page focuses on how the product is used operationally. Buying and stock actions stay one click away from here.</p>
            <p class="list-note">To add stock quantity for this product, use <strong>Add Stock</strong> so the quantity comes in through purchases. Use <strong>Adjust Stock</strong> only for corrections, stock take differences, or damaged goods.</p>
        </div>
    </section>

    <section class="panel" style="margin-bottom: 16px;">
        <h3>Selling Unit Configuration</h3>
        <p class="list-note">Stock is controlled above in the base unit. This table keeps the configured selling and buying packs visible without treating each unit as a separate stock balance.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Unit</th>
                        <th>Conversion</th>
                        <th>Selling Price</th>
                        <th>Cost Price</th>
                        <th>Setup</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($unitRows as $row)
                        <tr>
                            <td>
                                <div class="table-title">{{ $row['unit']->unit_name }}</div>
                                <div class="table-meta">
                                    @if ($row['unit']->is_pos_unit)
                                        POS unit
                                    @else
                                        Non-POS unit
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="table-title">{{ rtrim(rtrim(number_format((float) $row['unit']->conversion_factor, 3, '.', ''), '0'), '.') ?: '1' }}</div>
                                <div class="table-meta">{{ $row['unit']->is_base_unit ? 'Base unit' : 'Base units per selected unit' }}</div>
                            </td>
                            <td class="money">{{ $currency }} {{ number_format((float) $row['unit']->selling_price, 0) }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $row['unit']->cost_price, 0) }}</td>
                            <td>
                                <div class="status-inline">
                                    @if ($row['unit']->allow_fractional_quantity)
                                        <span class="badge soft">Decimals {{ (int) $row['unit']->quantity_precision }}</span>
                                    @else
                                        <span class="badge soft">Whole qty</span>
                                    @endif
                                    @if ($row['unit']->is_active)
                                        <span class="badge success">Active</span>
                                    @else
                                        <span class="badge credit">Inactive</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="action-stack">
                                    @if ($access->can('stock.view'))
                                        <a href="{{ route('stock.product-history', $product) }}" class="action-chip">History</a>
                                    @endif
                                    @if ($access->can('purchases.manage'))
                                        <a href="{{ route('purchases.create', ['product_unit_id' => $row['unit']->id, 'return_to' => url()->full()]) }}" class="action-chip primary">Add Stock</a>
                                    @endif
                                    @if ($access->can('stock.manage'))
                                        <a href="{{ route('stock.adjustments.create', ['product_unit_id' => $row['unit']->id, 'return_to' => url()->full()]) }}" class="action-chip">Adjust</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No units are configured for this product.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Recent Stock Movement</h3>
            <p class="list-note"><a href="{{ route('stock.product-history', $product) }}">Open product-level stock history</a> to see selected units, base impact, and running base balance.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Unit</th>
                            <th>Store</th>
                            <th>Reference</th>
                            <th>Movement</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentMovements as $movement)
                            <tr>
                                <td>{{ optional($movement->transaction_date)->format('d M Y') }}</td>
                                <td>{{ $movement->productUnit?->unit_name ?? '-' }}</td>
                                <td>{{ $movement->store?->name ?? '-' }}</td>
                                <td>{{ $movement->reference_no ?? '-' }}</td>
                                <td>{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $movement->movement_type)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No stock movement recorded for this product yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h3>Recent Trading</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Unit</th>
                            <th>Qty</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($recentSales->isEmpty() && $recentPurchases->isEmpty())
                            <tr><td colspan="5" class="muted">No recent sales or purchases for this product yet.</td></tr>
                        @else
                            @foreach ($recentSales as $saleItem)
                                <tr>
                                    <td><span class="badge credit">Sale</span></td>
                                    <td><a href="{{ route('sales.show', $saleItem->sale) }}">{{ $saleItem->sale?->sale_no ?? '-' }}</a></td>
                                    <td>{{ $saleItem->productUnit?->unit_name ?? '-' }}</td>
                                    <td>{{ number_format((float) $saleItem->quantity, 0) }}</td>
                                    <td class="money">{{ $currency }} {{ number_format((float) $saleItem->line_total, 0) }}</td>
                                </tr>
                            @endforeach
                            @foreach ($recentPurchases as $purchaseItem)
                                <tr>
                                    <td><span class="badge success">Purchase</span></td>
                                    <td><a href="{{ route('purchases.show', $purchaseItem->purchase) }}">{{ $purchaseItem->purchase?->purchase_no ?? '-' }}</a></td>
                                    <td>{{ $purchaseItem->productUnit?->unit_name ?? '-' }}</td>
                                    <td>{{ number_format((float) $purchaseItem->quantity, 0) }}</td>
                                    <td class="money">{{ $currency }} {{ number_format((float) $purchaseItem->line_total, 0) }}</td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
