<?php

namespace App\Enums;

/** États d'un objet (cahier §7.4.3). */
enum ObjetStatut: string
{
    case Enregistre = 'enregistre';
    case Associe = 'associe';
    case DeclarePerdu = 'declare_perdu';
    case Retrouve = 'retrouve';
    case EnCoursRestitution = 'en_cours_restitution';
    case Restitue = 'restitue';
    case Archive = 'archive';

    public function peutPasserA(self $cible): bool
    {
        $autorises = match ($this) {
            self::Enregistre => [self::Associe, self::DeclarePerdu, self::Archive],
            self::Associe => [self::DeclarePerdu, self::Archive],
            self::DeclarePerdu => [self::Associe, self::Retrouve, self::Archive],
            self::Retrouve => [self::EnCoursRestitution, self::DeclarePerdu, self::Associe, self::Archive],
            self::EnCoursRestitution => [self::Restitue, self::Retrouve],
            self::Restitue => [self::Associe, self::Archive],
            self::Archive => [],
        };

        return in_array($cible, $autorises, true);
    }
}
