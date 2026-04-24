@extends('layouts.app', ['title' => 'Roles'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Roles</h2>
            <p>Review the available roles in the system and open any role to manage its permissions from the browser.</p>
        </div>
        <div class="actions">
            <a href="{{ route('roles.matrix') }}" class="button-link primary">Open Permissions Matrix</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Roles</div><div class="value">{{ number_format($roles->count()) }}</div></div>
        <div class="card"><div class="label">Available Permissions</div><div class="value">{{ number_format(count($availablePermissions)) }}</div></div>
        <div class="card"><div class="label">Admin Access</div><div class="value">Full</div></div>
    </section>

    <section class="panel">
        <p class="list-note">Each role can be edited from this page. Admin keeps full access automatically.</p>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Description</th>
                    <th>Users</th>
                    <th>Permissions</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr>
                        <td>
                            <div class="table-title">{{ \Illuminate\Support\Str::headline($role->name) }}</div>
                            <div class="table-meta">{{ $role->name === 'admin' ? 'System super-user role' : 'Operational access profile' }}</div>
                        </td>
                        <td>{{ $role->description ?: '-' }}</td>
                        <td>{{ number_format($role->users_count) }}</td>
                        <td>
                            @if (in_array('*', $role->permissionList(), true))
                                <span class="badge success">Full Access</span>
                            @else
                                <span class="badge">{{ number_format(count($role->permissionList())) }} permissions</span>
                            @endif
                        </td>
                        <td>
                            <div class="action-stack">
                                <a href="{{ route('roles.edit', $role) }}" class="action-chip primary">Manage Permissions</a>
                                <a href="{{ route('roles.matrix') }}" class="action-chip">Matrix</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </section>
@endsection
