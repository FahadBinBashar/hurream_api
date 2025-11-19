<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Share;
use App\Models\SharePackage;
use App\Models\ShareSale;
use App\Models\ShareSaleBatch;
use App\Services\CertificateGenerator;
use App\Services\InstallmentScheduleService;
use App\Services\NotificationService;
use App\Services\PackageBenefitService;
use App\Services\ShareInventoryService;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\Env;
use RuntimeException;
use Throwable;

class ShareSalesController extends Controller
{
    public function __construct(
        private readonly ShareInventoryService $inventoryService = new ShareInventoryService(),
        private readonly CertificateGenerator $certificateGenerator = new CertificateGenerator(),
        private readonly PackageBenefitService $benefitService = new PackageBenefitService(),
        private readonly InstallmentScheduleService $installmentService = new InstallmentScheduleService(),
        private readonly NotificationService $notificationService = new NotificationService()
    ) {
    }

    public function sellSingle(Request $request)
    {
        if ($response = $this->validate($request, [
            'customer_id' => 'required|numeric|min:1',
            'project_id' => 'required|numeric|min:1',
            'share_units' => 'required|numeric|min:1',
            'share_price' => 'numeric|min:1',
            'payment_mode' => 'required|in:one_time,installment',
            'down_payment' => 'numeric|min:0',
            'stage' => 'regex:/^MÖW-\\d+$/u',
        ])) {
            return $response;
        }

        $payload = $request->all();
        $customer = Customer::find((int)$payload['customer_id']);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found'], 404);
        }

        $project = Project::find((int)$payload['project_id']);
        if (!$project) {
            return $this->json(['message' => 'Project not found'], 404);
        }

        $unitPrice = isset($payload['share_price']) ? (float)$payload['share_price'] : (float)Env::get('SHARE_DEFAULT_UNIT_PRICE', 25000);
        $shareUnits = (int)$payload['share_units'];
        $totalAmount = $unitPrice * $shareUnits;
        $downPayment = isset($payload['down_payment']) ? (float)$payload['down_payment'] : $totalAmount;

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $allocations = $this->inventoryService->reserve($pdo, (int)$project['id'], $shareUnits);
            $certificate = $this->certificateGenerator->generate($pdo, (int)$project['id'], $shareUnits);

            $sale = ShareSale::create([
                'customer_id' => (int)$customer['id'],
                'project_id' => (int)$project['id'],
                'package_id' => null,
                'sale_type' => 'single',
                'total_shares' => $shareUnits,
                'bonus_shares' => 0,
                'share_price' => $unitPrice,
                'total_amount' => $totalAmount,
                'down_payment' => $downPayment,
                'installment_months' => 0,
                'installment_amount' => 0,
                'payment_mode' => $payload['payment_mode'],
                'invoice_no' => $this->generateInvoiceNo($project['project_code']),
                'certificate_no' => $certificate['certificate_no'],
                'certificate_start' => $certificate['certificate_start'],
                'certificate_end' => $certificate['certificate_end'],
                'status' => 'completed',
                'sale_source' => 'api',
                'metadata' => json_encode(['allocations' => $allocations]),
            ]);

            foreach ($allocations as $allocation) {
                ShareSaleBatch::create([
                    'share_sale_id' => (int)$sale['id'],
                    'batch_id' => $allocation['batch_id'],
                    'shares_deducted' => $allocation['shares'],
                    'certificate_from' => $allocation['certificate_from'],
                    'certificate_to' => $allocation['certificate_to'],
                    'share_price' => $allocation['share_price'],
                ]);
            }

            $shareRecord = Share::create([
                'customer_id' => (int)$customer['id'],
                'project_id' => (int)$project['id'],
                'sale_id' => (int)$sale['id'],
                'primary_batch_id' => $allocations[0]['batch_id'] ?? null,
                'share_type' => 'single',
                'package_id' => null,
                'unit_price' => $unitPrice,
                'share_units' => $shareUnits,
                'bonus_units' => 0,
                'total_units' => $shareUnits,
                'amount' => $totalAmount,
                'down_payment' => $downPayment,
                'monthly_installment' => null,
                'installment_months' => null,
                'payment_mode' => $payload['payment_mode'],
                'stage' => $payload['stage'] ?? 'MÖW-1',
                'reinvest_flag' => 0,
                'status' => 'active',
                'approval_status' => 'approved',
                'approver_gate_triggered' => 0,
                'benefits_snapshot' => null,
                'certificate_no' => $certificate['certificate_no'],
                'invoice_no' => $sale['invoice_no'],
            ]);

            $pdo->commit();
        } catch (RuntimeException $exception) {
            $pdo->rollBack();
            return $this->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        $this->notificationService->sendSaleConfirmation($sale, $customer);
        AuditLogger::log(Auth::user(), 'create', 'share_sales', 'share_sale', (int)$sale['id'], $sale, $request->ip(), $request->userAgent());

        return $this->json([
            'data' => [
                'sale' => $sale,
                'share' => $shareRecord,
                'allocations' => $allocations,
            ],
        ], 201);
    }

    public function sellPackage(Request $request)
    {
        if ($response = $this->validate($request, [
            'customer_id' => 'required|numeric|min:1',
            'package_id' => 'required|numeric|min:1',
            'payment_mode' => 'required|in:one_time,installment',
            'down_payment' => 'numeric|min:0',
            'stage' => 'regex:/^MÖW-\\d+$/u',
        ])) {
            return $response;
        }

        $payload = $request->all();
        $customer = Customer::find((int)$payload['customer_id']);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found'], 404);
        }

        $package = SharePackage::find((int)$payload['package_id']);
        if (!$package) {
            return $this->json(['message' => 'Package not found'], 404);
        }
        if (($package['status'] ?? 'inactive') !== 'active') {
            return $this->json(['message' => 'Package is inactive'], 422);
        }

        $project = Project::find((int)$package['project_id']);
        if (!$project) {
            return $this->json(['message' => 'Project not found for package'], 404);
        }

        $shareUnits = (int)$package['total_shares_included'];
        $bonusUnits = (int)$package['bonus_shares'];
        $totalUnits = $shareUnits + $bonusUnits;
        $packagePrice = (float)$package['package_price'];
        $downPayment = isset($payload['down_payment']) ? (float)$payload['down_payment'] : 0;
        $installmentMonths = (int)($package['installment_months'] ?? 0);
        $remaining = max($packagePrice - $downPayment, 0);
        $monthlyInstallment = $installmentMonths > 0 ? round($remaining / $installmentMonths, 2) : 0;
        $benefits = $this->benefitService->snapshot($package);

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $allocations = $this->inventoryService->reserve($pdo, (int)$project['id'], $totalUnits);
            $certificate = $this->certificateGenerator->generate($pdo, (int)$project['id'], $totalUnits);

            $sale = ShareSale::create([
                'customer_id' => (int)$customer['id'],
                'project_id' => (int)$project['id'],
                'package_id' => (int)$package['id'],
                'sale_type' => 'package',
                'total_shares' => $shareUnits,
                'bonus_shares' => $bonusUnits,
                'share_price' => $packagePrice,
                'total_amount' => $packagePrice,
                'down_payment' => $downPayment,
                'installment_months' => $installmentMonths,
                'installment_amount' => $monthlyInstallment,
                'payment_mode' => $payload['payment_mode'],
                'invoice_no' => $this->generateInvoiceNo($project['project_code']),
                'certificate_no' => $certificate['certificate_no'],
                'certificate_start' => $certificate['certificate_start'],
                'certificate_end' => $certificate['certificate_end'],
                'status' => 'completed',
                'sale_source' => 'api',
                'metadata' => json_encode(['benefits' => $benefits, 'allocations' => $allocations]),
            ]);

            foreach ($allocations as $allocation) {
                ShareSaleBatch::create([
                    'share_sale_id' => (int)$sale['id'],
                    'batch_id' => $allocation['batch_id'],
                    'shares_deducted' => $allocation['shares'],
                    'certificate_from' => $allocation['certificate_from'],
                    'certificate_to' => $allocation['certificate_to'],
                    'share_price' => $allocation['share_price'],
                ]);
            }

            $shareRecord = Share::create([
                'customer_id' => (int)$customer['id'],
                'project_id' => (int)$project['id'],
                'sale_id' => (int)$sale['id'],
                'primary_batch_id' => $allocations[0]['batch_id'] ?? null,
                'share_type' => 'package',
                'package_id' => (int)$package['id'],
                'unit_price' => (float)Env::get('SHARE_DEFAULT_UNIT_PRICE', 25000),
                'share_units' => $shareUnits,
                'bonus_units' => $bonusUnits,
                'total_units' => $shareUnits + $bonusUnits,
                'amount' => $packagePrice,
                'down_payment' => $downPayment,
                'monthly_installment' => $monthlyInstallment ?: null,
                'installment_months' => $installmentMonths ?: null,
                'payment_mode' => $payload['payment_mode'],
                'stage' => $payload['stage'] ?? 'MÖW-1',
                'reinvest_flag' => 0,
                'status' => 'active',
                'approval_status' => 'approved',
                'approver_gate_triggered' => $packagePrice >= (float)Env::get('SHARE_MULTI_APPROVAL_THRESHOLD', 500000) ? 1 : 0,
                'benefits_snapshot' => json_encode($benefits),
                'certificate_no' => $certificate['certificate_no'],
                'invoice_no' => $sale['invoice_no'],
            ]);

            if ($installmentMonths > 0 && $remaining > 0) {
                $this->installmentService->buildForSale(
                    (int)$sale['id'],
                    (int)$customer['id'],
                    (int)$project['id'],
                    $remaining,
                    $installmentMonths,
                    date('Y-m-d')
                );
            }

            $pdo->commit();
        } catch (RuntimeException $exception) {
            $pdo->rollBack();
            return $this->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        $this->notificationService->sendSaleConfirmation($sale, $customer);
        AuditLogger::log(Auth::user(), 'create', 'share_sales', 'share_sale', (int)$sale['id'], $sale, $request->ip(), $request->userAgent());

        return $this->json([
            'data' => [
                'sale' => $sale,
                'share' => $shareRecord,
                'allocations' => $allocations,
                'benefits' => $benefits,
            ],
        ], 201);
    }

    private function generateInvoiceNo(?string $projectCode): string
    {
        $prefix = $projectCode ? strtoupper($projectCode) : 'HRM';
        return sprintf('%s-%s', $prefix, date('YmdHis'));
    }
}
