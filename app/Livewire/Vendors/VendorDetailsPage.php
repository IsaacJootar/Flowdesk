<?php

namespace App\Livewire\Vendors;

use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Vendor Profile')]
class VendorDetailsPage extends VendorsPage
{
    public function mount(): void
    {
        parent::mount();

        $vendorParam = request()->route('vendor');
        $vendor = $vendorParam instanceof Vendor
            ? $vendorParam
            : Vendor::query()->findOrFail((int) $vendorParam);

        $this->readyToLoad = true;
        $this->showDetails((int) $vendor->id);
        $this->showDetailPanel = false;
    }

    public function render(): View
    {
        return view('livewire.vendors.vendor-details-page', [
            'vendor' => $this->selectedVendor,
            'vendorTypes' => ['supplier', 'contractor', 'service', 'other'],
            'invoiceStatuses' => VendorInvoice::DISPLAY_STATUSES,
            'paymentMethods' => ['cash', 'transfer', 'pos', 'online', 'cheque'],
        ]);
    }
}

