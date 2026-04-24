@extends('layouts.app', ['title' => $config['title']])

@section('content')
    <div class="page-head">
        <div>
            <h2>{{ $config['title'] }}</h2>
            <p>{{ $config['description'] }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('settings.business.edit') }}" class="button-link">Business Settings</a>
            <a href="{{ route('master-data.index', ['resource' => $resource]) }}" class="button-link {{ ! request()->integer('edit') ? 'primary' : '' }}">New {{ $config['single'] }}</a>
        </div>
    </div>

    <div class="actions" style="margin-bottom: 16px;">
        @foreach ([
            'stores' => 'Stores',
            'categories' => 'Categories',
            'payment-modes' => 'Payment Modes',
            'capital-sources' => 'Capital Sources',
        ] as $resourceKey => $resourceLabel)
            <a href="{{ route('master-data.index', ['resource' => $resourceKey]) }}" class="button-link {{ $resource === $resourceKey ? 'primary' : '' }}">{{ $resourceLabel }}</a>
        @endforeach
    </div>

    <section class="cards">
        <div class="card"><div class="label">Listed</div><div class="value">{{ number_format($records->total()) }}</div></div>
        <div class="card"><div class="label">Active On Page</div><div class="value">{{ number_format($records->getCollection()->where('is_active', true)->count()) }}</div></div>
        <div class="card"><div class="label">Inactive On Page</div><div class="value">{{ number_format($records->getCollection()->where('is_active', false)->count()) }}</div></div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <form class="filters" method="get">
                <input type="hidden" name="resource" value="{{ $resource }}">
                <input type="text" name="q" value="{{ $search }}" placeholder="Search {{ strtolower($config['title']) }}">
                <select name="status">
                    <option value="">All statuses</option>
                    <option value="active" @selected($statusFilter === 'active')>Active</option>
                    <option value="inactive" @selected($statusFilter === 'inactive')>Inactive</option>
                </select>
                <button type="submit">Filter</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            @foreach ($config['columns'] as $column)
                                <th>{{ $column['label'] }}</th>
                            @endforeach
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                @foreach ($config['columns'] as $column)
                                    <td>
                                        @if (($column['type'] ?? null) === 'status')
                                            <span class="badge {{ $record->{$column['value']} ? 'success' : 'credit' }}">{{ $record->{$column['value']} ? 'Active' : 'Inactive' }}</span>
                                        @elseif (isset($column['meta']))
                                            <div class="table-title">{{ $record->{$column['value']} ?: '-' }}</div>
                                            <div class="table-meta">{{ $record->{$column['meta']} ?: '-' }}</div>
                                        @else
                                            {{ $record->{$column['value']} ?: '-' }}
                                        @endif
                                    </td>
                                @endforeach
                                <td>
                                    <div class="action-stack">
                                        <a href="{{ route('master-data.index', ['resource' => $resource, 'edit' => $record->id]) }}" class="action-chip">Edit</a>
                                        <form method="post" action="{{ route('master-data.status', ['resource' => $resource, 'record' => $record->id]) }}">
                                            @csrf
                                            <input type="hidden" name="is_active" value="{{ $record->is_active ? 0 : 1 }}">
                                            <button type="submit" class="action-chip {{ $record->is_active ? 'accent' : 'good' }}">{{ $record->is_active ? 'Archive' : 'Activate' }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($config['columns']) + 1 }}" class="muted">No {{ strtolower($config['title']) }} found for the current filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination">{{ $records->links() }}</div>
        </div>

        <div class="panel">
            <h3>{{ $editing->exists ? 'Edit '.$config['single'] : 'New '.$config['single'] }}</h3>
            <p class="list-note">Use this form to keep the setup records clean so sales, purchases, and reports stay accurate for the team.</p>

            <form method="post" action="{{ $editing->exists ? route('master-data.update', ['resource' => $resource, 'record' => $editing->id]) : route('master-data.store', ['resource' => $resource]) }}" class="entry-form">
                @csrf
                @if ($editing->exists)
                    @method('put')
                @endif

                <div class="form-grid">
                    @foreach ($config['fields'] as $field)
                        @if ($field['type'] === 'status')
                            <label class="form-field">
                                <span>{{ $field['label'] }}</span>
                                <select name="is_active">
                                    <option value="1" @selected(old('is_active', $editing->is_active ?? true))>Active</option>
                                    <option value="0" @selected((string) old('is_active', $editing->is_active ?? true) === '0')>Inactive</option>
                                </select>
                            </label>
                        @else
                            <label class="form-field">
                                <span>{{ $field['label'] }}</span>
                                <input type="{{ $field['type'] }}" name="{{ $field['name'] }}" value="{{ old($field['name'], $editing->{$field['name']} ?? '') }}" {{ ($field['required'] ?? false) ? 'required' : '' }}>
                            </label>
                        @endif
                    @endforeach
                </div>

                <div class="actions">
                    <button type="submit">Save {{ $config['single'] }}</button>
                    @if ($editing->exists)
                        <a href="{{ route('master-data.index', ['resource' => $resource]) }}" class="button-link">Cancel Edit</a>
                    @endif
                </div>
            </form>
        </div>
    </section>
@endsection
