@extends('layouts.app', ['title' => 'Open Cash Shift'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Open Cash Shift</h2>
            <p>Start a cashier session with the opening cash so the day can be reconciled properly at closing time.</p>
        </div>
        <div class="actions">
            <a href="{{ route('cash-shifts.index') }}" class="button-link">Back to Shifts</a>
        </div>
    </div>

    <section class="grid-two">
        <div class="panel">
            @if ($activeShift)
                <div class="empty-cart" style="text-align:left; margin-bottom: 14px;">
                    You already have an open shift: <strong>{{ $activeShift->shift_no }}</strong>.
                    <a href="{{ route('cash-shifts.show', $activeShift) }}">Open it here</a>.
                </div>
            @endif

            <form method="post" action="{{ route('cash-shifts.store') }}" class="entry-form">
                @csrf
                <div class="form-grid">
                    <label class="form-field">
                        <span>Store</span>
                        <select name="store_id">
                            <option value="">Use my default store</option>
                            @foreach ($stores as $store)
                                <option value="{{ $store->id }}" @selected((string) old('store_id', $defaultStoreId) === (string) $store->id)>{{ $store->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-field">
                        <span>Opened At</span>
                        <input type="datetime-local" name="opened_at" value="{{ old('opened_at', now()->format('Y-m-d\TH:i')) }}" required>
                    </label>
                    <label class="form-field">
                        <span>Opening Cash</span>
                        <input type="number" step="0.01" min="0" name="opening_balance" value="{{ old('opening_balance', 0) }}" required>
                    </label>
                </div>

                <label class="form-field">
                    <span>Opening Notes</span>
                    <textarea name="opening_notes" rows="4" placeholder="Optional note about the opening drawer cash">{{ old('opening_notes') }}</textarea>
                </label>

                <div class="actions">
                    <button type="submit">Open Shift</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h3>What this controls</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left;">Opening balance</th><td>The actual starting cash in the drawer.</td></tr>
                    <tr><th style="text-align:left;">Expected cash</th><td>Opening cash plus cash received minus cash expenses.</td></tr>
                    <tr><th style="text-align:left;">Difference</th><td>Counted cash minus expected cash at shift close.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection
