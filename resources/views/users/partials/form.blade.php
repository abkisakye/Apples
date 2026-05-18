@php($selectedRoleIds = collect(old('role_ids', $user->exists ? $user->roles->pluck('id')->all() : ($user->role_id ? [$user->role_id] : [])))->map(fn ($id) => (string) $id)->all())

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
        <h2>{{ $title }}</h2>
        <p>Set the user roles, shop assignment, and login details.</p>
    </div>
    <div class="actions">
        <a href="{{ route('users.index') }}" class="button-link">Back to Users</a>
    </div>
</div>

<section class="grid-two">
    <div class="panel">
        <form method="post" action="{{ $action }}" class="entry-form">
            @csrf
            @if ($method === 'put')
                @method('put')
            @endif

            <div class="form-grid">
                <label class="form-field">
                    <span>Name</span>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                </label>
                <label class="form-field">
                    <span>Username</span>
                    <input type="text" name="username" value="{{ old('username', $user->username) }}" required>
                </label>
                <label class="form-field">
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
                </label>
                <label class="form-field">
                    <span>Shop</span>
                    <select name="default_store_id">
                        <option value="">Apples Of Gold</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected((string) old('default_store_id', $user->default_store_id) === (string) $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-field" style="grid-column: 1 / -1;">
                    <span>Password {{ $method === 'put' ? '(leave blank to keep current)' : '' }}</span>
                    <input type="password" name="password" {{ $method === 'post' ? 'required' : '' }}>
                </label>
                <label class="form-field">
                    <span>Status</span>
                    <select name="is_active">
                        <option value="1" @selected(old('is_active', $user->is_active ?? true))>Active</option>
                        <option value="0" @selected(old('is_active', $user->is_active ?? true) == false)>Inactive</option>
                    </select>
                </label>
                <div class="form-field" style="grid-column: 1 / -1;">
                    <span>Roles</span>
                    <div class="role-grid">
                        @foreach ($roles as $role)
                            <label class="role-option">
                                <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(in_array((string) $role->id, $selectedRoleIds, true))>
                                <div>
                                    <strong>{{ ucfirst(str_replace('_', ' ', $role->name)) }}</strong>
                                    <div class="list-note">Tick every role this user should combine in daily work.</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="actions">
                <button type="submit">Save User</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h3>Account Guidance</h3>
        <p class="list-note">Keep usernames short and easy to type at the counter. Use roles to guide what each staff member can see and do.</p>
        <table>
            <tbody>
                <tr>
                    <th style="text-align:left; width:38%;">Recommended Pattern</th>
                    <td>One account per person</td>
                </tr>
                <tr>
                    <th style="text-align:left;">Role Control</th>
                    <td>Assign one or more roles so one person can safely cover sales, stock, or supervision when needed.</td>
                </tr>
                <tr>
                    <th style="text-align:left;">Shop Assignment</th>
                    <td>This installation uses one shop: Apples Of Gold. Staff transactions are posted there automatically.</td>
                </tr>
                <tr>
                    <th style="text-align:left;">Password Note</th>
                    <td>{{ $method === 'put' ? 'Leave password blank to keep the current login secret unchanged.' : 'Set a temporary password, then ask the staff member to change it after first login.' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
