<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProfilType;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Beneficiaire;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Informations propres aux enfants (§4.4) et aux personnes vulnérables (§4.5). */
class ProfilAssistanceController extends Controller
{
    public function updateEnfant(Request $request, Beneficiaire $beneficiaire): JsonResponse
    {
        Gate::authorize('update', $beneficiaire);
        if ($beneficiaire->type_profil !== ProfilType::Enfant) {
            throw ApiException::validation("Ce bénéficiaire n'est pas un profil de type enfant.");
        }

        $data = $request->validate([
            'ecole' => ['nullable', 'string', 'max:190'],
            'signes_distinctifs' => ['nullable', 'string', 'max:1000'],
            'informations_reunification' => ['nullable', 'string', 'max:1000'],
        ]);

        $profil = $beneficiaire->profilEnfant()->updateOrCreate([], $data);
        Audit::log('profil_enfant.modifie', $beneficiaire, ['champs' => array_keys($data)]);

        return ApiResponse::ok($profil->fresh()->toApi());
    }

    public function updateVulnerable(Request $request, Beneficiaire $beneficiaire): JsonResponse
    {
        Gate::authorize('update', $beneficiaire);
        if ($beneficiaire->type_profil !== ProfilType::PersonneVulnerable) {
            throw ApiException::validation("Ce bénéficiaire n'est pas un profil de type personne vulnérable.");
        }

        $data = $request->validate([
            'situation' => ['nullable', 'string', 'max:1000'],
            'consignes_assistance' => ['nullable', 'string', 'max:1000'],
            'precautions' => ['nullable', 'string', 'max:1000'],
        ]);

        $profil = $beneficiaire->profilVulnerable()->updateOrCreate([], $data);
        Audit::log('profil_vulnerable.modifie', $beneficiaire, ['champs' => array_keys($data)]);

        return ApiResponse::ok($profil->fresh()->toApi());
    }
}
