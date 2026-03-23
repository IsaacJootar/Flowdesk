<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PlatformAccessService;
use App\Support\CorrelationContext;
use App\Support\FlowdeskLogContext;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyContext
{
    public function __construct(
        private readonly PlatformAccessService $platformAccessService,
        private readonly TenantContext $tenantContext,
        private readonly CorrelationContext $correlationContext,
        private readonly FlowdeskLogContext $flowdeskLogContext,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $user = $request->user();

        // Tenant routes are scoped to tenant operators only; platform operators stay on /platform/*
        // to avoid accidental cross-surface access with mixed account attributes.
        if ($user instanceof User && $this->platformAccessService->isPlatformOperator($user)) {
            abort(403);
        }

        if ($user instanceof User && $user->company_id) {
            $user->loadMissing('company:id,timezone');
            $timezone = (string) ($user->company?->timezone ?? '');

            if ($timezone !== '' && in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
                config(['app.timezone' => $timezone]);
                date_default_timezone_set($timezone);
            }
        }

        if ($request->routeIs('settings.company.setup')) {
            return $next($request);
        }

        if (! $user->company_id || ! $user->department_id || ! $user->role) {
            return redirect()->route('settings.company.setup');
        }

        $this->tenantContext->setCompanyId((int) $user->company_id);
        $this->correlationContext->mergeContext([
            'company_id' => (int) $user->company_id,
            'actor_id' => (int) $user->id,
            'actor_role' => (string) $user->role,
        ]);
        $this->flowdeskLogContext->share($this->correlationContext->all());

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
        }
    }
}
