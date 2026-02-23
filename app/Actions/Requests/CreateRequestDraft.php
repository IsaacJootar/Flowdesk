<?php

namespace App\Actions\Requests;

use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Requests\Models\CompanyRequestType;
use App\Domains\Requests\Models\CompanySpendCategory;
use App\Domains\Requests\Models\RequestItem;
use App\Domains\Requests\Models\SpendRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\RequestCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateRequestDraft
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly RequestCodeGenerator $requestCodeGenerator
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, array $input): SpendRequest
    {
        Gate::forUser($user)->authorize('create', SpendRequest::class);

        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'company' => 'User must belong to a company before creating requests.',
            ]);
        }

        $companyId = (int) $user->company_id;
        $requestType = $this->resolveRequestType($companyId, (string) ($input['type'] ?? ''));
        $validated = Validator::make($input, $this->rules($companyId, $requestType))->validate();
        $notificationChannels = $this->resolveRequestChannels($companyId, $validated);
        $normalizedItems = $requestType->requires_line_items
            ? $this->normalizeItems($validated['items'] ?? [])
            : [];
        $totalAmount = $this->resolveAmount($requestType, $validated, $normalizedItems);
        $currency = $this->companyCurrency($user);
        $metadata = $this->buildMetadata($requestType, $validated, $notificationChannels);

        $request = DB::transaction(function () use ($user, $validated, $normalizedItems, $totalAmount, $currency, $metadata, $requestType): SpendRequest {
            $request = SpendRequest::query()->create([
                'company_id' => (int) $user->company_id,
                'request_code' => $this->requestCodeGenerator->generateForCompany((int) $user->company_id),
                'requested_by' => $user->id,
                'department_id' => (int) $validated['department_id'],
                'vendor_id' => $validated['vendor_id'] ? (int) $validated['vendor_id'] : null,
                'workflow_id' => (int) $validated['workflow_id'],
                'title' => trim((string) $validated['title']),
                'description' => $validated['description'] ?? null,
                'amount' => $totalAmount,
                'currency' => $currency,
                'status' => 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'metadata' => $metadata,
            ]);

            if ($requestType->requires_line_items) {
                foreach ($normalizedItems as $item) {
                    RequestItem::query()->create([
                        'company_id' => (int) $user->company_id,
                        'request_id' => $request->id,
                        'item_name' => $item['name'],
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'],
                        'line_total' => $item['line_total'],
                        'vendor_id' => $item['vendor_id'],
                        'category' => $item['category'],
                    ]);
                }
            }

            return $request;
        });

        $this->activityLogger->log(
            action: 'request.created',
            entityType: SpendRequest::class,
            entityId: $request->id,
            metadata: [
                'request_code' => $request->request_code,
                'status' => $request->status,
                'type' => $request->metadata['type'] ?? null,
                'amount' => $request->amount,
                'items_count' => count($normalizedItems),
                'channels' => $notificationChannels,
            ],
            companyId: (int) $request->company_id,
            userId: $user->id,
        );

        return $request->load(['items', 'workflow']);
    }

    private function rules(int $companyId, CompanyRequestType $requestType): array
    {
        $categoryExistsRule = Rule::exists('company_spend_categories', 'code')
            ->where(fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('is_active', true));

        $vendorRule = Rule::exists('vendors', 'id')
            ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at'));

        $userRule = Rule::exists('users', 'id')
            ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at'));

        $itemRules = $requestType->requires_line_items
            ? ['required', 'array', 'min:1']
            : ['nullable', 'array'];

        $itemNameRules = $requestType->requires_line_items ? ['required', 'string', 'max:180'] : ['nullable', 'string', 'max:180'];
        $itemQuantityRules = $requestType->requires_line_items ? ['required', 'integer', 'min:1'] : ['nullable', 'integer', 'min:1'];
        $itemUnitCostRules = $requestType->requires_line_items ? ['required', 'integer', 'min:1'] : ['nullable', 'integer', 'min:1'];
        $categoryRules = $requestType->requires_line_items ? ['required', 'string', 'max:120', $categoryExistsRule] : ['nullable', 'string', 'max:120'];

        return [
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'vendor_id' => [
                $requestType->requires_vendor ? 'required' : 'nullable',
                $vendorRule,
            ],
            'workflow_id' => [
                'required',
                Rule::exists('approval_workflows', 'id')
                    ->where(fn ($query) => $query
                        ->where('company_id', $companyId)
                        ->where('applies_to', 'request')
                        ->where('is_active', true)
                        ->whereNull('deleted_at')),
            ],
            'type' => ['required', Rule::in([(string) $requestType->code])],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'amount' => $requestType->requires_line_items
                ? ['nullable', 'integer', 'min:0']
                : ($requestType->requires_amount ? ['required', 'integer', 'min:1'] : ['nullable', 'integer', 'min:0']),
            'needed_by' => ['nullable', 'date'],
            'start_date' => $requestType->requires_date_range ? ['required', 'date'] : ['nullable', 'date'],
            'end_date' => $requestType->requires_date_range ? ['required', 'date', 'after_or_equal:start_date'] : ['nullable', 'date'],
            'destination' => ['nullable', 'string', 'max:200'],
            'leave_type' => ['nullable', 'string', 'max:120'],
            'handover_user_id' => ['nullable', $userRule],
            'channel_mode' => ['nullable', Rule::in(['workflow_default', 'custom'])],
            'notification_channels' => ['nullable', 'array'],
            'notification_channels.*' => ['string', Rule::in(CompanyCommunicationSetting::CHANNELS)],
            'items' => $itemRules,
            'items.*.name' => $itemNameRules,
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => $itemQuantityRules,
            'items.*.unit_cost' => $itemUnitCostRules,
            'items.*.vendor_id' => [
                'nullable',
                $vendorRule,
            ],
            'items.*.category' => $categoryRules,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{name: string, description: ?string, quantity: int, unit_cost: int, line_total: int, vendor_id: ?int, category: ?string}>
     */
    private function normalizeItems(array $items): array
    {
        return array_map(function (array $item): array {
            $quantity = (int) $item['quantity'];
            $unitCost = (int) $item['unit_cost'];

            return [
                'name' => trim((string) $item['name']),
                'description' => $this->nullableString($item['description'] ?? null),
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'line_total' => $quantity * $unitCost,
                'vendor_id' => ! empty($item['vendor_id']) ? (int) $item['vendor_id'] : null,
                'category' => $this->nullableString($item['category'] ?? null),
            ];
        }, $items);
    }

    /**
     * @param  array<int, array{line_total: int}>  $items
     */
    private function sumItems(array $items): int
    {
        return (int) array_sum(array_column($items, 'line_total'));
    }

    private function resolveRequestType(int $companyId, string $typeCode): CompanyRequestType
    {
        $typeCode = trim(strtolower($typeCode));
        if ($typeCode === '') {
            throw ValidationException::withMessages([
                'type' => 'Select a request type.',
            ]);
        }

        $type = CompanyRequestType::query()
            ->where('company_id', $companyId)
            ->where('code', $typeCode)
            ->where('is_active', true)
            ->first();

        if (! $type) {
            throw ValidationException::withMessages([
                'type' => 'Selected request type is invalid or inactive.',
            ]);
        }

        return $type;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, string>
     * @throws ValidationException
     */
    private function resolveRequestChannels(int $companyId, array $validated): array
    {
        $settings = CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                CompanyCommunicationSetting::defaultAttributes()
            );

        if (($validated['channel_mode'] ?? 'workflow_default') !== 'custom') {
            return [];
        }

        $channels = array_values(array_unique(array_map('strval', (array) ($validated['notification_channels'] ?? []))));
        if ($channels === []) {
            throw ValidationException::withMessages([
                'notification_channels' => 'Select at least one channel when using custom notification mode.',
            ]);
        }

        $selectable = $settings->selectableChannels();
        if ($selectable === []) {
            throw ValidationException::withMessages([
                'notification_channels' => 'No channels are currently configured for this organization.',
            ]);
        }

        $invalid = array_values(array_diff($channels, $selectable));
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'notification_channels' => 'Selected channel is not allowed/configured: '.implode(', ', $invalid).'.',
            ]);
        }

        return $channels;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, array{line_total: int}>  $normalizedItems
     */
    private function resolveAmount(CompanyRequestType $requestType, array $validated, array $normalizedItems): int
    {
        if ($requestType->requires_line_items) {
            return $this->sumItems($normalizedItems);
        }

        if (array_key_exists('amount', $validated) && $validated['amount'] !== null && $validated['amount'] !== '') {
            return (int) $validated['amount'];
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, string>  $notificationChannels
     * @return array<string, mixed>
     */
    private function buildMetadata(CompanyRequestType $requestType, array $validated, array $notificationChannels): array
    {
        $metadata = [
            'type' => (string) $requestType->code,
            'request_type_code' => (string) $requestType->code,
            'request_type_name' => (string) $requestType->name,
            'needed_by' => $validated['needed_by'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'destination' => $this->nullableString($validated['destination'] ?? null),
            'leave_type' => $this->nullableString($validated['leave_type'] ?? null),
            'handover_user_id' => ! empty($validated['handover_user_id']) ? (int) $validated['handover_user_id'] : null,
            'channel_mode' => (string) (($validated['channel_mode'] ?? null) ?: 'workflow_default'),
            'notification_channels' => $notificationChannels,
        ];

        return $metadata;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function companyCurrency(User $user): string
    {
        return strtoupper((string) ($user->company?->currency_code ?: 'NGN'));
    }
}
