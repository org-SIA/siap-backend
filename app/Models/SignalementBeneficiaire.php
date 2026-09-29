<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalementBeneficiaire extends Model
{
    use HasUlids;

    protected $table = 'signalements_beneficiaires';

    protected $fillable = [
        'beneficiaire_id', 'signale_par_nom', 'signale_par_telephone', 'message',
        'lieu', 'latitude', 'longitude', 'contacts_alertes', 'ip',
    ];

    protected function casts(): array
    {
        return ['contacts_alertes' => 'boolean'];
    }

    public function beneficiaire(): BelongsTo
    {
        return $this->belongsTo(Beneficiaire::class);
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
            'contacts_alertes' => $this->contacts_alertes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
