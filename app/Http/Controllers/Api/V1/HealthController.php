<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /** Vérifie que l'API et la base répondent (utile pour le déploiement et pour le collaborateur frontend). */
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
            $db = 'ok';
        } catch (Throwable) {
            $db = 'indisponible';
        }

        return ApiResponse::ok([
            'service' => 'SIAP API',
            'version' => 'v1',
            'base_de_donnees' => $db,
            'heure_serveur' => now()->utc()->toIso8601String(),
        ]);
    }
}
