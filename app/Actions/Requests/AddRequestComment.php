<?php

namespace App\Actions\Requests;

use App\Domains\Requests\Models\RequestComment;
use App\Domains\Requests\Models\SpendRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\RequestCommunicationLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AddRequestComment
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly RequestCommunicationLogger $requestCommunicationLogger
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, SpendRequest $request, array $input): RequestComment
    {
        Gate::forUser($user)->authorize('view', $request);

        $validated = Validator::make($input, [
            'body' => ['required', 'string', 'max:4000'],
        ])->validate();

        $comment = RequestComment::query()->create([
            'company_id' => (int) $request->company_id,
            'request_id' => (int) $request->id,
            'user_id' => (int) $user->id,
            'body' => trim((string) $validated['body']),
        ]);

        $this->activityLogger->log(
            action: 'request.comment.added',
            entityType: RequestComment::class,
            entityId: $comment->id,
            metadata: [
                'request_id' => (int) $request->id,
                'request_code' => (string) $request->request_code,
            ],
            companyId: (int) $request->company_id,
            userId: (int) $user->id,
        );

        $channels = array_values(array_unique(array_map(
            'strval',
            (array) (($request->metadata ?? [])['notification_channels'] ?? [])
        )));
        // Keep comment notifications resilient even when request metadata has no channel config yet.
        $channels = $channels === [] ? ['in_app'] : $channels;

        $this->requestCommunicationLogger->log(
            request: $request,
            event: 'request.comment.added',
            channels: $channels,
            recipientUserIds: [(int) $request->requested_by],
            requestApprovalId: null,
            metadata: [
                'request_code' => (string) $request->request_code,
                'comment_id' => (int) $comment->id,
                'audience' => 'requester',
            ]
        );

        return $comment->loadMissing('user:id,name');
    }
}
