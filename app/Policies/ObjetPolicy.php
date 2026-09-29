<?php

namespace App\Policies;

use App\Models\Objet;
use App\Models\User;

class ObjetPolicy
{
    public function create(User $user): bool
    {
        return $user->can('objets.creer');
    }

    public function view(User $user, Objet $o): bool
    {
        return $user->can('objets.gerer_tous') || $o->estProprietaire($user);
    }

    public function update(User $user, Objet $o): bool
    {
        return $this->view($user, $o);
    }
}
