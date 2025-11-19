<?php

namespace App\Services;

use PDO;
use RuntimeException;

class ShareInventoryService
{
    public function reserve(PDO $pdo, int $projectId, int $requiredShares): array
    {
        if ($requiredShares <= 0) {
            throw new RuntimeException('Share quantity must be greater than zero.');
        }

        $stmt = $pdo->prepare('SELECT * FROM share_batches WHERE project_id = :project AND status = :status AND available_shares > 0 ORDER BY certificate_start_no ASC, id ASC FOR UPDATE');
        $stmt->execute(['project' => $projectId, 'status' => 'active']);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$batches) {
            throw new RuntimeException('No active share batches found for the project.');
        }

        $remaining = $requiredShares;
        $allocations = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = (int)$batch['available_shares'];
            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);
            $consumed = (int)$batch['total_shares'] - $available;
            $certificateFrom = (int)$batch['certificate_start_no'] + $consumed;
            $certificateTo = $certificateFrom + $take - 1;

            $allocations[] = [
                'batch_id' => (int)$batch['id'],
                'project_id' => $projectId,
                'shares' => $take,
                'share_price' => (float)$batch['share_price'],
                'certificate_from' => $certificateFrom,
                'certificate_to' => $certificateTo,
            ];

            $update = $pdo->prepare('UPDATE share_batches SET available_shares = available_shares - :qty, updated_at = NOW() WHERE id = :id');
            $update->execute(['qty' => $take, 'id' => $batch['id']]);

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new RuntimeException('Insufficient share inventory for the request.');
        }

        return $allocations;
    }
}
