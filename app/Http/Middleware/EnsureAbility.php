<?php

namespace App\Http\Middleware;

use App\Support\AccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAbility
{
    public function __construct(
        private readonly AccessService $access
    ) {
    }

    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        foreach ($abilities as $ability) {
            if ($this->access->can($ability)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
