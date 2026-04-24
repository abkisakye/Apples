@extends('layouts.app', ['title' => 'Physical Count Log'])

@section('content')
    @php($countCollection = $rows instanceof \Illuminate\Support\Collection ? $rows : collect($rows))
    <div class="page-head">
        <div>
            <h2>Physical Count Log</h2>
            <p>Review posted shelf-count documents and open the note used to reconcile differences between the shop floor and the system.</p>
        </div>
        <div class="actions">
            @if ($access->can('stock.manage'))
                <a href="{{ route('stock.counts.create') }}" class="button-link primary">New Physical Count</a>
            @endif
            <a href="{{ route('stock.balances') }}" class="button-link">Back to Stock</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Counts Logged</div><div class="value">{{ number_format($countCollection->count()) }}</div></div>
        <div class="card"><div class="label">Draft Counts</div><div class="value">{{ number_format($countCollection->where('status', 'draft')->count()) }}</div></div>
        <div class="card"><div class="label">Total Variance Units</div><div class="value">{{ number_format((int) $countCollection->sum('total_variance_qty')) }}</div></div>
        <div class="card"><div class="label">Latest Count</div><div class="value">{{ optional($countCollection->first()?->count_date)->format('d M Y') ?? '-' }}</div></div>
    </section>

    <section class="panel">
        <p class="list-note">Open the document whenever you need to explain a recount, a shortage, or stock found during a shelf check.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Store</th>
                        <th>Assigned</th>
                        <th>Status</th>
                        <th>Variance</th>
                        <th>Open</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><a href="{{ route('stock.counts.show', $row->count_no) }}">{{ $row->count_no }}</a></td>
                            <td>{{ optional($row->count_date)->format('d M Y') ?: '-' }}</td>
                            <td>{{ $row->store?->name ?? '-' }}</td>
                            <td>
                                <div class="table-title">{{ $row->assignedUser?->name ?? ($row->user?->name ?? 'System User') }}</div>
                                <div class="table-meta">{{ $row->section_name ?: 'General count' }}</div>
                            </td>
                            <td><span class="badge {{ $row->status === 'draft' ? 'soft' : 'success' }}">{{ ucfirst($row->status) }}</span></td>
                            <td>{{ number_format((int) $row->total_variance_qty) }}</td>
                            <td>
                                @if ($row->status === 'draft')
                                    <a href="{{ route('stock.counts.create', ['draft_id' => $row->id]) }}">Resume Draft</a>
                                @else
                                    <a href="{{ route('stock.counts.print', $row->count_no) }}" target="_blank">Print</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">No physical stock counts have been posted yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
