<?php

declare(strict_types=1);

namespace App\Contracts\Billing;

use App\Models\BillingOrder;
use App\Models\BillingRefund;

interface PaymentGateway
{
    /** @return array{id: string, url: string} */
    public function checkout(BillingOrder $order, string $successUrl, string $cancelUrl): array;

    /** @return array{id: string} */
    public function refund(BillingRefund $refund): array;
}
