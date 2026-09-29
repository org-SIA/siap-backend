<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Gestion des comptes du personnel (permission utilisateurs.gerer : admin). */
class UtilisateurController extends Controller
{
    private const ROLES = ['admin', 'agent_terrain', 'structure_sante', 'utilisateur'];

    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('roles')->orderBy('name');

        if ($request->filled('role')) {
            $query->role($request->query('role'));
        }
        if ($s = trim((string) $request->query('q'))) {
            $like = '%'.mb_strtolower($s).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(email) LIKE ?', [$like]));
        }

        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        return ApiResponse::paginated($query->paginate($perPage), fn (User $u) => $u->toApi());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'telephone' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
            'role' => ['required', Rule::in(self::ROLES)],
            'structure_partenaire_id' => ['nullable', 'integer', 'exists:structures_partenaires,id', 'required_if:role,structure_sante'],
        ]);

        $role = $data['role'];
        unset($data['role']);

        $user = User::create($data);
        $user->assignRole($role);
        Audit::log('utilisateur.cree', $user, ['role' => $role], 'securite');

        return ApiResponse::ok($user->toApi(), null, 201);
    }

    public function update(Request $request, User $utilisateur): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'telephone' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
            'password' => ['sometimes', 'string', 'min:8', 'max:100'],
            'actif' => ['sometimes', 'boolean'],
            'role' => ['sometimes', Rule::in(self::ROLES)],
            'structure_partenaire_id' => ['nullable', 'integer', 'exists:structures_partenaires,id'],
        ]);

        $role = $data['role'] ?? null;
        unset($data['role']);

        $utilisateur->update($data);

        if ($role) {
            $utilisateur->syncRoles([$role]);
            Audit::log('utilisateur.role_change', $utilisateur, ['role' => $role], 'securite');
        }

        // Compte désactivé ou mot de passe changé : on invalide toutes les sessions ouvertes (cahier §9.4).
        if ((array_key_exists('actif', $data) && ! $data['actif']) || array_key_exists('password', $data)) {
            $utilisateur->tokens()->delete();
        }

        Audit::log('utilisateur.modifie', $utilisateur, ['champs' => array_values(array_diff(array_keys($data), ['password']))], 'securite');

        return ApiResponse::ok($utilisateur->fresh()->toApi());
    }
}
