<?php

use App\Base\Traits\Response as ResponseTrait;
use App\Exceptions\ApiException;
use App\Http\Middleware\AssignRequestIdMiddleware;
use App\Http\Middleware\AuthenticateMiddleware;
use App\Http\Middleware\EnsureAdminMiddleware;
use App\Http\Middleware\ForceJsonMiddleware;
use App\Http\Middleware\RejectMalformedJsonMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => AuthenticateMiddleware::class,
            'admin' => EnsureAdminMiddleware::class,
        ]);

        // Proxies confiáveis (X-Forwarded-For/Proto): `config/trustedproxy.php`.

        $middleware->prepend([AssignRequestIdMiddleware::class, ForceJsonMiddleware::class]);

        // O GraphQL recebe os argumentos como o cliente mandou: `"   "` não vira `null` antes da validação
        // (viraria um erro de tipo `String!` em vez da mensagem do campo). Trim e vazio ficam nos DTOs.
        $middleware->trimStrings(except: [fn(Request $request) => $request->is('graphql')]);
        $middleware->convertEmptyStringsToNull(except: [fn(Request $request) => $request->is('graphql')]);
        $middleware->api(prepend: [RejectMalformedJsonMiddleware::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Regra de negócio não é erro do sistema: não polui o log.
        $exceptions->dontReport(ApiException::class);
        $exceptions->dontReportDuplicates();

        $exceptions->render(function (Throwable $throwable) {
            return ResponseTrait::returnError($throwable);
        });
    })->create();
