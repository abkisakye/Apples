@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Pagination Navigation">
        <div class="pager-summary">
            Showing
            <strong>{{ number_format($paginator->firstItem() ?? 0) }}</strong>
            to
            <strong>{{ number_format($paginator->lastItem() ?? 0) }}</strong>
            of
            <strong>{{ number_format($paginator->total()) }}</strong>
            results
        </div>

        <div class="pager-links">
            @if ($paginator->onFirstPage())
                <span class="pager-link is-disabled">Previous</span>
            @else
                <a class="pager-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pager-gap">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pager-link is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="pager-link" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="pager-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="pager-link is-disabled">Next</span>
            @endif
        </div>
    </nav>
@endif
