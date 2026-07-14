@extends('layouts.app', ['title' => 'Suppliers'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Suppliers</h2>
            <p>Keep supplier accounts clean with contacts, opening balances, payment terms, and direct access to purchase history and statements.</p>
        </div>
        <div class="actions">
            @if ($access->can('supplier_payments.manage'))
                <a href="{{ route('supplier-payments.create') }}" class="button-link primary">Post Supplier Payment</a>
            @endif
            @if ($access->can('purchases.manage'))
                <a href="{{ route('suppliers.create') }}" class="button-link">New Supplier</a>
                <a href="{{ route('purchases.create') }}" class="button-link">New Purchase</a>
            @endif
        </div>
    </div>

    <form class="filters" method="get" data-server-live-search-form data-server-live-search-results="#suppliers-results" data-server-live-search-delay="450">
        <input type="search" name="q" value="{{ $search }}" placeholder="Search suppliers" autocomplete="off" data-server-live-search-input>
        <select name="status">
            <option value="">All statuses</option>
            <option value="active" @selected($statusFilter === 'active')>Active</option>
            <option value="inactive" @selected($statusFilter === 'inactive')>Inactive</option>
        </select>
        <button type="submit">Filter</button>
    </form>
    <p class="list-note">Searches all suppliers, not only visible rows.</p>

    <div id="suppliers-results" aria-live="polite" aria-busy="false">
        @include('suppliers.partials.index_results')
    </div>
@endsection
