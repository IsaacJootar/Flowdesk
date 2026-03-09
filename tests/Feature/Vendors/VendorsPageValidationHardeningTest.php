<?php

namespace Tests\Feature\Vendors;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorCommunicationLog;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use App\Livewire\Vendors\VendorsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class VendorsPageValidationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendors_page_normalizes_tampered_filters(): void
    {
        [$company, $department] = $this->createCompanyContext('Vendor Filter Harden');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner);

        Livewire::test(VendorsPage::class)
            ->set('statusFilter', 'unexpected')
            ->assertSet('statusFilter', 'all')
            ->set('typeFilter', 'marketplace')
            ->assertSet('typeFilter', 'all')
            ->set('perPage', 999)
            ->assertSet('perPage', 10)
            ->set('invoiceSearch', '  INV-001 ')
            ->assertSet('invoiceSearch', 'INV-001')
            ->set('invoiceStatusFilter', 'not-a-real-status')
            ->assertSet('invoiceStatusFilter', 'all')
            ->set('statementDateFrom', '2026/03/01')
            ->assertSet('statementDateFrom', '')
            ->set('statementDateTo', '2026-99-99')
            ->assertSet('statementDateTo', '')
            ->set('statementDateFrom', '2026-03-10')
            ->set('statementDateTo', '2026-03-01')
            ->assertSet('statementDateTo', '')
            ->set('statementInvoiceStatus', 'invalid')
            ->assertSet('statementInvoiceStatus', 'all')
            ->set('reminderDaysAhead', -4)
            ->assertSet('reminderDaysAhead', 0)
            ->set('reminderDaysAhead', 99)
            ->assertSet('reminderDaysAhead', 30)
            ->set('vendorCommunicationPerPage', 111)
            ->assertSet('vendorCommunicationPerPage', 10)
            ->set('vendorCommQueuedOlderThanMinutes', -2)
            ->assertSet('vendorCommQueuedOlderThanMinutes', 0)
            ->set('vendorCommQueuedOlderThanMinutes', 'invalid')
            ->assertSet('vendorCommQueuedOlderThanMinutes', 2)
            ->set('vendorCommQueuedOlderThanMinutes', 9000)
            ->assertSet('vendorCommQueuedOlderThanMinutes', 1440);
    }

    public function test_retry_vendor_communication_is_scoped_to_selected_vendor(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Vendor Retry Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('Vendor Retry Scope B');

        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);
        $ownerB = $this->createUser($companyB, $departmentB, UserRole::Owner->value);

        $vendorA = $this->createVendor($companyA);
        $vendorB = $this->createVendor($companyB);
        $invoiceB = $this->createInvoice($companyB, $vendorB);

        $foreignLog = VendorCommunicationLog::query()->create([
            'company_id' => $companyB->id,
            'vendor_id' => $vendorB->id,
            'vendor_invoice_id' => $invoiceB->id,
            'recipient_user_id' => $ownerB->id,
            'event' => 'vendor.internal.payment_recorded',
            'channel' => 'in_app',
            'status' => 'failed',
            'message' => 'Failed first attempt.',
            'recipient_email' => $ownerB->email,
            'reminder_date' => now()->toDateString(),
        ]);

        $this->actingAs($ownerA);

        Livewire::test(VendorsPage::class)
            ->call('showDetails', $vendorA->id)
            ->call('retryVendorCommunication', $foreignLog->id)
            ->assertSet('feedbackError', 'Communication log not found.');
    }

    /**
     * @return array{0:Company,1:Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+vendor-page@example.test',
            'is_active' => true,
        ]);

        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'Finance',
            'code' => 'FIN',
            'is_active' => true,
        ]);

        return [$company, $department];
    }

    private function createUser(Company $company, Department $department, string $role): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createVendor(Company $company): Vendor
    {
        return Vendor::query()->create([
            'company_id' => $company->id,
            'name' => 'Vendor '.Str::lower(Str::random(6)),
            'vendor_type' => 'supplier',
            'contact_person' => 'Vendor Contact',
            'phone' => '08000000000',
            'email' => Str::lower(Str::random(6)).'@vendor.test',
            'address' => 'Vendor Address',
            'bank_name' => 'Example Bank',
            'bank_code' => '999',
            'account_name' => 'Vendor Account',
            'account_number' => (string) random_int(10000000, 99999999),
            'notes' => 'Vendor seed',
            'is_active' => true,
        ]);
    }

    private function createInvoice(Company $company, Vendor $vendor): VendorInvoice
    {
        return VendorInvoice::query()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'INV-'.Str::upper(Str::random(6)),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'currency' => 'NGN',
            'total_amount' => 120000,
            'paid_amount' => 0,
            'outstanding_amount' => 120000,
            'status' => VendorInvoice::STATUS_UNPAID,
            'description' => 'Testing invoice',
            'notes' => 'Testing notes',
        ]);
    }
}
