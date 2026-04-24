@extends('layouts.app', ['title' => 'Users'])

@section('content')
    @php($userCollection = $users->getCollection())
    <section class="cards">
        <div class="card"><div class="label">Users</div><div class="value">{{ number_format($users->total()) }}</div></div>
        <div class="card"><div class="label">Shown</div><div class="value">{{ number_format($userCollection->count()) }}</div></div>
        <div class="card"><div class="label">Active</div><div class="value">{{ number_format($userCollection->where('is_active', true)->count()) }}</div></div>
        <div class="card"><div class="label">Imported Legacy</div><div class="value">{{ number_format($userCollection->where('is_legacy_user', true)->count()) }}</div></div>
    </section>

    <div class="page-head">
        <div>
            <h2>Users</h2>
            <p>Manage staff accounts, roles, and default store assignments.</p>
        </div>
        <div class="actions">
            <a href="{{ route('roles.matrix') }}" class="button-link">Permissions Matrix</a>
            <a href="{{ route('roles.index') }}" class="button-link">Manage Roles</a>
            <a href="{{ route('users.create') }}" class="button-link primary">Add User</a>
        </div>
    </div>

    <section class="panel">
        <form method="get" class="filters">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search name, username, or email">
            <select name="role_id">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected($roleId === $role->id)>{{ \Illuminate\Support\Str::headline($role->name) }}</option>
                @endforeach
            </select>
            <select name="status">
                <option value="">All statuses</option>
                <option value="active" @selected($statusFilter === 'active')>Active</option>
                <option value="inactive" @selected($statusFilter === 'inactive')>Inactive</option>
            </select>
            <button type="submit">Filter</button>
        </form>
        <p class="list-note">Use this page to find staff quickly, confirm which store and role they work with, and jump straight into role assignment or account edits.</p>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Source</th>
                    <th>Access Department</th>
                    <th>Role</th>
                    <th>Default Store</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>
                            <div class="table-title">{{ $user->name }}</div>
                            @if ($user->is_legacy_user)
                                <div class="table-meta">Legacy Login ID: {{ $user->legacy_login_id }}</div>
                            @endif
                        </td>
                        <td>{{ $user->username ?? '-' }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            <span class="badge {{ $user->is_legacy_user ? '' : 'credit' }}">{{ $user->importSourceLabel() }}</span>
                        </td>
                        <td>{{ $user->legacyDepartmentName() ?? '-' }}</td>
                        <td><span class="badge">{{ $user->displayRoleName() }}</span></td>
                        <td>{{ $user->defaultStore?->name ?? '-' }}</td>
                        <td><span class="badge {{ $user->is_active ? 'success' : 'credit' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="action-stack">
                                <a href="{{ route('users.role.edit', $user) }}" class="action-chip">Assign Role</a>
                                <a href="{{ route('users.edit', $user) }}" class="action-chip primary">Edit</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <div class="pagination">{{ $users->links() }}</div>
    </section>
@endsection
