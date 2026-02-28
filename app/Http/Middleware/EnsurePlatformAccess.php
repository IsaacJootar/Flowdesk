<?php

namespace App\Http\Middleware;

use App\Services\PlatformAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(PlatformAccessService::class)->isPlatformOperator($request->user()), 403);

        return $next($request);
    }
}

