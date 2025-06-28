<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\VerifyToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ForceJsonResponse::class);
        $middleware->alias([
            'verify.token' => VerifyToken::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return response()->json([
                'code' => 201,
                'message' => 'Resource not found'
            ], 404);
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'code' => 201,
                'message' => 'Unauthorized'
            ], 401);
        });
        $exceptions->render(function (Throwable $e, Request $request) {
            return response()->json([
                'code' => 201,
                'message' => $e->getMessage()
            ], 500);
        });
    })->create();
