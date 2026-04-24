@extends('layouts.app', ['title' => 'Capital Inputs'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Capital Inputs</h2>
            <p>Track money introduced into the business and clearly separate what came from operations versus outside funding.</p>
        </div>
        <div class="actions">
            @if ($access->can('capital.manage'))
                <a href="{{ route('capital.create') }}" class="button-link primary">Record Capital</a>
            @endif
            @if ($access->can('dashboard.view'))
                <a href="{{ route('dashboard') }}" class="button-link">Dashboard</a>
            @endif
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Entries</div><div class="value">{{ number_format($capitalSummary['total_entries']) }}</div></div>
        <div class="card"><div class="label">Total Capital</div><div class="value money">{{ $currency }} {{ number_format($capitalSummary['total_amount'], 0) }}</div></div>
        <div class="card"><div class="label">From Business</div><div class="value money">{{ $currency }} {{ number_format($capitalSummary['business_generated'], 0) }}</div></div>
        <div class="card"><div class="label">From Outside</div><div class="value money">{{ $currency }} {{ number_format($capitalSummary['external'], 0) }}</div></div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <form method="get" class="filters">
                <input type="text" name="q" value="{{ $search }}" placeholder="Search entry, source, store, or reference">
                <select name="origin">
                    <option value="">All origins</option>
                    <option value="business_generated" @selected($origin === 'business_generated')>From business</option>
                    <option value="external" @selected($origin === 'external')>From outside</option>
                </select>
                <select name="store_id">
                    <option value="">All stores</option>
                    @foreach ($stores as $store)
                        <option value="{{ $store->id }}" @selected($storeId === $store->id)>{{ $store->name }}</option>
                    @endforeach
                </select>
                <button type="submit">Filter</button>
            </form>

            <p class="list-note">Use this list when management asks where business cash came from, whether from the business itself or from an outside source.</p>

            <div class="table-wrap table-mobile-friendly">
                <table>
                    <thead>
                        <tr>
                            <th>Entry</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($capitalEntries as $entry)
                            <tr>
                                <td>
                                    <div class="cell-stack">
                                        <div class="table-title">{{ $entry->entry_no }}</div>
                                        <div class="table-meta">{{ optional($entry->entry_date)->format('d M Y') }} / {{ $entry->reference_no ?: 'No reference' }}</div>
                                        <div class="table-meta">{{ $entry->paymentMode?->name ?? 'Payment mode not set' }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-stack">
                                        <div class="table-title">{{ $entry->source?->name ?? 'Unknown source' }}</div>
                                        <div class="table-meta">{{ $entry->store?->name ?? 'General store entry' }}</div>
                                        <div class="table-meta">{{ $entry->notes ?: 'No extra notes recorded.' }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="status-inline">
                                        <span class="badge {{ $entry->source_origin === 'external' ? 'credit' : 'success' }}">
                                            {{ str_replace('_', ' ', ucfirst($entry->source_origin)) }}
                                        </span>
                                        <span class="badge soft">
                                            {{ str_replace('_', ' ', ucfirst($entry->source?->source_type ?? 'general')) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="money">{{ $currency }} {{ number_format((float) $entry->amount, 0) }}</td>
                                <td>
                                    <div class="action-stack">
                                        <a href="{{ route('capital.index', ['origin' => $entry->source_origin]) }}" class="action-chip">Same Origin</a>
                                        @if ($entry->store_id)
                                            <a href="{{ route('capital.index', ['store_id' => $entry->store_id]) }}" class="action-chip soft">Same Store</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="muted">No capital entries have been recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                {{ $capitalEntries->links() }}
            </div>
        </div>

        <div class="panel">
            <h3>Capital Sources</h3>
            <div class="table-wrap table-mobile-friendly">
                <table>
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Type</th>
                            <th>Entries</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($capitalSources as $source)
                            <tr>
                                <td>
                                    <div class="table-title">{{ $source->name }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $source->source_type === 'external' ? 'credit' : 'success' }}">
                                        {{ str_replace('_', ' ', ucfirst($source->source_type)) }}
                                    </span>
                                </td>
                                <td>{{ number_format($source->entries_count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="list-note">This gives the client a clear answer to a common question: did the money come from the business itself or from outside?</p>
        </div>
    </section>
@endsection
