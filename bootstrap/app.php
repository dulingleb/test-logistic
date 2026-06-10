<?php

use App\Exceptions\IdempotencyConflictException;
use Illuminate\Contracts\Cache\LockTimeoutException;
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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(fn (IdempotencyConflictException $e) => response()->json([
            'error' => 'idempotency_conflict',
            'message' => $e->getMessage(),
        ], 409));

        $exceptions->render(fn (LockTimeoutException $e) => response()->json([
            'error' => 'concurrent_request',
            'message' => 'Another request with the same Idempotency-Key is in progress.',
        ], 409));
    })->create();
