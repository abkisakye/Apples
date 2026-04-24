<div class="page-head">
    <div>
        <h2>{{ $title }}</h2>
        <p>Maintain supplier accounts here so purchases, statements, and supplier payments stay accurate and easy to follow.</p>
    </div>
    <div class="actions">
        <a href="{{ route('suppliers.index') }}" class="button-link">Back to Suppliers</a>
        @if ($supplier->exists)
            <a href="{{ route('suppliers.show', $supplier) }}" class="button-link">Supplier Profile</a>
        @endif
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
                    <input type="text" name="name" value="{{ old('name', $supplier->name) }}" required>
                </label>
                <label class="form-field">
                    <span>Phone</span>
                    <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}">
                </label>
                <label class="form-field">
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email', $supplier->email) }}">
                </label>
                <label class="form-field">
                    <span>TIN</span>
                    <input type="text" name="tin" value="{{ old('tin', $supplier->tin) }}">
                </label>
                <label class="form-field">
                    <span>Country</span>
                    <input type="text" name="country" value="{{ old('country', $supplier->country) }}">
                </label>
                <label class="form-field">
                    <span>Postal Code</span>
                    <input type="text" name="postal_code" value="{{ old('postal_code', $supplier->postal_code) }}">
                </label>
                <label class="form-field">
                    <span>Supplier Type</span>
                    <input type="text" name="supplier_type" value="{{ old('supplier_type', $supplier->supplier_type) }}" placeholder="Wholesaler, distributor, farmer, etc.">
                </label>
                <label class="form-field">
                    <span>Payment Terms (days)</span>
                    <input type="number" min="0" max="3650" name="payment_terms_days" value="{{ old('payment_terms_days', $supplier->payment_terms_days) }}">
                </label>
                <label class="form-field">
                    <span>Opening Balance</span>
                    <input type="number" step="0.01" min="0" name="opening_balance" value="{{ old('opening_balance', $supplier->opening_balance ?? 0) }}">
                </label>
                <label class="form-field">
                    <span>Status</span>
                    <select name="is_active">
                        <option value="1" @selected(old('is_active', $supplier->is_active ?? true))>Active</option>
                        <option value="0" @selected((string) old('is_active', $supplier->is_active ?? true) === '0')>Inactive</option>
                    </select>
                </label>
            </div>

            <label class="form-field">
                <span>Address</span>
                <input type="text" name="address" value="{{ old('address', $supplier->address) }}">
            </label>

            <label class="form-field">
                <span>Notes</span>
                <textarea name="notes" rows="4">{{ old('notes', $supplier->notes) }}</textarea>
            </label>

            <div class="actions">
                <button type="submit">Save Supplier</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h3>Supplier Setup Notes</h3>
        <table>
            <tbody>
                <tr><th style="text-align:left; width:38%;">Use Case</th><td>Create named supplier accounts for wholesalers, distributors, and other stock sources you buy from regularly.</td></tr>
                <tr><th style="text-align:left;">Opening Balance</th><td>Use this only for amounts still owed from the old system or before this app started.</td></tr>
                <tr><th style="text-align:left;">Payment Terms</th><td>Capture the usual supplier credit terms so the team can understand expected due periods.</td></tr>
                <tr><th style="text-align:left;">Status</th><td>Inactive suppliers remain in history but stop being treated as normal active suppliers for new work.</td></tr>
            </tbody>
        </table>
    </div>
</section>
