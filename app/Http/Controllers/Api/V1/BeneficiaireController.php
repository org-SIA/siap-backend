<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BeneficiaireStatut;
use App\Enums\ProfilType;
use App\Enums\QrStatut;
use App\Http\Controllers\Controller;
use App\Models\Beneficiaire;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BeneficiaireController extends Controller
{
    private const TEL = 'regex:/^\+?[0-9\s\-]{8,20}$/';

    /** Personnel : tous les profils (filtrables). Utilisateur ordinaire : uniquement les siens. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Beneficiaire::query()->latest();

        if (! $user->can('beneficiaires.voir_tous')) {
            $query->where('user_id', $user->id);
        }

        if ($s = trim((string) $request->query('q'))) {
            $like = '%'.mb_strtolower($s).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(nom) LIKE ?', [$like])->orWhereRaw('LOWER(prenom) LIKE ?', [$like]));
        }
        if ($request->filled('type_profil')) {
            $query->where('type_profil', $request->query('type_profil'));
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->query('statut'));
        }

        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        return ApiResponse::paginated($query->paginate($perPage), fn (Beneficiaire $b) => $b->toApi());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Beneficiaire::class);
        $user = $request->user();

        $data = $request->validate([
            'type_profil' => ['required', Rule::enum(ProfilType::class)],
            'nom' => ['required', 'string', 'max:120'],
            'prenom' => ['required', 'string', 'max:120'],
            'date_naissance' => ['nullable', 'date', 'before:tomorrow'],
            'telephone' => ['nullable', 'string', self::TEL],
            'email' => ['nullable', 'email', 'max:190'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Le personnel peut enregistrer un profil pour un tiers ; un utilisateur ordinaire crée le sien.
        $data['user_id'] = $user->can('beneficiaires.voir_tous') ? ($data['user_id'] ?? null) : $user->id;
        $data['created_by'] = $user->id;
        $data['statut'] = BeneficiaireStatut::EnCreation;

        $b = Beneficiaire::create($data);
        Audit::log('beneficiaire.cree', $b, ['type_profil' => $b->type_profil->value]);

        return ApiResponse::ok($this->complet($b), null, 201);
    }

    public function show(Beneficiaire $beneficiaire): JsonResponse
    {
        Gate::authorize('view', $beneficiaire);

        return ApiResponse::ok($this->complet($beneficiaire));
    }

    public function update(Request $request, Beneficiaire $beneficiaire): JsonResponse
    {
        Gate::authorize('update', $beneficiaire);

        $data = $request->validate([
            'type_profil' => ['sometimes', Rule::enum(ProfilType::class)],
            'nom' => ['sometimes', 'string', 'max:120'],
            'prenom' => ['sometimes', 'string', 'max:120'],
            'date_naissance' => ['nullable', 'date', 'before:tomorrow'],
            'telephone' => ['nullable', 'string', self::TEL],
            'email' => ['nullable', 'email', 'max:190'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:120'],
        ]);

        $beneficiaire->update($data);
        Audit::log('beneficiaire.modifie', $beneficiaire, ['champs' => array_keys($data)]);

        return ApiResponse::ok($this->complet($beneficiaire->fresh()));
    }

    /** Archivage (pas de suppression physique). Désactive aussi les QR codes du profil. */
    public function destroy(Beneficiaire $beneficiaire): JsonResponse
    {
        Gate::authorize('delete', $beneficiaire);

        $beneficiaire->update(['statut' => BeneficiaireStatut::Archive]);
        $beneficiaire->codesQr()
            ->whereIn('statut', [QrStatut::Associe->value, QrStatut::Actif->value, QrStatut::Suspendu->value])
            ->update(['statut' => QrStatut::Desactive->value, 'desactive_le' => now()]);

        Audit::log('beneficiaire.archive', $beneficiaire);

        return ApiResponse::ok(['message' => 'Profil archivé.']);
    }

    /** Changement d'état. Propriétaire : en_creation / incomplet / actif. Personnel : tous (dont suspendu, archive). */
    public function statut(Request $request, Beneficiaire $beneficiaire): JsonResponse
    {
        Gate::authorize('update', $beneficiaire);

        $personnel = $request->user()->can('beneficiaires.modifier');
        $autorises = $personnel
            ? array_column(BeneficiaireStatut::cases(), 'value')
            : [BeneficiaireStatut::EnCreation->value, BeneficiaireStatut::Incomplet->value, BeneficiaireStatut::Actif->value];

        $data = $request->validate(['statut' => ['required', Rule::in($autorises)]]);

        $ancien = $beneficiaire->statut->value;
        $beneficiaire->update(['statut' => $data['statut']]);
        Audit::log('beneficiaire.statut', $beneficiaire, ['de' => $ancien, 'vers' => $data['statut']]);

        return ApiResponse::ok($this->complet($beneficiaire->fresh()));
    }

    /** Envoi de la photo (multipart/form-data, champ "photo"). Stockage privé. */
    public function photo(Request $request, Beneficiaire $beneficiaire): JsonResponse
    {
        Gate::authorize('update', $beneficiaire);

        $request->validate(['photo' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048']]);

        $disque = Storage::disk(config('siap.storage_disk'));
        $ancien = $beneficiaire->photo_path;
        $chemin = $disque->putFile('photos/beneficiaires', $request->file('photo'));
        $beneficiaire->update(['photo_path' => $chemin]);

        if ($ancien) {
            $disque->delete($ancien);
        }
        Audit::log('beneficiaire.photo', $beneficiaire);

        return ApiResponse::ok(['photo_url' => $beneficiaire->fresh()->photoUrl()]);
    }

    /** Profil complet (sans les données médicales, servies par leur propre endpoint). */
    private function complet(Beneficiaire $b): array
    {
        $b->load(['contacts', 'profilEnfant', 'profilVulnerable', 'codesQr']);

        return $b->toApi() + [
            'contacts' => $b->contacts->map(fn ($c) => $c->toApi())->values(),
            'profil_enfant' => $b->profilEnfant?->toApi(),
            'profil_vulnerable' => $b->profilVulnerable?->toApi(),
            'codes_qr' => $b->codesQr->map(fn ($q) => ['id' => $q->id, 'code' => $q->code, 'statut' => $q->statut->value])->values(),
        ];
    }
}
