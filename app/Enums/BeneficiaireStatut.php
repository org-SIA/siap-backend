<?php

namespace App\Enums;

/** États d'un profil bénéficiaire (cahier §7.4.2). */
enum BeneficiaireStatut: string
{
    case EnCreation = 'en_creation';
    case Incomplet = 'incomplet';
    case Actif = 'actif';
    case Suspendu = 'suspendu';
    case Archive = 'archive';
}
