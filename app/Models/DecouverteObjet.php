<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class DecouverteObjet extends Model
{
    use HasUlids;

    protected $table = 'decouvertes_objets';

    protected $fillable = [
        'objet_id', 'signale_par_nom', 'signale_par_telephone', 'message', 'lieu',
        'latitude', 'longitude', 'traitee', 'ip',
    ];

    protected function casts(): array
    {
        return ['traitee' => 'boolean'];
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'signale_par_nom' => $this->signale_par_nom,
            'signale_par_telephone' => $this->signale_par_telephone,
            'message' => $this->message,
            'lieu' => $this->lieu,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'traitee' => $this->traitee,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
