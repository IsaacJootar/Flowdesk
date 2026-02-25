<?php

namespace App\Actions\Requests;

use App\Domains\Requests\Models\RequestAttachment;
use App\Domains\Requests\Models\SpendRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UploadRequestAttachment
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, SpendRequest $request, UploadedFile $file): RequestAttachment
    {
        Gate::forUser($user)->authorize('uploadAttachment', $request);

        if (! in_array((string) $request->status, ['draft', 'returned'], true)) {
            throw ValidationException::withMessages([
                'file' => 'Attachments can only be uploaded while request is in draft or returned status.',
            ]);
        }

        Validator::make(
            ['file' => $file],
            ['file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp']]
        )->validate();

        // Tenant + request partitioning is required for safe multi-tenant storage and cleanup.
        $path = $file->store("private/request-attachments/{$request->company_id}/{$request->id}", 'local');

        $attachment = RequestAttachment::query()->create([
            'company_id' => $request->company_id,
            'request_id' => $request->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => (int) $file->getSize(),
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
        ]);

        $this->activityLogger->log(
            action: 'request.attachment.uploaded',
            entityType: SpendRequest::class,
            entityId: $request->id,
            metadata: [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'file_size' => $attachment->file_size,
                'request_code' => (string) $request->request_code,
            ],
            companyId: (int) $request->company_id,
            userId: (int) $user->id,
        );

        return $attachment;
    }
}
