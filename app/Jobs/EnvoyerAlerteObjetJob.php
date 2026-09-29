<?php

namespace App\Jobs;

use App\Models\DecouverteObjet;
use App\Models\Objet;
use App\Services\AlerteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnvoyerAlerteObjetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly string $objetId, private readonly string $decouverteId)
    {
    }

    public function handle(AlerteService $alertes): void
    {
        $o = Objet::find($this->objetId);
        $d = DecouverteObjet::find($this->decouverteId);
        if ($o && $d) {
            $alertes->alerterPourObjet($o, $d);
        }
    }
}
