<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\Validator;

class VoucherController extends Controller
{
    public function index(): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT v.*, (SELECT JSON_GROUP_ARRAY(JSON_OBJECT("account_id", account_id, "debit", debit, "credit", credit, "description", description)) FROM voucher_lines WHERE voucher_id = v.id) AS lines FROM vouchers v ORDER BY date DESC';
        $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return ['data' => $rows];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'voucher_no' => 'required|unique:vouchers,voucher_no',
            'voucher_type' => 'required|in:receipt,payment,journal',
            'date' => 'required|date',
            'description' => '',
            'lines' => 'required|array|min:2',
        ])) {
            return $response;
        }

        $data = $request->all();
        $lines = $data['lines'] ?? [];
        $debitTotal = 0;
        $creditTotal = 0;
        foreach ($lines as $line) {
            $debitTotal += (float)($line['debit'] ?? 0);
            $creditTotal += (float)($line['credit'] ?? 0);
        }

        if (round($debitTotal, 2) !== round($creditTotal, 2)) {
            return $this->json(['message' => 'Debit and credit totals must match'], 422);
        }

        $voucher = Voucher::create([
            'voucher_no' => $data['voucher_no'],
            'voucher_type' => $data['voucher_type'],
            'date' => $data['date'],
            'description' => $data['description'] ?? null,
            'created_by' => Auth::user()['id'] ?? null,
            'status' => $data['status'] ?? 'draft',
        ]);

        foreach ($lines as $line) {
            VoucherLine::create([
                'voucher_id' => $voucher['id'],
                'account_id' => $line['account_id'],
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'description' => $line['description'] ?? null,
            ]);
        }

        AuditLogger::log(Auth::user(), 'create', 'finance', 'voucher', (int)$voucher['id'], $data, $request->ip(), $request->userAgent());

        return $this->json(['data' => $voucher], 201);
    }

    public function show(Request $request, array $params)
    {
        $voucher = Voucher::find((int)$params['id']);
        if (!$voucher) {
            return $this->json(['message' => 'Voucher not found'], 404);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM voucher_lines WHERE voucher_id = :id');
        $stmt->execute(['id' => $voucher['id']]);
        $voucher['lines'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->json(['data' => $voucher]);
    }
}
