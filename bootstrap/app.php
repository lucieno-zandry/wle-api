<?php

use App\Http\Middleware\AuthTokenFromCookie;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SyncPreferences;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->append([
            HandleCors::class,
            AuthTokenFromCookie::class,
            SetLocale::class,
            SyncPreferences::class,
        ]);

        $middleware->alias([
            'custom.sanctum' => \App\Http\Middleware\CustomSanctumAuth::class,
            'verified'       => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'approved'       => \App\Http\Middleware\EnsureUserIsApproved::class,
            'not-blocked'    => \App\Http\Middleware\EnsureUserIsNotBlocked::class,
            'not-suspended'  => \App\Http\Middleware\EnsureUserIsNotSuspended::class,
            'not-in-maintenance-mode' => \App\Http\Middleware\EnsureAppIsNotInMaintenanceMode::class,
            'auto-authenticate-guest' => \App\Http\Middleware\AutoAuthenticateGuest::class,
        ]);

        $middleware->group('api.auth', [
            'custom.sanctum',
            'not-in-maintenance-mode',
            'verified',
            'not-blocked',
            'not-suspended',
        ]);

        $middleware->group('api.auth.approved', [
            'custom.sanctum',
            'not-in-maintenance-mode',
            'verified',
            'not-blocked',
            'not-suspended',
            'approved',
        ]);

        $middleware->group('guest.auth', [
            'not-in-maintenance-mode',
            'auto-authenticate-guest',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
