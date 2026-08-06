<style>
    .profile-form {
        display: grid;
        gap: 16px;
    }
    .form-section-title {
        margin: 0 0 10px;
        font-size: .95rem;
    }
    .field-tip {
        color: var(--muted);
        font-size: .82rem;
        line-height: 1.45;
        margin-top: 2px;
    }
    .side-note {
        display: grid;
        gap: 10px;
    }
    .side-note-card {
        padding: 12px 14px;
        border: 1px solid var(--line);
        border-radius: 14px;
        background: var(--panel-soft);
    }
    .side-note-card strong {
        display: block;
        margin-bottom: 4px;
    }
</style>

<div class="page-head">
    <div>
        <h2>{{ $title }}</h2>
        <p>Maintain named customer accounts here so sales, statements, and payment follow-up stay accurate.</p>
    </div>
    <div class="actions">
        <a href="{{ route('customers.index') }}" class="button-link">Back to Customers</a>
        @if ($customer->exists)
            <a href="{{ route('customers.show', $customer) }}" class="button-link">Customer Profile</a>
        @endif
    </div>
</div>

<section class="grid-two">
    <div class="panel">
        <form method="post" action="{{ $action }}" class="entry-form profile-form">
            @csrf
            @if ($method === 'put')
                @method('put')
            @endif

            <div>
                <h3 class="form-section-title">1. Customer Details</h3>
                <p class="list-note">Use named accounts for regular buyers, offices, schools, and anyone who needs statements or controlled credit.</p>
            </div>
            <div class="form-grid">
                <label class="form-field">
                    <span>Name</span>
                    <input type="text" name="name" value="{{ old('name', $customer->name) }}" required>
                </label>
                <label class="form-field">
                    <span>Phone</span>
                    <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}">
                    <div class="field-tip">Phone helps staff search faster and makes follow-up easier later.</div>
                </label>
                <label class="form-field">
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email', $customer->email) }}">
                </label>
                <label class="form-field">
                    <span>Fax</span>
                    <input type="text" name="fax" value="{{ old('fax', $customer->fax) }}">
                </label>
                <label class="form-field">
                    <span>Location</span>
                    <input type="text" name="location" value="{{ old('location', $customer->location) }}">
                </label>
                <label class="form-field">
                    <span>Address</span>
                    <input type="text" name="address" value="{{ old('address', $customer->address) }}">
                </label>
                <label class="form-field">
                    <span>Customer Type</span>
                    <input type="text" name="customer_type" value="{{ old('customer_type', $customer->customer_type) }}" placeholder="Retail, Wholesale, School, etc.">
                    <div class="field-tip">Use a simple label the business already understands.</div>
                </label>
                <label class="form-field">
                    <span>Opening Balance</span>
                    <input type="number" step="0.01" min="0" name="opening_balance" value="{{ old('opening_balance', $customer->opening_balance ?? 0) }}">
                    <div class="field-tip">Only use this for balances brought in from the old system.</div>
                </label>
                <label class="form-field">
                    <span>Opening Balance Date</span>
                    <input type="date" name="opening_balance_date" value="{{ old('opening_balance_date', optional($customer->opening_balance_date)->toDateString() ?? now()->toDateString()) }}">
                    <div class="field-tip">Use the date the carried debt was already outstanding from the old system.</div>
                </label>
                <label class="form-field">
                    <span>Credit Limit</span>
                    <input type="number" step="0.01" min="0" name="credit_limit" value="{{ old('credit_limit', $customer->credit_limit ?? 0) }}">
                    <div class="field-tip">Use this as the maximum balance once management approves this customer for credit.</div>
                </label>
                <label class="form-field">
                    <span>Credit Sales Approval</span>
                    <select name="allow_credit_sales">
                        <option value="0" @selected(! old('allow_credit_sales', $customer->allow_credit_sales ?? false))>Not approved for credit</option>
                        <option value="1" @selected(old('allow_credit_sales', $customer->allow_credit_sales ?? false))>Approved for credit sales</option>
                    </select>
                    <div class="field-tip">Only approved customers can leave the counter with an unpaid or partly paid sale.</div>
                </label>
                <label class="form-field">
                    <span>Status</span>
                    <select name="is_active">
                        <option value="1" @selected(old('is_active', $customer->is_active ?? true))>Active</option>
                        <option value="0" @selected((string) old('is_active', $customer->is_active ?? true) === '0')>Inactive</option>
                    </select>
                </label>
            </div>

            <label class="form-field">
                <span>Notes</span>
                <textarea name="notes" rows="4">{{ old('notes', $customer->notes) }}</textarea>
            </label>

            <div class="actions">
                <button type="submit">Save Customer</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h3>Customer Setup Notes</h3>
        <div class="side-note">
            <div class="side-note-card">
                <strong>Named account</strong>
                <div class="field-tip">Use this form for real customers who need statements, balances, or repeat sales history. Walk-in stays separate.</div>
            </div>
            <div class="side-note-card">
                <strong>Credit control</strong>
                <div class="field-tip">Approve credit only for trusted named customers. Walk-in and unknown customers cannot use credit sales.</div>
            </div>
        </div>
        <table>
            <tbody>
                <tr><th style="text-align:left; width:38%;">Use Case</th><td>Create named accounts for repeat buyers, credit customers, and institutions.</td></tr>
                <tr><th style="text-align:left;">Opening Balance</th><td>Use this only for amounts brought over from the old system or pre-existing balances.</td></tr>
                <tr><th style="text-align:left;">Credit Approval</th><td>Turn this on only when management has approved the customer for credit sales.</td></tr>
                <tr><th style="text-align:left;">Status</th><td>Inactive accounts stay in history but can be clearly separated from active trading customers.</td></tr>
            </tbody>
        </table>
    </div>
</section>
