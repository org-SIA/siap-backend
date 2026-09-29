<?php

namespace App\Services\Notifications;

interface SmsGateway
{
    public function envoyer(string $telephone, string $message): bool;
}
