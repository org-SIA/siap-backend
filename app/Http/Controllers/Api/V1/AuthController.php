<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /** Inscription d'un utilisateur ordinaire (rôle "utilisateur"). */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'telephone' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
        ]);

        $user = User::create($data);
        $user->assignRole('utilisateur');
        Audit::log('auth.inscription', $user, [], 'info', $user);

        return ApiResponse::ok($this->jetons($user), null, 201);
    }

    /** Connexion : renvoie un jeton Bearer à envoyer dans l'en-tête Authorization. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            Audit::log('auth.echec', null, ['email' => $data['email']], 'securite');
            throw new ApiException('AUTH-401', 'Identifiants invalides.', 401, 'ERROR-MAJOR');
        }

        if (! $user->actif) {
            Audit::log('auth.compte_desactive', $user, [], 'securite', $user);
            throw new ApiException('ACCESS-403', 'Ce compte est désactivé.', 403, 'ERROR-MAJOR');
        }

        $user->forceFill(['dernier_login_at' => now()])->save();
        Audit::log('auth.connexion', $user, [], 'info', $user);

        return ApiResponse::ok($this->jetons($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
        Audit::log('auth.deconnexion');

        return ApiResponse::ok(['message' => 'Déconnecté.']);
    }

    /** Utilisateur connecté + rôles + permissions (pour afficher/masquer les écrans côté frontend). */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::ok($request->user()->toApi(true));
    }

    /** Enregistre le jeton FCM de l'app mobile pour recevoir les notifications push. */
    public function enregistrerPushToken(Request $request): JsonResponse
    {
        $data = $request->validate(['push_token' => ['required', 'string', 'max:255']]);
        $request->user()->update(['push_token' => $data['push_token']]);

        return ApiResponse::ok(['message' => 'Jeton push enregistré.']);
    }

    private function jetons(User $user): array
    {
        $expire = now()->addHours(config('siap.token_ttl_hours'));
        $jeton = $user->createToken('siap-api', ['*'], $expire);

        return [
            'token' => $jeton->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expire->toIso8601String(),
            'user' => $user->toApi(true),
        ];
    }
}
