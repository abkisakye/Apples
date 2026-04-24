@extends('layouts.app', ['title' => 'Change Password'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Change Password</h2>
            <p>Update your current password for daily system access.</p>
        </div>
    </div>

    <section class="panel">
        <form method="post" action="{{ route('password.change.update') }}" class="entry-form">
            @csrf
            <div class="form-grid">
                <label class="form-field">
                    <span>Current Password</span>
                    <input type="password" name="current_password" required>
                </label>
                <label class="form-field">
                    <span>New Password</span>
                    <input type="password" name="password" required>
                </label>
                <label class="form-field">
                    <span>Confirm New Password</span>
                    <input type="password" name="password_confirmation" required>
                </label>
            </div>
            <div class="actions">
                <button type="submit">Update Password</button>
            </div>
        </form>
    </section>
@endsection
