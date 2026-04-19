<?php

namespace App\Enums;

enum AccountingCategory: string
{
    case SpendOperations = 'spend_operations';
    case SpendTravel = 'spend_travel';
    case SpendUtilities = 'spend_utilities';
    case SpendSoftware = 'spend_software';
    case SpendProcurement = 'spend_procurement';
    case SpendMaintenance = 'spend_maintenance';
    case SpendTraining = 'spend_training';
    case VendorPayment = 'vendor_payment';
    case StaffReimbursement = 'staff_reimbursement';
    case PurchaseOrder = 'purchase_order';
    case VendorInvoice = 'vendor_invoice';
    case PettyCash = 'petty_cash';

    public function label(): string
    {
        return match ($this) {
            self::SpendOperations => 'Operations',
            self::SpendTravel => 'Travel',
            self::SpendUtilities => 'Utilities',
            self::SpendSoftware => 'Software',
            self::SpendProcurement => 'Procurement',
            self::SpendMaintenance => 'Maintenance',
            self::SpendTraining => 'Training',
            self::VendorPayment => 'Vendor payment',
            self::StaffReimbursement => 'Staff reimbursement',
            self::PurchaseOrder => 'Purchase order',
            self::VendorInvoice => 'Vendor invoice',
            self::PettyCash => 'Petty cash',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SpendOperations => 'Day-to-day running costs.',
            self::SpendTravel => 'Trips, transport, lodging, and field movement.',
            self::SpendUtilities => 'Power, internet, water, and similar bills.',
            self::SpendSoftware => 'Software tools, licenses, and subscriptions.',
            self::SpendProcurement => 'Items, supplies, stock, and equipment purchases.',
            self::SpendMaintenance => 'Repairs, servicing, and upkeep.',
            self::SpendTraining => 'Courses, workshops, and staff development.',
            self::VendorPayment => 'Payment made to an approved vendor.',
            self::StaffReimbursement => 'Money paid back to staff for approved spend.',
            self::PurchaseOrder => 'Purchase commitment before payment.',
            self::VendorInvoice => 'Supplier invoice that will be paid or matched.',
            self::PettyCash => 'Cash payment or petty cash movement.',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $category): string => $category->value,
            self::cases()
        );
    }

    /**
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $category): array => [
                'key' => $category->value,
                'label' => $category->label(),
                'description' => $category->description(),
            ],
            self::cases()
        );
    }

    public static function normalize(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, self::values(), true) ? $normalized : null;
    }

    public static function labelFor(?string $value): string
    {
        $category = self::tryFrom((string) $value);

        return $category?->label() ?? 'Not set';
    }
}
