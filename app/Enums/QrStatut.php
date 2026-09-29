<?php

namespace App\Enums;

/** Cycle de vie d'un code QR (cahier §7.4.1). */
enum QrStatut: string
{
    case Cree = 'cree';
    case Associe = 'associe';
    case Actif = 'actif';
    case Suspendu = 'suspendu';
    case Desactive = 'desactive';
    case Remplace = 'remplace';

    public function peutPasserA(self $cible): bool
    {
        $autorises = match ($this) {
            self::Cree => [self::Associe, self::Desactive],
            self::Associe => [self::Associe, self::Actif, self::Desactive],
            self::Actif => [self::Suspendu, self::Desactive, self::Remplace],
            self::Suspendu => [self::Actif, self::Desactive, self::Remplace],
            self::Desactive, self::Remplace => [],
        };

        return in_array($cible, $autorises, true);
    }
}
