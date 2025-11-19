<?php

namespace App\Services;

use RuntimeException;

class CertificateGenerator
{
    public function fromAllocations(array $allocations, string $prefix = 'HRM'): array
    {
        if (empty($allocations)) {
            throw new RuntimeException('Certificate generation requires share allocations.');
        }

        $start = null;
        $end = null;

        foreach ($allocations as $allocation) {
            $allocationStart = isset($allocation['certificate_from']) ? (int)$allocation['certificate_from'] : null;
            $allocationEnd = isset($allocation['certificate_to']) ? (int)$allocation['certificate_to'] : null;

            if ($allocationStart === null || $allocationEnd === null) {
                continue;
            }

            $start = $start === null ? $allocationStart : min($start, $allocationStart);
            $end = $end === null ? $allocationEnd : max($end, $allocationEnd);
        }

        if ($start === null || $end === null) {
            throw new RuntimeException('Unable to determine certificate range from allocations.');
        }

        $prefix = strtoupper($prefix ?: 'HRM');
        $certificateNo = sprintf('%s-%06d', $prefix, $start);

        return [
            'certificate_no' => $certificateNo,
            'certificate_start' => $start,
            'certificate_end' => $end,
        ];
    }
}
