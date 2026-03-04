<?php

namespace App\Livewire\Vendors;

use App\Actions\Vendors\CreateVendor;
use App\Actions\Vendors\CreateVendorInvoice;
use App\Actions\Vendors\DeleteVendor;
use App\Actions\Vendors\RecordVendorInvoicePayment;
use App\Actions\Vendors\UploadVendorInvoiceAttachment;
use App\Actions\Vendors\UploadVendorInvoicePaymentAttachment;
use App\Actions\Vendors\UpdateVendor;
use App\Actions\Vendors\UpdateVendorInvoice;
use App\Actions\Vendors\VoidVendorInvoice;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Vendors\Models\VendorCommunicationLog;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Domains\Vendors\Models\VendorInvoicePayment;
use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Services\VendorCommunicationLogger;
use App\Services\VendorCommunicationRetryService;
use App\Services\VendorPaymentInsights;
use App\Services\VendorReminderService;
use Throwable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class VendorsPage extends Component
{
    use WithFileUploads;
    use WithPagination;

    public bool $readyToLoad = false;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $typeFilter = 'all';

    public int $perPage = 10;

    public bool $showFormModal = false;

    public bool $showDetailPanel = false;

    public bool $showInvoiceModal = false;

    public bool $showPaymentModal = false;

    public bool $showVoidInvoiceModal = false;

    public bool $isEditing = false;

    public bool $isEditingInvoice = false;

    public ?int $editingVendorId = null;

    public ?int $selectedVendorId = null;

    public ?int $editingInvoiceId = null;

    public ?int $payingInvoiceId = null;

    public ?int $voidingInvoiceId = null;

    public ?string $feedbackMessage = null;

    public int $feedbackKey = 0;

    public ?string $feedbackError = null;

    public int $vendorTotalPaid = 0;

    public int $vendorPaymentsCount = 0;

    public ?string $vendorLastPaymentDate = null;

    public string $invoiceSearch = '';

    public string $invoiceStatusFilter = 'all';

    public string $voidInvoiceReason = '';

    public int $vendorTotalInvoiced = 0;

    public int $vendorTotalInvoicePaid = 0;

    public int $vendorOutstandingBalance = 0;

    public int $vendorInvoicesCount = 0;

    public int $vendorUnpaidInvoicesCount = 0;

    public int $vendorPartPaidInvoicesCount = 0;

    public int $vendorPartPaymentsCount = 0;

    public int $vendorPaidInvoicesCount = 0;

    public int $vendorOverdueInvoicesCount = 0;

    public string $statementDateFrom = '';

    public string $statementDateTo = '';

    public string $statementInvoiceStatus = 'all';

    public int $reminderDaysAhead = 0;

    /**
     * Keep untyped so temporary empty-string edits do not break Livewire hydration.
     */
    public $vendorCommQueuedOlderThanMinutes = 2;

    /** @var array<int, array<string, mixed>> */
    public array $vendorRecentPayments = [];

    /** @var array<int, array<string, mixed>> */
    public array $vendorInvoices = [];

    /** @var array<int, array<string, mixed>> */
    public array $vendorStatementTimeline = [];

    /** @var array<string, mixed> */
    public array $form = [
        'name' => '',
        'vendor_type' => '',
        'contact_person' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
        'bank_name' => '',
        'account_name' => '',
        'account_number' => '',
        'notes' => '',
        'is_active' => true,
    ];

    /** @var array<string, mixed> */
    public array $invoiceForm = [
        'invoice_number' => '',
        'invoice_date' => '',
        'due_date' => '',
        'total_amount' => '',
        'description' => '',
        'notes' => '',
    ];

    /** @var array<string, mixed> */
    public array $paymentForm = [
        'amount' => '',
        'payment_date' => '',
        'payment_method' => '',
        'payment_reference' => '',
        'notes' => '',
    ];

    /** @var array<int, mixed> */
    public array $newInvoiceAttachments = [];

    /** @var array<int, mixed> */
    public array $newPaymentAttachments = [];

    public function mount(): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        abort_unless($user && Gate::forUser($user)->allows('viewAny', Vendor::class), 403);

        $this->feedbackMessage = session('status');
        $this->invoiceForm['invoice_date'] = now()->toDateString();
        $this->paymentForm['payment_date'] = now()->toDateString();
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50], true)) {
            $this->perPage = 10;
        }

        $this->resetPage();
    }

    public function updatedVendorCommQueuedOlderThanMinutes(mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $this->vendorCommQueuedOlderThanMinutes = max(0, (int) $value);
    }

    public function updated(string $propertyName, mixed $value = null): void
    {
        if (str_starts_with($propertyName, 'form.')) {
            $this->resetErrorBag('form.no_changes');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function openCreateModal(): void
    {
        Gate::authorize('create', Vendor::class);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->resetForm();
        $this->isEditing = false;
        $this->editingVendorId = null;
        $this->showFormModal = true;
    }

    /**
     * @throws AuthorizationException
     */
    public function openEditModal(int $vendorId): void
    {
        $vendor = $this->findVendorOrFail($vendorId);
        Gate::authorize('update', $vendor);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->isEditing = true;
        $this->editingVendorId = $vendor->id;
        $this->fillFormFromVendor($vendor);
        $this->showFormModal = true;
    }

    /**
     * @throws AuthorizationException
     */
    public function showDetails(int $vendorId): void
    {
        $vendor = $this->findVendorOrFail($vendorId);
        Gate::authorize('view', $vendor);

        $this->selectedVendorId = $vendor->id;
        $this->loadVendorInsights($vendor);
        $this->showDetailPanel = true;
    }

    public function closeDetailPanel(): void
    {
        $this->showDetailPanel = false;
        $this->selectedVendorId = null;
        $this->invoiceSearch = '';
        $this->invoiceStatusFilter = 'all';
        $this->statementDateFrom = '';
        $this->statementDateTo = '';
        $this->statementInvoiceStatus = 'all';
        $this->reminderDaysAhead = 0;
        $this->closeInvoiceModal();
        $this->closePaymentModal();
        $this->closeVoidInvoiceModal();
        $this->resetVendorInsights();
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
        $this->isEditing = false;
        $this->editingVendorId = null;
        $this->resetValidation();
    }

    public function closeInvoiceModal(): void
    {
        $this->showInvoiceModal = false;
        $this->isEditingInvoice = false;
        $this->editingInvoiceId = null;
        $this->resetInvoiceForm();
        $this->resetValidation();
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->payingInvoiceId = null;
        $this->resetPaymentForm();
        $this->resetValidation();
    }

    public function closeVoidInvoiceModal(): void
    {
        $this->showVoidInvoiceModal = false;
        $this->voidingInvoiceId = null;
        $this->voidInvoiceReason = '';
        $this->resetValidation();
    }

    /**
     * @throws AuthorizationException
     */
    public function save(CreateVendor $createVendor, UpdateVendor $updateVendor): void
    {
        $this->feedbackError = null;
        $this->validate();

        try {
            if ($this->isEditing && $this->editingVendorId) {
                $vendor = $this->findVendorOrFail($this->editingVendorId);
                $updateVendor(\Illuminate\Support\Facades\Auth::user(), $vendor, $this->formPayload());
                $this->setFeedback('Vendor updated successfully.');
            } else {
                $createVendor(\Illuminate\Support\Facades\Auth::user(), $this->formPayload());
                $this->setFeedback('Vendor created successfully.');
            }
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            if (array_key_exists('no_changes', $errors)) {
                $this->feedbackError = null;
                $this->addError('form.no_changes', (string) ($errors['no_changes'][0] ?? 'No changes made.'));

                return;
            }

            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable) {
            $this->setFeedbackError('Save failed. Please try again.');
            return;
        }

        $this->closeFormModal();
        $this->resetPage();
    }

    /**
     * @throws AuthorizationException
     */
    public function delete(int $vendorId, DeleteVendor $deleteVendor): void
    {
        $vendor = $this->findVendorOrFail($vendorId);
        $deleteVendor(\Illuminate\Support\Facades\Auth::user(), $vendor);

        if ($this->selectedVendorId === $vendorId) {
            $this->closeDetailPanel();
        }

        $this->setFeedback('Vendor deleted successfully.');
        $this->resetPage();
    }

    public function openCreateInvoiceModal(): void
    {
        $vendor = $this->selectedVendor;
        if (! $vendor) {
            return;
        }

        Gate::authorize('manageInvoices', $vendor);
        $this->feedbackError = null;
        $this->resetValidation();
        $this->isEditingInvoice = false;
        $this->editingInvoiceId = null;
        $this->resetInvoiceForm();
        $this->showInvoiceModal = true;
    }

    public function openEditInvoiceModal(int $invoiceId): void
    {
        $invoice = $this->findSelectedVendorInvoiceOrFail($invoiceId);
        Gate::authorize('manageInvoices', $invoice->vendor);

        $this->feedbackError = null;
        $this->resetValidation();
        $this->isEditingInvoice = true;
        $this->editingInvoiceId = $invoice->id;
        $this->invoiceForm = [
            'invoice_number' => (string) $invoice->invoice_number,
            'invoice_date' => optional($invoice->invoice_date)->toDateString() ?? now()->toDateString(),
            'due_date' => optional($invoice->due_date)->toDateString() ?? '',
            'total_amount' => (string) ((int) $invoice->total_amount),
            'description' => (string) ($invoice->description ?? ''),
            'notes' => (string) ($invoice->notes ?? ''),
        ];
        $this->showInvoiceModal = true;
    }

    public function saveInvoice(
        CreateVendorInvoice $createVendorInvoice,
        UpdateVendorInvoice $updateVendorInvoice,
        UploadVendorInvoiceAttachment $uploadVendorInvoiceAttachment
    ): void
    {
        $vendor = $this->selectedVendor;
        if (! $vendor) {
            return;
        }

        Gate::authorize('manageInvoices', $vendor);
        $this->feedbackError = null;
        $this->validate($this->invoiceRules(), $this->invoiceMessages());

        try {
            if ($this->isEditingInvoice && $this->editingInvoiceId) {
                $invoice = $this->findSelectedVendorInvoiceOrFail($this->editingInvoiceId);
                $updateVendorInvoice(\Illuminate\Support\Facades\Auth::user(), $invoice, $this->invoicePayload());
                $this->attachInvoiceUploadedFiles($uploadVendorInvoiceAttachment, $invoice);
                $this->setFeedback('Vendor invoice updated.');
            } else {
                $invoice = $createVendorInvoice(\Illuminate\Support\Facades\Auth::user(), $vendor, $this->invoicePayload());
                $this->attachInvoiceUploadedFiles($uploadVendorInvoiceAttachment, $invoice);
                $this->setFeedback('Vendor invoice created.');
            }
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable) {
            $this->setFeedbackError('Unable to save invoice right now.');
            return;
        }

        $this->closeInvoiceModal();
        $this->refreshSelectedVendorInsights();
    }

    public function openPaymentModal(int $invoiceId): void
    {
        $invoice = $this->findSelectedVendorInvoiceOrFail($invoiceId);
        Gate::authorize('recordPayments', $invoice->vendor);

        $this->feedbackError = null;
        $this->resetValidation();
        $this->payingInvoiceId = $invoice->id;
        $this->resetPaymentForm();
        $this->paymentForm['amount'] = (string) ((int) $invoice->outstanding_amount);
        $this->showPaymentModal = true;
    }

    public function recordInvoicePayment(
        RecordVendorInvoicePayment $recordVendorInvoicePayment,
        UploadVendorInvoicePaymentAttachment $uploadVendorInvoicePaymentAttachment,
        VendorCommunicationLogger $vendorCommunicationLogger
    ): void
    {
        if (! $this->payingInvoiceId) {
            return;
        }

        $invoice = $this->findSelectedVendorInvoiceOrFail($this->payingInvoiceId);
        Gate::authorize('recordPayments', $invoice->vendor);

        $this->feedbackError = null;
        $this->validate($this->paymentRules(), $this->paymentMessages());

        $payment = null;
        try {
            $payment = $recordVendorInvoicePayment(\Illuminate\Support\Facades\Auth::user(), $invoice, $this->paymentPayload());
            $this->attachPaymentUploadedFiles($uploadVendorInvoicePaymentAttachment, $payment);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable) {
            $this->setFeedbackError('Unable to record invoice payment right now.');
            return;
        }

        $this->setFeedback('Invoice payment recorded.');
        $this->closePaymentModal();
        $this->refreshSelectedVendorInsights();

        if ($payment) {
            $invoice->refresh();
            $vendorCommunicationLogger->queueVendorPaymentEvent(
                $invoice,
                'vendor.invoice.payment_recorded',
                [
                    CompanyCommunicationSetting::CHANNEL_EMAIL,
                    CompanyCommunicationSetting::CHANNEL_SMS,
                ],
                'Payment update queued.',
                null,
                [
                    'invoice_number' => (string) $invoice->invoice_number,
                    'payment_amount' => (int) $payment->amount,
                    'payment_date' => optional($payment->payment_date)->toDateString(),
                    'payment_reference' => (string) ($payment->payment_reference ?? ''),
                    'outstanding_after_payment' => (int) $invoice->outstanding_amount,
                    'currency' => strtoupper((string) ($invoice->currency ?: 'NGN')),
                ]
            );

            $vendorCommunicationLogger->queueFinanceTeamEvent(
                $invoice,
                'vendor.internal.payment_recorded',
                [
                    CompanyCommunicationSetting::CHANNEL_IN_APP,
                    CompanyCommunicationSetting::CHANNEL_EMAIL,
                ],
                'Internal finance notification queued.',
                null,
                [
                    'invoice_number' => (string) $invoice->invoice_number,
                    'payment_amount' => (int) $payment->amount,
                    'payment_date' => optional($payment->payment_date)->toDateString(),
                    'payment_reference' => (string) ($payment->payment_reference ?? ''),
                    'outstanding_after_payment' => (int) $invoice->outstanding_amount,
                    'currency' => strtoupper((string) ($invoice->currency ?: 'NGN')),
                ]
            );
        }
    }

    public function openVoidInvoiceModal(int $invoiceId): void
    {
        $invoice = $this->findSelectedVendorInvoiceOrFail($invoiceId);
        Gate::authorize('manageInvoices', $invoice->vendor);

        $this->feedbackError = null;
        $this->resetValidation();
        $this->voidingInvoiceId = $invoice->id;
        $this->voidInvoiceReason = '';
        $this->showVoidInvoiceModal = true;
    }

    public function submitVoidInvoice(VoidVendorInvoice $voidVendorInvoice): void
    {
        if (! $this->voidingInvoiceId) {
            return;
        }

        $invoice = $this->findSelectedVendorInvoiceOrFail($this->voidingInvoiceId);
        Gate::authorize('manageInvoices', $invoice->vendor);
        try {
            $voidVendorInvoice(\Illuminate\Support\Facades\Auth::user(), $invoice, ['reason' => $this->voidInvoiceReason]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable) {
            $this->setFeedbackError('Unable to void invoice right now.');
            return;
        }

        $this->setFeedback('Vendor invoice voided.');
        $this->closeVoidInvoiceModal();
        $this->refreshSelectedVendorInsights();
    }

    public function getCanCreateVendorProperty(): bool
    {
        return Gate::allows('create', Vendor::class);
    }

    public function getCanManageVendorProfileProperty(): bool
    {
        $vendor = $this->selectedVendor;
        if (! $vendor) {
            return false;
        }

        return Gate::allows('update', $vendor) || Gate::allows('delete', $vendor);
    }

    public function getCanManageVendorFinanceProperty(): bool
    {
        $vendor = $this->selectedVendor;

        return $vendor ? Gate::allows('manageInvoices', $vendor) : false;
    }

    public function getCanRecordVendorPaymentsProperty(): bool
    {
        $vendor = $this->selectedVendor;

        return $vendor ? Gate::allows('recordPayments', $vendor) : false;
    }

    public function getCanManageVendorCommunicationsProperty(): bool
    {
        $vendor = $this->selectedVendor;

        return $vendor ? Gate::allows('manageCommunications', $vendor) : false;
    }

    public function getCanExportVendorStatementsProperty(): bool
    {
        $vendor = $this->selectedVendor;

        return $vendor ? Gate::allows('exportStatements', $vendor) : false;
    }

    public function getCanManageProperty(): bool
    {
        // Legacy aggregate flag kept for older template branches that still read canManage.
        return $this->canManageVendorProfile
            || $this->canManageVendorFinance
            || $this->canRecordVendorPayments
            || $this->canManageVendorCommunications;
    }

    public function getSelectedVendorProperty(): ?Vendor
    {
        if (! $this->selectedVendorId) {
            return null;
        }

        return Vendor::query()->find($this->selectedVendorId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getVendorCommunicationLogsProperty(): array
    {
        if (! $this->selectedVendorId) {
            return [];
        }

        return VendorCommunicationLog::query()
            ->where('vendor_id', (int) $this->selectedVendorId)
            ->with(['invoice:id,invoice_number', 'recipient:id,name,email'])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (VendorCommunicationLog $log): array => [
                'id' => (int) $log->id,
                'event' => (string) $log->event,
                'event_label' => $this->vendorCommunicationEventLabel((string) $log->event),
                'audience' => (int) ($log->recipient_user_id ?? 0) > 0 ? 'internal_finance' : 'vendor_external',
                'channel' => (string) $log->channel,
                'status' => (string) $log->status,
                'invoice_number' => (string) ($log->invoice?->invoice_number ?? '-'),
                'recipient' => (string) ($log->recipient?->name ?: $log->recipient_email ?: $log->recipient_phone ?: 'Vendor contact'),
                'message' => (string) ($log->message ?? ''),
                'created_at' => optional($log->created_at)->format('M d, Y H:i'),
                'sent_at' => optional($log->sent_at)->format('M d, Y H:i'),
            ])
            ->all();
    }

    /**
     * @return array{failed:int,queued_stuck:int}
     */
    public function getVendorCommunicationSummaryProperty(): array
    {
        if (! $this->selectedVendorId) {
            return ['failed' => 0, 'queued_stuck' => 0];
        }

        $vendorId = (int) $this->selectedVendorId;
        $cutoff = now()->subMinutes(max(0, (int) $this->vendorCommQueuedOlderThanMinutes));

        return [
            'failed' => VendorCommunicationLog::query()
                ->where('vendor_id', $vendorId)
                ->where('status', 'failed')
                ->count(),
            'queued_stuck' => VendorCommunicationLog::query()
                ->where('vendor_id', $vendorId)
                ->where('status', 'queued')
                ->where('created_at', '<=', $cutoff)
                ->count(),
        ];
    }

    public function sendDueInvoiceReminders(VendorReminderService $vendorReminderService): void
    {
        $vendor = $this->selectedVendor;
        if (! $vendor) {
            return;
        }

        Gate::authorize('manageCommunications', $vendor);
        $this->feedbackError = null;

        $this->validate([
            'reminderDaysAhead' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        $stats = $vendorReminderService->dispatchDueInvoiceReminders(
            companyId: (int) $vendor->company_id,
            vendorId: (int) $vendor->id,
            daysAhead: (int) $this->reminderDaysAhead
        );

        $this->setFeedback(
            "Reminders queued: {$stats['queued']} (scanned {$stats['scanned']}, duplicates {$stats['duplicates']}, missing finance contacts {$stats['missing_recipient']})."
        );
    }

    public function retryVendorCommunication(int $logId, VendorCommunicationRetryService $retryService): void
    {
        $vendor = $this->selectedVendor;
        if (! $vendor) {
            return;
        }

        Gate::authorize('manageCommunications', $vendor);
        $log = VendorCommunicationLog::query()
            ->where('vendor_id', (int) $vendor->id)
            ->find($logId);

        if (! $log) {
            $this->setFeedbackError('Communication log not found.');

            return;
        }

        $after = $retryService->retryLog($log);
        $this->setFeedback('Retry completed with status: '.strtoupper((string) $after->status).'.');
    }

    public function retryFailedVendorCommunications(VendorCommunicationRetryService $retryService): void
    {
        $vendor = $this->selectedVendor;
        if (! $vendor) {
            return;
        }

        Gate::authorize('manageCommunications', $vendor);
        $stats = $retryService->retryFailed(
            companyId: (int) $vendor->company_id,
            vendorId: (int) $vendor->id,
            batchSize: 200
        );

        $this->setFeedback(
            "Vendor retries done. Retried {$stats['retried']}, sent {$stats['sent']}, failed {$stats['failed']}, skipped {$stats['skipped']}."
        );
    }

    public function processQueuedVendorCommunications(VendorCommunicationRetryService $retryService): void
    {
        $vendor = $this->selectedVendor;
        if (! $vendor) {
            return;
        }

        Gate::authorize('manageCommunications', $vendor);
        $olderThan = max(0, (int) $this->vendorCommQueuedOlderThanMinutes);
        $stats = $retryService->processStuckQueued(
            companyId: (int) $vendor->company_id,
            vendorId: (int) $vendor->id,
            olderThanMinutes: $olderThan,
            batchSize: 500
        );

        $this->setFeedback(
            "Vendor queued processing done. Processed {$stats['processed']}, sent {$stats['sent']}, failed {$stats['failed']}, skipped {$stats['skipped']}, remaining queued {$stats['remaining_queued']}."
        );
    }

    public function vendorStatementCsvUrl(int $vendorId): string
    {
        return route('vendors.statement.export.csv', [
            'vendor' => $vendorId,
            'from' => $this->statementDateFrom ?: null,
            'to' => $this->statementDateTo ?: null,
            'invoice_status' => $this->statementInvoiceStatus,
        ]);
    }

    public function vendorStatementPrintUrl(int $vendorId): string
    {
        return route('vendors.statement.print', [
            'vendor' => $vendorId,
            'from' => $this->statementDateFrom ?: null,
            'to' => $this->statementDateTo ?: null,
            'invoice_status' => $this->statementInvoiceStatus,
        ]);
    }

    public function render(): View
    {
        $vendors = $this->readyToLoad
            ? $this->vendorQuery()->paginate($this->perPage)
            : Vendor::query()->whereRaw('1 = 0')->paginate($this->perPage);

        return view('livewire.vendors.vendors-page', [
            'vendors' => $vendors,
            'vendorTypes' => ['supplier', 'contractor', 'service', 'other'],
            'invoiceStatuses' => VendorInvoice::DISPLAY_STATUSES,
            'paymentMethods' => ['cash', 'transfer', 'pos', 'online', 'cheque'],
        ]);
    }

    private function vendorQuery()
    {
        return Vendor::query()
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('contact_person', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhere('phone', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($this->typeFilter !== 'all', fn ($query) => $query->where('vendor_type', $this->typeFilter))
            ->latest('created_at');
    }

    private function findVendorOrFail(int $vendorId): Vendor
    {
        /** @var Vendor $vendor */
        $vendor = Vendor::query()->findOrFail($vendorId);

        return $vendor;
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(): array
    {
        return [
            'name' => $this->nullableString($this->form['name']),
            'vendor_type' => $this->nullableString($this->form['vendor_type']),
            'contact_person' => $this->nullableString($this->form['contact_person']),
            'phone' => $this->nullableString($this->form['phone']),
            'email' => $this->nullableString($this->form['email']),
            'address' => $this->nullableString($this->form['address']),
            'bank_name' => $this->nullableString($this->form['bank_name']),
            'account_name' => $this->nullableString($this->form['account_name']),
            'account_number' => $this->nullableString($this->form['account_number']),
            'notes' => $this->nullableString($this->form['notes']),
            'is_active' => (bool) $this->form['is_active'],
        ];
    }

    private function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'vendor_type' => '',
            'contact_person' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'bank_name' => '',
            'account_name' => '',
            'account_number' => '',
            'notes' => '',
            'is_active' => true,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function fillFormFromVendor(Vendor $vendor): void
    {
        $this->form = [
            'name' => (string) $vendor->name,
            'vendor_type' => (string) ($vendor->vendor_type ?? ''),
            'contact_person' => (string) ($vendor->contact_person ?? ''),
            'phone' => (string) ($vendor->phone ?? ''),
            'email' => (string) ($vendor->email ?? ''),
            'address' => (string) ($vendor->address ?? ''),
            'bank_name' => (string) ($vendor->bank_name ?? ''),
            'account_name' => (string) ($vendor->account_name ?? ''),
            'account_number' => (string) ($vendor->account_number ?? ''),
            'notes' => (string) ($vendor->notes ?? ''),
            'is_active' => (bool) $vendor->is_active,
        ];
    }

    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }

    private function setFeedbackError(string $message): void
    {
        $this->feedbackMessage = null;
        $this->feedbackError = $message;
        $this->feedbackKey++;
    }

    private function loadVendorInsights(Vendor $vendor): void
    {
        /** @var VendorPaymentInsights $insightsService */
        $insightsService = app(VendorPaymentInsights::class);
        $insights = $insightsService->forVendor($vendor);

        $this->vendorTotalPaid = (int) $insights['total_paid'];
        $this->vendorPaymentsCount = (int) $insights['payments_count'];
        $this->vendorLastPaymentDate = $insights['last_payment_date']
            ? (string) \Illuminate\Support\Carbon::parse($insights['last_payment_date'])->format('M d, Y')
            : null;

        $this->vendorRecentPayments = $insights['recent_payments']
            ->map(function (Expense $expense): array {
                return [
                    'expense_code' => $expense->expense_code,
                    'title' => $expense->title,
                    'amount' => $expense->amount,
                    'expense_date' => $expense->expense_date?->format('M d, Y'),
                    'payment_method' => $expense->payment_method,
                    'department_name' => $expense->department?->name,
                    'status' => $expense->status,
                ];
            })
            ->all();

        $this->vendorTotalInvoiced = (int) ($insights['total_invoiced'] ?? 0);
        $this->vendorTotalInvoicePaid = (int) ($insights['total_invoice_paid'] ?? 0);
        $this->vendorOutstandingBalance = (int) ($insights['total_outstanding'] ?? 0);
        $this->vendorInvoicesCount = (int) ($insights['invoices_count'] ?? 0);

        $this->vendorInvoices = $insights['invoices']
            ->map(function (VendorInvoice $invoice): array {
                $displayStatus = $this->resolveInvoiceDisplayStatus($invoice);
                $dueMeta = $this->resolveInvoiceDueMeta($invoice, $displayStatus);

                return [
                    'id' => (int) $invoice->id,
                    'invoice_number' => (string) $invoice->invoice_number,
                    'invoice_date' => optional($invoice->invoice_date)->format('M d, Y'),
                    'invoice_date_sort' => optional($invoice->invoice_date)->toDateString(),
                    'due_date' => optional($invoice->due_date)->format('M d, Y'),
                    'currency' => (string) ($invoice->currency ?: 'NGN'),
                    'total_amount' => (int) $invoice->total_amount,
                    'paid_amount' => (int) $invoice->paid_amount,
                    'outstanding_amount' => (int) $invoice->outstanding_amount,
                    'status' => (string) $invoice->status,
                    'display_status' => $displayStatus,
                    'due_countdown' => $dueMeta['due_countdown'],
                    'due_days_delta' => $dueMeta['due_days_delta'],
                    'is_overdue' => $displayStatus === VendorInvoice::STATUS_OVERDUE,
                    'description' => (string) ($invoice->description ?? ''),
                    'notes' => (string) ($invoice->notes ?? ''),
                    'payment_count' => (int) $invoice->payments->count(),
                    // Keep historical "paid in parts" visibility even after an invoice reaches paid status.
                    'was_partially_settled' => ((int) $invoice->paid_amount > 0 && (int) $invoice->outstanding_amount > 0)
                        || ((int) $invoice->payments->count() > 1 && (int) $invoice->paid_amount > 0),
                    'attachments' => $invoice->attachments
                        ->map(fn ($attachment): array => [
                            'id' => (int) $attachment->id,
                            'original_name' => (string) $attachment->original_name,
                            'mime_type' => strtoupper((string) $attachment->mime_type),
                            'file_size_kb' => number_format(((int) $attachment->file_size) / 1024, 1),
                            'uploaded_at' => optional($attachment->uploaded_at)->format('M d, Y H:i'),
                        ])
                        ->all(),
                    'attachment_count' => (int) $invoice->attachments->count(),
                    'payment_attachment_count' => (int) $invoice->payments->sum(
                        fn (VendorInvoicePayment $payment): int => (int) $payment->attachments->count()
                    ),
                    'can_receive_payment' => (string) $invoice->status !== VendorInvoice::STATUS_VOID
                        && (int) $invoice->outstanding_amount > 0,
                ];
            })
            ->all();

        $invoiceCollection = collect($this->vendorInvoices);
        $this->vendorUnpaidInvoicesCount = (int) $invoiceCollection
            ->where('display_status', VendorInvoice::STATUS_UNPAID)
            ->count();
        $this->vendorPartPaidInvoicesCount = (int) $invoiceCollection
            ->where('was_partially_settled', true)
            ->count();
        // Count partial payment actions across invoice lifecycle, including invoices
        // that are now fully paid but were settled in multiple installments.
        $this->vendorPartPaymentsCount = (int) $invoiceCollection
            ->sum(function (array $invoice): int {
                $wasPartiallySettled = (bool) ($invoice['was_partially_settled'] ?? false);
                if (! $wasPartiallySettled) {
                    return 0;
                }

                $paymentCount = (int) ($invoice['payment_count'] ?? 0);
                $outstandingAmount = (int) ($invoice['outstanding_amount'] ?? 0);

                if ($outstandingAmount > 0) {
                    return max($paymentCount, 1);
                }

                return max($paymentCount - 1, 0);
            });
        $this->vendorOverdueInvoicesCount = (int) $invoiceCollection
            ->where('display_status', VendorInvoice::STATUS_OVERDUE)
            ->count();
        $this->vendorPaidInvoicesCount = (int) $invoiceCollection
            ->where('display_status', VendorInvoice::STATUS_PAID)
            ->count();

        $this->vendorStatementTimeline = $insights['statement_timeline']
            ->map(function (array $event): array {
                return [
                    'event_type' => (string) ($event['event_type'] ?? 'event'),
                    'event_subtype' => (string) ($event['event_subtype'] ?? ''),
                    'title' => (string) ($event['title'] ?? 'Vendor event'),
                    'amount' => (int) ($event['amount'] ?? 0),
                    'happened_at' => (string) ($event['happened_at'] ?? ''),
                    'happened_at_label' => ! empty($event['happened_at'])
                        ? (string) \Illuminate\Support\Carbon::parse((string) $event['happened_at'])->format('M d, Y')
                        : '-',
                    'meta' => is_array($event['meta'] ?? null) ? $event['meta'] : [],
                ];
            })
            ->all();
    }

    private function resetVendorInsights(): void
    {
        $this->vendorTotalPaid = 0;
        $this->vendorPaymentsCount = 0;
        $this->vendorLastPaymentDate = null;
        $this->vendorRecentPayments = [];
        $this->vendorTotalInvoiced = 0;
        $this->vendorTotalInvoicePaid = 0;
        $this->vendorOutstandingBalance = 0;
        $this->vendorInvoicesCount = 0;
        $this->vendorUnpaidInvoicesCount = 0;
        $this->vendorPartPaidInvoicesCount = 0;
        $this->vendorPartPaymentsCount = 0;
        $this->vendorPaidInvoicesCount = 0;
        $this->vendorOverdueInvoicesCount = 0;
        $this->vendorInvoices = [];
        $this->vendorStatementTimeline = [];
    }

    private function resolveInvoiceDisplayStatus(VendorInvoice $invoice): string
    {
        if ((string) $invoice->status === VendorInvoice::STATUS_VOID) {
            return VendorInvoice::STATUS_VOID;
        }

        if ((int) $invoice->outstanding_amount <= 0) {
            return VendorInvoice::STATUS_PAID;
        }

        $dueDate = $invoice->due_date?->copy()->startOfDay();
        if ($dueDate && $dueDate->lt(now()->startOfDay())) {
            return VendorInvoice::STATUS_OVERDUE;
        }

        return (int) $invoice->paid_amount > 0
            ? VendorInvoice::STATUS_PART_PAID
            : VendorInvoice::STATUS_UNPAID;
    }

    /**
     * @return array{due_countdown: ?string, due_days_delta: ?int}
     */
    private function resolveInvoiceDueMeta(VendorInvoice $invoice, string $displayStatus): array
    {
        if ((string) $invoice->status === VendorInvoice::STATUS_VOID || $displayStatus === VendorInvoice::STATUS_PAID) {
            return ['due_countdown' => null, 'due_days_delta' => null];
        }

        $dueDate = $invoice->due_date?->copy()->startOfDay();
        if (! $dueDate) {
            return ['due_countdown' => null, 'due_days_delta' => null];
        }

        $daysDelta = Carbon::now()->startOfDay()->diffInDays($dueDate, false);
        if ($daysDelta > 1) {
            return ['due_countdown' => 'Due in '.$daysDelta.' days', 'due_days_delta' => $daysDelta];
        }

        if ($daysDelta === 1) {
            return ['due_countdown' => 'Due tomorrow', 'due_days_delta' => $daysDelta];
        }

        if ($daysDelta === 0) {
            return ['due_countdown' => 'Due today', 'due_days_delta' => $daysDelta];
        }

        return ['due_countdown' => 'Overdue by '.abs($daysDelta).' days', 'due_days_delta' => $daysDelta];
    }

    private function vendorCommunicationEventLabel(string $event): string
    {
        return match ($event) {
            'vendor.invoice.payment_recorded' => 'Payment Posted to Vendor',
            'vendor.internal.payment_recorded' => 'Payment Posted to Finance Inbox',
            'vendor.internal.overdue.reminder' => 'Overdue Reminder to Finance',
            'vendor.internal.due_today.reminder' => 'Due Today Reminder to Finance',
            'vendor.internal.due_soon.reminder' => 'Due Soon Reminder to Finance',
            'vendor.invoice.created' => 'Invoice Created',
            'vendor.invoice.voided' => 'Invoice Voided',
            default => ucwords(str_replace(['.', '_'], ' ', $event)),
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function normalizeValidationErrors(array $errors): array
    {
        $mapped = [];
        $formFields = [
            'name',
            'vendor_type',
            'contact_person',
            'phone',
            'email',
            'address',
            'bank_name',
            'account_name',
            'account_number',
            'notes',
            'is_active',
        ];
        $invoiceFields = [
            'invoice_number',
            'invoice_date',
            'due_date',
            'total_amount',
            'description',
            'notes',
        ];
        $paymentFields = [
            'amount',
            'payment_date',
            'payment_method',
            'payment_reference',
            'notes',
        ];

        foreach ($errors as $key => $messages) {
            if (
                str_starts_with($key, 'form.')
                || str_starts_with($key, 'invoiceForm.')
                || str_starts_with($key, 'paymentForm.')
                || str_starts_with($key, 'newInvoiceAttachments.')
                || str_starts_with($key, 'newPaymentAttachments.')
                || $key === 'voidInvoiceReason'
            ) {
                $mapped[$key] = $messages;
                continue;
            }

            if (in_array($key, $formFields, true)) {
                $mapped['form.'.$key] = $messages;
                continue;
            }

            if (in_array($key, $invoiceFields, true)) {
                $mapped['invoiceForm.'.$key] = $messages;
                continue;
            }

            if (in_array($key, $paymentFields, true)) {
                $mapped['paymentForm.'.$key] = $messages;
                continue;
            }

            if ($key === 'reason') {
                $mapped['voidInvoiceReason'] = $messages;
                continue;
            }

            $mapped[$key] = $messages;
        }

        return $mapped;
    }

    public function vendorInvoiceAttachmentDownloadUrlById(int $attachmentId): string
    {
        return route('vendors.attachments.invoices.download', ['attachment' => $attachmentId]);
    }

    public function vendorPaymentAttachmentDownloadUrlById(int $attachmentId): string
    {
        return route('vendors.attachments.payments.download', ['attachment' => $attachmentId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredVendorInvoicesProperty(): array
    {
        return collect($this->vendorInvoices)
            ->filter(function (array $invoice): bool {
                if (
                    $this->invoiceStatusFilter !== 'all'
                    && (string) ($invoice['display_status'] ?? '') !== $this->invoiceStatusFilter
                ) {
                    return false;
                }

                if ($this->invoiceSearch === '') {
                    return true;
                }

                $needle = mb_strtolower($this->invoiceSearch);
                $haystacks = [
                    (string) ($invoice['invoice_number'] ?? ''),
                    (string) ($invoice['description'] ?? ''),
                    (string) ($invoice['notes'] ?? ''),
                    (string) ($invoice['status'] ?? ''),
                    (string) ($invoice['display_status'] ?? ''),
                ];

                foreach ($haystacks as $value) {
                    if (str_contains(mb_strtolower($value), $needle)) {
                        return true;
                    }
                }

                return false;
            })
            ->sortByDesc(fn (array $invoice): string => (string) ($invoice['invoice_date_sort'] ?? ''))
            ->values()
            ->all();
    }

    private function refreshSelectedVendorInsights(): void
    {
        $vendor = $this->selectedVendor;
        if (! $vendor) {
            return;
        }

        $this->loadVendorInsights($vendor);
    }

    private function attachInvoiceUploadedFiles(
        UploadVendorInvoiceAttachment $uploadVendorInvoiceAttachment,
        VendorInvoice $invoice
    ): void {
        if (empty($this->newInvoiceAttachments)) {
            return;
        }

        foreach ($this->newInvoiceAttachments as $file) {
            if ($file) {
                $uploadVendorInvoiceAttachment(\Illuminate\Support\Facades\Auth::user(), $invoice, $file);
            }
        }

        $this->newInvoiceAttachments = [];
    }

    private function attachPaymentUploadedFiles(
        UploadVendorInvoicePaymentAttachment $uploadVendorInvoicePaymentAttachment,
        VendorInvoicePayment $payment
    ): void {
        if (empty($this->newPaymentAttachments)) {
            return;
        }

        foreach ($this->newPaymentAttachments as $file) {
            if ($file) {
                $uploadVendorInvoicePaymentAttachment(\Illuminate\Support\Facades\Auth::user(), $payment, $file);
            }
        }

        $this->newPaymentAttachments = [];
    }

    private function findSelectedVendorInvoiceOrFail(int $invoiceId): VendorInvoice
    {
        $vendor = $this->selectedVendor;
        abort_if(! $vendor, 404);

        /** @var VendorInvoice $invoice */
        $invoice = VendorInvoice::query()
            ->where('vendor_id', $vendor->id)
            ->findOrFail($invoiceId);

        return $invoice;
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(): array
    {
        return [
            'invoice_number' => trim((string) ($this->invoiceForm['invoice_number'] ?? '')),
            'invoice_date' => trim((string) ($this->invoiceForm['invoice_date'] ?? '')),
            'due_date' => $this->nullableString($this->invoiceForm['due_date'] ?? null),
            'total_amount' => (int) ($this->invoiceForm['total_amount'] ?: 0),
            'description' => $this->nullableString($this->invoiceForm['description'] ?? null),
            'notes' => $this->nullableString($this->invoiceForm['notes'] ?? null),
        ];
    }

    private function resetInvoiceForm(): void
    {
        $this->invoiceForm = [
            'invoice_number' => '',
            'invoice_date' => now()->toDateString(),
            'due_date' => '',
            'total_amount' => '',
            'description' => '',
            'notes' => '',
        ];
        $this->newInvoiceAttachments = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(): array
    {
        return [
            'amount' => (int) ($this->paymentForm['amount'] ?: 0),
            'payment_date' => trim((string) ($this->paymentForm['payment_date'] ?? '')),
            'payment_method' => $this->nullableString($this->paymentForm['payment_method'] ?? null),
            'payment_reference' => $this->nullableString($this->paymentForm['payment_reference'] ?? null),
            'notes' => $this->nullableString($this->paymentForm['notes'] ?? null),
        ];
    }

    private function resetPaymentForm(): void
    {
        $this->paymentForm = [
            'amount' => '',
            'payment_date' => now()->toDateString(),
            'payment_method' => '',
            'payment_reference' => '',
            'notes' => '',
        ];
        $this->newPaymentAttachments = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceRules(): array
    {
        return [
            'invoiceForm.invoice_number' => ['required', 'string', 'max:80'],
            'invoiceForm.invoice_date' => ['required', 'date'],
            'invoiceForm.due_date' => ['nullable', 'date', 'after_or_equal:invoiceForm.invoice_date'],
            'invoiceForm.total_amount' => ['required', 'integer', 'min:1'],
            'invoiceForm.description' => ['nullable', 'string', 'max:2000'],
            'invoiceForm.notes' => ['nullable', 'string', 'max:2000'],
            'newInvoiceAttachments.*' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function invoiceMessages(): array
    {
        return [
            'invoiceForm.invoice_number.required' => 'Invoice number is required.',
            'invoiceForm.invoice_date.required' => 'Invoice date is required.',
            'invoiceForm.due_date.after_or_equal' => 'Due date cannot be before invoice date.',
            'invoiceForm.total_amount.required' => 'Total amount is required.',
            'invoiceForm.total_amount.min' => 'Total amount must be at least 1.',
            'invoiceForm.description.max' => 'Description cannot exceed 2000 characters.',
            'invoiceForm.notes.max' => 'Notes cannot exceed 2000 characters.',
            'newInvoiceAttachments.*.max' => 'Each invoice attachment must be 10MB or less.',
            'newInvoiceAttachments.*.mimes' => 'Only PDF and image files are supported.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentRules(): array
    {
        return [
            'paymentForm.amount' => ['required', 'integer', 'min:1'],
            'paymentForm.payment_date' => ['required', 'date'],
            'paymentForm.payment_method' => ['nullable', Rule::in(['cash', 'transfer', 'pos', 'online', 'cheque'])],
            'paymentForm.payment_reference' => ['nullable', 'string', 'max:80'],
            'paymentForm.notes' => ['nullable', 'string', 'max:2000'],
            'newPaymentAttachments.*' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function paymentMessages(): array
    {
        return [
            'paymentForm.amount.required' => 'Payment amount is required.',
            'paymentForm.amount.min' => 'Payment amount must be at least 1.',
            'paymentForm.payment_date.required' => 'Payment date is required.',
            'paymentForm.payment_method.in' => 'Select a valid payment method.',
            'paymentForm.payment_reference.max' => 'Payment reference cannot exceed 80 characters.',
            'paymentForm.notes.max' => 'Notes cannot exceed 2000 characters.',
            'newPaymentAttachments.*.max' => 'Each payment attachment must be 10MB or less.',
            'newPaymentAttachments.*.mimes' => 'Only PDF and image files are supported.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:180'],
            'form.vendor_type' => ['required', Rule::in(['supplier', 'contractor', 'service', 'other'])],
            'form.contact_person' => ['required', 'string', 'max:180'],
            'form.phone' => ['required', 'string', 'max:50'],
            'form.email' => ['required', 'email', 'max:255'],
            'form.address' => ['required', 'string', 'max:1000'],
            'form.bank_name' => ['required', 'string', 'max:180'],
            'form.account_name' => ['required', 'string', 'max:180'],
            'form.account_number' => ['required', 'string', 'max:80'],
            'form.notes' => ['required', 'string', 'max:2000'],
            'form.is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'form.name.required' => 'Vendor name is required.',
            'form.vendor_type.required' => 'Vendor type is required.',
            'form.contact_person.required' => 'Contact person is required.',
            'form.phone.required' => 'Phone is required.',
            'form.email.required' => 'Email is required.',
            'form.address.required' => 'Address is required.',
            'form.bank_name.required' => 'Bank name is required.',
            'form.account_name.required' => 'Account name is required.',
            'form.account_number.required' => 'Account number is required.',
            'form.notes.required' => 'Notes are required.',
            'form.is_active.required' => 'Status is required.',
            'form.vendor_type.in' => 'Please select a valid vendor type.',
            'form.email.email' => 'Please enter a valid email address.',
            'form.name.max' => 'Vendor name cannot exceed 180 characters.',
            'form.contact_person.max' => 'Contact person cannot exceed 180 characters.',
            'form.phone.max' => 'Phone cannot exceed 12 characters.',
            'form.email.max' => 'Email cannot exceed 255 characters.',
            'form.address.max' => 'Address cannot exceed 1000 characters.',
            'form.bank_name.max' => 'Bank name cannot exceed 180 characters.',
            'form.account_name.max' => 'Account name cannot exceed 180 characters.',
            'form.account_number.max' => 'Account number cannot exceed 80 characters.',
            'form.notes.max' => 'Notes cannot exceed 2000 characters.',
        ];
    }
}
