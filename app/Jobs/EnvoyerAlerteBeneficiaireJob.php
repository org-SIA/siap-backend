<?php

namespace App\Jobs;

use App\Models\Beneficiaire;
use App\Models\SignalementBeneficiaire;
use App\Services\AlerteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnvoyerAlerteBeneficiaireJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly string $beneficiaireId, private readonly string $signalementId)
    {
    }

    public function handle(AlerteService $alertes): void
    {
        $b = Beneficiaire::find($this->beneficiaireId);
        $s = SignalementBeneficiaire::find($this->signalementId);
        if ($b && $s) {
            $alertes->alerterPourBeneficiaire($b, $s);
        }
    }
}
