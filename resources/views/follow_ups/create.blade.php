@extends('layouts.app', ['title' => 'Create Follow-up'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Create Follow-up</h2>
            <p>Schedule the next reminder for an overdue sale or purchase so the team knows who should follow up and when.</p>
        </div>
        <div class="actions">
            <a href="{{ route('follow-ups.index') }}" class="button-link">Back to Follow-ups</a>
        </div>
    </div>

    <section class="grid-two">
        <div class="panel">
            <form method="post" action="{{ route('follow-ups.store') }}" class="entry-form">
                @csrf

                <div class="form-grid">
                    <label class="form-field">
                        <span>Overdue Sale</span>
                        <select name="sale_id">
                            <option value="">Select customer sale</option>
                            @foreach ($sales as $sale)
                                <option value="{{ $sale->id }}" @selected((string) old('sale_id', $selectedSaleId) === (string) $sale->id)>{{ $sale->sale_no }} - {{ $sale->customer?->name }} - Bal {{ $currency }} {{ number_format((float) $sale->balance_due, 0) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-field">
                        <span>Overdue Purchase</span>
                        <select name="purchase_id">
                            <option value="">Select supplier purchase</option>
                            @foreach ($purchases as $purchase)
                                <option value="{{ $purchase->id }}" @selected((string) old('purchase_id', $selectedPurchaseId) === (string) $purchase->id)>{{ $purchase->purchase_no }} - {{ $purchase->supplier?->name }} - Bal {{ $currency }} {{ number_format((float) $purchase->balance_due, 0) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-field">
                        <span>Assigned User</span>
                        <select name="assigned_user_id">
                            <option value="">Auto-assign to me</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) old('assigned_user_id') === (string) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-field">
                        <span>Reminder Date</span>
                        <input type="date" name="reminder_date" value="{{ old('reminder_date', now()->toDateString()) }}" required>
                    </label>
                    <label class="form-field">
                        <span>Channel</span>
                        <input type="text" name="channel" value="{{ old('channel', 'Phone Call') }}">
                    </label>
                </div>

                <label class="form-field">
                    <span>Notes</span>
                    <textarea name="notes" rows="4">{{ old('notes') }}</textarea>
                </label>

                <div class="actions">
                    <button type="submit">Save Follow-up</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h3>Simple Follow-up Guide</h3>
            <p class="list-note">Choose either an overdue customer sale or an overdue supplier purchase, not both. The reminder will then appear on the follow-up list for action.</p>
            <table>
                <tbody>
                    <tr>
                        <th style="text-align:left; width: 40%;">When to use</th>
                        <td>When a balance is overdue and someone needs to call, email, or follow up in person.</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Assigned user</th>
                        <td>Leave blank to assign it to yourself automatically.</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Channel</th>
                        <td>Examples: Phone Call, Email, SMS, Office Visit.</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Notes</th>
                        <td>Use notes for promises made, agreed dates, or the reason for delay.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection
