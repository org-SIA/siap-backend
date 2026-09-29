<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ProfilVulnerable extends Model
{
    use HasUlids;

    protected $table = 'profils_vulnerables';

    protected $fillable = ['beneficiaire_id', 'situation', 'consignes_assistance', 'precautions'];

    public function toApi(): array
    {
        return [
            'situation' => $this->situation,
            'consignes_assistance' => $this->consignes_assistance,
            'precautions' => $this->precautions,
        ];
    }
}
