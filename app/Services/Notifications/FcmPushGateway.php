<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Firebase Cloud Messaging (API historique par clé serveur). Configurer SIAP_FCM_SERVER_KEY. */
class FcmPushGateway implements PushGateway
{
    public function envoyer(string $jeton, string $titre, string $message, array $donnees = []): bool
    {
        $cle = config('siap.fcm_server_key');
        if (! $cle) {
            Log::warning('SIAP_FCM_SERVER_KEY non configurée : notification push non envoyée.');

            return false;
        }

        try {
            $reponse = Http::withHeaders(['Authorization' => 'key='.$cle])
                ->timeout(10)
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $jeton,
                    'notification' => ['title' => $titre, 'body' => $message],
                    'data' => $donnees,
                ]);

            return $reponse->successful();
        } catch (Throwable $e) {
            Log::error('Échec envoi push', ['erreur' => $e->getMessage()]);

            return false;
        }
    }
}
