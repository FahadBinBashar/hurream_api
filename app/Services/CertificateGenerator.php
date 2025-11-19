<?php

namespace App\Services;

use PDO;
use RuntimeException;

class CertificateGenerator
{
    public function generate(PDO $pdo, int $projectId, int $units): array
    {
        if ($units <= 0) {
            throw new RuntimeException('Certificate generation requires at least one unit.');
        }

        $stmt = $pdo->prepare('SELECT certificate_prefix, next_certificate_no FROM projects WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            throw new RuntimeException('Project not found for certificate generation.');
        }

        $start = (int)$project['next_certificate_no'];
        $end = $start + $units - 1;
        $prefix = strtoupper($project['certificate_prefix'] ?? 'HRM');
        $certificateNo = sprintf('%s-%06d', $prefix, $start);

        $update = $pdo->prepare('UPDATE projects SET next_certificate_no = :next WHERE id = :id');
        $update->execute(['next' => $end + 1, 'id' => $projectId]);

        return [
            'certificate_no' => $certificateNo,
            'certificate_start' => $start,
            'certificate_end' => $end,
        ];
    }
}
