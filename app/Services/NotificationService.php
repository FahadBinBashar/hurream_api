<?php

namespace App\Services;

use App\Support\AuditLogger;
use App\Support\Auth;

class NotificationService
{
    public function sendSaleConfirmation(array $sale, array $customer): void
    {
        $message = sprintf(
            'Share sale %s confirmed for %s (%s shares)',
            $sale['invoice_no'] ?? 'N/A',
            $customer['name'] ?? 'Customer',
            $sale['total_shares'] ?? 0
        );

        AuditLogger::log(
            Auth::user(),
            'notify',
            'share_sales',
            'share_sale',
            $sale['id'] ?? null,
            ['message' => $message],
            null,
            null
        );
    }
}
