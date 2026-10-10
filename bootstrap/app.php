<?php

use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\RequireRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Library\Domain\Exceptions\LibraryBusinessException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'jwt.auth' => AuthenticateJwt::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (LibraryBusinessException $e, Request $request) {
            return response()->json([
                'code' => $e->errorCode(),
                'message' => $e->getMessage(),
                'details' => $e->details(),
            ], $e->httpStatus());
        });

        $exceptions->render(function (LaravelValidationException $e, Request $request) {
            $details = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $msg) {
                    $details[] = [
                        'field' => $field,
                        'issue' => $msg,
                    ];
                }
            }

            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Error de validación en la solicitud.',
                'details' => $details,
            ], 422);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'code' => 'NOT_FOUND',
                    'message' => 'Recurso no encontrado.',
                    'details' => [],
                ], 404);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'code' => 'FORBIDDEN_ROLE',
                    'message' => 'No tiene permisos para realizar esta acción.',
                    'details' => [],
                ], 403);
            }
        });
    })->create();
