@extends('layouts.app', ['title' => 'Assign User Role'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Assign Role</h2>
            <p>Update the role for {{ $user->name }} without changing the rest of the account details.</p>
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

                <label class="form-field">
                    <span>Role</span>
                    <select name="role_id" required>
                        <option value="">Select role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>{{ \Illuminate\Support\Str::headline($role->name) }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="actions">
                    <button type="submit">Save Role</button>
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
                        <th style="text-align:left;">Current Role</th>
                        <td>{{ $user->displayRoleName() }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Default Store</th>
                        <td>{{ $user->defaultStore?->name ?? '-' }}</td>
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
