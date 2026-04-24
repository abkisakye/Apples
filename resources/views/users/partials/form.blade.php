<div class="page-head">
    <div>
        <h2>{{ $title }}</h2>
        <p>Set the user role, default store, and login details.</p>
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
                    <span>Role</span>
                    <select name="role_id" required>
                        <option value="">Select role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-field">
                    <span>Default Store</span>
                    <select name="default_store_id">
                        <option value="">No default store</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected((string) old('default_store_id', $user->default_store_id) === (string) $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-field">
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
            </div>

            <div class="actions">
                <button type="submit">Save User</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h3>Account Guidance</h3>
        <p class="list-note">Keep usernames short and easy to type at the counter. Use role and store assignment to guide what each staff member can see and do.</p>
        <table>
            <tbody>
                <tr>
                    <th style="text-align:left; width:38%;">Recommended Pattern</th>
                    <td>One account per person</td>
                </tr>
                <tr>
                    <th style="text-align:left;">Role Control</th>
                    <td>Choose the closest real workflow role, then fine-tune permissions if needed</td>
                </tr>
                <tr>
                    <th style="text-align:left;">Store Assignment</th>
                    <td>Use a default store where staff mainly work so entry pages feel faster</td>
                </tr>
                <tr>
                    <th style="text-align:left;">Password Note</th>
                    <td>{{ $method === 'put' ? 'Leave password blank to keep the current login secret unchanged.' : 'Set a temporary password, then ask the staff member to change it after first login.' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
