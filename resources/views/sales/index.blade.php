@extends('layouts.app', ['title' => $pageTitle ?? 'Sales'])

@section('content')
    <style>
        .desk-cards {
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }
        .desk-cards .card {
            padding: 10px;
        }
        .desk-cards .card .value {
            font-size: 1.08rem;
        }
        .desk-filters {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) 160px auto;
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
        @media (max-width: 900px) {
            .desk-filters {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <div class="page-head">
        <div>
            <h2>{{ $pageTitle ?? 'Sales' }}</h2>
            <p>Review supermarket sales only. Open a sale to reprint the receipt, check payment, or follow up a customer balance.</p>
        </div>
        <div class="actions">
            @if ($access->can('customer_payments.manage'))
                <a href="{{ route('customer-payments.create') }}" class="button-link">Record Customer Payment</a>
            @endif
            @if ($access->can('sales.manage'))
                <a href="{{ route('sales.create') }}" class="button-link primary">Record Sale</a>
            @endif
        </div>
    </div>

    <form class="filters desk-filters" method="get" data-server-live-search-form data-server-live-search-results="#sales-results" data-server-live-search-delay="450">
        <input type="search" name="q" value="{{ $search }}" placeholder="Search sales" autocomplete="off" data-server-live-search-input>
        <select name="type">
            <option value="">All types</option>
            <option value="cash" @selected($type === 'cash')>Cash</option>
            <option value="credit" @selected($type === 'credit')>Credit</option>
        </select>
        <button type="submit">Filter</button>
    </form>
    <p class="list-note">Searches all sales records, not only visible rows.</p>

    <div id="sales-results" aria-live="polite" aria-busy="false">
        @include('sales.partials.index_results')
    </div>
@endsection
