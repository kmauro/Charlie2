<?php

namespace App\Console\Commands\Domains\Coverages\Commands;

use App\Domains\Coverages\Services\CoverageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('coverages:generate-recurring-charges')]
#[Description('Emite los cobros recurrentes vencidos de contratos de cobertura.')]
class GenerateRecurringCoverageCharges extends Command
{
    public function handle(CoverageService $coverage): int
    {
        $generated = $coverage->generateDueCharges();
        $this->info("Cobros recurrentes emitidos: {$generated}.");

        return self::SUCCESS;
    }
}
