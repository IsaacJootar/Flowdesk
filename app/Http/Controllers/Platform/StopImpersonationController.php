<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class StopImpersonationController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $impersonatorId = session('impersonator_id');
        $returnUrl = session('impersonator_return_url', route('platform.tenants'));

        if (! $impersonatorId) {
            return redirect('/');
        }

        session()->forget(['impersonator_id', 'impersonator_name', 'impersonator_return_url']);

        $operator = User::query()->find((int) $impersonatorId);
        if ($operator) {
            Auth::loginUsingId($operator->id);
        } else {
            Auth::logout();
        }

        return redirect($returnUrl);
    }
}
