<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ProfilEnfant extends Model
{
    use HasUlids;

    protected $table = 'profils_enfants';

    protected $fillable = ['beneficiaire_id', 'ecole', 'signes_distinctifs', 'informations_reunification'];

    public function toApi(): array
    {
        return [
            'ecole' => $this->ecole,
            'signes_distinctifs' => $this->signes_distinctifs,
            'informations_reunification' => $this->informations_reunification,
        ];
    }
}
