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

    public function sendReceiptNotification(array $customer, string $receiptNo, string $receiptUrl): void
    {
        $message = sprintf(
            'Payment received. Receipt %s is available at %s',
            $receiptNo,
            $receiptUrl
        );

        AuditLogger::log(
            Auth::user(),
            'notify',
            'share_sale_payments',
            'receipt',
            $customer['id'] ?? null,
            ['message' => $message, 'channel' => 'sms_whatsapp'],
            null,
            null
        );
    }
}
