<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ObjetStatut;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Objet;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ObjetController extends Controller
{
    private const TEL = 'regex:/^\+?[0-9\s\-]{8,20}$/';

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Objet::query()->latest();

        if (! $user->can('objets.gerer_tous')) {
            $query->where('user_id', $user->id);
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->query('statut'));
        }
        if ($s = trim((string) $request->query('q'))) {
            $like = '%'.mb_strtolower($s).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(categorie) LIKE ?', [$like])->orWhereRaw('LOWER(description) LIKE ?', [$like]));
        }

        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        return ApiResponse::paginated($query->paginate($perPage), fn (Objet $o) => $o->toApi());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Objet::class);
        $user = $request->user();

        $data = $request->validate([
            'categorie' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'confidentialite_active' => ['nullable', 'boolean'],
            'proprietaire_nom' => ['nullable', 'string', 'max:120'],
            'proprietaire_telephone' => ['nullable', 'string', self::TEL],
            'proprietaire_email' => ['nullable', 'email', 'max:190'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $data['user_id'] = $user->can('objets.gerer_tous') ? ($data['user_id'] ?? null) : $user->id;
        $data['created_by'] = $user->id;
        $aProprietaire = $data['user_id'] || ! empty($data['proprietaire_nom']) || ! empty($data['proprietaire_telephone']);
        $data['statut'] = $aProprietaire ? ObjetStatut::Associe : ObjetStatut::Enregistre;

        $objet = Objet::create($data);
        Audit::log('objet.cree', $objet);

        return ApiResponse::ok($this->complet($objet->fresh()), null, 201);
    }

    public function show(Objet $objet): JsonResponse
    {
        Gate::authorize('view', $objet);

        return ApiResponse::ok($this->complet($objet));
    }

    public function update(Request $request, Objet $objet): JsonResponse
    {
        Gate::authorize('update', $objet);

        $data = $request->validate([
            'categorie' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'confidentialite_active' => ['nullable', 'boolean'],
            'proprietaire_nom' => ['nullable', 'string', 'max:120'],
            'proprietaire_telephone' => ['nullable', 'string', self::TEL],
            'proprietaire_email' => ['nullable', 'email', 'max:190'],
        ]);

        $objet->update($data);
        Audit::log('objet.modifie', $objet, ['champs' => array_keys($data)]);

        return ApiResponse::ok($this->complet($objet->fresh()));
    }

    /** Changement d'état : declare_perdu, retrouve, en_cours_restitution, restitue, archive, associe. */
    public function statut(Request $request, Objet $objet): JsonResponse
    {
        Gate::authorize('update', $objet);

        $data = $request->validate(['statut' => ['required', Rule::enum(ObjetStatut::class)]]);
        $cible = ObjetStatut::from($data['statut']);

        if (! $objet->statut->peutPasserA($cible)) {
            throw ApiException::conflit('OBJET-409', 'Transition de statut non autorisée.', [
                'statut_actuel' => $objet->statut->value,
                'statut_demande' => $cible->value,
            ]);
        }

        $ancien = $objet->statut->value;
        $objet->update(['statut' => $cible]);
        Audit::log('objet.statut', $objet, ['de' => $ancien, 'vers' => $cible->value]);

        return ApiResponse::ok($this->complet($objet->fresh()));
    }

    public function photo(Request $request, Objet $objet): JsonResponse
    {
        Gate::authorize('update', $objet);

        $request->validate(['photo' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048']]);

        $disque = Storage::disk(config('siap.storage_disk'));
        $ancien = $objet->photo_path;
        $objet->update(['photo_path' => $disque->putFile('photos/objets', $request->file('photo'))]);

        if ($ancien) {
            $disque->delete($ancien);
        }
        Audit::log('objet.photo', $objet);

        return ApiResponse::ok(['photo_url' => $objet->fresh()->photoUrl()]);
    }

    /** Objet + signalements de découverte + codes QR associés. */
    private function complet(Objet $o): array
    {
        $o->load(['decouvertes', 'codesQr']);

        return $o->toApi() + [
            'decouvertes' => $o->decouvertes->map(fn ($d) => $d->toApi())->values(),
            'codes_qr' => $o->codesQr->map(fn ($q) => ['id' => $q->id, 'code' => $q->code, 'statut' => $q->statut->value])->values(),
        ];
    }
}
