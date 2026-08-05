@extends('layouts.app', ['title' => 'Products'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Products</h2>
            <p>Browse the product master, codes, linked suppliers, and reorder settings in a cleaner list.</p>
        </div>
        <div class="actions">
            @if ($access->can('stock.view'))
                <a href="{{ route('stock.balances') }}" class="button-link">Stock Balance</a>
                <a href="{{ route('stock.reorder') }}" class="button-link">Reorder List</a>
            @endif
            @if ($access->can('purchases.manage'))
                <a href="{{ route('products.create') }}" class="button-link">New Product</a>
                <a href="{{ route('purchases.create') }}" class="button-link primary">New Purchase</a>
            @endif
            @if ($access->can('reports.view'))
                <a href="{{ route('reports.price-margins') }}" class="button-link">Product Price & Margin Review</a>
                <a href="{{ route('reports.product-unit-fix-workbench') }}" class="button-link">Product Cost & Conversion Fix</a>
            @endif
        </div>
    </div>

    <form class="filters" method="get" data-server-live-search-form data-server-live-search-results="#products-results" data-server-live-search-delay="450">
        <input type="search" name="q" value="{{ $search }}" placeholder="Search all products or code" autocomplete="off" data-server-live-search-input>
        <select name="category">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="supplier_id">
            <option value="">All suppliers</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected($supplierId === $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">All statuses</option>
            <option value="active" @selected($statusFilter === 'active')>Active</option>
            <option value="inactive" @selected($statusFilter === 'inactive')>Inactive</option>
        </select>
        <button type="submit">Filter</button>
    </form>
    <p class="list-note">Searches all products, not only visible rows.</p>

    <div id="products-results" aria-live="polite" aria-busy="false">
        @include('products.partials.index_results')
    </div>
@endsection
