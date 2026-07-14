@extends('layouts.app', ['title' => 'Purchases'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Purchases</h2>
            <p>Track goods coming in, see outstanding supplier balances, and print purchase documents from one page.</p>
        </div>
        <div class="actions">
            @if ($access->can('supplier_payments.manage'))
                <a href="{{ route('supplier-payments.create') }}" class="button-link">Record Supplier Payment</a>
            @endif
            @if ($access->can('purchases.manage'))
                <a href="{{ route('purchases.create') }}" class="button-link primary">Record Purchase</a>
            @endif
        </div>
    </div>

    <form method="get" class="filters" data-server-live-search-form data-server-live-search-results="#purchases-results" data-server-live-search-delay="450">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search purchases" autocomplete="off" data-server-live-search-input>
            <select name="type">
                <option value="">All purchase types</option>
                <option value="cash" @selected($type === 'cash')>Cash purchases</option>
                <option value="credit" @selected($type === 'credit')>Credit purchases</option>
            </select>
            <select name="balance">
                <option value="">All balances</option>
                <option value="outstanding" @selected($balance === 'outstanding')>Outstanding only</option>
            </select>
            <button type="submit">Filter</button>
            @if ($search !== '' || $type !== '' || $balance !== '' || $dateFrom || $dateTo)
                <a href="{{ route('purchases.index') }}" class="button-link">Clear</a>
            @endif
    </form>
    <p class="list-note">Searches all purchases, not only visible rows.</p>

    <div id="purchases-results" aria-live="polite" aria-busy="false">
        @include('purchases.partials.index_results')
    </div>
@endsection
