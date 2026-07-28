<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Apples</title>
    <style>
        :root {
            --ink: #2f2616;
            --muted: #6d6554;
            --brand: #066838;
            --brand-strong: #04512c;
            --line: #e3dcc7;
            --panel: #ffffff;
            --accent: #d4af37;
            --accent-ink: #5e4500;
            --accent-soft: #fbf1cf;
            --apple: #662828;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(212, 175, 55, .14), transparent 24%),
                linear-gradient(180deg, #fffdf8 0%, #f7f3e8 100%);
            color: var(--ink);
        }
        .shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .card {
            width: min(1100px, 100%);
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(340px, .85fr);
            background: rgba(255,255,255,.94);
            border: 1px solid var(--line);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 26px 54px rgba(47, 38, 22, .10);
        }
        .hero {
            position: relative;
            min-height: 100%;
            padding: 34px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #fffef8;
            background:
                linear-gradient(180deg, rgba(6, 104, 56, .30), rgba(4, 81, 44, .84)),
                url('{{ asset('brand/apples-banner-storefront.png') }}') center/cover no-repeat;
        }
        .hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(135deg, rgba(6, 104, 56, .90), rgba(6, 104, 56, .35) 48%, rgba(4, 81, 44, .88)),
                linear-gradient(0deg, rgba(0,0,0,.16), rgba(0,0,0,.16));
        }
        .hero > * {
            position: relative;
            z-index: 1;
        }
        .hero-lockup {
            display: grid;
            gap: 14px;
        }
        .hero-logo {
            max-width: min(520px, 100%);
            max-height: 110px;
            object-fit: contain;
            filter: drop-shadow(0 12px 24px rgba(0, 0, 0, .22));
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.16);
            color: #fff6d7;
            font-size: .84rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .hero h1 {
            margin: 0;
            font-size: 2.1rem;
            line-height: 1.05;
        }
        .hero p {
            max-width: 640px;
            color: rgba(255,255,255,.92);
            line-height: 1.62;
            font-size: 1rem;
        }
        .hero-grid {
            display: grid;
            gap: 12px;
            margin-top: 26px;
        }
        .hero-card {
            padding: 14px 16px;
            border-radius: 18px;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.12);
            backdrop-filter: blur(4px);
        }
        .hero-card strong {
            display: block;
            margin-bottom: 6px;
            color: #fff0bf;
        }
        .form-area {
            padding: 34px;
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(251,248,239,.96));
        }
        .form-logo {
            max-width: 240px;
            max-height: 60px;
            object-fit: contain;
            margin-bottom: 18px;
        }
        .form-area h2 {
            margin: 0 0 8px;
            font-size: 1.7rem;
        }
        .form-area p {
            color: var(--muted);
            line-height: 1.58;
        }
        label {
            display: grid;
            gap: 8px;
            margin-bottom: 14px;
            font-weight: 600;
        }
        input {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 14px;
            font: inherit;
            background: #fff;
        }
        input:focus {
            outline: 2px solid rgba(212, 175, 55, .18);
            border-color: #ceb56b;
        }
        button {
            border: 0;
            border-radius: 14px;
            padding: 12px 16px;
            background: linear-gradient(135deg, var(--accent), #ba9324);
            color: var(--accent-ink);
            cursor: pointer;
            width: 100%;
            font: inherit;
            font-weight: 700;
            box-shadow: 0 12px 24px rgba(212, 175, 55, .20);
        }
        .error {
            background: var(--accent-soft);
            border: 1px solid #e0ca8a;
            color: #7b611a;
            border-radius: 16px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }
        .link-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid var(--line);
            color: var(--ink);
            text-decoration: none;
            font-weight: 700;
            background: #fff;
        }
        .footer-note {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: .92rem;
            line-height: 1.55;
        }
        @media (max-width: 920px) {
            .card {
                grid-template-columns: 1fr;
            }
            .hero,
            .form-area {
                padding: 24px;
            }
            .hero-logo {
                max-height: 84px;
            }
            .hero h1 {
                font-size: 1.72rem;
            }
        }
    </style>
</head>
<body>
    @php($businessName = config('business.name', 'Apples Of Gold'))
    @php($businessTagline = config('business.tagline', 'Freshness & Value Every Day'))
    @php($businessLogo = config('business.logo_url'))
    <div class="shell">
        <div class="card">
            <div class="hero">
                <div class="hero-lockup">
                    <div class="hero-badge">Apples Of Gold Workspace</div>
                    @if ($businessLogo)
                        <img src="{{ $businessLogo }}" alt="{{ $businessName }} logo" class="hero-logo">
                    @endif
                    <h1>Wholesale & Retail Management System</h1>
                    <p>Run counter sales, bulk sales, carton and piece stock control, customer credit, supplier purchases, and owner reporting in one workspace built for Apples Of Gold.</p>
                </div>
                <div class="hero-grid">
                    <div class="hero-card">
                        <strong>Wholesale-ready stock</strong>
                        <span>Handle cartons, sacks, boxes, dozens, pieces, kg, and other selling packs without confusing duplicate product records.</span>
                    </div>
                    <div class="hero-card">
                        <strong>Fast sales desk</strong>
                        <span>Search products, choose the right pack, take payment, and print clean branded receipts at the counter.</span>
                    </div>
                    <div class="hero-card">
                        <strong>Owner visibility</strong>
                        <span>Track daily sales, supplier purchases, low stock, credit customers, and staff activity from the same system.</span>
                    </div>
                </div>
            </div>

            <div class="form-area">
                @if ($businessLogo)
                    <img src="{{ $businessLogo }}" alt="{{ $businessName }} logo" class="form-logo">
                @endif
                <h2>Sign In</h2>
                <p>Use your staff account to open {{ $businessName }}. Accounts are controlled by role, so each user sees only the work that matters to them.</p>

                @if ($errors->any())
                    <div class="error">{{ $errors->first() }}</div>
                @endif

                <form method="post" action="{{ route('login.attempt') }}">
                    @csrf
                    <label>
                        <span>Username or Email</span>
                        <input type="text" name="login" value="{{ old('login') }}" required>
                    </label>
                    <label>
                        <span>Password</span>
                        <input type="password" name="password" required>
                    </label>
                    <button type="submit">Sign In</button>
                </form>

                <div class="actions">
                    <a href="{{ route('password.request') }}" class="link-chip">Forgot Password?</a>
                </div>

                <div class="footer-note">
                    Sign in with your assigned staff account. If you cannot access the system, contact the person managing setup and user accounts.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
