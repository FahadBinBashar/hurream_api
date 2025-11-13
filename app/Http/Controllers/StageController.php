<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\InvestorStage;
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
            'investor_id' => 'required|numeric',
            'profit_amount' => 'required|numeric',
            'period_start' => 'required|date',
            'period_end' => 'required|date',
        ])) {
            return $response;
        }

        $data = $request->all();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM investor_stages WHERE investor_id = :investor_id LIMIT 1');
        $stmt->execute(['investor_id' => $data['investor_id']]);
        $investorStage = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$investorStage) {
            return $this->json(['message' => 'Investor stage not found'], 404);
        }

        $capital = (float)$investorStage['capital_amount'];
        $profit = (float)$data['profit_amount'];

        if ((int)$investorStage['reinvest_enabled']) {
            $reinvest = round($profit * 0.5, 2);
            $cashout = round($profit - $reinvest, 2);
            $nextCapital = $capital + $reinvest;
        } else {
            $reinvest = 0.0;
            $cashout = round($profit, 2);
            $nextCapital = $capital;
        }

        $period = StagePeriod::create([
            'investor_stage_id' => $investorStage['id'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'profit_amount' => $profit,
            'reinvest_amount' => $reinvest,
            'cashout_amount' => $cashout,
            'next_capital_amount' => $nextCapital,
        ]);

        InvestorStage::update((int)$investorStage['id'], [
            'capital_amount' => $nextCapital,
            'last_closed_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLogger::log(Auth::user(), 'close_period', 'stages', 'investor_stage', (int)$investorStage['id'], $data, $request->ip(), $request->userAgent());

        return $this->json([
            'data' => [
                'period' => $period,
                'reinvest' => $reinvest,
                'cashout' => $cashout,
                'next_capital' => $nextCapital,
            ],
        ]);
    }

    public function investorStages(Request $request, array $params)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM investor_stages WHERE investor_id = :id LIMIT 1');
        $stmt->execute(['id' => (int)$params['id']]);
        $investorStage = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$investorStage) {
            return $this->json(['message' => 'Investor stage not found'], 404);
        }

        $historyStmt = $pdo->prepare('SELECT * FROM stage_periods WHERE investor_stage_id = :id ORDER BY period_start DESC');
        $historyStmt->execute(['id' => $investorStage['id']]);

        return $this->json([
            'data' => [
                'current' => $investorStage,
                'history' => $historyStmt->fetchAll(\PDO::FETCH_ASSOC),
            ],
        ]);
    }

    public function report(): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT s.code, s.name, COUNT(isg.id) as investor_count, IFNULL(SUM(isg.capital_amount),0) as total_capital FROM stages s LEFT JOIN investor_stages isg ON isg.current_stage_id = s.id GROUP BY s.id ORDER BY s.sequence ASC';
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
        $stmt = $pdo->prepare('SELECT * FROM investor_stages WHERE investor_id = :investor_id LIMIT 1');
        $stmt->execute(['investor_id' => (int)$params['id']]);
        $investorStage = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$investorStage) {
            return $this->json(['message' => 'Investor stage not found'], 404);
        }

        $stage = Stage::find((int)$request->input('stage_id'));
        if (!$stage) {
            return $this->json(['message' => 'Stage not found'], 404);
        }

        InvestorStage::update((int)$investorStage['id'], [
            'current_stage_id' => $stage['id'],
        ]);

        AuditLogger::log(Auth::user(), 'upgrade_stage', 'stages', 'investor_stage', (int)$investorStage['id'], ['stage_id' => $stage['id']], $request->ip(), $request->userAgent());

        return $this->json(['message' => 'Stage upgraded']);
    }
}
