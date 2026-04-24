@php
    $brandIconPath = public_path('brand/apples-icon.png');
    $brandIconSrc = file_exists($brandIconPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($brandIconPath))
        : asset('brand/apples-icon.png');
    $businessLines = $businessMetaLines ?? collect([
        config('business.address'),
        trim(collect([
            config('business.phone') ? 'Tel: '.config('business.phone') : null,
            config('business.email') ? 'Email: '.config('business.email') : null,
        ])->filter()->implode(' / ')),
        config('business.tin') ? 'TIN: '.config('business.tin') : null,
    ])->filter()->values()->all();
@endphp
<table class="header">
    <tr>
        <td class="brand-block">
            <table class="brand-row">
                <tr>
                    <td class="brand-icon-wrap">
                        <img src="{{ $brandIconSrc }}" alt="{{ config('business.name', 'Apples Of Gold') }} logo" class="brand-icon">
                    </td>
                    <td>
                        <div class="brand-name">{{ config('business.name', 'Apples Of Gold') }}</div>
                        <div class="brand-tagline">{{ config('business.tagline', 'Freshness & Value Every Day') }}</div>
                    </td>
                </tr>
            </table>
            @if (! empty($businessLines))
                <div class="brand-meta">
                    @foreach ($businessLines as $line)
                        {{ $line }}@if (! $loop->last)<br>@endif
                    @endforeach
                </div>
            @endif
        </td>
        <td class="doc-block">
            <div class="doc-label">{{ $documentLabel }}</div>
            <div class="doc-name">{{ $documentName }}</div>
            <div class="doc-meta">
                @foreach ($documentMetaLines as $line)
                    {{ $line }}@if (! $loop->last)<br>@endif
                @endforeach
            </div>
        </td>
    </tr>
</table>
