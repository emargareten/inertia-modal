<?php

namespace Emargareten\InertiaModal\Tests;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BaseMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        Inertia::share('from_base_middleware', true);

        return $next($request);
    }
}
