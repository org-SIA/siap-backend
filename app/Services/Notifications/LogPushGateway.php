<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Log;

class LogPushGateway implements PushGateway
{
    public function envoyer(string $jeton, string $titre, string $message, array $donnees = []): bool
    {
        Log::channel(config('siap.notifications_log_channel'))
            ->info('[Push simulé]', compact('jeton', 'titre', 'message', 'donnees'));

        return true;
    }
}
