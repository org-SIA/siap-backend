<?php

namespace App\Services;

use App\Enums\ContactType;
use App\Mail\AlerteMail;
use App\Models\Beneficiaire;
use App\Models\DecouverteObjet;
use App\Models\Objet;
use App\Models\SignalementBeneficiaire;
use App\Services\Notifications\PushGateway;
use App\Services\Notifications\SmsGateway;
use App\Support\Audit;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Met en relation les personnes concernées quand un enfant / une personne vulnérable / un objet
 * est retrouvé (cahier §6.8, §6.9, §6.10). Envoie SMS, e-mail et/ou push selon ce qui est disponible ;
 * n'échoue jamais bruyamment : un canal en panne ne doit pas bloquer le signalement lui-même.
 */
class AlerteService
{
    public function __construct(
        private readonly SmsGateway $sms,
        private readonly PushGateway $push,
    ) {
    }

    public function alerterPourBeneficiaire(Beneficiaire $b, SignalementBeneficiaire $signalement): void
    {
        $b->loadMissing(['contacts', 'user']);
        $message = $this->messageBeneficiaire($b, $signalement);

        $contacts = $b->contacts->whereIn('type', [ContactType::ParentTuteur, ContactType::ProcheAutorise, ContactType::Urgence]);
        $envoyes = 0;
        foreach ($contacts as $contact) {
            if (in_array($contact->preference_contact, ['sms', 'les_deux'], true) && $contact->telephone) {
                $envoyes += (int) $this->tenter(fn () => $this->sms->envoyer($contact->telephone, $message));
            }
            if ($contact->email) {
                $envoyes += (int) $this->tenter(fn () => Mail::to($contact->email)->queue(new AlerteMail('SIAP — Personne retrouvée', $message)));
            }
        }

        if ($b->user?->push_token) {
            $envoyes += (int) $this->tenter(fn () => $this->push->envoyer($b->user->push_token, 'Personne retrouvée', $message, ['beneficiaire_id' => $b->id]));
        }

        $signalement->update(['contacts_alertes' => $envoyes > 0]);
        Audit::log('alerte.beneficiaire', $b, ['canaux_reussis' => $envoyes]);
    }

    public function alerterPourObjet(Objet $o, DecouverteObjet $decouverte): void
    {
        $o->loadMissing('user');
        $proprietaire = $o->contactProprietaire(); // accès interne complet, indépendant de la confidentialité affichée au public
        $message = $this->messageObjet($o, $decouverte);
        $envoyes = 0;

        if (! empty($proprietaire['telephone'])) {
            $envoyes += (int) $this->tenter(fn () => $this->sms->envoyer($proprietaire['telephone'], $message));
        }
        if (! empty($proprietaire['email'])) {
            $envoyes += (int) $this->tenter(fn () => Mail::to($proprietaire['email'])->queue(new AlerteMail('SIAP — Objet retrouvé', $message)));
        }
        if ($o->user?->push_token) {
            $envoyes += (int) $this->tenter(fn () => $this->push->envoyer($o->user->push_token, 'Objet retrouvé', $message, ['objet_id' => $o->id]));
        }

        Audit::log('alerte.objet', $o, ['canaux_reussis' => $envoyes]);
    }

    private function tenter(callable $fn): bool
    {
        try {
            return (bool) $fn();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    private function messageBeneficiaire(Beneficiaire $b, SignalementBeneficiaire $s): string
    {
        $lieu = $s->lieu ? " près de {$s->lieu}" : '';

        return "SIAP : {$b->prenom} {$b->nom} a été retrouvé(e){$lieu}. Contact du signaleur : ".($s->signale_par_telephone ?: 'non communiqué').'.';
    }

    private function messageObjet(Objet $o, DecouverteObjet $d): string
    {
        $lieu = $d->lieu ? " ({$d->lieu})" : '';

        return "SIAP : votre objet « {$o->categorie} »{$lieu} a été retrouvé. Contact : ".($d->signale_par_telephone ?: 'non communiqué').'.';
    }
}
