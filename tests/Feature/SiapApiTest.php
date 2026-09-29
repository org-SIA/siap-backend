<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Garde-fous des règles essentielles du cahier : niveaux d'accès du scan, données médicales,
 * format d'erreur standard, cycle de vie d'un QR. Lancer : php artisan test
 */
class SiapApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['siap.seed_demo' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    private function url(string $nom): string
    {
        return '/api/v1/scan/'.DemoSeeder::code($nom);
    }

    private function acteur(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_scan_public_ne_montre_ni_medical_ni_donnees_protegees(): void
    {
        $r = $this->getJson($this->url('DEMOADULTE'))->assertOk();

        $r->assertJsonPath('success', true)
            ->assertJsonPath('data.niveau_acces', 'public')
            ->assertJsonPath('data.beneficiaire.prenom', 'Jean')
            ->assertJsonMissingPath('data.information_medicale')
            ->assertJsonMissingPath('data.beneficiaire.date_naissance')
            ->assertJsonMissingPath('data.beneficiaire.telephone');

        // Un seul contact est marqué "visible publiquement" dans les données de démo.
        $this->assertCount(1, $r->json('data.contacts'));
        $this->assertStringNotContainsString('Pénicilline', $r->getContent());
    }

    public function test_scan_agent_donne_le_niveau_protege_sans_medical(): void
    {
        Sanctum::actingAs($this->acteur('agent@siap.test'));

        $this->getJson($this->url('DEMOADULTE'))->assertOk()
            ->assertJsonPath('data.niveau_acces', 'protege')
            ->assertJsonPath('data.beneficiaire.telephone', '+237 677 00 11 22')
            ->assertJsonMissingPath('data.information_medicale');
    }

    public function test_scan_structure_de_sante_donne_le_medical(): void
    {
        Sanctum::actingAs($this->acteur('sante@siap.test'));

        $this->getJson($this->url('DEMOADULTE'))->assertOk()
            ->assertJsonPath('data.niveau_acces', 'medical')
            ->assertJsonPath('data.information_medicale.groupe_sanguin', 'O+');
    }

    public function test_qr_inconnu_et_qr_desactive_suivent_le_format_d_erreur_standard(): void
    {
        $this->getJson('/api/v1/scan/INCONNU')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'QR-404')
            ->assertJsonPath('error.category', 'QR')
            ->assertJsonStructure(['error' => ['code', 'category', 'severity', 'message', 'timestamp', 'requestId', 'details']]);

        $this->getJson($this->url('DEMODESACTIVE'))
            ->assertStatus(410)
            ->assertJsonPath('error.code', 'QR-410');
    }

    public function test_objet_masque_les_coordonnees_du_proprietaire_au_public(): void
    {
        $this->getJson($this->url('DEMOOBJET'))->assertOk()
            ->assertJsonPath('data.type_cible', 'objet')
            ->assertJsonPath('data.proprietaire', null);

        Sanctum::actingAs($this->acteur('user@siap.test'));
        $this->getJson($this->url('DEMOOBJET'))->assertOk()
            ->assertJsonPath('data.proprietaire.nom', 'Utilisateur Démo');
    }

    public function test_agent_ne_peut_pas_lire_les_donnees_medicales_via_l_api(): void
    {
        $id = \App\Models\Beneficiaire::where('nom', 'Mbarga')->value('id');

        Sanctum::actingAs($this->acteur('agent@siap.test'));
        $this->getJson("/api/v1/beneficiaires/{$id}/information-medicale")
            ->assertStatus(403)->assertJsonPath('error.code', 'ACCESS-403');

        Sanctum::actingAs($this->acteur('sante@siap.test'));
        $this->getJson("/api/v1/beneficiaires/{$id}/information-medicale")
            ->assertOk()->assertJsonPath('data.allergies', 'Pénicilline');
    }

    public function test_route_protegee_sans_jeton_renvoie_auth_401(): void
    {
        $this->getJson('/api/v1/beneficiaires')
            ->assertStatus(401)->assertJsonPath('error.code', 'AUTH-401');
    }

    public function test_connexion_et_identifiants_invalides(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'agent@siap.test', 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at', 'user' => ['roles', 'permissions']]]);

        $this->postJson('/api/v1/auth/login', ['email' => 'agent@siap.test', 'password' => 'mauvais'])
            ->assertStatus(401)->assertJsonPath('error.code', 'AUTH-401');
    }

    public function test_validation_renvoie_les_champs_en_erreur(): void
    {
        Sanctum::actingAs($this->acteur('agent@siap.test'));

        $this->postJson('/api/v1/beneficiaires', ['nom' => 'X'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION-422')
            ->assertJsonStructure(['error' => ['details' => ['champs' => ['type_profil', 'prenom']]]]);
    }

    public function test_cycle_de_vie_d_un_qr_creation_association_activation_puis_scan(): void
    {
        Sanctum::actingAs($this->acteur('agent@siap.test'));

        $qr = $this->postJson('/api/v1/codes-qr', ['quantite' => 1])->assertCreated()->json('data.0');
        $this->assertSame('cree', $qr['statut']);

        // Un code non activé ne donne accès à rien.
        $this->getJson('/api/v1/scan/'.$qr['code'])->assertStatus(410);

        $ben = $this->postJson('/api/v1/beneficiaires', ['type_profil' => 'adulte', 'nom' => 'Test', 'prenom' => 'Nouveau'])
            ->assertCreated()->json('data');
        $this->postJson("/api/v1/beneficiaires/{$ben['id']}/statut", ['statut' => 'actif'])->assertOk();

// Passer directement de "cree" à "actif" est une transition interdite (il faut d'abord associer).
$this->postJson("/api/v1/codes-qr/{$qr['id']}/statut", ['statut' => 'actif'])
    ->assertStatus(409)->assertJsonPath('error.code', 'QR-409');
        $this->postJson("/api/v1/codes-qr/{$qr['id']}/associer", [
            'cible_type' => 'beneficiaire', 'cible_id' => $ben['id'], 'activer' => true,
        ])->assertOk()->assertJsonPath('data.statut', 'actif');

        $this->getJson('/api/v1/scan/'.$qr['code'])->assertOk()->assertJsonPath('data.beneficiaire.prenom', 'Nouveau');

        // Transition interdite : actif -> associe.
        $this->postJson("/api/v1/codes-qr/{$qr['id']}/associer", [
            'cible_type' => 'beneficiaire', 'cible_id' => $ben['id'],
        ])->assertStatus(409)->assertJsonPath('error.code', 'QR-409');

        // Remplacement : l'ancien code cesse de fonctionner, le nouveau prend le relais.
        $nouveau = $this->postJson("/api/v1/codes-qr/{$qr['id']}/remplacer")->assertCreated()->json('data');
        $this->getJson('/api/v1/scan/'.$qr['code'])->assertStatus(410);
        $this->getJson('/api/v1/scan/'.$nouveau['code'])->assertOk();
    }

    public function test_signalement_objet_retrouve_alerte_le_proprietaire(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $this->postJson($this->url('DEMOOBJET').'/decouverte', [
            'nom' => 'Un passant', 'telephone' => '+237 611 22 33 44', 'lieu' => 'Marché central',
        ])->assertCreated()->assertJsonPath('success', true);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\EnvoyerAlerteObjetJob::class);

        $objet = \App\Models\Objet::where('categorie', 'Sac à dos')->first();
        $this->assertSame('retrouve', $objet->statut->value);
    }

    public function test_signalement_enfant_retrouve_alerte_les_contacts(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $this->postJson($this->url('DEMOENFANT').'/signalement', [
            'nom' => 'Une voisine', 'telephone' => '+237 622 33 44 55', 'lieu' => 'Marché Dang',
        ])->assertCreated()->assertJsonPath('success', true);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\EnvoyerAlerteBeneficiaireJob::class);
    }

    public function test_alerte_service_envoie_bien_un_sms_au_contact_visible(): void
    {
        $enfant = \App\Models\Beneficiaire::where('nom', 'Abena')->firstOrFail();
        $signalement = \App\Models\SignalementBeneficiaire::create([
            'beneficiaire_id' => $enfant->id, 'signale_par_telephone' => '+237 600 00 00 00',
        ]);

        $sms = \Mockery::mock(\App\Services\Notifications\SmsGateway::class);
        $sms->shouldReceive('envoyer')->once()->andReturn(true);
        $this->app->instance(\App\Services\Notifications\SmsGateway::class, $sms);

        $push = \Mockery::mock(\App\Services\Notifications\PushGateway::class);
        $push->shouldReceive('envoyer')->zeroOrMoreTimes()->andReturn(true);
        $this->app->instance(\App\Services\Notifications\PushGateway::class, $push);

        app(\App\Services\AlerteService::class)->alerterPourBeneficiaire($enfant, $signalement);

        $this->assertTrue($signalement->fresh()->contacts_alertes);
    }

    public function test_utilisateur_ordinaire_ne_voit_que_ses_propres_profils(): void
    {
        Sanctum::actingAs($this->acteur('user@siap.test'));
        $this->assertCount(3, $this->getJson('/api/v1/beneficiaires')->assertOk()->json('data'));

        // Un autre compte ne voit rien et ne peut pas accéder au profil d'autrui.
        $autre = User::create(['name' => 'Autre', 'email' => 'autre@siap.test', 'password' => 'password']);
        $autre->assignRole('utilisateur');
        $id = \App\Models\Beneficiaire::where('nom', 'Mbarga')->value('id');

        Sanctum::actingAs($autre);
        $this->assertCount(0, $this->getJson('/api/v1/beneficiaires')->json('data'));
        $this->getJson("/api/v1/beneficiaires/{$id}")->assertStatus(403);
    }
}
