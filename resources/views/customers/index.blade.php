@extends('layouts.app', ['title' => 'Customers'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Customers</h2>
            <p>Keep customer accounts easy to follow with contact details, credit setup, and direct access to each customer statement.</p>
        </div>
        <div class="actions">
            @if ($access->can('customer_payments.manage'))
                <a href="{{ route('customer-payments.create') }}" class="button-link primary">Record Customer Payment</a>
            @endif
            @if ($access->can('sales.manage'))
                <a href="{{ route('customers.create') }}" class="button-link">New Customer</a>
                <a href="{{ route('sales.create') }}" class="button-link">New Sale</a>
            @endif
        </div>
    </div>

    <form class="filters" method="get" data-server-live-search-form data-server-live-search-results="#customers-results" data-server-live-search-delay="450">
        <input type="search" name="q" value="{{ $search }}" placeholder="Search customers" autocomplete="off" data-server-live-search-input>
        <select name="type">
            <option value="">All account types</option>
            <option value="regular" @selected($accountType === 'regular')>Named customers</option>
            <option value="credit" @selected($accountType === 'credit')>Credit customers</option>
            <option value="walk_in" @selected($accountType === 'walk_in')>Walk-in account</option>
        </select>
        <select name="status">
            <option value="">All statuses</option>
            <option value="active" @selected($statusFilter === 'active')>Active</option>
            <option value="inactive" @selected($statusFilter === 'inactive')>Inactive</option>
        </select>
        <button type="submit">Filter</button>
    </form>
    <p class="list-note">Searches all customers, not only visible rows.</p>

    <div id="customers-results" aria-live="polite" aria-busy="false">
        @include('customers.partials.index_results')
    </div>
@endsection
