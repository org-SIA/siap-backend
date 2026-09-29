<?php

namespace App\Enums;

enum ProfilType: string
{
    case Adulte = 'adulte';
    case Enfant = 'enfant';
    case PersonneVulnerable = 'personne_vulnerable';
}
