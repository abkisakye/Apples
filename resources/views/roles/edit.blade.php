@extends('layouts.app', ['title' => 'Edit Role'])

@section('content')
    <div class="page-head">
        <div>
            <h2>{{ \Illuminate\Support\Str::headline($role->name) }} Permissions</h2>
            <p>Choose which parts of the system this role can open and manage.</p>
        </div>
        <div class="actions">
            <a href="{{ route('roles.index') }}" class="button-link">Back to Roles</a>
            <a href="{{ route('roles.matrix') }}" class="button-link">Permissions Matrix</a>
        </div>
    </div>

    <section class="grid-two">
        <div class="panel">
            <form method="post" action="{{ route('roles.update', $role) }}" class="entry-form">
                @csrf
                @method('put')

                <label class="form-field">
                    <span>Description</span>
                    <input type="text" name="description" value="{{ old('description', $role->description) }}">
                </label>

                @if ($role->name === 'admin')
                    <div class="flash">Admin always keeps full system access.</div>
                @endif

                <p class="list-note">Tick only what this role really needs. Cashier-style roles should stay focused, while manager roles can carry wider reporting and review access.</p>
                <div class="form-grid">
                    @foreach ($availablePermissions as $permission)
                        <label class="form-field" style="padding:12px; border:1px solid #d9e0d4; border-radius:16px; background:#f8faf7;">
                            <span style="display:flex; align-items:center; gap:10px;">
                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    value="{{ $permission }}"
                                    @checked($role->name === 'admin' || in_array($permission, old('permissions', $selectedPermissions), true))
                                    {{ $role->name === 'admin' ? 'disabled' : '' }}
                                >
                                <strong>{{ $permission }}</strong>
                            </span>
                            <span class="muted">{{ \Illuminate\Support\Str::headline(str_replace('.', ' ', $permission)) }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="actions">
                    <button type="submit">Save Permissions</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h3>Role Notes</h3>
            <p class="list-note">Permissions ending in `.view` normally allow opening a screen. Permissions ending in `.manage` normally allow creating, editing, posting, or updating records.</p>
            <table>
                <tbody>
                    <tr>
                        <th style="text-align:left; width: 38%;">Role Name</th>
                        <td>{{ \Illuminate\Support\Str::headline($role->name) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Current Permission Count</th>
                        <td>{{ in_array('*', $selectedPermissions, true) ? 'Full access' : count($selectedPermissions) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Users On This Role</th>
                        <td>{{ number_format($role->users()->count()) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Fast Rule</th>
                        <td>{{ $role->name === 'admin' ? 'Admin always keeps complete access.' : 'Give only the screens this team actually uses.' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection
