<?php

namespace App\Services\Notifications;

interface PushGateway
{
    public function envoyer(string $jeton, string $titre, string $message, array $donnees = []): bool;
}
