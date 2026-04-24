@extends('layouts.app', ['title' => 'Product Profile'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>{{ $product->name }}</h2>
            <p>Review selling units, stock position, and recent movement for this product before buying, adjusting, or checking history.</p>
        </div>
        <div class="actions">
            @if ($access->can('stock.view'))
                <a href="{{ route('stock.balances', ['q' => $product->code ?: $product->name]) }}" class="button-link">Stock Balance</a>
                <a href="{{ route('stock.reorder', ['q' => $product->code ?: $product->name]) }}" class="button-link">Reorder View</a>
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
        <div class="card"><div class="label">Active Units</div><div class="value">{{ number_format($productSummary['active_units']) }}</div></div>
        <div class="card"><div class="label">System Stock</div><div class="value">{{ number_format((float) $productSummary['stock_balance_units'], 0) }}</div></div>
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
        <h3>Selling Units And Stock Position</h3>
        <p class="list-note">These figures show the current system count for each selling unit. During physical stock count, compare the shelf count to this view before posting any adjustment.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Unit</th>
                        <th>Selling Price</th>
                        <th>Cost Price</th>
                        <th>Units In</th>
                        <th>Units Out</th>
                        <th>System Count</th>
                        <th>Stock Value</th>
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
                            <td class="money">{{ $currency }} {{ number_format((float) $row['unit']->selling_price, 0) }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $row['unit']->cost_price, 0) }}</td>
                            <td>{{ number_format((float) $row['quantity_in'], 0) }}</td>
                            <td>{{ number_format((float) $row['quantity_out'], 0) }}</td>
                            <td><span class="badge {{ (float) $row['balance_qty'] <= 0 ? 'credit' : 'success' }}">{{ number_format((float) $row['balance_qty'], 0) }}</span></td>
                            <td class="money">{{ $currency }} {{ number_format((float) $row['stock_value'], 0) }}</td>
                            <td>
                                <div class="action-stack">
                                    @if ($access->can('stock.view'))
                                        <a href="{{ route('stock.history', $row['unit']->id) }}" class="action-chip">History</a>
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
                        <tr><td colspan="8" class="muted">No units are configured for this product.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Recent Stock Movement</h3>
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
