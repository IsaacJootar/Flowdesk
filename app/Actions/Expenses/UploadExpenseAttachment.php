<?php

namespace App\Actions\Expenses;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Models\ExpenseAttachment;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UploadExpenseAttachment
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, Expense $expense, UploadedFile $file): ExpenseAttachment
    {
        Gate::forUser($user)->authorize('uploadAttachment', $expense);

        Validator::make(
            ['file' => $file],
            ['file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp']]
        )->validate();

        $path = $file->store("private/expense-attachments/{$expense->company_id}/{$expense->id}", 'local');

        $attachment = ExpenseAttachment::query()->create([
            'company_id' => $expense->company_id,
            'expense_id' => $expense->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => (int) $file->getSize(),
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
        ]);

        $this->activityLogger->log(
            action: 'expense.attachment.uploaded',
            entityType: Expense::class,
            entityId: $expense->id,
            metadata: [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'file_size' => $attachment->file_size,
            ],
            companyId: $expense->company_id,
            userId: $user->id,
        );

        return $attachment;
    }
}
