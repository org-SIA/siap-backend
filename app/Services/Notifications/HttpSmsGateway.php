<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pilote générique : envoie le SMS via un webhook HTTP (passerelle Orange/MTN/Nexah/Twilio...).
 * Configurer SIAP_SMS_WEBHOOK_URL et, si besoin, SIAP_SMS_WEBHOOK_TOKEN (Bearer) dans le .env.
 * Adapter le corps de la requête au format exact exigé par le fournisseur choisi.
 */
class HttpSmsGateway implements SmsGateway
{
    public function envoyer(string $telephone, string $message): bool
    {
        $url = config('siap.sms_webhook_url');
        if (! $url) {
            Log::warning('SIAP_SMS_WEBHOOK_URL non configurée : SMS non envoyé.', ['telephone' => $telephone]);

            return false;
        }

        try {
            $token = config('siap.sms_webhook_token');
            $requete = $token ? Http::withToken($token) : Http::withOptions([]);
            $reponse = $requete->timeout(10)->post($url, ['to' => $telephone, 'message' => $message]);

            return $reponse->successful();
        } catch (Throwable $e) {
            Log::error('Échec envoi SMS', ['telephone' => $telephone, 'erreur' => $e->getMessage()]);

            return false;
        }
    }
}
