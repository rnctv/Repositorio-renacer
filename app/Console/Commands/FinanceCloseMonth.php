<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{FinancePaymentMethod, FinanceOpeningBalance, FinanceTransaction};

class FinanceCloseMonth extends Command
{
    protected $signature = 'finance:close-month {year} {month}';
    protected $description = 'Calcula cierre por medio y arrastra apertura del siguiente mes';

    public function handle()
    {
        $y = (int) $this->argument('year');
        $m = (int) $this->argument('month');

        $methods = FinancePaymentMethod::all();
        foreach ($methods as $method) {
            $from = sprintf('%04d-%02d-01', $y, $m);
            $to   = date('Y-m-d', strtotime("$from +1 month"));

            $opening = FinanceOpeningBalance::firstOrCreate(
                ['year' => $y, 'month' => $m, 'payment_method_id' => $method->id],
                ['amount' => 0, 'source' => 'manual']
            );

            $tx = FinanceTransaction::whereBetween('date', [$from, $to])
                    ->where('payment_method_id', $method->id);

            $ingresos = (clone $tx)->where('type', 'ingreso')->sum('amount');
            $egresos  = (clone $tx)->where('type', 'gasto')->sum('amount');
            $closing = (float)$opening->amount + (float)$ingresos - (float)$egresos;

            $nextY = (int)date('Y', strtotime("$from +1 month"));
            $nextM = (int)date('n', strtotime("$from +1 month"));

            FinanceOpeningBalance::updateOrCreate(
                ['year' => $nextY, 'month' => $nextM, 'payment_method_id' => $method->id],
                ['amount' => $closing, 'source' => 'carryover']
            );

            $this->info("{$method->name} cierre $y-$m = $closing ; apertura {$nextY}-{$nextM} creada.");
        }

        return Command::SUCCESS;
    }
}
