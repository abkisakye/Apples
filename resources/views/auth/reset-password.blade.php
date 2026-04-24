<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Apples</title>
    <style>
        :root { --ink: #203128; --muted: #617167; --brand: #21554a; --line: #d9e0d4; }
        body { margin: 0; font-family: "Trebuchet MS", "Segoe UI", sans-serif; background: linear-gradient(180deg, #f7faf6 0%, #eef1ec 100%); color: var(--ink); }
        .shell { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .card { width: min(520px, 100%); background: white; border: 1px solid var(--line); border-radius: 22px; padding: 28px; box-shadow: 0 16px 36px rgba(30,48,40,.08); }
        h1 { margin: 0 0 8px; }
        p { color: var(--muted); line-height: 1.55; }
        label { display: grid; gap: 8px; margin-bottom: 14px; }
        input { border: 1px solid var(--line); border-radius: 14px; padding: 12px 14px; font: inherit; }
        button { border: 0; border-radius: 14px; padding: 12px 16px; background: var(--brand); color: white; cursor: pointer; width: 100%; font: inherit; font-weight: 700; }
    </style>
</head>
<body>
    <div class="shell">
        <div class="card">
            <h1>Reset Password</h1>
            <p>Create a new password for this account, then return to sign in with the updated login secret.</p>
            <form method="post" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email', $email) }}" required>
                </label>
                <label>
                    <span>New Password</span>
                    <input type="password" name="password" required>
                </label>
                <label>
                    <span>Confirm Password</span>
                    <input type="password" name="password_confirmation" required>
                </label>
                <button type="submit">Reset Password</button>
            </form>
        </div>
    </div>
</body>
</html>
