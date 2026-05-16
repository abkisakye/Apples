@extends('layouts.app', ['title' => 'Business Settings'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Business Settings</h2>
            <p>Update the business identity shown in the sidebar and on printed receipts, invoices, and statements.</p>
        </div>
    </div>

    <section class="grid-two">
        <div class="panel">
            <form method="post" action="{{ route('settings.business.update') }}" class="entry-form" enctype="multipart/form-data">
                @csrf

                <div class="form-grid">
                    <label class="form-field">
                        <span>Business Name</span>
                        <input type="text" name="name" value="{{ old('name', $settings['name']) }}" required>
                    </label>
                    <label class="form-field">
                        <span>Tagline</span>
                        <input type="text" name="tagline" value="{{ old('tagline', $settings['tagline']) }}">
                    </label>
                    <label class="form-field">
                        <span>Phone</span>
                        <input type="text" name="phone" value="{{ old('phone', $settings['phone']) }}">
                    </label>
                    <label class="form-field">
                        <span>Email</span>
                        <input type="email" name="email" value="{{ old('email', $settings['email']) }}">
                    </label>
                    <label class="form-field">
                        <span>TIN</span>
                        <input type="text" name="tin" value="{{ old('tin', $settings['tin']) }}">
                    </label>
                    <label class="form-field">
                        <span>Currency</span>
                        <input type="text" name="currency" value="{{ old('currency', $settings['currency']) }}" required>
                    </label>
                    <label class="form-field">
                        <span>Cashier Discount Limit</span>
                        <input type="number" step="0.01" min="0" name="cashier_discount_limit" value="{{ old('cashier_discount_limit', $settings['cashier_discount_limit']) }}">
                    </label>
                    <label class="form-field">
                        <span>Admin Approval PIN</span>
                        <input type="password" name="admin_approval_pin" value="" placeholder="Leave blank to keep the current approval PIN">
                    </label>
                </div>

                <label class="form-field">
                    <span>Address</span>
                    <textarea name="address" rows="3">{{ old('address', $settings['address']) }}</textarea>
                </label>

                <label class="form-field">
                    <span>Business Logo</span>
                    <input type="file" name="logo" accept="image/*">
                </label>

                @if (! empty($settings['logo_url']))
                    <label class="form-field" style="display:flex; align-items:center; gap:10px;">
                        <input type="checkbox" name="clear_logo" value="1">
                        <span>Remove current logo</span>
                    </label>
                @endif

                <label class="form-field">
                    <span>Receipt Footer</span>
                    <textarea name="receipt_footer" rows="3">{{ old('receipt_footer', $settings['receipt_footer']) }}</textarea>
                </label>

                <label class="form-field">
                    <span>Invoice Footer</span>
                    <textarea name="invoice_footer" rows="3">{{ old('invoice_footer', $settings['invoice_footer']) }}</textarea>
                </label>

                <label class="form-field">
                    <span>Statement Footer</span>
                    <textarea name="statement_footer" rows="3">{{ old('statement_footer', $settings['statement_footer']) }}</textarea>
                </label>

                <div class="actions">
                    <button type="submit">Save Business Settings</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h3>Preview Notes</h3>
            <p class="list-note">These settings affect the application shell and the printable business documents.</p>

            @if (! empty($settings['logo_url']))
                <div style="padding:14px; border:1px solid #d9e0d4; border-radius:16px; background:#f8faf7; margin-bottom:16px;">
                    <div class="muted" style="margin-bottom:10px;">Current Logo</div>
                    <img src="{{ $settings['logo_url'] }}" alt="Business logo" style="max-width: 180px; max-height: 90px; object-fit: contain;">
                </div>
            @endif

            <table>
                <tbody>
                    <tr>
                        <th style="text-align:left; width: 42%;">Sidebar Brand</th>
                        <td>Shows the business name, tagline, and logo when available.</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Receipt Footer</th>
                        <td>Appears on cash sale receipts.</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Invoice Footer</th>
                        <td>Appears on credit sale invoices.</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Statement Footer</th>
                        <td>Appears on customer and supplier statements.</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Cashier Discount Limit</th>
                        <td>Any discount above this amount will ask for the admin approval PIN.</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Admin Approval PIN</th>
                        <td>Used when a sales person needs approval for sensitive sales actions while the admin is away. The current PIN is kept hidden.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection
