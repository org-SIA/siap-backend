<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal en écriture seule : les modifications et suppressions sont bloquées (cahier §5.12). */
class JournalActivite extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'journal_activites';

    protected $fillable = [
        'user_id', 'action', 'sujet_type', 'sujet_id', 'niveau', 'request_id', 'ip', 'user_agent', 'proprietes',
    ];

    protected function casts(): array
    {
        return ['proprietes' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'action' => $this->action,
            'sujet_type' => $this->sujet_type,
            'sujet_id' => $this->sujet_id,
            'niveau' => $this->niveau,
            'request_id' => $this->request_id,
            'ip' => $this->ip,
            'proprietes' => $this->proprietes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
