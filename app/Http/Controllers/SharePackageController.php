<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\PackageBenefit;
use App\Models\Project;
use App\Models\SharePackage;
use App\Services\PackageBenefitService;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\Env;
use App\Support\Validator;
use PDO;

class SharePackageController extends Controller
{
    private const STATUSES = ['active', 'inactive'];

    public function __construct(private readonly PackageBenefitService $benefitService = new PackageBenefitService())
    {
    }

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
        $benefitItems = $this->mapBenefitItems($packages);

        return $this->json([
            'data' => array_map(function (array $package) use ($benefitItems) {
                $package['benefit_items'] = $benefitItems[$package['id']] ?? [];
                $package['benefits'] = $this->decodeBenefits($package['benefits'] ?? null);
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

        [$payload, $benefitItems] = $this->preparePayload($request->all());
        $package = SharePackage::create($payload);
        $this->syncBenefits((int)$package['id'], $benefitItems);

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

        [$payload, $benefitItems] = $this->preparePayload(array_merge($package, $request->all()));
        $updated = SharePackage::update((int)$package['id'], $payload);
        if ($benefitItems !== null) {
            $this->syncBenefits((int)$package['id'], $benefitItems);
        }

        AuditLogger::log(Auth::user(), 'update', 'share_packages', 'share_package', (int)$package['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $this->packageWithBenefits((int)$package['id'])]);
    }

    public function destroy(Request $request, array $params)
    {
        if ($response = $this->ensureRole(['admin'])) {
            return $response;
        }

        $package = SharePackage::find((int)$params['id']);
        if (!$package) {
            return $this->json(['message' => 'Package not found'], 404);
        }

        $this->syncBenefits((int)$package['id'], []);
        SharePackage::delete((int)$package['id']);

        AuditLogger::log(Auth::user(), 'delete', 'share_packages', 'share_package', (int)$package['id'], [], $request->ip(), $request->userAgent());

        return $this->json(['message' => 'Package deleted']);
    }

    public function updateStatus(Request $request, array $params)
    {
        if ($response = $this->ensureRole(['admin'])) {
            return $response;
        }

        if ($response = $this->validate($request, [
            'status' => 'required|in:' . implode(',', self::STATUSES),
        ])) {
            return $response;
        }

        $package = SharePackage::find((int)$params['id']);
        if (!$package) {
            return $this->json(['message' => 'Package not found'], 404);
        }

        $updated = SharePackage::update((int)$package['id'], ['status' => $request->input('status')]);

        AuditLogger::log(Auth::user(), 'update', 'share_packages', 'share_package_status', (int)$package['id'], ['status' => $request->input('status')], $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }

    private function validatePackage(array $input, ?int $ignoreId)
    {
        $rules = [
            'project_id' => 'required|numeric|min:1',
            'package_name' => 'required',
            'package_code' => 'required|unique:share_packages,package_code' . ($ignoreId ? ',' . $ignoreId : ''),
            'package_price' => 'required|numeric|min:1',
            'down_payment' => 'numeric|min:0',
            'total_shares_included' => 'numeric|min:1',
            'bonus_shares' => 'numeric|min:0',
            'installment_months' => 'numeric|min:0',
            'status' => 'in:' . implode(',', self::STATUSES),
            'benefits' => 'array',
            'benefit_items' => 'array',
        ];

        $errors = Validator::make($input, $rules);
        if (!empty($errors)) {
            return $this->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        if (isset($input['down_payment'], $input['package_price']) && (float)$input['down_payment'] > (float)$input['package_price']) {
            return $this->json(['message' => 'Down payment cannot exceed package price'], 422);
        }

        $project = Project::find((int)$input['project_id']);
        if (!$project) {
            return $this->json(['message' => 'Project not found'], 404);
        }

        return null;
    }

    private function preparePayload(array $input): array
    {
        $unitPrice = (float)Env::get('SHARE_DEFAULT_UNIT_PRICE', 25000);
        $payload = [
            'project_id' => (int)$input['project_id'],
            'package_name' => trim((string)$input['package_name']),
            'package_code' => trim((string)$input['package_code']),
            'package_price' => (float)$input['package_price'],
            'down_payment' => isset($input['down_payment']) ? (float)$input['down_payment'] : 0,
            'total_shares_included' => isset($input['total_shares_included'])
                ? (int)$input['total_shares_included']
                : (int)max(1, floor(((float)$input['package_price']) / max(1, $unitPrice))),
            'bonus_shares' => isset($input['bonus_shares']) ? (int)$input['bonus_shares'] : 0,
            'installment_months' => isset($input['installment_months']) ? (int)$input['installment_months'] : 0,
            'benefits' => isset($input['benefits']) ? json_encode($this->benefitService->normalize((array)$input['benefits'])) : ($input['benefits'] ?? null),
            'status' => $input['status'] ?? 'active',
            'description' => $input['description'] ?? null,
        ];

        $benefitItems = null;
        if (array_key_exists('benefit_items', $input)) {
            $benefitItems = [];
            foreach ((array)$input['benefit_items'] as $benefit) {
                if (empty($benefit['benefit_type']) || empty($benefit['benefit_value'])) {
                    continue;
                }
                $benefitItems[] = [
                    'benefit_type' => trim((string)$benefit['benefit_type']),
                    'benefit_value' => trim((string)$benefit['benefit_value']),
                    'notes' => $benefit['notes'] ?? null,
                ];
            }
        }

        return [$payload, $benefitItems];
    }

    private function syncBenefits(int $packageId, ?array $benefitItems): void
    {
        if ($benefitItems === null) {
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM package_benefits WHERE package_id = :id');
        $stmt->execute(['id' => $packageId]);

        foreach ($benefitItems as $benefit) {
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
        $package['benefit_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $package['benefits'] = $this->decodeBenefits($package['benefits'] ?? null);

        return $package;
    }

    private function decodeBenefits(?string $benefits): array
    {
        if (!$benefits) {
            return [];
        }

        $decoded = json_decode($benefits, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function mapBenefitItems(array $packages): array
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
