<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserAvatarController extends Controller
{
    public function __invoke(User $user): StreamedResponse
    {
        abort_unless(auth()->check(), 403);
        abort_unless((int) auth()->user()->company_id === (int) $user->company_id, 404);
        abort_if(! $user->avatar_path, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($user->avatar_path), 404);

        return $disk->response(
            path: $user->avatar_path,
            headers: [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]
        );
    }
}
