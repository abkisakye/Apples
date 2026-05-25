@extends('layouts.app', ['title' => 'Expenses'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Expenses</h2>
            <p>Record and review supermarket operating costs like transport, wages, repairs, rent, and daily running expenses.</p>
        </div>
        <div class="actions">
            @if ($access->can('expenses.manage'))
                <a href="{{ route('expenses.create') }}" class="button-link primary">New Expense</a>
            @endif
            <a href="{{ route('expenses.index', ['period' => 'today']) }}" class="button-link {{ $period === 'today' ? 'primary' : '' }}">Today</a>
            <a href="{{ route('expenses.index', ['period' => 'week']) }}" class="button-link {{ $period === 'week' ? 'primary' : '' }}">This Week</a>
            <a href="{{ route('expenses.index', ['period' => 'month']) }}" class="button-link {{ $period === 'month' ? 'primary' : '' }}">This Month</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Entries</div><div class="value">{{ number_format($summary['count']) }}</div></div>
        <div class="card"><div class="label">Total Value</div><div class="value money">{{ $currency }} {{ number_format($summary['amount'], 0) }}</div></div>
        <div class="card"><div class="label">Today</div><div class="value money">{{ $currency }} {{ number_format($summary['today'], 0) }}</div></div>
    </section>

    <section class="panel">
        <form method="get" class="filters">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search expense no, reference, category, notes, or store">
            <select name="expense_category_id">
                <option value="">All categories</option>
                @foreach ($categories as $categoryOption)
                    <option value="{{ $categoryOption->id }}" @selected((string) $expenseCategoryId === (string) $categoryOption->id)>{{ $categoryOption->name }}</option>
                @endforeach
            </select>
            <select name="store_id">
                <option value="">All stores</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}" @selected((string) $storeId === (string) $store->id)>{{ $store->name }}</option>
                @endforeach
            </select>
            <select name="period">
                <option value="">Any date</option>
                <option value="today" @selected($period === 'today')>Today</option>
                <option value="week" @selected($period === 'week')>This week</option>
                <option value="month" @selected($period === 'month')>This month</option>
            </select>
            <button type="submit">Filter</button>
            @if ($search !== '' || $category !== '' || $expenseCategoryId > 0 || $storeId > 0 || $period !== '')
                <a href="{{ route('expenses.index') }}" class="button-link">Clear</a>
            @endif
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Expense</th>
                        <th>Category</th>
                        <th>Store</th>
                        <th>Mode</th>
                        <th>Amount</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td>{{ optional($expense->expense_date)->format('d M Y') }}</td>
                            <td>
                                <div class="table-title"><a href="{{ route('expenses.show', $expense) }}">{{ $expense->expense_no }}</a></div>
                                <div class="table-meta">{{ $expense->creator?->name ?? 'System user' }}</div>
                            </td>
                            <td>{{ $expense->categoryName() }}</td>
                            <td>{{ $expense->store?->name ?? '-' }}</td>
                            <td>{{ $expense->paymentMode?->name ?? '-' }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $expense->amount, 0) }}</td>
                            <td>{{ $expense->reference_no ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">No expenses match this filter yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $expenses->links() }}
        </div>
    </section>
@endsection
