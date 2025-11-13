<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\Validator;

class PayrollController extends Controller
{
    public function index(): array
    {
        return ['data' => PayrollRun::all()];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'period_start' => 'required|date',
            'period_end' => 'required|date',
        ])) {
            return $response;
        }

        $data = $request->all();
        $run = PayrollRun::create([
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'status' => 'processed',
            'processed_at' => date('Y-m-d H:i:s'),
        ]);

        $pdo = Database::connection();
        $employees = Employee::all();
        foreach ($employees as $employee) {
            $salaryStmt = $pdo->prepare('SELECT * FROM employee_salaries WHERE employee_id = :id AND effective_from <= :end ORDER BY effective_from DESC LIMIT 1');
            $salaryStmt->execute([
                'id' => $employee['id'],
                'end' => $data['period_end'],
            ]);
            $salary = $salaryStmt->fetch(\PDO::FETCH_ASSOC);
            $baseSalary = (float)($salary['base_salary'] ?? $employee['salary'] ?? 0);
            $allowance = (float)($salary['allowance'] ?? 0);
            $commissionRate = (float)($salary['commission_rate'] ?? 0);

            $attendanceStmt = $pdo->prepare('SELECT COUNT(*) FROM attendance_records WHERE employee_id = :id AND check_in_at BETWEEN :start AND :end');
            $attendanceStmt->execute([
                'id' => $employee['id'],
                'start' => $data['period_start'] . ' 00:00:00',
                'end' => $data['period_end'] . ' 23:59:59',
            ]);
            $attendanceCount = (int)$attendanceStmt->fetchColumn();

            $gross = $baseSalary + $allowance + ($attendanceCount * $commissionRate);
            $deductions = 0;
            $net = $gross - $deductions;

            PayrollItem::create([
                'payroll_run_id' => $run['id'],
                'employee_id' => $employee['id'],
                'gross_salary' => $gross,
                'deductions' => $deductions,
                'net_salary' => $net,
                'details' => json_encode([
                    'attendance_days' => $attendanceCount,
                    'base_salary' => $baseSalary,
                    'allowance' => $allowance,
                    'commission_rate' => $commissionRate,
                ]),
            ]);
        }

        AuditLogger::log(Auth::user(), 'create', 'payroll', 'payroll_run', (int)$run['id'], $data, $request->ip(), $request->userAgent());

        return $this->json(['data' => $run], 201);
    }
}
