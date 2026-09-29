<?php

namespace App\Models;

use App\Enums\ContactType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use HasUlids;

    protected $table = 'contacts';

    protected $fillable = [
        'beneficiaire_id', 'type', 'nom', 'prenom', 'telephone', 'email', 'lien',
        'priorite', 'visible_publiquement', 'preference_contact',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
            'priorite' => 'integer',
            'visible_publiquement' => 'boolean',
        ];
    }

    public function beneficiaire(): BelongsTo
    {
        return $this->belongsTo(Beneficiaire::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'beneficiaire_id' => $this->beneficiaire_id,
            'type' => $this->type->value,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'lien' => $this->lien,
            'priorite' => $this->priorite,
            'visible_publiquement' => $this->visible_publiquement,
            'preference_contact' => $this->preference_contact,
        ];
    }
}
