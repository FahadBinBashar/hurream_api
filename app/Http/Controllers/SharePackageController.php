<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\PackageBenefit;
use App\Models\SharePackage;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\Env;
use App\Support\Validator;
use PDO;

class SharePackageController extends Controller
{
    private const STATUSES = ['active', 'inactive'];

    public function index(Request $request)
    {
        $pdo = Database::connection();
        $conditions = [];
        $params = [];
        if ($status = $request->input('status')) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $sql = 'SELECT * FROM share_packages';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY package_price DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $packageMap = $this->mapBenefits($packages);

        return $this->json([
            'data' => array_map(function (array $package) use ($packageMap) {
                $package['benefits'] = $packageMap[$package['id']] ?? [];
                return $package;
            }, $packages),
            'meta' => [
                'total' => count($packages),
                'active' => $this->countByStatus($pdo, 'active'),
                'inactive' => $this->countByStatus($pdo, 'inactive'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        if ($response = $this->ensureRole(['admin'])) {
            return $response;
        }

        if ($response = $this->validatePackage($request->all(), null)) {
            return $response;
        }

        [$payload, $benefits] = $this->preparePayload($request->all());
        $package = SharePackage::create($payload);
        $this->syncBenefits((int)$package['id'], $benefits);

        AuditLogger::log(Auth::user(), 'create', 'share_packages', 'share_package', (int)$package['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json([
            'data' => $this->packageWithBenefits((int)$package['id']),
        ], 201);
    }

    public function show(Request $request, array $params)
    {
        $package = $this->packageWithBenefits((int)$params['id']);
        if (!$package) {
            return $this->json(['message' => 'Package not found'], 404);
        }

        return $this->json(['data' => $package]);
    }

    public function update(Request $request, array $params)
    {
        if ($response = $this->ensureRole(['admin'])) {
            return $response;
        }

        $package = SharePackage::find((int)$params['id']);
        if (!$package) {
            return $this->json(['message' => 'Package not found'], 404);
        }

        if ($response = $this->validatePackage($request->all(), (int)$package['id'])) {
            return $response;
        }

        [$payload, $benefits] = $this->preparePayload(array_merge($package, $request->all()));
        $updated = SharePackage::update((int)$package['id'], $payload);
        if ($benefits !== null) {
            $this->syncBenefits((int)$package['id'], $benefits);
        }

        AuditLogger::log(Auth::user(), 'update', 'share_packages', 'share_package', (int)$package['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $this->packageWithBenefits((int)$package['id'])]);
    }

    private function validatePackage(array $input, ?int $ignoreId)
    {
        $rules = [
            'package_name' => 'required',
            'package_code' => 'required|unique:share_packages,package_code' . ($ignoreId ? ',' . $ignoreId : ''),
            'package_price' => 'required|numeric|min:1',
            'down_payment' => 'numeric|min:0',
            'duration_months' => 'required|numeric|min:1',
            'monthly_installment' => 'numeric|min:0',
            'bonus_share_percent' => 'numeric|min:0',
            'bonus_share_units' => 'numeric|min:0',
            'free_nights' => 'numeric|min:0',
            'lifetime_discount' => 'numeric|min:0',
            'tour_voucher_value' => 'numeric|min:0',
            'status' => 'in:' . implode(',', self::STATUSES),
            'benefits' => 'array',
        ];

        $errors = Validator::make($input, $rules);
        if (!empty($errors)) {
            return $this->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        if (isset($input['down_payment'], $input['package_price']) && (float)$input['down_payment'] > (float)$input['package_price']) {
            return $this->json(['message' => 'Down payment cannot exceed package price'], 422);
        }

        if (isset($input['benefits']) && !is_array($input['benefits'])) {
            return $this->json(['message' => 'Benefits must be an array'], 422);
        }

        return null;
    }

    private function preparePayload(array $input): array
    {
        $unitPrice = (float)Env::get('SHARE_DEFAULT_UNIT_PRICE', 25000);
        $payload = [
            'package_name' => trim((string)$input['package_name']),
            'package_code' => trim((string)$input['package_code']),
            'package_price' => (float)$input['package_price'],
            'down_payment' => isset($input['down_payment']) ? (float)$input['down_payment'] : 0,
            'duration_months' => (int)$input['duration_months'],
            'monthly_installment' => isset($input['monthly_installment']) ? (float)$input['monthly_installment'] : 0,
            'auto_share_units' => (int)max(1, floor(((float)$input['package_price']) / max(1, $unitPrice))),
            'bonus_share_percent' => isset($input['bonus_share_percent']) ? (float)$input['bonus_share_percent'] : 0,
            'bonus_share_units' => isset($input['bonus_share_units']) ? (int)$input['bonus_share_units'] : 0,
            'free_nights' => isset($input['free_nights']) ? (int)$input['free_nights'] : 0,
            'lifetime_discount' => isset($input['lifetime_discount']) ? (float)$input['lifetime_discount'] : 0,
            'tour_voucher_value' => isset($input['tour_voucher_value']) ? (float)$input['tour_voucher_value'] : 0,
            'gift_items' => $input['gift_items'] ?? null,
            'status' => $input['status'] ?? 'active',
            'description' => $input['description'] ?? null,
        ];

        if (!$payload['bonus_share_units'] && $payload['bonus_share_percent']) {
            $payload['bonus_share_units'] = (int)round($payload['auto_share_units'] * ($payload['bonus_share_percent'] / 100));
        }

        if ($payload['monthly_installment'] <= 0 && $payload['duration_months'] > 0) {
            $remaining = max($payload['package_price'] - $payload['down_payment'], 0);
            $payload['monthly_installment'] = round($remaining / $payload['duration_months'], 2);
        }

        $benefits = null;
        if (array_key_exists('benefits', $input)) {
            $benefits = [];
            foreach ((array)$input['benefits'] as $benefit) {
                if (empty($benefit['benefit_type']) || empty($benefit['benefit_value'])) {
                    continue;
                }
                $benefits[] = [
                    'benefit_type' => trim((string)$benefit['benefit_type']),
                    'benefit_value' => trim((string)$benefit['benefit_value']),
                    'notes' => $benefit['notes'] ?? null,
                ];
            }
        }

        return [$payload, $benefits];
    }

    private function syncBenefits(int $packageId, ?array $benefits): void
    {
        if ($benefits === null) {
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM package_benefits WHERE package_id = :id');
        $stmt->execute(['id' => $packageId]);

        foreach ($benefits as $benefit) {
            PackageBenefit::create(array_merge($benefit, ['package_id' => $packageId]));
        }
    }

    private function packageWithBenefits(int $id): ?array
    {
        $package = SharePackage::find($id);
        if (!$package) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM package_benefits WHERE package_id = :id ORDER BY id');
        $stmt->execute(['id' => $id]);
        $package['benefits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $package;
    }

    private function mapBenefits(array $packages): array
    {
        if (!$packages) {
            return [];
        }

        $ids = array_map(static fn($pkg) => (int)$pkg['id'], $packages);
        $pdo = Database::connection();
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare('SELECT * FROM package_benefits WHERE package_id IN (' . $in . ') ORDER BY package_id');
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['package_id']][] = $row;
        }

        return $map;
    }

    private function countByStatus(PDO $pdo, string $status): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM share_packages WHERE status = :status');
        $stmt->execute(['status' => $status]);
        return (int)$stmt->fetchColumn();
    }
}
