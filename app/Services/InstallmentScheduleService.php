<?php

namespace App\Services;

use App\Models\Installment;
use DateInterval;
use DateTimeImmutable;

class InstallmentScheduleService
{
    public function buildForSale(int $saleId, int $customerId, int $projectId, float $remainingAmount, int $months, string $startDate): array
    {
        if ($remainingAmount <= 0 || $months <= 0) {
            return [];
        }

        $baseAmount = round($remainingAmount / $months, 2);
        $schedule = [];
        $date = new DateTimeImmutable($startDate);

        for ($i = 1; $i <= $months; $i++) {
            $dueDate = $date->add(new DateInterval('P' . $i . 'M'))->format('Y-m-d');
            $schedule[] = Installment::create([
                'related_type' => 'share_sale',
                'related_id' => $saleId,
                'customer_id' => $customerId,
                'project_id' => $projectId,
                'due_date' => $dueDate,
                'amount' => $baseAmount,
                'status' => 'pending',
                'notes' => 'Auto generated installment',
            ]);
        }

        return $schedule;
    }
}
