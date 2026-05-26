@extends('layouts.app', ['title' => 'Supplier Payment'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($suppliersPayload = $suppliers->map(fn ($supplier) => [
        'id' => $supplier->id,
        'name' => $supplier->name,
        'country' => $supplier->country,
        'credit' => (float) ($supplier->outstanding_credit ?? 0),
        'search' => strtolower(trim(implode(' ', array_filter([$supplier->name, $supplier->country])))),
    ]))
    @php($purchasesPayload = $outstandingPurchases->map(fn ($purchase) => [
        'id' => $purchase->id,
        'purchase_no' => $purchase->purchase_no,
        'supplier_id' => $purchase->supplier_id,
        'supplier_name' => $purchase->supplier?->name,
        'store_name' => $purchase->store?->name,
        'balance' => (float) $purchase->balance_due,
        'purchase_date' => optional($purchase->purchase_date)->format('Y-m-d'),
        'credit_due_date' => optional($purchase->credit_due_date)->format('Y-m-d'),
        'search' => strtolower(trim(implode(' ', array_filter([$purchase->purchase_no, $purchase->supplier?->name, $purchase->store?->name])))),
    ]))
    <style>
        .payment-shell { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(320px,.9fr); gap:16px; align-items:start; }
        .payment-shell.focused-payment { grid-template-columns:minmax(0,1fr) minmax(320px,.75fr); }
        .payment-stack { display:grid; gap:14px; }
        .summary-card { position:sticky; top:18px; }
        .picker-input { width:100%; min-height:48px; padding:12px 14px; border-radius:14px; border:1px solid var(--line); background:#fff; color:var(--ink); }
        .party-results, .doc-results { display:grid; gap:6px; max-height:210px; overflow-y:auto; margin-top:10px; }
        .pick-card { display:flex; justify-content:space-between; gap:10px; align-items:center; padding:10px 11px; border:1px solid var(--line); border-radius:12px; background:#fff; }
        .pick-card strong { display:block; margin:0 0 2px; }
        .pick-meta { color:var(--muted); font-size:.9rem; line-height:1.4; }
        .picker-empty { padding:12px; border:1px dashed var(--line); border-radius:12px; color:var(--muted); background:var(--panel-soft); font-size:.9rem; }
        .summary-grid { display:grid; gap:10px; }
        .summary-row { display:flex; justify-content:space-between; gap:10px; align-items:center; font-size:.94rem; }
        .summary-row.total { padding-top:12px; border-top:1px solid var(--line); font-weight:700; }
        .focused-purchase-card { display:grid; gap:10px; margin-top:14px; }
        .focused-purchase-line { display:flex; justify-content:space-between; gap:12px; padding:10px 12px; border:1px solid var(--line); border-radius:12px; background:var(--panel-soft); }
        .focused-purchase-line span { color:var(--muted); }
        .footer-actions { display:flex; gap:10px; flex-wrap:wrap; }
        .footer-actions .button-link, .footer-actions button { flex:1 1 180px; justify-content:center; }
        @media (max-width:980px) { .payment-shell { grid-template-columns:1fr; } .summary-card { position:static; } }
        @media (max-width:760px) { .footer-actions .button-link, .footer-actions button { flex-basis:100%; } .picker-input { font-size:16px; } }
    </style>

    <div class="page-head">
        <div>
            <h2>Supplier Payment</h2>
            <p>{{ $selectedPurchase ? 'Record payment for the selected supplier purchase. Partial payments are allowed and the remaining balance updates immediately.' : 'Choose the supplier, pick the open purchase, enter the amount you are paying now, and record the payment. The remaining balance updates immediately.' }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('supplier-payments.index') }}" class="button-link">Payment History</a>
            <a href="{{ route('purchases.index') }}" class="button-link">Back to Purchases</a>
        </div>
    </div>

    <form method="post" action="{{ route('supplier-payments.store') }}" id="supplier-payment-form" class="payment-shell {{ $selectedPurchase ? 'focused-payment' : '' }}">
        @csrf
        @if ($selectedPurchase)
            <input type="hidden" name="supplier_id" id="payment-supplier-id" value="{{ old('supplier_id', $selectedSupplierId) }}">
            <input type="hidden" name="purchase_id" id="payment-purchase-id" value="{{ old('purchase_id', $selectedPurchaseId) }}">
            <section class="panel">
                <h3>Record Supplier Payment</h3>
                <div class="focused-purchase-card">
                    <div class="focused-purchase-line"><span>Supplier</span><strong>{{ $selectedPurchase->supplier?->name }}</strong></div>
                    <div class="focused-purchase-line"><span>Purchase</span><strong>{{ $selectedPurchase->purchase_no }}</strong></div>
                    <div class="focused-purchase-line"><span>Outstanding</span><strong class="money">{{ $currency }} {{ number_format((float) $selectedPurchase->balance_due, 0) }}</strong></div>
                </div>
            </section>
        @else
            <div class="payment-stack summary-card">
                <section class="panel">
                    <h3>1. Choose Supplier</h3>
                    <input type="hidden" name="supplier_id" id="payment-supplier-id" value="{{ old('supplier_id', $selectedSupplierId) }}">
                    <input type="text" id="payment-supplier-search" class="picker-input" placeholder="Search supplier by name or country">
                    <div id="payment-supplier-results" class="party-results"></div>
                </section>

                <section class="panel">
                    <h3>2. Choose Open Purchase</h3>
                    <input type="hidden" name="purchase_id" id="payment-purchase-id" value="{{ old('purchase_id', $selectedPurchaseId) }}">
                    <input type="text" id="payment-purchase-search" class="picker-input" placeholder="Search purchase number or store">
                    <div id="payment-purchase-results" class="doc-results"></div>
                </section>
            </div>
        @endif

        <div class="payment-stack">
            <section class="panel">
                <h3>{{ $selectedPurchase ? 'Payment Details' : '3. Payment Details' }}</h3>
                <div class="form-grid" style="margin-top:14px;">
                    <label class="form-field">
                        <span>Payment Date</span>
                        <input type="date" name="payment_date" id="supplier-payment-date" value="{{ old('payment_date', now()->toDateString()) }}" required>
                    </label>
                    <label class="form-field">
                        <span>Amount Paid</span>
                        <input type="number" step="0.01" min="0.01" name="amount" id="supplier-payment-amount" value="{{ old('amount') }}" @if($selectedPurchase) max="{{ (float) $selectedPurchase->balance_due }}" @endif required>
                    </label>
                    <label class="form-field">
                        <span>Payment Mode</span>
                        <select name="payment_mode_id">
                            <option value="">Choose mode</option>
                            @foreach ($paymentModes as $mode)
                                <option value="{{ $mode->id }}" @selected((string) old('payment_mode_id') === (string) $mode->id)>{{ $mode->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-field">
                        <span>Supplier Invoice No</span>
                        <input type="text" name="supplier_invoice_no" value="{{ old('supplier_invoice_no') }}">
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
                <h3>{{ $selectedPurchase ? 'Payment Summary' : '4. Payment Summary' }}</h3>
                <div class="summary-grid" style="margin-top:14px;">
                    <div class="summary-row"><span>Supplier</span><strong id="supplier-summary">Choose supplier</strong></div>
                    <div class="summary-row"><span>Open Purchase</span><strong id="purchase-summary">Choose purchase</strong></div>
                    <div class="summary-row"><span>Outstanding</span><strong id="supplier-balance-summary">{{ $currency }} 0</strong></div>
                    <div class="summary-row"><span>Amount Entered</span><strong id="supplier-amount-summary">{{ $currency }} 0</strong></div>
                    <div class="summary-row total"><span>Remaining</span><span id="supplier-remaining-summary">{{ $currency }} 0</span></div>
                </div>
                <div class="footer-actions" style="margin-top:14px;">
                    <button type="button" id="supplier-fill-balance" class="button-link">Use Full Balance</button>
                    <button type="submit">Record Payment</button>
                </div>
            </section>
        </div>
    </form>

    <script>
        (() => {
            const currency = @json($currency);
            const suppliers = @json($suppliersPayload);
            const purchases = @json($purchasesPayload);
            const form = document.getElementById('supplier-payment-form');
            if (!form) return;

            const supplierIdInput = document.getElementById('payment-supplier-id');
            const supplierSearch = document.getElementById('payment-supplier-search');
            const supplierResults = document.getElementById('payment-supplier-results');
            const purchaseIdInput = document.getElementById('payment-purchase-id');
            const purchaseSearch = document.getElementById('payment-purchase-search');
            const purchaseResults = document.getElementById('payment-purchase-results');
            const amountInput = document.getElementById('supplier-payment-amount');

            function money(value) { return `${currency} ${Number(value || 0).toLocaleString()}`; }
            function currentSupplier() { return suppliers.find((item) => String(item.id) === String(supplierIdInput.value)); }
            function availablePurchases() { const id = String(supplierIdInput.value || ''); return id ? purchases.filter((item) => String(item.supplier_id) === id) : []; }
            function currentPurchase() { return availablePurchases().find((item) => String(item.id) === String(purchaseIdInput.value)); }

            function renderSuppliers() {
                if (!supplierSearch || !supplierResults) return;
                const selectedId = String(supplierIdInput.value || '');
                const needle = String(supplierSearch.value || '').trim().toLowerCase();
                const rows = needle.length < 1 ? suppliers.slice(0, 10) : suppliers.filter((item) => item.search.includes(needle)).slice(0, 12);
                supplierResults.innerHTML = rows.length ? rows.map((supplier) => `
                    <div class="pick-card">
                        <div>
                            <strong>${supplier.name}</strong>
                            <div class="pick-meta">${[supplier.country, supplier.credit > 0 ? `${money(supplier.credit)} credit` : null].filter(Boolean).join(' / ')}</div>
                        </div>
                        <button type="button" class="button-link" data-pick-supplier="${supplier.id}">${selectedId === String(supplier.id) ? 'Selected' : 'Select'}</button>
                    </div>
                `).join('') : `<div class="picker-empty">No supplier matched that search.</div>`;
            }

            function renderPurchases() {
                if (!purchaseSearch || !purchaseResults) return;
                const selectedId = String(purchaseIdInput.value || '');
                const needle = String(purchaseSearch.value || '').trim().toLowerCase();
                const rows = availablePurchases().filter((item) => !needle || item.search.includes(needle));
                purchaseResults.innerHTML = rows.length ? rows.map((purchase) => `
                    <div class="pick-card">
                        <div>
                            <strong>${purchase.purchase_no}</strong>
                            <div class="pick-meta">${[purchase.store_name, money(purchase.balance), purchase.credit_due_date ? `Due ${purchase.credit_due_date}` : null].filter(Boolean).join(' / ')}</div>
                        </div>
                        <button type="button" class="button-link" data-pick-purchase="${purchase.id}">${selectedId === String(purchase.id) ? 'Selected' : 'Select'}</button>
                    </div>
                `).join('') : `<div class="picker-empty">No open purchases match this supplier.</div>`;
            }

            function renderSummary() {
                const supplier = currentSupplier();
                const purchase = currentPurchase();
                const amount = Number(amountInput.value || 0);
                const balance = Number(purchase?.balance || 0);
                document.getElementById('supplier-summary').textContent = supplier ? supplier.name : 'Choose supplier';
                document.getElementById('purchase-summary').textContent = purchase ? purchase.purchase_no : 'Choose purchase';
                document.getElementById('supplier-balance-summary').textContent = money(balance);
                document.getElementById('supplier-amount-summary').textContent = money(amount);
                document.getElementById('supplier-remaining-summary').textContent = money(Math.max(balance - amount, 0));
            }

            supplierSearch?.addEventListener('input', renderSuppliers);
            supplierResults?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-pick-supplier]');
                if (!button) return;
                supplierIdInput.value = button.dataset.pickSupplier;
                const supplier = currentSupplier();
                supplierSearch.value = supplier ? supplier.name : '';
                purchaseIdInput.value = '';
                purchaseSearch.value = '';
                renderSuppliers();
                renderPurchases();
                renderSummary();
            });

            purchaseSearch?.addEventListener('input', renderPurchases);
            purchaseResults?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-pick-purchase]');
                if (!button) return;
                purchaseIdInput.value = button.dataset.pickPurchase;
                const purchase = currentPurchase();
                purchaseSearch.value = purchase ? purchase.purchase_no : '';
                renderPurchases();
                renderSummary();
            });

            amountInput.addEventListener('input', renderSummary);
            document.getElementById('supplier-fill-balance').addEventListener('click', () => {
                const purchase = currentPurchase();
                if (!purchase) return;
                amountInput.value = purchase.balance;
                renderSummary();
            });

            form.addEventListener('submit', (event) => {
                if (!supplierIdInput.value) {
                    event.preventDefault();
                    alert('Choose a supplier before recording the payment.');
                    supplierSearch?.focus();
                    return;
                }
                if (!purchaseIdInput.value) {
                    event.preventDefault();
                    alert('Choose the open purchase before recording the payment.');
                    purchaseSearch?.focus();
                }
            });

            renderSuppliers();
            renderPurchases();
            renderSummary();
            if (supplierIdInput.value) {
                const supplier = currentSupplier();
                if (supplier && supplierSearch) supplierSearch.value = supplier.name;
                renderSuppliers();
                renderPurchases();
            }
            if (purchaseIdInput.value) {
                const purchase = currentPurchase();
                if (purchase && purchaseSearch) purchaseSearch.value = purchase.purchase_no;
                renderPurchases();
                renderSummary();
            }
        })();
    </script>
@endsection
