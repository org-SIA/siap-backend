<?php

namespace App\Enums;

enum ContactType: string
{
    case Urgence = 'urgence';
    case ParentTuteur = 'parent_tuteur';
    case ProcheAutorise = 'proche_autorise';
    case Autre = 'autre';
}
