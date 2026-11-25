<?php

namespace App\Http\Middleware;

use App\Base\Traits\Response;
use App\Queries\Auth\FindUserByAccessTokenQuery;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Throwable;

/**
 * Sessão opcional do `/graphql`: sem `Authorization`, segue anônimo (as consultas públicas funcionam e os
 * campos que exigem sessão respondem `UNAUTHENTICATED`). Com um token, ele **precisa** ser válido:
 * inválido ou vencido → 401 no envelope, igual ao REST, para o web renovar a sessão e repetir (seguir
 * anônimo esconderia o vencimento e o usuário veria a tela "deslogada").
 */
class OptionalAuthenticateMiddleware {

    use Response;

    public function __construct(
        private readonly FindUserByAccessTokenQuery $findUserByAccessToken
    ) {
    }

    public function handle(Request $request, Closure $next): mixed {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return $next($request);
        }

        try {
            $user = $this->findUserByAccessToken->get($token);

            if (!$user) {
                return self::notAuthorizeExceptionResponse();
            }

            Auth::setUser($user);
            Context::add('user_id', $user->id);

            return $next($request);
        } catch (Throwable $exception) {
            return self::returnError($exception);
        }
    }
}
