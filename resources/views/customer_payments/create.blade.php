@extends('layouts.app', ['title' => 'Customer Payment'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($customersPayload = $customers->map(fn ($customer) => [
        'id' => $customer->id,
        'name' => $customer->name,
        'location' => $customer->location,
        'credit' => (float) ($customer->outstanding_credit ?? 0),
        'search' => strtolower(trim(implode(' ', array_filter([$customer->name, $customer->location])))),
    ]))
    @php($salesPayload = $outstandingSales->map(fn ($sale) => [
        'id' => $sale->id,
        'sale_no' => $sale->sale_no,
        'customer_id' => $sale->customer_id,
        'customer_name' => $sale->customer?->name,
        'store_name' => $sale->store?->name,
        'balance' => (float) $sale->balance_due,
        'sale_date' => optional($sale->sale_date)->format('Y-m-d'),
        'credit_due_date' => optional($sale->credit_due_date)->format('Y-m-d'),
        'search' => strtolower(trim(implode(' ', array_filter([$sale->sale_no, $sale->customer?->name, $sale->store?->name])))),
    ]))
    <style>
        .payment-shell { display:grid; grid-template-columns:minmax(0,1.15fr) minmax(300px,.85fr); gap:12px; align-items:start; }
        .payment-stack { display:grid; gap:12px; }
        .picker-input { width:100%; min-height:44px; padding:10px 12px; border-radius:12px; border:1px solid var(--line); background:#fff; color:var(--ink); font-size:.88rem; }
        .party-results, .doc-results { display:grid; gap:6px; max-height:180px; overflow-y:auto; margin-top:8px; }
        .pick-card { display:flex; justify-content:space-between; gap:10px; align-items:center; padding:9px 10px; border:1px solid var(--line); border-radius:12px; background:#fff; }
        .pick-card strong { display:block; margin:0 0 2px; }
        .pick-meta { color:var(--muted); font-size:.8rem; line-height:1.35; }
        .picker-empty { padding:10px; border:1px dashed var(--line); border-radius:12px; color:var(--muted); background:var(--panel-soft); font-size:.84rem; }
        .summary-grid { display:grid; gap:10px; }
        .summary-row { display:flex; justify-content:space-between; gap:12px; align-items:center; }
        .summary-row.total { padding-top:10px; border-top:1px solid var(--line); font-weight:700; }
        .payment-shell .panel {
            padding: 10px;
        }
        .payment-shell .form-grid {
            gap: 8px;
        }
        .payment-shell .form-field input,
        .payment-shell .form-field select,
        .payment-shell .form-field textarea {
            font-size: .86rem;
        }
        @media (max-width:980px) { .payment-shell { grid-template-columns:1fr; } }
    </style>

    <div class="page-head">
        <div>
            <h2>Customer Payment</h2>
            <p>Choose the customer, pick the open sale, enter the amount received, and record the payment. The remaining balance updates automatically.</p>
        </div>
        <div class="actions">
            <a href="{{ route('customer-payments.index') }}" class="button-link">Payment History</a>
            <a href="{{ route('sales.index') }}" class="button-link">Back to Sales</a>
        </div>
    </div>

    <form method="post" action="{{ route('customer-payments.store') }}" id="customer-payment-form" class="payment-shell">
        @csrf
        <div class="payment-stack">
            <section class="panel">
                <h3>1. Choose Customer</h3>
                <input type="hidden" name="customer_id" id="payment-customer-id" value="{{ old('customer_id', request('customer_id')) }}">
                <input type="text" id="payment-customer-search" class="picker-input" placeholder="Search customer by name or location">
                <div id="payment-customer-results" class="party-results"></div>
            </section>

            <section class="panel">
                <h3>2. Choose Open Sale</h3>
                <input type="hidden" name="sale_id" id="payment-sale-id" value="{{ old('sale_id') }}">
                <input type="text" id="payment-sale-search" class="picker-input" placeholder="Search sale number or store">
                <div id="payment-sale-results" class="doc-results"></div>
            </section>
        </div>

        <div class="payment-stack">
            <section class="panel">
                <h3>3. Payment Details</h3>
                <div class="form-grid" style="margin-top:14px;">
                    <label class="form-field">
                        <span>Payment Date</span>
                        <input type="date" name="payment_date" id="payment-date" value="{{ old('payment_date', now()->toDateString()) }}" required>
                    </label>
                    <label class="form-field">
                        <span>Amount Received</span>
                        <input type="number" step="0.01" min="0.01" name="amount" id="payment-amount" value="{{ old('amount') }}" required>
                    </label>
                    <label class="form-field">
                        <span>Payment Mode</span>
                        <select name="payment_mode_id">
                            <option value="">Optional</option>
                            @foreach ($paymentModes as $mode)
                                <option value="{{ $mode->id }}" @selected((string) old('payment_mode_id') === (string) $mode->id)>{{ $mode->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-field">
                        <span>Reference No</span>
                        <input type="text" name="reference_no" value="{{ old('reference_no') }}">
                    </label>
                    <label class="form-field">
                        <span>Cheque Number</span>
                        <input type="text" name="cheque_number" value="{{ old('cheque_number') }}">
                    </label>
                </div>
                <label class="form-field" style="margin-top:12px;">
                    <span>Remarks</span>
                    <textarea name="remarks" rows="3">{{ old('remarks') }}</textarea>
                </label>
            </section>

            <section class="panel">
                <h3>4. Payment Summary</h3>
                <div class="summary-grid" style="margin-top:14px;">
                    <div class="summary-row"><span>Customer</span><strong id="summary-customer">Choose customer</strong></div>
                    <div class="summary-row"><span>Open Sale</span><strong id="summary-sale">Choose sale</strong></div>
                    <div class="summary-row"><span>Outstanding</span><strong id="summary-balance">{{ $currency }} 0</strong></div>
                    <div class="summary-row"><span>Amount Entered</span><strong id="summary-amount">{{ $currency }} 0</strong></div>
                    <div class="summary-row total"><span>Remaining</span><span id="summary-remaining">{{ $currency }} 0</span></div>
                </div>
                <div class="footer-actions" style="margin-top:14px;">
                    <button type="button" id="payment-fill-balance" class="button-link">Use Full Balance</button>
                    <button type="submit">Record Payment</button>
                </div>
            </section>
        </div>
    </form>

    <script>
        (() => {
            const currency = @json($currency);
            const customers = @json($customersPayload);
            const sales = @json($salesPayload);
            const form = document.getElementById('customer-payment-form');
            if (!form) return;

            const customerIdInput = document.getElementById('payment-customer-id');
            const customerSearch = document.getElementById('payment-customer-search');
            const customerResults = document.getElementById('payment-customer-results');
            const saleIdInput = document.getElementById('payment-sale-id');
            const saleSearch = document.getElementById('payment-sale-search');
            const saleResults = document.getElementById('payment-sale-results');
            const amountInput = document.getElementById('payment-amount');

            function money(value) { return `${currency} ${Number(value || 0).toLocaleString()}`; }

            function currentCustomer() { return customers.find((item) => String(item.id) === String(customerIdInput.value)); }
            function availableSales() {
                const id = String(customerIdInput.value || '');
                return id ? sales.filter((item) => String(item.customer_id) === id) : [];
            }
            function currentSale() { return availableSales().find((item) => String(item.id) === String(saleIdInput.value)); }

            function renderCustomers() {
                const selectedId = String(customerIdInput.value || '');
                const needle = String(customerSearch.value || '').trim().toLowerCase();
                const rows = needle.length < 1 ? customers.slice(0, 10) : customers.filter((item) => item.search.includes(needle)).slice(0, 12);
                customerResults.innerHTML = rows.length ? rows.map((customer) => `
                    <div class="pick-card">
                        <div>
                            <strong>${customer.name}</strong>
                            <div class="pick-meta">${[customer.location, customer.credit > 0 ? `${money(customer.credit)} credit` : null].filter(Boolean).join(' / ')}</div>
                        </div>
                        <button type="button" class="button-link" data-pick-customer="${customer.id}">${selectedId === String(customer.id) ? 'Selected' : 'Select'}</button>
                    </div>
                `).join('') : `<div class="picker-empty">No customer matched that search.</div>`;
            }

            function renderSales() {
                const selectedId = String(saleIdInput.value || '');
                const needle = String(saleSearch.value || '').trim().toLowerCase();
                const rows = availableSales().filter((item) => !needle || item.search.includes(needle));
                saleResults.innerHTML = rows.length ? rows.map((sale) => `
                    <div class="pick-card">
                        <div>
                            <strong>${sale.sale_no}</strong>
                            <div class="pick-meta">${[sale.store_name, money(sale.balance), sale.credit_due_date ? `Due ${sale.credit_due_date}` : null].filter(Boolean).join(' / ')}</div>
                        </div>
                        <button type="button" class="button-link" data-pick-sale="${sale.id}">${selectedId === String(sale.id) ? 'Selected' : 'Select'}</button>
                    </div>
                `).join('') : `<div class="picker-empty">No open sales match this customer.</div>`;
            }

            function renderSummary() {
                const customer = currentCustomer();
                const sale = currentSale();
                const amount = Number(amountInput.value || 0);
                const balance = Number(sale?.balance || 0);
                document.getElementById('summary-customer').textContent = customer ? customer.name : 'Choose customer';
                document.getElementById('summary-sale').textContent = sale ? sale.sale_no : 'Choose sale';
                document.getElementById('summary-balance').textContent = money(balance);
                document.getElementById('summary-amount').textContent = money(amount);
                document.getElementById('summary-remaining').textContent = money(Math.max(balance - amount, 0));
            }

            customerSearch.addEventListener('input', renderCustomers);
            customerResults.addEventListener('click', (event) => {
                const button = event.target.closest('[data-pick-customer]');
                if (!button) return;
                customerIdInput.value = button.dataset.pickCustomer;
                const customer = currentCustomer();
                customerSearch.value = customer ? customer.name : '';
                saleIdInput.value = '';
                saleSearch.value = '';
                renderCustomers();
                renderSales();
                renderSummary();
            });

            saleSearch.addEventListener('input', renderSales);
            saleResults.addEventListener('click', (event) => {
                const button = event.target.closest('[data-pick-sale]');
                if (!button) return;
                saleIdInput.value = button.dataset.pickSale;
                const sale = currentSale();
                saleSearch.value = sale ? sale.sale_no : '';
                renderSales();
                renderSummary();
            });

            amountInput.addEventListener('input', renderSummary);
            document.getElementById('payment-fill-balance').addEventListener('click', () => {
                const sale = currentSale();
                if (!sale) return;
                amountInput.value = sale.balance;
                renderSummary();
            });

            form.addEventListener('submit', (event) => {
                if (!customerIdInput.value) {
                    event.preventDefault();
                    alert('Choose a customer before recording the payment.');
                    customerSearch.focus();
                    return;
                }
                if (!saleIdInput.value) {
                    event.preventDefault();
                    alert('Choose the open sale before recording the payment.');
                    saleSearch.focus();
                }
            });

            renderCustomers();
            renderSales();
            renderSummary();
            if (customerIdInput.value) {
                const customer = currentCustomer();
                if (customer) customerSearch.value = customer.name;
                renderCustomers();
                renderSales();
            }
        })();
    </script>
@endsection
