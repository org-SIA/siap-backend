<?php

namespace App\Support;

use App\Models\JournalActivite;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/** Écrit dans le journal d'activité (cahier §5.12 / §9.6). Ne doit jamais casser une requête. */
class Audit
{
    /**
     * @param  string  $niveau  'info' ou 'securite'
     * @param  array  $props  Ne jamais y mettre de données médicales ni de mot de passe.
     */
    public static function log(string $action, ?Model $subject = null, array $props = [], string $niveau = 'info', ?User $user = null): void
    {
        try {
            $request = request();
            $user ??= $request->user('sanctum');

            JournalActivite::create([
                'user_id' => $user?->id,
                'action' => $action,
                'sujet_type' => $subject?->getMorphClass(),
                'sujet_id' => $subject ? (string) $subject->getKey() : null,
                'niveau' => $niveau,
                'request_id' => $request->attributes->get('request_id'),
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 250),
                'proprietes' => $props ?: null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
