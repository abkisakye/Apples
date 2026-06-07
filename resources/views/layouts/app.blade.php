<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Apples' }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('brand/apples-icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('brand/apples-icon.png') }}">
    <meta name="theme-color" content="#066838">
    <style>
        :root {
            --bg: #f7f3e8;
            --panel: #ffffff;
            --panel-soft: #fbf8ef;
            --line: #e3dcc7;
            --line-strong: #d1c08a;
            --ink: #2f2616;
            --muted: #6d6554;
            --brand: #066838;
            --brand-strong: #04512c;
            --brand-soft: #e7f2eb;
            --accent: #d4af37;
            --accent-ink: #5e4500;
            --accent-soft: #fbf1cf;
            --apple: #662828;
            --apple-strong: #4d1d1d;
            --apple-soft: #efe2e2;
            --good: #066838;
            --warn: #8b6513;
            --sidebar: #066838;
            --sidebar-soft: rgba(255, 255, 255, 0.08);
            --shadow: 0 18px 38px rgba(47, 38, 22, 0.08);
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(212, 175, 55, .12), transparent 22%),
                radial-gradient(circle at top right, rgba(6, 104, 56, .06), transparent 18%),
                linear-gradient(180deg, #ffffff 0%, var(--bg) 100%);
        }
        body.nav-open {
            overflow: hidden;
        }
        a { color: inherit; text-decoration: none; }
        .shell {
            display: grid;
            grid-template-columns: 252px minmax(0, 1fr);
            min-height: 100vh;
        }
        .sidebar {
            background:
                radial-gradient(circle at top right, rgba(212, 175, 55, .18), transparent 22%),
                linear-gradient(180deg, #066838 0%, #04512c 100%);
            color: #f4ffef;
            padding: 18px 14px;
            border-right: 1px solid rgba(255,255,255,.10);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(19, 28, 24, 0.42);
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s ease;
            z-index: 45;
        }
        .sidebar-backdrop.is-visible {
            opacity: 1;
            pointer-events: auto;
        }
        .brand {
            position: relative;
            padding: 12px 12px 16px;
            margin-bottom: 10px;
            border-radius: 16px;
            background:
                linear-gradient(135deg, rgba(212, 175, 55, .22), rgba(255,255,255,.05)),
                rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.10);
            overflow: hidden;
        }
        .brand::before {
            content: "";
            position: absolute;
            top: 14px;
            right: 16px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #f5dc83 0%, var(--accent) 48%, #9b7a1e 100%);
            box-shadow: 0 10px 22px rgba(212, 175, 55, .24);
        }
        .brand::after {
            content: "";
            position: absolute;
            top: 10px;
            right: 11px;
            width: 10px;
            height: 7px;
            border-radius: 10px 10px 2px 10px;
            background: #8ab857;
            transform: rotate(-28deg);
        }
        .brand-logo {
            display: block;
            max-width: 148px;
            max-height: 50px;
            margin-bottom: 10px;
            object-fit: contain;
        }
        .brand h1 {
            margin: 0;
            font-size: 1.28rem;
            letter-spacing: .02em;
            color: var(--accent);
            text-shadow: 0 2px 10px rgba(0, 0, 0, .16);
        }
        .brand p {
            margin: 6px 0 0;
            color: #eef5ef;
            line-height: 1.4;
            font-size: .86rem;
        }
        .role-preview {
            margin-bottom: 14px;
            padding: 10px;
            border-radius: 14px;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.08);
        }
        .role-preview label {
            display: block;
            margin-bottom: 6px;
            color: #e5efe7;
            font-size: .82rem;
        }
        .role-preview select, .role-preview button {
            width: 100%;
            margin-top: 6px;
        }
        .nav-section {
            margin-top: 12px;
        }
        .nav-label {
            padding: 0 8px;
            margin-bottom: 6px;
            color: #e5ca74;
            font-size: .74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .nav {
            display: grid;
            gap: 6px;
        }
        .nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: 12px;
            background: var(--sidebar-soft);
            border: 1px solid rgba(255,255,255,.08);
            color: #f0f8ef;
            font-size: .88rem;
            transition: transform .18s ease, border-color .18s ease, background .18s ease;
        }
        .nav a:hover {
            transform: translateX(2px);
            border-color: rgba(212, 175, 55, .34);
        }
        .nav a.active {
            background: linear-gradient(135deg, rgba(212, 175, 55, .18), rgba(255,255,255,.08));
            border-color: rgba(212, 175, 55, .38);
            color: #fff;
        }
        .nav-mark {
            display: inline-grid;
            place-items: center;
            width: 26px;
            height: 26px;
            border-radius: 9px;
            background: rgba(255,255,255,.10);
            font-size: .72rem;
            font-weight: 700;
            flex: 0 0 auto;
        }
        .nav a.active .nav-mark {
            background: linear-gradient(135deg, var(--accent) 0%, #e3c15a 100%);
            color: #4f3a08;
            box-shadow: 0 10px 18px rgba(212, 175, 55, .22);
        }
        .signout-box {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,.08);
        }
        .workspace {
            min-width: 0;
            padding: 12px 14px 16px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            padding: 11px 13px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background:
                linear-gradient(135deg, rgba(251, 241, 207, .92), rgba(255,255,255,.88)),
                rgba(255,255,255,.72);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow);
        }
        .topbar-title {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .topbar-heading {
            min-width: 0;
        }
        .topbar-kicker {
            color: var(--accent-ink);
            font-size: .74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            min-height: 40px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,.86);
            color: var(--brand-strong);
            font-size: 1rem;
            font-weight: 700;
            box-shadow: 0 10px 20px rgba(47, 38, 22, 0.08);
        }
        .topbar-title h2 {
            margin: 5px 0 0;
            font-size: 1.26rem;
            line-height: 1.1;
        }
        .topbar-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .topbar-tools {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .topbar-form {
            margin: 0;
        }
        .topbar-chip {
            padding: 8px 10px;
            border-radius: 12px;
            background: rgba(255,255,255,.84);
            border: 1px solid var(--line);
            color: var(--muted);
            font-size: .82rem;
            white-space: nowrap;
        }
        .topbar-chip-date {
            background: var(--accent-soft);
            border-color: #ead79f;
            color: var(--accent-ink);
        }
        .topbar-chip-role {
            background: var(--brand-soft);
        }
        .topbar-chip-store {
            background: var(--apple-soft);
            border-color: #d7b3b3;
            color: var(--apple);
        }
        .topbar-logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 8px 12px;
            border-radius: 12px;
            border: 1px solid #d7b3b3;
            background: linear-gradient(135deg, #fff, #f5e6e6);
            color: var(--apple-strong);
            font-weight: 700;
            box-shadow: 0 10px 20px rgba(102, 40, 40, 0.08);
        }
        .topbar-logout:hover {
            background: linear-gradient(135deg, #fff7f7, #efd6d6);
        }
        .page {
            max-width: 1260px;
            margin: 0 auto;
        }
        main {
            min-width: 0;
        }
        .page-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: end;
            margin-bottom: 12px;
        }
        .page-head h2 {
            margin: 0;
            font-size: 1.52rem;
            line-height: 1.05;
        }
        .page-head p {
            margin: 6px 0 0;
            color: var(--muted);
            max-width: 720px;
            line-height: 1.42;
            font-size: .92rem;
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }
        .card, .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: visible;
        }
        .card { padding: 12px; }
        .card .label {
            color: var(--muted);
            font-size: .74rem;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .card .value {
            font-size: 1.28rem;
            font-weight: 700;
        }
        .panel { padding: 12px; }
        .panel h3 {
            margin: 0 0 8px;
            font-size: .92rem;
        }
        .grid-two {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
            gap: 12px;
        }
        .filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .filters input, .filters select {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 8px 10px;
            background: white;
            min-width: 160px;
            font-size: .9rem;
        }
        .filters button {
            border: 0;
            border-radius: 12px;
            padding: 8px 12px;
            background: var(--brand);
            color: white;
            cursor: pointer;
        }
        button {
            border: 0;
            border-radius: 12px;
            padding: 8px 12px;
            background: var(--brand);
            color: white;
            cursor: pointer;
            font-size: .9rem;
        }
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .button-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 11px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #ffffff;
            color: var(--ink);
            font-weight: 600;
            font-size: .84rem;
        }
        .button-link.primary {
            background: linear-gradient(135deg, var(--accent) 0%, #ba9324 100%);
            border-color: #c49d2d;
            color: var(--accent-ink);
        }
        .table-wrap {
            overflow-x: auto;
            overflow-y: visible;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 560px;
        }
        th, td {
            text-align: left;
            padding: 8px 7px;
            border-top: 1px solid #ecf0ea;
            vertical-align: top;
        }
        th {
            color: var(--muted);
            font-size: .72rem;
            font-weight: 700;
            border-top: 0;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 700;
            background: var(--brand-soft);
            color: var(--brand);
        }
        .badge.credit {
            background: var(--apple-soft);
            color: var(--apple);
        }
        .badge.success {
            background: #e1f4e8;
            color: var(--good);
        }
        .badge.soft {
            background: var(--panel-soft);
            color: var(--muted);
            border: 1px solid var(--line);
        }
        .muted { color: var(--muted); }
        .money { font-variant-numeric: tabular-nums; }
        .list-note {
            color: var(--muted);
            margin-top: 6px;
            font-size: .82rem;
            line-height: 1.38;
        }
        .table-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            font-weight: 700;
        }
        .table-title + .table-title,
        .table-meta {
            margin-top: 4px;
        }
        .table-meta {
            color: var(--muted);
            font-size: .82rem;
            line-height: 1.38;
        }
        .cell-stack {
            display: grid;
            gap: 4px;
        }
        .status-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        .table-actions,
        .action-stack {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .action-stack {
            align-items: flex-start;
        }
        .action-stack form,
        .table-actions form {
            margin: 0;
        }
        .action-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            font-size: .8rem;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }
        .action-chip.primary {
            background: linear-gradient(135deg, var(--accent) 0%, #ba9324 100%);
            border-color: #c49d2d;
            color: var(--accent-ink);
        }
        .action-chip.accent {
            background: var(--accent-soft);
            border-color: #e3cf93;
            color: var(--accent-ink);
        }
        .action-chip.good {
            background: #e1f4e8;
            border-color: #bfe1cd;
            color: var(--good);
        }
        .action-chip.soft {
            background: var(--panel-soft);
            border-color: var(--line);
            color: var(--ink);
        }
        button.action-chip {
            cursor: pointer;
        }
        .mobile-hide {
            display: table-cell;
        }
        .desktop-hide {
            display: none;
        }
        .table-mobile-friendly table {
            min-width: 0;
        }
        .row-actions-menu {
            position: relative;
            display: inline-block;
            z-index: 50;
        }
        .row-actions-menu[open] {
            z-index: 9998;
        }
        .row-actions-toggle {
            list-style: none;
            user-select: none;
        }
        .row-actions-toggle::-webkit-details-marker {
            display: none;
        }
        .row-actions-toggle::marker {
            display: none;
        }
        .row-actions-toggle .action-chip {
            min-width: 104px;
            justify-content: space-between;
        }
        .row-actions-toggle .caret {
            font-size: .72rem;
            color: var(--muted);
        }
        .row-actions-menu[open] .row-actions-toggle .caret {
            transform: rotate(180deg);
        }
        .row-actions-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 9999;
            min-width: 190px;
            padding: 8px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 18px 36px rgba(47, 38, 22, 0.14);
            display: grid;
            gap: 6px;
        }
        .row-actions-menu[open] .row-actions-dropdown {
            position: fixed;
            top: var(--row-actions-top, auto);
            left: var(--row-actions-left, auto);
            right: auto;
        }
        .row-actions-dropdown form {
            margin: 0;
        }
        .row-action-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            width: 100%;
            padding: 9px 11px;
            border-radius: 12px;
            color: var(--ink);
            font-size: .88rem;
            font-weight: 600;
            background: #fff;
            border: 1px solid transparent;
            text-align: left;
        }
        .row-action-link:hover {
            background: var(--panel-soft);
            border-color: var(--line);
        }
        .row-action-link.primary {
            background: var(--accent-soft);
            border-color: #ead79f;
            color: var(--accent-ink);
        }
        .row-action-link.accent {
            background: var(--accent-soft);
            border-color: #e3cf93;
            color: var(--accent-ink);
        }
        .row-action-link.good {
            background: #e1f4e8;
            border-color: #bfe1cd;
            color: var(--good);
        }
        .row-action-link .meta {
            color: var(--muted);
            font-size: .76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .pagination { margin-top: 16px; }
        .pager {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid var(--line);
        }
        .pager-summary {
            color: var(--muted);
            font-size: .84rem;
        }
        .pager-links {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .pager-link,
        .pager-gap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            height: 38px;
            padding: 0 12px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            font-weight: 600;
            font-size: .84rem;
        }
        .pager-link.is-active {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
        }
        .pager-link.is-disabled {
            background: #f3f6f2;
            color: #96a39b;
            border-color: #dde5d8;
            pointer-events: none;
        }
        .pager-gap {
            min-width: auto;
            background: transparent;
            border-color: transparent;
            color: var(--muted);
            padding: 0 6px;
        }
        .flash {
            margin-bottom: 14px;
            padding: 12px 14px;
            border-radius: 14px;
            background: var(--brand-soft);
            color: var(--good);
            border: 1px solid #bcd7c7;
            font-size: .9rem;
        }
        .error-list {
            margin: 0 0 14px;
            padding: 12px 14px;
            border-radius: 14px;
            background: var(--accent-soft);
            border: 1px solid #e5d3a0;
            color: var(--warn);
            font-size: .9rem;
        }
        .entry-form { display: grid; gap: 12px; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
        }
        .form-field { display: grid; gap: 6px; }
        .form-field input, .form-field select, .form-field textarea, table select, table input {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 8px 10px;
            background: white;
            width: 100%;
            color: var(--ink);
            font-size: .88rem;
        }
        .form-field textarea { resize: vertical; }
        @media (max-width: 1280px) {
            .shell {
                grid-template-columns: 232px minmax(0, 1fr);
            }
            .page {
                max-width: 1180px;
            }
        }
        @media (max-width: 1100px) {
            .shell { grid-template-columns: 1fr; }
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: min(84vw, 290px);
                max-width: 290px;
                height: 100vh;
                z-index: 50;
                border-right: 1px solid rgba(255,255,255,.08);
                border-bottom: 0;
                transform: translateX(-102%);
                transition: transform .22s ease;
            }
            body.nav-open .sidebar {
                transform: translateX(0);
            }
            .workspace { padding: 12px; }
            .nav {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .menu-toggle {
                display: inline-flex;
            }
        }
        @media (max-width: 900px) {
            .topbar, .page-head, .grid-two {
                grid-template-columns: 1fr;
                flex-direction: column;
                align-items: stretch;
            }
            .topbar-meta {
                justify-content: flex-start;
            }
            .topbar-tools {
                justify-content: stretch;
            }
            .topbar-form {
                width: 100%;
            }
            .page-head {
                align-items: stretch;
            }
            .page-head h2 {
                font-size: 1.34rem;
            }
            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .filters {
                flex-direction: column;
            }
            .actions {
                width: 100%;
                margin-top: 10px;
            }
            .actions .button-link,
            .actions button,
            .actions a {
                width: 100%;
                justify-content: center;
            }
            .topbar-logout {
                width: 100%;
            }
            .pager {
                align-items: stretch;
            }
            .pager-links {
                width: 100%;
            }
            .pager-link,
            .pager-gap {
                flex: 1 1 calc(25% - 8px);
            }
            .row-actions-menu {
                display: block;
            }
            .row-actions-toggle .action-chip {
                width: 100%;
            }
            .row-actions-dropdown {
                left: 0;
                right: auto;
                min-width: min(220px, calc(100vw - 64px));
            }
            .mobile-hide {
                display: none;
            }
            .desktop-hide {
                display: block;
            }
        }
        @media (max-width: 680px) {
            .sidebar {
                padding: 14px 12px;
                width: min(88vw, 280px);
            }
            .nav {
                grid-template-columns: 1fr;
            }
            .cards {
                grid-template-columns: 1fr;
            }
            .topbar-chip {
                width: 100%;
                white-space: normal;
            }
            table {
                min-width: 500px;
            }
        }
    </style>
</head>
<body>
    @php($role = $access->currentRole())
    @php($currentUser = auth()->user())
    @php($currentRoute = request()->route()?->getName() ?? '')
    @php($navGroups = [
        [
            'label' => 'Overview',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'match' => 'dashboard', 'ability' => 'dashboard.view', 'mark' => 'DB'],
                ['label' => 'Management Centre', 'route' => 'management-centre', 'match' => 'management-centre', 'ability' => 'dashboard.view', 'mark' => 'MC'],
                ['label' => 'Follow-ups', 'route' => 'follow-ups.index', 'match' => 'follow-ups.*', 'ability' => 'follow_ups.manage', 'mark' => 'FU'],
            ],
        ],
        [
            'label' => 'Sales Desk',
            'items' => [
                ['label' => 'Sales', 'route' => 'sales.index', 'match' => 'sales.*', 'ability' => 'sales.view', 'mark' => 'SL'],
                ['label' => 'Cash Shifts', 'route' => 'cash-shifts.index', 'match' => 'cash-shifts.*', 'ability' => 'cash_shifts.manage', 'mark' => 'CS'],
                ['label' => 'Customer Payments', 'route' => 'customer-payments.index', 'match' => 'customer-payments.*', 'ability' => 'customer_payments.manage', 'mark' => 'CP'],
                ['label' => 'Customers', 'route' => 'customers.index', 'match' => 'customers.*', 'ability' => 'customers.view', 'mark' => 'CU'],
            ],
        ],
        [
            'label' => 'Stock & Buying',
            'items' => [
                ['label' => 'Purchases', 'route' => 'purchases.index', 'match' => 'purchases.*', 'ability' => 'purchases.view', 'mark' => 'PU'],
                ['label' => 'Supplier Payments', 'route' => 'supplier-payments.index', 'match' => 'supplier-payments.*', 'ability' => 'supplier_payments.manage', 'mark' => 'SP'],
                ['label' => 'Suppliers', 'route' => 'suppliers.index', 'match' => 'suppliers.*', 'ability' => 'suppliers.view', 'mark' => 'SU'],
                ['label' => 'Stock', 'route' => 'stock.balances', 'match' => 'stock.*', 'ability' => 'stock.view', 'mark' => 'ST'],
                ['label' => 'Products', 'route' => 'products.index', 'match' => 'products.*', 'ability' => 'products.view', 'mark' => 'PR'],
            ],
        ],
        [
            'label' => 'Finance',
            'items' => [
                ['label' => 'Reports', 'route' => 'reports.financial-summary', 'match' => 'reports.*', 'ability' => 'reports.view', 'mark' => 'RP'],
                ['label' => 'Expenses', 'route' => 'expenses.index', 'match' => 'expenses.*', 'ability' => 'expenses.view', 'mark' => 'EX'],
                ['label' => 'Capital Inputs', 'route' => 'capital.index', 'match' => 'capital.*', 'ability' => 'capital.view', 'mark' => 'CA'],
            ],
        ],
        [
            'label' => 'Setup',
            'items' => [
                ['label' => 'Stores', 'route' => 'master-data.index', 'route_params' => ['resource' => 'stores'], 'match' => 'master-data.*', 'active_resource' => 'stores', 'abilities' => ['business.manage', 'master_data.manage'], 'mark' => 'ST'],
                ['label' => 'Product Categories', 'route' => 'master-data.index', 'route_params' => ['resource' => 'categories'], 'match' => 'master-data.*', 'active_resource' => 'categories', 'abilities' => ['business.manage', 'master_data.manage'], 'mark' => 'PC'],
                ['label' => 'Payment Modes', 'route' => 'master-data.index', 'route_params' => ['resource' => 'payment-modes'], 'match' => 'master-data.*', 'active_resource' => 'payment-modes', 'abilities' => ['business.manage', 'master_data.manage'], 'mark' => 'PM'],
                ['label' => 'Capital Sources', 'route' => 'master-data.index', 'route_params' => ['resource' => 'capital-sources'], 'match' => 'master-data.*', 'active_resource' => 'capital-sources', 'abilities' => ['business.manage', 'master_data.manage'], 'mark' => 'CS'],
                ['label' => 'Expense Categories', 'route' => 'master-data.index', 'route_params' => ['resource' => 'expense-categories'], 'match' => 'master-data.*', 'active_resource' => 'expense-categories', 'abilities' => ['business.manage', 'master_data.manage'], 'mark' => 'EC'],
            ],
        ],
        [
            'label' => 'Admin',
            'items' => [
                ['label' => 'UAT Center', 'route' => 'admin.uat-center', 'match' => 'admin.uat-center', 'ability' => 'users.manage', 'mark' => 'UA'],
                ['label' => 'Demo Center', 'route' => 'admin.demo-center', 'match' => 'admin.demo-center', 'ability' => 'users.manage', 'mark' => 'DC'],
                ['label' => 'Readiness', 'route' => 'admin.readiness', 'match' => 'admin.readiness', 'ability' => 'users.manage', 'mark' => 'RD'],
                ['label' => 'Permissions', 'route' => 'roles.matrix', 'match' => 'roles.matrix*', 'ability' => 'users.manage', 'mark' => 'PM'],
                ['label' => 'Roles', 'route' => 'roles.index', 'match' => 'roles.*', 'ability' => 'users.manage', 'mark' => 'RL'],
                ['label' => 'Business Settings', 'route' => 'settings.business.edit', 'match' => 'settings.business.*', 'ability' => 'business.manage', 'mark' => 'BS'],
                ['label' => 'Activity Log', 'route' => 'activity-logs.index', 'match' => 'activity-logs.*', 'ability' => 'activity_logs.view', 'mark' => 'LG'],
                ['label' => 'Users', 'route' => 'users.index', 'match' => 'users.*', 'ability' => 'users.manage', 'mark' => 'US'],
            ],
        ],
        [
            'label' => 'Account',
            'items' => [
                ['label' => 'Change Password', 'route' => 'password.change', 'match' => 'password.change*', 'ability' => null, 'mark' => 'PW'],
            ],
        ],
    ])
    <div class="shell">
        <div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
        <aside class="sidebar" id="app-sidebar">
            <div class="brand">
                @if (config('business.logo_url'))
                    <img src="{{ config('business.logo_url') }}" alt="{{ config('business.name', 'Apples Of Gold') }} logo" class="brand-logo">
                @endif
                <h1>{{ config('business.name', 'Apples Of Gold') }}</h1>
                <p>{{ config('business.tagline', 'Business Management System') }}</p>
            </div>
            @if (app()->environment('local') && $access->hasRole('admin'))
                <form method="post" action="{{ route('access.preview-role') }}" class="role-preview">
                    @csrf
                    <label for="preview_role">View As Role</label>
                    <select id="preview_role" name="preview_role">
                        @foreach ($access->roles() as $availableRole)
                            <option value="{{ $availableRole }}" @selected($role === $availableRole)>{{ ucfirst(str_replace('_', ' ', $availableRole)) }}</option>
                        @endforeach
                    </select>
                    <button type="submit">Apply View</button>
                </form>
            @endif

            @foreach ($navGroups as $group)
                @php($visibleItems = collect($group['items'])->filter(fn ($item) => isset($item['abilities']) ? collect($item['abilities'])->contains(fn ($ability) => $access->can($ability)) : (! $item['ability'] || $access->can($item['ability']))))
                @if ($visibleItems->isNotEmpty())
                    <div class="nav-section">
                        <div class="nav-label">{{ $group['label'] }}</div>
                        <nav class="nav">
                            @foreach ($visibleItems as $item)
                                @php($isActive = request()->routeIs($item['match']) && (! isset($item['active_resource']) || request()->route('resource') === $item['active_resource']))
                                <a href="{{ route($item['route'], $item['route_params'] ?? []) }}" class="{{ $isActive ? 'active' : '' }}">
                                    <span class="nav-mark">{{ $item['mark'] }}</span>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </nav>
                    </div>
                @endif
            @endforeach

            <div class="signout-box">
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" style="width:100%;">Sign Out</button>
                </form>
            </div>
        </aside>

        <div class="workspace">
            <div class="page">
                <header class="topbar">
                    <div class="topbar-title">
                        <button type="button" class="menu-toggle" id="menu-toggle" aria-expanded="false" aria-controls="app-sidebar">Menu</button>
                        <div class="topbar-heading">
                            <div class="topbar-kicker">Business Workspace</div>
                            <h2>{{ $title ?? 'Apples' }}</h2>
                        </div>
                    </div>
                    <div class="topbar-tools">
                        <div class="topbar-meta">
                            <div class="topbar-chip topbar-chip-date">{{ now()->format('D, d M Y') }}</div>
                            <div class="topbar-chip topbar-chip-role">User: {{ $currentUser->username ?: $currentUser->name }}</div>
                            <div class="topbar-chip topbar-chip-store">Shop: {{ $currentUser->defaultStore?->name ?? config('business.name', 'Apples Of Gold') }}</div>
                        </div>
                        <a href="{{ route('password.change') }}" class="button-link">Change Password</a>
                        <form method="post" action="{{ route('logout') }}" class="topbar-form">
                            @csrf
                            <button type="submit" class="topbar-logout">Log Out</button>
                        </form>
                    </div>
                </header>

                <main>
                    @if (session('status'))
                        <div class="flash">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="error-list">
                            <strong>Please fix the following:</strong>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>
    </div>
    <script>
        (() => {
            const body = document.body;
            const sidebar = document.getElementById('app-sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            const menuToggle = document.getElementById('menu-toggle');

            function closeNav() {
                body.classList.remove('nav-open');
                if (backdrop) {
                    backdrop.classList.remove('is-visible');
                    backdrop.hidden = true;
                }
                if (menuToggle) {
                    menuToggle.setAttribute('aria-expanded', 'false');
                }
            }

            function openNav() {
                if (window.innerWidth > 1100) {
                    return;
                }

                body.classList.add('nav-open');
                if (backdrop) {
                    backdrop.hidden = false;
                    requestAnimationFrame(() => backdrop.classList.add('is-visible'));
                }
                if (menuToggle) {
                    menuToggle.setAttribute('aria-expanded', 'true');
                }
            }

            menuToggle?.addEventListener('click', () => {
                if (body.classList.contains('nav-open')) {
                    closeNav();
                } else {
                    openNav();
                }
            });

            backdrop?.addEventListener('click', closeNav);

            sidebar?.querySelectorAll('a').forEach((link) => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 1100) {
                        closeNav();
                    }
                });
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth > 1100) {
                    closeNav();
                }
            });

            const menus = Array.from(document.querySelectorAll('.row-actions-menu'));
            if (menus.length) {
                function positionRowActionsMenu(menu) {
                    if (!menu.open) {
                        return;
                    }

                    const toggle = menu.querySelector('.row-actions-toggle');
                    const dropdown = menu.querySelector('.row-actions-dropdown');
                    if (!toggle || !dropdown) {
                        return;
                    }

                    const toggleRect = toggle.getBoundingClientRect();
                    const dropdownRect = dropdown.getBoundingClientRect();
                    const viewportPadding = 12;
                    const dropdownWidth = dropdownRect.width || 190;
                    const dropdownHeight = dropdownRect.height || 0;
                    let left = toggleRect.right - dropdownWidth;
                    let top = toggleRect.bottom + 8;

                    left = Math.max(viewportPadding, Math.min(left, window.innerWidth - dropdownWidth - viewportPadding));

                    if (dropdownHeight && top + dropdownHeight > window.innerHeight - viewportPadding) {
                        top = Math.max(viewportPadding, toggleRect.top - dropdownHeight - 8);
                    }

                    menu.style.setProperty('--row-actions-left', `${left}px`);
                    menu.style.setProperty('--row-actions-top', `${top}px`);
                }

                menus.forEach((menu) => {
                    menu.addEventListener('toggle', () => {
                        if (!menu.open) {
                            menu.style.removeProperty('--row-actions-left');
                            menu.style.removeProperty('--row-actions-top');
                            return;
                        }

                        menus.forEach((otherMenu) => {
                            if (otherMenu !== menu) {
                                otherMenu.removeAttribute('open');
                            }
                        });

                        requestAnimationFrame(() => positionRowActionsMenu(menu));
                    });
                });

                document.addEventListener('click', (event) => {
                    if (event.target.closest('.row-actions-menu')) {
                        return;
                    }

                    menus.forEach((menu) => menu.removeAttribute('open'));
                });

                window.addEventListener('scroll', () => {
                    menus.forEach(positionRowActionsMenu);
                }, true);

                window.addEventListener('resize', () => {
                    menus.forEach(positionRowActionsMenu);
                });
            }

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }

                closeNav();
                menus.forEach((menu) => menu.removeAttribute('open'));
            });
        })();
    </script>
</body>
</html>
