<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StructurePartenaire extends Model
{
    protected $table = 'structures_partenaires';

    protected $fillable = ['nom', 'type', 'telephone', 'email', 'adresse', 'ville', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'type' => $this->type,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'adresse' => $this->adresse,
            'ville' => $this->ville,
            'actif' => $this->actif,
        ];
    }
}
