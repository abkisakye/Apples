@extends('layouts.app', ['title' => 'Products'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Products</h2>
            <p>Browse the product master, codes, linked suppliers, and reorder settings in a cleaner list.</p>
        </div>
        <div class="actions">
            @if ($access->can('stock.view'))
                <a href="{{ route('stock.balances') }}" class="button-link">Stock Balance</a>
                <a href="{{ route('stock.reorder') }}" class="button-link">Reorder List</a>
            @endif
            @if ($access->can('purchases.manage'))
                <a href="{{ route('products.create') }}" class="button-link">New Product</a>
                <a href="{{ route('purchases.create') }}" class="button-link primary">New Purchase</a>
            @endif
        </div>
    </div>

    <form class="filters" method="get" data-products-live-search-form data-products-live-search-delay="450">
        <input type="search" name="q" value="{{ $search }}" placeholder="Search all products or code" autocomplete="off" data-products-live-search-input>
        <select name="category">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="supplier_id">
            <option value="">All suppliers</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected($supplierId === $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">All statuses</option>
            <option value="active" @selected($statusFilter === 'active')>Active</option>
            <option value="inactive" @selected($statusFilter === 'inactive')>Inactive</option>
        </select>
        <button type="submit">Filter</button>
    </form>
    <p class="list-note">Searches all products, not only visible rows.</p>

    <div id="products-results" data-products-live-search-results aria-live="polite" aria-busy="false">
        @include('products.partials.index_results')
    </div>

    <script>
        (() => {
            const form = document.querySelector('[data-products-live-search-form]');
            const input = form?.querySelector('[data-products-live-search-input]');
            const results = document.querySelector('[data-products-live-search-results]');
            if (!form || !input || !results || !window.fetch) {
                return;
            }

            const delay = Number.parseInt(form.dataset.productsLiveSearchDelay || '450', 10);
            let timer = null;
            let activeController = null;

            function buildUrl(pageUrl = null) {
                const formData = new FormData(form);
                const url = pageUrl ? new URL(pageUrl, window.location.origin) : new URL(form.action || window.location.href, window.location.origin);

                if (!pageUrl) {
                    url.search = '';
                }

                formData.forEach((value, key) => {
                    const normalizedValue = String(value || '').trim();
                    url.searchParams.delete(key);
                    if (normalizedValue !== '') {
                        url.searchParams.set(key, normalizedValue);
                    }
                });

                if (!pageUrl) {
                    url.searchParams.delete('page');
                }

                return url;
            }

            async function refreshProducts(pageUrl = null, pushUrl = true) {
                const url = buildUrl(pageUrl);

                if (activeController) {
                    activeController.abort();
                }

                activeController = new AbortController();
                results.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch(url.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                        },
                        signal: activeController.signal,
                    });

                    if (!response.ok) {
                        throw new Error('Could not refresh products.');
                    }

                    results.innerHTML = await response.text();
                    if (pushUrl) {
                        window.history.replaceState({}, '', url.toString());
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        form.submit();
                    }
                } finally {
                    results.setAttribute('aria-busy', 'false');
                }
            }

            input.addEventListener('input', () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(() => refreshProducts(), Number.isFinite(delay) ? delay : 450);
            });

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                refreshProducts();
            });

            results.addEventListener('click', (event) => {
                const link = event.target.closest('.pagination a');
                if (!link) {
                    return;
                }

                event.preventDefault();
                refreshProducts(link.href);
            });
        })();
    </script>
@endsection
