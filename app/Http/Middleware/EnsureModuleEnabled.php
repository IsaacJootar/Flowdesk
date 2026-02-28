<?php

namespace App\Http\Middleware;

use App\Services\PlatformAccessService;
use App\Services\TenantModuleAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(
        private readonly TenantModuleAccessService $tenantModuleAccessService,
        private readonly PlatformAccessService $platformAccessService,
    ) {
    }

    public function handle(Request $request, Closure $next, string $modules): Response
    {
        $user = $request->user();

        // Platform operators are managed by platform roles and should not be blocked by tenant module flags.
        if ($this->platformAccessService->isPlatformOperator($user)) {
            return $next($request);
        }

        $requiredModules = array_values(array_filter(array_map(
            static fn (string $module): string => strtolower(trim($module)),
            explode(',', $modules)
        )));

        if (! $this->tenantModuleAccessService->moduleEnabled($user, $requiredModules)) {
            abort(403, 'This module is disabled for your organization plan.');
        }

        return $next($request);
    }
}

