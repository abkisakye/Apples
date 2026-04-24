@extends('layouts.app', ['title' => 'Permissions Matrix'])

@section('content')
    @php($permissionDescriptions = [
        'dashboard.view' => 'Open the main dashboard and summary cards.',
        'customers.view' => 'Open and search the customer list.',
        'customers.statement' => 'View and print customer account statements.',
        'suppliers.view' => 'Open and search the supplier list.',
        'suppliers.statement' => 'View and print supplier statements.',
        'products.view' => 'Browse products and selling units.',
        'users.manage' => 'Create users and control who can access admin tools.',
        'follow_ups.manage' => 'Create, send, and complete follow-up reminders.',
        'activity_logs.view' => 'Review audit history and staff activity.',
        'business.manage' => 'Change business profile, logo, and print settings.',
        'reports.view' => 'Open aging reports and management exports.',
        'sales.view' => 'Browse sales history and print receipts.',
        'sales.manage' => 'Post new sales and edit the sales workflow.',
        'purchases.view' => 'Browse purchases and print purchase documents.',
        'purchases.manage' => 'Post purchases and manage buying workflow.',
        'capital.view' => 'Open capital input history.',
        'capital.manage' => 'Record new capital entries.',
        'stock.view' => 'See stock balances, reorder, and stock history.',
        'stock.manage' => 'Post transfers and stock adjustments.',
        'customer_payments.manage' => 'Record money received from customers.',
        'supplier_payments.manage' => 'Record money paid to suppliers.',
        'expenses.view' => 'Open expense history and expense summaries.',
        'expenses.manage' => 'Record operating expenses and print expense slips.',
        'cash_shifts.manage' => 'Open, review, and close cashier shifts.',
        'sales.override' => 'Approve sensitive sales actions such as large discounts and closing another user shift.',
    ])
    <div class="page-head">
        <div>
            <h2>Permissions Matrix</h2>
            <p>Manage all role permissions from one grid. Permissions are rows and roles are columns, so you can tick access quickly without opening each role one by one.</p>
        </div>
        <div class="actions">
            <a href="{{ route('roles.index') }}" class="button-link">Back to Roles</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Roles</div><div class="value">{{ number_format($roles->count()) }}</div></div>
        <div class="card"><div class="label">Permissions</div><div class="value">{{ number_format(count($availablePermissions)) }}</div></div>
        <div class="card"><div class="label">Admin</div><div class="value">Full Access</div></div>
    </section>

    <section class="panel">
        <p class="list-note">Admin always keeps full access. Changes here affect what each role can open and manage across the system.</p>

        <form method="post" action="{{ route('roles.matrix.update') }}">
            @csrf

            <div class="actions" style="margin: 14px 0 16px;">
                @foreach ($roles as $role)
                    @continue($role->name === 'admin')
                    <button type="button" class="button-link matrix-toggle-column" data-role="{{ $role->id }}">{{ \Illuminate\Support\Str::headline($role->name) }}: Toggle All</button>
                @endforeach
            </div>

            <div class="table-wrap">
            <table style="min-width: 980px;">
                <thead>
                    <tr>
                        <th style="position: sticky; left: 0; background: var(--panel); z-index: 2; min-width: 260px;">Permission</th>
                        @foreach ($roles as $role)
                            <th style="text-align:center; min-width: 130px;">{{ \Illuminate\Support\Str::headline($role->name) }}<div class="muted" style="margin-top:4px;">{{ number_format($role->users_count) }} users</div></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($availablePermissions as $permission)
                        <tr>
                            <td style="position: sticky; left: 0; background: var(--panel); z-index: 1;">
                                <strong>{{ $permission }}</strong>
                                <div class="muted">{{ \Illuminate\Support\Str::headline(str_replace('.', ' ', $permission)) }}</div>
                                <div class="muted" style="margin-top:4px; font-size:.85rem;">{{ $permissionDescriptions[$permission] ?? 'Controls access to this part of the system.' }}</div>
                            </td>
                            @foreach ($roles as $role)
                                @php($hasPermission = in_array('*', $role->permissionList(), true) || in_array($permission, $role->permissionList(), true))
                                @php($fieldKey = str_replace('.', '_', $permission))
                                <td style="text-align:center;">
                                    <input
                                        type="checkbox"
                                        class="matrix-checkbox"
                                        data-role="{{ $role->id }}"
                                        data-permission="{{ $permission }}"
                                        name="matrix[{{ $role->id }}][{{ $fieldKey }}]"
                                        value="1"
                                        @checked($hasPermission)
                                        {{ $role->name === 'admin' ? 'disabled' : '' }}
                                    >
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit">Save Permissions Matrix</button>
            </div>
        </form>
    </section>

    <script>
        document.querySelectorAll('.matrix-toggle-column').forEach(function (button) {
            button.addEventListener('click', function () {
                var roleId = button.dataset.role;
                var checkboxes = Array.from(document.querySelectorAll('.matrix-checkbox[data-role="' + roleId + '"]'));
                var shouldCheck = checkboxes.some(function (checkbox) {
                    return !checkbox.checked;
                });

                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = shouldCheck;
                });
            });
        });
    </script>
@endsection
