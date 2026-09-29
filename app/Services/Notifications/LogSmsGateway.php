<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Log;

/** Pilote par défaut (dev/pilote) : écrit le SMS dans les logs au lieu de l'envoyer réellement. */
class LogSmsGateway implements SmsGateway
{
    public function envoyer(string $telephone, string $message): bool
    {
        Log::channel(config('siap.notifications_log_channel'))
            ->info('[SMS simulé]', ['telephone' => $telephone, 'message' => $message]);

        return true;
    }
}
