<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Données médicales : chiffrées en base (cast "encrypted", clé = APP_KEY).
 * ⚠ Perdre l'APP_KEY = perdre ces données. Sauvegardez-la.
 */
class InformationMedicale extends Model
{
    use HasUlids;

    protected $table = 'informations_medicales';

    protected $fillable = [
        'beneficiaire_id', 'groupe_sanguin', 'allergies', 'traitements', 'informations_essentielles', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'groupe_sanguin' => 'encrypted',
            'allergies' => 'encrypted',
            'traitements' => 'encrypted',
            'informations_essentielles' => 'encrypted',
        ];
    }

    public function beneficiaire(): BelongsTo
    {
        return $this->belongsTo(Beneficiaire::class);
    }

    public function toApi(): array
    {
        return [
            'beneficiaire_id' => $this->beneficiaire_id,
            'groupe_sanguin' => $this->groupe_sanguin,
            'allergies' => $this->allergies,
            'traitements' => $this->traitements,
            'informations_essentielles' => $this->informations_essentielles,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
