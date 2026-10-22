<?php

use App\Http\Middleware\EnforceModulePermissions;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['roles' => \App\Http\Middleware\RequireRole::class]);
        $middleware->web(append: [
            EnsureUserIsActive::class,
            EnforceModulePermissions::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            $status = $response->getStatusCode();

            // An expired page (a form left open while the session ended) goes back with a plain message, not a raw error.
            if ($status === 419) {
                $message = 'Your page expired. Please try again.';

                return redirect()->back()->withInput($request->except('password', 'password_confirmation', 'current_password'))->withErrors(['login' => $message])->with('error', $message);
            }

            $friendly = in_array($status, [403, 404], true) || (in_array($status, [500, 503], true) && ! config('app.debug'));
            if ($friendly && ! $request->expectsJson() && ! $request->is('files/*', 'payment-proofs/*', '*.pdf', 'export/*', 'pdf/*')) {
                $message = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e->getMessage() : '';

                return \Inertia\Inertia::render('error', ['status' => $status, 'message' => $message])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
