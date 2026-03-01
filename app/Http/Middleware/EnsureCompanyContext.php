<?php

namespace App\Http\Middleware;

use App\Models\User;
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

        $user = $request->user();

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

        return $next($request);
    }
}
