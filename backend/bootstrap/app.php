<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Keep CORS global so it also handles OPTIONS requests before routing,
        // but use the application's strict origin validation exclusively.
        $middleware->replace(
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\HandleCors::class,
        );

        $middleware->prepend(\App\Http\Middleware\RejectDisallowedMethods::class);
        $middleware->prepend(\App\Http\Middleware\EnforceRequestSize::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->api(prepend: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);

        // Reject unauthorized roles before resolving resource identifiers.
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\EnsureRole::class,
        );
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\EnsureAdmin::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $exception, \Illuminate\Http\Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();

                if (! in_array($status, [404, 405], true) && $status < 500) {
                    return null;
                }
            } elseif ($exception instanceof \Illuminate\Validation\ValidationException
                || $exception instanceof \Illuminate\Auth\AuthenticationException
                || $exception instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                return null;
            } else {
                $status = 500;
            }

            $message = match ($status) {
                404 => 'Recurso no encontrado.',
                405 => 'Metodo HTTP no permitido.',
                default => $status >= 500 ? 'Error interno del servidor.' : ($exception->getMessage() ?: 'Solicitud no valida.'),
            };

            return response()->json(['message' => $message], $status);
        });
    })->create();
