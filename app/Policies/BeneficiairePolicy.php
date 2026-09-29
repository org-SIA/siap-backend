<?php

namespace App\Policies;

use App\Models\Beneficiaire;
use App\Models\User;

/** Le personnel (permissions) OU le propriétaire du profil. */
class BeneficiairePolicy
{
    public function create(User $user): bool
    {
        return $user->can('beneficiaires.creer');
    }

    public function view(User $user, Beneficiaire $b): bool
    {
        return $user->can('beneficiaires.voir_tous') || $b->estProprietaire($user);
    }

    public function update(User $user, Beneficiaire $b): bool
    {
        return $user->can('beneficiaires.modifier') || $b->estProprietaire($user);
    }

    public function delete(User $user, Beneficiaire $b): bool
    {
        return $user->can('beneficiaires.archiver') || $b->estProprietaire($user);
    }
}
