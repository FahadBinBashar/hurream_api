<?php

namespace App\Console\Commands;

use App\Core\Database;
use App\Models\InvestorStage;
use App\Models\StagePeriod;

class CloseInvestorStagesCommand
{
    public function __invoke(float $defaultProfitRate = 0.02): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM investor_stages WHERE reinvest_enabled = 1');
        $stages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($stages as $stage) {
            $capital = (float)$stage['capital_amount'];
            $profit = $capital * $defaultProfitRate;
            $reinvest = round($profit * 0.5, 2);
            $cashout = round($profit - $reinvest, 2);
            $nextCapital = $capital + $reinvest;

            StagePeriod::create([
                'investor_stage_id' => $stage['id'],
                'period_start' => date('Y-m-01'),
                'period_end' => date('Y-m-t'),
                'profit_amount' => $profit,
                'reinvest_amount' => $reinvest,
                'cashout_amount' => $cashout,
                'next_capital_amount' => $nextCapital,
            ]);

            InvestorStage::update((int)$stage['id'], [
                'capital_amount' => $nextCapital,
                'last_closed_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
