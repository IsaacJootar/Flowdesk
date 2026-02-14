<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        if ($request->routeIs('settings.company.setup')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user->company_id || ! $user->department_id || ! $user->role) {
            return redirect()->route('settings.company.setup');
        }

        return $next($request);
    }
}
