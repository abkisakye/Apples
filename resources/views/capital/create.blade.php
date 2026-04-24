@extends('layouts.app', ['title' => 'Record Capital Input'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Record Capital Input</h2>
            <p>Capture capital introduced into the business and label whether it came from the business itself or from outside.</p>
        </div>
        <div class="actions">
            <a href="{{ route('capital.index') }}" class="button-link">Back to Capital Inputs</a>
        </div>
    </div>

    <section class="panel">
        <form method="post" action="{{ route('capital.store') }}" class="entry-form">
            @csrf

            <div class="form-grid">
                <label class="form-field">
                    <span>Entry Date</span>
                    <input type="date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" required>
                </label>
                <label class="form-field">
                    <span>Store</span>
                    <select name="store_id">
                        <option value="">General / not store-specific</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected((string) old('store_id') === (string) $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-field">
                    <span>Capital Source</span>
                    <select name="capital_source_id" required>
                        <option value="">Select source</option>
                        @foreach ($capitalSources as $source)
                            <option value="{{ $source->id }}" @selected((string) old('capital_source_id') === (string) $source->id)>{{ $source->name }} ({{ str_replace('_', ' ', $source->source_type) }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-field">
                    <span>Origin</span>
                    <select name="source_origin" required>
                        <option value="business_generated" @selected(old('source_origin') === 'business_generated')>From business</option>
                        <option value="external" @selected(old('source_origin', 'external') === 'external')>From outside</option>
                    </select>
                </label>
                <label class="form-field">
                    <span>Payment Mode</span>
                    <select name="payment_mode_id">
                        <option value="">Select payment mode</option>
                        @foreach ($paymentModes as $mode)
                            <option value="{{ $mode->id }}" @selected((string) old('payment_mode_id') === (string) $mode->id)>{{ $mode->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-field">
                    <span>Amount</span>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required>
                </label>
                <label class="form-field">
                    <span>Reference No</span>
                    <input type="text" name="reference_no" value="{{ old('reference_no') }}">
                </label>
            </div>

            <label class="form-field">
                <span>Notes</span>
                <textarea name="notes" rows="4">{{ old('notes') }}</textarea>
            </label>

            <div class="actions">
                <button type="submit">Save Capital Input</button>
            </div>
        </form>
    </section>
@endsection
