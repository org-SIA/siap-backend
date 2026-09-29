<?php

namespace App\Support;

use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Enveloppe unique de toutes les réponses de l'API.
 *   succès : { "success": true,  "data": ..., "meta": {...}? }
 *   erreur : { "success": false, "error": { code, category, severity, message, timestamp, requestId, details } }
 */
class ApiResponse
{
    public static function ok(mixed $data = null, ?array $meta = null, int $status = 200): JsonResponse
    {
        $body = ['success' => true, 'data' => $data];
        if ($meta !== null) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    /** Réponse paginée : data = liste, meta.pagination = infos de page. */
    public static function paginated(LengthAwarePaginator $paginator, ?callable $map = null): JsonResponse
    {
        $items = collect($paginator->items());
        if ($map !== null) {
            $items = $items->map($map);
        }

        return self::ok($items->values(), [
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public static function error(
        string $code,
        string $message,
        int $status,
        string $severity = 'ERROR-MINOR',
        mixed $details = null,
        ?Request $request = null,
    ): JsonResponse {
        $requestId = self::requestId($request);

        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'category' => explode('-', $code)[0],
                'severity' => $severity,
                'message' => $message,
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
                'requestId' => $requestId,
                'details' => $details,
            ],
        ], $status)->header('X-Request-Id', $requestId);
    }

    public static function requestId(?Request $request = null): string
    {
        $request ??= request();

        return $request->attributes->get('request_id') ?? 'REQ-'.strtoupper(Str::random(8));
    }

    /** Convertit n'importe quelle exception en réponse d'erreur standard. */
    public static function fromThrowable(Throwable $e, Request $request): JsonResponse
    {
        if ($e instanceof ApiException) {
            return self::error($e->errorCode, $e->getMessage(), $e->status, $e->severity, $e->details, $request);
        }

        if ($e instanceof ValidationException) {
            return self::error('VALIDATION-422', 'Les données envoyées sont invalides.', 422, 'ERROR-MINOR', ['champs' => $e->errors()], $request);
        }

        if ($e instanceof AuthenticationException) {
            return self::error('AUTH-401', 'Authentification requise ou session expirée.', 401, 'ERROR-MAJOR', null, $request);
        }

        if ($e instanceof QueryException) {
            return self::error('DATABASE-500', 'Erreur interne du serveur.', 500, 'CRITICAL', self::debug($e), $request);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            [$code, $message, $severity] = match ($status) {
                401 => ['AUTH-401', 'Authentification requise ou session expirée.', 'ERROR-MAJOR'],
                403 => ['ACCESS-403', 'Accès refusé.', 'ERROR-MAJOR'],
                404 => ['RESOURCE-404', 'Ressource introuvable.', 'ERROR-MINOR'],
                405 => ['ROUTE-405', 'Méthode non autorisée pour cette route.', 'ERROR-MINOR'],
                408 => ['NETWORK-408', 'Délai de communication dépassé.', 'ERROR-MINOR'],
                413 => ['USER-413', 'Fichier ou requête trop volumineux.', 'ERROR-MINOR'],
                429 => ['SECURITY-429', 'Trop de requêtes. Veuillez réessayer dans un instant.', 'WARN'],
                default => ['SERVER-'.$status, 'Erreur du serveur.', $status >= 500 ? 'CRITICAL' : 'ERROR-MINOR'],
            };
            $response = self::error($code, $message, $status, $severity, null, $request);
            foreach ($e->getHeaders() as $name => $value) {
                $response->headers->set($name, $value);
            }

            return $response;
        }

        return self::error('SERVER-500', 'Erreur interne du serveur.', 500, 'CRITICAL', self::debug($e), $request);
    }

    private static function debug(Throwable $e): ?array
    {
        return config('app.debug') ? ['exception' => get_class($e), 'message' => $e->getMessage()] : null;
    }
}
