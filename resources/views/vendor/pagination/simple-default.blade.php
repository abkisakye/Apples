@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Pagination Navigation">
        <div class="pager-summary">
            Page <strong>{{ number_format($paginator->currentPage()) }}</strong>
        </div>

        <div class="pager-links">
            @if ($paginator->onFirstPage())
                <span class="pager-link is-disabled">Previous</span>
            @else
                <a class="pager-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="pager-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="pager-link is-disabled">Next</span>
            @endif
        </div>
    </nav>
@endif
