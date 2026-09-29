<?php

namespace App\Models;

use App\Enums\ObjetStatut;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\URL;

class Objet extends Model
{
    use HasUlids;

    protected $table = 'objets';

    protected $fillable = [
        'user_id', 'created_by', 'categorie', 'description', 'photo_path', 'statut',
        'confidentialite_active', 'proprietaire_nom', 'proprietaire_telephone', 'proprietaire_email',
    ];

    protected function casts(): array
    {
        return [
            'statut' => ObjetStatut::class,
            'confidentialite_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decouvertes(): HasMany
    {
        return $this->hasMany(DecouverteObjet::class)->latest();
    }

    public function codesQr(): MorphMany
    {
        return $this->morphMany(CodeQr::class, 'cible');
    }

    public function estProprietaire(?User $user): bool
    {
        return $user !== null && $this->user_id !== null && (int) $this->user_id === (int) $user->id;
    }

    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return URL::temporarySignedRoute(
            'fichiers.photo',
            now()->addMinutes(config('siap.signed_url_minutes')),
            ['type' => 'objet', 'id' => $this->id],
        );
    }

    /** Coordonnées du propriétaire (compte lié en priorité, sinon champs saisis par l'agent). */
    public function contactProprietaire(): ?array
    {
        if ($this->user) {
            return ['nom' => $this->user->name, 'telephone' => $this->user->telephone, 'email' => $this->user->email];
        }
        if ($this->proprietaire_nom || $this->proprietaire_telephone || $this->proprietaire_email) {
            return ['nom' => $this->proprietaire_nom, 'telephone' => $this->proprietaire_telephone, 'email' => $this->proprietaire_email];
        }

        return null;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'categorie' => $this->categorie,
            'description' => $this->description,
            'statut' => $this->statut->value,
            'confidentialite_active' => $this->confidentialite_active,
            'proprietaire_nom' => $this->proprietaire_nom,
            'proprietaire_telephone' => $this->proprietaire_telephone,
            'proprietaire_email' => $this->proprietaire_email,
            'photo_url' => $this->photoUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
