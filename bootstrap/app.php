<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AssignRequestId;
use App\Support\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Un identifiant de requête (REQ-XXXXXXXX) sur toutes les réponses, succès comme erreurs.
        $middleware->prepend(AssignRequestId::class);

        // Derrière un proxy/tunnel HTTPS (Cloudflare, Railway, Nginx...) : URLs générées en https correct.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([ApiException::class]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        // Toute erreur de l'API est rendue au format standard du cahier (§13.3).
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::fromThrowable($e, $request);
            }

            return null;
        });
    })->create();
