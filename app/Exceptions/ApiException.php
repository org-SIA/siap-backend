<?php

namespace App\Exceptions;

use App\Enums\QrStatut;
use RuntimeException;

/**
 * Erreur métier rendue au format standard du cahier (§13.3) :
 * { success:false, error:{ code, category, severity, message, timestamp, requestId, details } }
 */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 400,
        public readonly string $severity = 'ERROR-MINOR',
        public readonly mixed $details = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function qrInconnu(): self
    {
        return new self('QR-404', "Ce code QR n'est pas reconnu.", 404, 'ERROR-MINOR');
    }

    public static function qrIndisponible(QrStatut $statut): self
    {
        return new self('QR-410', "Ce code QR est désactivé ou n'est pas encore activé.", 410, 'ERROR-MAJOR', ['statut' => $statut->value]);
    }

    public static function profilIndisponible(): self
    {
        return new self('PROFILE-404', "Ce profil n'est pas disponible.", 404, 'ERROR-MINOR');
    }

    public static function introuvable(string $message = 'Ressource introuvable.'): self
    {
        return new self('RESOURCE-404', $message, 404, 'ERROR-MINOR');
    }

    public static function conflit(string $code, string $message, mixed $details = null): self
    {
        return new self($code, $message, 409, 'ERROR-MINOR', $details);
    }

    public static function validation(string $message, mixed $details = null): self
    {
        return new self('VALIDATION-422', $message, 422, 'ERROR-MINOR', $details);
    }
}
