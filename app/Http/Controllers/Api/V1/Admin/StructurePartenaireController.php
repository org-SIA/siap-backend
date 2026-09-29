<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\StructurePartenaire;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Structures partenaires : hôpitaux, Croix-Rouge, mairie... (permission structures.gerer : admin). */
class StructurePartenaireController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::ok(StructurePartenaire::orderBy('nom')->get()->map(fn ($s) => $s->toApi())->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->regles());

        $structure = StructurePartenaire::create($data);
        Audit::log('structure.cree', $structure);

        return ApiResponse::ok($structure->fresh()->toApi(), null, 201);
    }

    public function update(Request $request, StructurePartenaire $structure): JsonResponse
    {
        $data = $request->validate($this->regles(true));

        $structure->update($data);
        Audit::log('structure.modifie', $structure, ['champs' => array_keys($data)]);

        return ApiResponse::ok($structure->fresh()->toApi());
    }

    private function regles(bool $partiel = false): array
    {
        $sometimes = $partiel ? ['sometimes'] : ['required'];

        return [
            'nom' => [...$sometimes, 'string', 'max:190'],
            'type' => ['nullable', Rule::in(['sante', 'institution', 'ong', 'autre'])],
            'telephone' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:120'],
            'actif' => ['nullable', 'boolean'],
        ];
    }
}
