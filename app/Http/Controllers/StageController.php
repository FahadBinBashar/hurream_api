<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\CustomerStage;
use App\Models\Stage;
use App\Models\StagePeriod;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\Validator;

class StageController extends Controller
{
    public function closePeriod(Request $request)
    {
        if ($response = $this->validate($request, [
            'customer_id' => 'required|numeric',
            'profit_amount' => 'required|numeric',
            'period_start' => 'required|date',
            'period_end' => 'required|date',
        ])) {
            return $response;
        }

        $data = $request->all();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM customer_stages WHERE customer_id = :customer_id LIMIT 1');
        $stmt->execute(['customer_id' => $data['customer_id']]);
        $customerStage = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$customerStage) {
            return $this->json(['message' => 'Customer stage not found'], 404);
        }

        $capital = (float)$customerStage['capital_amount'];
        $profit = (float)$data['profit_amount'];

        if ((int)$customerStage['reinvest_enabled']) {
            $reinvest = round($profit * 0.5, 2);
            $cashout = round($profit - $reinvest, 2);
            $nextCapital = $capital + $reinvest;
        } else {
            $reinvest = 0.0;
            $cashout = round($profit, 2);
            $nextCapital = $capital;
        }

        $period = StagePeriod::create([
            'customer_stage_id' => $customerStage['id'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'profit_amount' => $profit,
            'reinvest_amount' => $reinvest,
            'cashout_amount' => $cashout,
            'next_capital_amount' => $nextCapital,
        ]);

        CustomerStage::update((int)$customerStage['id'], [
            'capital_amount' => $nextCapital,
            'last_closed_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLogger::log(Auth::user(), 'close_period', 'stages', 'customer_stage', (int)$customerStage['id'], $data, $request->ip(), $request->userAgent());

        return $this->json([
            'data' => [
                'period' => $period,
                'reinvest' => $reinvest,
                'cashout' => $cashout,
                'next_capital' => $nextCapital,
            ],
        ]);
    }

    public function customerStages(Request $request, array $params)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM customer_stages WHERE customer_id = :id LIMIT 1');
        $stmt->execute(['id' => (int)$params['id']]);
        $customerStage = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$customerStage) {
            return $this->json(['message' => 'Customer stage not found'], 404);
        }

        $historyStmt = $pdo->prepare('SELECT * FROM stage_periods WHERE customer_stage_id = :id ORDER BY period_start DESC');
        $historyStmt->execute(['id' => $customerStage['id']]);

        return $this->json([
            'data' => [
                'current' => $customerStage,
                'history' => $historyStmt->fetchAll(\PDO::FETCH_ASSOC),
            ],
        ]);
    }

    public function report(): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT s.code, s.name, COUNT(isg.id) as investor_count, IFNULL(SUM(isg.capital_amount),0) as total_capital FROM stages s LEFT JOIN customer_stages isg ON isg.current_stage_id = s.id GROUP BY s.id ORDER BY s.sequence ASC';
        $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return ['data' => $rows];
    }

    public function upgrade(Request $request, array $params)
    {
        if ($response = $this->validate($request, [
            'stage_id' => 'required|numeric',
        ])) {
            return $response;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM customer_stages WHERE customer_id = :customer_id LIMIT 1');
        $stmt->execute(['customer_id' => (int)$params['id']]);
        $customerStage = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$customerStage) {
            return $this->json(['message' => 'Customer stage not found'], 404);
        }

        $stage = Stage::find((int)$request->input('stage_id'));
        if (!$stage) {
            return $this->json(['message' => 'Stage not found'], 404);
        }

        CustomerStage::update((int)$customerStage['id'], [
            'current_stage_id' => $stage['id'],
        ]);

        AuditLogger::log(Auth::user(), 'upgrade_stage', 'stages', 'customer_stage', (int)$customerStage['id'], ['stage_id' => $stage['id']], $request->ip(), $request->userAgent());

        return $this->json(['message' => 'Stage upgraded']);
    }
}
