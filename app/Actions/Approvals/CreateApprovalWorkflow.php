<?php

namespace App\Actions\Approvals;

use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateApprovalWorkflow
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, array $input): ApprovalWorkflow
    {
        $this->ensureOwner($actor);

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('approval_workflows', 'code')->where(
                    fn ($query) => $query
                        ->where('company_id', $actor->company_id)
                ),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
            'applies_to' => ['nullable', Rule::in(['request'])],
        ])->validate();

        $workflow = DB::transaction(function () use ($actor, $validated): ApprovalWorkflow {
            $isDefault = (bool) ($validated['is_default'] ?? false);

            if ($isDefault) {
                ApprovalWorkflow::query()
                    ->where('company_id', $actor->company_id)
                    ->where('applies_to', $validated['applies_to'] ?? 'request')
                    ->update(['is_default' => false]);
            }
            $code = $validated['code']
                ? strtolower(trim((string) $validated['code']))
                : $this->generateWorkflowCode((int) $actor->company_id, (string) $validated['name']);

            return ApprovalWorkflow::query()->create([
                'company_id' => (int) $actor->company_id,
                'name' => trim($validated['name']),
                'code' => $code,
                'applies_to' => $validated['applies_to'] ?? 'request',
                'description' => $validated['description'] ?? null,
                'is_active' => true,
                'is_default' => $isDefault,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });

        $this->activityLogger->log(
            action: 'approval.workflow.created',
            entityType: ApprovalWorkflow::class,
            entityId: $workflow->id,
            metadata: [
                'name' => $workflow->name,
                'code' => $workflow->code,
                'is_default' => $workflow->is_default,
                'applies_to' => $workflow->applies_to,
            ],
            companyId: (int) $actor->company_id,
            userId: $actor->id,
        );

        return $workflow;
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureOwner(User $actor): void
    {
        if (! $actor->hasRole(UserRole::Owner)) {
            throw new AuthorizationException('Only owner can manage approval workflows.');
        }
    }

    private function generateWorkflowCode(int $companyId, string $name): string
    {
        $baseCode = Str::of($name)
            ->slug('_')
            ->trim('_')
            ->value();

        if ($baseCode === '') {
            $baseCode = 'workflow';
        }

        $candidate = $baseCode;
        $counter = 1;

        while (
            ApprovalWorkflow::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('code', $candidate)
                ->exists()
        ) {
            $candidate = $baseCode.'_'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
