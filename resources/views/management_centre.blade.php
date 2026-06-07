@extends('layouts.app', ['title' => 'Management Centre'])

@section('content')
    <style>
        .management-shell {
            display: grid;
            gap: 16px;
        }

        .management-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 16px;
            align-items: end;
            padding: 16px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background:
                linear-gradient(135deg, rgba(231, 242, 235, .9), rgba(251, 241, 207, .8)),
                #fff;
            box-shadow: var(--shadow);
        }

        .management-hero h2 {
            margin: 0;
            font-size: 1.55rem;
        }

        .management-hero p {
            margin: 7px 0 0;
            color: var(--muted);
            max-width: 760px;
            line-height: 1.45;
        }

        .management-quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .management-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .management-group {
            display: grid;
            gap: 12px;
            align-content: start;
        }

        .management-group-head {
            display: grid;
            gap: 5px;
        }

        .management-group-head h3 {
            margin: 0;
            font-size: 1rem;
        }

        .management-group-head p {
            margin: 0;
            color: var(--muted);
            font-size: .85rem;
            line-height: 1.38;
        }

        .management-links {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .management-link {
            display: grid;
            gap: 6px;
            min-height: 112px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--panel-soft);
            transition: transform .16s ease, border-color .16s ease, background .16s ease;
        }

        .management-link:hover {
            transform: translateY(-2px);
            border-color: var(--accent);
            background: #fff;
        }

        .management-link strong {
            font-size: .91rem;
        }

        .management-link span {
            color: var(--muted);
            font-size: .8rem;
            line-height: 1.35;
        }

        .management-empty {
            padding: 12px;
            border: 1px dashed var(--line);
            border-radius: 12px;
            color: var(--muted);
            background: var(--panel-soft);
        }

        @media (max-width: 1060px) {
            .management-grid,
            .management-hero {
                grid-template-columns: 1fr;
            }

            .management-quick-actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 720px) {
            .management-links {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @php
        $canSeeItem = function (array $item) use ($access): bool {
            if (isset($item['abilities'])) {
                return collect($item['abilities'])->contains(fn (string $ability) => $access->can($ability));
            }

            return ! isset($item['ability']) || $access->can($item['ability']);
        };
    @endphp

    <section class="management-shell">
        <div class="management-hero">
            <div>
                <h2>Management Centre</h2>
                <p>Wholesale and retail workflows in one place: sales, purchases, stock control, accounts, reports, and setup shortcuts for day-to-day management.</p>
            </div>
            <div class="management-quick-actions">
                @if ($access->can('sales.manage'))
                    <a href="{{ route('sales.create') }}" class="button-link primary">New Sale</a>
                @endif
                @if ($access->can('purchases.manage'))
                    <a href="{{ route('purchases.create') }}" class="button-link">Receive Stock</a>
                @endif
                @if ($access->can('stock.view'))
                    <a href="{{ route('stock.balances') }}" class="button-link">Stock Balances</a>
                @endif
                @if ($access->can('reports.view'))
                    <a href="{{ route('reports.daily-closing') }}" class="button-link">Daily Closing</a>
                @endif
            </div>
        </div>

        <div class="management-grid">
            @foreach ($groups as $group)
                @php($visibleItems = collect($group['items'])->filter($canSeeItem)->values())
                <section class="panel management-group">
                    <div class="management-group-head">
                        <h3>{{ $group['title'] }}</h3>
                        <p>{{ $group['summary'] }}</p>
                    </div>

                    @if ($visibleItems->isNotEmpty())
                        <div class="management-links">
                            @foreach ($visibleItems as $item)
                                <a href="{{ route($item['route'], $item['route_params'] ?? []) }}" class="management-link">
                                    <strong>{{ $item['label'] }}</strong>
                                    <span>{{ $item['description'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="management-empty">No shortcuts are available for your current role in this group.</div>
                    @endif
                </section>
            @endforeach
        </div>
    </section>
@endsection
