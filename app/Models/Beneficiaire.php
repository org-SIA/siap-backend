<?php

namespace App\Models;

use App\Enums\BeneficiaireStatut;
use App\Enums\ProfilType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\URL;

class Beneficiaire extends Model
{
    use HasUlids;

    protected $table = 'beneficiaires';

    protected $fillable = [
        'user_id', 'created_by', 'type_profil', 'statut', 'nom', 'prenom', 'date_naissance',
        'telephone', 'email', 'adresse', 'ville', 'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'type_profil' => ProfilType::class,
            'statut' => BeneficiaireStatut::class,
            'date_naissance' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class)->orderBy('priorite');
    }

    public function informationMedicale(): HasOne
    {
        return $this->hasOne(InformationMedicale::class);
    }

    public function profilEnfant(): HasOne
    {
        return $this->hasOne(ProfilEnfant::class);
    }

    public function profilVulnerable(): HasOne
    {
        return $this->hasOne(ProfilVulnerable::class);
    }

    public function codesQr(): MorphMany
    {
        return $this->morphMany(CodeQr::class, 'cible');
    }

    public function estProprietaire(?User $user): bool
    {
        return $user !== null && $this->user_id !== null && (int) $this->user_id === (int) $user->id;
    }

    /** URL de photo signée et temporaire : jamais d'URL publique permanente (cahier §8.7). */
    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return URL::temporarySignedRoute(
            'fichiers.photo',
            now()->addMinutes(config('siap.signed_url_minutes')),
            ['type' => 'beneficiaire', 'id' => $this->id],
        );
    }

    /** Représentation complète, réservée au propriétaire et au personnel autorisé. */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type_profil' => $this->type_profil->value,
            'statut' => $this->statut->value,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'date_naissance' => $this->date_naissance?->toDateString(),
            'telephone' => $this->telephone,
            'email' => $this->email,
            'adresse' => $this->adresse,
            'ville' => $this->ville,
            'photo_url' => $this->photoUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
