<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Attribue un identifiant unique (REQ-XXXXXXXX) à chaque requête : réponse, erreurs, logs, journal. */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = 'REQ-'.strtoupper(Str::random(8));
        $request->attributes->set('request_id', $id);
        Log::withContext(['request_id' => $id]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
