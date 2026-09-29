<?php

namespace App\Providers;

use App\Models\Beneficiaire;
use App\Models\Objet;
use App\Models\User;
use App\Policies\BeneficiairePolicy;
use App\Policies\ObjetPolicy;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\Notifications\FcmPushGateway;
use App\Services\Notifications\HttpSmsGateway;
use App\Services\Notifications\LogPushGateway;
use App\Services\Notifications\LogSmsGateway;
use App\Services\Notifications\PushGateway;
use App\Services\Notifications\SmsGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, fn () => config('siap.sms_driver') === 'http' ? new HttpSmsGateway() : new LogSmsGateway());
        $this->app->bind(PushGateway::class, fn () => config('siap.push_driver') === 'fcm' ? new FcmPushGateway() : new LogPushGateway());
    }

    public function boot(): void
    {
        // Valeurs lisibles dans cible_type ('beneficiaire' / 'objet') au lieu des noms de classes.
        Relation::morphMap([
            'beneficiaire' => Beneficiaire::class,
            'objet' => Objet::class,
        ]);

        Gate::policy(Beneficiaire::class, BeneficiairePolicy::class);
        Gate::policy(Objet::class, ObjetPolicy::class);

        // Données médicales : permission dédiée OU propriétaire du profil.
        Gate::define('voir-medical', fn (User $u, Beneficiaire $b) => $u->can('medical.voir') || $b->estProprietaire($u));
        Gate::define('modifier-medical', fn (User $u, Beneficiaire $b) => $u->can('medical.modifier') || $b->estProprietaire($u));

        $this->limitesDeDebit();
        $this->documentationApi();
    }

    private function limitesDeDebit(): void
    {
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('register', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        // Le scan est public : plafonné par IP pour empêcher l'énumération des codes.
        RateLimiter::for('scan', fn (Request $r) => Limit::perMinute(config('siap.scan_rate_limit'))->by($r->ip()));
        RateLimiter::for('decouverte', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
    }

    private function documentationApi(): void
    {
        // Swagger UI : /docs/api  —  contrat OpenAPI : /docs/api.json
        // Ouvert en local ; ailleurs, seulement si SIAP_DOCS_PUBLIC=true (le contrat ne contient aucun secret).
        Gate::define('viewApiDocs', fn (?User $user = null) => (bool) config('siap.docs_public'));

        if (class_exists(Scramble::class)) {
            Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
                $openApi->secure(SecurityScheme::http('bearer'));
            });
        }
    }
}
