<?php

namespace App\Policies;

use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Models\User;

class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return app(PurchaseOrderPolicy::class)->viewAny($user);
    }

    public function view(User $user, GoodsReceipt $receipt): bool
    {
        return $this->viewAny($user)
            && (int) $user->company_id === (int) $receipt->company_id;
    }
}

