<?php

namespace App\Models;

use App\Enums\QrStatut;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class CodeQr extends Model
{
    use HasUlids;

    protected $table = 'codes_qr';

    protected $fillable = [
        'code', 'statut', 'cible_type', 'cible_id', 'remplace_par_id', 'created_by', 'active_le', 'desactive_le',
    ];

    protected function casts(): array
    {
        return [
            'statut' => QrStatut::class,
            'active_le' => 'datetime',
            'desactive_le' => 'datetime',
        ];
    }

    public function cible(): MorphTo
    {
        return $this->morphTo();
    }

    /** Jeton aléatoire de 24 caractères alphanumériques (~143 bits) : impossible à deviner. */
    public static function genererCode(): string
    {
        return Str::random(24);
    }

    /** Contenu encodé dans le QR imprimé : l'URL de la page de scan du frontend. */
    public function url(): string
    {
        return rtrim(config('siap.scan_url'), '/').'/'.$this->code;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'url' => $this->url(),
            'statut' => $this->statut->value,
            'cible_type' => $this->cible_type,
            'cible_id' => $this->cible_id,
            'remplace_par_id' => $this->remplace_par_id,
            'active_le' => $this->active_le?->toIso8601String(),
            'desactive_le' => $this->desactive_le?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
