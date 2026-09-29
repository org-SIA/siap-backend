<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ScanService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    /**
     * Scan d'un code QR (route publique).
     *
     * Sans jeton : niveau "public". Avec un jeton Bearer valide : niveau selon les droits du compte
     * (protege / medical). Le champ `niveau_acces` de la réponse dit ce qui a été accordé ; les autres
     * champs dépendent de ce niveau et ne doivent jamais être supposés présents.
     */
    public function show(Request $request, string $code, ScanService $scan): JsonResponse
    {
        return ApiResponse::ok($scan->resolve($code, $request->user('sanctum')));
    }

    /** Signaler la découverte d'un objet portant ce QR (route publique, sans compte). */
    public function signalerDecouverte(Request $request, string $code, ScanService $scan): JsonResponse
    {
        $data = $this->validerSignalement($request);
        $scan->signalerDecouverte($code, $data, $request->ip());

        return ApiResponse::ok(['message' => 'Votre signalement a bien été enregistré. Le propriétaire va être averti.'], null, 201);
    }

    /** Signaler qu'un enfant ou une personne vulnérable a été retrouvé(e) (route publique, sans compte). */
    public function signalerLocalisation(Request $request, string $code, ScanService $scan): JsonResponse
    {
        $data = $this->validerSignalement($request);
        $scan->signalerLocalisation($code, $data, $request->ip());

        return ApiResponse::ok(['message' => 'Merci. Les proches autorisés vont être alertés.'], null, 201);
    }

    private function validerSignalement(Request $request): array
    {
        return $request->validate([
            'nom' => ['nullable', 'string', 'max:120'],
            'telephone' => ['required_without:message', 'nullable', 'string', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
            'message' => ['required_without:telephone', 'nullable', 'string', 'max:1000'],
            'lieu' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
    }
}
