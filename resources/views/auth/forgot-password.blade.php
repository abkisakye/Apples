<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Apples</title>
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
        .notice { background: #e3f3ea; border: 1px solid #bfe1cd; color: #1f7a4d; border-radius: 16px; padding: 12px 14px; margin-bottom: 14px; }
        .link-row { margin-top: 16px; }
    </style>
</head>
<body>
    <div class="shell">
        <div class="card">
            <h1>Forgot Password</h1>
            <p>Enter your username or email and the system will prepare a reset link.</p>

            @if (session('status'))
                <div class="notice">
                    {{ session('status') }}
                    @if (session('reset_link_preview'))
                        <div style="margin-top:8px;">Local preview: <a href="{{ session('reset_link_preview') }}">{{ session('reset_link_preview') }}</a></div>
                    @endif
                </div>
            @endif

            <form method="post" action="{{ route('password.email') }}">
                @csrf
                <label>
                    <span>Username or Email</span>
                    <input type="text" name="login" value="{{ old('login') }}" required>
                </label>
                <button type="submit">Send Reset Link</button>
            </form>

            <div class="link-row"><a href="{{ route('login') }}">Back to sign in</a></div>
        </div>
    </div>
</body>
</html>
