<?php

use App\Http\Controllers\Api\V1\Admin\JournalController;
use App\Http\Controllers\Api\V1\Admin\StructurePartenaireController;
use App\Http\Controllers\Api\V1\Admin\UtilisateurController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BeneficiaireController;
use App\Http\Controllers\Api\V1\CodeQrController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\FichierController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InformationMedicaleController;
use App\Http\Controllers\Api\V1\ObjetController;
use App\Http\Controllers\Api\V1\ProfilAssistanceController;
use App\Http\Controllers\Api\V1\ScanController;
use Illuminate\Support\Facades\Route;

/*
| API SIAP v1 — préfixe final : /api/v1
| Format de réponse : voir App\Support\ApiResponse (succès + erreurs du cahier §13.3).
*/
Route::prefix('v1')->group(function () {

    // ---- Public -------------------------------------------------------------
    Route::get('health', HealthController::class);

    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // Scan : public. Un jeton Bearer optionnel élève le niveau d'accès (protege / medical).
    Route::get('scan/{code}', [ScanController::class, 'show'])->middleware('throttle:scan');
    Route::post('scan/{code}/decouverte', [ScanController::class, 'signalerDecouverte'])->middleware('throttle:decouverte');
    Route::post('scan/{code}/signalement', [ScanController::class, 'signalerLocalisation'])->middleware('throttle:decouverte');

    // Photos : accessibles uniquement via une URL signée temporaire (générée par l'API).
    Route::get('fichiers/{type}/{id}/photo', [FichierController::class, 'photo'])
        ->middleware('signed')
        ->name('fichiers.photo');

    // ---- Authentifié --------------------------------------------------------
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/push-token', [AuthController::class, 'enregistrerPushToken']);

        // Bénéficiaires
        Route::get('beneficiaires', [BeneficiaireController::class, 'index']);
        Route::post('beneficiaires', [BeneficiaireController::class, 'store']);
        Route::get('beneficiaires/{beneficiaire}', [BeneficiaireController::class, 'show']);
        Route::put('beneficiaires/{beneficiaire}', [BeneficiaireController::class, 'update']);
        Route::delete('beneficiaires/{beneficiaire}', [BeneficiaireController::class, 'destroy']);
        Route::post('beneficiaires/{beneficiaire}/statut', [BeneficiaireController::class, 'statut']);
        Route::post('beneficiaires/{beneficiaire}/photo', [BeneficiaireController::class, 'photo']);

        // Contacts (contacts d'urgence, parents/tuteurs, proches autorisés)
        Route::get('beneficiaires/{beneficiaire}/contacts', [ContactController::class, 'index']);
        Route::post('beneficiaires/{beneficiaire}/contacts', [ContactController::class, 'store']);
        Route::put('contacts/{contact}', [ContactController::class, 'update']);
        Route::delete('contacts/{contact}', [ContactController::class, 'destroy']);

        // Informations médicales (structures de santé + propriétaire)
        Route::get('beneficiaires/{beneficiaire}/information-medicale', [InformationMedicaleController::class, 'show']);
        Route::put('beneficiaires/{beneficiaire}/information-medicale', [InformationMedicaleController::class, 'update']);

        // Profils d'assistance
        Route::put('beneficiaires/{beneficiaire}/profil-enfant', [ProfilAssistanceController::class, 'updateEnfant']);
        Route::put('beneficiaires/{beneficiaire}/profil-vulnerable', [ProfilAssistanceController::class, 'updateVulnerable']);

        // Objets perdus
        Route::get('objets', [ObjetController::class, 'index']);
        Route::post('objets', [ObjetController::class, 'store']);
        Route::get('objets/{objet}', [ObjetController::class, 'show']);
        Route::put('objets/{objet}', [ObjetController::class, 'update']);
        Route::post('objets/{objet}/statut', [ObjetController::class, 'statut']);
        Route::post('objets/{objet}/photo', [ObjetController::class, 'photo']);

        // Codes QR (admin + agents de terrain)
        Route::middleware('can:qr.gerer')->prefix('codes-qr')->group(function () {
            Route::get('/', [CodeQrController::class, 'index']);
            Route::post('/', [CodeQrController::class, 'store']);
            Route::get('code/{code}', [CodeQrController::class, 'parCode']);
            Route::get('{codeQr}', [CodeQrController::class, 'show']);
            Route::post('{codeQr}/associer', [CodeQrController::class, 'associer']);
            Route::post('{codeQr}/statut', [CodeQrController::class, 'statut']);
            Route::post('{codeQr}/remplacer', [CodeQrController::class, 'remplacer']);
            Route::get('{codeQr}/image', [CodeQrController::class, 'image']);
        });

        // Administration
        Route::prefix('admin')->group(function () {
            Route::middleware('can:utilisateurs.gerer')->group(function () {
                Route::get('utilisateurs', [UtilisateurController::class, 'index']);
                Route::post('utilisateurs', [UtilisateurController::class, 'store']);
                Route::put('utilisateurs/{utilisateur}', [UtilisateurController::class, 'update']);
            });
            Route::middleware('can:structures.gerer')->group(function () {
                Route::get('structures', [StructurePartenaireController::class, 'index']);
                Route::post('structures', [StructurePartenaireController::class, 'store']);
                Route::put('structures/{structure}', [StructurePartenaireController::class, 'update']);
            });
            Route::get('journal', [JournalController::class, 'index'])->middleware('can:journal.voir');
        });
    });
});
