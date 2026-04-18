<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PlatformAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonateTenantUserController extends Controller
{
    public function __invoke(Request $request, int $userId): RedirectResponse
    {
        app(PlatformAccessService::class)->authorizePlatformOperator();

        $operator = Auth::user();

        $target = User::query()
            ->whereNotNull('company_id')
            ->findOrFail($userId);

        // Never impersonate another platform operator.
        if (app(PlatformAccessService::class)->isPlatformOperator($target)) {
            abort(403, 'Cannot impersonate a platform operator.');
        }

        session([
            'impersonator_id' => (int) $operator->id,
            'impersonator_name' => (string) $operator->name,
            'impersonator_return_url' => url()->previous(route('platform.tenants')),
        ]);

        Auth::loginUsingId($target->id);

        return redirect('/');
    }
}
