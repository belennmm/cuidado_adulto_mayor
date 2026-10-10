<?php

use App\Http\Middleware\EnforceRequestSize;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureApproved;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTokenScope;
use App\Http\Middleware\RejectDisallowedMethods;
use App\Http\Middleware\RequireAccessRule;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ValidateCurrentAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
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
            HandleCors::class,
            App\Http\Middleware\HandleCors::class,
        );

        $middleware->trimStrings(except: ['new_password', 'new_password_confirmation']);
        $middleware->prepend(RejectDisallowedMethods::class);
        $middleware->prepend(EnforceRequestSize::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->api(prepend: [
            SecurityHeaders::class,
        ], append: [ValidateCurrentAccess::class, EnsureTokenScope::class, RequireAccessRule::class]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'role' => EnsureRole::class,
            'approved' => EnsureApproved::class,
        ]);

        // Reject unauthorized roles before resolving resource identifiers.
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            EnsureRole::class,
        );
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            EnsureAdmin::class,
        );
        $middleware->prependToPriorityList(
            EnsureRole::class,
            ValidateCurrentAccess::class,
        );
        $middleware->prependToPriorityList(
            EnsureAdmin::class,
            ValidateCurrentAccess::class,
        );
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            EnsureTokenScope::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Covers HttpResponseException and framework-rendered errors as well.
        $exceptions->respond(function (Response $response) {
            if (request()->is('api/*') && $response->getStatusCode() >= 500) {
                $response->setContent(json_encode(['message' => 'Error interno del servidor.']));
                $response->headers->set('Content-Type', 'application/json');
                $response->headers->remove('Content-Length');
            }

            return $response;
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();

            } elseif ($exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || $exception instanceof HttpResponseException) {
                return null;
            } else {
                $status = 500;
            }

            $message = match ($status) {
                401 => 'No autenticado.',
                403 => 'No tienes permiso para realizar esta accion.',
                404 => 'Recurso no encontrado.',
                405 => 'Metodo HTTP no permitido.',
                default => $status >= 500 ? 'Error interno del servidor.' : 'Solicitud no valida.',
            };

            return response()->json(['message' => $message], $status);
        });
    })->create();
