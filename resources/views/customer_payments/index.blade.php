@extends('layouts.app', ['title' => 'Customer Payments'])

@section('content')
    <style>
        .desk-filters {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(150px, .8fr) 140px auto auto;
            gap: 8px;
            align-items: center;
            margin-bottom: 10px;
        }
        .desk-filters input,
        .desk-filters select {
            min-width: 0;
        }
        .desk-panel {
            padding: 10px;
        }
        .desk-panel table {
            min-width: 0;
        }
        .desk-panel th,
        .desk-panel td {
            padding: 7px 6px;
        }
        .desk-panel .table-title,
        .desk-panel .table-title a {
            font-size: .86rem;
        }
        .desk-panel .table-meta {
            font-size: .78rem;
        }
        @media (max-width: 980px) {
            .desk-filters {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <div class="page-head">
        <div>
            <h2>Customer Payments</h2>
            <p>Use this page to find posted customer payments quickly by payment number, customer, date, or related sale. This is the simplest place to confirm that a payment was received.</p>
        </div>
        <div class="actions">
            <a href="{{ route('customer-payments.create') }}" class="button-link primary">Record Payment</a>
            <a href="{{ route('customer-payments.index', ['period' => 'today']) }}" class="button-link {{ $period === 'today' ? 'primary' : '' }}">Today</a>
            <a href="{{ route('customer-payments.index', ['period' => 'week']) }}" class="button-link {{ $period === 'week' ? 'primary' : '' }}">This Week</a>
            <a href="{{ route('customers.index') }}" class="button-link">Customers</a>
        </div>
    </div>

    <form method="get" class="filters desk-filters" data-server-live-search-form data-server-live-search-results="#customer-payments-results" data-server-live-search-delay="450">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search customer payments" autocomplete="off" data-server-live-search-input>
            <select name="customer_id">
                <option value="">All customers</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) $customerId === (string) $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
            <select name="period">
                <option value="">Any date</option>
                <option value="today" @selected($period === 'today')>Today</option>
                <option value="week" @selected($period === 'week')>This Week</option>
            </select>
            <button type="submit">Filter</button>
            @if ($search !== '' || $customerId > 0 || $period !== '')
                <a href="{{ route('customer-payments.index') }}" class="button-link">Clear</a>
            @endif
    </form>
    <p class="list-note">Searches all customer payments, not only visible rows.</p>

    <div id="customer-payments-results" aria-live="polite" aria-busy="false">
        @include('customer_payments.partials.index_results')
    </div>
@endsection
