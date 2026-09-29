<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Beneficiaire;
use App\Models\InformationMedicale;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InformationMedicaleController extends Controller
{
    private const GROUPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /** Consultation réservée aux structures de santé autorisées et au propriétaire. Toujours journalisée. */
    public function show(Beneficiaire $beneficiaire): JsonResponse
    {
        Gate::authorize('voir-medical', $beneficiaire);

        Audit::log('medical.consultation', $beneficiaire, ['via' => 'api'], 'securite');

        // data = null si rien n'est renseigné (on n'invente pas de valeurs).
        return ApiResponse::ok($beneficiaire->informationMedicale?->toApi());
    }

    public function update(Request $request, Beneficiaire $beneficiaire): JsonResponse
    {
        Gate::authorize('modifier-medical', $beneficiaire);

        $data = $request->validate([
            'groupe_sanguin' => ['nullable', Rule::in(self::GROUPES)],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'traitements' => ['nullable', 'string', 'max:2000'],
            'informations_essentielles' => ['nullable', 'string', 'max:3000'],
        ]);

        $info = InformationMedicale::updateOrCreate(
            ['beneficiaire_id' => $beneficiaire->id],
            $data + ['updated_by' => $request->user()->id],
        );

        // On journalise QUELS champs ont changé, jamais leurs valeurs.
        Audit::log('medical.modification', $beneficiaire, ['champs' => array_keys($data)], 'securite');

        return ApiResponse::ok($info->fresh()->toApi());
    }
}
