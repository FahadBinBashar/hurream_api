<?php

namespace App\Services;

class PackageBenefitService
{
    public function normalize(array $benefits): array
    {
        return [
            'free_nights' => (int)($benefits['free_nights'] ?? 0),
            'discount_percent' => (float)($benefits['discount_percent'] ?? 0),
            'voucher_value' => (float)($benefits['voucher_value'] ?? 0),
            'gifts' => $benefits['gifts'] ?? null,
            'notes' => $benefits['notes'] ?? null,
        ];
    }

    public function snapshot(array $package): array
    {
        $benefits = $package['benefits'] ?? [];
        if (is_string($benefits)) {
            $decoded = json_decode($benefits, true);
            if (is_array($decoded)) {
                $benefits = $decoded;
            }
        }

        return $this->normalize($benefits);
    }
}
