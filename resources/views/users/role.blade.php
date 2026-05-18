@extends('layouts.app', ['title' => 'Assign User Role'])

@section('content')
    @php($selectedRoleIds = collect(old('role_ids', $user->roles->pluck('id')->all() ?: ($user->role_id ? [$user->role_id] : [])))->map(fn ($id) => (string) $id)->all())
    <style>
        .role-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
        }
        .role-option {
            display: flex;
            gap: 10px;
            align-items: start;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--panel-soft);
        }
        .role-option input {
            width: auto;
            margin-top: 2px;
        }
        .role-option strong {
            display: block;
            margin-bottom: 2px;
        }
    </style>
    <div class="page-head">
        <div>
            <h2>Assign Roles</h2>
            <p>Update the roles for {{ $user->name }} without changing the rest of the account details.</p>
        </div>
        <div class="actions">
            <a href="{{ route('users.index') }}" class="button-link">Back to Users</a>
            <a href="{{ route('users.edit', $user) }}" class="button-link">Full Edit</a>
        </div>
    </div>

    <section class="grid-two">
        <div class="panel">
            <form method="post" action="{{ route('users.role.update', $user) }}" class="entry-form">
                @csrf
                @method('put')

                <div class="form-field">
                    <span>Roles</span>
                    <div class="role-grid">
                        @foreach ($roles as $role)
                            <label class="role-option">
                                <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(in_array((string) $role->id, $selectedRoleIds, true))>
                                <div>
                                    <strong>{{ \Illuminate\Support\Str::headline($role->name) }}</strong>
                                    <div class="list-note">Tick every role this user should combine in daily work.</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="actions">
                    <button type="submit">Save Roles</button>
                    <a href="{{ route('roles.matrix') }}" class="button-link">Open Matrix</a>
                </div>
            </form>
        </div>

        <div class="panel">
            <h3>User Summary</h3>
            <table>
                <tbody>
                    <tr>
                        <th style="text-align:left; width: 38%;">Name</th>
                        <td>{{ $user->name }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Username</th>
                        <td>{{ $user->username ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Current Roles</th>
                        <td>{{ $user->displayRoleName() }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Shop</th>
                        <td>{{ $user->defaultStore?->name ?? config('business.name', 'Apples Of Gold') }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Status</th>
                        <td>{{ $user->is_active ? 'Active' : 'Inactive' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection
