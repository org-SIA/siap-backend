<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BeneficiaireStatut;
use App\Enums\ObjetStatut;
use App\Enums\QrStatut;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Beneficiaire;
use App\Models\CodeQr;
use App\Models\Objet;
use App\Services\QrImage;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Gestion des codes QR (permission qr.gerer : admin et agents de terrain). */
class CodeQrController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CodeQr::query()->latest();

        if ($request->filled('statut')) {
            $query->where('statut', $request->query('statut'));
        }
        if ($s = trim((string) $request->query('q'))) {
            $query->where('code', 'like', '%'.$s.'%');
        }

        $perPage = min(max((int) $request->query('per_page', 50), 1), 200);

        return ApiResponse::paginated($query->paginate($perPage), fn (CodeQr $q) => $q->toApi());
    }

    /** Génère un lot de codes vierges (statut "cree"), prêts à imprimer. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['quantite' => ['required', 'integer', 'min:1', 'max:200']]);

        $codes = collect(range(1, $data['quantite']))->map(fn () => CodeQr::create([
            'code' => CodeQr::genererCode(),
            'statut' => QrStatut::Cree,
            'created_by' => $request->user()->id,
        ]));

        Audit::log('qr.lot_cree', null, ['quantite' => $data['quantite']]);

        return ApiResponse::ok($codes->map(fn (CodeQr $q) => $q->toApi())->values(), null, 201);
    }

    public function show(CodeQr $codeQr): JsonResponse
    {
        return ApiResponse::ok($codeQr->toApi());
    }

    /**
     * Retrouve un code à partir du contenu scanné (utile à l'agent qui scanne un autocollant vierge
     * pour l'associer à un bénéficiaire : le scan public refuse les codes non activés).
     */
    public function parCode(string $code): JsonResponse
    {
        $qr = CodeQr::where('code', $code)->first();
        if (! $qr) {
            throw ApiException::qrInconnu();
        }

        return ApiResponse::ok($qr->toApi());
    }

    /** Associe le code à un bénéficiaire ou à un objet. `activer=true` l'active dans la foulée. */
    public function associer(Request $request, CodeQr $codeQr): JsonResponse
    {
        $data = $request->validate([
            'cible_type' => ['required', Rule::in(['beneficiaire', 'objet'])],
            'cible_id' => ['required', 'string', 'size:26'],
            'activer' => ['nullable', 'boolean'],
        ]);

        if (! $codeQr->statut->peutPasserA(QrStatut::Associe)) {
            throw ApiException::conflit('QR-409', "Ce code ne peut plus être associé dans son état actuel.", ['statut_actuel' => $codeQr->statut->value]);
        }

        $classe = Relation::getMorphedModel($data['cible_type']);
        $cible = $classe::find($data['cible_id']);
        if (! $cible) {
            throw ApiException::introuvable('Cible introuvable.');
        }
        if (($cible instanceof Beneficiaire && $cible->statut === BeneficiaireStatut::Archive)
            || ($cible instanceof Objet && $cible->statut === ObjetStatut::Archive)) {
            throw ApiException::validation('Impossible d\'associer un code à une ressource archivée.');
        }

        DB::transaction(function () use ($codeQr, $cible, $data) {
            $codeQr->cible()->associate($cible);
            $codeQr->statut = QrStatut::Associe;
            if (! empty($data['activer'])) {
                $codeQr->statut = QrStatut::Actif;
                $codeQr->active_le = now();
            }
            $codeQr->save();
        });

        Audit::log('qr.associe', $codeQr, ['cible_type' => $data['cible_type'], 'cible_id' => $data['cible_id'], 'active' => ! empty($data['activer'])]);

        return ApiResponse::ok($codeQr->fresh()->toApi());
    }

    /** Active, suspend ou désactive un code (transitions contrôlées, cahier §7.4.1). */
    public function statut(Request $request, CodeQr $codeQr): JsonResponse
    {
        $data = $request->validate(['statut' => ['required', Rule::in(['actif', 'suspendu', 'desactive'])]]);
        $cible = QrStatut::from($data['statut']);

        if (! $codeQr->statut->peutPasserA($cible)) {
            throw ApiException::conflit('QR-409', 'Transition de statut non autorisée.', [
                'statut_actuel' => $codeQr->statut->value,
                'statut_demande' => $cible->value,
            ]);
        }
        if ($cible === QrStatut::Actif && ! $codeQr->cible_id) {
            throw ApiException::validation("Ce code doit être associé à un bénéficiaire ou à un objet avant d'être activé.");
        }

        $ancien = $codeQr->statut->value;
        $codeQr->statut = $cible;
        if ($cible === QrStatut::Actif) {
            $codeQr->active_le = now();
        }
        if ($cible === QrStatut::Desactive) {
            $codeQr->desactive_le = now();
        }
        $codeQr->save();

        Audit::log('qr.statut', $codeQr, ['de' => $ancien, 'vers' => $cible->value], 'securite');

        return ApiResponse::ok($codeQr->toApi());
    }

    /** Autocollant perdu ou abîmé : crée un nouveau code actif sur la même cible, l'ancien devient "remplace". */
    public function remplacer(CodeQr $codeQr): JsonResponse
    {
        if (! $codeQr->statut->peutPasserA(QrStatut::Remplace) || ! $codeQr->cible_id) {
            throw ApiException::conflit('QR-409', 'Ce code ne peut pas être remplacé dans son état actuel.', ['statut_actuel' => $codeQr->statut->value]);
        }

        $nouveau = DB::transaction(function () use ($codeQr) {
            $nouveau = CodeQr::create([
                'code' => CodeQr::genererCode(),
                'statut' => QrStatut::Actif,
                'cible_type' => $codeQr->cible_type,
                'cible_id' => $codeQr->cible_id,
                'active_le' => now(),
                'created_by' => request()->user()->id,
            ]);
            $codeQr->update(['statut' => QrStatut::Remplace, 'remplace_par_id' => $nouveau->id, 'desactive_le' => now()]);

            return $nouveau;
        });

        Audit::log('qr.remplace', $codeQr, ['nouveau_code_id' => $nouveau->id], 'securite');

        return ApiResponse::ok($nouveau->toApi(), null, 201);
    }

    /** Image SVG du QR (à imprimer ou à afficher). Contenu = URL de scan du frontend. */
    public function image(CodeQr $codeQr): Response
    {
        return response(QrImage::svg($codeQr->url()), 200, ['Content-Type' => 'image/svg+xml']);
    }
}
