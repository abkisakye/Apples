@extends('layouts.app', ['title' => 'Stock Balance'])

@section('content')
    <style>
        .stock-unit-title {
            display: grid;
            gap: 4px;
        }
        .stock-product-link {
            font-weight: 800;
        }
        .stock-unit-chip {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 4px 8px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--panel-soft);
            color: var(--ink);
            font-size: .8rem;
            font-weight: 800;
        }
        .stock-unit-chip span {
            color: var(--muted);
            font-weight: 700;
            margin-right: 4px;
        }
    </style>
    <div class="page-head">
        <div>
            <h2>Stock Balance</h2>
            <p>See the current base-unit stock count, what is low, and how the balance translates into wholesale packs.</p>
        </div>
        <div class="actions">
            @if ($access->can('stock.manage'))
                <a href="{{ route('stock.counts.index') }}" class="button-link">Count Log</a>
                <a href="{{ route('stock.transfers.index') }}" class="button-link">Transfer Log</a>
                <a href="{{ route('stock.adjustments.index') }}" class="button-link">Adjustment Log</a>
                <a href="{{ route('stock.counts.create', request()->only('store_id', 'category_id', 'q')) }}" class="button-link primary">Physical Count</a>
                <a href="{{ route('stock.opening-stock.create', request()->only('store_id', 'category_id', 'q')) }}" class="button-link primary">Add Existing Stock</a>
                <a href="{{ route('stock.transfers.create') }}" class="button-link">Stock Transfer</a>
                <a href="{{ route('stock.adjustments.create') }}" class="button-link">Stock Adjustment</a>
            @endif
            @if ($access->can('purchases.manage'))
                <a href="{{ route('purchases.create') }}" class="button-link">Record Purchase</a>
            @endif
            @if ($access->can('reports.view'))
                <a href="{{ route('reports.product-unit-fix-workbench', request()->only('q', 'category_id')) }}" class="button-link">Product Unit Setup Workbench</a>
            @endif
            <a href="{{ route('stock.balances.export', request()->only('store_id', 'category_id', 'q')) }}" class="button-link">Export CSV</a>
            <a href="{{ route('stock.reorder', request()->only('store_id', 'category_id', 'q')) }}" class="button-link primary">View Reorder List</a>
        </div>
    </div>

    <form method="get" class="filters" data-server-live-search-form data-server-live-search-results="#stock-balances-results" data-server-live-search-delay="450">
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search stock" autocomplete="off" data-server-live-search-input>
            <select name="store_id">
                <option value="">All stores</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}" @selected($filters['store_id'] === $store->id)>{{ $store->name }}</option>
                @endforeach
            </select>
            <select name="category_id">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($filters['category_id'] === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button type="submit">Filter</button>
    </form>
    <p class="list-note">Searches all stock rows, not only visible rows.</p>

    <div id="stock-balances-results" aria-live="polite" aria-busy="false">
        @include('stock.partials.balances_results')
    </div>
@endsection
