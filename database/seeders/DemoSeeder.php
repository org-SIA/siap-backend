<?php

namespace Database\Seeders;

use App\Enums\BeneficiaireStatut;
use App\Enums\ContactType;
use App\Enums\ObjetStatut;
use App\Enums\ProfilType;
use App\Enums\QrStatut;
use App\Models\Beneficiaire;
use App\Models\CodeQr;
use App\Models\InformationMedicale;
use App\Models\Objet;
use App\Models\StructurePartenaire;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Données de test pour le développement du frontend.
 * Comptes : mot de passe "password" pour tous. Codes QR à valeur FIXE (voir docs/API.md).
 */
class DemoSeeder extends Seeder
{
    /** Code de démo = nom complété par des 0 jusqu'à 24 caractères. */
    public static function code(string $nom): string
    {
        return str_pad($nom, 24, '0');
    }

    public function run(): void
    {
        $structure = StructurePartenaire::firstOrCreate(
            ['nom' => 'Hôpital Régional de Ngaoundéré'],
            ['type' => 'sante', 'ville' => 'Ngaoundéré', 'telephone' => '+237 222 25 10 10'],
        );

        $admin = $this->utilisateur('Admin SIAP', 'admin@siap.test', 'admin');
        $agent = $this->utilisateur('Agent Terrain', 'agent@siap.test', 'agent_terrain');
        $sante = $this->utilisateur('Dr Démo', 'sante@siap.test', 'structure_sante', $structure->id);
        $user = $this->utilisateur('Utilisateur Démo', 'user@siap.test', 'utilisateur');

        // --- Adulte (visible en public : prénom/nom + 1 contact public ; le reste est protégé) ---
        $adulte = Beneficiaire::firstOrCreate(['nom' => 'Mbarga', 'prenom' => 'Jean'], [
            'user_id' => $user->id, 'created_by' => $agent->id, 'type_profil' => ProfilType::Adulte,
            'statut' => BeneficiaireStatut::Actif, 'date_naissance' => '1985-04-12',
            'telephone' => '+237 677 00 11 22', 'ville' => 'Ngaoundéré', 'adresse' => 'Quartier Baladji',
        ]);
        if (! $adulte->contacts()->exists()) {
            $adulte->contacts()->createMany([
                ['type' => ContactType::Urgence, 'nom' => 'Mbarga', 'prenom' => 'Marie', 'lien' => 'Épouse', 'telephone' => '+237 699 11 22 33', 'priorite' => 1, 'visible_publiquement' => true, 'preference_contact' => 'appel'],
                ['type' => ContactType::Urgence, 'nom' => 'Ndzana', 'prenom' => 'Paul', 'lien' => 'Frère', 'telephone' => '+237 655 44 55 66', 'priorite' => 2, 'visible_publiquement' => false, 'preference_contact' => 'sms'],
            ]);
        }
        InformationMedicale::updateOrCreate(['beneficiaire_id' => $adulte->id], [
            'groupe_sanguin' => 'O+', 'allergies' => 'Pénicilline', 'traitements' => 'Antihypertenseur (1 comprimé/jour)',
            'informations_essentielles' => 'Hypertension artérielle suivie.', 'updated_by' => $sante->id,
        ]);

        // --- Enfant ---
        $enfant = Beneficiaire::firstOrCreate(['nom' => 'Abena', 'prenom' => 'Léa'], [
            'user_id' => $user->id, 'created_by' => $agent->id, 'type_profil' => ProfilType::Enfant,
            'statut' => BeneficiaireStatut::Actif, 'date_naissance' => '2018-09-03', 'ville' => 'Ngaoundéré',
        ]);
        if (! $enfant->contacts()->exists()) {
            $enfant->contacts()->create(['type' => ContactType::ParentTuteur, 'nom' => 'Abena', 'prenom' => 'Sophie', 'lien' => 'Mère', 'telephone' => '+237 670 12 34 56', 'priorite' => 1, 'visible_publiquement' => true, 'preference_contact' => 'les_deux']);
        }
        $enfant->profilEnfant()->updateOrCreate([], [
            'ecole' => 'École publique de Dang', 'signes_distinctifs' => 'Petite cicatrice au genou gauche',
            'informations_reunification' => 'Rester avec l\'enfant et appeler la mère.',
        ]);

        // --- Personne vulnérable ---
        $vulnerable = Beneficiaire::firstOrCreate(['nom' => 'Hamadou', 'prenom' => 'Aïssatou'], [
            'user_id' => $user->id, 'created_by' => $agent->id, 'type_profil' => ProfilType::PersonneVulnerable,
            'statut' => BeneficiaireStatut::Actif, 'date_naissance' => '1948-01-20', 'ville' => 'Ngaoundéré',
        ]);
        if (! $vulnerable->contacts()->exists()) {
            $vulnerable->contacts()->create(['type' => ContactType::ProcheAutorise, 'nom' => 'Hamadou', 'prenom' => 'Ibrahim', 'lien' => 'Fils', 'telephone' => '+237 690 98 76 54', 'priorite' => 1, 'visible_publiquement' => true, 'preference_contact' => 'appel']);
        }
        $vulnerable->profilVulnerable()->updateOrCreate([], [
            'situation' => 'Désorientation légère (début de démence).',
            'consignes_assistance' => 'Parler calmement, ne pas la laisser seule, appeler son fils.',
            'precautions' => 'Ne pas lui donner de médicaments sans avis médical.',
        ]);

        // --- Objet (confidentialité active : coordonnées masquées au public) ---
        $objet = Objet::firstOrCreate(['categorie' => 'Sac à dos', 'user_id' => $user->id], [
            'created_by' => $agent->id, 'description' => 'Sac noir avec fermeture rouge',
            'statut' => ObjetStatut::Associe, 'confidentialite_active' => true,
        ]);

        // --- Codes QR à valeurs fixes ---
        $this->qr('DEMOADULTE', QrStatut::Actif, $adulte);
        $this->qr('DEMOENFANT', QrStatut::Actif, $enfant);
        $this->qr('DEMOVULNERABLE', QrStatut::Actif, $vulnerable);
        $this->qr('DEMOOBJET', QrStatut::Actif, $objet);
        $this->qr('DEMODESACTIVE', QrStatut::Desactive, $adulte);
        $this->qr('DEMOLIBRE', QrStatut::Cree, null); // vierge : à associer via /codes-qr/{id}/associer
    }

    private function utilisateur(string $nom, string $email, string $role, ?int $structureId = null): User
    {
        $u = User::firstOrCreate(['email' => $email], [
            'name' => $nom, 'password' => 'password', 'structure_partenaire_id' => $structureId, 'telephone' => '+237 600 00 00 00',
        ]);
        $u->syncRoles([$role]);

        return $u;
    }

    private function qr(string $nom, QrStatut $statut, ?object $cible): void
    {
        $qr = CodeQr::firstOrNew(['code' => self::code($nom)]);
        $qr->statut = $statut;
        if ($cible) {
            $qr->cible()->associate($cible);
        }
        $qr->active_le = $statut === QrStatut::Actif ? now() : null;
        $qr->desactive_le = $statut === QrStatut::Desactive ? now() : null;
        $qr->save();
    }
}
