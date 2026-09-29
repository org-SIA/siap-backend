<?php

namespace App\Services;

use App\Enums\BeneficiaireStatut;
use App\Enums\ObjetStatut;
use App\Enums\QrStatut;
use App\Exceptions\ApiException;
use App\Models\Beneficiaire;
use App\Models\CodeQr;
use App\Models\Contact;
use App\Models\DecouverteObjet;
use App\Models\Objet;
use App\Models\SignalementBeneficiaire;
use App\Jobs\EnvoyerAlerteBeneficiaireJob;
use App\Jobs\EnvoyerAlerteObjetJob;
use App\Models\User;
use App\Support\Audit;

/**
 * Cœur du système : résout un code QR et ne renvoie QUE ce que le demandeur a le droit de voir
 * (cahier §6.5, §6.6, §6.7). Le filtrage est fait ici, côté serveur — jamais côté client.
 *
 * Niveaux : public < protege < medical
 *   - public  : n'importe qui (sans connexion)
 *   - protege : personnel avec la permission beneficiaires.voir_tous (admin, agent de terrain)
 *   - medical : structure de santé (medical.voir) ou propriétaire du profil
 */
class ScanService
{
    public function resolve(string $code, ?User $viewer): array
    {
        $qr = $this->trouverQrActif($code);
        $cible = $qr->cible;

        return match (true) {
            $cible instanceof Beneficiaire => $this->scanBeneficiaire($qr, $cible, $viewer),
            $cible instanceof Objet => $this->scanObjet($qr, $cible, $viewer),
            default => throw ApiException::profilIndisponible(),
        };
    }

    public function signalerDecouverte(string $code, array $data, ?string $ip): DecouverteObjet
    {
        $qr = $this->trouverQrActif($code);
        $objet = $qr->cible;

        if (! $objet instanceof Objet) {
            throw ApiException::validation("Ce code QR n'est pas associé à un objet.");
        }

        $decouverte = DecouverteObjet::create([
            'objet_id' => $objet->id,
            'signale_par_nom' => $data['nom'] ?? null,
            'signale_par_telephone' => $data['telephone'] ?? null,
            'message' => $data['message'] ?? null,
            'lieu' => $data['lieu'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'ip' => $ip,
        ]);

        if (in_array($objet->statut, [ObjetStatut::Enregistre, ObjetStatut::Associe, ObjetStatut::DeclarePerdu], true)) {
            $objet->update(['statut' => ObjetStatut::Retrouve]);
        }

        Audit::log('objet.decouverte', $objet, ['decouverte_id' => $decouverte->id]);
        // Envoi asynchrone (SMS/e-mail/push) : ne bloque jamais la réponse au signaleur.
        EnvoyerAlerteObjetJob::dispatch($objet->id, $decouverte->id);

        return $decouverte;
    }

    /** Signale qu'un enfant / une personne vulnérable a été retrouvé(e) et alerte ses contacts autorisés (cahier §6.8/§6.9). */
    public function signalerLocalisation(string $code, array $data, ?string $ip): SignalementBeneficiaire
    {
        $qr = $this->trouverQrActif($code);
        $b = $qr->cible;

        if (! $b instanceof Beneficiaire) {
            throw ApiException::validation("Ce code QR n'est pas associé à un bénéficiaire.");
        }

        $signalement = SignalementBeneficiaire::create([
            'beneficiaire_id' => $b->id,
            'signale_par_nom' => $data['nom'] ?? null,
            'signale_par_telephone' => $data['telephone'] ?? null,
            'message' => $data['message'] ?? null,
            'lieu' => $data['lieu'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'ip' => $ip,
        ]);

        Audit::log('beneficiaire.signalement', $b, ['signalement_id' => $signalement->id], 'securite');
        EnvoyerAlerteBeneficiaireJob::dispatch($b->id, $signalement->id);

        return $signalement;
    }

    private function trouverQrActif(string $code): CodeQr
    {
        $qr = null;
        // Un code invalide ou endommagé est traité comme un code inconnu (cahier §6.6).
        if (preg_match('/^[A-Za-z0-9]{20,40}$/', $code)) {
            $qr = CodeQr::with('cible')->where('code', $code)->first();
        }

        if (! $qr) {
            Audit::log('qr.scan.inconnu', null, ['code_debut' => substr($code, 0, 4)], 'securite');
            throw ApiException::qrInconnu();
        }

        if ($qr->statut !== QrStatut::Actif) {
            Audit::log('qr.scan.refuse', $qr, ['statut' => $qr->statut->value], 'securite');
            throw ApiException::qrIndisponible($qr->statut);
        }

        if (! $qr->cible) {
            throw ApiException::profilIndisponible();
        }

        return $qr;
    }

    // ---------------------------------------------------------------- Bénéficiaire

    private function niveauBeneficiaire(?User $viewer, Beneficiaire $b): string
    {
        if (! $viewer) {
            return 'public';
        }
        if ($b->estProprietaire($viewer) || $viewer->can('medical.voir')) {
            return 'medical';
        }
        if ($viewer->can('beneficiaires.voir_tous')) {
            return 'protege';
        }

        return 'public';
    }

    private function scanBeneficiaire(CodeQr $qr, Beneficiaire $b, ?User $viewer): array
    {
        // Profil non visible : en création, suspendu ou archivé (cahier §7.5).
        if (! in_array($b->statut, [BeneficiaireStatut::Actif, BeneficiaireStatut::Incomplet], true)) {
            throw ApiException::profilIndisponible();
        }

        $b->load(['contacts', 'profilEnfant', 'profilVulnerable']);
        $niveau = $this->niveauBeneficiaire($viewer, $b);

        $identite = [
            'id' => $b->id,
            'type_profil' => $b->type_profil->value,
            'nom' => $b->nom,
            'prenom' => $b->prenom,
            'photo_url' => $b->photoUrl(),
        ];

        if ($niveau === 'public') {
            $data = [
                'beneficiaire' => $identite,
                // Uniquement les contacts que le titulaire a choisi de rendre visibles.
                'contacts' => $b->contacts->where('visible_publiquement', true)
                    ->map(fn (Contact $c) => $this->contactPublic($c))->values(),
            ];
            // Consignes utiles à celui qui porte assistance ; rien d'autre du profil vulnérable.
            if ($b->profilVulnerable?->consignes_assistance) {
                $data['assistance'] = ['consignes_assistance' => $b->profilVulnerable->consignes_assistance];
            }
        } else {
            $data = [
                'beneficiaire' => $identite + [
                    'date_naissance' => $b->date_naissance?->toDateString(),
                    'telephone' => $b->telephone,
                    'email' => $b->email,
                    'adresse' => $b->adresse,
                    'ville' => $b->ville,
                ],
                'contacts' => $b->contacts->map(fn (Contact $c) => $c->toApi())->values(),
                'profil_enfant' => $b->profilEnfant?->toApi(),
                'profil_vulnerable' => $b->profilVulnerable?->toApi(),
            ];
        }

        if ($niveau === 'medical') {
            // null si rien n'est renseigné : on n'invente jamais de valeur (cahier §6.7).
            $data['information_medicale'] = $b->informationMedicale?->toApi();
            Audit::log('medical.consultation', $b, ['via' => 'scan'], 'securite', $viewer);
        }

        Audit::log('qr.scan', $qr, ['niveau_acces' => $niveau, 'type_cible' => 'beneficiaire'], 'info', $viewer);

        return [
            'niveau_acces' => $niveau,
            'type_cible' => 'beneficiaire',
            'profil_incomplet' => $b->statut === BeneficiaireStatut::Incomplet,
        ] + $data;
    }

    private function contactPublic(Contact $c): array
    {
        return [
            'nom' => $c->nom,
            'prenom' => $c->prenom,
            'lien' => $c->lien,
            'telephone' => $c->telephone,
            'preference_contact' => $c->preference_contact,
        ];
    }

    // ---------------------------------------------------------------- Objet

    private function scanObjet(CodeQr $qr, Objet $o, ?User $viewer): array
    {
        if ($o->statut === ObjetStatut::Archive) {
            throw ApiException::profilIndisponible();
        }

        $privilegie = $viewer && ($o->estProprietaire($viewer) || $viewer->can('objets.gerer_tous'));
        $niveau = $privilegie ? 'protege' : 'public';

        // Coordonnées masquées si la confidentialité est active (cahier §6.10).
        $proprietaire = ($privilegie || ! $o->confidentialite_active) ? $o->contactProprietaire() : null;

        Audit::log('qr.scan', $qr, ['niveau_acces' => $niveau, 'type_cible' => 'objet'], 'info', $viewer);

        return [
            'niveau_acces' => $niveau,
            'type_cible' => 'objet',
            'objet' => [
                'id' => $o->id,
                'categorie' => $o->categorie,
                'description' => $o->description,
                'statut' => $o->statut->value,
                'photo_url' => $o->photoUrl(),
            ],
            'proprietaire' => $proprietaire,
            'peut_signaler_decouverte' => true,
        ];
    }
}
